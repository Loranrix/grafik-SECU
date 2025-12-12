<?php
/**
 * GRAFIK - Test de redirection QR code
 * Ce script teste si l'URL du QR code fonctionne correctement
 */

require_once __DIR__ . '/includes/config.php';
require_once __DIR__ . '/classes/Database.php';

$db = Database::getInstance();

// URL correcte
$test_url = 'https://grafik.napopizza.lv/employee/';

// Récupérer l'URL de la base de données
$current = $db->fetchOne("SELECT value FROM settings WHERE `key` = 'general_qr_url'");
$db_url = $current ? $current['value'] : 'Aucune URL en base de données';

?>
<!DOCTYPE html>
<html lang="fr">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Test QR Code - Grafik</title>
    <style>
        body {
            font-family: Arial, sans-serif;
            max-width: 800px;
            margin: 50px auto;
            padding: 20px;
            background: #f5f5f5;
        }
        .card {
            background: white;
            padding: 30px;
            border-radius: 10px;
            box-shadow: 0 2px 10px rgba(0,0,0,0.1);
            margin-bottom: 20px;
        }
        h1 {
            color: #2c3e50;
        }
        .test-result {
            padding: 15px;
            border-radius: 8px;
            margin: 10px 0;
        }
        .success {
            background: #d5f4e6;
            color: #27ae60;
            border: 1px solid #27ae60;
        }
        .error {
            background: #fadbd8;
            color: #e74c3c;
            border: 1px solid #e74c3c;
        }
        .info {
            background: #d1ecf1;
            color: #0c5460;
            border: 1px solid #bee5eb;
        }
        code {
            background: #f8f9fa;
            padding: 2px 6px;
            border-radius: 4px;
            font-family: monospace;
        }
        .qr-test {
            text-align: center;
            margin: 30px 0;
        }
        .qr-test img {
            border: 2px solid #27ae60;
            border-radius: 8px;
            max-width: 300px;
        }
    </style>
</head>
<body>
    <div class="card">
        <h1>🔍 Test de diagnostic QR Code</h1>
        
        <h2>1. URL dans la base de données :</h2>
        <div class="test-result info">
            <code><?= htmlspecialchars($db_url) ?></code>
        </div>
        
        <h2>2. URL correcte attendue :</h2>
        <div class="test-result info">
            <code><?= htmlspecialchars($test_url) ?></code>
        </div>
        
        <h2>3. Test d'accessibilité de l'URL :</h2>
        <?php
        $ch = curl_init($test_url);
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
        curl_setopt($ch, CURLOPT_FOLLOWLOCATION, true);
        curl_setopt($ch, CURLOPT_TIMEOUT, 10);
        curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, false);
        curl_setopt($ch, CURLOPT_USERAGENT, 'Mozilla/5.0');
        $response = curl_exec($ch);
        $http_code = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        $curl_error = curl_error($ch);
        $redirect_url = curl_getinfo($ch, CURLINFO_REDIRECT_URL);
        curl_close($ch);
        
        if ($http_code == 200) {
            echo '<div class="test-result success">';
            echo '✅ L\'URL est accessible (HTTP ' . $http_code . ')';
            echo '</div>';
        } else {
            echo '<div class="test-result error">';
            echo '❌ L\'URL retourne le code HTTP ' . $http_code;
            if ($curl_error) {
                echo '<br>Erreur: ' . htmlspecialchars($curl_error);
            }
            if ($redirect_url) {
                echo '<br>Redirection vers: ' . htmlspecialchars($redirect_url);
            }
            echo '</div>';
        }
        ?>
        
        <h2>4. Test du QR code généré :</h2>
        <div class="qr-test">
            <p>QR code avec l'URL correcte :</p>
            <img src="https://api.qrserver.com/v1/create-qr-code/?size=300x300&data=<?= urlencode($test_url) ?>" 
                 alt="QR Code Test">
            <p style="margin-top: 15px;">
                <a href="<?= htmlspecialchars($test_url) ?>" target="_blank" style="color: #27ae60; text-decoration: none; font-weight: bold;">
                    → Tester l'URL directement
                </a>
            </p>
        </div>
        
        <h2>5. Recommandations :</h2>
        <div class="test-result info">
            <?php if ($db_url !== $test_url): ?>
                <p><strong>⚠️ L'URL dans la base de données ne correspond pas à l'URL correcte.</strong></p>
                <p>Allez sur <code>/admin/auto-fix-qr.php</code> pour corriger automatiquement.</p>
            <?php else: ?>
                <p><strong>✅ L'URL dans la base de données est correcte.</strong></p>
                <p>Si le QR code imprimé ne fonctionne toujours pas, le problème peut venir :</p>
                <ul>
                    <li>Du service <code>api.qrserver.com</code> qui a des restrictions</li>
                    <li>D'un problème de cache du navigateur</li>
                    <li>D'un problème de redirection sur le serveur</li>
                </ul>
            <?php endif; ?>
        </div>
    </div>
</body>
</html>

