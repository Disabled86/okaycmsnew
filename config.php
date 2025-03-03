<?php
// Настройки базы данных для остатков
define('DB_HOST', 'localhost');
define('DB_NAME', '');
define('DB_USER', '');
define('DB_PASSWORD', '');

// Путь к файлу лога
define('LOG_FILE', __DIR__ . '/app.log');

// Включить отображение ошибок (для разработки)
ini_set('display_errors', 1);
ini_set('display_startup_errors', 1);
error_reporting(E_ALL);

?>
