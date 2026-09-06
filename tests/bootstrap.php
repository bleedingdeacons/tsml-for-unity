<?php

declare(strict_types=1);

use BleedingDeacons\WpMocks\Bootstrap;
use BleedingDeacons\WpMocks\WpState;

// Load Composer autoloader
require_once __DIR__ . '/../vendor/autoload.php';

// Patchwork first, and nothing patchable before it.
//
// It rewrites functions as their defining file is included, so anything
// defined ahead of it can never be overridden per-test afterwards; Brain
// Monkey only requires it lazily inside Monkey\setUp(), by which point the
// stubs below exist. Symptom otherwise: Patchwork\Exceptions\DefinedTooEarly.
Bootstrap::loadPatchwork();

WpState::$pluginSlug = 'tsml-for-unity';

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

// WP_DEBUG is not defined here any more, and defining it would no longer mean
// anything: the change trackers used to wrap their diagnostic logging in
// `defined('WP_DEBUG') && WP_DEBUG`, so the branches only ran on a
// debug-enabled site. They log at debug level unconditionally now and let
// Sentinel's own SENTINEL_LOG_LEVEL decide, so the lines run in every test
// regardless. Plugin::logDebug() degrades to a no-op when the logger is
// absent, which it is here.

// The $wpdb output-format constants the custom-table repositories pass to
// get_row()/get_results() are no longer declared here: bleedingdeacons/wp-mocks
// carries OBJECT, OBJECT_K, ARRAY_A and ARRAY_N, along with the *_IN_SECONDS
// family.

// TsmlPasswordCredentialRepository type-hints wpdb, which WordPress
// declares and this suite does not load. FakeWpdb cannot stand in for it:
// it is final, so it cannot be aliased to the name a type-hint resolves.
// WpdbStub exists for that, and is the same trick Reach and Unity use.
if (!class_exists('wpdb')) {
    class_alias(\TsmlForUnity\Tests\Support\WpdbStub::class, 'wpdb');
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

// The shared stub layer, loaded last so the classes above keep winning — its
// definitions are all class_exists()/function_exists()-guarded.
//
// Only the `wordpress` group:
//
//   - `sentinel` would replace the Sentinel_Log_Channel in stubs/wordpress.php,
//     which records level/message pairs in the shape HasLoggerTest asserts on.
//   - `acf` must stay out. Several tests cover the ACF-unavailable branch by
//     asserting acf_get_field() is *absent*, and a function, once defined,
//     stays defined for the life of the process. Tests that need ACF present
//     set their own expectation, which is enough for Brain Monkey to define
//     the function for that test.
//
// Note what the group deliberately does not contain: add_action, add_filter,
// do_action and apply_filters. Brain Monkey owns the hook layer and defines
// them inside its own setUp(), which is why every test extends
// TsmlForUnity\Tests\TestCase.
Bootstrap::load(['wordpress']);

// wp_json_encode() used to be declared here, for the error-logging paths that
// reach for it. The shared layer above defines the same thing — json_encode()
// with the same arguments — so this file's copy would never have been reached.
