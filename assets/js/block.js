( function ( wp ) {
	'use strict';

	var el = wp.element.createElement;
	var __ = wp.i18n.__;
	var InspectorControls = wp.blockEditor.InspectorControls;
	var useBlockProps = wp.blockEditor.useBlockProps;
	var PanelColorSettings = wp.blockEditor.PanelColorSettings;
	var components = wp.components;
	var config = window.leadsCollectorBlock || { connected: false, labels: {}, locked: [], defaults: {} };
	var order = Object.keys( config.labels );

	function current( attributes, key ) {
		var value = attributes[ key ];

		if ( Array.isArray( value ) ) {
			return value.length ? value : config.defaults[ key ];
		}

		return value ? value : config.defaults[ key ];
	}

	/** Toggle one field in a list, keep display order and always keep the fields Lead Manager needs. */
	function toggled( list, field, on ) {
		return order.filter( function ( name ) {
			if ( config.locked.indexOf( name ) !== -1 ) {
				return true;
			}

			return name === field ? on : list.indexOf( name ) !== -1;
		} );
	}

	function preview( attributes ) {
		var fields = current( attributes, 'fields' );
		var required = current( attributes, 'required' );
		var columns = current( attributes, 'columns' );
		var color = current( attributes, 'color' );
		var half = fields.filter( function ( field ) {
			return field !== 'message';
		} );

		var inputs = fields.map( function ( field, index ) {
			var isLastOddHalf = field !== 'message' && half.indexOf( field ) === half.length - 1 && half.length % columns !== 0;

			return el(
				'div',
				{ key: field, style: { gridColumn: field === 'message' || isLastOddHalf || columns === 1 ? '1 / -1' : 'auto', display: 'grid', gap: '6px' } },
				el( 'span', { style: { fontSize: '14px', fontWeight: 600 } }, config.labels[ field ], required.indexOf( field ) !== -1 ? el( 'span', { style: { color: '#e11d48' } }, ' *' ) : null ),
				el( 'div', { style: { minHeight: field === 'message' ? '110px' : '46px', border: '1px solid #d0d5dd', borderRadius: '10px', background: '#fff' } } )
			);
		} );

		inputs.push(
			el(
				'div',
				{ key: 'submit', style: { gridColumn: '1 / -1', padding: '15px', borderRadius: '10px', background: color, color: '#fff', textAlign: 'center', fontWeight: 600 } },
				current( attributes, 'buttonText' )
			)
		);

		return el( 'div', { style: { display: 'grid', gridTemplateColumns: 'repeat(' + columns + ', minmax(0, 1fr))', gap: '16px' } }, inputs );
	}

	function fieldControls( attributes, setAttributes ) {
		var fields = current( attributes, 'fields' );
		var required = current( attributes, 'required' );

		return order.map( function ( field ) {
			var locked = config.locked.indexOf( field ) !== -1;
			var shown = fields.indexOf( field ) !== -1;

			return el(
				'div',
				{ key: field, style: { display: 'flex', justifyContent: 'space-between', alignItems: 'center', gap: '8px', marginBottom: '8px' } },
				el( components.CheckboxControl, {
					__nextHasNoMarginBottom: true,
					label: config.labels[ field ],
					checked: shown,
					disabled: locked,
					onChange: function ( on ) {
						setAttributes( {
							fields: toggled( fields, field, on ),
							required: on ? required : toggled( required, field, false ),
						} );
					},
				} ),
				el( components.ToggleControl, {
					__nextHasNoMarginBottom: true,
					label: __( 'Required', 'leads-collector' ),
					checked: required.indexOf( field ) !== -1,
					disabled: locked || ! shown,
					onChange: function ( on ) {
						setAttributes( { required: toggled( required, field, on ) } );
					},
				} )
			);
		} );
	}

	wp.blocks.registerBlockType( 'leads-collector/form', {
		apiVersion: 3,
		title: __( 'Leads Collector Form', 'leads-collector' ),
		category: 'widgets',
		icon: 'feedback',
		edit: function ( props ) {
			var attributes = props.attributes;
			var setAttributes = props.setAttributes;
			var blockProps = useBlockProps();

			var inspector = el(
				InspectorControls,
				{ key: 'inspector' },
				el(
					components.PanelBody,
					{ title: __( 'Layout', 'leads-collector' ), initialOpen: true },
					el( components.RangeControl, {
						__nextHasNoMarginBottom: true,
						__next40pxDefaultSize: true,
						label: __( 'Columns', 'leads-collector' ),
						help: __( 'Phones always get one column.', 'leads-collector' ),
						min: 1,
						max: 3,
						value: current( attributes, 'columns' ),
						onChange: function ( value ) {
							setAttributes( { columns: value || 0 } );
						},
					} )
				),
				el( components.PanelBody, { title: __( 'Fields', 'leads-collector' ), initialOpen: true }, fieldControls( attributes, setAttributes ) ),
				el( PanelColorSettings, {
					title: __( 'Color', 'leads-collector' ),
					colorSettings: [
						{
							label: __( 'Button and accent', 'leads-collector' ),
							value: current( attributes, 'color' ),
							onChange: function ( value ) {
								setAttributes( { color: value || '' } );
							},
						},
					],
				} ),
				el(
					components.PanelBody,
					{ title: __( 'Text and after sending', 'leads-collector' ), initialOpen: false },
					el( components.TextControl, {
						__nextHasNoMarginBottom: true,
						__next40pxDefaultSize: true,
						label: __( 'Form name in Lead Manager', 'leads-collector' ),
						value: attributes.label,
						placeholder: config.defaults.label,
						onChange: function ( value ) {
							setAttributes( { label: value } );
						},
					} ),
					el( components.TextControl, {
						__nextHasNoMarginBottom: true,
						__next40pxDefaultSize: true,
						label: __( 'Button text', 'leads-collector' ),
						value: attributes.buttonText,
						placeholder: config.defaults.buttonText,
						onChange: function ( value ) {
							setAttributes( { buttonText: value } );
						},
					} ),
					el( components.TextControl, {
						__nextHasNoMarginBottom: true,
						__next40pxDefaultSize: true,
						label: __( 'Message after sending', 'leads-collector' ),
						value: attributes.successMessage,
						placeholder: config.defaults.successMessage,
						onChange: function ( value ) {
							setAttributes( { successMessage: value } );
						},
					} ),
					el( components.TextControl, {
						__nextHasNoMarginBottom: true,
						__next40pxDefaultSize: true,
						type: 'url',
						label: __( 'Go to this page after sending (optional)', 'leads-collector' ),
						value: attributes.redirect,
						onChange: function ( value ) {
							setAttributes( { redirect: value } );
						},
					} ),
					el( components.SelectControl, {
						__nextHasNoMarginBottom: true,
						__next40pxDefaultSize: true,
						label: __( 'Lead source', 'leads-collector' ),
						value: attributes.source,
						options: [
							{ label: __( 'Detect automatically', 'leads-collector' ), value: '' },
							{ label: __( 'Page', 'leads-collector' ), value: 'page' },
							{ label: __( 'Post', 'leads-collector' ), value: 'post' },
							{ label: __( 'Popup', 'leads-collector' ), value: 'popup' },
							{ label: __( 'Other', 'leads-collector' ), value: 'other' },
						],
						onChange: function ( value ) {
							setAttributes( { source: value } );
						},
					} )
				)
			);

			var notice = config.connected
				? null
				: el(
						components.Notice,
						{ status: 'warning', isDismissible: false },
						__( 'Add your site key in Settings → Leads Collector to show this form on the site.', 'leads-collector' ),
						' ',
						el( 'a', { href: config.settingsUrl, target: '_blank', rel: 'noopener noreferrer' }, __( 'Open settings', 'leads-collector' ) )
				  );

			return el( 'div', blockProps, inspector, notice, preview( attributes ) );
		},
		save: function () {
			return null;
		},
	} );
} )( window.wp );
