<?php
session_start();
require_once 'config.php';

// Включаем отображение ошибок PHP
ini_set('display_errors', 1);
ini_set('display_startup_errors', 1);
error_reporting(E_ALL);

// Функция для логирования в файл
function logToFile(string $message): void
{
    $timestamp = date('Y-m-d H:i:s');
    $logMessage = "[$timestamp] $message" . PHP_EOL;
    error_log($logMessage, 3, LOG_FILE);
}

// Функция для обработки ошибок PDO
function handlePDOException(PDOException $e, string $sql = '', array $params = []): void
{
    $errorMessage = "PDO Exception: " . $e->getMessage() . PHP_EOL;
    if (!empty($sql)) {
        $errorMessage .= "SQL: " . $sql . PHP_EOL;
    }
    if (!empty($params)) {
        $errorMessage .= "Params: " . print_r($params, true) . PHP_EOL;
    }
    logToFile($errorMessage);
    echo "<div style='color:red; border: 1px solid red; padding: 10px;'>";
    echo "<b>Ошибка базы данных:</b><br>";
    echo nl2br(htmlspecialchars($errorMessage));
    echo "</div>";
    die();
}

// Функция для получения списка брендов из базы данных
function getBrands(): array
{
    try {
        $pdo = new PDO("mysql:host=" . DB_HOST . ";dbname=" . DB_NAME . ";charset=utf8mb4", DB_USER, DB_PASSWORD);
        $stmt = $pdo->prepare("SELECT id, name FROM ok_brands ORDER BY name");
        $stmt->execute();
        return $stmt->fetchAll(PDO::FETCH_KEY_PAIR);
    } catch (PDOException $e) {
        handlePDOException($e, "SELECT id, name FROM ok_brands ORDER BY name");
        return [];
    }
}

// Функция для получения списка фидов из базы данных
function getFeedSettings(): array
{
    try {
        $pdo = new PDO("mysql:host=" . DB_HOST . ";dbname=" . DB_NAME . ";charset=utf8mb4", DB_USER, DB_PASSWORD);
        $stmt = $pdo->prepare("SELECT feed_id, feed_name, include_no_image, include_out_of_stock, min_price, selected_brands, title_contains FROM feed_settings");
        $stmt->execute();
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    } catch (PDOException $e) {
        handlePDOException($e, "SELECT feed_id, feed_name, include_no_image, include_out_of_stock, min_price, selected_brands, title_contains FROM feed_settings");
        return [];
    }
}

// Функция для сохранения настроек
function saveSettings(int $feed_id, string $feed_name, bool $includeNoImage, bool $includeOutOfStock, float $minPrice, array $selectedBrands, string $titleContains): void
{
    try {
        $pdo = new PDO("mysql:host=" . DB_HOST . ";dbname=" . DB_NAME . ";charset=utf8mb4", DB_USER, DB_PASSWORD);
        // Преобразуем массив ID брендов в строку, разделенную запятыми
        $brandsString = implode(',', array_map('intval', $selectedBrands));
        $sql = "UPDATE feed_settings SET feed_name = ?, include_no_image = ?, include_out_of_stock = ?, min_price = ?, selected_brands = ?, title_contains = ? WHERE feed_id = ?";
        $stmt = $pdo->prepare($sql);
        $stmt->execute([$feed_name, $includeNoImage, $includeOutOfStock, $minPrice, $brandsString, $titleContains, $feed_id]);
    } catch (PDOException $e) {
        handlePDOException($e, "saveSettings", [$feed_id, $feed_name, $includeNoImage, $includeOutOfStock, $minPrice, $selectedBrands, $titleContains]);
    }
}

// Получаем списки брендов и фидов
$brands = getBrands();
$settings = getFeedSettings();

// Обработка отправки формы
if ($_SERVER["REQUEST_METHOD"] == "POST") {
    foreach ($settings as $setting) {
        $feed_id = intval($_POST['feed_id_' . $setting['feed_id']]);
        $feed_name = trim($_POST['feed_name_' . $setting['feed_id']]);
        $includeNoImage = isset($_POST['include_no_image_' . $setting['feed_id']]) ? 1 : 0;
        $includeOutOfStock = isset($_POST['include_out_of_stock_' . $setting['feed_id']]) ? 1 : 0;
        $minPrice = floatval(trim($_POST['min_price_' . $setting['feed_id']] ?? 0.00));
        // Получаем массив выбранных брендов
        $selectedBrands = $_POST['brands_' . $setting['feed_id']] ?? [];
        // Получаем значение "Название содержит"
        $titleContains = trim($_POST['title_contains_' . $setting['feed_id']] ?? '');

        saveSettings($feed_id, $feed_name, $includeNoImage, $includeOutOfStock, $minPrice, $selectedBrands, $titleContains);
    }

    // После сохранения настроек, перенаправляем на ту же страницу для обновления данных
    header("Location: " . $_SERVER['PHP_SELF']);
    exit;
}

