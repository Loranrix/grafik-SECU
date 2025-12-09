<?php
/**
 * GRAFIK - Gestion des pointages
 */

require_once __DIR__ . '/../includes/config.php';
require_once __DIR__ . '/../classes/Database.php';
require_once __DIR__ . '/../classes/Admin.php';
require_once __DIR__ . '/../classes/Employee.php';
require_once __DIR__ . '/../classes/Firebase.php';
require_once __DIR__ . '/../classes/Punch.php';

include 'header.php';

$employeeModel = new Employee();
$punchModel = new Punch();
$message = '';
$error = '';

// Période sélectionnée (jour, semaine, mois, range)
$view_period = isset($_GET['period']) ? $_GET['period'] : 'day';
$selected_date = isset($_GET['date']) ? $_GET['date'] : date('Y-m-d');

// Dates personnalisées (du... au...)
$start_date = isset($_GET['start_date']) ? $_GET['start_date'] : null;
$end_date = isset($_GET['end_date']) ? $_GET['end_date'] : null;

// Calculer les dates de début et fin selon la période
if ($view_period === 'range' && $start_date && $end_date) {
    // Plage personnalisée : utiliser les dates fournies
    // Pas besoin de modifier
} elseif ($view_period === 'week') {
    // Semaine : du lundi au dimanche de la semaine sélectionnée
    $day_of_week = date('N', strtotime($selected_date)); // 1 (lundi) à 7 (dimanche)
    $start_date = date('Y-m-d', strtotime($selected_date . ' -' . ($day_of_week - 1) . ' days'));
    $end_date = date('Y-m-d', strtotime($start_date . ' +6 days'));
} elseif ($view_period === 'month') {
    // Mois : du 1er au dernier jour du mois sélectionné
    $start_date = date('Y-m-01', strtotime($selected_date));
    $end_date = date('Y-m-t', strtotime($selected_date));
} else {
    // Jour : par défaut
    $start_date = $selected_date;
    $end_date = $selected_date;
}

