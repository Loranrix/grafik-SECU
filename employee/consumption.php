<?php
/**
 * GRAFIK - Page employé - Consommation
 * Interface en letton
 */

require_once __DIR__ . '/../includes/config.php';
require_once __DIR__ . '/../classes/Database.php';
require_once __DIR__ . '/../classes/Employee.php';
require_once __DIR__ . '/../classes/Consumption.php';

// Vérifier qu'un employé est connecté
if (!isset($_SESSION['employee_id'])) {
    header('Location: index.php');
    exit;
}

$employee_id = $_SESSION['employee_id'];
$employee_name = $_SESSION['employee_name'];

$consumptionModel = new Consumption();

$message = '';
$error = '';

// Traiter l'ajout
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $action = $_POST['action'] ?? '';
    
    if ($action === 'add') {
        $item_name = trim($_POST['item_name']);
        $original_price = floatval($_POST['original_price']);
        
        if (empty($item_name)) {
            $error = 'Lūdzu, ievadiet produkta nosaukumu';
        } elseif ($original_price <= 0) {
            $error = 'Lūdzu, ievadiet derīgu cenu';
        } else {
            $consumptionModel->add($employee_id, $item_name, $original_price, 50);
            $message = 'Patēriņš pievienots!';
        }
    } elseif ($action === 'add_drink') {
        $drink_type = $_POST['drink_type'] ?? '';
        $quantity = intval($_POST['quantity'] ?? 1);
        $price = floatval($_POST['price'] ?? 0);
        
        if (empty($drink_type)) {
            $error = 'Lūdzu, izvēlieties dzērienu';
        } else {
            // Vérifier les boissons gratuites du jour
            $today_drinks = $consumptionModel->getTodayDrinksForEmployee($employee_id);
            $free_tea = $today_drinks['tea'] ?? 0;
            $free_coffee = $today_drinks['coffee'] ?? 0;
            
            // Calculer combien sont gratuits et combien sont payants
            $free_count = 0;
            $paid_count = 0;
            
            if ($drink_type === 'tea' || $drink_type === 'coffee') {
                // Thé OU Café : 1 gratuit au total (pas une de chaque)
                $total_free_drinks = $free_tea + $free_coffee;
                if ($total_free_drinks < 1) {
                    // Premier thé/café gratuit
                    $free_count = 1;
                    $paid_count = max(0, $quantity - 1);
                } else {
                    // Tous payants
                    $paid_count = $quantity;
                }
            } else {
                // Autres : tous payants
                $paid_count = $quantity;
            }
            
            // Ajouter les consommations
            $drink_names = [
                'tea' => 'Tēja',
                'coffee' => 'Kafija',
                'other' => 'Cits dzēriens'
            ];
            $drink_name = $drink_names[$drink_type] ?? 'Dzēriens';
            
            if ($free_count > 0) {
                // Ajouter les gratuits (prix 0)
                for ($i = 0; $i < $free_count; $i++) {
                    $consumptionModel->add($employee_id, $drink_name . ' (Bezmaksas)', 0, 0);
                }
            }
            
            if ($paid_count > 0) {
                if ($price <= 0) {
                    $error = 'Lūdzu, ievadiet cenu (vairāk nekā 1 ' . strtolower($drink_name) . ')';
                } else {
                    // Ajouter les payants avec 50% de remise
                    for ($i = 0; $i < $paid_count; $i++) {
                        $consumptionModel->add($employee_id, $drink_name, $price, 50);
                    }
                    $message = $drink_name . ' pievienots! (' . $free_count . ' bezmaksas, ' . $paid_count . ' ar 50% atlaidi)';
                }
            } else {
                $message = $drink_name . ' pievienots bez maksas!';
            }
        }
    }
}

// Récupérer les consommations
$consumptions_today = $consumptionModel->getTodayForEmployee($employee_id);
$consumptions_month = $consumptionModel->getMonthForEmployee($employee_id, date('Y'), date('n'));

// Calculer les totaux
$total_today = $consumptionModel->getTotalForPeriod($employee_id, date('Y-m-d'), date('Y-m-d'));
$total_month = $consumptionModel->getTotalForPeriod($employee_id, date('Y-m-01'), date('Y-m-t'));
?>
<!DOCTYPE html>
<html lang="lv">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <meta name="mobile-web-app-capable" content="yes">
    <meta name="apple-mobile-web-app-capable" content="yes">
    <title>Grafik - Patēriņš</title>
    <link rel="stylesheet" href="../css/employee.css">
