<?php
// Copia este archivo como config.php y completa con tus datos reales

define('APP_NAME', 'SoccerAPP');
define('ENV', 'development'); // 'development' | 'production'

define('BASE_URL', 'http://localhost/torneo'); // Cambia a tu dominio en producción

define('DB_HOST', 'localhost');
define('DB_NAME', 'torneo_db');
define('DB_USER', 'root');
define('DB_PASS', '');

define('SESSION_LIFETIME', 28800); // 8 horas
define('UPLOADS_PATH', __DIR__ . '/../uploads');
define('UPLOADS_URL', BASE_URL . '/uploads');

// Claude API — configura tu clave en admin/settings o como variable de entorno
define('CLAUDE_API_KEY', getenv('CLAUDE_API_KEY') ?: '');
define('CLAUDE_MODEL',   'claude-haiku-4-5-20251001');

// Configuración de sesión
ini_set('session.cookie_httponly', '1');
ini_set('session.cookie_samesite', 'Strict');
ini_set('session.cookie_secure', ENV === 'production' ? '1' : '0');
ini_set('session.gc_maxlifetime', (string) SESSION_LIFETIME);

error_reporting(E_ALL);
ini_set('display_errors', ENV === 'development' ? '1' : '0');
ini_set('log_errors', '1');
ini_set('error_log', __DIR__ . '/../logs/error.log');
