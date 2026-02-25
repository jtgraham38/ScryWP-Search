<?php
/**
 * Dynamic render template for facets block.
 *
 * Exposed variables:
 * - $attributes (array)
 * - $content (string)
 * - $block (WP_Block)
 */

use jtgraham38\jgwordpressstyle\BlockStyle;

// Shared helper: convert preset tokens into CSS custom property values.
if (!function_exists('scry_facets_convert_preset_value')) {
	/**
	 * Converts Gutenberg preset tokens to CSS var references.
	 *
	 * Examples:
	 * - var:preset|spacing|30 => var(--wp--preset--spacing--30)
	 * - var:preset|color|contrast => var(--wp--preset--color--contrast)
	 *
	 * @param string $value Raw style value from block attributes.
	 * @return string
	 */
	function scry_facets_convert_preset_value($value) {
		if (!is_string($value) || strpos($value, 'var:preset|') !== 0) {
			return is_string($value) ? $value : '';
		}

		$parts = explode('|', $value);
		if (count($parts) !== 3) {
			return $value;
		}

		return sprintf(
			'var(--wp--preset--%s--%s)',
			sanitize_key($parts[1]),
			sanitize_key($parts[2])
		);
	}
}

// Shared helper: normalize CSS length values (e.g. "10" -> "10px").
if (!function_exists('scry_facets_normalize_css_length')) {
	/**
	 * Normalizes CSS lengths while preserving preset tokens and explicit units.
	 *
	 * @param mixed  $value Raw style value.
	 * @param string $default_unit Unit to use for unitless numeric values.
	 * @return string
	 */
	function scry_facets_normalize_css_length($value, $default_unit = 'px') {
		if (!is_string($default_unit) || !preg_match('/^(px|em|rem|%|vw|vh)$/', $default_unit)) {
			$default_unit = 'px';
		}

		if (is_int($value) || is_float($value)) {
			return (string) $value . $default_unit;
		}

		if (!is_string($value)) {
			return '';
		}

		$trimmed = trim($value);
		if ($trimmed === '') {
			return '';
		}

		if (strpos($trimmed, 'var:preset|') === 0) {
			return scry_facets_convert_preset_value($trimmed);
		}

		if (preg_match('/^-?\d+(\.\d+)?$/', $trimmed)) {
			return $trimmed . $default_unit;
		}

		return $trimmed;
	}
}

// Read raw block attributes with defensive defaults.
$facets            = isset($attributes['facets']) && is_array($attributes['facets']) ? $attributes['facets'] : array();
$facets_taxonomies = isset($facets['taxonomies']) && is_array($facets['taxonomies']) ? $facets['taxonomies'] : array();
$search_form_class = isset($attributes['searchFormClassName']) ? (string) $attributes['searchFormClassName'] : '';

// Normalize layout controls from block attributes.
$display_layout = isset($attributes['displayLayout']) ? (string) $attributes['displayLayout'] : 'column';
if (!in_array($display_layout, array('column', 'row', 'grid'), true)) {
	$display_layout = 'column';
}

// Normalize grid columns to a safe positive integer.
$grid_columns = isset($attributes['gridColumns']) ? (int) $attributes['gridColumns'] : 3;
$grid_columns = max(1, $grid_columns);

// Keep only taxonomy groups that contain selected terms.
$facet_taxonomies = array_filter(
	$facets_taxonomies,
	static function ($terms) {
		return is_array($terms) && count($terms) > 0;
	}
);

// Build layout classes and optional CSS variable used by grid.
$facets_container_class = 'scry-facets-container scry-facets-container--' . $display_layout;
$facets_container_style = $display_layout === 'grid'
	? '--scry-facets-grid-columns:' . $grid_columns
	: '';

// Build additional frontend classes, including search-form scoping classes.
$extra_classes = array('scry-facets');
if ($search_form_class !== '') {
	$search_tokens = preg_split('/\s+/', trim($search_form_class));
	if (is_array($search_tokens)) {
		foreach ($search_tokens as $token) {
			$sanitized_token = sanitize_html_class($token);
			if ($sanitized_token !== '') {
				$extra_classes[] = $sanitized_token;
			}
		}
	}
}

// Collect inline style declarations for each rendered taxonomy group.
$details_style_parts = array();
$has_border_radius_style = false;

