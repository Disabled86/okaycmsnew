<?php
// Настройки базы данных для остатков
define('DB_HOST', 'localhost');
define('DB_NAME', 'waterglow_ru');
define('DB_USER', 'waterglow_ru');
define('DB_PASSWORD', '7wZyWVyxcyLDfTJV');

// Путь к файлу лога
define('LOG_FILE', __DIR__ . '/app.log');

// Включить отображение ошибок (для разработки)
ini_set('display_errors', 1);
ini_set('display_startup_errors', 1);
error_reporting(E_ALL);

?>