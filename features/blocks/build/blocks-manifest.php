<?php
// This file is generated. Do not modify it manually.
return array(
	'facets' => array(
		'$schema' => 'https://schemas.wp.org/trunk/block.json',
		'apiVersion' => 3,
		'title' => 'Search Facets',
		'keywords' => array(
			'search',
			'facets',
			'filters'
		),
		'name' => 'scry-search/facets',
		'version' => '0.1.0',
		'category' => 'widgets',
		'icon' => 'filter',
		'description' => 'Block containing filters for use in facetted search',
		'example' => array(
			
		),
		'supports' => array(
			'html' => false,
			'color' => array(
				'background' => true,
				'text' => true
			),
			'typography' => array(
				'fontSize' => true
			),
			'spacing' => array(
				'padding' => true
			),
			'__experimentalBorder' => array(
				'color' => true,
				'radius' => true,
				'width' => true,
				'__experimentalSkipSerialization' => true,
				'__experimentalDefaultControls' => array(
					'color' => true,
					'radius' => true,
					'width' => true
				)
			)
		),
		'attributes' => array(
			'facets' => array(
				'type' => 'object',
				'default' => array(
					'taxonomies' => array(
						
					),
					'meta' => array(
						
					)
				)
			),
			'searchFormClassName' => array(
				'type' => 'string',
				'default' => ''
			),
			'displayLayout' => array(
				'type' => 'string',
				'default' => 'column'
			),
			'gridColumns' => array(
				'type' => 'number',
				'default' => 3
			),
			'textColor' => array(
				'type' => 'string',
				'default' => ''
			),
			'backgroundColor' => array(
				'type' => 'string',
				'default' => ''
			),
			'fontSize' => array(
				'type' => 'string',
				'default' => ''
			),
			'style' => array(
				'type' => 'object',
				'default' => array(
					'elements' => array(
						'button' => array(
							'color' => array(
								'text' => '#ffffff',
								'background' => '#000000'
							)
						)
					)
				)
			)
		),
		'textdomain' => 'scry-search',
		'editorScript' => 'file:./index.js',
		'editorStyle' => 'file:./index.css',
		'style' => 'file:./style-index.css',
		'render' => 'file:./render.php',
		'viewScript' => 'file:./view.js'
	)
);
