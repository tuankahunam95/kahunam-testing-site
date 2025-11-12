<?php
/**
 * Scanner class for detecting orphaned pages
 *
 * @package InternalLink_Manager
 */

class ILM_Scanner {

    /**
     * Scan all posts and pages for internal links
     */
    public static function scan_site($batch_size = 20, $offset = 0) {
        global $wpdb;

        $settings = get_option('ilm_settings');
        $post_types = isset($settings['post_types']) ? $settings['post_types'] : array('post', 'page');

        // Get all published posts
        $args = array(
            'post_type' => $post_types,
            'post_status' => 'publish',
            'posts_per_page' => $batch_size,
            'offset' => $offset,
            'orderby' => 'ID',
            'order' => 'ASC',
        );

        $posts = get_posts($args);

        if (empty($posts)) {
            return array('completed' => true, 'processed' => 0);
        }

        $processed = 0;

        foreach ($posts as $post) {
            // Count internal links pointing to this post
            $internal_link_count = self::count_internal_links($post->ID);

            // Update or insert into database
            $table_pages = $wpdb->prefix . 'ilm_pages';

            $wpdb->replace(
                $table_pages,
                array(
                    'post_id' => $post->ID,
                    'internal_link_count' => $internal_link_count,
                    'last_scanned' => current_time('mysql'),
                    'is_orphaned' => ($internal_link_count == 0) ? 1 : 0,
                ),
                array('%d', '%d', '%s', '%d')
            );

            $processed++;
        }

        return array(
            'completed' => count($posts) < $batch_size,
            'processed' => $processed,
            'total_posts' => self::get_total_posts_count($post_types),
        );
    }

    /**
     * Count internal links pointing to a specific post
     */
    private static function count_internal_links($post_id) {
        global $wpdb;

        $post_url = get_permalink($post_id);
        $site_url = get_site_url();

        // Extract the relative URL
        $relative_url = str_replace($site_url, '', $post_url);

        // Search for links in all published posts
        $settings = get_option('ilm_settings');
        $post_types = isset($settings['post_types']) ? $settings['post_types'] : array('post', 'page');
        $post_types_string = "'" . implode("','", array_map('esc_sql', $post_types)) . "'";

        // Count posts containing links to this post
        $query = $wpdb->prepare(
            "SELECT COUNT(DISTINCT ID)
            FROM {$wpdb->posts}
            WHERE post_status = 'publish'
            AND post_type IN ({$post_types_string})
            AND ID != %d
            AND (post_content LIKE %s OR post_content LIKE %s)",
            $post_id,
            '%' . $wpdb->esc_like($post_url) . '%',
            '%' . $wpdb->esc_like($relative_url) . '%'
        );

        $count = $wpdb->get_var($query);

        return intval($count);
    }

    /**
     * Get total number of posts to scan
     */
    public static function get_total_posts_count($post_types = array('post', 'page')) {
        $args = array(
            'post_type' => $post_types,
            'post_status' => 'publish',
            'posts_per_page' => -1,
            'fields' => 'ids',
        );

        $query = new WP_Query($args);
        return $query->found_posts;
    }

    /**
     * Get all orphaned pages
     */
    public static function get_orphaned_pages($limit = 100, $offset = 0) {
        global $wpdb;

        $table_pages = $wpdb->prefix . 'ilm_pages';

        $query = $wpdb->prepare(
            "SELECT p.*, posts.post_title, posts.post_type, posts.post_date
            FROM {$table_pages} p
            INNER JOIN {$wpdb->posts} posts ON p.post_id = posts.ID
            WHERE p.is_orphaned = 1
            AND posts.post_status = 'publish'
            ORDER BY posts.post_date DESC
            LIMIT %d OFFSET %d",
            $limit,
            $offset
        );

        return $wpdb->get_results($query);
    }

    /**
     * Get total count of orphaned pages
     */
    public static function get_orphaned_pages_count() {
        global $wpdb;

        $table_pages = $wpdb->prefix . 'ilm_pages';

        $query = "SELECT COUNT(*)
                 FROM {$table_pages} p
                 INNER JOIN {$wpdb->posts} posts ON p.post_id = posts.ID
                 WHERE p.is_orphaned = 1
                 AND posts.post_status = 'publish'";

        return intval($wpdb->get_var($query));
    }