?>

<!DOCTYPE html>
<html>
<head>
    <title>Настройки фидов</title>
    <link href="https://cdn.jsdelivr.net/npm/select2@4.1.0-rc.0/dist/css/select2.min.css" rel="stylesheet" />
    <style>
        .select2-container--default .select2-selection--multiple .select2-selection__choice {
            background-color: #444;
            border: 1px solid #aaa;
            border-radius: 4px;
            cursor: default;
            float: left;
            margin-right: 5px;
            margin-top: 5px;
            padding: 0 5px;
            color: white;
        }
    </style>
</head>
<body>

<h1>Настройки фидов</h1>

<form method="post">
    <?php if (empty($settings)): ?>
        <p>Нет доступных фидов.</p>
    <?php else: ?>
       <?php foreach ($settings as $setting): ?>
            <fieldset>
                <legend>Фид: <?php echo htmlspecialchars($setting['feed_name']); ?></legend>
                <input type="hidden" name="feed_id_<?php echo $setting['feed_id']; ?>" value="<?php echo $setting['feed_id']; ?>">

                <label for="feed_name_<?php echo $setting['feed_id']; ?>">Название фида:</label>
                <input type="text" name="feed_name_<?php echo $setting['feed_id']; ?>" value="<?php echo htmlspecialchars($setting['feed_name']); ?>"><br><br>

                <!--  Выбор брендов  -->
                <label for="brands_<?php echo $setting['feed_id']; ?>">Выбранные бренды:</label><br>
                <select name="brands_<?php echo $setting['feed_id']; ?>[]" id="brands_<?php echo $setting['feed_id']; ?>" multiple style="width:300px">
                    <option value="all">Выбрать все</option>
                    <?php
                    // Преобразуем строку выбранных брендов в массив
                    $selectedBrands = explode(',', $setting['selected_brands']);
                    foreach ($brands as $id => $name): ?>
                        <option value="<?php echo htmlspecialchars($id); ?>" <?php if (in_array($id, $selectedBrands)): ?>selected<?php endif; ?>>
                            <?php echo htmlspecialchars($name); ?>
                        </option>
                    <?php endforeach; ?>
                </select><br><br>

                <label for="include_no_image_<?php echo $setting['feed_id']; ?>">Выгружать товары без фото:</label>
                <input type="checkbox" name="include_no_image_<?php echo $setting['feed_id']; ?>" value="1" <?php if ($setting['include_no_image']): ?>checked<?php endif; ?>><br><br>

                <label for="include_out_of_stock_<?php echo $setting['feed_id']; ?>">Выгружать товары, которых нет в наличии:</label>
                <input type="checkbox" name="include_out_of_stock_<?php echo $setting['feed_id']; ?>" value="1" <?php if ($setting['include_out_of_stock']): ?>checked<?php endif; ?>><br><br>

                <label for="min_price_<?php echo $setting['feed_id']; ?>">Выгружать товары с ценой от:</label>
                <input type="number" step="0.01" name="min_price_<?php echo $setting['feed_id']; ?>" value="<?php echo htmlspecialchars($setting['min_price']); ?>" style="width:100px"><br><br>

                <!--  Фильтрация по названию  -->
                <label for="title_contains_<?php echo $setting['feed_id']; ?>">Название содержит:</label>
                <input type="text" name="title_contains_<?php echo $setting['feed_id']; ?>" value="<?php echo htmlspecialchars((string) $setting['title_contains']); ?>" style="width:200px"><br><br>

                <a href="avito.php?feed_id=<?php echo htmlspecialchars($setting['feed_id']); ?>" target="_blank">Экспортировать в XML</a>
            </fieldset>
            <br>
        <?php endforeach; ?>
        <button type="submit">Сохранить</button>
    <?php endif; ?>
</form>

<script src="https://code.jquery.com/jquery-3.6.0.min.js"></script>
<script src="https://cdn.jsdelivr.net/npm/select2@4.1.0-rc.0/dist/js/select2.min.js"></script>
<script>
    $(document).ready(function() {
        // Инициализируем select2 для каждого поля выбора брендов
        <?php foreach ($settings as $setting): ?>
            $('#brands_<?php echo $setting['feed_id']; ?>').select2({
                placeholder: "Выберите бренды",
                allowClear: true
            });
        <?php endforeach; ?>
    });
</script>

</body>
</html>