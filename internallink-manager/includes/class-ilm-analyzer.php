<?php
/**
 * Analyzer class for link opportunity detection
 *
 * @package InternalLink_Manager
 */

class ILM_Analyzer {

    /**
     * Generate link suggestions for an orphaned page
     */
    public static function generate_suggestions($target_post_id) {
        global $wpdb;

        $settings = get_option('ilm_settings');
        $min_relevance_score = isset($settings['min_relevance_score']) ? $settings['min_relevance_score'] : 50;
        $max_suggestions = isset($settings['max_suggestions_per_page']) ? $settings['max_suggestions_per_page'] : 10;

        // Extract keywords from the target post
        $keywords = ILM_Scanner::extract_keywords($target_post_id);

        if (empty($keywords)) {
            return array('success' => false, 'message' => 'No keywords found');
        }

        // Get target post details
        $target_post = get_post($target_post_id);
        $target_title = $target_post->post_title;

        // Get all other posts to search for link opportunities
        $post_types = isset($settings['post_types']) ? $settings['post_types'] : array('post', 'page');

        $args = array(
            'post_type' => $post_types,
            'post_status' => 'publish',
            'posts_per_page' => -1,
            'post__not_in' => array($target_post_id),
        );

        $posts = get_posts($args);
        $suggestions = array();

        foreach ($posts as $post) {
            // Skip if this post already links to the target
            if (self::post_links_to_target($post->ID, $target_post_id)) {
                continue;
            }

            // Find link opportunities in this post
            $opportunities = self::find_link_opportunities($post, $target_post_id, $keywords, $target_title);

            if (!empty($opportunities)) {
                foreach ($opportunities as $opportunity) {
                    if ($opportunity['relevance_score'] >= $min_relevance_score) {
                        $suggestions[] = $opportunity;
                    }
                }
            }
        }

        // Sort by relevance score
        usort($suggestions, function($a, $b) {
            return $b['relevance_score'] - $a['relevance_score'];
        });

        // Limit to max suggestions
        $suggestions = array_slice($suggestions, 0, $max_suggestions);

        // Save suggestions to database
        $table_suggestions = $wpdb->prefix . 'ilm_link_suggestions';

        // First, delete old suggestions for this target post
        $wpdb->delete($table_suggestions, array('target_post_id' => $target_post_id), array('%d'));

        // Insert new suggestions
        foreach ($suggestions as $suggestion) {
            $wpdb->insert(
                $table_suggestions,
                array(
                    'target_post_id' => $target_post_id,
                    'source_post_id' => $suggestion['source_post_id'],
                    'paragraph_index' => $suggestion['paragraph_index'],
                    'sentence_text' => $suggestion['sentence_text'],
                    'anchor_text' => $suggestion['anchor_text'],
                    'relevance_score' => $suggestion['relevance_score'],
                    'status' => 'pending',
                    'created_at' => current_time('mysql'),
                ),
                array('%d', '%d', '%d', '%s', '%s', '%d', '%s', '%s')
            );
        }

        return array(
            'success' => true,
            'count' => count($suggestions),
            'suggestions' => $suggestions,
        );
    }

    /**
     * Check if a post already links to the target post
     */
    private static function post_links_to_target($source_post_id, $target_post_id) {
        $source_post = get_post($source_post_id);
        $target_url = get_permalink($target_post_id);
        $site_url = get_site_url();
        $relative_url = str_replace($site_url, '', $target_url);

        // Check if content contains the link
        return (strpos($source_post->post_content, $target_url) !== false ||
                strpos($source_post->post_content, $relative_url) !== false);
    }

