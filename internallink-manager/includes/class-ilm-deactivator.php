<?php
/**
 * Fired during plugin deactivation
 *
 * @package InternalLink_Manager
 */

class ILM_Deactivator {

    /**
     * Deactivate the plugin
     *
     * Cleans up scheduled events and flushes rewrite rules.
     */
    public static function deactivate() {
        // Clear any scheduled cron events
        $timestamp = wp_next_scheduled('ilm_scheduled_scan');
        if ($timestamp) {
            wp_unschedule_event($timestamp, 'ilm_scheduled_scan');
        }

        // Flush rewrite rules
        flush_rewrite_rules();
    }
}
