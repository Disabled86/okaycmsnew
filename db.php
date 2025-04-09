<?php
// Настройки базы данных
define('DB_HOST', 'localhost');
define('DB_NAME', 'waterglow_ru');
define('DB_USER', 'waterglow_ru');
define('DB_PASSWORD', '7wZyWVyxcyLDfTJV');

try {
    // Подключение к базе данных через PDO
    $dsn = "mysql:host=" . DB_HOST . ";dbname=" . DB_NAME . ";charset=utf8mb4";
    $options = [
        PDO::ATTR_ERRMODE            => PDO::ERRMODE_EXCEPTION,
        PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
        PDO::ATTR_EMULATE_PREPARES   => false,
    ];

    $pdo = new PDO($dsn, DB_USER, DB_PASSWORD, $options);

    // Получаем артикул из POST-запроса
    if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['article'])) {
        $article = trim($_POST['article']);

        // SQL-запрос для получения общего количества товара
        $stmt = $pdo->prepare("
            SELECT 
                manufacturer_sku AS article,
                (stock_in_storage + stock_for_shipment) AS total_stock
            FROM 
                stock
            WHERE 
                manufacturer_sku = :article
        ");
        $stmt->execute(['article' => $article]);
        $product = $stmt->fetch();

        if ($product) {
            // Если товар найден, формируем ответ
            $response = [
                'status' => 'success',
                'message' => "Общий остаток товара с артикулом '{$product['article']}' составляет {$product['total_stock']} шт.",
                'total_stock' => $product['total_stock']
            ];
        } else {
            // Если товар не найден
            $response = [
                'status' => 'error',
                'message' => 'Товар с таким артикулом не найден.'
            ];
        }

        // Возвращаем ответ в формате JSON
        header('Content-Type: application/json');
        echo json_encode($response);
    } else {
        // Если запрос некорректный
        http_response_code(400);
        echo json_encode(['status' => 'error', 'message' => 'Некорректный запрос.']);
    }
} catch (PDOException $e) {
    // Логирование ошибки в файл
    error_log("Ошибка базы данных: " . $e->getMessage(), 3, "error.log");

    // Возвращаем JSON с ошибкой
    http_response_code(500);
    echo json_encode(['status' => 'error', 'message' => 'Ошибка базы данных.']);
}
?>