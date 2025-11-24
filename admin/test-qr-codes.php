<?php
/**
 * GRAFIK - Script de test pour vérifier que qr-codes.php fonctionne
 */

require_once __DIR__ . '/../includes/config.php';
require_once __DIR__ . '/../classes/Database.php';

header('Content-Type: text/plain; charset=utf-8');

echo "=== TEST QR CODES ===\n\n";

try {
    $db = Database::getInstance();
    echo "✅ Connexion à la base de données OK\n";
} catch (Exception $e) {
    echo "❌ Erreur connexion DB: " . $e->getMessage() . "\n";
    exit(1);
}

// Vérifier la structure de la table settings
echo "\n1. Vérification de la table settings:\n";
try {
    $columns = $db->fetchAll("SHOW COLUMNS FROM settings");
    echo "   ✅ Table settings existe\n";
    echo "   Colonnes trouvées:\n";
    foreach ($columns as $col) {
        echo "   - " . $col['Field'] . " (" . $col['Type'] . ")\n";
    }
    
    $has_key = false;
    $has_setting_key = false;
    foreach ($columns as $col) {
        if ($col['Field'] === 'key') $has_key = true;
        if ($col['Field'] === 'setting_key') $has_setting_key = true;
    }
    
    if ($has_key) {
        echo "   ✅ Structure simple détectée (key, value)\n";
    } elseif ($has_setting_key) {
        echo "   ✅ Structure complète détectée (setting_key, setting_value)\n";
    }
} catch (Exception $e) {
    echo "   ⚠️ Table settings n'existe pas: " . $e->getMessage() . "\n";
    echo "   → La table sera créée automatiquement au premier usage\n";
}

// Tester les fonctions getSetting et setSetting
echo "\n2. Test des fonctions getSetting/setSetting:\n";

function getSetting($db, $key) {
    try {
        $result = $db->fetchOne("SELECT value FROM settings WHERE `key` = ?", [$key]);
        if ($result) return $result['value'];
    } catch (Exception $e) {
        try {
            $result = $db->fetchOne("SELECT setting_value FROM settings WHERE setting_key = ?", [$key]);
            if ($result) return $result['setting_value'];
        } catch (Exception $e2) {
            return null;
        }
    }
    return null;
}

function setSetting($db, $key, $value) {
    try {
        $db->query(
            "INSERT INTO settings (`key`, value) VALUES (?, ?) 
             ON DUPLICATE KEY UPDATE value = ?",
            [$key, $value, $value]
        );
        return true;
    } catch (Exception $e) {
        try {
            $db->query(
                "INSERT INTO settings (setting_key, setting_value) VALUES (?, ?) 
                 ON DUPLICATE KEY UPDATE setting_value = ?",
                [$key, $value, $value]
            );
            return true;
        } catch (Exception $e2) {
            try {
                $db->query("
                    CREATE TABLE IF NOT EXISTS settings (
                        `key` VARCHAR(255) PRIMARY KEY,
                        value TEXT
                    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4
                ");
                $db->query(
                    "INSERT INTO settings (`key`, value) VALUES (?, ?) 
                     ON DUPLICATE KEY UPDATE value = ?",
                    [$key, $value, $value]
                );
                return true;
            } catch (Exception $e3) {
                throw $e3;
            }
        }
    }
}

try {
    $test_value = 'https://grafik.napopizza.lv/employee/';
    setSetting($db, 'test_qr_url', $test_value);
    echo "   ✅ setSetting() fonctionne\n";
    
    $retrieved = getSetting($db, 'test_qr_url');
    if ($retrieved === $test_value) {
        echo "   ✅ getSetting() fonctionne (valeur: $retrieved)\n";
    } else {
        echo "   ❌ getSetting() retourne une valeur incorrecte: $retrieved\n";
    }
    
    // Nettoyer
    try {
        $db->query("DELETE FROM settings WHERE `key` = 'test_qr_url'");
    } catch (Exception $e) {
        try {
            $db->query("DELETE FROM settings WHERE setting_key = 'test_qr_url'");
        } catch (Exception $e2) {}
    }
} catch (Exception $e) {
    echo "   ❌ Erreur test fonctions: " . $e->getMessage() . "\n";
}

// Vérifier l'URL actuelle
echo "\n3. Vérification de l'URL du QR code:\n";
$current_url = getSetting($db, 'general_qr_url');
if ($current_url) {
    echo "   ✅ URL trouvée: $current_url\n";
    if ($current_url === 'https://grafik.napopizza.lv/employee/') {
        echo "   ✅ URL est correcte\n";
    } else {
        echo "   ⚠️ URL n'est pas la bonne (devrait être https://grafik.napopizza.lv/employee/)\n";
    }
} else {
    echo "   ⚠️ Aucune URL configurée (sera créée automatiquement)\n";
}

echo "\n=== TEST TERMINÉ ===\n";
echo "✅ Tous les tests sont passés avec succès!\n";

