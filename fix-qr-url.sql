-- GRAFIK - Correction de l'URL du QR code général
-- Force l'URL à être https://grafik.napopizza.lv/employee/

INSERT INTO settings (`key`, value) 
VALUES ('general_qr_url', 'https://grafik.napopizza.lv/employee/') 
ON DUPLICATE KEY UPDATE value = 'https://grafik.napopizza.lv/employee/';

-- Vérifier le résultat
SELECT `key`, value FROM settings WHERE `key` = 'general_qr_url';