</head>
<body>
    <div class="container">
        <div class="logo">🍽️</div>
        <h1>Mans patēriņš</h1>
        <p class="subtitle"><?= htmlspecialchars($employee_name) ?></p>
        
        <?php if ($message): ?>
        <div class="alert alert-success"><?= htmlspecialchars($message) ?></div>
        <?php endif; ?>
        
        <?php if ($error): ?>
        <div class="alert alert-error"><?= htmlspecialchars($error) ?></div>
        <?php endif; ?>
        
        <!-- Formulaire d'ajout nourriture -->
        <div class="card" style="margin-bottom: 20px;">
            <h2>Pievienot patēriņu</h2>
            <form method="POST" class="consumption-form">
                <input type="hidden" name="action" value="add">
                
                <div class="form-group">
                    <label for="item_name">Produkta nosaukums</label>
                    <input type="text" 
                           id="item_name" 
                           name="item_name" 
                           placeholder="Piemēram: Kafija, Sendviča, Sula"
                           required
                           autocomplete="off">
                </div>
                
                <div class="form-group">
                    <label for="original_price">Pilna cena (€)</label>
                    <input type="number" 
                           id="original_price" 
                           name="original_price" 
                           step="0.01"
                           min="0.01"
                           placeholder="Piemēram: 5.00"
                           inputmode="decimal"
                           pattern="[0-9]*\.?[0-9]*"
                           required>
                    <small style="color: #7f8c8d; display: block; margin-top: 5px;">
                        50% atlaide tiks piemērota automātiski
                    </small>
                </div>
                
                <button type="submit" class="btn btn-primary btn-large">
                    ✓ Pievienot
                </button>
            </form>
        </div>
        
        <!-- Statistiques du jour -->
        <div class="stats-grid" style="margin-bottom: 20px;">
            <div class="stat-card">
                <div class="label">Šodien</div>
                <div class="value"><?= $total_today['count'] ?></div>
                <div class="unit">patēriņi</div>
            </div>
            
            <div class="stat-card">
                <div class="label">Šodienas summa</div>
                <div class="value"><?= number_format($total_today['total_discounted'], 2) ?>€</div>
                <div class="unit">(-50%)</div>
            </div>
        </div>
        
        <!-- Section Boissons (APRÈS la nourriture) -->
        <div class="card" style="margin-top: 20px; margin-bottom: 20px; background: linear-gradient(135deg, #667eea 0%, #764ba2 100%); color: white; border: none; box-shadow: 0 8px 16px rgba(0,0,0,0.2);">
            <h2 style="color: white; margin-bottom: 25px; font-size: 24px; text-align: center;">🍹 Dzērieni</h2>
                <form method="POST" id="drinkForm">
                    <input type="hidden" name="action" value="add_drink">
                    
                    <div style="margin-bottom: 25px;">
                        <label style="display: block; font-weight: 700; margin-bottom: 15px; color: white; font-size: 18px; text-align: center;">Dzēriena veids:</label>
                        
                        <div style="display: flex; flex-direction: column; gap: 15px;">
                            <label style="display: flex; align-items: center; padding: 20px; background: white; border: 3px solid rgba(255,255,255,0.5); border-radius: 15px; cursor: pointer; transition: all 0.3s; box-shadow: 0 4px 8px rgba(0,0,0,0.1);" onmouseover="this.style.transform='scale(1.02)'" onmouseout="this.style.transform='scale(1)'">
                                <input type="radio" name="drink_type" value="tea" style="width: 30px; height: 30px; margin-right: 15px; cursor: pointer; accent-color: #667eea;" onchange="updateDrinkForm()">
                                <span style="font-size: 22px; font-weight: 600; color: #2c3e50;">🍵 Tēja</span>
                            </label>
                            
                            <label style="display: flex; align-items: center; padding: 20px; background: white; border: 3px solid rgba(255,255,255,0.5); border-radius: 15px; cursor: pointer; transition: all 0.3s; box-shadow: 0 4px 8px rgba(0,0,0,0.1);" onmouseover="this.style.transform='scale(1.02)'" onmouseout="this.style.transform='scale(1)'">
                                <input type="radio" name="drink_type" value="coffee" style="width: 30px; height: 30px; margin-right: 15px; cursor: pointer; accent-color: #667eea;" onchange="updateDrinkForm()">
                                <span style="font-size: 22px; font-weight: 600; color: #2c3e50;">☕ Kafija</span>
                            </label>
                            
                            <label style="display: flex; align-items: center; padding: 20px; background: white; border: 3px solid rgba(255,255,255,0.5); border-radius: 15px; cursor: pointer; transition: all 0.3s; box-shadow: 0 4px 8px rgba(0,0,0,0.1);" onmouseover="this.style.transform='scale(1.02)'" onmouseout="this.style.transform='scale(1)'">
                                <input type="radio" name="drink_type" value="other" style="width: 30px; height: 30px; margin-right: 15px; cursor: pointer; accent-color: #667eea;" onchange="updateDrinkForm()">
                                <span style="font-size: 22px; font-weight: 600; color: #2c3e50;">🥤 Cits</span>
                            </label>
                        </div>
                    </div>
                    
                    <div class="form-group" style="margin-bottom: 20px;">
                        <label for="drink_quantity" style="color: white; font-weight: 600; font-size: 16px; display: block; margin-bottom: 10px;">Daudzums:</label>
                        <input type="number" 
                               id="drink_quantity" 
                               name="quantity" 
                               min="1" 
                               value="1"
                               required
                               style="width: 100%; padding: 18px; border: 3px solid white; border-radius: 12px; font-size: 20px; font-weight: 600; text-align: center; background: white; box-sizing: border-box;"
                               onchange="updateDrinkForm()">
                    </div>
                    
                    <div class="form-group" id="drink_price_group" style="display: none; margin-bottom: 20px;">
                        <label for="drink_price" style="color: white; font-weight: 600; font-size: 16px; display: block; margin-bottom: 10px;">Cena (€):</label>
                        <input type="number" 
                               id="drink_price" 
                               name="price" 
                               step="0.01"
                               min="0.01"
                               placeholder="0.00"
                               inputmode="decimal"
                               style="width: 100%; padding: 18px; border: 3px solid white; border-radius: 12px; font-size: 20px; font-weight: 600; text-align: center; background: white; box-sizing: border-box;">
                        <small style="color: rgba(255,255,255,0.9); display: block; margin-top: 8px; text-align: center; font-size: 14px;">
                            50% atlaide tiks piemērota automātiski
                        </small>
                    </div>
                    
                    <div id="drink_info" style="background: rgba(255,255,255,0.95); padding: 15px; border-radius: 12px; margin: 20px 0; display: none; border: 2px solid white;">
                        <small style="color: #2e7d32; font-weight: 600; font-size: 15px;" id="drink_info_text"></small>
                    </div>
                    
                    <div style="display: flex; gap: 15px; margin-top: 30px; padding-top: 25px; border-top: 3px solid rgba(255,255,255,0.5); padding-bottom: 20px;">
                        <button type="button" onclick="resetDrinkForm()" style="flex: 1; padding: 20px; font-size: 20px; font-weight: 700; border: 3px solid white; background: rgba(255,255,255,0.2); color: white; border-radius: 15px; cursor: pointer; min-height: 65px; transition: all 0.3s; touch-action: manipulation;" onmouseover="this.style.background='rgba(255,255,255,0.3)'" onmouseout="this.style.background='rgba(255,255,255,0.2)'">
                            ✕ Atcelt
                        </button>
                        <button type="submit" style="flex: 1; padding: 20px; font-size: 20px; font-weight: 700; background: white; color: #667eea; border: 3px solid white; border-radius: 15px; cursor: pointer; min-height: 65px; box-shadow: 0 6px 12px rgba(0,0,0,0.3); transition: all 0.3s; touch-action: manipulation;" onmouseover="this.style.transform='scale(1.05)'" onmouseout="this.style.transform='scale(1)'">
                            ✓ Pievienot dzērienu
                        </button>
                    </div>
                </form>
        </div>
        
        <!-- Consommations du jour -->
        <?php if (!empty($consumptions_today)): ?>
        <div class="card">
            <h2>Šodienas patēriņš</h2>
            <div class="consumption-list">
                <?php foreach ($consumptions_today as $c): ?>
                <div class="consumption-item">
                    <div class="consumption-info">
                        <div class="consumption-name"><?= htmlspecialchars($c['item_name']) ?></div>
                        <div class="consumption-time"><?= substr($c['consumption_time'], 0, 5) ?></div>
                    </div>
                    <div class="consumption-price">
                        <div class="original-price"><?= number_format($c['original_price'], 2) ?>€</div>
                        <div class="discounted-price"><?= number_format($c['discounted_price'], 2) ?>€</div>
                    </div>
                </div>
                <?php endforeach; ?>
            </div>
        </div>
        <?php endif; ?>
        
        <!-- Résumé mensuel -->
        <div class="card" style="margin-top: 20px;">
            <h2>Mēneša kopsavilkums</h2>
            <div class="month-summary">
                <div class="summary-row">
                    <span>Kopējais patēriņu skaits:</span>
                    <strong><?= $total_month['count'] ?></strong>
                </div>
                <div class="summary-row">
                    <span>Pilna cena:</span>
                    <strong><?= number_format($total_month['total_original'], 2) ?>€</strong>
                </div>
                <div class="summary-row">
                    <span>Ar 50% atlaidi:</span>
                    <strong class="discounted"><?= number_format($total_month['total_discounted'], 2) ?>€</strong>
                </div>
                <div class="summary-row savings">
                    <span>Ietaupījums:</span>
                    <strong><?= number_format($total_month['total_original'] - $total_month['total_discounted'], 2) ?>€</strong>
                </div>
            </div>
        </div>
        
        <!-- Navigation buttons -->
        <div class="button-group" style="margin-top: 30px;">
            <a href="actions.php" class="btn btn-secondary">← Atpakaļ</a>
            <a href="dashboard.php" class="btn btn-dashboard">📊 Statistika</a>
            <a href="logout.php" class="btn btn-exit">✕ Iziet</a>
        </div>
    </div>
    
    <script>
    function updateDrinkForm() {
        const drinkType = document.querySelector('input[name="drink_type"]:checked')?.value;
        const quantity = parseInt(document.getElementById('drink_quantity').value) || 1;
        const priceGroup = document.getElementById('drink_price_group');
        const drinkInfo = document.getElementById('drink_info');
        const drinkInfoText = document.getElementById('drink_info_text');
        const priceInput = document.getElementById('drink_price');
        
        if (!drinkType) {
            priceGroup.style.display = 'none';
            drinkInfo.style.display = 'none';
            return;
        }
        
        // Vérifier les boissons gratuites (simulation côté client)
        // Le serveur fera la vérification réelle
        let needsPrice = false;
        let infoMessage = '';
        
        if (drinkType === 'tea' || drinkType === 'coffee') {
            // Thé ou Café : 1 gratuit au total
            if (quantity > 1) {
                needsPrice = true;
                infoMessage = '1 ' + (drinkType === 'tea' ? 'tēja' : 'kafija') + ' būs bezmaksas, pārējās ' + (quantity - 1) + ' ar 50% atlaidi';
            } else {
                infoMessage = '1 ' + (drinkType === 'tea' ? 'tēja' : 'kafija') + ' būs bezmaksas (ja vēl nav izmantots bezmaksas dzēriens šodien)';
            }
        } else {
            // Autres : tous payants
            needsPrice = true;
            infoMessage = 'Cits dzēriens: ' + quantity + ' gab. ar 50% atlaidi';
        }
        
        if (needsPrice) {
            priceGroup.style.display = 'block';
            priceInput.required = true;
        } else {
            priceGroup.style.display = 'none';
            priceInput.required = false;
            priceInput.value = '';
        }
        
        if (infoMessage) {
            drinkInfo.style.display = 'block';
            drinkInfoText.textContent = infoMessage;
        } else {
            drinkInfo.style.display = 'none';
        }
    }
    
    function resetDrinkForm() {
        // Réinitialiser le formulaire
        document.querySelectorAll('input[name="drink_type"]').forEach(radio => {
            radio.checked = false;
        });
        document.getElementById('drink_quantity').value = 1;
        document.getElementById('drink_price').value = '';
        updateDrinkForm();
    }
    
    // Mettre à jour au chargement
    document.addEventListener('DOMContentLoaded', function() {
        updateDrinkForm();
    });
    </script>
</body>
</html>
