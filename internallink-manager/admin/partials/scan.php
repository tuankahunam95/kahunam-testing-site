<?php
/**
 * Scan Site page
 *
 * @package InternalLink_Manager
 */

// If this file is called directly, abort.
if (!defined('WPINC')) {
    die;
}

$settings = get_option('ilm_settings');
$post_types = isset($settings['post_types']) ? $settings['post_types'] : array('post', 'page');
$total_posts = ILM_Scanner::get_total_posts_count($post_types);
?>

<div class="wrap ilm-scan">
    <h1>Scan Site for Orphaned Pages</h1>

    <div class="ilm-scan-info">
        <p>This will scan your entire site to identify pages with zero internal links pointing to them.</p>
        <p><strong>Total posts to scan:</strong> <?php echo esc_html($total_posts); ?></p>
    </div>

    <div class="ilm-scan-controls">
        <button id="ilm-start-scan" class="button button-primary button-hero">
            Start Scanning
        </button>
    </div>

    <div id="ilm-scan-progress" style="display: none;">
        <h3>Scanning in progress...</h3>
        <div class="ilm-progress-bar">
            <div class="ilm-progress-fill" style="width: 0%"></div>
        </div>
        <p class="ilm-progress-text">Processed: <span id="ilm-processed">0</span> / <span id="ilm-total"><?php echo esc_html($total_posts); ?></span></p>
    </div>

    <div id="ilm-scan-results" style="display: none;">
        <h3>Scan Complete!</h3>
        <div class="notice notice-success">
            <p>Successfully scanned <span id="ilm-results-total">0</span> posts.</p>
            <p><strong>Orphaned pages found:</strong> <span id="ilm-results-orphaned">0</span></p>
        </div>
        <p>
            <a href="<?php echo admin_url('admin.php?page=internallink-manager'); ?>" class="button button-primary">
                View Orphaned Pages
            </a>
        </p>
    </div>
</div>

<script type="text/javascript">
jQuery(document).ready(function($) {
    var totalPosts = <?php echo intval($total_posts); ?>;
    var batchSize = <?php echo isset($settings['batch_size']) ? intval($settings['batch_size']) : 20; ?>;
    var offset = 0;
    var processed = 0;

    $('#ilm-start-scan').on('click', function() {
        $(this).prop('disabled', true);
        $('#ilm-scan-progress').show();
        scanBatch();
    });

    function scanBatch() {
        $.ajax({
            url: ilm_ajax.ajax_url,
            type: 'POST',
            data: {
                action: 'ilm_scan_orphaned_pages',
                nonce: ilm_ajax.nonce,
                batch_size: batchSize,
                offset: offset
            },
            success: function(response) {
                if (response.success) {
                    processed += response.data.processed;
                    offset += batchSize;

                    // Update progress bar
                    var percentage = Math.min(100, Math.round((processed / totalPosts) * 100));
                    $('.ilm-progress-fill').css('width', percentage + '%');
                    $('#ilm-processed').text(processed);

                    if (response.data.completed) {
                        // Scan complete
                        showResults();
                    } else {
                        // Continue scanning
                        scanBatch();
                    }
                } else {
                    alert('Error: ' + (response.data.message || 'Unknown error'));
                    resetScan();
                }
            },
            error: function() {
                alert('An error occurred. Please try again.');
                resetScan();
            }
        });
    }

    function showResults() {
        $('#ilm-scan-progress').hide();

        // Get orphaned count
        $.ajax({
            url: ilm_ajax.ajax_url,
            type: 'POST',
            data: {
                action: 'ilm_scan_orphaned_pages',
                nonce: ilm_ajax.nonce,
                get_orphaned_count: true
            },
            success: function(response) {
                $('#ilm-results-total').text(processed);
                $('#ilm-scan-results').show();

                // Reload the page after a short delay to show updated stats
                setTimeout(function() {
                    window.location.href = '<?php echo admin_url('admin.php?page=internallink-manager'); ?>';
                }, 3000);
            }
        });
    }

    function resetScan() {
        $('#ilm-start-scan').prop('disabled', false);
        $('#ilm-scan-progress').hide();
        offset = 0;
        processed = 0;
    }
});
</script>
