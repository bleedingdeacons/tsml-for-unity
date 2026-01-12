<?php
/**
 * Plugin Name: TSML for Unity
 * Plugin URI: https://github.com/bleeding-deacons/tsml-for-unity
 * Description: Integrates 12 Step Meeting List (TSML) with the Unity plugin, providing meeting & group support.
 * Version: 1.0.1
 * Requires at least: 5.0
 * Requires PHP: 7.4
 * Author: The Bleeding Deacons
 * Author URI: thebleedingdeacons@gmail.com
 * License: MIT (Modified)
 */

declare(strict_types=1);

namespace TsmlForUnity;

// Prevent direct access
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

// Autoloader for TsmlForUnity namespace - only loads classes when Unity is available
spl_autoload_register(function ($class) {
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
});

/**
 * Check if Unity plugin is active and has required interfaces
 *
 * @return bool
 */
function unity_is_available(): bool
{
    return interface_exists('Unity\\Meetings\\Interfaces\\MeetingFactoryInterface')
        && interface_exists('Unity\\Meetings\\Interfaces\\MeetingInterface')
        && class_exists('Unity\\Meetings\\Meeting')
        && class_exists('Unity\\Meetings\\Contact');
}

/**
 * Check if Unity's group interfaces are available
 *
 * @return bool
 */
function unity_groups_available(): bool
{
    return interface_exists('Unity\\Groups\\Interfaces\\GroupFactoryInterface')
        && interface_exists('Unity\\Groups\\Interfaces\\GroupInterface')
        && class_exists('Unity\\Groups\\Group');
}

/**
 * Display admin notice if Unity plugin is not active
 */
function admin_notice_missing_unity(): void
{
    ?>
    <div class="notice notice-error">
        <p>
            <strong><?php esc_html_e('TSML for Unity', 'tsml-for-unity'); ?>:</strong>
            <?php esc_html_e('This plugin requires the Unity plugin to be installed and activated.', 'tsml-for-unity'); ?>
        </p>
    </div>
    <?php
}

/**
 * Initialize the plugin
 */
function init(): void
{
    // Check for Unity plugin
    if (!unity_is_available()) {
        add_action('admin_notices', __NAMESPACE__ . '\\admin_notice_missing_unity');
        return;
    }

    /**
     * Fires when TSML for Unity is fully loaded
     */
    do_action('tsml_for_unity_loaded');
}

add_action('plugins_loaded', __NAMESPACE__ . '\\init', 20);

/**
 * Register the TsmlMeetingFactory with Unity's dependency container
 * This must be hooked early, before plugins_loaded priority 10 where Unity runs
 *
 * @param mixed $container Unity's dependency container
 */
function register_with_unity($container): void
{
    if (!method_exists($container, 'register')) {
        return;
    }

    // Register meeting factory
    $container->register(
        'Unity\\Meetings\\Interfaces\\MeetingFactoryInterface',
        function($container) {
            return new TsmlMeetingFactory();
        }
    );

    // Register group factory if Unity's group interfaces are available
    if (unity_groups_available()) {
        $container->register(
            'Unity\\Groups\\Interfaces\\GroupFactoryInterface',
            function($container) {
                return new TsmlGroupFactory();
            }
        );
    }
}

// Hook into unity_register_services - this hook fires during Unity's plugins_loaded at priority 10
add_action('unity_register_services', __NAMESPACE__ . '\\register_with_unity');

/**
 * Get the TsmlMeetingFactory instance
 *
 * @return TsmlMeetingFactory|null Returns null if Unity is not available
 */
function get_meeting_factory(): ?TsmlMeetingFactory
{
    static $factory = null;

    if (!unity_is_available()) {
        return null;
    }

    if ($factory === null) {
        $factory = new TsmlMeetingFactory();
    }

    return $factory;
}

/**
 * Get the TsmlGroupFactory instance
 *
 * @return TsmlGroupFactory|null Returns null if Unity groups are not available
 */
function get_group_factory(): ?TsmlGroupFactory
{
    static $factory = null;

    if (!unity_groups_available()) {
        return null;
    }

    if ($factory === null) {
        $factory = new TsmlGroupFactory();
    }

    return $factory;
}

/**
 * Plugin activation hook
 */
function activate(): void
{
    if (!unity_is_available()) {
        deactivate_plugins(plugin_basename(__FILE__));
        wp_die(
            esc_html__('TSML for Unity requires the Unity plugin to be installed and activated.', 'tsml-for-unity'),
            esc_html__('Plugin Activation Error', 'tsml-for-unity'),
            ['back_link' => true]
        );
    }
}

register_activation_hook(__FILE__, __NAMESPACE__ . '\\activate');

/**
 * Plugin deactivation hook
 */
function deactivate(): void
{
    // Clean up if needed
}

register_deactivation_hook(__FILE__, __NAMESPACE__ . '\\deactivate');
