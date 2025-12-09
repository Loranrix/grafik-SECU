<?php
/**
 * GRAFIK - Gestion des consommations employés
 */

require_once __DIR__ . '/../includes/config.php';
require_once __DIR__ . '/../classes/Database.php';
require_once __DIR__ . '/../classes/Admin.php';
require_once __DIR__ . '/../classes/Employee.php';
require_once __DIR__ . '/../classes/Consumption.php';

include 'header.php';

$employeeModel = new Employee();
$consumptionModel = new Consumption();
$message = '';
$error = '';

// Traiter les actions
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $action = $_POST['action'] ?? '';
    
    if ($action === 'add_consumption') {
        $employee_id = $_POST['employee_id'] ?? '';
        $item_name = $_POST['item_name'] ?? '';
        $original_price = floatval($_POST['original_price'] ?? 0);
        $discount_percent = floatval($_POST['discount_percent'] ?? 50);
        
        if (empty($employee_id) || empty($item_name) || $original_price <= 0) {
            $error = 'Veuillez remplir tous les champs correctement';
        } else {
            $result = $consumptionModel->add($employee_id, $item_name, $original_price, $discount_percent);
            if ($result) {
                $message = 'Consommation ajoutée avec succès';
            } else {
                $error = 'Erreur lors de l\'ajout de la consommation';
            }
        }
    } elseif ($action === 'delete_consumption') {
        $consumption_id = $_POST['consumption_id'] ?? '';
        if (!empty($consumption_id)) {
            $result = $consumptionModel->delete($consumption_id);
            if ($result) {
                $message = 'Consommation supprimée avec succès';
            } else {
                $error = 'Erreur lors de la suppression';
            }
        }
    }
}

// Récupérer toutes les consommations
$all_consumptions = $consumptionModel->getRecent(100);

// Calculer les totaux par employé
$totals_by_employee = [];
foreach ($all_consumptions as $cons) {
    $emp_id = $cons['employee_id'];
    if (!isset($totals_by_employee[$emp_id])) {
        $totals_by_employee[$emp_id] = [
            'name' => $cons['first_name'] . ' ' . $cons['last_name'],
            'count' => 0,
            'total_original' => 0,
            'total_paid' => 0
        ];
    }
    $totals_by_employee[$emp_id]['count']++;
    $totals_by_employee[$emp_id]['total_original'] += $cons['original_price'];
    $totals_by_employee[$emp_id]['total_paid'] += $cons['paid_price'];
}
?>

<div class="container">
    <div class="page-header">
        <h1>Consommations Employés</h1>
        <button class="btn btn-success" onclick="openAddConsumptionModal()">+ Ajouter une consommation</button>
    </div>
    
    <?php if ($message): ?>
    <div class="alert alert-success"><?= htmlspecialchars($message) ?></div>
    <?php endif; ?>
    
    <?php if ($error): ?>
    <div class="alert alert-error"><?= htmlspecialchars($error) ?></div>
    <?php endif; ?>
    
    <div class="card" style="margin-bottom: 30px;">
        <h2>Résumé par employé</h2>
        
        <?php if (count($totals_by_employee) > 0): ?>
        <table class="table">
            <thead>
                <tr>
                    <th>Employé</th>
                    <th>Nombre d'articles</th>
                    <th>Prix total original</th>
                    <th>Prix total payé (-50%)</th>
                    <th>À déduire du salaire</th>
                </tr>
            </thead>
            <tbody>
                <?php foreach ($totals_by_employee as $total): ?>
                <tr>
                    <td><strong><?= htmlspecialchars($total['name']) ?></strong></td>
                    <td><?= $total['count'] ?></td>
                    <td><?= number_format($total['total_original'], 2) ?> €</td>
                    <td><?= number_format($total['total_paid'], 2) ?> €</td>
                    <td style="color: #e74c3c; font-weight: bold;">-<?= number_format($total['total_paid'], 2) ?> €</td>
                </tr>
                <?php endforeach; ?>
            </tbody>
        </table>
        <?php else: ?>
        <p style="color: #999; text-align: center; padding: 20px;">Aucune consommation enregistrée</p>
        <?php endif; ?>
    </div>
    
    <div class="card">
        <h2>Historique détaillé</h2>
        
        <?php if (count($all_consumptions) > 0): ?>
        <table class="table">
            <thead>
                <tr>
                    <th>Date</th>
                    <th>Employé</th>
                    <th>Article</th>
                    <th>Prix original</th>
                    <th>Réduction</th>
                    <th>Prix payé</th>
                    <th>Actions</th>
                </tr>
            </thead>
            <tbody>
                <?php foreach ($all_consumptions as $cons): ?>
                <tr>
                    <td><?= date('d/m/Y H:i', strtotime($cons['consumption_datetime'])) ?></td>
                    <td><?= htmlspecialchars($cons['first_name'] . ' ' . $cons['last_name']) ?></td>
                    <td><?= htmlspecialchars($cons['item_name']) ?></td>
                    <td><?= number_format($cons['original_price'], 2) ?> €</td>
                    <td style="color: #27ae60;">-<?= $cons['discount_percent'] ?>%</td>
                    <td style="font-weight: bold;"><?= number_format($cons['paid_price'], 2) ?> €</td>
                    <td>
                        <form method="POST" style="display: inline;" onsubmit="return confirm('Supprimer cette consommation ?');">
                            <input type="hidden" name="action" value="delete_consumption">
                            <input type="hidden" name="consumption_id" value="<?= htmlspecialchars($cons['id'] ?? '') ?>">
                            <button type="submit" class="btn btn-danger btn-sm">Supprimer</button>
                        </form>
                    </td>
                </tr>
                <?php endforeach; ?>
            </tbody>
        </table>
        <?php else: ?>
        <p style="color: #999; text-align: center; padding: 20px;">Aucune consommation enregistrée</p>
        <?php endif; ?>
    </div>