// Extract typography/color/spacing/border values via the BlockStyle helper library.
if (class_exists(BlockStyle::class)) {
	$style = new BlockStyle($attributes);

	// Guard all helper method calls since malformed style data may throw.
	$read_style_value = static function ($method_name) use ($style) {
		try {
			if (!method_exists($style, $method_name)) {
				return null;
			}
			return $style->{$method_name}();
		} catch (\Throwable $error) {
			return null;
		}
	};

	// Apply text color as class (preset) or inline style (custom value).
	$text_color = $read_style_value('textColor');
	if ($text_color && !empty($text_color->value) && is_string($text_color->value)) {
		if ($text_color->isPreset) {
			$extra_classes[] = 'has-text-color';
			$extra_classes[] = 'has-' . sanitize_html_class($text_color->value) . '-color';
		} else {
			$details_style_parts[] = 'color:' . scry_facets_convert_preset_value($text_color->value);
		}
	}

	// Apply background color as class (preset) or inline style (custom value).
	$background_color = $read_style_value('bgColor');
	if ($background_color && !empty($background_color->value) && is_string($background_color->value)) {
		if ($background_color->isPreset) {
			$extra_classes[] = 'has-background';
			$extra_classes[] = 'has-' . sanitize_html_class($background_color->value) . '-background-color';
		} else {
			$details_style_parts[] = 'background-color:' . scry_facets_convert_preset_value($background_color->value);
		}
	}

	// Apply font size as class (preset) or inline style (custom value).
	$font_size = $read_style_value('fontSize');
	if ($font_size && !empty($font_size->value) && is_string($font_size->value)) {
		if ($font_size->isPreset) {
			$extra_classes[] = 'has-' . sanitize_html_class($font_size->value) . '-font-size';
		} else {
			$details_style_parts[] = 'font-size:' . scry_facets_convert_preset_value($font_size->value);
		}
	}

	// Apply border color as class (preset) or inline style (custom value).
	$border_color = $read_style_value('borderColor');
	if ($border_color && !empty($border_color->value) && is_string($border_color->value)) {
		if ($border_color->isPreset) {
			$extra_classes[] = 'has-border-color';
			$extra_classes[] = 'has-' . sanitize_html_class($border_color->value) . '-border-color';
		} else {
			$details_style_parts[] = 'border-color:' . scry_facets_convert_preset_value($border_color->value);
		}
	}

	// Apply border width when available.
	$border_width = $read_style_value('borderWidth');
	if ($border_width && !empty($border_width->value) && is_string($border_width->value)) {
		$normalized_border_width = scry_facets_normalize_css_length($border_width->value);
		$details_style_parts[] = 'border-style:solid';
		$details_style_parts[] = 'border-width:' . $normalized_border_width;
	}

	// Apply border radius when available.
	$border_radius = $read_style_value('borderRadius');
	if ($border_radius && !empty($border_radius->value) && is_string($border_radius->value)) {
		$details_style_parts[] = 'border-radius:' . scry_facets_normalize_css_length($border_radius->value);
		$has_border_radius_style = true;
	}

	// Apply per-side padding values when they are valid.
	$padding = $read_style_value('padding');
	foreach (is_array($padding) ? $padding : array() as $side => $padding_value) {
		if (empty($padding_value->value)) {
			continue;
		}
		$normalized_side = sanitize_key($side);
		if (!in_array($normalized_side, array('top', 'right', 'bottom', 'left'), true)) {
			continue;
		}
		$details_style_parts[] = 'padding-' . $normalized_side . ':' . scry_facets_convert_preset_value($padding_value->value);
	}
}

