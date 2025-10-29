<?php
/**
 * Dashboard page - Display orphaned pages
 *
 * @package InternalLink_Manager
 */

// If this file is called directly, abort.
if (!defined('WPINC')) {
    die;
}

// Get orphaned pages
$orphaned_pages = ILM_Scanner::get_orphaned_pages(100);
$total_orphaned = ILM_Scanner::get_orphaned_pages_count();
?>

<div class="wrap ilm-dashboard">
    <h1>InternalLink Manager - Dashboard</h1>

    <div class="ilm-stats-container">
        <div class="ilm-stat-box">
            <h3>Orphaned Pages</h3>
            <div class="ilm-stat-number"><?php echo esc_html($total_orphaned); ?></div>
            <p>Pages with zero internal links</p>
        </div>
    </div>

    <div class="ilm-actions">
        <a href="<?php echo admin_url('admin.php?page=ilm-scan'); ?>" class="button button-primary">
            Scan Site Now
        </a>
        <?php if ($total_orphaned > 0): ?>
            <a href="<?php echo wp_nonce_url(admin_url('admin.php?ilm_export_report=1'), 'ilm_export_report'); ?>" class="button">
                Export Report (CSV)
            </a>
        <?php endif; ?>
    </div>

    <h2>Orphaned Pages</h2>

    <?php if (empty($orphaned_pages)): ?>
        <div class="notice notice-info">
            <p>No orphaned pages found. Click "Scan Site Now" to scan your site for orphaned pages.</p>
        </div>
    <?php else: ?>
        <table class="wp-list-table widefat fixed striped">
            <thead>
                <tr>
                    <th>Title</th>
                    <th>Post Type</th>
                    <th>Published Date</th>
                    <th>Suggestions</th>
                    <th>Actions</th>
                </tr>
            </thead>
            <tbody>
                <?php foreach ($orphaned_pages as $page): ?>
                    <?php
                    $suggestions_count = ILM_Analyzer::get_suggestions_count($page->post_id, 'pending');
                    $edit_url = get_edit_post_link($page->post_id);
                    $view_url = get_permalink($page->post_id);
                    $suggestions_url = admin_url('admin.php?page=ilm-suggestions&post_id=' . $page->post_id);
                    ?>
                    <tr>
                        <td>
                            <strong><?php echo esc_html($page->post_title); ?></strong>
                            <div class="row-actions">
                                <span class="view">
                                    <a href="<?php echo esc_url($view_url); ?>" target="_blank">View</a> |
                                </span>
                                <span class="edit">
                                    <a href="<?php echo esc_url($edit_url); ?>">Edit</a>
                                </span>
                            </div>
                        </td>
                        <td><?php echo esc_html(ucfirst($page->post_type)); ?></td>
                        <td><?php echo esc_html(date('Y-m-d', strtotime($page->post_date))); ?></td>
                        <td>
                            <?php if ($suggestions_count > 0): ?>
                                <span class="ilm-badge ilm-badge-success"><?php echo esc_html($suggestions_count); ?> found</span>
                            <?php else: ?>
                                <span class="ilm-badge ilm-badge-warning">0</span>
                            <?php endif; ?>
                        </td>
                        <td>
                            <?php if ($suggestions_count > 0): ?>
                                <a href="<?php echo esc_url($suggestions_url); ?>" class="button button-small">
                                    View Suggestions
                                </a>
                            <?php else: ?>
                                <button class="button button-small ilm-generate-suggestions" data-post-id="<?php echo esc_attr($page->post_id); ?>">
                                    Generate Suggestions
                                </button>
                            <?php endif; ?>
                        </td>
                    </tr>
                <?php endforeach; ?>
            </tbody>
        </table>
    <?php endif; ?>
</div>

<script type="text/javascript">
jQuery(document).ready(function($) {
    // Generate suggestions button handler
    $('.ilm-generate-suggestions').on('click', function(e) {
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
