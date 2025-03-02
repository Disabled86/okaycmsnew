<?php ob_start(); ?>
<?php
// avito.php
require_once 'config.php';

// Добавляем функцию logToFile
function logToFile(string $message): void
{
    $timestamp = date('Y-m-d H:i:s');
    $logMessage = "[$timestamp] $message" . PHP_EOL;
    error_log($logMessage, 3, LOG_FILE);
}

// Настройки для Avito
$formatVersion = "3";
$target = "Avito.ru";
$fixedAddress = "город Москва, Ленинградское шоссе, 16А, стр. 4";
$fixedCategory = "Мебель и интерьер";
$fixedLedLamp = "Да";
$defaultCondition = "Новое";
$imagePrefix = "https://waterglow.ru/files/originals/products/";
$defaultAdType = "Товар приобретен на продажу";
$contactPhone = "+7(980)480-15-28";
$availability = "в наличии";
$fixedGoodsSubType = "Потолочное и настенное";
$fixedGoodsType = "Освещение";
$lightingType = "Люстры";
$chandelierType = "Люстра";
$chandelierMountingType = "Подвесное";
$delivery = "Свой курьер";
$internetCalls = "Да";
$contactMethod = "По телефону и в сообщениях";

// Получаем feed_id из GET-параметра
$feed_id = isset($_GET['feed_id']) ? intval($_GET['feed_id']) : 0;
if ($feed_id <= 0) {
    die("Ошибка: Не указан feed_id в URL.");
}

// Функция для получения настроек фида
function getFeedSettings(int $feed_id): array
{
    try {
        $pdo = new PDO("mysql:host=" . DB_HOST . ";dbname=" . DB_NAME . ";charset=utf8mb4", DB_USER, DB_PASSWORD);
        $stmt = $pdo->prepare("SELECT feed_name, selected_brands, include_no_image, include_out_of_stock, min_price, title_contains FROM feed_settings WHERE feed_id = ?");
        logToFile("SQL (getFeedSettings): SELECT feed_name, selected_brands, include_no_image, include_out_of_stock, min_price, title_contains FROM feed_settings WHERE feed_id = ?");
        logToFile("Params (getFeedSettings): " . print_r([$feed_id], true));
        $stmt->execute([$feed_id]);
        $settings = $stmt->fetch(PDO::FETCH_ASSOC);

        if ($settings === false) {
            logToFile("Settings not found for feed_id: " . $feed_id);
            return []; // Возвращаем пустой массив, если настройки не найдены
        }

        logToFile("Settings found for feed_id " . $feed_id . ": " . print_r($settings, true));
        return $settings;

    } catch (PDOException $e) {
        handlePDOException($e, "SELECT feed_name, selected_brands, include_no_image, include_out_of_stock, min_price, title_contains FROM feed_settings WHERE feed_id = ?", [$feed_id]);
        return [];
    }
}

// Получение настроек фида
$feedSettings = getFeedSettings($feed_id);

// Проверяем, что настройки найдены
if (empty($feedSettings)) {
    die("Ошибка: Настройки для feed_id " . $feed_id . " не найдены.");
}

// Извлекаем настройки из массива
$feedName = $feedSettings['feed_name'] ?? '';
$selectedBrandsString = $feedSettings['selected_brands'] ?? '';
$includeNoImage = (bool) ($feedSettings['include_no_image'] ?? false);
$includeOutOfStock = (bool) ($feedSettings['include_out_of_stock'] ?? false);
$minPrice = (float) ($feedSettings['min_price'] ?? 0.00);
$titleContains = $feedSettings['title_contains'] ?? '';

// Преобразуем строку выбранных брендов в массив
$selectedBrands = ($selectedBrandsString === 'all') ? 'all' : explode(',', $selectedBrandsString);