// Fallback for border radius when Gutenberg stores per-corner values as an array.
if (!$has_border_radius_style && isset($attributes['style']['border']['radius'])) {
	$raw_border_radius = $attributes['style']['border']['radius'];
	$raw_border_radius_array = is_array($raw_border_radius) ? $raw_border_radius : array();
	$raw_border_unit = isset($raw_border_radius_array['unit']) && is_string($raw_border_radius_array['unit'])
		? $raw_border_radius_array['unit']
		: 'px';

	// Apply a single border-radius value.
	if (is_string($raw_border_radius) && $raw_border_radius !== '') {
		$details_style_parts[] = 'border-radius:' . scry_facets_normalize_css_length($raw_border_radius, $raw_border_unit);
		$has_border_radius_style = true;
	} elseif (is_numeric($raw_border_radius)) {
		$details_style_parts[] = 'border-radius:' . scry_facets_normalize_css_length($raw_border_radius, $raw_border_unit);
		$has_border_radius_style = true;
	}

	// Apply per-corner border radius values with flexible key matching.
	if (is_array($raw_border_radius)) {
		$corner_radius_values = array(
			'top-left' => '',
			'top-right' => '',
			'bottom-right' => '',
			'bottom-left' => '',
		);

		foreach ($raw_border_radius as $corner_key => $corner_value) {
			$normalized_corner_key = strtolower((string) preg_replace('/[^a-z]/i', '', (string) $corner_key));
			$css_corner = '';
			if (in_array($normalized_corner_key, array('topleft', 'topleftradius'), true)) {
				$css_corner = 'top-left';
			} elseif (in_array($normalized_corner_key, array('topright', 'toprightradius'), true)) {
				$css_corner = 'top-right';
			} elseif (in_array($normalized_corner_key, array('bottomright', 'bottomrightradius'), true)) {
				$css_corner = 'bottom-right';
			} elseif (in_array($normalized_corner_key, array('bottomleft', 'bottomleftradius'), true)) {
				$css_corner = 'bottom-left';
			}

			if ($css_corner === '') {
				continue;
			}

			if (is_string($corner_value) && $corner_value !== '') {
				$normalized_corner_value = scry_facets_normalize_css_length($corner_value, $raw_border_unit);
				if ($normalized_corner_value === '') {
					continue;
				}
				$corner_radius_values[$css_corner] = $normalized_corner_value;
				$has_border_radius_style = true;
				continue;
			}

			if (is_numeric($corner_value)) {
				$corner_radius_values[$css_corner] = scry_facets_normalize_css_length($corner_value, $raw_border_unit);
				$has_border_radius_style = true;
				continue;
			}

			// Support nested corner objects like { value: "10", unit: "px" }.
			if (is_array($corner_value) && isset($corner_value['value'])) {
				$corner_unit = isset($corner_value['unit']) && is_string($corner_value['unit'])
					? $corner_value['unit']
					: $raw_border_unit;
				$normalized_corner_value = scry_facets_normalize_css_length($corner_value['value'], $corner_unit);
				if ($normalized_corner_value === '') {
					continue;
				}
				$corner_radius_values[$css_corner] = $normalized_corner_value;
				$has_border_radius_style = true;
			}
		}

		// Output a single 4-corner shorthand when we have all corners.
		if (
			$corner_radius_values['top-left'] !== ''
			&& $corner_radius_values['top-right'] !== ''
			&& $corner_radius_values['bottom-right'] !== ''
			&& $corner_radius_values['bottom-left'] !== ''
		) {
			$details_style_parts[] = 'border-radius:'
				. $corner_radius_values['top-left'] . ' '
				. $corner_radius_values['top-right'] . ' '
				. $corner_radius_values['bottom-right'] . ' '
				. $corner_radius_values['bottom-left'];
		} else {
			// Otherwise, emit only the corner declarations that are present.
			foreach ($corner_radius_values as $css_corner => $corner_radius_value) {
				if ($corner_radius_value === '') {
					continue;
				}
				$details_style_parts[] = 'border-' . $css_corner . '-radius:' . $corner_radius_value;
			}
		}
	}
}

// Clip inner content to rounded edges so corners render consistently.
if ($has_border_radius_style) {
	$details_style_parts[] = 'overflow:hidden';
}

// Flatten all style declarations into a single inline style string.
$details_style = implode(';', $details_style_parts);

// Read selected facet IDs from the current URL query params.
$selected_facet_ids = array();
if (isset($_GET['scry-search']) && is_array($_GET['scry-search']) && isset($_GET['scry-search']['facets'])) {
	$raw_selected_facets = $_GET['scry-search']['facets'];
	if (!is_array($raw_selected_facets)) {
		$raw_selected_facets = array($raw_selected_facets);
	}

	$selected_facet_ids = array_values(
		array_unique(
			array_filter(
				array_map(
					'absint',
					wp_unslash($raw_selected_facets)
				),
				static function ($facet_id) {
					return $facet_id > 0;
				}
			)
		)
	);
}

