<?php
require 'db.php';

// Включение отображения ошибок PHP
ini_set('display_errors', 1);
ini_set('display_startup_errors', 1);
error_reporting(E_ALL);

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

// Функция для получения данных заказа по номеру
function getOrderDetails(PDO $pdo, int $order_id): array
{
    $stmt = $pdo->prepare("
        SELECT ok_orders.id, ok_orders.delivery_price, ok_orders.name, ok_orders.last_name, ok_orders.phone, ok_orders.comment, ok_orders.note, ok_orders.status_id,
               GROUP_CONCAT(ok_purchases.sku SEPARATOR '; ') as sku,
               GROUP_CONCAT(ok_purchases.product_name SEPARATOR '; ') as product_name
        FROM ok_orders
        LEFT JOIN ok_purchases ON ok_orders.id = ok_purchases.order_id
        WHERE ok_orders.id = ?
        GROUP BY ok_orders.id
        ORDER BY ok_orders.id DESC
    ");
    $stmt->execute([$order_id]);
    return $stmt->fetchAll(PDO::FETCH_ASSOC);
}

$selectedProducts = isset($_POST['selected_products']) ? explode(',', $_POST['selected_products']) : [];

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['export'])) {
    if (count($selectedProducts) > 0) {
        $placeholders = str_repeat('?,', count($selectedProducts) - 1) . '?';
        $stmt = $pdo->prepare("SELECT v.sku AS artikul_modifikatsii, v.sku, v.purchaseprice, v.compare_price, p.id AS product_id, p.name AS naimenovanie, GROUP_CONCAT(CONCAT('https://waterglow.ru/files/originals/products/', i.filename) SEPARATOR ';') AS images
                               FROM ok_variants v
                               JOIN ok_images i ON v.product_id = i.product_id
                               JOIN ok_products p ON v.product_id = p.id
                               WHERE v.sku IN ($placeholders)
                               GROUP BY v.sku, v.purchaseprice, v.compare_price, p.id, p.name");
        $stmt->execute($selectedProducts);
        $results = $stmt->fetchAll(PDO::FETCH_OBJ);

        // Получаем характеристики для каждого товара
        $allFeatures = [];
        foreach ($results as $product) {
            $product->features = getProductFeatures($pdo, $product->product_id);
            foreach ($product->features as $feature) {
                $featureName = 'Свойство: ' . $feature['name'];
                if (!in_array($featureName, $allFeatures)) {
                    $allFeatures[] = $featureName;
                }
            }
        }

        $csvData = [];
        $header = ['Артикул', 'Артикул модификации', 'Закупочная цена', 'Цена', 'Наименование', 'Фото товара', 'Категория'];
        $csvData[] = array_merge($header, $allFeatures);

        foreach ($results as $product) {
            $row = [
                $product->sku,
                $product->artikul_modifikatsii,
                $product->purchaseprice,
                $product->compare_price,
                $product->naimenovanie,
                $product->images,
                'ArteLame'
            ];

            $features = [];
            foreach ($allFeatures as $featureName) {
                $featureValue = '';
                foreach ($product->features as $feature) {
                    if ('Свойство: ' . $feature['name'] === $featureName) {
                        $featureValue = $feature['value'];
                        break;
                    }
                }
                $features[] = $featureValue;
            }

            $csvData[] = array_merge($row, $features);
        }

        $filename = 'product_data_' . date('Ymd') . '.csv';
        header('Content-Type: text/csv; charset=utf-8');
        header('Content-Disposition: attachment; filename="' . $filename . '"');

        $output = fopen('php://output', 'w');
        // Установим правильную кодировку и разделитель
        fprintf($output, chr(0xEF).chr(0xBB).chr(0xBF)); // Добавляем BOM для правильного отображения UTF-8 в Excel
        foreach ($csvData as $line) {
            fputcsv($output, $line, ';');
        }
        fclose($output);
        exit;
    } else {
        echo "Выберите хотя бы один товар для экспорта.";
    }
}

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['export_order'])) {
    $order_id = $_POST['order_id'];
    if (!empty($order_id)) {
        $orderDetails = getOrderDetails($pdo, $order_id);
        if (!empty($orderDetails)) {
            $skuList = explode('; ', $orderDetails[0]['sku']);
            $placeholders = str_repeat('?,', count($skuList) - 1) . '?';
            $stmt = $pdo->prepare("SELECT v.sku AS artikul_modifikatsii, v.sku, v.purchaseprice, v.compare_price, p.id AS product_id, p.name AS naimenovanie, GROUP_CONCAT(CONCAT('https://waterglow.ru/files/originals/products/', i.filename) SEPARATOR ';') AS images
                                   FROM ok_variants v
                                   JOIN ok_images i ON v.product_id = i.product_id
                                   JOIN ok_products p ON v.product_id = p.id
                                   WHERE v.sku IN ($placeholders)
                                   GROUP BY v.sku, v.purchaseprice, v.compare_price, p.id, p.name");
            $stmt->execute($skuList);
            $results = $stmt->fetchAll(PDO::FETCH_OBJ);

            // Получаем характеристики для каждого товара
            $allFeatures = [];
            foreach ($results as $product) {
                $product->features = getProductFeatures($pdo, $product->product_id);
                foreach ($product->features as $feature) {
                    $featureName = 'Свойство: ' . $feature['name'];
                    if (!in_array($featureName, $allFeatures)) {
                        $allFeatures[] = $featureName;
                    }
                }
            }

            $csvData = [];
            $header = ['Артикул', 'Артикул модификации', 'Закупочная цена', 'Цена', 'Наименование', 'Фото товара', 'Категория'];
            $csvData[] = array_merge($header, $allFeatures);

            foreach ($results as $product) {
                $row = [
                    $product->sku,
                    $product->artikul_modifikatsii,
                    $product->purchaseprice,
                    $product->compare_price,
                    $product->naimenovanie,
                    $product->images,
                    'ArteLame'
                ];

                $features = [];
                foreach ($allFeatures as $featureName) {
                    $featureValue = '';
                    foreach ($product->features as $feature) {
                        if ('Свойство: ' . $feature['name'] === $featureName) {
                            $featureValue = $feature['value'];
                            break;
                        }
                    }
                    $features[] = $featureValue;
                }

                $csvData[] = array_merge($row, $features);
            }

            $filename = 'order_' . $order_id . '_products_' . date('Ymd') . '.csv';
            header('Content-Type: text/csv; charset=utf-8');
            header('Content-Disposition: attachment; filename="' . $filename . '"');

            $output = fopen('php://output', 'w');
            // Установим правильную кодировку и разделитель
            fprintf($output, chr(0xEF).chr(0xBB).chr(0xBF)); // Добавляем BOM для правильного отображения UTF-8 в Excel
            foreach ($csvData as $line) {
                fputcsv($output, $line, ';');
            }
            fclose($output);
            exit;
        } else {
            echo "Заказ не найден.";
        }
    } else {
        echo "Введите номер заказа.";
    }
}

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['export_no_images'])) {
    $stmt = $pdo->prepare("SELECT v.sku, p.name
                           FROM ok_variants v
                           JOIN ok_products p ON v.product_id = p.id
                           LEFT JOIN ok_images i ON v.product_id = i.product_id
                           WHERE i.product_id IS NULL");
    $stmt->execute();
    $results = $stmt->fetchAll(PDO::FETCH_ASSOC);

    $csvData = [];
    $csvData[] = ['Артикул', 'Наименование'];

    foreach ($results as $row) {
        $csvData[] = [
            $row['sku'],
            $row['name']
        ];
    }

    $filename = 'products_without_images_' . date('Ymd') . '.csv';
    header('Content-Type: text/csv; charset=utf-8');
    header('Content-Disposition: attachment; filename="' . $filename . '"');

    $output = fopen('php://output', 'w');
    // Установим правильную кодировку и разделитель
    fprintf($output, chr(0xEF).chr(0xBB).chr(0xBF)); // Добавляем BOM для правильного отображения UTF-8 в Excel
    foreach ($csvData as $line) {
        fputcsv($output, $line, ';');
    }
    fclose($output);
    exit;
}

if ($_SERVER['REQUEST_METHOD'] === 'GET' && isset($_GET['search'])) {
    $artikul = trim($_GET['artikul']);
    $results = [];
    if (!empty($artikul)) {
        $stmt = $pdo->prepare("SELECT v.sku AS artikul_modifikatsii, v.sku, v.purchaseprice, v.compare_price, p.id AS product_id, p.name AS naimenovanie, GROUP_CONCAT(CONCAT('https://waterglow.ru/files/originals/products/', i.filename) SEPARATOR ';') AS images
                               FROM ok_variants v
                               JOIN ok_images i ON v.product_id = i.product_id
                               JOIN ok_products p ON v.product_id = p.id
                               WHERE v.sku = ?
                               GROUP BY v.sku, v.purchaseprice, v.compare_price, p.id, p.name");
        $stmt->execute([$artikul]);
        $results = $stmt->fetchAll(PDO::FETCH_ASSOC);
    }
    echo json_encode($results);
    exit;
}

if ($_SERVER['REQUEST_METHOD'] === 'GET' && isset($_GET['order_search'])) {
    $order_id = trim($_GET['order_id']);
    $results = [];
    if (!empty($order_id)) {
        $orderDetails = getOrderDetails($pdo, $order_id);
        if (!empty($orderDetails)) {
            $skuList = explode('; ', $orderDetails[0]['sku']);
            $placeholders = str_repeat('?,', count($skuList) - 1) . '?';
            $stmt = $pdo->prepare("SELECT v.sku AS artikul_modifikatsii, v.sku, v.purchaseprice, v.compare_price, p.id AS product_id, p.name AS naimenovanie, GROUP_CONCAT(CONCAT('https://waterglow.ru/files/originals/products/', i.filename) SEPARATOR ';') AS images
                                   FROM ok_variants v
                                   JOIN ok_images i ON v.product_id = i.product_id
                                   JOIN ok_products p ON v.product_id = p.id
                                   WHERE v.sku IN ($placeholders)
                                   GROUP BY v.sku, v.purchaseprice, v.compare_price, p.id, p.name");
            $stmt->execute($skuList);
            $results = $stmt->fetchAll(PDO::FETCH_ASSOC);
            foreach ($results as &$product) {
                $product['features'] = getProductFeatures($pdo, $product['product_id']);
            }
        }
    }
    echo json_encode($results);
    exit;
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Product Data Form</title>
    <script src="https://code.jquery.com/jquery-3.6.0.min.js"></script>
    <style>
        #results, #order-results { margin-top: 20px; }
        #selected-products { margin-top: 20px; }
    </style>
</head>
<body>
    <form id="search-form">
        <label for="artikul">Введите артикул:</label>
        <input type="text" id="artikul" name="artikul">
        <button type="button" id="search-btn">Поиск</button>
    </form>

    <div id="results"></div>

    <form action="perenos.php" method="post" id="export-form">
        <div id="selected-products">
            <h2>Выбранные товары:</h2>
            <ul id="selected-products-list">
                <?php foreach ($selectedProducts as $selectedProduct): ?>
                    <li data-sku="<?= htmlspecialchars($selectedProduct) ?>"><?= htmlspecialchars($selectedProduct) ?> <button type="button" class="remove-btn">Удалить</button></li>
                <?php endforeach; ?>
            </ul>
        </div>
        <input type="hidden" name="selected_products" id="selected-products-hidden" value="<?= htmlspecialchars(implode(',', $selectedProducts)) ?>">
        <button type="submit" name="export">Экспортировать в CSV</button>
        <button type="submit" name="export_no_images">Выгрузить товары без изображений</button>
    </form>

    <form id="order-search-form" action="perenos.php" method="post">
        <label for="order_id">Введите номер заказа:</label>
        <input type="text" id="order_id" name="order_id">
        <button type="submit" name="export_order">Выгрузить товары из заказа</button>
    </form>

    <div id="order-results"></div>

    <script>
        $(document).ready(function() {
            $('#search-btn').click(function() {
                var artikul = $('#artikul').val();
                if (artikul) {
                    $.get('perenos.php', { search: true, artikul: artikul }, function(data) {
                        var results = JSON.parse(data);
                        var resultsDiv = $('#results');
                        resultsDiv.empty();
                        if (results.length > 0) {
                            var table = $('<table border="1"><thead><tr><th>Артикул</th><th>Артикул модификации</th><th>Закупочная цена</th><th>Цена</th><th>Наименование</th><th>Фото товара</th><th>Действие</th></tr></thead><tbody></tbody></table>');
                            results.forEach(function(product) {
                                var row = $('<tr></tr>');
                                row.append('<td>' + product.sku + '</td>');
                                row.append('<td>' + product.artikul_modifikatsii + '</td>');
                                row.append('<td>' + product.purchaseprice + '</td>');
                                row.append('<td>' + product.compare_price + '</td>');
                                row.append('<td>' + product.naimenovanie + '</td>');
                                row.append('<td>' + product.images + '</td>');
                                row.append('<td><button type="button" class="add-btn" data-sku="' + product.artikul_modifikatsii + '">Добавить</button></td>');
                                table.find('tbody').append(row);
                            });
                            resultsDiv.append(table);
                        } else {
                            resultsDiv.append('<p>Товар не найден.</p>');
                        }
                    });
                }
            });

            $(document).on('click', '.add-btn', function() {
                var sku = $(this).data('sku');
                var selectedProductsList = $('#selected-products-list');
                var selectedProductsHidden = $('#selected-products-hidden');
                selectedProductsList.append('<li data-sku="' + sku + '">' + sku + ' <button type="button" class="remove-btn">Удалить</button></li>');
                var selectedProducts = [];
                selectedProductsList.find('li').each(function() {
                    selectedProducts.push($(this).data('sku'));
                });
                selectedProductsHidden.val(selectedProducts.join(','));
            });

            $(document).on('click', '.remove-btn', function() {
                $(this).parent().remove();
                var selectedProductsList = $('#selected-products-list');
                var selectedProductsHidden = $('#selected-products-hidden');
                var selectedProducts = [];
                selectedProductsList.find('li').each(function() {
                    selectedProducts.push($(this).data('sku'));
                });
                selectedProductsHidden.val(selectedProducts.join(','));
            });

            $('#order-search-btn').click(function() {
                var order_id = $('#order_id').val();
                if (order_id) {
                    $.get('perenos.php', { order_search: true, order_id: order_id }, function(data) {
                        var results = JSON.parse(data);
                        var orderResultsDiv = $('#order-results');
                        orderResultsDiv.empty();
                        if (results.length > 0) {
                            var table = $('<table border="1"><thead><tr><th>Артикул</th><th>Артикул модификации</th><th>Закупочная цена</th><th>Цена</th><th>Наименование</th><th>Фото товара</th><th>Свойства</th></tr></thead><tbody></tbody></table>');
                            results.forEach(function(product) {
                                var row = $('<tr></tr>');
                                row.append('<td>' + product.sku + '</td>');
                                row.append('<td>' + product.artikul_modifikatsii + '</td>');
                                row.append('<td>' + product.purchaseprice + '</td>');
                                row.append('<td>' + product.compare_price + '</td>');
                                row.append('<td>' + product.naimenovanie + '</td>');
                                row.append('<td>' + product.images + '</td>');
                                var features = product.features.map(function(feature) {
                                    return feature.name + ': ' + feature.value;
                                }).join('; ');
                                row.append('<td>' + features + '</td>');
                                table.find('tbody').append(row);
                            });
                            orderResultsDiv.append(table);
                        } else {
                            orderResultsDiv.append('<p>Заказ не найден.</p>');
                        }
                    });
                }
				                else {
                    alert("Введите номер заказа.");
                }
            });
        });
    </script>
</body>
</html>