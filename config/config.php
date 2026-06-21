<?php
// Database Configuration
define('DB_HOST', 'localhost');
define('DB_NAME', 'rucu_gets');
define('DB_USER', 'root');
define('DB_PASS', '');
define('DB_CHARSET', 'utf8mb4');

// Application Configuration
define('APP_NAME', 'RUCU Graduate Employment Tracking System');
define('APP_URL', 'http://localhost/rgts');
define('BASE_PATH', __DIR__ . '/..');

// Session Configuration
define('SESSION_LIFETIME', 900); // 15 minutes in seconds
define('PASSWORD_EXPIRY_DAYS', 30);

// Upload Configuration
define('UPLOAD_DIR', BASE_PATH . '/uploads');
define('MAX_FILE_SIZE', 2 * 1024 * 1024); // 2MB
define('ALLOWED_EXTENSIONS', ['jpg', 'jpeg', 'png', 'gif']);

// Pagination
define('RECORDS_PER_PAGE', 15);

// Error Reporting (disable in production)
ini_set('display_errors', 1);
error_reporting(E_ALL);

// Timezone
date_default_timezone_set('Africa/Dar_es_Salaam');
