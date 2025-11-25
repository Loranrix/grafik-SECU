<?php
/**
 * GRAFIK - Gestion des pointages
 */

require_once __DIR__ . '/../includes/config.php';
require_once __DIR__ . '/../classes/Database.php';
require_once __DIR__ . '/../classes/Admin.php';
require_once __DIR__ . '/../classes/Employee.php';
require_once __DIR__ . '/../classes/Punch.php';

include 'header.php';

$employeeModel = new Employee();
$punchModel = new Punch();
$message = '';
$error = '';

// Fonction pour arrondir au 1/4 d'heure supérieur (pour arrivée)
function roundUpQuarter($datetime) {
    $timestamp = strtotime($datetime);
    $minutes = (int)date('i', $timestamp);
    $hours = (int)date('H', $timestamp);
    
    // Arrondir au quart supérieur
    $rounded_minutes = ceil($minutes / 15) * 15;
    if ($rounded_minutes >= 60) {
        $hours++;
        $rounded_minutes = 0;
    }
    
    return date('Y-m-d H:i:s', mktime($hours, $rounded_minutes, 0, date('m', $timestamp), date('d', $timestamp), date('Y', $timestamp)));
}

// Fonction pour arrondir au 1/4 d'heure inférieur (pour départ)
function roundDownQuarter($datetime) {
    $timestamp = strtotime($datetime);
    $minutes = (int)date('i', $timestamp);
    $hours = (int)date('H', $timestamp);
    
    // Arrondir au quart inférieur
    $rounded_minutes = floor($minutes / 15) * 15;
    
    return date('Y-m-d H:i:s', mktime($hours, $rounded_minutes, 0, date('m', $timestamp), date('d', $timestamp), date('Y', $timestamp)));
}

// Période sélectionnée
$period_type = isset($_GET['period']) ? $_GET['period'] : 'day'; // day, week, month
$selected_date = isset($_GET['date']) ? $_GET['date'] : date('Y-m-d');
$selected_employee = isset($_GET['employee']) ? $_GET['employee'] : '';

// Calculer les dates de début et fin selon la période
$start_date = $selected_date;
$end_date = $selected_date;

if ($period_type === 'week') {
    // Semaine : du lundi au dimanche
    $day_of_week = date('w', strtotime($selected_date));
    $monday_offset = ($day_of_week == 0) ? -6 : (1 - $day_of_week);
    $start_date = date('Y-m-d', strtotime($selected_date . ' ' . $monday_offset . ' days'));
    $end_date = date('Y-m-d', strtotime($start_date . ' +6 days'));
} elseif ($period_type === 'month') {
    // Mois : du 1er au dernier jour du mois
    $start_date = date('Y-m-01', strtotime($selected_date));
    $end_date = date('Y-m-t', strtotime($selected_date));
}

// Traiter les actions
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $action = $_POST['action'] ?? '';
    
    if ($action === 'add_punch') {
        $employee_id = trim($_POST['employee_id']); // Garder comme string pour Firebase
        $punch_type = $_POST['punch_type'];
        $punch_date = $_POST['punch_date'];
        $punch_time = $_POST['punch_time'];
        $punch_datetime = $punch_date . ' ' . $punch_time;
        
        try {
            $punchModel->addManual($employee_id, $punch_type, $punch_datetime);
            $message = 'Pointage ajouté avec succès';
        } catch (Exception $e) {
            $error = 'Erreur lors de l\'ajout du pointage : ' . $e->getMessage();
        }
    } elseif ($action === 'update_punch') {
        $punch_id = trim($_POST['punch_id']); // Garder comme string pour Firebase
        $punch_date = $_POST['punch_date'];
        $punch_time = $_POST['punch_time'];
        $punch_datetime = $punch_date . ' ' . $punch_time;
        $boxes_count = isset($_POST['boxes_count']) && $_POST['boxes_count'] !== '' ? intval($_POST['boxes_count']) : null;
        
        $result = $punchModel->update($punch_id, $punch_datetime, $boxes_count);
        if ($result) {
            $message = 'Pointage modifié avec succès';
        } else {
            $error = 'Erreur lors de la modification du pointage';
        }
    } elseif ($action === 'delete_punch') {
        $punch_id = trim($_POST['punch_id']); // Garder comme string pour Firebase
        $result = $punchModel->delete($punch_id);
        if ($result) {
            $message = 'Pointage supprimé avec succès';
        } else {
            $error = 'Erreur lors de la suppression du pointage';
        }
    }
}

$employees = $employeeModel->getAll(true);

// Récupérer les pointages selon la période et l'employé sélectionné
$all_punches_by_employee = [];
foreach ($employees as $emp) {
    if ($selected_employee && $emp['id'] !== $selected_employee) {
        continue;
    }
    $punches = $punchModel->getByEmployeeDateRange($emp['id'], $start_date, $end_date);
    if (!empty($punches)) {
        $all_punches_by_employee[$emp['id']] = [
            'employee' => $emp,
            'punches' => $punches
        ];
    }
}

