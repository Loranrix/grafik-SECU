<?php
/**
 * GRAFIK - Corriger l'URL du QR code général
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
$message = '';
$error = '';

// URL correcte
$correct_url = 'https://grafik.napopizza.lv/employee/';

// Récupérer l'URL actuelle
$current = $db->fetchOne("SELECT value FROM settings WHERE `key` = 'general_qr_url'");
$current_url = $current ? $current['value'] : '';

// Si on demande de corriger
if (isset($_GET['fix']) && $_GET['fix'] === 'yes') {
    try {
        $db->query(
            "INSERT INTO settings (`key`, value) VALUES ('general_qr_url', ?) 
             ON DUPLICATE KEY UPDATE value = ?",
            [$correct_url, $correct_url]
        );
        $message = '✅ URL corrigée avec succès !';
        $current_url = $correct_url;
    } catch (Exception $e) {
        $error = 'Erreur : ' . $e->getMessage();
    }
}

include 'header.php';
?>

<div class="container">
    <div class="page-header">
        <h1>🔧 Corriger l'URL du QR Code</h1>
    </div>
    
    <?php if ($message): ?>
    <div class="alert alert-success"><?= htmlspecialchars($message) ?></div>
    <?php endif; ?>
    
    <?php if ($error): ?>
    <div class="alert alert-error"><?= htmlspecialchars($error) ?></div>
    <?php endif; ?>
    
    <div class="card">
        <div style="padding: 30px;">
            <h2>URL actuelle dans la base de données :</h2>
            <?php if ($current_url): ?>
                <div style="background: #f8f9fa; padding: 15px; border-radius: 8px; margin: 20px 0;">
                    <code style="font-size: 14px; word-break: break-all;"><?= htmlspecialchars($current_url) ?></code>
                </div>
            <?php else: ?>
                <div style="background: #fff3cd; color: #856404; padding: 15px; border-radius: 8px; margin: 20px 0;">
                    ⚠️ Aucune URL configurée
                </div>
            <?php endif; ?>
            
            <h2 style="margin-top: 30px;">URL correcte :</h2>
            <div style="background: #d5f4e6; padding: 15px; border-radius: 8px; margin: 20px 0;">
                <code style="font-size: 14px; word-break: break-all;"><?= htmlspecialchars($correct_url) ?></code>
            </div>
            
            <?php if ($current_url !== $correct_url): ?>
                <div style="background: #fadbd8; color: #721c24; padding: 20px; border-radius: 8px; margin: 30px 0;">
                    <strong>⚠️ Les URLs ne correspondent pas !</strong>
                    <p style="margin-top: 10px;">C'est probablement la cause du problème. Cliquez sur le bouton ci-dessous pour corriger.</p>
                </div>
                
                <div style="text-align: center; margin: 30px 0;">
                    <a href="?fix=yes" class="btn btn-primary btn-large" onclick="return confirm('Êtes-vous sûr de vouloir corriger l\'URL ?')">
                        🔧 Corriger l'URL
                    </a>
                </div>
            <?php else: ?>
                <div style="background: #d5f4e6; color: #155724; padding: 20px; border-radius: 8px; margin: 30px 0;">
                    <strong>✅ L'URL est correcte !</strong>
                    <p style="margin-top: 10px;">Si le problème persiste, il peut venir d'ailleurs (service de génération de QR code, cache, etc.)</p>
                </div>
            <?php endif; ?>
            
            <div style="margin-top: 30px;">
                <h3>QR Code avec l'URL correcte :</h3>
                <div style="text-align: center; margin: 30px 0;">
                    <img src="https://api.qrserver.com/v1/create-qr-code/?size=300x300&data=<?= urlencode($correct_url) ?>" 
                         alt="QR Code"
                         style="max-width: 100%; border: 2px solid #27ae60; border-radius: 8px;">
                </div>
            </div>
            
            <div style="margin-top: 30px;">
                <a href="qr-general.php" class="btn btn-secondary">← Retour à la gestion du QR code</a>
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

.alert-error {
    background: #fadbd8;
    color: #e74c3c;
    border: 1px solid #e74c3c;
}
</style>

<?php include 'footer.php'; ?>

