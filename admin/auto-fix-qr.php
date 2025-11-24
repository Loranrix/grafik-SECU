<?php
/**
 * GRAFIK - Correction automatique de l'URL du QR code
 * Ce script vérifie et corrige automatiquement l'URL si nécessaire
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

// URL correcte
$correct_url = 'https://grafik.napopizza.lv/employee/';

// Récupérer l'URL actuelle
$current = $db->fetchOne("SELECT value FROM settings WHERE `key` = 'general_qr_url'");
$current_url = $current ? $current['value'] : '';

// Corriger automatiquement si nécessaire
$fixed = false;
if ($current_url !== $correct_url) {
    try {
        $db->query(
            "INSERT INTO settings (`key`, value) VALUES ('general_qr_url', ?) 
             ON DUPLICATE KEY UPDATE value = ?",
            [$correct_url, $correct_url]
        );
        $fixed = true;
        $current_url = $correct_url;
    } catch (Exception $e) {
        die('Erreur lors de la correction : ' . $e->getMessage());
    }
}

// Vérifier que la page employee fonctionne
$test_url = $correct_url;
$ch = curl_init($test_url);
curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
curl_setopt($ch, CURLOPT_FOLLOWLOCATION, true);
curl_setopt($ch, CURLOPT_TIMEOUT, 10);
curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, false);
$response = curl_exec($ch);
$http_code = curl_getinfo($ch, CURLINFO_HTTP_CODE);
$curl_error = curl_error($ch);
curl_close($ch);

include 'header.php';
?>

<div class="container">
    <div class="page-header">
        <h1>🔧 Correction automatique du QR Code</h1>
    </div>
    
    <?php if ($fixed): ?>
    <div class="alert alert-success">
        ✅ <strong>URL corrigée automatiquement !</strong><br>
        L'URL dans la base de données a été mise à jour vers : <code><?= htmlspecialchars($correct_url) ?></code>
    </div>
    <?php else: ?>
    <div class="alert alert-success">
        ✅ <strong>L'URL est déjà correcte !</strong><br>
        URL actuelle : <code><?= htmlspecialchars($current_url) ?></code>
    </div>
    <?php endif; ?>
    
    <div class="card">
        <div style="padding: 30px;">
            <h2>Test de l'URL :</h2>
            <?php if ($http_code == 200): ?>
                <div style="background: #d5f4e6; color: #27ae60; padding: 15px; border-radius: 8px; margin: 20px 0;">
                    ✅ L'URL <code><?= htmlspecialchars($test_url) ?></code> est accessible (HTTP <?= $http_code ?>)
                </div>
            <?php else: ?>
                <div style="background: #fadbd8; color: #e74c3c; padding: 15px; border-radius: 8px; margin: 20px 0;">
                    ❌ L'URL retourne le code HTTP <?= $http_code ?>
                    <?php if ($curl_error): ?>
                        <br>Erreur: <?= htmlspecialchars($curl_error) ?>
                    <?php endif; ?>
                </div>
            <?php endif; ?>
            
            <h2 style="margin-top: 30px;">QR Code avec l'URL correcte :</h2>
            <div style="text-align: center; margin: 30px 0;">
                <img src="https://api.qrserver.com/v1/create-qr-code/?size=400x400&data=<?= urlencode($correct_url) ?>" 
                     alt="QR Code"
                     style="max-width: 100%; border: 2px solid #27ae60; border-radius: 8px;">
            </div>
            
            <div style="background: #ecf0f1; padding: 20px; border-radius: 8px; margin: 30px 0;">
                <h3>💡 Note importante :</h3>
                <p>Si le QR code imprimé ne fonctionne toujours pas après cette correction, le problème peut venir :</p>
                <ul style="margin-left: 20px; line-height: 1.8;">
                    <li>Du service <code>api.qrserver.com</code> qui génère le QR code</li>
                    <li>D'un problème de cache du navigateur</li>
                    <li>D'un problème de redirection sur le serveur</li>
                </ul>
                <p style="margin-top: 15px;"><strong>Solution :</strong> Le QR code imprimé devrait fonctionner si l'URL qu'il contient est bien <code><?= htmlspecialchars($correct_url) ?></code></p>
            </div>
            
            <div style="margin-top: 30px;">
                <a href="qr-general.php" class="btn btn-primary">← Retour à la gestion du QR code</a>
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
</style>

<?php include 'footer.php'; ?>

