<?php
/**
 * Fired during plugin activation
 *
 * @package InternalLink_Manager
 */

class ILM_Activator {

    /**
     * Activate the plugin
     *
     * Creates necessary database tables and sets default options.
     */
    public static function activate() {
        global $wpdb;

        $charset_collate = $wpdb->get_charset_collate();

        // Table 1: wp_ilm_pages - Track pages and their internal link status
        $table_pages = $wpdb->prefix . 'ilm_pages';
        $sql_pages = "CREATE TABLE IF NOT EXISTS $table_pages (
            id bigint(20) UNSIGNED NOT NULL AUTO_INCREMENT,
            post_id bigint(20) UNSIGNED NOT NULL,
            internal_link_count int(11) DEFAULT 0,
            last_scanned datetime DEFAULT CURRENT_TIMESTAMP,
            is_orphaned tinyint(1) DEFAULT 0,
            PRIMARY KEY  (id),
            UNIQUE KEY post_id (post_id),
            KEY is_orphaned (is_orphaned),
            KEY last_scanned (last_scanned)
        ) $charset_collate;";

        // Table 2: wp_ilm_link_suggestions - Store link suggestions
        $table_suggestions = $wpdb->prefix . 'ilm_link_suggestions';
        $sql_suggestions = "CREATE TABLE IF NOT EXISTS $table_suggestions (
            id bigint(20) UNSIGNED NOT NULL AUTO_INCREMENT,
            target_post_id bigint(20) UNSIGNED NOT NULL,
            source_post_id bigint(20) UNSIGNED NOT NULL,
            paragraph_index int(11) DEFAULT 0,
            sentence_text text,
            anchor_text varchar(255),
            relevance_score int(11) DEFAULT 0,
            status varchar(20) DEFAULT 'pending',
            created_at datetime DEFAULT CURRENT_TIMESTAMP,
            PRIMARY KEY  (id),
            KEY target_post_id (target_post_id),
            KEY source_post_id (source_post_id),
            KEY status (status),
            KEY relevance_score (relevance_score)
        ) $charset_collate;";

        require_once(ABSPATH . 'wp-admin/includes/upgrade.php');
        dbDelta($sql_pages);
        dbDelta($sql_suggestions);

        // Set default options
        $default_settings = array(
            'post_types' => array('post', 'page'),
            'min_relevance_score' => 50,
            'max_suggestions_per_page' => 10,
            'batch_size' => 20,
            'exclude_high_link_density' => true,
            'link_density_threshold' => 5, // max 5% link density
        );

        if (!get_option('ilm_settings')) {
            add_option('ilm_settings', $default_settings);
        }

        // Store plugin version
        add_option('ilm_version', ILM_VERSION);

        // Flush rewrite rules
        flush_rewrite_rules();
    }
}
