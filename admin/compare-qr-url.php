<?php
/**
 * GRAFIK - Comparer l'URL du QR code scanné avec celle en base de données
 */

require_once __DIR__ . '/../includes/config.php';
require_once __DIR__ . '/../classes/Database.php';
require_once __DIR__ . '/../classes/Admin.php';

// Vérifier l'authentification admin
if (!isset($_SESSION['admin_logged_in']) || !$_SESSION['admin_logged_in']) {
    header('Location: index.php');
    exit;
}

$db = Database::getInstance();

// URL scannée depuis le QR code
$scanned_url = isset($_GET['url']) ? trim($_GET['url']) : '';

// URL correcte attendue
$correct_url = 'https://grafik.napopizza.lv/employee/';

// Récupérer l'URL de la base de données
$current = $db->fetchOne("SELECT value FROM settings WHERE `key` = 'general_qr_url'");
$db_url = $current ? $current['value'] : 'Aucune URL en base de données';

$needs_fix = false;
$message = '';

// Si on demande de corriger
if (isset($_GET['fix']) && $_GET['fix'] === 'yes' && $scanned_url) {
    try {
        $db->query(
            "INSERT INTO settings (`key`, value) VALUES ('general_qr_url', ?) 
             ON DUPLICATE KEY UPDATE value = ?",
            [$scanned_url, $scanned_url]
        );
        $db_url = $scanned_url;
        $message = '✅ URL corrigée avec succès !';
    } catch (Exception $e) {
        $message = '❌ Erreur : ' . $e->getMessage();
    }
}

include 'header.php';
?>

<div class="container">
    <div class="page-header">
        <h1>🔍 Comparaison des URLs</h1>
    </div>
    
    <?php if ($message): ?>
    <div class="alert <?= strpos($message, '✅') !== false ? 'alert-success' : 'alert-error' ?>">
        <?= $message ?>
    </div>
    <?php endif; ?>
    
    <?php if (!$scanned_url): ?>
    <div class="card">
        <div style="padding: 30px;">
            <div class="alert alert-error">
                Aucune URL fournie. <a href="decode-qr.php">Retour au décodage</a>
            </div>
        </div>
    </div>
    <?php else: ?>
    
    <div class="card">
        <div style="padding: 30px;">
            <h2>1. URL scannée depuis le QR code imprimé :</h2>
            <div style="background: #f8f9fa; padding: 15px; border-radius: 8px; margin: 20px 0;">
                <code style="font-size: 14px; word-break: break-all;"><?= htmlspecialchars($scanned_url) ?></code>
            </div>
            
            <h2 style="margin-top: 30px;">2. URL dans la base de données :</h2>
            <div style="background: #f8f9fa; padding: 15px; border-radius: 8px; margin: 20px 0;">
                <code style="font-size: 14px; word-break: break-all;"><?= htmlspecialchars($db_url) ?></code>
            </div>
            
            <h2 style="margin-top: 30px;">3. URL correcte attendue :</h2>
            <div style="background: #d5f4e6; padding: 15px; border-radius: 8px; margin: 20px 0;">
                <code style="font-size: 14px; word-break: break-all;"><?= htmlspecialchars($correct_url) ?></code>
            </div>
            
            <h2 style="margin-top: 30px;">4. Analyse :</h2>
            <?php
            $scanned_matches_correct = ($scanned_url === $correct_url);
            $db_matches_scanned = ($db_url === $scanned_url);
            $db_matches_correct = ($db_url === $correct_url);
            
            if ($scanned_matches_correct && $db_matches_correct) {
                echo '<div class="alert alert-success">';
                echo '<strong>✅ Tout est correct !</strong><br>';
                echo 'L\'URL scannée correspond à l\'URL correcte et à celle en base de données.';
                echo '</div>';
            } elseif ($scanned_matches_correct && !$db_matches_scanned) {
                echo '<div class="alert alert-error">';
                echo '<strong>⚠️ Problème détecté !</strong><br>';
                echo 'L\'URL scannée est correcte, mais l\'URL en base de données est différente.';
                echo '<div style="margin-top: 20px;">';
                echo '<a href="?url=' . urlencode($scanned_url) . '&fix=yes" class="btn btn-primary" onclick="return confirm(\'Mettre à jour l\'URL en base de données avec celle du QR code scanné ?\')">';
                echo '🔧 Corriger l\'URL en base de données';
                echo '</a>';
                echo '</div>';
                echo '</div>';
            } elseif (!$scanned_matches_correct) {
                echo '<div class="alert alert-error">';
                echo '<strong>❌ L\'URL scannée ne correspond pas à l\'URL attendue !</strong><br>';
                echo 'Le QR code imprimé contient une URL différente de celle attendue.';
                echo '<p style="margin-top: 15px;"><strong>Solution :</strong> Il faudra régénérer le QR code avec la bonne URL.</p>';
                echo '</div>';
            }
            ?>
            
            <div style="margin-top: 30px;">
                <a href="decode-qr.php" class="btn btn-secondary">← Retour</a>
                <a href="qr-general.php" class="btn btn-secondary">Gérer le QR code</a>
            </div>
        </div>
    </div>
    <?php endif; ?>
</div>

<style>
.alert {
    padding: 15px 20px;
    border-radius: 8px;
    margin-bottom: 20px;
}

.alert-success {
    background: #d5f4e6;
    color: #27ae60;
    border: 1px solid #27ae60;
}

.alert-error {
    background: #fadbd8;
    color: #e74c3c;
    border: 1px solid #e74c3c;
}
</style>

<?php include 'footer.php'; ?>

