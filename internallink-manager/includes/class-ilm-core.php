<?php
/**
 * The core plugin class
 *
 * @package InternalLink_Manager
 */

class ILM_Core {

    /**
     * The loader that's responsible for maintaining and registering all hooks that power
     * the plugin.
     */
    protected $loader;

    /**
     * The unique identifier of this plugin.
     */
    protected $plugin_name;

    /**
     * The current version of the plugin.
     */
    protected $version;

    /**
     * Initialize the class and set its properties.
     */
    public function __construct() {
        $this->version = ILM_VERSION;
        $this->plugin_name = 'internallink-manager';

        $this->load_dependencies();
        $this->define_admin_hooks();
        $this->define_public_hooks();
    }

    /**
     * Load the required dependencies for this plugin.
     */
    private function load_dependencies() {
        // Load the loader class
        require_once ILM_PLUGIN_DIR . 'includes/class-ilm-loader.php';

        // Load the scanner class
        require_once ILM_PLUGIN_DIR . 'includes/class-ilm-scanner.php';

        // Load the analyzer class
        require_once ILM_PLUGIN_DIR . 'includes/class-ilm-analyzer.php';

        // Load the admin class
        require_once ILM_PLUGIN_DIR . 'admin/class-ilm-admin.php';

        $this->loader = new ILM_Loader();
    }

    /**
     * Register all of the hooks related to the admin area functionality
     * of the plugin.
     */
    private function define_admin_hooks() {
        $admin = new ILM_Admin($this->get_plugin_name(), $this->get_version());

        $this->loader->add_action('admin_enqueue_scripts', $admin, 'enqueue_styles');
        $this->loader->add_action('admin_enqueue_scripts', $admin, 'enqueue_scripts');
        $this->loader->add_action('admin_menu', $admin, 'add_plugin_admin_menu');

        // AJAX hooks
        $this->loader->add_action('wp_ajax_ilm_scan_orphaned_pages', $admin, 'ajax_scan_orphaned_pages');
        $this->loader->add_action('wp_ajax_ilm_generate_suggestions', $admin, 'ajax_generate_suggestions');
        $this->loader->add_action('wp_ajax_ilm_update_suggestion_status', $admin, 'ajax_update_suggestion_status');
    }

    /**
     * Register all of the hooks related to the public-facing functionality
     * of the plugin.
     */
    private function define_public_hooks() {
        // Currently no public-facing hooks needed for MVP
    }

    /**
     * Run the loader to execute all of the hooks with WordPress.
     */
    public function run() {
        $this->loader->run();
    }

    /**
     * The name of the plugin used to uniquely identify it within the context of
     * WordPress and to define internationalization functionality.
     */
    public function get_plugin_name() {
        return $this->plugin_name;
    }

    /**
     * Retrieve the version number of the plugin.
     */
    public function get_version() {
        return $this->version;
    }
}
