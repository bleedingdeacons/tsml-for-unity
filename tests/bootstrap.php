<?php

declare(strict_types=1);

// Load Composer autoloader
require_once __DIR__ . '/../vendor/autoload.php';

// Initialize WP_Mock
WP_Mock::bootstrap();

// Define WordPress constants if not defined.
//
// ABSPATH is a real, writable temp directory rather than a fictional
// '/var/www/html/'. The custom-table installers do a hard
// require_once ABSPATH . 'wp-admin/includes/upgrade.php' before calling
// dbDelta(); a require cannot be stubbed, so a minimal stand-in is written
// at that path below and the DDL paths become testable.
if (!defined('ABSPATH')) {
    $tsmlTestRoot = sys_get_temp_dir() . '/tsml-test-abspath-' . getmypid() . '/';
    if (!is_dir($tsmlTestRoot)) {
        mkdir($tsmlTestRoot, 0777, true);
    }
    define('ABSPATH', $tsmlTestRoot);
}

$tsmlUpgradeDir = ABSPATH . 'wp-admin/includes/';
if (!is_dir($tsmlUpgradeDir)) {
    mkdir($tsmlUpgradeDir, 0777, true);
}
if (!file_exists($tsmlUpgradeDir . 'upgrade.php')) {
    file_put_contents(
        $tsmlUpgradeDir . 'upgrade.php',
        "<?php\n"
        . "// Test stand-in for WordPress's upgrade.php.\n"
        . "if (!function_exists('dbDelta')) {\n"
        . "    function dbDelta(\$queries = '', \$execute = true) {\n"
        . "        \$GLOBALS['tsml_test_dbdelta'][] = \$queries;\n"
        . "        return [];\n"
        . "    }\n"
        . "}\n"
    );
}
$GLOBALS['tsml_test_dbdelta'] = [];

if (!defined('TSML_FOR_UNITY_VERSION')) {
    define('TSML_FOR_UNITY_VERSION', '1.0.0');
}

// $wpdb output-format constants. The custom-table repositories pass ARRAY_A
// to get_row()/get_results(); undefined constants are fatal on PHP 8, so the
// values are declared here rather than in each test.
if (!defined('OBJECT')) {
    define('OBJECT', 'OBJECT');
}
if (!defined('ARRAY_A')) {
    define('ARRAY_A', 'ARRAY_A');
}
if (!defined('ARRAY_N')) {
    define('ARRAY_N', 'ARRAY_N');
}

if (!defined('TSML_FOR_UNITY_PATH')) {
    define('TSML_FOR_UNITY_PATH', dirname(__DIR__) . '/');
}

if (!defined('TSML_FOR_UNITY_URL')) {
    define('TSML_FOR_UNITY_URL', 'https://example.com/wp-content/plugins/tsml-for-unity/');
}

// Unity's interfaces come from the real plugin, pulled in as a require-dev
// Composer path repository (see composer.json), so they autoload through
// vendor/autoload.php above.
//
// They were previously hand-copied into tests/stubs/unity-interfaces.php --
// 550 lines, 44 declarations, kept in sync by discipline alone. That meant the
// suite validated this plugin against a *duplicate* of the contract rather
// than the contract itself: Unity could change a signature and these tests
// would stay green while production fataled.
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
