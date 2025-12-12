<?php
/**
 * GRAFIK - Page employé - Enregistrement pointage
 * Interface en letton
 */

// Charger la configuration
require_once __DIR__ . '/../includes/config.php';
require_once __DIR__ . '/../classes/Database.php';
require_once __DIR__ . '/../classes/Employee.php';
require_once __DIR__ . '/../classes/Punch.php';
require_once __DIR__ . '/../classes/Shift.php';

// Vérifier qu'un employé est connecté
if (!isset($_SESSION['employee_id'])) {
    header('Location: index.php');
    exit;
}

$employee_id = $_SESSION['employee_id'];
$employee_name = $_SESSION['employee_name'];
$employeeModel = new Employee();

// Vérifier le type de pointage
$type = isset($_GET['type']) ? $_GET['type'] : 'in';
if (!in_array($type, ['in', 'out'])) {
    $type = 'in';
}

// Vérifier la confirmation
$confirm = isset($_GET['confirm']) && $_GET['confirm'] === 'yes';
if (!$confirm) {
    // Rediriger vers la page de confirmation
    header('Location: confirm-punch.php?type=' . $type);
    exit;
}

$punchModel = new Punch();
$shiftModel = new Shift();

// Récupérer le nombre de boîtes si présent
$boxes_count = isset($_GET['boxes']) ? intval($_GET['boxes']) : null;

// Enregistrer le pointage
$error_message = null;
$warning_message = null;
$punch_id = null;
try {
    // Vérifier d'abord s'il y a un oubli de scan avant d'enregistrer
    $lastPunch = $punchModel->getLastPunch($employee_id);
    if ($lastPunch) {
        $last_type = $lastPunch['punch_type'] ?? '';
        if ($type === 'in' && $last_type === 'in') {
            $warning_message = "⚠️ Warning: You are registering arrival, but the previous departure was not registered. Please contact Loran on WhatsApp about the forgotten scan.";
        } elseif ($type === 'out' && $last_type === 'out') {
            $warning_message = "⚠️ Warning: You are registering departure, but the previous arrival was not registered. Please contact Loran on WhatsApp about the forgotten scan.";
        }
    } elseif ($type === 'out') {
        $warning_message = "⚠️ Warning: You are registering departure without a previous arrival. Please contact Loran on WhatsApp about the forgotten scan.";
    }
    
    $punch_id = $punchModel->record($employee_id, $type, null, $boxes_count);
    $punch_datetime = date('Y-m-d H:i:s');
    
    // Si des boîtes ont été saisies, envoyer une notification par email
    if ($boxes_count !== null && $boxes_count > 0) {
        require_once __DIR__ . '/../classes/SecuritySettings.php';
        $securitySettings = new SecuritySettings();
        $admin_email = $securitySettings->getAdminNotificationEmail();
        if (empty($admin_email)) {
            $admin_email = 'info@napopizza.lv'; // Email par défaut
        }
        
        $employee = $employeeModel->getById($employee_id);
        $employee_full_name = $employee['first_name'] . ' ' . $employee['last_name'];
        
        $to = $admin_email;
        $subject = 'Grafik - Saisie boîtes vides - ' . $employee_full_name;
        $body = "📦 Nouvelle saisie de boîtes vides\n\n";
        $body .= "Darbinieks: " . $employee_full_name . "\n";
        $body .= "Datums/Laiks: " . date('d/m/Y H:i:s', strtotime($punch_datetime)) . "\n";
        $body .= "Tips punktējuma: Aiziešana\n";
        $body .= "Metāla kastīšu skaits: " . $boxes_count . "\n";
        $body .= "Laiks: " . date('H:i', strtotime($punch_datetime)) . "\n";
        
        // Configuration email avec SMTP
        $headers = "From: grafik@napopizza.lv\r\n";
        $headers .= "Reply-To: grafik@napopizza.lv\r\n";
        $headers .= "Content-Type: text/plain; charset=UTF-8\r\n";
        $headers .= "X-Mailer: PHP/" . phpversion() . "\r\n";
        
        // Tentative d'envoi avec ini_set pour SMTP si disponible
        $old_smtp = ini_get('SMTP');
        $old_smtp_port = ini_get('smtp_port');
        
        ini_set('SMTP', 'napopizza.lv');
        ini_set('smtp_port', '587');
        
        @mail($to, $subject, $body, $headers);
        
        // Restaurer les paramètres
        if ($old_smtp !== false) ini_set('SMTP', $old_smtp);
        if ($old_smtp_port !== false) ini_set('smtp_port', $old_smtp_port);
    }
} catch (Exception $e) {
    $error_message = $e->getMessage();
    $punch_datetime = date('Y-m-d H:i:s');
}

// Récupérer l'heure prévue si un shift existe
$shift = null;
$today = date('Y-m-d');
$shifts_today = $shiftModel->getByEmployeeMonth($employee_id, date('Y'), date('n'));
foreach ($shifts_today as $s) {
    if ($s['shift_date'] === $today) {
        $shift = $s;
        break;
    }
}

$type_label = $type === 'in' ? 'Ierašanās' : 'Aiziešana';
$type_icon = $type === 'in' ? '✓' : '👋';
?>
<!DOCTYPE html>
<html lang="lv">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0, maximum-scale=1.0, user-scalable=no">
    <meta name="mobile-web-app-capable" content="yes">
    <meta name="apple-mobile-web-app-capable" content="yes">
    <title>Grafik - <?= $type_label ?></title>
    <link rel="stylesheet" href="../css/employee.css">
</head>
<body>
    <div class="container">
        <?php if ($error_message): ?>
        <div class="logo" style="font-size: 72px;">❌</div>
        <h1>Kļūda!</h1>
        
        <div class="message error" style="background: #e74c3c; color: white; padding: 20px; border-radius: 15px; margin: 20px 0;">
            <?= htmlspecialchars($error_message) ?>
        </div>
        <?php else: ?>
        
        <?php if ($warning_message): ?>
        <div class="message warning" style="background: #f39c12; color: white; padding: 20px; border-radius: 15px; margin: 20px 0; font-weight: bold; text-align: center;">
            <?= htmlspecialchars($warning_message) ?>
        </div>
        <?php endif; ?>
        <div class="logo" style="font-size: 72px;"><?= $type_icon ?></div>
        <h1><?= $type_label ?> reģistrēta!</h1>
        
        <div class="message success">
            Paldies, <?= htmlspecialchars($employee_name) ?>!
        </div>
        <?php endif; ?>
        
        <?php if (!$error_message): ?>
        <div class="punch-info">
            <div class="label">Datums:</div>
            <div class="value"><?= date('d.m.Y', strtotime($punch_datetime)) ?></div>
            
            <div class="label">Laiks:</div>
            <div class="value"><?= date('H:i', strtotime($punch_datetime)) ?></div>
            
            <?php if ($shift && $type === 'in'): ?>
            <div class="label">Plānotais laiks:</div>
            <div class="value"><?= date('H:i', strtotime($shift['start_time'])) ?></div>
            <?php endif; ?>
            
            <?php if ($shift && $type === 'out'): ?>
            <div class="label">Plānotais laiks:</div>
            <div class="value"><?= date('H:i', strtotime($shift['end_time'])) ?></div>
            <?php endif; ?>
        </div>
        <?php endif; ?>
        
        <div class="button-group" style="margin-top: 30px;">
            <a href="actions.php" class="btn btn-secondary">← Atpakaļ</a>
            <a href="logout.php" class="btn btn-exit">✕ Iziet</a>
        </div>
    </div>
</body>
</html>