// Traiter les actions
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $action = $_POST['action'] ?? '';
    
    if ($action === 'add_punch') {
        $employee_id = $_POST['employee_id'] ?? '';
        $punch_type = $_POST['punch_type'] ?? '';
        $punch_date = $_POST['punch_date'] ?? '';
        $punch_time = $_POST['punch_time'] ?? '';
        $punch_datetime = $punch_date . ' ' . $punch_time;
        
        if (empty($employee_id) || empty($punch_type) || empty($punch_date) || empty($punch_time)) {
            $error = 'Veuillez remplir tous les champs';
        } else {
            // Convertir employee_id en string si c'est un nombre
            $employee_id = (string)$employee_id;
            
            // Récupérer l'employé pour afficher son nom dans les messages
            $employee = $employeeModel->getById($employee_id);
            $employee_name = ($employee['first_name'] ?? '') . ' ' . ($employee['last_name'] ?? '');
            if (empty(trim($employee_name))) {
                $employee_name = "ID: $employee_id";
            }
            
            // Vérifications avant l'ajout
            $warnings = [];
            $punches_today = $punchModel->getByEmployeeAndDate($employee_id, $punch_date);
            
            // Vérifier les doublons le même jour (BLOQUANT)
            $duplicate_punch = null;
            foreach ($punches_today as $p) {
                if ($p['punch_type'] === $punch_type) {
                    $duplicate_punch = $p;
                    break;
                }
            }
            
            if ($duplicate_punch) {
                $type_label = $punch_type === 'in' ? 'arrivée' : 'départ';
                $existing_time = date('H:i', strtotime($duplicate_punch['punch_datetime']));
                $existing_date = date('d/m/Y', strtotime($duplicate_punch['punch_datetime']));
                $new_time = date('H:i', strtotime($punch_datetime));
                $new_date = date('d/m/Y', strtotime($punch_datetime));
                
                $error = "❌ DOUBLON DÉTECTÉ - Impossible d'ajouter ce pointage" . PHP_EOL . PHP_EOL;
                $error .= "Employé : " . trim($employee_name) . PHP_EOL;
                $error .= "Type : {$type_label}" . PHP_EOL;
                $error .= "Date demandée : {$new_date} à {$new_time}" . PHP_EOL . PHP_EOL;
                $error .= "⚠️ Il existe déjà une {$type_label} pour cet employé :" . PHP_EOL;
                $error .= "   - Date : {$existing_date}" . PHP_EOL;
                $error .= "   - Heure : {$existing_time}" . PHP_EOL;
                $error .= "   - ID du pointage existant : " . ($duplicate_punch['id'] ?? $duplicate_punch['firebase_key'] ?? 'N/A') . PHP_EOL . PHP_EOL;
                $error .= "Action : Supprimez ou modifiez le pointage existant avant d'en ajouter un nouveau.";
            } else {
                // Vérifier les autres anomalies (NON BLOQUANTES mais avec alertes détaillées)
                if ($punch_type === 'in') {
                    // Vérifier s'il y a un départ avant cette arrivée le même jour
                    $has_out_before = false;
                    $last_out = null;
                    foreach ($punches_today as $p) {
                        if ($p['punch_type'] === 'out' && strtotime($p['punch_datetime']) < strtotime($punch_datetime)) {
                            $has_out_before = true;
                            $last_out = $p;
                            break;
                        }
                    }
                    if (!$has_out_before && !empty($punches_today)) {
                        // Il y a des pointages mais pas de départ avant cette arrivée
                        $last_punch = $punches_today[count($punches_today) - 1];
                        $last_time = date('H:i', strtotime($last_punch['punch_datetime']));
                        $last_type = $last_punch['punch_type'] === 'in' ? 'arrivée' : 'départ';
                            $warnings[] = "⚠️ ANOMALIE DÉTECTÉE : Ajout d'une arrivée sans départ précédent le même jour." . PHP_EOL;
                            $warnings[] = "   - Employé : " . trim($employee_name) . PHP_EOL;
                            $warnings[] = "   - Date : " . date('d/m/Y', strtotime($punch_date)) . PHP_EOL;
                            $warnings[] = "   - Nouvelle arrivée : {$punch_time}" . PHP_EOL;
                            $warnings[] = "   - Dernier pointage : {$last_type} à {$last_time}";
                    }
                } elseif ($punch_type === 'out') {
                    // Vérifier s'il y a une arrivée avant ce départ le même jour
                    $has_in_before = false;
                    $last_in = null;
                    foreach ($punches_today as $p) {
                        if ($p['punch_type'] === 'in' && strtotime($p['punch_datetime']) < strtotime($punch_datetime)) {
                            $has_in_before = true;
                            $last_in = $p;
                            break;
                        }
                    }
                    if (!$has_in_before) {
                        if (!empty($punches_today)) {
                            $last_punch = $punches_today[count($punches_today) - 1];
                            $last_time = date('H:i', strtotime($last_punch['punch_datetime']));
                            $last_type = $last_punch['punch_type'] === 'in' ? 'arrivée' : 'départ';
                            $warnings[] = "⚠️ ANOMALIE DÉTECTÉE : Ajout d'un départ sans arrivée précédente le même jour." . PHP_EOL;
                            $warnings[] = "   - Employé : " . trim($employee_name) . PHP_EOL;
                            $warnings[] = "   - Date : " . date('d/m/Y', strtotime($punch_date)) . PHP_EOL;
                            $warnings[] = "   - Nouveau départ : {$punch_time}" . PHP_EOL;
                            $warnings[] = "   - Dernier pointage : {$last_type} à {$last_time}";
                        } else {
                            $warnings[] = "⚠️ ANOMALIE DÉTECTÉE : Ajout d'un départ sans aucune arrivée le même jour." . PHP_EOL;
                            $warnings[] = "   - Employé : " . trim($employee_name) . PHP_EOL;
                            $warnings[] = "   - Date : " . date('d/m/Y', strtotime($punch_date)) . PHP_EOL;
                            $warnings[] = "   - Nouveau départ : {$punch_time}" . PHP_EOL;
                            $warnings[] = "   - Aucun pointage précédent ce jour";
                        }
                    }
                }
                
                // Ajouter le pointage (les warnings sont affichés mais n'empêchent pas l'ajout)
                try {
                    $result = $punchModel->addManual($employee_id, $punch_type, $punch_datetime);
                    if ($result) {
                        if (!empty($warnings)) {
                            $message = '✅ Pointage ajouté avec succès.' . PHP_EOL . PHP_EOL . implode(PHP_EOL, $warnings);
                        } else {
                            $message = '✅ Pointage ajouté avec succès';
                        }
                    } else {
                        $error = 'Erreur lors de l\'ajout du pointage';
                    }
                } catch (Exception $e) {
                    $error_msg = $e->getMessage();
                    // Traduire les messages d'erreur en français si nécessaire
                    if (strpos($error_msg, 'Nevar reģistrēt') !== false) {
                        if (strpos($error_msg, 'ierašanās') !== false) {
                            $error_msg = "Impossible d'enregistrer deux fois une arrivée le même jour. Veuillez enregistrer un départ.";
                        } elseif (strpos($error_msg, 'aiziešana') !== false) {
                            $error_msg = "Impossible d'enregistrer deux fois un départ le même jour. Veuillez enregistrer une arrivée.";
                        } else {
                            $error_msg = "Impossible d'enregistrer deux fois le même type de pointage le même jour.";
                        }
                    }
                    $error = 'Erreur : ' . htmlspecialchars($error_msg);
                    error_log("Erreur ajout pointage admin (employee_id: $employee_id): " . $e->getMessage());
                }
            }
        }
    } elseif ($action === 'update_punch') {
        $punch_id = $_POST['punch_id'] ?? '';
        $punch_date = $_POST['punch_date'] ?? '';
        $punch_time = $_POST['punch_time'] ?? '';
        $punch_datetime = $punch_date . ' ' . $punch_time;
        $boxes_count = isset($_POST['boxes_count']) && $_POST['boxes_count'] !== '' ? intval($_POST['boxes_count']) : null;
        $employee_id = $_POST['employee_id'] ?? '';
        
        if (empty($punch_id) || empty($punch_datetime)) {
            $error = 'Données manquantes pour la modification';
        } else {
            $success = false;
            require_once __DIR__ . '/../classes/Firebase.php';
            $firebase = Firebase::getInstance();
            
            // Si on a l'employee_id, modifier directement
            if (!empty($employee_id)) {
                try {
                    $ref = $firebase->getDatabase()->getReference('grafik/punches/' . $employee_id . '/' . $punch_id);
                    $existing = $ref->getValue();
                    
                    if ($existing !== null) {
                        $datetime_formatted = str_replace(' ', 'T', $punch_datetime);
                        $update_data = [
                            'datetime' => $datetime_formatted,
                            'punch_datetime' => $punch_datetime
                        ];
                        
                        if ($boxes_count !== null) {
                            $update_data['boxes_count'] = $boxes_count;
                        }
                        
                        $ref->update($update_data);
                        $success = true;
                        $message = 'Pointage modifié avec succès';
                    }
                } catch (Exception $e) {
                    error_log("Erreur modification directe: " . $e->getMessage());
                }
            }
            
            // Si pas de succès, utiliser la méthode update()
            if (!$success) {
                $result = $punchModel->update($punch_id, $punch_datetime, $boxes_count);
                if ($result) {
                    $message = 'Pointage modifié avec succès';
                } else {
                    $error = 'Erreur lors de la modification du pointage';
                }
            }
        }
    } elseif ($action === 'delete_punch') {
        $punch_id = $_POST['punch_id'] ?? '';
        $employee_id = $_POST['employee_id'] ?? '';
        
        if (empty($punch_id)) {
            $error = 'ID du pointage manquant';
        } else {
            $success = false;
            require_once __DIR__ . '/../classes/Firebase.php';
            $firebase = Firebase::getInstance();
            
            // Si on a l'employee_id, supprimer directement (plus rapide et fiable)
            if (!empty($employee_id)) {
                try {
                    $ref = $firebase->getDatabase()->getReference('grafik/punches/' . $employee_id . '/' . $punch_id);
                    $existing = $ref->getValue();
                    if ($existing !== null) {
                        $ref->remove();
                        $success = true;
                        $message = 'Pointage supprimé avec succès';
                    }
                } catch (Exception $e) {
                    error_log("Erreur suppression directe (employee_id: $employee_id, punch_id: $punch_id): " . $e->getMessage());
                }
            }
            
            // Si pas de succès, chercher dans tous les employés
            if (!$success) {
                try {
                    $ref = $firebase->getDatabase()->getReference('grafik/punches');
                    $allPunches = $ref->getValue() ?? [];
                    
                    foreach ($allPunches as $emp_id => $employeePunches) {
                        if (!is_array($employeePunches)) {
                            continue;
                        }
                        
                        // Vérifier si la clé existe directement
                        if (isset($employeePunches[$punch_id])) {
                            $punchRef = $firebase->getDatabase()->getReference('grafik/punches/' . $emp_id . '/' . $punch_id);
                            $punchRef->remove();
                            $success = true;
                            $message = 'Pointage supprimé avec succès';
                            break;
                        }
                    }
                } catch (Exception $e) {
                    error_log("Erreur suppression recherche (punch_id: $punch_id): " . $e->getMessage());
                }
            }
            
            if (!$success) {
                $error = 'Erreur lors de la suppression du pointage. ID: ' . htmlspecialchars($punch_id);
            }
        }
    }
}

