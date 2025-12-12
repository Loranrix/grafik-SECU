<?php
/**
 * GRAFIK - Page employé - Dashboard / Statistiques
 * Interface en letton
 */

// Charger la configuration
require_once __DIR__ . '/../includes/config.php';
require_once __DIR__ . '/../classes/Database.php';
require_once __DIR__ . '/../classes/Employee.php';
require_once __DIR__ . '/../classes/Punch.php';
require_once __DIR__ . '/../classes/Shift.php';
require_once __DIR__ . '/../classes/Schedule.php';

// Vérifier qu'un employé est connecté
if (!isset($_SESSION['employee_id'])) {
    header('Location: index.php');
    exit;
}

$employee_id = $_SESSION['employee_id'];
$employee_name = $_SESSION['employee_name'];

$punchModel = new Punch();
$shiftModel = new Shift();
$scheduleModel = new Schedule();

// Calculer les heures
$today = date('Y-m-d');
$yesterday = date('Y-m-d', strtotime('-1 day'));
$week_start = date('Y-m-d', strtotime('monday this week'));
$week_end = date('Y-m-d', strtotime('sunday this week'));
$month_start = date('Y-m-01');
$month_end = date('Y-m-t');

// Calculer les heures payées (avec arrondi) pour l'affichage dans le dashboard
$hours_today = $punchModel->calculatePaidHours($employee_id, $today);
$hours_yesterday = $punchModel->calculatePaidHours($employee_id, $yesterday);
$hours_week = $punchModel->calculatePaidHoursRange($employee_id, $week_start, $week_end);
$hours_month = $punchModel->calculatePaidHoursRange($employee_id, $month_start, $month_end);

// Récupérer le planning du mois
$shifts = $shiftModel->getByEmployeeMonth($employee_id, date('Y'), date('n'));
$schedules = $scheduleModel->getForEmployee($employee_id, $month_start, $month_end);

// Récupérer les pointages du mois
$punches = $punchModel->getByEmployeeDateRange($employee_id, $month_start, $month_end);
?>
<!DOCTYPE html>
<html lang="lv">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0, maximum-scale=1.0, user-scalable=no">
    <meta name="mobile-web-app-capable" content="yes">
    <meta name="apple-mobile-web-app-capable" content="yes">
    <title>Grafik - Mana statistika</title>
    <link rel="stylesheet" href="../css/employee.css">
</head>
<body>
    <div class="container">
        <div class="logo">📊</div>
        <h1>Mana statistika</h1>
        
        <div class="stats-grid">
            <div class="stat-card">
                <div class="label">Šodien</div>
                <div class="value"><?= number_format($hours_today, 1) ?></div>
                <div class="unit">stundas</div>
            </div>
            
            <div class="stat-card">
                <div class="label">Vakar</div>
                <div class="value"><?= number_format($hours_yesterday, 1) ?></div>
                <div class="unit">stundas</div>
            </div>
            
            <div class="stat-card">
                <div class="label">Šonedēļ</div>
                <div class="value"><?= number_format($hours_week, 1) ?></div>
                <div class="unit">stundas</div>
            </div>
            
            <div class="stat-card">
                <div class="label">Šomēnes</div>
                <div class="value"><?= number_format($hours_month, 1) ?></div>
                <div class="unit">stundas</div>
            </div>
        </div>
        
        <!-- Planning du mois -->
        <?php if (count($schedules) > 0): ?>
        <div class="section">
            <h2 class="section-title">Mans grafiks - <?= strftime('%B %Y', strtotime($month_start)) ?></h2>
            <div class="schedule-list">
                <?php foreach ($schedules as $schedule): ?>
                    <?php 
                    $schedule_date = $schedule['schedule_date'];
                    $is_today = $schedule_date === $today;
                    $day_name = strftime('%A', strtotime($schedule_date));
                    $start = strtotime($schedule['start_time']);
                    $end = strtotime($schedule['end_time']);
                    $hours = round(($end - $start) / 3600, 1);
                    ?>
                    <div class="schedule-item <?= $is_today ? 'today' : '' ?>">
                        <div class="schedule-date">
                            <div class="day"><?= date('d', strtotime($schedule_date)) ?></div>
                            <div class="month"><?= strftime('%b', strtotime($schedule_date)) ?></div>
                            <div class="weekday"><?= $day_name ?></div>
                        </div>
                        <div class="schedule-time">
                            <div class="time-range">
                                <?= substr($schedule['start_time'], 0, 5) ?> - <?= substr($schedule['end_time'], 0, 5) ?>
                            </div>
                            <div class="duration"><?= $hours ?>h</div>
                        </div>
                    </div>
                <?php endforeach; ?>
            </div>
        </div>
        <?php endif; ?>
        
        <!-- Pointages du mois -->
        <?php if (count($punches) > 0): ?>
        <div class="section">
            <h2 class="section-title">Mani punkti - <?= strftime('%B %Y', strtotime($month_start)) ?></h2>
            <div class="punch-list">
                <?php 
                $grouped_punches = [];
                foreach ($punches as $punch) {
                    $date = substr($punch['punch_datetime'], 0, 10);
                    if (!isset($grouped_punches[$date])) {
                        $grouped_punches[$date] = ['in' => [], 'out' => []];
                    }
                    $grouped_punches[$date][$punch['type']][] = $punch;
                }
                
                foreach ($grouped_punches as $date => $day_punches): 
                    $is_today = $date === $today;
                ?>
                    <div class="punch-day <?= $is_today ? 'today' : '' ?>">
                        <div class="punch-date">
                            <?= date('d.m.Y', strtotime($date)) ?> - <?= strftime('%A', strtotime($date)) ?>
                        </div>
                        <div class="punch-times">
                            <?php if (!empty($day_punches['in'])): ?>
                                <div class="punch-in">
                                    <span class="label">Ierašanās:</span>
                                    <?php foreach ($day_punches['in'] as $p): ?>
                                        <span class="time"><?= date('H:i', strtotime($p['punch_datetime'])) ?></span>
                                    <?php endforeach; ?>
                                </div>
                            <?php endif; ?>
                            
                            <?php if (!empty($day_punches['out'])): ?>
                                <div class="punch-out">
                                    <span class="label">Aiziešana:</span>
                                    <?php foreach ($day_punches['out'] as $p): ?>
                                        <span class="time"><?= date('H:i', strtotime($p['punch_datetime'])) ?></span>
                                    <?php endforeach; ?>
                                </div>
                            <?php endif; ?>
                            
                            <?php 
                            // Calculer les heures payées du jour (avec arrondi)
                            $day_hours = $punchModel->calculatePaidHours($employee_id, $date);
                            if ($day_hours > 0):
                            ?>
                                <div class="punch-hours">
                                    Stundas strādātas: <strong><?= number_format($day_hours, 1) ?>h</strong>
                                </div>
                            <?php endif; ?>
                        </div>
                    </div>
                <?php endforeach; ?>
            </div>
        </div>
        <?php endif; ?>
        
        <div class="button-group" style="margin-top: 30px;">
            <a href="actions.php" class="btn btn-secondary">← Atpakaļ</a>
            <a href="consumption.php" class="btn btn-consumption">Patēriņš</a>
            <a href="logout.php" class="btn btn-exit">✕ Iziet</a>
        </div>
    </div>
</body>
</html>

