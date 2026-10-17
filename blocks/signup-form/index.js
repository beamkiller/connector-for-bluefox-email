/**
 * Editor script for the cfbe/signup-form block (no build step: plain ES5 + WordPress globals).
 * The block is dynamic: the server renders it (Form_Block), the editor shows a ServerSideRender preview.
 */
( function ( wp ) {
	'use strict';

	var el = wp.element.createElement;
	var __ = wp.i18n.__;
	var InspectorControls = wp.blockEditor.InspectorControls;
	var useBlockProps = wp.blockEditor.useBlockProps;
	var PanelBody = wp.components.PanelBody;
	var TextControl = wp.components.TextControl;
	var TextareaControl = wp.components.TextareaControl;
	var SelectControl = wp.components.SelectControl;
	var ToggleControl = wp.components.ToggleControl;
	var Disabled = wp.components.Disabled;
	var ServerSideRender = wp.serverSideRender;

	// Lists cached by the General tab (printed by Form_Block::editor_data()).
	var data = window.cfbeSignupForm || {};
	var lists = Array.isArray( data.lists ) ? data.lists : [];

	function listControl( props ) {
		var value = props.attributes.list;
		var onChange = function ( list ) {
			props.setAttributes( { list: list } );
		};
		if ( ! lists.length ) {
			return el( TextControl, {
				label: __( 'bluefox list ID', 'connector-for-bluefox-email' ),
				help: __( 'Empty = the default list. Load your lists on the plugin’s General tab to pick one from a dropdown.', 'connector-for-bluefox-email' ),
				value: value,
				onChange: onChange,
				__nextHasNoMarginBottom: true,
			} );
		}
		var options = [ { label: __( 'Default list (General tab)', 'connector-for-bluefox-email' ), value: '' } ];
		var known = false;
		lists.forEach( function ( list ) {
			options.push( { label: list.name || list.id, value: list.id } );
			known = known || list.id === value;
		} );
		if ( value && ! known ) {
			options.push( { label: value, value: value } );
		}
		return el( SelectControl, {
			label: __( 'bluefox list', 'connector-for-bluefox-email' ),
			value: value,
			options: options,
			onChange: onChange,
			__nextHasNoMarginBottom: true,
		} );
	}

	function text( props, key, label, help, multiline ) {
		return el( multiline ? TextareaControl : TextControl, {
			label: label,
			help: help,
			value: props.attributes[ key ],
			onChange: function ( value ) {
				var next = {};
				next[ key ] = value;
				props.setAttributes( next );
			},
			__nextHasNoMarginBottom: true,
		} );
	}

	function Edit( props ) {
		var a = props.attributes;
		var fallback = __( 'Empty = the text from the plugin’s Form tab.', 'connector-for-bluefox-email' );

		return el(
			'div',
			useBlockProps(),
			el(
				InspectorControls,
				null,
				el(
					PanelBody,
					{ title: __( 'Subscription', 'connector-for-bluefox-email' ), initialOpen: true },
					listControl( props ),
					text( props, 'tags', __( 'Extra tags', 'connector-for-bluefox-email' ), __( 'Comma-separated bluefox tags added to everyone who subscribes with this form.', 'connector-for-bluefox-email' ) ),
					text( props, 'place', __( 'Placement name', 'connector-for-bluefox-email' ), __( 'Adds a placement-<name> tag, e.g. "sidebar".', 'connector-for-bluefox-email' ) )
				),
				el(
					PanelBody,
					{ title: __( 'Content', 'connector-for-bluefox-email' ), initialOpen: false },
					text( props, 'heading', __( 'Heading', 'connector-for-bluefox-email' ), fallback ),
					text( props, 'intro', __( 'Intro text', 'connector-for-bluefox-email' ), fallback, true ),
					text( props, 'button', __( 'Button label', 'connector-for-bluefox-email' ), fallback )
				),
				el(
					PanelBody,
					{ title: __( 'Layout and fields', 'connector-for-bluefox-email' ), initialOpen: false },
					el( SelectControl, {
						label: __( 'Style', 'connector-for-bluefox-email' ),
						value: a.variant,
						options: [
							{ label: __( 'Form tab setting', 'connector-for-bluefox-email' ), value: '' },
							{ label: __( 'Card', 'connector-for-bluefox-email' ), value: 'card' },
							{ label: __( 'Banner', 'connector-for-bluefox-email' ), value: 'banner' },
							{ label: __( 'Plain', 'connector-for-bluefox-email' ), value: 'plain' },
						],
						onChange: function ( variant ) {
							props.setAttributes( { variant: variant } );
						},
						__nextHasNoMarginBottom: true,
					} ),
					el( ToggleControl, {
						label: __( 'Name field', 'connector-for-bluefox-email' ),
						help: __( 'Shown only when enabled on the Form tab.', 'connector-for-bluefox-email' ),
						checked: !! a.showName,
						onChange: function ( value ) {
							props.setAttributes( { showName: value } );
						},
						__nextHasNoMarginBottom: true,
					} ),
					el( ToggleControl, {
						label: __( 'Interests field', 'connector-for-bluefox-email' ),
						help: __( 'Shown only when enabled on the Form tab.', 'connector-for-bluefox-email' ),
						checked: !! a.showInterests,
						onChange: function ( value ) {
							props.setAttributes( { showInterests: value } );
						},
						__nextHasNoMarginBottom: true,
					} )
				)
			),
			el(
				Disabled,
				null,
				el( ServerSideRender, { block: 'cfbe/signup-form', attributes: a } )
			)
		);
	}

	wp.blocks.registerBlockType( 'cfbe/signup-form', {
		edit: Edit,
		// Dynamic block: rendered on the server.
		save: function () {
			return null;
		},
	} );
}( window.wp ) );
