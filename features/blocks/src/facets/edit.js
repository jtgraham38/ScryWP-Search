/**
 * Retrieves the translation of text.
 *
 * @see https://developer.wordpress.org/block-editor/reference-guides/packages/packages-i18n/
 */
import { __ } from '@wordpress/i18n';

/**
 * React hook that is used to mark the block wrapper element.
 * It provides all the necessary props like the class name.
 *
 * @see https://developer.wordpress.org/block-editor/reference-guides/packages/packages-block-editor/#useblockprops
 */
import { InspectorControls, useBlockProps, __experimentalUseBorderProps as useBorderProps } from '@wordpress/block-editor';
import apiFetch from '@wordpress/api-fetch';
import { useEffect, useState } from '@wordpress/element';

/**
 * Lets webpack process CSS, SASS or SCSS files referenced in JavaScript files.
 * Those files can contain any CSS code that gets applied to the editor.
 *
 * @see https://www.npmjs.com/package/@wordpress/scripts#using-css
 */
import './editor.scss';



import { PanelBody, CheckboxControl, TextControl, Spinner, Notice, SelectControl, RangeControl } from '@wordpress/components';

/**
 * The edit function describes the structure of your block in the context of the
 * editor. This represents what the editor will render when the block is used.
 *
 * @see https://developer.wordpress.org/block-editor/reference-guides/block-api/block-edit-save/#edit
 *
 * @return {Element} Element to render.
 */
