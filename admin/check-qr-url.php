<?php
/**
 * GRAFIK - Vérifier l'URL du QR code général
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

// Récupérer l'URL du QR code
$qr_url = $db->fetchOne("SELECT value FROM settings WHERE `key` = 'general_qr_url'");
$qr_url = $qr_url ? $qr_url['value'] : '';

include 'header.php';
?>

<div class="container">
    <div class="page-header">
        <h1>🔍 Diagnostic QR Code</h1>
    </div>
    
    <div class="card">
        <div style="padding: 30px;">
            <h2>URL actuelle dans la base de données :</h2>
            <?php if ($qr_url): ?>
                <div style="background: #f8f9fa; padding: 15px; border-radius: 8px; margin: 20px 0;">
                    <code style="font-size: 14px; word-break: break-all;"><?= htmlspecialchars($qr_url) ?></code>
                </div>
                
                <h3>Test de l'URL :</h3>
                <div style="margin: 20px 0;">
                    <?php
                    // Tester si l'URL est accessible
                    $ch = curl_init($qr_url);
                    curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
                    curl_setopt($ch, CURLOPT_FOLLOWLOCATION, true);
                    curl_setopt($ch, CURLOPT_TIMEOUT, 10);
                    curl_setopt($ch, CURLOPT_NOBODY, true);
                    curl_exec($ch);
                    $http_code = curl_getinfo($ch, CURLINFO_HTTP_CODE);
                    $curl_error = curl_error($ch);
                    curl_close($ch);
                    
                    if ($http_code == 200) {
                        echo '<div style="background: #d5f4e6; color: #27ae60; padding: 15px; border-radius: 8px;">';
                        echo '✅ L\'URL est accessible (HTTP ' . $http_code . ')';
                        echo '</div>';
                    } else {
                        echo '<div style="background: #fadbd8; color: #e74c3c; padding: 15px; border-radius: 8px;">';
                        echo '❌ L\'URL retourne le code HTTP ' . $http_code;
                        if ($curl_error) {
                            echo '<br>Erreur: ' . htmlspecialchars($curl_error);
                        }
                        echo '</div>';
                    }
                    ?>
                </div>
                
                <h3>QR Code généré avec cette URL :</h3>
                <div style="text-align: center; margin: 30px 0;">
                    <img src="https://api.qrserver.com/v1/create-qr-code/?size=300x300&data=<?= urlencode($qr_url) ?>" 
                         alt="QR Code Test"
                         style="max-width: 100%; border: 2px solid #27ae60; border-radius: 8px;">
                </div>
                
                <div style="margin-top: 30px;">
                    <a href="qr-general.php" class="btn btn-primary">← Retour à la gestion du QR code</a>
                </div>
            <?php else: ?>
                <div style="background: #fff3cd; color: #856404; padding: 15px; border-radius: 8px;">
                    ⚠️ Aucune URL n'est configurée pour le QR code général
                </div>
                <div style="margin-top: 20px;">
                    <a href="qr-general.php" class="btn btn-primary">Créer un QR code général</a>
                </div>
            <?php endif; ?>
        </div>
    </div>
</div>

<?php include 'footer.php'; ?>

