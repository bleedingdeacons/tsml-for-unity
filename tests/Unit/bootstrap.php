<?php

declare(strict_types=1);

// Load Composer autoloader
require_once __DIR__ . '/../vendor/autoload.php';

// Initialize WP_Mock
WP_Mock::bootstrap();

// Define WordPress constants if not defined
if (!defined('ABSPATH')) {
    define('ABSPATH', '/var/www/html/');
}

if (!defined('TSML_FOR_UNITY_VERSION')) {
    define('TSML_FOR_UNITY_VERSION', '1.0.0');
}

if (!defined('TSML_FOR_UNITY_PATH')) {
    define('TSML_FOR_UNITY_PATH', dirname(__DIR__) . 'bootstrap.php/');
}

if (!defined('TSML_FOR_UNITY_URL')) {
    define('TSML_FOR_UNITY_URL', 'https://example.com/wp-content/plugins/tsml-for-unity/');
}
