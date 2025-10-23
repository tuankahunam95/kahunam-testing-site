<?php
/**
 * The admin-specific functionality of the plugin
 *
 * @package InternalLink_Manager
 */

class ILM_Admin {

    /**
     * The ID of this plugin.
     */
    private $plugin_name;

    /**
     * The version of this plugin.
     */
    private $version;

    /**
     * Initialize the class and set its properties.
     */
    public function __construct($plugin_name, $version) {
        $this->plugin_name = $plugin_name;
        $this->version = $version;
    }

    /**
     * Register the stylesheets for the admin area.
     */
    public function enqueue_styles() {
        wp_enqueue_style($this->plugin_name, ILM_PLUGIN_URL . 'assets/css/admin.css', array(), $this->version, 'all');
    }

    /**
     * Register the JavaScript for the admin area.
     */
    public function enqueue_scripts() {
        wp_enqueue_script($this->plugin_name, ILM_PLUGIN_URL . 'assets/js/admin.js', array('jquery'), $this->version, false);

        // Localize script for AJAX
        wp_localize_script($this->plugin_name, 'ilm_ajax', array(
            'ajax_url' => admin_url('admin-ajax.php'),
            'nonce' => wp_create_nonce('ilm_nonce'),
        ));
    }

    /**
     * Register the administration menu for this plugin
     */
    public function add_plugin_admin_menu() {
        // Main menu
        add_menu_page(
            'InternalLink Manager',
            'InternalLink Manager',
            'manage_options',
            'internallink-manager',
            array($this, 'display_dashboard_page'),
            'dashicons-admin-links',
            30
        );

        // Dashboard submenu (same as main menu)
        add_submenu_page(
            'internallink-manager',
            'Dashboard',
            'Dashboard',
            'manage_options',
            'internallink-manager',
            array($this, 'display_dashboard_page')
        );

        // Link Suggestions submenu
        add_submenu_page(
            'internallink-manager',
            'Link Suggestions',
            'Link Suggestions',
            'manage_options',
            'ilm-suggestions',
            array($this, 'display_suggestions_page')
        );

        // Settings submenu
        add_submenu_page(
            'internallink-manager',
            'Settings',
            'Settings',
            'manage_options',
            'ilm-settings',
            array($this, 'display_settings_page')
        );

        // Scan Site submenu
        add_submenu_page(
            'internallink-manager',
            'Scan Site',
            'Scan Site',
            'manage_options',
            'ilm-scan',
            array($this, 'display_scan_page')
        );
    }

    /**
     * Display the dashboard page
     */
    public function display_dashboard_page() {
        include_once ILM_PLUGIN_DIR . 'admin/partials/dashboard.php';
    }

    /**
     * Display the suggestions page
     */
    public function display_suggestions_page() {
        include_once ILM_PLUGIN_DIR . 'admin/partials/suggestions.php';
    }

    /**
     * Display the settings page
     */
    public function display_settings_page() {
        // Save settings if form is submitted
        if (isset($_POST['ilm_save_settings']) && check_admin_referer('ilm_settings_nonce')) {
            $this->save_settings();
        }

        include_once ILM_PLUGIN_DIR . 'admin/partials/settings.php';
    }

    /**
     * Display the scan page
     */
    public function display_scan_page() {
        include_once ILM_PLUGIN_DIR . 'admin/partials/scan.php';
    }

    /**
     * Save plugin settings
     */
    private function save_settings() {
        $settings = array(
            'post_types' => isset($_POST['ilm_post_types']) ? array_map('sanitize_text_field', $_POST['ilm_post_types']) : array('post', 'page'),
            'min_relevance_score' => isset($_POST['ilm_min_relevance_score']) ? intval($_POST['ilm_min_relevance_score']) : 50,
            'max_suggestions_per_page' => isset($_POST['ilm_max_suggestions']) ? intval($_POST['ilm_max_suggestions']) : 10,
            'batch_size' => isset($_POST['ilm_batch_size']) ? intval($_POST['ilm_batch_size']) : 20,
            'exclude_high_link_density' => isset($_POST['ilm_exclude_high_link_density']) ? true : false,
            'link_density_threshold' => isset($_POST['ilm_link_density_threshold']) ? intval($_POST['ilm_link_density_threshold']) : 5,
        );

        update_option('ilm_settings', $settings);

        add_settings_error('ilm_settings', 'settings_updated', 'Settings saved successfully.', 'updated');
    }

    /**
     * AJAX handler for scanning orphaned pages
     */
    public function ajax_scan_orphaned_pages() {
        check_ajax_referer('ilm_nonce', 'nonce');

        if (!current_user_can('manage_options')) {
            wp_send_json_error(array('message' => 'Unauthorized'));
            return;
        }

        $batch_size = isset($_POST['batch_size']) ? intval($_POST['batch_size']) : 20;
        $offset = isset($_POST['offset']) ? intval($_POST['offset']) : 0;

        $result = ILM_Scanner::scan_site($batch_size, $offset);

        wp_send_json_success($result);
    }

    /**
     * AJAX handler for generating suggestions
     */
    public function ajax_generate_suggestions() {
        check_ajax_referer('ilm_nonce', 'nonce');

        if (!current_user_can('manage_options')) {
            wp_send_json_error(array('message' => 'Unauthorized'));
            return;
        }

        $post_id = isset($_POST['post_id']) ? intval($_POST['post_id']) : 0;

        if (!$post_id) {
            wp_send_json_error(array('message' => 'Invalid post ID'));
            return;
        }

        $result = ILM_Analyzer::generate_suggestions($post_id);

        wp_send_json_success($result);
    }

    /**
     * AJAX handler for updating suggestion status
     */
    public function ajax_update_suggestion_status() {
        check_ajax_referer('ilm_nonce', 'nonce');

        if (!current_user_can('manage_options')) {
            wp_send_json_error(array('message' => 'Unauthorized'));
            return;
        }

        $suggestion_id = isset($_POST['suggestion_id']) ? intval($_POST['suggestion_id']) : 0;
        $status = isset($_POST['status']) ? sanitize_text_field($_POST['status']) : '';

        if (!$suggestion_id || !in_array($status, array('accepted', 'rejected', 'pending'))) {
            wp_send_json_error(array('message' => 'Invalid parameters'));
            return;
        }

        $result = ILM_Analyzer::update_suggestion_status($suggestion_id, $status);

        if ($result) {
            wp_send_json_success(array('message' => 'Status updated'));
        } else {
            wp_send_json_error(array('message' => 'Failed to update status'));
        }
    }
}
