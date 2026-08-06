<?php

if (!defined('ABSPATH')) {
	exit;
}

if (!isset($results) || !is_array($results)) {
	return;
}

/**
 * Markup mirrors features/autosuggest/assets/js/autosuggest.js
 * (scrySearch_renderAutosuggestResults) so HTML and client-built UIs stay in sync.
 */
?>
<div class="scry-search-autosuggest-results-inner">
	<ul class="scry-search-autosuggest-results-list">
		<?php foreach ($results as $result) :
			$url            = isset($result['url']) ? $result['url'] : '#';
			$title          = isset($result['title']) ? $result['title'] : '';
			$featured_image = !empty($result['featured_image']) ? $result['featured_image'] : '';
			?>
			<li class="scry-search-autosuggest-result-item">
				<a href="<?php echo esc_url($url); ?>" class="scry-search-autosuggest-result-link">
					<?php if ($featured_image) : ?>
						<img
							class="scry-search-autosuggest-result-thumb"
							src="<?php echo esc_url($featured_image); ?>"
							alt=""
							loading="lazy"
						/>
					<?php endif; ?>
					<span class="scry-search-autosuggest-result-title"><?php
						// Titles are already limited to plain text + <mark> via sanitize_highlighted_text.
						echo wp_kses(
							$title,
							array(
								'mark' => array(
									'class' => true,
								),
							)
						);
					?></span>
				</a>
			</li>
		<?php endforeach; ?>
	</ul>
	<button type="button" class="scry-search-autosuggest-results-close-button">
		<span class="dashicons dashicons-no-alt"></span>
	</button>
</div>
