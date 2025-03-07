<?php
// Подключаем конфигурационный файл
require_once 'config.php';

// Определение пути к лог-файлу в папке со скриптом
$logFile = __DIR__ . '/mysql.log';

function logMessage($message) {
    global $logFile;
    file_put_contents($logFile, date('Y-m-d H:i:s') . " - " . $message . PHP_EOL, FILE_APPEND);
}

// Функция для подключения к базе данных
function connectDB() {
    $dsn = 'mysql:host=' . DB_HOST . ';dbname=' . DB_NAME . ';charset=utf8';
    $options = [
        PDO::ATTR_ERRMODE            => PDO::ERRMODE_EXCEPTION,
        PDO::ATTR_DEFAULT_FETCH_MODE  => PDO::FETCH_ASSOC,
        PDO::ATTR_EMULATE_PREPARES    => false,
    ];

    try {
        $pdo = new PDO($dsn, DB_USER, DB_PASSWORD, $options);
        $pdo->exec("SET NAMES 'utf8'"); // Устанавливаем кодировку UTF-8
        logMessage("Успешное подключение к базе данных");
        return $pdo;
    } catch (PDOException $e) {
        logMessage("Ошибка подключения к базе данных: " . $e->getMessage());
        throw new PDOException($e->getMessage(), (int)$e->getCode());
    }
}

// Функция для удаления префиксов из артикулов
function removePrefix($manufacturer_sku) {
    $prefixes = ['mdd_', 'bmk_', 'nova_', 'san_', 'stx_'];
    foreach ($prefixes as $prefix) {
        if (strpos($manufacturer_sku, $prefix) === 0) {
            return substr($manufacturer_sku, strlen($prefix));
        }
    }
    return $manufacturer_sku;
}

// Функция для выгрузки данных в целевую таблицу
function uploadStockDataToDB(PDO $pdo, array $stockData, string $targetTable, string $stockColumn) {
    try {
        // Подготовка SQL-запроса для вставки данных
        $sql = "UPDATE $targetTable SET $stockColumn = :total_stock WHERE sku = :manufacturer_sku";
        logMessage("Подготовка запроса: " . $sql);

        $stmt = $pdo->prepare($sql);

        // Проходим по данным и выполняем обновление
        foreach ($stockData as $manufacturer_sku => $totalStock) {
            $stmt->bindValue(':manufacturer_sku', $manufacturer_sku);
            $stmt->bindValue(':total_stock', $totalStock);
            logMessage("Выполнение запроса: " . $sql . " с параметрами: manufacturer_sku=" . $manufacturer_sku . ", total_stock=" . $totalStock);
            $stmt->execute();
            if ($stmt->rowCount() == 0) {
                logMessage("Артикул не найден для обновления: " . $manufacturer_sku);
            }
        }

        logMessage("Успешная выгрузка данных в базу данных");
        return true; // Успешная выгрузка
    } catch (PDOException $e) {
        logMessage("Ошибка при выгрузке в базу данных: " . $e->getMessage()); // Логируем ошибку
        return false; // Ошибка выгрузки
    }
}

// Подключаемся к базе данных
$pdo = connectDB();

// SQL-запрос для выборки данных с пагинацией
$pageSize = 1000; // Размер страницы
$page = 1;
$stockData = [];

do {
    $offset = ($page - 1) * $pageSize;
    $sql = "
        SELECT manufacturer_sku, stock_for_shipment, stock_in_storage, manufacturer
        FROM " . DB_TABLE . "
        WHERE provider != 'Акваарт'
        LIMIT $offset, $pageSize
    ";
    logMessage("Выполнение запроса: " . $sql);

    $stmt = $pdo->query($sql);
    $results = $stmt->fetchAll();

    foreach ($results as $row) {
        $manufacturer_sku = removePrefix($row['manufacturer_sku']);
        $stock_for_shipment = $row['stock_for_shipment'];
        $stock_in_storage = $row['stock_in_storage'];
        $manufacturer = $row['manufacturer']; // Получаем значение manufacturer

        $totalStock = $stock_for_shipment + $stock_in_storage;

        $logMessage = "Производитель: " . var_export($manufacturer, true) . "\n"; // Записываем значение производителя

        if (strtolower($manufacturer) === 'runo') {
            $logMessage .= "Добавляем префикс run_\n"; // Помечаем добавление префикса
            $manufacturer_sku = 'run_' . $manufacturer_sku;
        } elseif (strtolower($manufacturer) === 'maytoni') {
            $logMessage .= "Добавляем префикс test_\n"; // Помечаем добавление префикса
            $manufacturer_sku = '' . $manufacturer_sku;
        } else {
            $logMessage .= "Не добавляем префикс\n"; // Помечаем отсутствие добавления префикса
        }

        logMessage($logMessage); // Пишем все в лог

        if (!isset($stockData[$manufacturer_sku])) {
            $stockData[$manufacturer_sku] = 0;
        }

        $stockData[$manufacturer_sku] += $totalStock;
    }

    $page++;
} while (count($results) > 0);

// Выгрузка данных в базу
$targetTable = 'ok_variants'; // Имя целевой таблицы
$stockColumn = 'stock'; // Имя столбца для остатков
if (uploadStockDataToDB($pdo, $stockData, $targetTable, $stockColumn)) {
    echo "Данные успешно выгружены в таблицу: $targetTable, столбец: $stockColumn\n";
} else {
    echo "Произошла ошибка при выгрузке данных в таблицу: $targetTable, столбец: $stockColumn\n";
}

// Подготовка данных для CSV
$csvData = [];
$csvData[] = ['manufacturer_sku', 'total_stock'];

foreach ($stockData as $manufacturer_sku => $totalStock) {
    $csvData[] = [$manufacturer_sku, $totalStock];
}

// Формируем имя файла CSV с датой и временем
$date = date('Ymd_His'); // Формат: годмесяцдень_часыминутысекунды
$csvFile = 'archive/stock_report_' . $date . '.csv';

// Открываем файл для записи
$fp = fopen($csvFile, 'w');

// Устанавливаем кодировку для CSV файла
fputs($fp, "\xEF\xBB\xBF"); // BOM для UTF-8

// Записываем данные в CSV файл с разделителем ;
foreach ($csvData as $fields) {
    fputcsv($fp, $fields, ';');
}

// Закрываем файл
fclose($fp);

logMessage("Данные успешно выгружены в файл: $csvFile");
echo "Данные успешно выгружены в файл: $csvFile";
?>