// Calculer les heures réelles et arrondies par employé
$hours_by_employee = [];
foreach ($all_punches_by_employee as $emp_id => $data) {
    $emp = $data['employee'];
    $punches = $data['punches'];
    
    $real_hours = 0;
    $rounded_hours = 0;
    $in_time = null;
    $in_time_rounded = null;
    
    foreach ($punches as $punch) {
        if ($punch['punch_type'] === 'in') {
            // Si on a déjà un "in" en attente, on l'ignore (pointage orphelin)
            if ($in_time !== null) {
                // On ignore l'ancien "in" et on prend le nouveau
            }
            $in_time = strtotime($punch['punch_datetime']);
            $in_time_rounded = strtotime(roundUpQuarter($punch['punch_datetime']));
        } elseif ($punch['punch_type'] === 'out') {
            if ($in_time !== null) {
                // On a un "in" précédent, on calcule les heures
                $out_time = strtotime($punch['punch_datetime']);
                $out_time_rounded = strtotime(roundDownQuarter($punch['punch_datetime']));
                
                // Heures réelles
                $real_hours += ($out_time - $in_time) / 3600;
                
                // Heures arrondies
                $rounded_hours += ($out_time_rounded - $in_time_rounded) / 3600;
                
                $in_time = null;
                $in_time_rounded = null;
            }
            // Si pas de "in" précédent, on ignore ce "out" (pointage orphelin)
        }
    }
    
    // Afficher même si pas d'heures calculées (pointages orphelins)
    $hours_by_employee[$emp_id] = [
        'employee' => $emp,
        'real_hours' => round($real_hours, 2),
        'rounded_hours' => round($rounded_hours, 2),
        'punches' => $punches
    ];
}
?>