</div>

<!-- Modal Ajouter Consommation -->
<div class="modal" id="addConsumptionModal">
    <div class="modal-content">
        <div class="modal-header">
            <h2>Ajouter une consommation</h2>
            <button class="modal-close" onclick="closeAddConsumptionModal()">&times;</button>
        </div>
        <form method="POST">
            <input type="hidden" name="action" value="add_consumption">
            
            <div class="form-group">
                <label for="employee_id">Employé</label>
                <select id="employee_id" name="employee_id" required style="width: 100%; padding: 12px; border: 2px solid #ddd; border-radius: 8px;">
                    <option value="">Sélectionner un employé</option>
                    <?php 
                    $employees = $employeeModel->getAll(true);
                    foreach ($employees as $emp): 
                    ?>
                    <option value="<?= $emp['id'] ?>">
                        <?= htmlspecialchars($emp['first_name'] . ' ' . $emp['last_name']) ?>
                    </option>
                    <?php endforeach; ?>
                </select>
            </div>
            
            <div class="form-group">
                <label for="item_name">Article (boisson, plat, etc.)</label>
                <input type="text" id="item_name" name="item_name" required placeholder="Ex: Coca-Cola, Pizza, etc." style="width: 100%; padding: 12px; border: 2px solid #ddd; border-radius: 8px;">
            </div>
            
            <div class="form-group">
                <label for="original_price">Prix original (€)</label>
                <input type="number" id="original_price" name="original_price" step="0.01" min="0" required placeholder="0.00" style="width: 100%; padding: 12px; border: 2px solid #ddd; border-radius: 8px;">
            </div>
            
            <div class="form-group">
                <label for="discount_percent">Réduction (%)</label>
                <input type="number" id="discount_percent" name="discount_percent" step="1" min="0" max="100" value="50" required style="width: 100%; padding: 12px; border: 2px solid #ddd; border-radius: 8px;">
                <small style="color: #666;">Par défaut: 50% (prix employé)</small>
            </div>
            
            <button type="submit" class="btn btn-primary">Enregistrer</button>
        </form>
    </div>
</div>

<script>
function openAddConsumptionModal() {
    document.getElementById('addConsumptionModal').classList.add('active');
}

function closeAddConsumptionModal() {
    document.getElementById('addConsumptionModal').classList.remove('active');
}

// Fermer modal en cliquant à l'extérieur
window.onclick = function(event) {
    const modal = document.getElementById('addConsumptionModal');
    if (event.target === modal) {
        closeAddConsumptionModal();
    }
}
</script>

<?php include 'footer.php'; ?>