// Получение объектов товаров
function getProductsFromDatabase(array $selectedBrands = [], bool $includeNoImage = false, bool $includeOutOfStock = false, float $minPrice = 0, string $titleContains = ''): array
{
    $pdo = new PDO("mysql:host=" . DB_HOST . ";dbname=" . DB_NAME . ";charset=utf8mb4", DB_USER, DB_PASSWORD);

    $sql = "
        SELECT p.id, p.name, p.description, p.main_image_id, p.brand_id
        FROM ok_products p
        JOIN ok_variants v ON p.id = v.product_id  --  JOIN чтобы получить цену
    ";

    $params = [];

    // Условие для брендов
    if (!empty($selectedBrands) && !in_array('all', $selectedBrands)) {
        $sql .= " WHERE p.brand_id IN (" . implode(',', array_fill(0, count($selectedBrands), '?')) . ")";
        $params = $selectedBrands;
    } else if ($selectedBrands !== 'all'){
        $sql .= " WHERE 1=1"; //  Чтобы можно было добавлять условия AND
    } else {
        //Если выбраны все бренды ничего не добавляем
        $sql .= " WHERE 1=1";
    }

    // Условие для минимальной цены
    if ($minPrice > 0) {
        $sql .= " AND v.price >= ?";
        $params[] = $minPrice;
    }

    // Условие для товаров без изображений
    if (!$includeNoImage) {
        $sql .= " AND p.main_image_id IS NOT NULL";
    }

    // Условие для товаров в наличии (stock > 0)
    if (!$includeOutOfStock) {
        $sql .= " AND EXISTS (SELECT 1 FROM ok_variants vx WHERE vx.product_id = p.id AND vx.stock > 0)";
    }

    // Условие для фильтрации по названию
    if (!empty($titleContains)) {
        $sql .= " AND p.name LIKE ?";
        $params[] = '%' . $titleContains . '%';
    }

    $sql .= " GROUP BY p.id"; //  Группируем, чтобы не было дубликатов из-за нескольких вариантов
    $sql .= " LIMIT 10"; // Ограничение количества товаров (если нужно)

    $stmt = $pdo->prepare($sql);
    $stmt->execute($params);
    $products = $stmt->fetchAll(PDO::FETCH_CLASS, 'stdClass');

    // Получаем характеристики для каждого товара
    foreach ($products as $product) {
        $product->features = getProductFeatures($pdo, $product->id);
    }

    return $products;
}

