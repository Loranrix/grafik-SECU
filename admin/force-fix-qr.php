<?php
/**
 * GRAFIK - Correction forcée de l'URL du QR code
 * Force l'URL à être https://grafik.napopizza.lv/employee/
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

// URL correcte absolue
$correct_url = 'https://grafik.napopizza.lv/employee/';

// Récupérer l'URL actuelle
$current = $db->fetchOne("SELECT value FROM settings WHERE `key` = 'general_qr_url'");
$current_url = $current ? $current['value'] : '';

// Forcer la correction
$fixed = false;
try {
    $db->query(
        "INSERT INTO settings (`key`, value) VALUES ('general_qr_url', ?) 
         ON DUPLICATE KEY UPDATE value = ?",
        [$correct_url, $correct_url]
    );
    $fixed = true;
    $current_url = $correct_url;
} catch (Exception $e) {
    die('Erreur : ' . $e->getMessage());
}

// Tester que la page employee fonctionne
$ch = curl_init($correct_url);
curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
curl_setopt($ch, CURLOPT_FOLLOWLOCATION, true);
curl_setopt($ch, CURLOPT_TIMEOUT, 10);
curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, false);
curl_setopt($ch, CURLOPT_USERAGENT, 'Mozilla/5.0');
$response = curl_exec($ch);
$http_code = curl_getinfo($ch, CURLINFO_HTTP_CODE);
curl_close($ch);

include 'header.php';
?>

<div class="container">
    <div class="page-header">
        <h1>✅ Correction effectuée</h1>
    </div>
    
    <div class="card">
        <div style="padding: 30px;">
            <div class="alert alert-success">
                <strong>✅ URL corrigée avec succès !</strong><br>
                L'URL en base de données est maintenant : <code><?= htmlspecialchars($correct_url) ?></code>
            </div>
            
            <h2>Test de l'URL :</h2>
            <?php if ($http_code == 200): ?>
                <div style="background: #d5f4e6; color: #27ae60; padding: 15px; border-radius: 8px; margin: 20px 0;">
                    ✅ L'URL <code><?= htmlspecialchars($correct_url) ?></code> est accessible (HTTP <?= $http_code ?>)
                </div>
            <?php else: ?>
                <div style="background: #fadbd8; color: #e74c3c; padding: 15px; border-radius: 8px; margin: 20px 0;">
                    ⚠️ L'URL retourne le code HTTP <?= $http_code ?>
                </div>
            <?php endif; ?>
            
            <h2 style="margin-top: 30px;">Nouveau QR Code :</h2>
            <div style="text-align: center; margin: 30px 0;">
                <div style="background: white; padding: 30px; border-radius: 12px; display: inline-block; box-shadow: 0 4px 16px rgba(0,0,0,0.1);">
                    <img src="https://api.qrserver.com/v1/create-qr-code/?size=400x400&data=<?= urlencode($correct_url) ?>" 
                         alt="QR Code"
                         style="max-width: 100%; border: 2px solid #27ae60; border-radius: 8px;">
                </div>
                <p style="margin-top: 15px; color: #7f8c8d;">
                    Ce QR code pointe directement vers <code><?= htmlspecialchars($correct_url) ?></code>
                </p>
            </div>
            
            <div style="background: #d1ecf1; padding: 20px; border-radius: 8px; margin: 30px 0;">
                <h3>📝 Prochaines étapes :</h3>
                <ol style="margin-left: 20px; line-height: 1.8;">
                    <li>✅ L'URL en base de données est maintenant correcte</li>
                    <li>📥 <strong>Télécharge</strong> ou <strong>imprime</strong> le nouveau QR code ci-dessus</li>
                    <li>🔄 <strong>Remplace</strong> l'ancien QR code au restaurant</li>
                    <li>✅ Le nouveau QR code fonctionnera directement sans service de redirection</li>
                </ol>
            </div>
            
            <div style="margin-top: 30px; display: flex; gap: 15px; justify-content: center;">
                <a href="qr-general.php" class="btn btn-primary">
                    📥 Aller à la page de gestion du QR code
                </a>
                <button onclick="window.print()" class="btn btn-secondary">
                    🖨️ Imprimer cette page
                </button>
            </div>
        </div>
    </div>
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

@media print {
    .btn, .page-header {
        display: none;
    }
}
</style>

<?php include 'footer.php'; ?>

