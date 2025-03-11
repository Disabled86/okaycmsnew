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
    $stmt->execute(['manufacturer1' => 'arte lamp',  'manufacturer3' => 'opadiris', 'manufacturer4' => 'point']);
    $results = $stmt->fetchAll();
	
	//		 OR LOWER(manufacturer) = LOWER(:manufacturer2) 
	//'manufacturer2' => 'Favourite',

// Шаг 2: Применяем условия к purchase_price
foreach ($results as &$row) {
    if ($row['purchase_price'] > 1 && $row['purchase_price'] <= 2999) {
        $row['purchase_price'] *= 1.80;
    } elseif ($row['purchase_price'] >= 3000 && $row['purchase_price'] <= 5000) {
        $row['purchase_price'] += 600;
    } elseif ($row['purchase_price'] > 5000 && $row['purchase_price'] <= 200000) {
        $row['purchase_price'] *= 1.08;
    }
}

// Шаг 3: Удаляем префикс mdd_ из manufacturer_sku и обновляем таблицу ok_variants
foreach ($results as $row) {
    $sku = str_replace('mdd_', '', $row['manufacturer_sku']);
    $stmt = $pdo->prepare('
        UPDATE ok_variants
        SET price = :price
        WHERE sku = :sku
    ');
    $stmt->execute([
        'price' => $row['purchase_price'],
        'sku' => $sku
    ]);
}

    echo 'Обновление завершено успешно.';
} catch (PDOException $e) {
    die('Ошибка выполнения запроса: ' . $e->getMessage());
}
?>