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

// Функция для обработки исключений PDO
function handlePDOException(PDOException $e, string $sql, array $params): void
{
    logToFile("PDOException: " . $e->getMessage());
    logToFile("SQL: " . $sql);
    logToFile("Params: " . print_r($params, true));
    die("Database error occurred. Check logs for details.");
}

// Настройки для Avito (общие)
$defaultCondition = "Новое";
$imagePrefix = "https://waterglow.ru/files/originals/products/";
$defaultAdType = "Товар приобретен на продажу";
$contactPhone = "+7(980)480-15-28";
$availability = "в наличии";
$fixedAddress = "город Москва, Ленинградское шоссе, 16А, стр. 4";
$material = "Металл";
const MAX_IMAGES = 10; // Максимальное количество изображений

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
        $stmt = $pdo->prepare("SELECT feed_name, selected_brands, include_no_image, include_out_of_stock, min_price, title_contains, exclude_words FROM feed_settings WHERE feed_id = ?");
        logToFile("SQL (getFeedSettings): SELECT feed_name, selected_brands, include_no_image, include_out_of_stock, min_price, title_contains, exclude_words FROM feed_settings WHERE feed_id = ?");
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
        handlePDOException($e, "SELECT feed_name, selected_brands, include_no_image, include_out_of_stock, min_price, title_contains, exclude_words FROM feed_settings WHERE feed_id = ?", [$feed_id]);
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
$excludeWords = $feedSettings['exclude_words'] ?? '';

// Преобразуем строку выбранных брендов в массив
$selectedBrands = ($selectedBrandsString === 'all') ? 'all' : explode(',', $selectedBrandsString);