$employees = $employeeModel->getAll(true);

// En cas d'erreur, recharger les pointages pour éviter la perte de données
if (!empty($error)) {
    // Ne pas empêcher l'affichage des pointages existants
}

// Récupérer les pointages selon la période
if ($view_period === 'day') {
    $punches = $punchModel->getAllByDate($selected_date);
} else {
    // Pour semaine, mois et plage personnalisée, récupérer jour par jour
    $punches = [];
    $current_date = $start_date;
    while (strtotime($current_date) <= strtotime($end_date)) {
        $day_punches = $punchModel->getAllByDate($current_date);
        $punches = array_merge($punches, $day_punches);
        $current_date = date('Y-m-d', strtotime($current_date . ' +1 day'));
    }
}

// Calculer les heures travaillées par employé pour la période sélectionnée
$hours_by_employee = [];
$paid_hours_by_employee = [];
foreach ($employees as $emp) {
    if ($view_period === 'day') {
        $hours = $punchModel->calculateHours($emp['id'], $selected_date);
        $paid_hours = $punchModel->calculatePaidHours($emp['id'], $selected_date);
    } else {
        $hours = $punchModel->calculateHoursRange($emp['id'], $start_date, $end_date);
        $paid_hours = $punchModel->calculatePaidHoursRange($emp['id'], $start_date, $end_date);
    }
    if ($hours > 0) {
        $hours_by_employee[$emp['id']] = $hours;
        $paid_hours_by_employee[$emp['id']] = $paid_hours;
    }
}
?>

