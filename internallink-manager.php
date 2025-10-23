<?php
/**
 * Plugin Name: InternalLink Manager
 * Plugin URI: https://github.com/tuankahunam95/kahunam-testing-site
 * Description: Automatically identifies orphaned pages and provides actionable suggestions for internal linking opportunities to improve SEO.
 * Version: 1.0.0
 * Author: KahunaM
 * Author URI: https://github.com/tuankahunam95
 * License: GPL v2 or later
 * License URI: https://www.gnu.org/licenses/gpl-2.0.html
 * Text Domain: internallink-manager
 * Domain Path: /languages
 * Requires at least: 6.0
 * Requires PHP: 7.4
 */

// If this file is called directly, abort.
if (!defined('WPINC')) {
    die;
}

// Define plugin constants
define('ILM_VERSION', '1.0.0');
define('ILM_PLUGIN_DIR', plugin_dir_path(__FILE__));
define('ILM_PLUGIN_URL', plugin_dir_url(__FILE__));
define('ILM_PLUGIN_BASENAME', plugin_basename(__FILE__));

/**
 * The code that runs during plugin activation.
 */
function activate_internallink_manager() {
    require_once ILM_PLUGIN_DIR . 'includes/class-ilm-activator.php';
    ILM_Activator::activate();
}

/**
 * The code that runs during plugin deactivation.
 */
function deactivate_internallink_manager() {
    require_once ILM_PLUGIN_DIR . 'includes/class-ilm-deactivator.php';
    ILM_Deactivator::deactivate();
}

register_activation_hook(__FILE__, 'activate_internallink_manager');
register_deactivation_hook(__FILE__, 'deactivate_internallink_manager');

/**
 * The core plugin class
 */
require ILM_PLUGIN_DIR . 'includes/class-ilm-core.php';

/**
 * Begins execution of the plugin.
 */
function run_internallink_manager() {
    $plugin = new ILM_Core();
    $plugin->run();
}

run_internallink_manager();
