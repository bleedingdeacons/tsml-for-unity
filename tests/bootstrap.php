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
    define('TSML_FOR_UNITY_PATH', dirname(__DIR__) . '/');
}

if (!defined('TSML_FOR_UNITY_URL')) {
    define('TSML_FOR_UNITY_URL', 'https://example.com/wp-content/plugins/tsml-for-unity/');
}

// Unity ships as a sibling WordPress plugin rather than a Composer package, so
// its interfaces are not autoloadable here. Declare them once for the suite.
require_once __DIR__ . '/stubs/unity-interfaces.php';
require_once __DIR__ . '/stubs/wordpress.php';

// The error-logging paths reach for wp_json_encode, so any test that exercises
// a failure branch needs it. It is a pure function, so use the real semantics
// rather than making every such test declare a mock.
if (!function_exists('wp_json_encode')) {
    function wp_json_encode($data, int $options = 0, int $depth = 512)
    {
        return json_encode($data, $options, $depth);
    }
}
