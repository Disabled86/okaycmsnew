<?php

// Определяем корневую директорию
$rootDir = realpath(__DIR__) . '/';

// Подключаем автозагрузчик
require_once $rootDir . 'vendor/autoload.php';

// Подключаем конфигурацию из двух файлов
$localConfigPath = $rootDir . 'config/config.local.php';
$mainConfigPath = $rootDir . 'config/config.php';

if (!file_exists($localConfigPath) || !file_exists($mainConfigPath)) {
    die("Configuration files are missing.");
}

$localConfig = include $localConfigPath;
$mainConfig = include $mainConfigPath;

if (!is_array($localConfig) || !is_array($mainConfig)) {
    die("Invalid configuration format.");
}

// Объединяем конфигурации
$config = array_merge($mainConfig, $localConfig);

require_once $rootDir . 'Okay/Core/Database.php';
require_once $rootDir . 'Okay/Core/Image.php';
require_once $rootDir . 'Okay/Entities/ImagesEntity.php';
require_once $rootDir . 'Okay/Core/QueryFactory.php';

use Okay\Core\Database;
use Okay\Core\Image;
use Okay\Entities\ImagesEntity;
use Okay\Core\QueryFactory;
use Aura\Sql\ExtendedPdo;
use Aura\SqlQuery\QueryFactory as AuraQueryFactory;
use Psr\Log\NullLogger;

// Создаем объект ExtendedPdo
$pdo = new ExtendedPdo(
    'mysql:host=' . $config['db_server'] . ';dbname=' . $config['db_name'],
    $config['db_user'],
    $config['db_password']
);

// Создаем объект NullLogger
$logger = new NullLogger();

// Создаем объект AuraQueryFactory
$auraQueryFactory = new AuraQueryFactory('mysql');

// Создаем объект QueryFactory
$queryFactory = new QueryFactory($auraQueryFactory);

// Инициализируем базу данных
$db = new Database(
    $pdo,
    $logger,
    $config['db_name'],
    $queryFactory
);

class GenerateResizes
{
    private $db;
    private $imageCore;
    private $imagesEntity;
    private $config;

    public function __construct(Database $db, Image $imageCore, ImagesEntity $imagesEntity, $config)
    {
        $this->db = $db;
        $this->imageCore = $imageCore;
        $this->imagesEntity = $imagesEntity;
        $this->config = $config;
    }

    public function log($message)
    {
        $logDir = $this->config['root_dir'] . 'logs/';
        $logFile = $logDir . 'generate_resizes.log';

        // Проверяем наличие директории для логов и создаем ее, если необходимо
        if (!is_dir($logDir)) {
            mkdir($logDir, 0777, true);
        }

        file_put_contents($logFile, date('Y-m-d H:i:s') . " - " . $message . "\n", FILE_APPEND);
    }

    public function run($productId)
    {
        $this->log("Starting resize process for product ID: $productId");
        
        // Получаем изображения для указанного товара
        $images = $this->imagesEntity->find(['product_id' => $productId]);
        $this->log("Found " . count($images) . " images for product ID: $productId");

        // Проверяем и генерируем ресайзы для каждого изображения
        foreach ($images as $image) {
            $this->log("Processing image: " . $image->filename);
            $originalImagePath = $this->config['root_dir'] . $this->config['original_images_dir'] . $image->filename;
            $resizedImagePath = $this->config['root_dir'] . $this->config['resized_images_dir'] . $image->filename;

            $this->log("Original image path: $originalImagePath");
            $this->log("Resized image path: $resizedImagePath");

            // Проверяем, существует ли уже ресайз изображения
            if (!file_exists($resizedImagePath)) {
                $this->log("Generating resize for image: " . $image->filename);
                // Генерируем ресайз изображения
                $this->imageCore->resize(
                    $image->filename,
                    $this->config['products_image_sizes'],
                    $this->config['original_images_dir'],
                    $this->config['resized_images_dir']
                );
                $this->log("Resized image generated for: " . $image->filename);
            } else {
                $this->log("Resized image already exists for: " . $image->filename);
            }
        }

        $this->log("Resize process completed for product ID: $productId");
    }
}

// Инициализируем объекты
$imageCore = new Image(
    new Okay\Core\Settings($db),
    $config,
    new Okay\Helpers\AdapterManager(),
    new Okay\Helpers\Request(),
    new Okay\Helpers\Response(),
    new QueryFactory($auraQueryFactory),
    $db,
    new Okay\Core\EntityFactory(),
    $config['root_dir']
);
$imagesEntity = new ImagesEntity($db);

// Получаем ID товара из аргументов командной строки
$productId = $argv[1];

// Запускаем скрипт
$generateResizes = new GenerateResizes($db, $imageCore, $imagesEntity, $config);
$generateResizes->run($productId);

?>