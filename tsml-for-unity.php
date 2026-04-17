<?php

declare(strict_types=1);

/**
 * Plugin Name: TSML for Unity
 * Plugin URI: https://github.com/bleeding-deacons/tsml-for-unity
 * Description: Integrates 12 Step Meeting List (TSML) with the Unity plugin, providing meeting, group & location support.
 * Version: 1.12.0
 * Requires at least: 6.0
 * Requires PHP: 8.1
 * GitHub Plugin URI: https://github.com/thebleedingdeacons/tsml-for-unity
 * GitHub Branch: main
 * Author: The Bleeding Deacons
 * Author URI: https://github.com/bleedingdeacons/tsml-for-unity
 * Contact: thebleedingdeacons@gmail.com
 * License: MIT (Modified)
 */

if (!defined('ABSPATH')) {
    exit;
}

// Define plugin constants
if (!function_exists('get_plugin_data')) {
    require_once(ABSPATH . 'wp-admin/includes/plugin.php');
}
$tsml_for_unity_plugin_data = get_plugin_data(__FILE__, false, false);
define('TSML_FOR_UNITY_VERSION', $tsml_for_unity_plugin_data['Version']);
define('TSML_FOR_UNITY_PATH', plugin_dir_path(__FILE__));
define('TSML_FOR_UNITY_URL', plugin_dir_url(__FILE__));

// Autoloader for TsmlForUnity namespace
spl_autoload_register(function ($class) {
    try {
        $prefix = 'TsmlForUnity\\';
        $base_dir = TSML_FOR_UNITY_PATH . 'src/';

        $len = strlen($prefix);
        if (strncmp($prefix, $class, $len) !== 0) {
            return;
        }

        $relative_class = substr($class, $len);
        $file = $base_dir . str_replace('\\', '/', $relative_class) . '.php';

        if (file_exists($file)) {
            require $file;
        }
    } catch (\Exception $e) {
        function_exists('wp_log')
            ? wp_log('tsml-for-unity')->error('TSML for Unity Autoloader Error: ' . $e->getMessage(), ['exception' => $e->getMessage(), 'trace' => $e->getTraceAsString()])
            : error_log('TSML for Unity Autoloader Error: ' . $e->getMessage());
    } catch (\Throwable $e) {
        function_exists('wp_log')
            ? wp_log('tsml-for-unity')->critical('TSML for Unity Autoloader Fatal Error: ' . $e->getMessage(), ['exception' => $e->getMessage(), 'trace' => $e->getTraceAsString()])
            : error_log('TSML for Unity Autoloader Fatal Error: ' . $e->getMessage());
    }
});

// Initialize the plugin after Unity is fully loaded
add_action('unity/loaded', function ($container) {
    try {
        if (!class_exists('TsmlForUnity\Plugin')) {
            throw new \Exception('TsmlForUnity\Plugin class not found. Check that Plugin.php exists in the src/ directory.');
        }

        // Check if Unity is available
        if (!\TsmlForUnity\Plugin::unityIsAvailable()) {
            return;
        }

        /**
         * Fires after TSML for Unity is fully loaded.
         */
        do_action('tsml_for_unity/loaded');

    } catch (\Exception $e) {
        function_exists('wp_log')
            ? wp_log('tsml-for-unity')->error('TSML for Unity Plugin Initialization Error: ' . $e->getMessage(), ['exception' => $e->getMessage(), 'trace' => $e->getTraceAsString()])
            : error_log('TSML for Unity Plugin Initialization Error: ' . $e->getMessage());

        if (is_admin()) {
            add_action('admin_notices', function () use ($e) {
                $message = sprintf(
                    '<strong>TSML for Unity Plugin Error:</strong> %s',
                    esc_html($e->getMessage())
                );
                echo '<div class="notice notice-error is-dismissible"><p>' . $message . '</p></div>';
            });
        }

        return;

    } catch (\Throwable $e) {
        function_exists('wp_log')
            ? wp_log('tsml-for-unity')->critical('TSML for Unity Plugin Fatal Error: ' . $e->getMessage(), ['exception' => $e->getMessage(), 'trace' => $e->getTraceAsString()])
            : error_log('TSML for Unity Plugin Fatal Error: ' . $e->getMessage());

        if (is_admin()) {
            add_action('admin_notices', function () {
                echo '<div class="notice notice-error is-dismissible"><p><strong>TSML for Unity Plugin Fatal Error:</strong> Plugin failed to load. Check error logs.</p></div>';
            });
        }

        return;
    }
});