<div class="container">
    <div class="page-header">
        <h1>Pointages</h1>
        <button class="btn btn-success" onclick="openAddPunchModal()">+ Ajouter un pointage</button>
    </div>
    
    <?php if ($message): ?>
    <div class="alert alert-success" style="background: #27ae60; color: white; padding: 15px; border-radius: 8px; margin: 15px 0; white-space: pre-line; font-family: monospace; font-size: 13px;">
        <?= htmlspecialchars($message) ?>
    </div>
    <?php endif; ?>
    
    <?php if ($error): ?>
    <div class="alert alert-error" style="background: #e74c3c; color: white; padding: 15px; border-radius: 8px; margin: 15px 0; font-weight: bold; white-space: pre-line; font-family: monospace; font-size: 13px;">
        <?= htmlspecialchars($error) ?>
    </div>
    <?php endif; ?>
    
    <!-- Sélecteur de période et date -->
    <div class="card">
        <form method="GET" id="filterForm" style="display: flex; gap: 15px; align-items: center; flex-wrap: wrap;">
            <div style="display: flex; gap: 10px; align-items: center;">
                <label for="period" style="font-weight: 600;">Période :</label>
                <select id="period" name="period" style="padding: 10px; border: 2px solid #ddd; border-radius: 8px;" onchange="toggleDateInputs(); document.getElementById('filterForm').submit();">
                    <option value="day" <?= $view_period === 'day' ? 'selected' : '' ?>>Jour</option>
                    <option value="week" <?= $view_period === 'week' ? 'selected' : '' ?>>Semaine</option>
                    <option value="month" <?= $view_period === 'month' ? 'selected' : '' ?>>Mois</option>
                    <option value="range" <?= $view_period === 'range' ? 'selected' : '' ?>>Du... au...</option>
                </select>
            </div>
            <div id="singleDateInput" style="display: <?= $view_period === 'range' ? 'none' : 'flex' ?>; gap: 10px; align-items: center;">
                <label for="date" style="font-weight: 600;">Date :</label>
                <input type="date" id="date" name="date" value="<?= $selected_date ?>" style="padding: 10px; border: 2px solid #ddd; border-radius: 8px;" onchange="document.getElementById('filterForm').submit();">
            </div>
            <div id="rangeDateInput" style="display: <?= $view_period === 'range' ? 'flex' : 'none' ?>; gap: 10px; align-items: center;">
                <label for="start_date" style="font-weight: 600;">Du :</label>
                <input type="date" id="start_date" name="start_date" value="<?= $start_date ?? date('Y-m-d', strtotime('-7 days')) ?>" style="padding: 10px; border: 2px solid #ddd; border-radius: 8px;" onchange="document.getElementById('filterForm').submit();">
                <label for="end_date" style="font-weight: 600;">Au :</label>
                <input type="date" id="end_date" name="end_date" value="<?= $end_date ?? date('Y-m-d') ?>" style="padding: 10px; border: 2px solid #ddd; border-radius: 8px;" onchange="document.getElementById('filterForm').submit();">
            </div>
            <a href="?period=<?= $view_period ?>&date=<?= date('Y-m-d') ?>" class="btn btn-secondary btn-sm">Aujourd'hui</a>
        </form>
        <?php if ($view_period === 'week'): ?>
            <p style="margin-top: 10px; color: #666; font-size: 14px;">
                Semaine du <?= date('d/m/Y', strtotime($start_date)) ?> au <?= date('d/m/Y', strtotime($end_date)) ?>
            </p>
        <?php elseif ($view_period === 'month'): ?>
            <p style="margin-top: 10px; color: #666; font-size: 14px;">
                Mois de <?= date('F Y', strtotime($selected_date)) ?> (du <?= date('d/m/Y', strtotime($start_date)) ?> au <?= date('d/m/Y', strtotime($end_date)) ?>)
            </p>
        <?php elseif ($view_period === 'range'): ?>
            <p style="margin-top: 10px; color: #666; font-size: 14px;">
                Période du <?= date('d/m/Y', strtotime($start_date)) ?> au <?= date('d/m/Y', strtotime($end_date)) ?>
            </p>
        <?php endif; ?>
    </div>
    
    <?php if (count($hours_by_employee) > 0): ?>
    <div class="card">
        <h2>Heures travaillées <?php 
            if ($view_period === 'day'): 
                echo 'le ' . date('d/m/Y', strtotime($selected_date));
            elseif ($view_period === 'week'):
                echo 'du ' . date('d/m/Y', strtotime($start_date)) . ' au ' . date('d/m/Y', strtotime($end_date));
            elseif ($view_period === 'range'):
                echo 'du ' . date('d/m/Y', strtotime($start_date)) . ' au ' . date('d/m/Y', strtotime($end_date));
            else:
                echo 'en ' . date('F Y', strtotime($selected_date));
            endif;
        ?></h2>
        <table class="table">
            <thead>
                <tr>
                    <th>Employé</th>
                    <th>Heures travaillées</th>
                    <th>Heures payées</th>
                </tr>
            </thead>
            <tbody>
                <?php foreach ($hours_by_employee as $emp_id => $hours): 
                    $emp = $employeeModel->getById($emp_id);
                    // Fallback si getById ne fonctionne pas
                    if (!$emp || empty($emp['first_name'])) {
                        foreach ($employees as $e) {
                            if ($e['id'] == $emp_id) {
                                $emp = $e;
                                break;
                            }
                        }
                    }
                    $paid_hours = $paid_hours_by_employee[$emp_id] ?? 0;
                ?>
                <tr>
                    <td><?= htmlspecialchars(($emp['first_name'] ?? '') . ' ' . ($emp['last_name'] ?? '')) ?></td>
                    <td><strong><?= number_format($hours, 2) ?> h</strong></td>
                    <td><strong style="color: #27ae60;"><?= number_format($paid_hours, 2) ?> h</strong></td>
                </tr>
                <?php endforeach; ?>
            </tbody>
        </table>
    </div>
    <?php endif; ?>
    
    <div class="card">
        <h2>Liste des pointages - <?php 
            if ($view_period === 'day'): 
                echo date('d/m/Y', strtotime($selected_date));
            elseif ($view_period === 'week'):
                echo 'Semaine du ' . date('d/m/Y', strtotime($start_date)) . ' au ' . date('d/m/Y', strtotime($end_date));
            elseif ($view_period === 'range'):
                echo 'Du ' . date('d/m/Y', strtotime($start_date)) . ' au ' . date('d/m/Y', strtotime($end_date));
            else:
                echo date('F Y', strtotime($selected_date));
            endif;
        ?></h2>
        
        <?php if (count($punches) > 0): ?>
        <table class="table">
            <thead>
                <tr>
                    <th>Employé</th>
                    <th>Type</th>
                    <th>Heure</th>
                    <th>Boîtes</th>
                    <th>Actions</th>
                </tr>
            </thead>
            <tbody>
                <?php foreach ($punches as $punch): 
                    // S'assurer que les noms sont présents
                    $punch_first_name = $punch['first_name'] ?? '';
                    $punch_last_name = $punch['last_name'] ?? '';
                    $punch_employee_id = $punch['employee_id'] ?? null;
                    
                    // Si les noms sont vides, chercher dans les employés
                    if (empty($punch_first_name) && $punch_employee_id) {
                        // Essayer getById d'abord
                        $emp_info = $employeeModel->getById($punch_employee_id);
                        if ($emp_info && !empty($emp_info['first_name'])) {
                            $punch_first_name = $emp_info['first_name'] ?? '';
                            $punch_last_name = $emp_info['last_name'] ?? '';
                        } else {
                            // Si getById ne fonctionne pas, chercher dans tous les employés
                            foreach ($employees as $e) {
                                $e_id = (string)($e['id'] ?? '');
                                $p_id = (string)($punch_employee_id ?? '');
                                if ($e_id === $p_id || $e['id'] == $punch_employee_id) {
                                    $punch_first_name = $e['first_name'] ?? '';
                                    $punch_last_name = $e['last_name'] ?? '';
                                    break;
                                }
                            }
                        }
                    }
                    
                    // Si toujours pas de nom, afficher l'ID de l'employé (mais pas si c'est 0 ou vide)
                    if (empty($punch_first_name)) {
                        if (!empty($punch_employee_id) && $punch_employee_id !== '0' && $punch_employee_id !== 0) {
                            $punch_first_name = 'Employé #' . htmlspecialchars($punch_employee_id);
                        } else {
                            $punch_first_name = 'Employé inconnu';
                            $punch_last_name = '';
                        }
                    }
                    
                    // Utiliser la clé Firebase comme ID (c'est ce qui est retourné par getAllPunchesByDate)
                    $punch_id = $punch['id'] ?? $punch['firebase_key'] ?? null;
                ?>
                <tr>
                    <td><?= htmlspecialchars(trim($punch_first_name . ' ' . $punch_last_name)) ?></td>
                    <td>
                        <?php if ($punch['punch_type'] === 'in'): ?>
                            <span style="color: #27ae60; font-weight: bold;">✓ Arrivée</span>
                        <?php else: ?>
                            <span style="color: #e74c3c; font-weight: bold;">← Départ</span>
                        <?php endif; ?>
                    </td>
                    <td><?= date('H:i:s', strtotime($punch['punch_datetime'])) ?></td>
                    <td>
                        <?php if (isset($punch['boxes_count']) && $punch['boxes_count'] !== null): ?>
                            <span style="color: #e74c3c; font-weight: bold;"><?= intval($punch['boxes_count']) ?></span>
                        <?php else: ?>
                            <span style="color: #999;">-</span>
                        <?php endif; ?>
                    </td>
                    <td>
                        <button class="btn btn-secondary btn-sm" onclick='editPunch(<?= json_encode($punch) ?>)'>
                            Modifier
                        </button>
                        <form method="POST" style="display: inline;" onsubmit="return confirm('Êtes-vous sûr de vouloir supprimer ce pointage ?');">
                            <input type="hidden" name="action" value="delete_punch">
                            <input type="hidden" name="punch_id" value="<?= htmlspecialchars($punch_id ?? $punch['id'] ?? '') ?>">
                            <input type="hidden" name="employee_id" value="<?= htmlspecialchars($punch_employee_id ?? $punch['employee_id'] ?? '') ?>">
                            <button type="submit" class="btn btn-danger btn-sm">
                                Supprimer
                            </button>
                        </form>
                    </td>
                </tr>
                <?php endforeach; ?>
            </tbody>
        </table>
        <?php else: ?>
        <p style="color: #999; text-align: center; padding: 20px;">Aucun pointage pour cette date</p>
        <?php endif; ?>
    </div>
