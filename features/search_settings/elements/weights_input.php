<?php

//exit if accessed directly
if (!defined('ABSPATH')) {
    exit;
}

// Get the search settings feature instance
$search_settings = $this->get_feature('scrywp_search_settings');
$current_weights = $search_settings->get_search_weights();
$available_factors = $search_settings->get_available_factors();
?>

<div class="scrywp-search-settings">
    <div class="scrywp-settings-header">
        <h2><?php _e('Search Factor Weights', 'scry-wp'); ?></h2>
        <p class="description">
            <?php _e('Configure the importance of different factors in search results. Each factor can have a weight between 0 and 1.', 'scry-wp'); ?>
        </p>
    </div>

    <div class="scrywp-weights-container">
        <div class="scrywp-add-factor-section">
            <h3><?php _e('Add New Factor', 'scry-wp'); ?></h3>
            <div class="scrywp-add-factor-controls">
                <select id="scrywp-factor-select" class="scrywp-factor-dropdown">
                    <option value=""><?php _e('Select a factor...', 'scry-wp'); ?></option>
                    <?php foreach ($available_factors as $factor_key => $factor_label) : ?>
                        <?php if (!isset($current_weights[$factor_key])) : ?>
                            <option value="<?php echo esc_attr($factor_key); ?>"><?php echo esc_html($factor_label); ?></option>
                        <?php endif; ?>
                    <?php endforeach; ?>
                </select>
                <button type="button" id="scrywp-add-factor-btn" class="button button-secondary">
                    <?php _e('Add Factor', 'scry-wp'); ?>
                </button>
            </div>
        </div>

        <div class="scrywp-current-factors">
            <h3><?php _e('Current Factors', 'scry-wp'); ?></h3>
            <div id="scrywp-factors-list">
                <?php foreach ($current_weights as $factor_key => $weight) : ?>
                    <div class="scrywp-factor-item" data-factor="<?php echo esc_attr($factor_key); ?>">
                        <div class="scrywp-factor-header">
                            <span class="scrywp-factor-label"><?php echo esc_html($available_factors[$factor_key] ?? $factor_key); ?></span>
                            <button type="button" class="scrywp-remove-factor button-link-delete" data-factor="<?php echo esc_attr($factor_key); ?>">
                                <?php _e('Remove', 'scry-wp'); ?>
                            </button>
                        </div>
                        <div class="scrywp-weight-control">
                            <label for="scrywp-weight-<?php echo esc_attr($factor_key); ?>">
                                <?php _e('Weight:', 'scry-wp'); ?> <span class="scrywp-weight-value"><?php echo esc_html($weight); ?></span>
                            </label>
                            <input 
                                type="range" 
                                id="scrywp-weight-<?php echo esc_attr($factor_key); ?>"
                                class="scrywp-weight-slider" 
                                min="0" 
                                max="1" 
                                step="0.01" 
                                value="<?php echo esc_attr($weight); ?>"
                                data-factor="<?php echo esc_attr($factor_key); ?>"
                            />
                        </div>
                    </div>
                <?php endforeach; ?>
            </div>
        </div>

        <div class="scrywp-weights-summary">
            <p>
                <strong><?php _e('Total Weight:', 'scry-wp'); ?></strong> 
                <span id="scrywp-total-weight"><?php echo esc_html(array_sum($current_weights)); ?></span>
            </p>
        </div>

        <div class="scrywp-save-section">
            <button type="button" id="scrywp-save-weights" class="button button-primary">
                <?php _e('Save Changes', 'scry-wp'); ?>
            </button>
            <span id="scrywp-save-status"></span>
        </div>
    </div>
</div>