// Получение объектов товаров
function getProductsFromDatabase(array $selectedBrands = [], bool $includeNoImage = false, bool $includeOutOfStock = false, float $minPrice = 0, string $titleContains = '', string $excludeWords = ''): array
{
    $pdo = new PDO("mysql:host=" . DB_HOST . ";dbname=" . DB_NAME . ";charset=utf8mb4", DB_USER, DB_PASSWORD);

    $sql = "
        SELECT
            p.id,
            p.name,
            p.description,
            p.main_image_id,
            p.brand_id,
            v.id_aprel,
            v.`category_av`,
            v.goodssubtype,
            v.goodstype,
            v.`BathroomAccessoriestype`,
            v.`producttype`,
            v.`BathMaterial`,
            v.`BathShape`,
            v.`BathJacuzzi`,
            v.`BathWidth`,
            v.`chandeliermountingtype`,    
            v.`chandeliertype`,                
            v.`ligitingtype`,  
            v.`installationfunction`,	
            v.`installationkit`,	
            v.`kit`,	
            v.`toiletinstailationtype`,		



			
			
            v.`BathLength`
        FROM ok_products p
        JOIN ok_variants v ON p.id = v.product_id
    ";

    $params = [];

    // Условие для брендов
    if (!empty($selectedBrands) && $selectedBrands !== 'all') {
        $sql .= " WHERE p.brand_id IN (" . implode(',', array_fill(0, count($selectedBrands), '?')) . ")";
        $params = $selectedBrands;
    } else {
        $sql .= " WHERE 1=1"; //  Чтобы можно было добавлять условия AND
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

    // Условие для исключения товаров по словам
    if (!empty($excludeWords)) {
        $excludeWordsArray = array_map('trim', explode(',', $excludeWords));
        $placeholders = implode(' AND p.name NOT LIKE ?', array_fill(0, count($excludeWordsArray), null));
        $sql .= " AND p.name NOT LIKE ?" . $placeholders;
        foreach ($excludeWordsArray as $word) {
            $params[] = '%' . $word . '%';
        }
    }

    $sql .= " GROUP BY p.id"; //  Группируем, чтобы не было дубликатов из-за нескольких вариантов
    $sql .= " LIMIT 10000"; // Ограничение количества товаров (если нужно)

    // Log SQL и параметры
    logToFile("SQL (getProductsFromDatabase): " . $sql);
    logToFile("Params (getProductsFromDatabase): " . print_r($params, true));

    try {
        $stmt = $pdo->prepare($sql);
        $stmt->execute($params);
        $products = $stmt->fetchAll(PDO::FETCH_CLASS, 'stdClass');

        // Получаем характеристики для каждого товара
        foreach ($products as $product) {
            $product->features = getProductFeatures($pdo, $product->id);
        }

        return $products;

    } catch (PDOException $e) {
        handlePDOException($e, $sql, $params);
        return []; // Возвращаем пустой массив в случае ошибки
    }
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
    $pdo = new PDO("mysql:host=" . DB_HOST . ";dbname=" . DB_NAME . ";charset=utf8mb4", DB_USER, DB_PASSWORD);

    $stmt = $pdo->prepare("
        SELECT price
        FROM ok_variants
        WHERE product_id = ?
        LIMIT 1
    ");
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
        $stmt->execute([$main_image_id]);
        $image = $stmt->fetch();

        if ($image && !empty($image['filename'])) {
            return $imagePrefix . $image['filename']; // Добавляем префикс к имени файла
        } else {
            return "";
        }
    } catch (PDOException $e) {
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
        $stmt->execute([$product_id]);
        $brand = $stmt->fetch();

        if (!$brand) {
            return "";
        }

        return $brand['name'];
    } catch (PDOException $e) {
        return "";
    }
}

// Функция для генерации XML-тега
function generateXmlTag(string $tagName, string $value): string {
    if (!empty($value)) {
        return "    <" . htmlspecialchars($tagName) . ">" . htmlspecialchars($value) . "</" . htmlspecialchars($tagName) . ">" . PHP_EOL;
    }
    return "";
}

// Функция для проверки наличия всех значений в товаре для каждой категории
function validateProductTags($product): bool
{
    $requiredTags = [];

    if ($product->id_aprel == 'san') {
        $requiredTags = [
            'name',
            'description',
            'main_image_id',
            'category_av',
            'goodssubtype',
            'goodstype',
            'producttype',
            'BathroomAccessoriestype',
            'BathMaterial',
            'BathShape',
            'BathJacuzzi',
            'BathWidth',
            'BathLength',
            'installationkit',			
            'installationfunction',
            'kit',			
            'toiletinstailationtype',				
			
			
			
			
        ];
    } elseif ($product->id_aprel == 'svet') {
        $requiredTags = [
            'name',
            'description',
            'main_image_id',
            'category_av',
            'goodssubtype',
            'goodstype',
            'chandeliermountingtype',
            'chandeliertype',
            'ligitingtype',
        ];
    }



    return true;
}

// Получаем объекты товаров
$products = getProductsFromDatabase($selectedBrands, $includeNoImage, $includeOutOfStock, $minPrice, $titleContains, $excludeWords);

// Формируем имя файла
$filename = !empty($feedName) ? $feedName . '.xml' : 'feed.xml';

// Заголовки для XML-файла
header('Content-Disposition: attachment; filename="' . $filename . '"');
header('Content-type: text/xml');

echo '<?xml version="1.0" encoding="UTF-8"?>' . PHP_EOL;
echo "<Ads formatVersion=\"3\" target=\"Avito.ru\">" . PHP_EOL;

// Генерируем XML
foreach ($products as $product) {
    // Проверяем наличие данных для всех тегов
    if (!validateProductTags($product)) {
        continue; // Пропускаем товар, если хотя бы один из тегов пустой
    }

    $xml = ""; // Инициализируем переменную для хранения XML-кода товара

    // Получаем URL-адрес главной картинки
    $main_image_url = getImageUrl($product->main_image_id);
    if (empty($main_image_url)) {
        // Пропускаем товар, если нет главной картинки и настройка includeNoImage отключена
        continue;
    }

    // Общие значения по умолчанию
    $formatVersion = "3";
    $target = "Avito.ru";
    $fixedAddress = "город Москва, Ленинградское шоссе, 16А, стр. 4";
    $defaultCondition = "Новое";
    $imagePrefix = "https://waterglow.ru/files/originals/products/";
    $defaultAdType = "Товар приобретен на продажу";
    $contactPhone = "+7(980)480-15-28";
    $availability = "в наличии";

    if ($product->id_aprel == 'san') {
        $fixedCategory = isset($product->category_av) ? htmlspecialchars($product->category_av) : "Не указано";
        $fixedGoodsSubType = isset($product->goodssubtype) ? htmlspecialchars($product->goodssubtype) : "Не указано";
        $fixedGoodsType = isset($product->goodstype) ? htmlspecialchars($product->goodstype) : "Не указано";
        $delivery = "Свой курьер";
        $internetCalls = "Да";
        $contactMethod = "По телефону и в сообщениях";
        $producttype = isset($product->producttype) ? htmlspecialchars($product->producttype) : "";
        $bathroombccessoriestype = isset($product->BathroomAccessoriestype) ? htmlspecialchars($product->BathroomAccessoriestype) : "";
        $BathMaterial = isset($product->BathMaterial) ? htmlspecialchars($product->BathMaterial) : "";
        $BathShape = isset($product->BathShape) ? htmlspecialchars($product->BathShape) : "";
        $BathJacuzzi = isset($product->BathJacuzzi) ? htmlspecialchars($product->BathJacuzzi) : "";
        $BathWidth = isset($product->BathWidth) ? htmlspecialchars($product->BathWidth) : "";
        $BathLength = isset($product->BathLength) ? htmlspecialchars($product->BathLength) : "";
        $installationfunction = isset($product->installationfunction) ? htmlspecialchars($product->installationfunction) : ""; 
        $installationkit = isset($product->installationkit) ? htmlspecialchars($product->installationkit) : ""; 	
        $kit = isset($product->kit) ? htmlspecialchars($product->kit) : ""; 		
        $toiletinstailationtype = isset($product->toiletinstailationtype) ? htmlspecialchars($product->toiletinstailationtype) : ""; 		
		
		
		
		
		
		
		
    } elseif ($product->id_aprel == 'svet') {
        $fixedCategory = "Мебель и интерьер";
        $fixedGoodsSubType = "Потолочное и настенное";
        $fixedGoodsType = "Освещение";
        $delivery = "Свой курьер";
        $internetCalls = "Да";
        $contactMethod = "По телефону и в сообщениях";
        $numberoflamps = "1";
        $ledlamp = "Да";
        $category_av = isset($product->category_av) ? htmlspecialchars($product->category_av) : "";
        $chandeliermountingtype = isset($product->chandeliermountingtype) ? htmlspecialchars($product->chandeliermountingtype) : "";
        $chandeliertype = isset($product->chandeliertype) ? htmlspecialchars($product->chandeliertype) : "";
        $ligitingtype = isset($product->ligitingtype) ? htmlspecialchars($product->ligitingtype) : "";        
        $producttype = isset($product->producttype) ? htmlspecialchars($product->producttype) : "";        
    
    } else {
        // Значения по умолчанию для других id_aprel
        $fixedCategory = "Разное";
        $fixedGoodsSubType = "Разное";
        $fixedGoodsType = "Разное";
        $delivery = "Разное";
        $internetCalls = "Нет";
        $contactMethod = "Разное";
        $numberoflamps = "1";
    }

    $xml .= "  <Ad>" . PHP_EOL;
    $xml .= "    <Id>" . htmlspecialchars($product->id) . "</Id>" . PHP_EOL;
    $xml .= "    <Title>" . htmlspecialchars($product->name) . "</Title>" . PHP_EOL;

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

    $xml .= "    <Description><![CDATA[" . $description . "]]></Description>" . PHP_EOL;

    // Получаем цену
    $price = getPrice($product->id);
    $xml .= "    <Price>" . htmlspecialchars($price) . "</Price>" . PHP_EOL;

    $xml .= "    <Category>" . $fixedCategory . "</Category>" . PHP_EOL;

    // Упрощенная логика с изображениями
    $xml .= "    <Images>" . PHP_EOL;
    $addedImageUrls = []; // Массив для отслеживания добавленных URL-адресов изображений
    $imageCount = 0;

    if (!empty($main_image_url) && !in_array($main_image_url, $addedImageUrls) && $imageCount < MAX_IMAGES) {
        $xml .= "      <Image url=\"" . htmlspecialchars(trim($main_image_url)) . "\"/>" . PHP_EOL;
        $addedImageUrls[] = $main_image_url; // Добавляем URL-адрес в массив
        $imageCount++;
    }

    // Получаем URL-адреса дополнительных изображений
    $additional_image_urls = getAdditionalImageUrls($product->id);
    foreach ($additional_image_urls as $image_url) {
        if (!empty($image_url) && !in_array($image_url, $addedImageUrls) && $imageCount < MAX_IMAGES) {
            $xml .= "      <Image url=\"" . htmlspecialchars(trim($image_url)) . "\"/>" . PHP_EOL;
            $addedImageUrls[] = $image_url; // Добавляем URL-адрес в массив
            $imageCount++;
        }
        if ($imageCount >= MAX_IMAGES) {
            break; // Прерываем цикл, если достигнуто максимальное количество изображений
        }
    }
    
    $xml .= "    </Images>" . PHP_EOL;
    $brand = getBrandName($product->id);
    $xml .= "    <Brand>" . htmlspecialchars($brand) . "</Brand>" . PHP_EOL;
    $xml .= "    <Address>" . htmlspecialchars($fixedAddress) . "</Address>" . PHP_EOL;
    $xml .= "    <Condition>" . htmlspecialchars($defaultCondition) . "</Condition>" . PHP_EOL;
    $xml .= "    <AdType>" . htmlspecialchars($defaultAdType) . "</AdType>" . PHP_EOL;
    $xml .= "    <ContactPhone>" . htmlspecialchars($contactPhone) . "</ContactPhone>" . PHP_EOL;
    $xml .= "    <Availability>" . htmlspecialchars($availability) . "</Availability>" . PHP_EOL;
    $xml .= "    <ContactMethod>" . htmlspecialchars($contactMethod) . "</ContactMethod>" . PHP_EOL;
    $xml .= "    <GoodsType>" . htmlspecialchars($fixedGoodsType) . "</GoodsType>" . PHP_EOL;
    $xml .= "    <GoodsSubType>" . htmlspecialchars($fixedGoodsSubType) . "</GoodsSubType>" . PHP_EOL;
    $xml .= "    <Delivery>" . htmlspecialchars($delivery) . "</Delivery>" . PHP_EOL;
    $xml .= "    <InternetCalls>" . htmlspecialchars($internetCalls) . "</InternetCalls>" . PHP_EOL;

    if ($product->id_aprel == 'san') {  // Настройки для сантехники
        $xml .= generateXmlTag("ProductType", $producttype);
        $xml .= generateXmlTag("BathroomAccessoriestype", $bathroombccessoriestype);
        $xml .= generateXmlTag("BathMaterial", $BathMaterial);
        $xml .= generateXmlTag("BathShape", $BathShape);
        $xml .= generateXmlTag("BathJacuzzi",  $BathJacuzzi);
        $xml .= generateXmlTag("BathWidth", $BathWidth);
        $xml .= generateXmlTag("BathLength", $BathLength);
        $xml .= generateXmlTag("InstallationFunction", $installationfunction);		
        $xml .= generateXmlTag("InstallationKit", $installationkit);			
        $xml .= generateXmlTag("Kit", $kit);			
        $xml .= generateXmlTag("ToiletInstallationType", $toiletinstailationtype);			
		
		
		
		
		
		
        
    }  elseif ($product->id_aprel == 'svet') { // Настройки для освещения
        $xml .= "    <NumberOfLamps>" . htmlspecialchars($numberoflamps) . "</NumberOfLamps>" . PHP_EOL;
        $xml .= "    <ChandelierMountingType>" . htmlspecialchars($chandeliermountingtype) . "</ChandelierMountingType>" . PHP_EOL;    
        $xml .= "    <LedLamp>" . htmlspecialchars($ledlamp) . "</LedLamp>" . PHP_EOL;
        $xml .= "    <ChandelierType>" . htmlspecialchars($chandeliertype) . "</ChandelierType>" . PHP_EOL;
        $xml .= "    <LigitingType>" . htmlspecialchars($ligitingtype) . "</LigitingType>" . PHP_EOL;
        $xml .= "    <Material>" . htmlspecialchars($material) . "</Material>" . PHP_EOL;        
    }

    $xml .= "  </Ad>" . PHP_EOL;

    echo $xml;
}

echo "</Ads>" . PHP_EOL;