    /**
     * Find link opportunities in a source post
     */
    private static function find_link_opportunities($source_post, $target_post_id, $keywords, $target_title) {
        $opportunities = array();
        $content = wp_strip_all_tags($source_post->post_content);

        // Split content into paragraphs
        $paragraphs = preg_split('/\n\n+/', $content);

        foreach ($paragraphs as $para_index => $paragraph) {
            if (empty(trim($paragraph))) {
                continue;
            }

            // Split paragraph into sentences
            $sentences = preg_split('/(?<=[.!?])\s+/', $paragraph);

            foreach ($sentences as $sentence) {
                // Check if sentence contains keywords
                $relevance = self::calculate_relevance($sentence, $keywords, $target_title);

                if ($relevance['score'] > 0) {
                    $opportunities[] = array(
                        'source_post_id' => $source_post->ID,
                        'paragraph_index' => $para_index,
                        'sentence_text' => trim($sentence),
                        'anchor_text' => $relevance['anchor_text'],
                        'relevance_score' => $relevance['score'],
                    );
                }
            }
        }

        return $opportunities;
    }

    /**
     * Calculate relevance score for a sentence
     */
    private static function calculate_relevance($sentence, $keywords, $target_title) {
        $sentence_lower = strtolower($sentence);
        $score = 0;
        $matched_keywords = array();

        // Check for exact title match (highest score)
        if (stripos($sentence, $target_title) !== false) {
            return array(
                'score' => 100,
                'anchor_text' => $target_title,
            );
        }

        // Check for keyword matches
        foreach ($keywords as $index => $keyword) {
            if (stripos($sentence_lower, $keyword) !== false) {
                // Weight decreases for lower-ranked keywords
                $weight = max(10, 50 - ($index * 2));
                $score += $weight;
                $matched_keywords[] = $keyword;

                if (count($matched_keywords) >= 3) {
                    break; // Don't over-count
                }
            }
        }

        // Determine best anchor text
        $anchor_text = '';
        if (!empty($matched_keywords)) {
            // Try to find the longest matching phrase
            $anchor_text = $matched_keywords[0];

            // Check if we can use a phrase from the title
            $title_words = explode(' ', strtolower($target_title));
            foreach ($title_words as $i => $word) {
                if (strlen($word) < 3) continue;

                if (stripos($sentence_lower, $word) !== false) {
                    // Try to extract a 2-3 word phrase
                    $phrase_length = min(3, count($title_words) - $i);
                    $phrase = implode(' ', array_slice($title_words, $i, $phrase_length));

                    if (stripos($sentence_lower, $phrase) !== false) {
                        $anchor_text = $phrase;
                        break;
                    }
                }
            }
        }

        return array(
            'score' => min(100, $score),
            'anchor_text' => $anchor_text,
        );
    }

    /**
     * Get suggestions for a target post
     */
    public static function get_suggestions($target_post_id, $status = 'pending') {
        global $wpdb;

        $table_suggestions = $wpdb->prefix . 'ilm_link_suggestions';

        $query = $wpdb->prepare(
            "SELECT s.*, p.post_title as source_title
            FROM {$table_suggestions} s
            INNER JOIN {$wpdb->posts} p ON s.source_post_id = p.ID
            WHERE s.target_post_id = %d",
            $target_post_id
        );

        if ($status !== 'all') {
            $query .= $wpdb->prepare(" AND s.status = %s", $status);
        }

        $query .= " ORDER BY s.relevance_score DESC";

        return $wpdb->get_results($query);
    }

    /**
     * Update suggestion status
     */
    public static function update_suggestion_status($suggestion_id, $status) {
        global $wpdb;

        $table_suggestions = $wpdb->prefix . 'ilm_link_suggestions';

        $result = $wpdb->update(
            $table_suggestions,
            array('status' => $status),
            array('id' => $suggestion_id),
            array('%s'),
            array('%d')
        );

        return $result !== false;
    }

    /**
     * Get suggestion count for a target post
     */
    public static function get_suggestions_count($target_post_id, $status = 'pending') {
        global $wpdb;

        $table_suggestions = $wpdb->prefix . 'ilm_link_suggestions';

        $query = $wpdb->prepare(
            "SELECT COUNT(*) FROM {$table_suggestions} WHERE target_post_id = %d",
            $target_post_id
        );

        if ($status !== 'all') {
            $query .= $wpdb->prepare(" AND status = %s", $status);
        }

        return intval($wpdb->get_var($query));
    }
}
