<?php

declare(strict_types=1);

/**
 * Plugin Name: TSML for Unity
 * Plugin URI: https://github.com/bleeding-deacons/tsml-for-unity
 * Description: Integrates 12 Step Meeting List (TSML) with the Unity plugin, providing meeting, group & location support.
 * Version: 1.1.0
 * Requires at least: 5.0
 * Requires PHP: 7.4
 * Author: The Bleeding Deacons
 * Author URI: thebleedingdeacons@gmail.com
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
        error_log('TSML for Unity Autoloader Error: ' . $e->getMessage());
    } catch (\Throwable $e) {
        error_log('TSML for Unity Autoloader Fatal Error: ' . $e->getMessage());
    }
});

// Register TSML taxonomies
add_action('init', function () {
    // Register Meeting Types taxonomy
    register_taxonomy('tsml_type', 'tsml_meeting', [
        'labels' => [
            'name' => __('Meeting Types', 'tsml-for-unity'),
            'singular_name' => __('Meeting Type', 'tsml-for-unity'),
            'search_items' => __('Search Meeting Types', 'tsml-for-unity'),
            'all_items' => __('All Meeting Types', 'tsml-for-unity'),
            'edit_item' => __('Edit Meeting Type', 'tsml-for-unity'),
            'update_item' => __('Update Meeting Type', 'tsml-for-unity'),
            'add_new_item' => __('Add New Meeting Type', 'tsml-for-unity'),
            'new_item_name' => __('New Meeting Type Name', 'tsml-for-unity'),
            'menu_name' => __('Meeting Types', 'tsml-for-unity'),
        ],
        'hierarchical' => false,
        'show_ui' => true,
        'show_admin_column' => true,
        'query_var' => true,
        'rewrite' => ['slug' => 'meeting-type'],
        'show_in_rest' => true,
    ]);
}, 0); // Priority 0 to ensure it runs early

/**
 * Get the TSML Meeting Factory instance
 *
 * @return \TsmlForUnity\TsmlMeetingFactory|null Returns null if Unity is not available
 */
function tsml_for_unity_meeting_factory(): ?\TsmlForUnity\TsmlMeetingFactory
{
    return \TsmlForUnity\Plugin::getMeetingFactory();
}

/**
 * Get the TSML Group Factory instance
 *
 * @return \TsmlForUnity\TsmlGroupFactory|null Returns null if Unity groups are not available
 */
function tsml_for_unity_group_factory(): ?\TsmlForUnity\TsmlGroupFactory
{
    return \TsmlForUnity\Plugin::getGroupFactory();
}

/**
 * Get the TSML Location Factory instance
 *
 * @return \TsmlForUnity\TsmlLocationFactory|null Returns null if Unity locations are not available
 */
function tsml_for_unity_location_factory(): ?\TsmlForUnity\TsmlLocationFactory
{
    return \TsmlForUnity\Plugin::getLocationFactory();
}

// Initialize the plugin after Unity is fully loaded
add_action('unity_loaded', function ($container) {
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
        do_action('tsml_for_unity_loaded');

    } catch (\Exception $e) {
        error_log('TSML for Unity Plugin Initialization Error: ' . $e->getMessage());
        error_log('TSML for Unity Plugin Stack Trace: ' . $e->getTraceAsString());

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
        error_log('TSML for Unity Plugin Fatal Error: ' . $e->getMessage());
        error_log('TSML for Unity Plugin Stack Trace: ' . $e->getTraceAsString());

        if (is_admin()) {
            add_action('admin_notices', function () {
                echo '<div class="notice notice-error is-dismissible"><p><strong>TSML for Unity Plugin Fatal Error:</strong> Plugin failed to load. Check error logs.</p></div>';
            });
        }

        return;
    }
});

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
add_action('unity_register_services', function ($container) {
    try {
        if (!class_exists('TsmlForUnity\Plugin')) {
            return;
        }

        \TsmlForUnity\Plugin::registerWithUnity($container);

    } catch (\Exception $e) {
        error_log('TSML for Unity Registration Error: ' . $e->getMessage());
    } catch (\Throwable $e) {
        error_log('TSML for Unity Registration Fatal Error: ' . $e->getMessage());
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
});

// Plugin deactivation hook
register_deactivation_hook(__FILE__, function () {
    // Cleanup code here if needed
});