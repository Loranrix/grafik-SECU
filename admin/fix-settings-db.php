<?php
/**
 * GRAFIK - Script de correction de la table settings
 * À exécuter une seule fois pour créer/corriger la table
 */

require_once __DIR__ . '/../includes/config.php';
require_once __DIR__ . '/../classes/Database.php';

try {
    $db = Database::getInstance();
    
    // Créer la table settings si elle n'existe pas
    $db->query("
        CREATE TABLE IF NOT EXISTS settings (
            `key` VARCHAR(255) PRIMARY KEY,
            value TEXT
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4
    ");
    
    // Insérer ou mettre à jour l'URL du QR code
    $correct_url = 'https://grafik.napopizza.lv/employee/';
    $db->query(
        "INSERT INTO settings (`key`, value) VALUES ('general_qr_url', ?) 
         ON DUPLICATE KEY UPDATE value = ?",
        [$correct_url, $correct_url]
    );
    
    // Vérifier le résultat
    $result = $db->fetchOne("SELECT `key`, value FROM settings WHERE `key` = 'general_qr_url'");
    
    echo "✅ Succès !\n";
    echo "Table settings créée/vérifiée\n";
    echo "URL du QR code : " . ($result ? $result['value'] : 'Non trouvée') . "\n";
    
} catch (Exception $e) {
    echo "❌ Erreur : " . $e->getMessage() . "\n";
    exit(1);
}

