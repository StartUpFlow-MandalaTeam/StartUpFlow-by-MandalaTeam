<?php
/**
 * config.example.php
 * * ISTRUZIONI:
 * 1. Crea una copia locale di questo file e rinominala in 'config.php'.
 * 2. Modifica 'config.php' con le credenziali del tuo database locale.
 * 3. NON committare mai 'config.php' su GitHub (è ignorato dal .gitignore).
 */

define('DB_HOST', 'localhost');
define('DB_NAME', 'startupflow_db');
define('DB_USER', 'root');
define('DB_PASS', 'its2025');
define('DB_CHARSET', 'utf8mb4'); 

define('APP_NAME', 'StartUpFlow');
define('APP_URL', 'http://localhost:8000');
define('SMTP_HOST', '127.0.0.1');
define('SMTP_PORT', 1025);
define('SMTP_USER', '');
define('SMTP_PASS', '');
define('SMTP_FROM', 'no-reply@local.test');
define('SMTP_FROM_NAME', 'StartUpFlow');
?>