// Check for database table upgrades on every load
add_action('plugins_loaded', function () {
    if (class_exists('TsmlForUnity\IntergroupMeetings\TsmlIntergroupMeetingGroupAttendanceTable')) {
        \TsmlForUnity\IntergroupMeetings\TsmlIntergroupMeetingGroupAttendanceTable::maybeUpgrade();
    }
    if (class_exists('TsmlForUnity\IntergroupMeetings\TsmlIntergroupMeetingOfficerAttendanceTable')) {
        \TsmlForUnity\IntergroupMeetings\TsmlIntergroupMeetingOfficerAttendanceTable::maybeUpgrade();
    }
}, 10);

// Show admin notice if Unity is not available
add_action('plugins_loaded', function () {
    if (!class_exists('Unity\\Plugin')) {
        add_action('admin_notices', function () {
            echo '<div class="notice notice-error"><p>';
            echo '<strong>' . esc_html__('TSML for Unity', 'tsml-for-unity') . ':</strong> ';
            echo esc_html__('This plugin requires the Unity plugin to be installed and activated.', 'tsml-for-unity');
            echo '</p></div>';
        });
    }
}, 20);

// Register factories with Unity's container - must run before Unity initializes services
add_action('unity/register_services', function ($container) {
    try {
        if (!class_exists('TsmlForUnity\Plugin')) {
            return;
        }

        \TsmlForUnity\Plugin::registerWithUnity($container);

    } catch (\Exception $e) {
        function_exists('wp_log')
            ? wp_log('tsml-for-unity')->error('TSML for Unity Registration Error: ' . $e->getMessage(), ['exception' => $e->getMessage(), 'trace' => $e->getTraceAsString()])
            : error_log('TSML for Unity Registration Error: ' . $e->getMessage());
    } catch (\Throwable $e) {
        function_exists('wp_log')
            ? wp_log('tsml-for-unity')->critical('TSML for Unity Registration Fatal Error: ' . $e->getMessage(), ['exception' => $e->getMessage(), 'trace' => $e->getTraceAsString()])
            : error_log('TSML for Unity Registration Fatal Error: ' . $e->getMessage());
    }
});

// Plugin activation hook
register_activation_hook(__FILE__, function () {
    if (!class_exists('TsmlForUnity\Plugin') || !\TsmlForUnity\Plugin::unityIsAvailable()) {
        deactivate_plugins(plugin_basename(__FILE__));
        wp_die(
            esc_html__('TSML for Unity requires the Unity plugin to be installed and activated.', 'tsml-for-unity'),
            esc_html__('Plugin Activation Error', 'tsml-for-unity'),
            ['back_link' => true]
        );
    }

    // Create the intergroup meeting attendance custom tables
    \TsmlForUnity\IntergroupMeetings\TsmlIntergroupMeetingGroupAttendanceTable::createTable();
    \TsmlForUnity\IntergroupMeetings\TsmlIntergroupMeetingOfficerAttendanceTable::createTable();

    // Resolve and cache ACF field name → key mapping so repositories
    // can reliably read/write posts that lack ACF shadow meta rows
    // (e.g. posts created via the REST API).
    \TsmlForUnity\IntergroupMeetings\AcfFieldKeyResolver::resolve();
});

// Ensure ACF field keys are cached even if the plugin was activated
// before ACF was fully loaded (activation hooks run early). The acf/init
// hook fires after ACF has registered all field groups, so this is the
// reliable moment to resolve field names to keys.
add_action('acf/init', function () {
    if (!\TsmlForUnity\IntergroupMeetings\AcfFieldKeyResolver::isCached()) {
        \TsmlForUnity\IntergroupMeetings\AcfFieldKeyResolver::resolve();
    }
}, 20);

// Plugin deactivation hook
register_deactivation_hook(__FILE__, function () {
    \TsmlForUnity\IntergroupMeetings\AcfFieldKeyResolver::clear();
});