<div class="container">
    <div class="page-header">
        <h1>Pointages</h1>
        <button class="btn btn-success" onclick="openAddPunchModal()">+ Ajouter un pointage</button>
    </div>
    
    <?php if ($message): ?>
    <div class="alert alert-success"><?= htmlspecialchars($message) ?></div>
    <?php endif; ?>
    
    <?php if ($error): ?>
    <div class="alert alert-error"><?= htmlspecialchars($error) ?></div>
    <?php endif; ?>
    
    <!-- Sélecteurs de période et employé -->
    <div class="card">
        <form method="GET" id="filterForm" style="display: flex; gap: 15px; align-items: center; flex-wrap: wrap;">
            <div style="display: flex; gap: 10px; align-items: center;">
                <label for="period" style="font-weight: 600;">Période :</label>
                <select id="period" name="period" onchange="updateDateInput(); autoSubmit();" style="padding: 10px; border: 2px solid #ddd; border-radius: 8px;">
                    <option value="day" <?= $period_type === 'day' ? 'selected' : '' ?>>Jour</option>
                    <option value="week" <?= $period_type === 'week' ? 'selected' : '' ?>>Semaine</option>
                    <option value="month" <?= $period_type === 'month' ? 'selected' : '' ?>>Mois</option>
                </select>
            </div>
            
            <div style="display: flex; gap: 10px; align-items: center;">
                <label for="date" style="font-weight: 600;">Date :</label>
                <input type="date" id="date" name="date" value="<?= $selected_date ?>" onchange="autoSubmit();" style="padding: 10px; border: 2px solid #ddd; border-radius: 8px;">
            </div>
            
            <div style="display: flex; gap: 10px; align-items: center;">
                <label for="employee" style="font-weight: 600;">Employé :</label>
                <select id="employee" name="employee" onchange="autoSubmit();" style="padding: 10px; border: 2px solid #ddd; border-radius: 8px; min-width: 200px;">
                    <option value="">Tous les employés</option>
                    <?php foreach ($employees as $emp): ?>
                    <option value="<?= $emp['id'] ?>" <?= $selected_employee === $emp['id'] ? 'selected' : '' ?>>
                        <?= htmlspecialchars($emp['first_name'] . ' ' . $emp['last_name']) ?>
                    </option>
                    <?php endforeach; ?>
                </select>
            </div>
            
            <button type="submit" class="btn btn-primary btn-sm">Afficher</button>
            <a href="?period=day&date=<?= date('Y-m-d') ?>" class="btn btn-secondary btn-sm">Aujourd'hui</a>
        </form>
    </div>
    
    <?php if (count($hours_by_employee) > 0 && array_sum(array_column($hours_by_employee, 'real_hours')) > 0): ?>
    <div class="card">
        <h2>
            <?php 
            if ($period_type === 'day') {
                echo 'Heures travaillées le ' . date('d/m/Y', strtotime($selected_date));
            } elseif ($period_type === 'week') {
                echo 'Heures travaillées du ' . date('d/m/Y', strtotime($start_date)) . ' au ' . date('d/m/Y', strtotime($end_date));
            } else {
                echo 'Heures travaillées - ' . date('F Y', strtotime($selected_date));
            }
            ?>
        </h2>
        <table class="table">
            <thead>
                <tr>
                    <th>Employé</th>
                    <th>Heures réelles</th>
                    <th>Heures arrondies</th>
                </tr>
            </thead>
            <tbody>
                <?php foreach ($hours_by_employee as $data): ?>
                <tr>
                    <td><?= htmlspecialchars($data['employee']['first_name'] . ' ' . $data['employee']['last_name']) ?></td>
                    <td><strong><?= number_format($data['real_hours'], 2) ?> h</strong></td>
                    <td><strong style="color: #27ae60;"><?= number_format($data['rounded_hours'], 2) ?> h</strong></td>
                </tr>
                <?php endforeach; ?>
            </tbody>
        </table>
    </div>
    <?php endif; ?>
    
    <!-- Détails des pointages par employé -->
    <?php foreach ($hours_by_employee as $data): 
        $emp = $data['employee'];
        $punches = $data['punches'];
    ?>
    <div class="card">
        <h2>Pointages - <?= htmlspecialchars($emp['first_name'] . ' ' . $emp['last_name']) ?></h2>
        
        <table class="table">
            <thead>
                <tr>
                    <th>Date</th>
                    <th>Type</th>
                    <th>Heure réelle</th>
                    <th>Heure arrondie</th>
                    <th>Boîtes</th>
                    <th>Actions</th>
                </tr>
            </thead>
            <tbody>
                <?php 
                $current_date = '';
                foreach ($punches as $punch): 
                    $punch_date = date('Y-m-d', strtotime($punch['punch_datetime']));
                    $show_date = ($current_date !== $punch_date);
                    $current_date = $punch_date;
                ?>
                <tr>
                    <td>
                        <?php if ($show_date): ?>
                            <strong><?= date('d/m/Y', strtotime($punch_date)) ?></strong>
                        <?php endif; ?>
                    </td>
                    <td>
                        <?php if ($punch['punch_type'] === 'in'): ?>
                            <span style="color: #27ae60; font-weight: bold;">✓ Arrivée</span>
                        <?php else: ?>
                            <span style="color: #e74c3c; font-weight: bold;">← Départ</span>
                        <?php endif; ?>
                    </td>
                    <td><?= date('H:i:s', strtotime($punch['punch_datetime'])) ?></td>
                    <td>
                        <?php 
                        if ($punch['punch_type'] === 'in') {
                            $rounded = roundUpQuarter($punch['punch_datetime']);
                            echo '<strong style="color: #27ae60;">' . date('H:i', strtotime($rounded)) . '</strong>';
                        } else {
                            $rounded = roundDownQuarter($punch['punch_datetime']);
                            echo '<strong style="color: #e74c3c;">' . date('H:i', strtotime($rounded)) . '</strong>';
                        }
                        ?>
                    </td>
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
                        <form method="POST" style="display: inline;">
                            <input type="hidden" name="action" value="delete_punch">
                            <input type="hidden" name="punch_id" value="<?= $punch['id'] ?>">
                            <button type="submit" class="btn btn-danger btn-sm" onclick="return confirm('Supprimer ce pointage ?')">
                                Supprimer
                            </button>
                        </form>
                    </td>
                </tr>
                <?php endforeach; ?>
            </tbody>
        </table>
    </div>
    <?php endforeach; ?>
    
    <?php if (count($hours_by_employee) === 0): ?>
    <div class="card">
        <p style="color: #999; text-align: center; padding: 20px;">Aucun pointage pour cette période</p>
    </div>
    <?php endif; ?>
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
function updateDateInput() {
    const period = document.getElementById('period').value;
    const dateInput = document.getElementById('date');
    const currentDate = new Date(dateInput.value || new Date());
    
    if (period === 'month') {
        // Pour le mois, on affiche le premier jour du mois sélectionné
        const firstDay = new Date(currentDate.getFullYear(), currentDate.getMonth(), 1);
        dateInput.value = firstDay.toISOString().split('T')[0];
    } else if (period === 'week') {
        // Pour la semaine, on affiche le lundi de la semaine de la date sélectionnée
        const day = currentDate.getDay();
        const diff = currentDate.getDate() - day + (day === 0 ? -6 : 1); // Ajuster pour lundi
        const monday = new Date(currentDate);
        monday.setDate(diff);
        dateInput.value = monday.toISOString().split('T')[0];
    }
}

function autoSubmit() {
    // Soumettre automatiquement le formulaire après un court délai
    clearTimeout(window.autoSubmitTimeout);
    window.autoSubmitTimeout = setTimeout(function() {
        document.getElementById('filterForm').submit();
    }, 300);
}

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
    document.getElementById('edit_punch_id').value = punch.id;
    
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

