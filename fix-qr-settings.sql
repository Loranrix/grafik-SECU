CREATE TABLE IF NOT EXISTS settings (
    `key` VARCHAR(255) PRIMARY KEY,
    value TEXT
);

INSERT INTO settings (`key`, value) 
VALUES ('general_qr_url', 'https://grafik.napopizza.lv/employee/') 
ON DUPLICATE KEY UPDATE value = 'https://grafik.napopizza.lv/employee/';

SELECT `key`, value FROM settings WHERE `key` = 'general_qr_url';