</div>

<!-- Modal Modifier Pointage -->
<div class="modal" id="editPunchModal">
    <div class="modal-content">
        <div class="modal-header">
            <h2>Modifier un pointage</h2>
            <button class="modal-close" onclick="closeEditPunchModal()">&times;</button>
        </div>
        <form method="POST">
            <input type="hidden" name="action" value="update_punch">
            <input type="hidden" name="punch_id" id="edit_punch_id">
            <input type="hidden" name="employee_id" id="edit_employee_id">
            
            <div class="form-group">
                <label for="edit_punch_date">Date</label>
                <input type="date" id="edit_punch_date" name="punch_date" required>
            </div>
            
            <div class="form-group">
                <label for="edit_punch_time">Heure</label>
                <input type="time" id="edit_punch_time" name="punch_time" step="1" required>
            </div>
            
            <div class="form-group">
                <label for="edit_boxes_count">Nombre de boîtes (optionnel)</label>
                <input type="number" id="edit_boxes_count" name="boxes_count" min="0" placeholder="Laisser vide si non applicable">
            </div>
            
            <button type="submit" class="btn btn-primary">Enregistrer</button>
        </form>
    </div>
</div>

<!-- Modal Ajouter Pointage -->
<div class="modal" id="addPunchModal">
    <div class="modal-content">
        <div class="modal-header">
            <h2>Ajouter un pointage manuel</h2>
            <button class="modal-close" onclick="closeAddPunchModal()">&times;</button>
        </div>
        <form method="POST">
            <input type="hidden" name="action" value="add_punch">
            
            <div class="form-group">
                <label for="employee_id">Employé</label>
                <select id="employee_id" name="employee_id" required style="width: 100%; padding: 12px; border: 2px solid #ddd; border-radius: 8px;">
                    <?php foreach ($employees as $emp): ?>
                    <option value="<?= $emp['id'] ?>">
                        <?= htmlspecialchars($emp['first_name'] . ' ' . $emp['last_name']) ?>
                    </option>
                    <?php endforeach; ?>
                </select>
            </div>
            
            <div class="form-group">
                <label for="punch_type">Type</label>
                <select id="punch_type" name="punch_type" required style="width: 100%; padding: 12px; border: 2px solid #ddd; border-radius: 8px;">
                    <option value="in">Arrivée</option>
                    <option value="out">Départ</option>
                </select>
            </div>
            
            <div class="form-group">
                <label for="punch_date">Date</label>
                <input type="date" id="punch_date" name="punch_date" required>
            </div>
            
            <div class="form-group">
                <label for="punch_time">Heure</label>
                <input type="time" id="punch_time" name="punch_time" step="1" required>
            </div>
            
            <button type="submit" class="btn btn-primary">Enregistrer</button>
        </form>
    </div>