// Expose server-rendered facet attributes to the Interactivity API context.
$interactivity_context_json = wp_json_encode(
	array(
		'facetTaxonomies' => $facet_taxonomies,
		'searchFormClassName' => $search_form_class,
		'selectedFacetIds' => $selected_facet_ids,
	)
);
if (!is_string($interactivity_context_json) || $interactivity_context_json === '') {
	$interactivity_context_json = '{}';
}
?>

<div
	class="scry-facets-container"
	data-wp-interactive="scry-search/facets"
	data-wp-init="callbacks.init"
	data-wp-context="<?php echo esc_attr($interactivity_context_json); ?>"
>

	<!-- <pre data-wp-text="state.allStateText"> -->

	</pre>
	<?php // Render only when at least one taxonomy has selected terms. ?>
	<?php if (!empty($facet_taxonomies)) : ?>
		<div
			class="<?php echo esc_attr($facets_container_class); ?>"
			<?php if ($facets_container_style !== '') : ?>
				style="<?php echo esc_attr($facets_container_style); ?>"
			<?php endif; ?>
		>
			<?php // Render one accordion group per selected taxonomy. ?>
			<?php foreach ($facet_taxonomies as $taxonomy_slug => $selected_terms) : ?>
				<?php
				// Resolve a human-readable taxonomy label for summary text.
				$taxonomy_object = get_taxonomy($taxonomy_slug);
				$taxonomy_label  = !empty($taxonomy_object->label) ? $taxonomy_object->label : $taxonomy_slug;

				// Build wrapper attributes with block support classes + computed styles.
				$details_attributes = get_block_wrapper_attributes(
					array(
						'class' => implode(' ', array_unique($extra_classes)),
						'style' => $details_style,
					)
				);
				?>
				<details
					<?php echo $details_attributes; ?>
					open
				>
					<summary style="cursor:pointer;font-weight:600;margin-bottom:8px;">
						<?php echo esc_html($taxonomy_label); ?>
					</summary>

					<?php // Render selected terms for this taxonomy. ?>
					<div class="scry-facets-terms-container">
						<?php foreach ($selected_terms as $term) : ?>
							<?php
							// Normalize term fields before output.
							$term_id    = isset($term['id']) ? (int) $term['id'] : 0;
							$term_name  = isset($term['name']) ? (string) $term['name'] : '';
							$term_count = isset($term['count']) ? (int) $term['count'] : 0;
							$is_checked = in_array($term_id, $selected_facet_ids, true);
							?>
							<label style="display:flex;gap:8px;align-items:center;">
								<input
									type="checkbox"
									class="scry-facets-checkbox"
									value="<?php echo esc_attr($term_id); ?>"
									<?php checked($is_checked); ?>
									data-wp-on--change="actions.handleFacetChange"
								/>
								<span><?php echo esc_html(sprintf('%s (%d)', $term_name, $term_count)); ?></span>
							</label>
						<?php endforeach; ?>

						<?php // Render clear-selection control to match editor preview markup. ?>
						<button
							type="button"
							class="scry-facets-clear-button"
							aria-label="<?php esc_attr_e('Clear selections', 'scry-search'); ?>"
							title="<?php esc_attr_e('Clear selections', 'scry-search'); ?>"
							data-scry-taxonomy-slug="<?php echo esc_attr($taxonomy_slug); ?>"
							data-wp-on--click="actions.clearTaxonomyFacets"
						>
							<svg
								aria-hidden="true"
								width="14"
								height="14"
								viewBox="0 0 24 24"
								fill="none"
								xmlns="http://www.w3.org/2000/svg"
							>
								<path d="M11 3H13V12.2H11V3Z" fill="currentColor" />
								<path d="M6 12.2H18L16 21H8L6 12.2Z" fill="currentColor" />
								<path d="M8.3 15.2H15.7M7.8 17.8H16.2" stroke="white" stroke-width="1" stroke-linecap="round" />
							</svg>
						</button>
					</div>
				</details>
			<?php endforeach; ?>
		</div>
	<?php endif; ?>
</div>