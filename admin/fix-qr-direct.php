<?php
/**
 * GRAFIK - Corriger le QR code pour utiliser l'URL directe
 * Le problème vient probablement d'un service de redirection désactivé
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

// URL directe correcte (sans service de redirection)
$direct_url = 'https://grafik.napopizza.lv/employee/';

// Récupérer l'URL actuelle
$current = $db->fetchOne("SELECT value FROM settings WHERE `key` = 'general_qr_url'");
$current_url = $current ? $current['value'] : '';

$fixed = false;
$message = '';

// Si on demande de corriger
if (isset($_GET['fix']) && $_GET['fix'] === 'yes') {
    try {
        $db->query(
            "INSERT INTO settings (`key`, value) VALUES ('general_qr_url', ?) 
             ON DUPLICATE KEY UPDATE value = ?",
            [$direct_url, $direct_url]
        );
        $current_url = $direct_url;
        $fixed = true;
        $message = '✅ URL corrigée ! Le QR code utilisera maintenant l\'URL directe.';
    } catch (Exception $e) {
        $message = '❌ Erreur : ' . $e->getMessage();
    }
}

include 'header.php';
?>

<div class="container">
    <div class="page-header">
        <h1>🔧 Correction du QR Code - URL Directe</h1>
        <p style="color: #7f8c8d; margin-top: 10px;">
            Le message "This QR Code has been deactivated" indique que le QR code utilise un service de redirection désactivé.
        </p>
    </div>
    
    <?php if ($message): ?>
    <div class="alert <?= strpos($message, '✅') !== false ? 'alert-success' : 'alert-error' ?>">
        <?= $message ?>
    </div>
    <?php endif; ?>
    
    <div class="card">
        <div style="padding: 30px;">
            <h2>Diagnostic :</h2>
            
            <div style="background: #fff3cd; padding: 20px; border-radius: 8px; margin: 20px 0;">
                <h3>⚠️ Problème identifié :</h3>
                <p>Le message "This QR Code has been deactivated" signifie que :</p>
                <ul style="margin-left: 20px; line-height: 1.8;">
                    <li>Le QR code imprimé pointe vers un <strong>service de redirection</strong> (comme bit.ly, tinyurl, ou un service de QR code dynamique)</li>
                    <li>Ce service a été <strong>désactivé</strong> ou n'existe plus</li>
                    <li>Il faut utiliser l'<strong>URL directe</strong> dans le QR code</li>
                </ul>
            </div>
            
            <h2 style="margin-top: 30px;">URL actuelle en base de données :</h2>
            <?php if ($current_url): ?>
                <div style="background: #f8f9fa; padding: 15px; border-radius: 8px; margin: 20px 0;">
                    <code style="font-size: 14px; word-break: break-all;"><?= htmlspecialchars($current_url) ?></code>
                </div>
                
                <?php
                // Détecter si l'URL utilise un service de redirection
                $is_redirect = false;
                $redirect_services = ['bit.ly', 'tinyurl.com', 'goo.gl', 't.co', 'ow.ly', 'rebrand.ly', 'qr', 'redirect', 'short'];
                foreach ($redirect_services as $service) {
                    if (stripos($current_url, $service) !== false) {
                        $is_redirect = true;
                        break;
                    }
                }
                ?>
                
                <?php if ($is_redirect || $current_url !== $direct_url): ?>
                    <div style="background: #fadbd8; color: #721c24; padding: 20px; border-radius: 8px; margin: 20px 0;">
                        <strong>❌ Problème détecté !</strong>
                        <p style="margin-top: 10px;">
                            <?php if ($is_redirect): ?>
                                L'URL utilise un service de redirection qui a probablement été désactivé.
                            <?php else: ?>
                                L'URL ne correspond pas à l'URL directe attendue.
                            <?php endif; ?>
                        </p>
                    </div>
                <?php else: ?>
                    <div style="background: #d5f4e6; color: #155724; padding: 20px; border-radius: 8px; margin: 20px 0;">
                        <strong>✅ L'URL est déjà directe !</strong>
                        <p style="margin-top: 10px;">Si le problème persiste, il peut venir d'ailleurs (cache, service de génération de QR code, etc.)</p>
                    </div>
                <?php endif; ?>
            <?php else: ?>
                <div style="background: #fff3cd; color: #856404; padding: 15px; border-radius: 8px; margin: 20px 0;">
                    ⚠️ Aucune URL configurée
                </div>
            <?php endif; ?>
            
            <h2 style="margin-top: 30px;">URL directe correcte :</h2>
            <div style="background: #d5f4e6; padding: 15px; border-radius: 8px; margin: 20px 0;">
                <code style="font-size: 16px; word-break: break-all;"><?= htmlspecialchars($direct_url) ?></code>
            </div>
            
            <?php if (!$current_url || $current_url !== $direct_url): ?>
                <div style="text-align: center; margin: 30px 0;">
                    <a href="?fix=yes" class="btn btn-primary btn-large" onclick="return confirm('Corriger l\'URL pour utiliser l\'URL directe ?\n\nNote: Le QR code imprimé devra être régénéré avec la nouvelle URL.')">
                        🔧 Corriger l'URL maintenant
                    </a>
                </div>
            <?php endif; ?>
            
            <h2 style="margin-top: 40px;">Nouveau QR Code avec URL directe :</h2>
            <div style="text-align: center; margin: 30px 0;">
                <img src="https://api.qrserver.com/v1/create-qr-code/?size=400x400&data=<?= urlencode($direct_url) ?>" 
                     alt="QR Code Direct"
                     style="max-width: 100%; border: 2px solid #27ae60; border-radius: 8px;">
                <p style="margin-top: 15px; color: #7f8c8d;">
                    Ce QR code pointe directement vers l'URL, sans service de redirection
                </p>
            </div>
            
            <div style="background: #d1ecf1; padding: 20px; border-radius: 8px; margin: 30px 0;">
                <h3>📝 Important :</h3>
                <p>Après avoir corrigé l'URL :</p>
                <ol style="margin-left: 20px; line-height: 1.8;">
                    <li>Le QR code en base de données sera mis à jour</li>
                    <li>Tu devras <strong>régénérer et réimprimer</strong> le QR code depuis <a href="qr-general.php">la page de gestion</a></li>
                    <li>Le nouveau QR code fonctionnera directement sans service de redirection</li>
                </ol>
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

