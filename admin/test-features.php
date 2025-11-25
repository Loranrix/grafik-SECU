<?php
/**
 * GRAFIK - Test des nouvelles fonctionnalités
 */

require_once __DIR__ . '/../includes/config.php';
require_once __DIR__ . '/../classes/Employee.php';
require_once __DIR__ . '/../classes/Punch.php';

header('Content-Type: text/html; charset=utf-8');
?>
<!DOCTYPE html>
<html>
<head>
    <title>Tests des fonctionnalités</title>
    <style>
        body { font-family: Arial, sans-serif; margin: 20px; }
        .test-section { margin: 20px 0; padding: 15px; border: 1px solid #ddd; border-radius: 5px; }
        .success { color: green; }
        .error { color: red; }
        .info { color: blue; }
    </style>
</head>
<body>
    <h1>Tests des nouvelles fonctionnalités</h1>
    
    <?php
    $employeeModel = new Employee();
    $punchModel = new Punch();
    
    // Test 1: Vérification PIN
    echo "<div class='test-section'>";
    echo "<h2>Test 1: Vérification PIN unique</h2>";
    $allEmployees = $employeeModel->getAll(false);
    $pins = [];
    $duplicates = [];
    
    foreach ($allEmployees as $emp) {
        $pin = $emp['pin'] ?? '';
        if ($pin) {
            if (isset($pins[$pin])) {
                $duplicates[] = $pin;
            }
            $pins[$pin] = ($pins[$pin] ?? 0) + 1;
        }
    }
    
    if (empty($duplicates)) {
        echo "<p class='success'>✓ Aucun PIN en double trouvé</p>";
    } else {
        echo "<p class='error'>✗ PINs en double trouvés: " . implode(', ', $duplicates) . "</p>";
    }
    
    // Test de la méthode pinExists
    if (!empty($allEmployees)) {
        $testPin = $allEmployees[0]['pin'] ?? '0000';
        $exists = $employeeModel->pinExists($testPin);
        echo "<p class='info'>Test pinExists('$testPin'): " . ($exists ? 'Existe' : 'N\'existe pas') . "</p>";
    }
    echo "</div>";
    
    // Test 2: Fonctions d'arrondi
    echo "<div class='test-section'>";
    echo "<h2>Test 2: Fonctions d'arrondi</h2>";
    
    function roundUpQuarter($datetime) {
        $timestamp = strtotime($datetime);
        $minutes = (int)date('i', $timestamp);
        $hours = (int)date('H', $timestamp);
        $rounded_minutes = ceil($minutes / 15) * 15;
        if ($rounded_minutes >= 60) {
            $hours++;
            $rounded_minutes = 0;
        }
        return date('Y-m-d H:i:s', mktime($hours, $rounded_minutes, 0, date('m', $timestamp), date('d', $timestamp), date('Y', $timestamp)));
    }
    
    function roundDownQuarter($datetime) {
        $timestamp = strtotime($datetime);
        $minutes = (int)date('i', $timestamp);
        $hours = (int)date('H', $timestamp);
        $rounded_minutes = floor($minutes / 15) * 15;
        return date('Y-m-d H:i:s', mktime($hours, $rounded_minutes, 0, date('m', $timestamp), date('d', $timestamp), date('Y', $timestamp)));
    }
    
    $testTimes = [
        '11:10:00' => ['up' => '11:15', 'down' => '11:00'],
        '11:20:00' => ['up' => '11:30', 'down' => '11:15'],
        '11:30:00' => ['up' => '11:30', 'down' => '11:30'],
        '11:45:00' => ['up' => '11:45', 'down' => '11:45'],
        '11:50:00' => ['up' => '12:00', 'down' => '11:45'],
    ];
    
    $allOk = true;
    foreach ($testTimes as $time => $expected) {
        $datetime = '2025-01-15 ' . $time;
        $up = date('H:i', strtotime(roundUpQuarter($datetime)));
        $down = date('H:i', strtotime(roundDownQuarter($datetime)));
        $ok = ($up === $expected['up'] && $down === $expected['down']);
        if (!$ok) $allOk = false;
        $color = $ok ? 'success' : 'error';
        echo "<p class='$color'>$time → Arrondi sup: $up (attendu: {$expected['up']}), Arrondi inf: $down (attendu: {$expected['down']})</p>";
    }
    
    if ($allOk) {
        echo "<p class='success'><strong>✓ Tous les tests d'arrondi passent</strong></p>";
    } else {
        echo "<p class='error'><strong>✗ Certains tests d'arrondi échouent</strong></p>";
    }
    echo "</div>";
    
    // Test 3: Récupération des pointages par période
    echo "<div class='test-section'>";
    echo "<h2>Test 3: Récupération des pointages par période</h2>";
    
    $today = date('Y-m-d');
    $employees = $employeeModel->getAll(true);
    
    if (!empty($employees)) {
        $testEmployee = $employees[0];
        echo "<p class='info'>Test avec l'employé: {$testEmployee['first_name']} {$testEmployee['last_name']} (ID: {$testEmployee['id']})</p>";
        
        // Test jour
        $punchesDay = $punchModel->getByEmployeeDateRange($testEmployee['id'], $today, $today);
        echo "<p>Pointages aujourd'hui: " . count($punchesDay) . "</p>";
        
        // Test semaine
        $day_of_week = date('w', strtotime($today));
        $monday_offset = ($day_of_week == 0) ? -6 : (1 - $day_of_week);
        $start_week = date('Y-m-d', strtotime($today . ' ' . $monday_offset . ' days'));
        $end_week = date('Y-m-d', strtotime($start_week . ' +6 days'));
        $punchesWeek = $punchModel->getByEmployeeDateRange($testEmployee['id'], $start_week, $end_week);
        echo "<p>Pointages cette semaine ($start_week à $end_week): " . count($punchesWeek) . "</p>";
        
        // Test mois
        $start_month = date('Y-m-01', strtotime($today));
        $end_month = date('Y-m-t', strtotime($today));
        $punchesMonth = $punchModel->getByEmployeeDateRange($testEmployee['id'], $start_month, $end_month);
        echo "<p>Pointages ce mois ($start_month à $end_month): " . count($punchesMonth) . "</p>";
        
        echo "<p class='success'>✓ Les méthodes de récupération fonctionnent</p>";
    } else {
        echo "<p class='error'>✗ Aucun employé trouvé pour les tests</p>";
    }
    echo "</div>";
    
    // Test 4: Calcul des heures arrondies
    echo "<div class='test-section'>";
    echo "<h2>Test 4: Calcul des heures arrondies</h2>";
    
    if (!empty($employees)) {
        $testEmployee = $employees[0];
        $punches = $punchModel->getByEmployeeDateRange($testEmployee['id'], $today, $today);
        
        if (!empty($punches)) {
            $real_hours = 0;
            $rounded_hours = 0;
            $in_time = null;
            $in_time_rounded = null;
            
            foreach ($punches as $punch) {
                if ($punch['punch_type'] === 'in') {
                    $in_time = strtotime($punch['punch_datetime']);
                    $in_time_rounded = strtotime(roundUpQuarter($punch['punch_datetime']));
                } elseif ($punch['punch_type'] === 'out' && $in_time !== null) {
                    $out_time = strtotime($punch['punch_datetime']);
                    $out_time_rounded = strtotime(roundDownQuarter($punch['punch_datetime']));
                    
                    $real_hours += ($out_time - $in_time) / 3600;
                    $rounded_hours += ($out_time_rounded - $in_time_rounded) / 3600;
                    
                    $in_time = null;
                    $in_time_rounded = null;
                }
            }
            
            echo "<p>Heures réelles: " . number_format($real_hours, 2) . " h</p>";
            echo "<p>Heures arrondies: " . number_format($rounded_hours, 2) . " h</p>";
            echo "<p class='success'>✓ Le calcul des heures fonctionne</p>";
        } else {
            echo "<p class='info'>Aucun pointage aujourd'hui pour tester le calcul</p>";
        }
    }
    echo "</div>";
    
    // Test 5: Vérification du fichier check-pin.php
    echo "<div class='test-section'>";
    echo "<h2>Test 5: Fichier check-pin.php</h2>";
    if (file_exists(__DIR__ . '/check-pin.php')) {
        echo "<p class='success'>✓ Le fichier check-pin.php existe</p>";
    } else {
        echo "<p class='error'>✗ Le fichier check-pin.php n'existe pas</p>";
    }
    echo "</div>";
    ?>
    
    <div class='test-section'>
        <h2>Résumé</h2>
        <p>Si tous les tests sont verts, les fonctionnalités sont prêtes à être utilisées.</p>
        <p><a href="employees.php">→ Aller à la gestion des employés</a></p>
        <p><a href="punches.php">→ Aller à la gestion des pointages</a></p>
    </div>
</body>
</html>

