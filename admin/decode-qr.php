<?php
/**
 * GRAFIK - Décoder un QR code depuis une image
 * Pour vérifier quelle URL est encodée dans le QR code imprimé
 */

require_once __DIR__ . '/../includes/config.php';
require_once __DIR__ . '/../classes/Admin.php';

// Vérifier l'authentification admin
if (!isset($_SESSION['admin_logged_in']) || !$_SESSION['admin_logged_in']) {
    header('Location: index.php');
    exit;
}

$decoded_url = '';
$error = '';
$success = false;

// Traiter l'upload d'image
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_FILES['qr_image'])) {
    if ($_FILES['qr_image']['error'] === UPLOAD_ERR_OK) {
        $tmp_name = $_FILES['qr_image']['tmp_name'];
        $file_type = $_FILES['qr_image']['type'];
        
        // Vérifier que c'est une image
        if (strpos($file_type, 'image/') === 0) {
            // Essayer de décoder avec une bibliothèque QR code si disponible
            // Sinon, utiliser une API externe ou une méthode alternative
            
            // Pour l'instant, on va utiliser une approche simple
            // Note: Pour décoder vraiment, il faudrait une bibliothèque comme ZXing ou similaire
            // Mais on peut au moins afficher l'image et demander à l'utilisateur de scanner
            
            $error = 'Pour décoder le QR code, veuillez utiliser un scanner QR code sur votre téléphone ou un outil en ligne comme <a href="https://zxing.org/w/decode.jspx" target="_blank">ZXing Decoder</a>';
        } else {
            $error = 'Le fichier doit être une image (JPG, PNG, etc.)';
        }
    } else {
        $error = 'Erreur lors de l\'upload du fichier';
    }
}

include 'header.php';
?>

<div class="container">
    <div class="page-header">
        <h1>📷 Décoder le QR Code imprimé</h1>
        <p style="color: #7f8c8d; margin-top: 10px;">
            Upload l'image du QR code pour vérifier quelle URL est encodée dedans
        </p>
    </div>
    
    <?php if ($error): ?>
    <div class="alert alert-error"><?= $error ?></div>
    <?php endif; ?>
    
    <?php if ($success && $decoded_url): ?>
    <div class="alert alert-success">
        <strong>✅ URL trouvée dans le QR code :</strong><br>
        <code style="font-size: 16px; margin-top: 10px; display: block;"><?= htmlspecialchars($decoded_url) ?></code>
    </div>
    <?php endif; ?>
    
    <div class="card">
        <div style="padding: 30px;">
            <h2>Méthode 1 : Upload de l'image</h2>
            <form method="POST" enctype="multipart/form-data" style="margin: 20px 0;">
                <div style="margin-bottom: 20px;">
                    <label for="qr_image" style="display: block; margin-bottom: 10px; font-weight: bold;">
                        Sélectionner l'image du QR code :
                    </label>
                    <input type="file" 
                           id="qr_image" 
                           name="qr_image" 
                           accept="image/*"
                           required
                           style="padding: 10px; border: 2px dashed #ddd; border-radius: 8px; width: 100%;">
                </div>
                <button type="submit" class="btn btn-primary">
                    📷 Analyser le QR code
                </button>
            </form>
            
            <div style="background: #fff3cd; padding: 20px; border-radius: 8px; margin: 30px 0;">
                <h3>💡 Note importante :</h3>
                <p>Pour décoder le QR code, vous pouvez aussi :</p>
                <ol style="margin-left: 20px; line-height: 1.8;">
                    <li><strong>Scanner avec votre téléphone</strong> et noter l'URL obtenue</li>
                    <li>Utiliser un outil en ligne comme <a href="https://zxing.org/w/decode.jspx" target="_blank">ZXing Decoder</a></li>
                    <li>Utiliser une application de scan QR code</li>
                </ol>
            </div>
            
            <h2 style="margin-top: 40px;">Méthode 2 : Entrer l'URL manuellement</h2>
            <p>Si vous avez déjà scanné le QR code et obtenu l'URL, entrez-la ici :</p>
            <form method="GET" action="compare-qr-url.php" style="margin: 20px 0;">
                <div style="margin-bottom: 20px;">
                    <label for="scanned_url" style="display: block; margin-bottom: 10px; font-weight: bold;">
                        URL obtenue en scannant le QR code :
                    </label>
                    <input type="url" 
                           id="scanned_url" 
                           name="url" 
                           placeholder="https://grafik.napopizza.lv/employee/"
                           required
                           style="padding: 15px; border: 2px solid #ddd; border-radius: 8px; width: 100%; font-size: 16px;">
                </div>
                <button type="submit" class="btn btn-primary">
                    🔍 Comparer avec l'URL en base de données
                </button>
            </form>
        </div>
    </div>
    
    <div class="card" style="margin-top: 30px;">
        <div style="padding: 20px;">
            <h3>🎯 Objectif :</h3>
            <p>Une fois que nous connaissons l'URL encodée dans le QR code imprimé, nous pourrons :</p>
            <ul style="margin-left: 20px; line-height: 1.8;">
                <li>Vérifier si elle correspond à l'URL en base de données</li>
                <li>Corriger l'URL en base de données si nécessaire</li>
                <li>Comprendre pourquoi le message "deactivated" apparaît</li>
            </ul>
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

