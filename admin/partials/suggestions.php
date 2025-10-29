<?php
/**
 * Link Suggestions page
 *
 * @package InternalLink_Manager
 */

// If this file is called directly, abort.
if (!defined('WPINC')) {
    die;
}

// Get post ID from URL
$post_id = isset($_GET['post_id']) ? intval($_GET['post_id']) : 0;

if ($post_id) {
    $target_post = get_post($post_id);
    $suggestions = ILM_Analyzer::get_suggestions($post_id, 'all');
} else {
    $target_post = null;
    $suggestions = array();
}
?>

<div class="wrap ilm-suggestions">
    <h1>Link Suggestions</h1>

    <?php if (!$post_id): ?>
        <div class="notice notice-warning">
            <p>Please select a post from the <a href="<?php echo admin_url('admin.php?page=internallink-manager'); ?>">dashboard</a>.</p>
        </div>
    <?php elseif (!$target_post): ?>
        <div class="notice notice-error">
            <p>Invalid post ID.</p>
        </div>
    <?php else: ?>
        <div class="ilm-target-post-info">
            <h2>Suggestions for: <?php echo esc_html($target_post->post_title); ?></h2>
            <p>
                <a href="<?php echo get_permalink($post_id); ?>" target="_blank">View Post</a> |
                <a href="<?php echo get_edit_post_link($post_id); ?>">Edit Post</a>
            </p>
        </div>

        <?php if (empty($suggestions)): ?>
            <div class="notice notice-info">
                <p>No suggestions found for this post. <button class="button ilm-generate-suggestions-single" data-post-id="<?php echo esc_attr($post_id); ?>">Generate Suggestions</button></p>
            </div>
        <?php else: ?>
            <div class="ilm-suggestions-list">
                <?php foreach ($suggestions as $index => $suggestion): ?>
                    <?php
                    $source_edit_url = get_edit_post_link($suggestion->source_post_id);
                    $source_view_url = get_permalink($suggestion->source_post_id);
                    $status_class = 'ilm-status-' . $suggestion->status;
                    ?>
                    <div class="ilm-suggestion-card <?php echo esc_attr($status_class); ?>" data-suggestion-id="<?php echo esc_attr($suggestion->id); ?>">
                        <div class="ilm-suggestion-header">
                            <h3>Suggestion #<?php echo $index + 1; ?></h3>
                            <span class="ilm-relevance-score">Relevance: <?php echo esc_html($suggestion->relevance_score); ?>%</span>
                        </div>

                        <div class="ilm-suggestion-body">
                            <div class="ilm-suggestion-field">
                                <label>Source Page:</label>
                                <strong><?php echo esc_html($suggestion->source_title); ?></strong>
                                <div class="ilm-suggestion-links">
                                    <a href="<?php echo esc_url($source_view_url); ?>" target="_blank">View</a> |
                                    <a href="<?php echo esc_url($source_edit_url); ?>" target="_blank">Edit</a>
                                </div>
                            </div>

                            <div class="ilm-suggestion-field">
                                <label>Paragraph Index:</label>
                                <span><?php echo esc_html($suggestion->paragraph_index); ?></span>
                            </div>

                            <div class="ilm-suggestion-field">
                                <label>Context (Sentence):</label>
                                <div class="ilm-sentence-context">
                                    <?php echo esc_html($suggestion->sentence_text); ?>
                                </div>
                            </div>

                            <div class="ilm-suggestion-field">
                                <label>Suggested Anchor Text:</label>
                                <code class="ilm-anchor-text"><?php echo esc_html($suggestion->anchor_text); ?></code>
                                <button class="button button-small ilm-copy-anchor" data-anchor="<?php echo esc_attr($suggestion->anchor_text); ?>">Copy</button>
                            </div>

                            <div class="ilm-suggestion-field">
                                <label>Status:</label>
                                <span class="ilm-status-badge ilm-status-badge-<?php echo esc_attr($suggestion->status); ?>">
                                    <?php echo esc_html(ucfirst($suggestion->status)); ?>
                                </span>
                            </div>
                        </div>

                        <div class="ilm-suggestion-actions">
                            <?php if ($suggestion->status === 'pending'): ?>
                                <button class="button button-primary ilm-accept-suggestion" data-suggestion-id="<?php echo esc_attr($suggestion->id); ?>">
                                    Mark as Accepted
                                </button>
                                <button class="button ilm-reject-suggestion" data-suggestion-id="<?php echo esc_attr($suggestion->id); ?>">
                                    Reject
                                </button>
                            <?php elseif ($suggestion->status === 'accepted'): ?>
                                <button class="button ilm-reset-suggestion" data-suggestion-id="<?php echo esc_attr($suggestion->id); ?>">
                                    Reset to Pending
                                </button>
                            <?php elseif ($suggestion->status === 'rejected'): ?>
                                <button class="button ilm-reset-suggestion" data-suggestion-id="<?php echo esc_attr($suggestion->id); ?>">
                                    Reset to Pending
                                </button>
                            <?php endif; ?>
                        </div>
                    </div>
                <?php endforeach; ?>
            </div>
        <?php endif; ?>
    <?php endif; ?>
</div>

<script type="text/javascript">
jQuery(document).ready(function($) {
    // Copy anchor text
    $('.ilm-copy-anchor').on('click', function() {
        var anchor = $(this).data('anchor');
        navigator.clipboard.writeText(anchor).then(function() {
            alert('Anchor text copied to clipboard!');
        });
    });

    // Update suggestion status
    function updateSuggestionStatus(suggestionId, status) {
        $.ajax({
            url: ilm_ajax.ajax_url,
            type: 'POST',
            data: {
                action: 'ilm_update_suggestion_status',
                nonce: ilm_ajax.nonce,
                suggestion_id: suggestionId,
                status: status
            },
            success: function(response) {
                if (response.success) {
                    location.reload();
                } else {
                    alert('Error: ' + (response.data.message || 'Unknown error'));
                }
            },
            error: function() {
                alert('An error occurred. Please try again.');
            }
        });
    }

    // Accept suggestion
    $('.ilm-accept-suggestion').on('click', function() {
        var suggestionId = $(this).data('suggestion-id');
        updateSuggestionStatus(suggestionId, 'accepted');
    });

    // Reject suggestion
    $('.ilm-reject-suggestion').on('click', function() {
        var suggestionId = $(this).data('suggestion-id');
        updateSuggestionStatus(suggestionId, 'rejected');
    });

    // Reset suggestion
    $('.ilm-reset-suggestion').on('click', function() {
        var suggestionId = $(this).data('suggestion-id');
        updateSuggestionStatus(suggestionId, 'pending');
    });

    // Generate suggestions for single post
    $('.ilm-generate-suggestions-single').on('click', function(e) {
        e.preventDefault();

        var button = $(this);
        var postId = button.data('post-id');

        button.prop('disabled', true).text('Generating...');

        $.ajax({
            url: ilm_ajax.ajax_url,
            type: 'POST',
            data: {
                action: 'ilm_generate_suggestions',
                nonce: ilm_ajax.nonce,
                post_id: postId
            },
            success: function(response) {
                if (response.success) {
                    alert('Generated ' + response.data.count + ' suggestions!');
                    location.reload();
                } else {
                    alert('Error: ' + (response.data.message || 'Unknown error'));
                    button.prop('disabled', false).text('Generate Suggestions');
                }
            },
            error: function() {
                alert('An error occurred. Please try again.');
                button.prop('disabled', false).text('Generate Suggestions');
            }
        });
    });
});
</script>