    /**
     * Extract keywords from post content
     */
    public static function extract_keywords($post_id) {
        $post = get_post($post_id);

        if (!$post) {
            return array();
        }

        // Get post title and content
        $title = $post->post_title;
        $content = wp_strip_all_tags($post->post_content);

        // Combine title (weighted more) and content
        $text = $title . ' ' . $title . ' ' . $content;

        // Convert to lowercase
        $text = strtolower($text);

        // Remove punctuation and split into words
        $text = preg_replace('/[^\w\s]/', ' ', $text);
        $words = preg_split('/\s+/', $text, -1, PREG_SPLIT_NO_EMPTY);

        // Remove common stop words
        $stop_words = array('the', 'a', 'an', 'and', 'or', 'but', 'in', 'on', 'at', 'to', 'for', 'of', 'with', 'by', 'from', 'as', 'is', 'was', 'are', 'been', 'be', 'have', 'has', 'had', 'do', 'does', 'did', 'will', 'would', 'should', 'could', 'may', 'might', 'must', 'can', 'this', 'that', 'these', 'those', 'i', 'you', 'he', 'she', 'it', 'we', 'they', 'what', 'which', 'who', 'when', 'where', 'why', 'how');

        $words = array_diff($words, $stop_words);

        // Remove words shorter than 3 characters
        $words = array_filter($words, function($word) {
            return strlen($word) >= 3;
        });

        // Count word frequency
        $word_freq = array_count_values($words);

        // Sort by frequency
        arsort($word_freq);

        // Return top keywords
        return array_slice(array_keys($word_freq), 0, 20);
    }

    /**
     * Get post content as plain text
     */
    public static function get_post_plain_content($post_id) {
        $post = get_post($post_id);

        if (!$post) {
            return '';
        }

        // Strip all HTML tags and shortcodes
        $content = wp_strip_all_tags(strip_shortcodes($post->post_content));

        return $content;
    }

    /**
     * Generate comprehensive report for orphaned pages
     */
    public static function generate_report() {
        global $wpdb;

        $orphaned_pages = self::get_orphaned_pages(1000);
        $report_data = array();

        foreach ($orphaned_pages as $page) {
            $suggestions_count = ILM_Analyzer::get_suggestions_count($page->post_id, 'all');
            $pending_count = ILM_Analyzer::get_suggestions_count($page->post_id, 'pending');
            $accepted_count = ILM_Analyzer::get_suggestions_count($page->post_id, 'accepted');

            $report_data[] = array(
                'post_id' => $page->post_id,
                'title' => $page->post_title,
                'url' => get_permalink($page->post_id),
                'post_type' => $page->post_type,
                'published_date' => $page->post_date,
                'total_suggestions' => $suggestions_count,
                'pending_suggestions' => $pending_count,
                'accepted_suggestions' => $accepted_count,
                'status' => ($pending_count > 0) ? 'Has Suggestions' : 'Needs Analysis',
            );
        }

        return $report_data;
    }

    /**
     * Export report as CSV
     */
    public static function export_report_csv() {
        $report_data = self::generate_report();

        // Set headers for CSV download
        header('Content-Type: text/csv; charset=utf-8');
        header('Content-Disposition: attachment; filename=orphaned-pages-report-' . date('Y-m-d') . '.csv');

        // Create output stream
        $output = fopen('php://output', 'w');

        // Add CSV headers
        fputcsv($output, array(
            'Post ID',
            'Title',
            'URL',
            'Post Type',
            'Published Date',
            'Total Suggestions',
            'Pending Suggestions',
            'Accepted Suggestions',
            'Status'
        ));

        // Add data rows
        foreach ($report_data as $row) {
            fputcsv($output, array(
                $row['post_id'],
                $row['title'],
                $row['url'],
                $row['post_type'],
                $row['published_date'],
                $row['total_suggestions'],
                $row['pending_suggestions'],
                $row['accepted_suggestions'],
                $row['status']
            ));
        }

        fclose($output);
        exit;
    }

