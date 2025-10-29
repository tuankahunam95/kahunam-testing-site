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
        $best_anchor = '';

        // Check for exact title match (highest score)
        if (stripos($sentence, $target_title) !== false) {
            return array(
                'score' => 100,
                'anchor_text' => $target_title,
            );
        }

        // Try to find partial title phrases (very high relevance)
        $title_phrases = self::extract_title_phrases($target_title, $sentence);
        if (!empty($title_phrases)) {
            $longest_phrase = reset($title_phrases);
            $word_count = count(explode(' ', $longest_phrase));

            // Score based on phrase length: 2+ words = 80-95 points
            $phrase_score = min(95, 70 + ($word_count * 8));

            return array(
                'score' => $phrase_score,
                'anchor_text' => $longest_phrase,
            );
        }

        // Check for keyword matches and extract contextual phrases
        foreach ($keywords as $index => $keyword) {
            if (stripos($sentence_lower, $keyword) !== false) {
                // Weight decreases for lower-ranked keywords
                $weight = max(15, 60 - ($index * 3));
                $score += $weight;
                $matched_keywords[] = $keyword;

                // Extract contextual phrase around the keyword
                if (empty($best_anchor)) {
                    $best_anchor = self::extract_contextual_phrase($sentence, $keyword);
                }

                if (count($matched_keywords) >= 2) {
                    break; // Limit to avoid over-matching
                }
            }
        }

        // If we didn't find a good phrase, try to use the most relevant keyword
        if (empty($best_anchor) && !empty($matched_keywords)) {
            $best_anchor = $matched_keywords[0];
        }

        // Boost score if multiple keywords match
        if (count($matched_keywords) > 1) {
            $score = min(100, $score * 1.3);
        }

        return array(
            'score' => min(100, $score),
            'anchor_text' => $best_anchor,
        );
    }

    /**
     * Extract phrases from title that appear in the sentence
     */
    private static function extract_title_phrases($title, $sentence) {
        $title_words = preg_split('/\s+/', strtolower($title));
        $sentence_lower = strtolower($sentence);
        $found_phrases = array();

        // Try to find phrases of 2-5 words from the title
        for ($length = 5; $length >= 2; $length--) {
            for ($i = 0; $i <= count($title_words) - $length; $i++) {
                $phrase = implode(' ', array_slice($title_words, $i, $length));

                // Skip if phrase contains only short words
                $words_in_phrase = explode(' ', $phrase);
                $has_meaningful_word = false;
                foreach ($words_in_phrase as $word) {
                    if (strlen($word) > 3) {
                        $has_meaningful_word = true;
                        break;
                    }
                }

                if (!$has_meaningful_word) {
                    continue;
                }

                if (stripos($sentence_lower, $phrase) !== false) {
                    $found_phrases[] = $phrase;
                    return $found_phrases; // Return first (longest) match
                }
            }
        }

        return $found_phrases;
    }

    /**
     * Extract a contextual phrase around a keyword
     */
    private static function extract_contextual_phrase($sentence, $keyword) {
        $sentence_lower = strtolower($sentence);
        $keyword_lower = strtolower($keyword);

        // Find the position of the keyword
        $pos = stripos($sentence_lower, $keyword_lower);
        if ($pos === false) {
            return $keyword;
        }

        // Split sentence into words
        $words = preg_split('/\s+/', $sentence, -1, PREG_SPLIT_NO_EMPTY | PREG_SPLIT_OFFSET_CAPTURE);

        // Find which word contains the keyword
        $keyword_word_index = -1;
        foreach ($words as $index => $word_data) {
            $word_pos = $word_data[1];
            $word_text = $word_data[0];

            if ($word_pos <= $pos && $pos < $word_pos + strlen($word_text)) {
                $keyword_word_index = $index;
                break;
            }
        }

        if ($keyword_word_index === -1) {
            return $keyword;
        }

        // Extract 2-4 words around the keyword
        $start_index = max(0, $keyword_word_index - 1);
        $end_index = min(count($words) - 1, $keyword_word_index + 2);

        // Build the phrase
        $phrase_words = array();
        for ($i = $start_index; $i <= $end_index; $i++) {
            $word = $words[$i][0];
            // Clean up punctuation
            $word = preg_replace('/[^\w\s-]/', '', $word);
            if (!empty($word)) {
                $phrase_words[] = $word;
            }
        }

        $phrase = implode(' ', $phrase_words);

        // Ensure phrase is not too long
        if (str_word_count($phrase) > 5) {
            // Trim to 4-5 words centered on keyword
            $phrase_words = array_slice($phrase_words, 0, 5);
            $phrase = implode(' ', $phrase_words);
        }

        return !empty($phrase) ? $phrase : $keyword;
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