export default function Edit({ attributes, setAttributes }) {
	const {
		facets = { taxonomies: {}, meta: {} },
		searchFormClassName = '',
		displayLayout = 'column',
		gridColumns = 3,
	} = attributes;
	const [availableTaxonomies, setAvailableTaxonomies] = useState({});
	const [loading, setLoading] = useState(true);
	const [error, setError] = useState('');
	const [facetSelections, setFacetSelections] = useState({});
	const blockProps = useBlockProps();
	const borderProps = useBorderProps(attributes);
	const wrappedBlockProps = {
		...blockProps,
		className: [blockProps?.className, borderProps?.className].filter(Boolean).join(' '),
		style: {
			...(blockProps?.style || {}),
			...(borderProps?.style || {}),
		},
	};
	useEffect(() => {

		//get the taxonomies to display in the block
		const fetchTaxonomiesAndTerms = async () => {
			setLoading(true);
			setError('');

			try {
				// Get all non-excluded taxonomies + terms in one request from blocks feature endpoint.
				const response = await apiFetch({ path: '/scry-search/v1/blocks/facets/search-facets' });
				setAvailableTaxonomies(response?.taxonomies || {});
				setLoading(false);
			} catch (error) {
				setError(__('Failed to load taxonomies.', 'scry-search'));
				console.error('Failed to load taxonomies for facets block', error);
				setLoading(false);
			}
		};

		fetchTaxonomiesAndTerms();
	}, []);

	//get the taxonomies to display in the block
	const facetsTaxonomies = facets?.taxonomies || {};

	//update the facets to display on the frontend
	const updateFacets = (nextTaxonomies) => {
		setAttributes({
			facets: {
				taxonomies: nextTaxonomies,
				meta: facets?.meta || {},
			},
		});
	};

	//set the taxonomy enabled or disabled
	const setTaxonomyEnabled = (taxonomy, enabled) => {
		const next = { ...facetsTaxonomies };
		if (enabled) {
			if (!Array.isArray(next[taxonomy])) {
				next[taxonomy] = [];
			}
		} else {
			delete next[taxonomy];
		}
		updateFacets(next);
	};

	//set the taxonomy term enabled or disabled
	const setTaxonomyTermEnabled = (taxonomy, term, enabled) => {
		const next = { ...facetsTaxonomies };
		const currentTerms = Array.isArray(next[taxonomy]) ? [...next[taxonomy]] : [];
		const existingIndex = currentTerms.findIndex((item) => item?.id === term.id);

		if (enabled && existingIndex === -1) {
			// Store full term data in attribute.
			currentTerms.push({
				id: term.id,
				name: term.name,
				slug: term.slug,
				taxonomy,
				count: term.count,
				description: term.description,
				link: term.link,
			});
		}

		if (!enabled && existingIndex !== -1) {
			currentTerms.splice(existingIndex, 1);
		}

		next[taxonomy] = currentTerms;
		updateFacets(next);
	};

	const facetTaxonomySlugs = Object.entries(facetsTaxonomies)
		.filter(([, terms]) => Array.isArray(terms) && terms.length > 0)
		.map(([slug]) => slug);

	// Keep checkbox state in sync with Inspector-selected terms.
	useEffect(() => {
		const nextFacetSelections = {};
		Object.entries(facetsTaxonomies).forEach(([taxonomySlug, terms]) => {
			nextFacetSelections[taxonomySlug] = Array.isArray(terms)
				? terms.map((term) => term.id)
				: [];
		});
		setFacetSelections(nextFacetSelections);
	}, [facetsTaxonomies]);

	//toggle the term
	const toggleFacetTerm = (taxonomySlug, termId, checked) => {
		setFacetSelections((prev) => {
			const current = Array.isArray(prev[taxonomySlug]) ? [...prev[taxonomySlug]] : [];
			const exists = current.includes(termId);
			if (checked && !exists) {
				current.push(termId);
			}
			if (!checked && exists) {
				return {
					...prev,
					[taxonomySlug]: current.filter((id) => id !== termId),
				};
			}
			return {
				...prev,
				[taxonomySlug]: current,
			};
		});
	};

	//clear the taxonomy
	const clearFacetTaxonomy = (taxonomySlug) => {
		setFacetSelections((prev) => ({
			...prev,
			[taxonomySlug]: [],
		}));
	};

	//get the container class name and css vars
	const facetsContainerClassName = `scry-facets-container scry-facets-container--${displayLayout}`;
	const facetsContainerCssVars = displayLayout === 'grid'
		? { '--scry-facets-grid-columns': Math.max(1, gridColumns) }
		: undefined;

	return (
		<>
			<InspectorControls>
				<PanelBody title={__('Facet Settings', 'scry-search')} initialOpen={true}>
					<TextControl
						label={__('Search Form Class Name', 'scry-search')}
						help={__('Apply this filter set only to forms using this CSS class.', 'scry-search')}
						value={searchFormClassName}
						onChange={(value) => setAttributes({ searchFormClassName: value })}
					/>
				</PanelBody>

				<PanelBody title={__('Display Options', 'scry-search')} initialOpen={true}>
					<SelectControl
						label={__('Layout', 'scry-search')}
						value={displayLayout}
						options={[
							{ label: __('Column', 'scry-search'), value: 'column' },
							{ label: __('Row', 'scry-search'), value: 'row' },
							{ label: __('Grid', 'scry-search'), value: 'grid' },
						]}
						onChange={(value) => setAttributes({ displayLayout: value })}
						help={__('Choose how facet groups are arranged.', 'scry-search')}
					/>

					{displayLayout === 'grid' && (
						<RangeControl
							label={__('Grid Columns', 'scry-search')}
							value={gridColumns}
							onChange={(value) => setAttributes({ gridColumns: value || 1 })}
							min={2}
							max={6}
							step={1}
						/>
					)}
				</PanelBody>

				<PanelBody title={__('Taxonomies', 'scry-search')} initialOpen={true}>
					{loading && <Spinner />}
					{!!error && (
						<Notice status="error" isDismissible={false}>
							{error}
						</Notice>
					)}

					{!loading && !error && Object.keys(availableTaxonomies).length === 0 && (
						<p>{__('No REST-enabled taxonomies with terms were found.', 'scry-search')}</p>
					)}

					{!loading &&
						!error && (<p>Select the taxonomies to display in the filter set.</p>)

					}

					{!loading &&
						!error &&
						Object.entries(availableTaxonomies).map(([taxonomy, data]) => {
							const isEnabled = Object.prototype.hasOwnProperty.call(facetsTaxonomies, taxonomy);
							const selectedTerms = Array.isArray(facetsTaxonomies[taxonomy]) ? facetsTaxonomies[taxonomy] : [];

							return (
								<div key={taxonomy} style={{ marginBottom: '1rem' }}>
									<CheckboxControl
										label={`${data.label} (${taxonomy})`}
										checked={isEnabled}
										onChange={(checked) => setTaxonomyEnabled(taxonomy, checked)}
									/>

									{isEnabled && (
										<div style={{ marginLeft: '1.5rem' }}>
											{data.terms.map((term) => {
												const checked = selectedTerms.some((item) => item?.id === term.id);
												return (
													<CheckboxControl
														key={`${taxonomy}-${term.id}`}
														label={`${term.name} (${term.count})`}
														checked={checked}
														onChange={(isChecked) => setTaxonomyTermEnabled(taxonomy, term, isChecked)}
													/>
												);
											})}
										</div>
									)}
								</div>
							);
						})}
				</PanelBody>
			</InspectorControls>

			<div className="scry-facets-container">
				{facetTaxonomySlugs.length > 0 && (
					<div className={facetsContainerClassName} style={facetsContainerCssVars}>
						{facetTaxonomySlugs.map((taxonomySlug) => {
							const taxonomyData = availableTaxonomies[taxonomySlug];
							const selectedTerms = Array.isArray(facetsTaxonomies[taxonomySlug]) ? facetsTaxonomies[taxonomySlug] : [];
							const checkedTermIds = Array.isArray(facetSelections[taxonomySlug]) ? facetSelections[taxonomySlug] : selectedTerms.map((term) => term.id);
							const taxonomyLabel = taxonomyData?.label || taxonomySlug;

							return (
								<details
									key={`facet-${taxonomySlug}`}
									open
									{...wrappedBlockProps}
								>
									<summary style={{ cursor: 'pointer', fontWeight: 600, marginBottom: '8px' }}>
										{`${taxonomyLabel}`}
									</summary>

									<div className="scry-facets-terms-container">
										{selectedTerms.length === 0 && (
											<span>{__('No terms available for this taxonomy.', 'scry-search')}</span>
										)}

										{selectedTerms.map((term) => {
											return (
												<label key={`facet-term-${taxonomySlug}-${term.id}`} style={{ display: 'flex', gap: '8px', alignItems: 'center' }}>
													<input
														type="checkbox"
														className="scry-facets-checkbox"
														checked={checkedTermIds.includes(term.id)}
														onChange={(event) => toggleFacetTerm(taxonomySlug, term.id, event.target.checked)}
													/>
													<span>{`${term.name} (${term.count})`}</span>
												</label>
											);
										})}

										<button
											type="button"
											className="scry-facets-clear-button"
											onClick={() => clearFacetTaxonomy(taxonomySlug)}
											aria-label={__('Clear selections', 'scry-search')}
											title={__('Clear selections', 'scry-search')}
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
												<path d="M8.3 15.2H15.7M7.8 17.8H16.2" stroke="white" strokeWidth="1" strokeLinecap="round" />
											</svg>
										</button>
									</div>
								</details>
							);
						})}
					</div>
				)}
			</div>
		</>
	);
}
