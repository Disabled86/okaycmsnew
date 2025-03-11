<?php
// Подключаем конфигурационный файл
require_once 'config.php';

// Функция для подключения к базе данных
function getPDOConnection() {
    try {
        $dsn = 'mysql:host=' . DB_HOST . ';dbname=' . DB_NAME . ';charset=utf8';
        $options = [
            PDO::ATTR_ERRMODE            => PDO::ERRMODE_EXCEPTION,
            PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
            PDO::ATTR_EMULATE_PREPARES   => false,
        ];
        return new PDO($dsn, DB_USER, DB_PASSWORD, $options);
    } catch (PDOException $e) {
        die('Подключение не удалось: ' . $e->getMessage());
    }
}

// Получаем соединение с базой данных
$pdo = getPDOConnection();

// Шаг 1: Выполняем запрос к таблице stock
try {
    $stmt = $pdo->prepare('
        SELECT manufacturer_sku, purchase_price, retail_price
        FROM ' . DB_TABLE . '
        WHERE LOWER(manufacturer) = LOWER(:manufacturer1) 

		OR LOWER(manufacturer) = LOWER(:manufacturer3)
		OR LOWER(manufacturer) = LOWER(:manufacturer4)
    ');
    $stmt->execute(['manufacturer1' => 'arte lamp', 'manufacturer3' => 'opadiris', 'manufacturer4' => 'point']);
    $results = $stmt->fetchAll();
// , 'manufacturer2' => 'Favourite'
// OR LOWER(manufacturer) = LOWER(:manufacturer2) 


    // Шаг 2: Удаляем префикс mdd_ из manufacturer_sku и обновляем таблицу ok_variants
    foreach ($results as $row) {
        $sku = str_replace('mdd_', '', $row['manufacturer_sku']);
        $stmt = $pdo->prepare('
            UPDATE ok_variants
            SET price = :price
            WHERE sku = :sku
        ');
        $stmt->execute([
            'price' => $row['retail_price'],
            'sku' => $sku
        ]);
    }

    echo 'Обновление завершено успешно.';
} catch (PDOException $e) {
    die('Ошибка выполнения запроса: ' . $e->getMessage());
}
?>