// Функция для получения характеристик товара
function getProductFeatures(PDO $pdo, int $product_id): array
{
    $stmt = $pdo->prepare("
        SELECT f.name, fv.value
        FROM ok_products_features_values pfv
        JOIN ok_features_values fv ON pfv.value_id = fv.id
        JOIN ok_features f ON fv.feature_id = f.id
        WHERE pfv.product_id = ?
    ");
    $stmt->execute([$product_id]);
    return $stmt->fetchAll(PDO::FETCH_ASSOC);
}

//Функция для получения цены
function getPrice(int $product_id): string
{
    // Здесь нужно реализовать код для получения цены товара из вашей базы данных.
    //  Должна вернуть цену товара.
    $pdo = new PDO("mysql:host=" . DB_HOST . ";dbname=" . DB_NAME . ";charset=utf8mb4", DB_USER, DB_PASSWORD);

    $stmt = $pdo->prepare("
        SELECT price
        FROM ok_variants
        WHERE product_id = ?
        LIMIT 1
    ");
    //logToFile("SQL (getPrice): " . $stmt->queryString);
    //logToFile("Params (getPrice): " . print_r([$product_id], true));
    $stmt->execute([$product_id]);
    $price = $stmt->fetchColumn();
    return $price ?: "";
}

//Функция для получения url главной картинки
function getImageUrl(int $main_image_id): string
{
    global $imagePrefix;
    $pdo = new PDO("mysql:host=" . DB_HOST . ";dbname=" . DB_NAME . ";charset=utf8mb4", DB_USER, DB_PASSWORD);
    try {
        if (empty($main_image_id)) {
            return "";
        }

        $stmt = $pdo->prepare("
            SELECT filename
            FROM ok_images
            WHERE id = ?
        ");
        //logToFile("SQL (getImageUrl): " . $stmt->queryString);
        //logToFile("Params (getImageUrl): " . print_r([$main_image_id], true));
        $stmt->execute([$main_image_id]);
        $image = $stmt->fetch();

        if ($image && !empty($image['filename'])) {
            return $imagePrefix . $image['filename']; // Добавляем префикс к имени файла
        } else {
            return "";
        }
    } catch (PDOException $e) {
        //logToFile("Ошибка при получении URL-адреса изображения: " . $e->getMessage());
        return "";
    }
}

//Функция для получения url дополнительных картинок
function getAdditionalImageUrls(int $product_id): array
{
    global $imagePrefix;
    $pdo = new PDO("mysql:host=" . DB_HOST . ";dbname=" . DB_NAME . ";charset=utf8mb4", DB_USER, DB_PASSWORD);
    try {
        $stmt = $pdo->prepare("
            SELECT filename
            FROM ok_images
            WHERE product_id = ?
        ");
      //logToFile("SQL (getAdditionalImageUrls): " . $stmt->queryString);
      //logToFile("Params (getAdditionalImageUrls): " . print_r([$product_id], true));
        $stmt->execute([$product_id]);
        $images = $stmt->fetchAll();

        $image_urls = [];
        foreach ($images as $image) {
            if (!empty($image['filename'])) {
                $image_urls[] = $imagePrefix . $image['filename'];
            }
        }

        return $image_urls;

    } catch (PDOException $e) {
        //logToFile("Ошибка при получении URL-адресов дополнительных изображений: " . $e->getMessage());
        return [];
    }
}

//Функция для получения бренда
function getBrandName(int $product_id): string
{
    $pdo = new PDO("mysql:host=" . DB_HOST . ";dbname=" . DB_NAME . ";charset=utf8mb4", DB_USER, DB_PASSWORD);
    try {
        $stmt = $pdo->prepare("
            SELECT b.name
            FROM ok_products p
            JOIN ok_brands b ON p.brand_id = b.id
            WHERE p.id = ?
        ");
      //logToFile("SQL (getBrandName): " . $stmt->queryString);
      //logToFile("Params (getBrandName): " . print_r([$product_id], true));
        $stmt->execute([$product_id]);
        $brand = $stmt->fetch();

        if (!$brand) {
            return "";
        }

        return $brand['name'];
    } catch (PDOException $e) {
        //logToFile("Ошибка при получении бренда: " . $e->getMessage());
        return "";
    }
}

// Получаем объекты товаров
$products = getProductsFromDatabase($selectedBrands, $includeNoImage, $includeOutOfStock, $minPrice, $titleContains);

// Формируем имя файла
$filename = !empty($feedName) ? $feedName . '.xml' : 'feed.xml';

// Заголовки для XML-файла - перенесены в конец файла
?>
<?php
$xml = ob_get_contents(); // Получаем содержимое буфера
ob_end_clean(); //Очищаем буфер

header('Content-Disposition: attachment; filename="' . $filename . '"');
header('Content-type: text/xml');

echo '<?xml version="1.0" encoding="UTF-8"?>' . PHP_EOL;
echo "<Ads formatVersion=\"{$formatVersion}\" target=\"{$target}\">" . PHP_EOL;

// Генерируем XML
foreach ($products as $product) {
    echo "  <Ad>" . PHP_EOL;
    echo "    <Id>" . htmlspecialchars($product->id) . "</Id>" . PHP_EOL;
    echo "    <Title>" . htmlspecialchars($product->name) . "</Title>" . PHP_EOL;

    // Формирование описания товара
    $description = ""; // Инициализируем пустую строку

    if (empty($product->description)) {
        // Если описания нет, добавляем название товара жирным шрифтом
        $description .= "<b>" . htmlspecialchars($product->name) . "</b><br><br>";
    } else {
        // Если описание есть, добавляем его
        $description .= htmlspecialchars($product->description) . "<br><br>";
    }

    // Добавляем характеристики (независимо от наличия описания)
    $description .= "<b>Характеристики:</b><ul>"; // Начало списка характеристик

    if (isset($product->features) && is_array($product->features)) {
        foreach ($product->features as $feature) {
            $description .= "<li>" . htmlspecialchars($feature['name']) . ": " . htmlspecialchars($feature['value']) . "</li>";
        }
    }

    $description .= "</ul>"; // Закрытие списка характеристик

    echo "    <Description><![CDATA[" . $description . "]]></Description>" . PHP_EOL;

    // Получаем цену (замените на ваш код получения цены)
    $price = getPrice($product->id); // Замените на ваш код получения цены
    echo "    <Price>" . htmlspecialchars($price) . "</Price>" . PHP_EOL;

    echo "    <Category>" . htmlspecialchars($fixedCategory) . "</Category>" . PHP_EOL;

    // Упрощенная логика с изображениями
    echo "    <Images>" . PHP_EOL;
    $addedImageUrls = []; // Массив для отслеживания добавленных URL-адресов изображений
    // Получаем URL-адрес главной картинки (замените на ваш код)
    $main_image_url = ($product->main_image_id !== null) ? getImageUrl($product->main_image_id) : "";
    if (!empty($main_image_url) && !in_array($main_image_url, $addedImageUrls)) {
        echo "      <Image url=\"" . htmlspecialchars(trim($main_image_url)) . "\"/>" . PHP_EOL;
        $addedImageUrls[] = $main_image_url; // Добавляем URL-адрес в массив
    }

    // Получаем URL-адреса дополнительных изображений (замените на ваш код)
    $additional_image_urls = getAdditionalImageUrls($product->id);
    foreach ($additional_image_urls as $image_url) {
        if (!empty($image_url) && !in_array($image_url, $addedImageUrls)) {
            echo "      <Image url=\"" . htmlspecialchars(trim($image_url)) . "\"/>" . PHP_EOL;
            $addedImageUrls[] = $image_url; // Добавляем URL-адрес в массив
        }
    }
    echo "    </Images>" . PHP_EOL;

    echo "    <Address>" . htmlspecialchars($fixedAddress) . "</Address>" . PHP_EOL;
    echo "    <LedLamp>" . htmlspecialchars($fixedLedLamp) . "</LedLamp>" . PHP_EOL;

    // Получаем бренд (замените на ваш код)
    $brand = getBrandName($product->id);
    echo "    <Brand>" . htmlspecialchars($brand) . "</Brand>" . PHP_EOL;

    echo "    <GoodsType>" . htmlspecialchars($fixedGoodsType) . "</GoodsType>" . PHP_EOL;
    echo "    <GoodsSubType>" . htmlspecialchars($fixedGoodsSubType) . "</GoodsSubType>" . PHP_EOL;
    echo "    <LightingType>" . htmlspecialchars($lightingType) . "</LightingType>" . PHP_EOL;
    echo "    <ChandelierType>" . htmlspecialchars($chandelierType) . "</ChandelierType>" . PHP_EOL;
    echo "    <ChandelierMountingType>" . htmlspecialchars($chandelierMountingType) . "</ChandelierMountingType>" . PHP_EOL;
    echo "    <Delivery>" . htmlspecialchars($delivery) . "</Delivery>" . PHP_EOL;
    echo "    <InternetCalls>" . htmlspecialchars($internetCalls) . "</InternetCalls>" . PHP_EOL;
    echo "    <ContactMethod>" . htmlspecialchars($contactMethod) . "</ContactMethod>" . PHP_EOL;
    echo "    <Condition>" . htmlspecialchars($defaultCondition) . "</Condition>" . PHP_EOL;
    echo "    <AdType>" . htmlspecialchars($defaultAdType) . "</AdType>" . PHP_EOL;
    echo "    <ContactPhone>" . htmlspecialchars($contactPhone) . "</ContactPhone>" . PHP_EOL;
    echo "    <Availability>" . htmlspecialchars($availability) . "</Availability>" . PHP_EOL;
    echo "  </Ad>" . PHP_EOL;
}

echo "</Ads>" . PHP_EOL;
?>
<?php
$xml = ob_get_clean(); // Получаем содержимое буфера и очищаем его
header('Content-Disposition: attachment; filename="' . $filename . '"');
header('Content-type: text/xml');
echo $xml; // Выводим XML-код
?>