    /**
     * Export report as PDF
     */
    public static function export_report_pdf() {
        $report_data = self::generate_report();

        // Include TCPDF library
        require_once(ILM_PLUGIN_DIR . 'includes/tcpdf/tcpdf.php');

        // Create new PDF document
        $pdf = new TCPDF('L', 'mm', 'A4', true, 'UTF-8', false);

        // Set document information
        $pdf->SetCreator('InternalLink Manager');
        $pdf->SetAuthor('InternalLink Manager');
        $pdf->SetTitle('Orphaned Pages Report');
        $pdf->SetSubject('WordPress Internal Linking Report');

        // Remove default header/footer
        $pdf->setPrintHeader(false);
        $pdf->setPrintFooter(false);

        // Set margins
        $pdf->SetMargins(10, 10, 10);
        $pdf->SetAutoPageBreak(true, 10);

        // Add a page
        $pdf->AddPage();

        // Set font
        $pdf->SetFont('helvetica', 'B', 16);

        // Title
        $pdf->Cell(0, 10, 'Orphaned Pages Report', 0, 1, 'C');
        $pdf->SetFont('helvetica', '', 10);
        $pdf->Cell(0, 5, 'Generated on: ' . date('Y-m-d H:i:s'), 0, 1, 'C');
        $pdf->Ln(5);

        // Table header
        $pdf->SetFont('helvetica', 'B', 9);
        $pdf->SetFillColor(0, 115, 170);
        $pdf->SetTextColor(255, 255, 255);

        // Column widths (total should be ~277mm for A4 landscape with margins)
        $w = array(15, 60, 60, 25, 30, 22, 22, 22, 21);

        $pdf->Cell($w[0], 7, 'ID', 1, 0, 'C', true);
        $pdf->Cell($w[1], 7, 'Title', 1, 0, 'C', true);
        $pdf->Cell($w[2], 7, 'URL', 1, 0, 'C', true);
        $pdf->Cell($w[3], 7, 'Type', 1, 0, 'C', true);
        $pdf->Cell($w[4], 7, 'Published Date', 1, 0, 'C', true);
        $pdf->Cell($w[5], 7, 'Total Sug.', 1, 0, 'C', true);
        $pdf->Cell($w[6], 7, 'Pending', 1, 0, 'C', true);
        $pdf->Cell($w[7], 7, 'Accepted', 1, 0, 'C', true);
        $pdf->Cell($w[8], 7, 'Status', 1, 1, 'C', true);

        // Table data
        $pdf->SetFont('helvetica', '', 8);
        $pdf->SetTextColor(0, 0, 0);

        $fill = false;
        foreach ($report_data as $row) {
            $pdf->SetFillColor(249, 249, 249);

            // Get current Y position before row
            $start_y = $pdf->GetY();

            // Calculate row height (use MultiCell to handle long text)
            $title = substr($row['title'], 0, 80); // Limit title length
            $url = substr($row['url'], 0, 80); // Limit URL length

            $pdf->Cell($w[0], 6, $row['post_id'], 1, 0, 'C', $fill);
            $pdf->Cell($w[1], 6, $title, 1, 0, 'L', $fill);
            $pdf->Cell($w[2], 6, $url, 1, 0, 'L', $fill);
            $pdf->Cell($w[3], 6, ucfirst($row['post_type']), 1, 0, 'C', $fill);
            $pdf->Cell($w[4], 6, date('Y-m-d', strtotime($row['published_date'])), 1, 0, 'C', $fill);
            $pdf->Cell($w[5], 6, $row['total_suggestions'], 1, 0, 'C', $fill);
            $pdf->Cell($w[6], 6, $row['pending_suggestions'], 1, 0, 'C', $fill);
            $pdf->Cell($w[7], 6, $row['accepted_suggestions'], 1, 0, 'C', $fill);
            $pdf->Cell($w[8], 6, $row['status'], 1, 1, 'C', $fill);

            $fill = !$fill;
        }

        // Output PDF
        $filename = 'orphaned-pages-report-' . date('Y-m-d') . '.pdf';
        $pdf->Output($filename, 'D');
        exit;
    }
}
