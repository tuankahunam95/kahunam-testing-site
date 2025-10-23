<?php
/**
 * Settings page
 *
 * @package InternalLink_Manager
 */

// If this file is called directly, abort.
if (!defined('WPINC')) {
    die;
}

// Get current settings
$settings = get_option('ilm_settings');

// Get all available post types
$post_types = get_post_types(array('public' => true), 'objects');
?>

<div class="wrap ilm-settings">
    <h1>InternalLink Manager - Settings</h1>

    <?php settings_errors('ilm_settings'); ?>

    <form method="post" action="">
        <?php wp_nonce_field('ilm_settings_nonce'); ?>

        <table class="form-table">
            <tbody>
                <tr>
                    <th scope="row">
                        <label>Post Types to Include</label>
                    </th>
                    <td>
                        <?php foreach ($post_types as $post_type): ?>
                            <?php
                            $checked = in_array($post_type->name, $settings['post_types']) ? 'checked' : '';
                            ?>
                            <label>
                                <input type="checkbox" name="ilm_post_types[]" value="<?php echo esc_attr($post_type->name); ?>" <?php echo $checked; ?>>
                                <?php echo esc_html($post_type->label); ?>
                            </label><br>
                        <?php endforeach; ?>
                        <p class="description">Select which post types to scan for orphaned pages and link opportunities.</p>
                    </td>
                </tr>

                <tr>
                    <th scope="row">
                        <label for="ilm_min_relevance_score">Minimum Relevance Score</label>
                    </th>
                    <td>
                        <input type="number" id="ilm_min_relevance_score" name="ilm_min_relevance_score" value="<?php echo esc_attr($settings['min_relevance_score']); ?>" min="0" max="100" step="1">
                        <p class="description">Only show suggestions with a relevance score above this threshold (0-100).</p>
                    </td>
                </tr>

                <tr>
                    <th scope="row">
                        <label for="ilm_max_suggestions">Max Suggestions per Page</label>
                    </th>
                    <td>
                        <input type="number" id="ilm_max_suggestions" name="ilm_max_suggestions" value="<?php echo esc_attr($settings['max_suggestions_per_page']); ?>" min="1" max="50" step="1">
                        <p class="description">Maximum number of link suggestions to generate per orphaned page.</p>
                    </td>
                </tr>

                <tr>
                    <th scope="row">
                        <label for="ilm_batch_size">Batch Size</label>
                    </th>
                    <td>
                        <input type="number" id="ilm_batch_size" name="ilm_batch_size" value="<?php echo esc_attr($settings['batch_size']); ?>" min="5" max="100" step="5">
                        <p class="description">Number of posts to process per batch during scanning (lower = slower but safer for large sites).</p>
                    </td>
                </tr>

                <tr>
                    <th scope="row">
                        <label for="ilm_exclude_high_link_density">Link Density Settings</label>
                    </th>
                    <td>
                        <label>
                            <input type="checkbox" id="ilm_exclude_high_link_density" name="ilm_exclude_high_link_density" value="1" <?php checked($settings['exclude_high_link_density'], true); ?>>
                            Exclude pages with high link density
                        </label>
                        <br><br>
                        <label for="ilm_link_density_threshold">
                            Link Density Threshold (%):
                            <input type="number" id="ilm_link_density_threshold" name="ilm_link_density_threshold" value="<?php echo esc_attr($settings['link_density_threshold']); ?>" min="1" max="20" step="1">
                        </label>
                        <p class="description">Don't suggest adding links to pages where links make up more than this percentage of the content.</p>
                    </td>
                </tr>
            </tbody>
        </table>

        <p class="submit">
            <input type="submit" name="ilm_save_settings" class="button button-primary" value="Save Settings">
        </p>
    </form>
</div>