</div>

<script>
function toggleDateInputs() {
    const period = document.getElementById('period').value;
    const singleDateInput = document.getElementById('singleDateInput');
    const rangeDateInput = document.getElementById('rangeDateInput');
    
    if (period === 'range') {
        singleDateInput.style.display = 'none';
        rangeDateInput.style.display = 'flex';
    } else {
        singleDateInput.style.display = 'flex';
        rangeDateInput.style.display = 'none';
    }
}

// Initialiser l'affichage au chargement
document.addEventListener('DOMContentLoaded', function() {
    toggleDateInputs();
});

function openAddPunchModal() {
    document.getElementById('addPunchModal').classList.add('active');
    // Définir la date d'aujourd'hui par défaut
    document.getElementById('punch_date').value = '<?= $selected_date ?>';
    document.getElementById('punch_time').value = new Date().toTimeString().slice(0, 5);
}

function closeAddPunchModal() {
    document.getElementById('addPunchModal').classList.remove('active');
}

function editPunch(punch) {
    // Utiliser l'ID du pointage (clé Firebase)
    document.getElementById('edit_punch_id').value = punch.id || punch.firebase_key || '';
    document.getElementById('edit_employee_id').value = punch.employee_id || '';
    
    // Parser la date/heure du pointage
    const punchDateTime = punch.punch_datetime || punch.datetime;
    let dateStr, timeStr;
    
    if (punchDateTime) {
        // Gérer le format "YYYY-MM-DD HH:MM:SS" ou "YYYY-MM-DDTHH:MM:SS"
        const dateTimeStr = punchDateTime.replace('T', ' ');
        const parts = dateTimeStr.split(' ');
        if (parts.length >= 1) {
            dateStr = parts[0];
        }
        if (parts.length >= 2) {
            timeStr = parts[1].substring(0, 5); // HH:MM
        }
    }
    
    // Fallback si pas de date trouvée
    if (!dateStr) {
        const now = new Date();
        dateStr = now.toISOString().split('T')[0];
        timeStr = now.toTimeString().slice(0, 5);
    }
    
    document.getElementById('edit_punch_date').value = dateStr;
    document.getElementById('edit_punch_time').value = timeStr || '';
    document.getElementById('edit_boxes_count').value = punch.boxes_count || '';
    document.getElementById('editPunchModal').classList.add('active');
}

function closeEditPunchModal() {
    document.getElementById('editPunchModal').classList.remove('active');
}

// Fermer modal en cliquant à l'extérieur
window.onclick = function(event) {
    const addPunchModal = document.getElementById('addPunchModal');
    const editPunchModal = document.getElementById('editPunchModal');
    if (event.target === addPunchModal) {
        closeAddPunchModal();
    }
    if (event.target === editPunchModal) {
        closeEditPunchModal();
    }
}
</script>

<?php include 'footer.php'; ?>

