<?php
/*
Plugin Name: CHIPS Custom Formats
Description: Creates an options page for adding custom formats to the WordPress WYSIWYG editor.
Author: CHIPS
Version: 1.0.0
Author URI: http://chips.nyc
*/

if( function_exists('acf_add_options_page') ) :
	acf_add_options_page(array(
		'page_title' => 'WYSIWYG Formats',
		'parent_slug' => 'options-general.php'
	));
endif;

if( function_exists('register_field_group') ):

register_field_group(array (
	'key' => 'group_57850aa2de4e2',
	'title' => 'WYSIWYG Formats',
	'fields' => array (
		array (
			'key' => 'field_57850aa81e81a',
			'label' => 'Formats',
			'name' => 'formats',
			'prefix' => '',
			'type' => 'repeater',
			'instructions' => '',
			'required' => 0,
			'conditional_logic' => 0,
			'wrapper' => array (
				'width' => '',
				'class' => '',
				'id' => '',
			),
			'min' => '',
			'max' => '',
			'layout' => 'row',
			'button_label' => 'Add Format',
			'sub_fields' => array (
				array (
					'key' => 'field_57850acf1e81b',
					'label' => 'Title',
					'name' => 'title',
					'prefix' => '',
					'type' => 'text',
					'instructions' => '',
					'required' => 0,
					'conditional_logic' => 0,
					'wrapper' => array (
						'width' => '',
						'class' => '',
						'id' => '',
					),
					'default_value' => '',
					'placeholder' => '',
					'prepend' => '',
					'append' => '',
					'maxlength' => '',
					'readonly' => 0,
					'disabled' => 0,
				),
				array (
					'key' => 'field_57850c2b1e81c',
					'label' => 'Type',
					'name' => 'type',
					'prefix' => '',
					'type' => 'select',
					'instructions' => '',
					'required' => 0,
					'conditional_logic' => 0,
					'wrapper' => array (
						'width' => '',
						'class' => '',
						'id' => '',
					),
					'choices' => array (
						'inline' => 'Inline',
						'block' => 'Block',
					),
					'default_value' => array (
						'inline' => 'inline',
					),
					'allow_null' => 0,
					'multiple' => 0,
					'ui' => 0,
					'ajax' => 0,
					'placeholder' => '',
					'disabled' => 0,
					'readonly' => 0,
				),
				array (
					'key' => 'field_57850d421e81f',
					'label' => 'Inline',
					'name' => 'inline',
					'prefix' => '',
					'type' => 'text',
					'instructions' => 'Name of the inline element to produce for example "span". The current text selection will be wrapped in this inline element.',
					'required' => 0,
					'conditional_logic' => array (
						array (
							array (
								'field' => 'field_57850c2b1e81c',
								'operator' => '==',
								'value' => 'inline',
							),
						),
					),
					'wrapper' => array (
						'width' => '',
						'class' => '',
						'id' => '',
					),
					'default_value' => 'span',
					'placeholder' => '',
					'prepend' => '',
					'append' => '',
					'maxlength' => '',
					'readonly' => 0,
					'disabled' => 0,
				),
				array (
					'key' => 'field_57850dc51e821',
					'label' => 'Classes',
					'name' => 'classes',
					'prefix' => '',
					'type' => 'text',
					'instructions' => 'Space separated list of classes to apply the the selected elements or the new inline/block element.',
					'required' => 0,
					'conditional_logic' => 0,
					'wrapper' => array (
						'width' => '',
						'class' => '',
						'id' => '',
					),
					'default_value' => '',
					'placeholder' => '',
					'prepend' => '',
					'append' => '',
					'maxlength' => '',
					'readonly' => 0,
					'disabled' => 0,
				),
				array (
					'key' => 'field_57850c7f1e81e',
					'label' => 'Block',
					'name' => 'block',
					'prefix' => '',
					'type' => 'radio',
					'instructions' => 'Name of the block element to produce for example "h1". Existing block elements within the selection gets replaced with the new block element.',
					'required' => 0,
					'conditional_logic' => array (
						array (
							array (
								'field' => 'field_57850c2b1e81c',
								'operator' => '==',
								'value' => 'block',
							),
						),
					),
					'wrapper' => array (
						'width' => '',
						'class' => '',
						'id' => '',
					),
					'choices' => array (
						'p' => 'p',
						'a' => 'a',
						'h1' => 'h1',
						'h2' => 'h2',
						'h3' => 'h3',
						'h4' => 'h4',
						'h5' => 'h5',
						'ul' => 'ul',
						'ol' => 'ol',
						'li' => 'li',
						'img' => 'img',
						'div' => 'div',
					),
					'other_choice' => 1,
					'save_other_choice' => 1,
					'default_value' => '',
					'layout' => 'vertical',
				),
				array (
					'key' => 'field_57850d821e820',
					'label' => 'Selector',
					'name' => 'selector',
					'prefix' => '',
					'type' => 'text',
					'instructions' => 'CSS 3 selector pattern to find elements within the selection by. This can be used to apply classes to specific elements or complex things like odd rows in a table.',
					'required' => 0,
					'conditional_logic' => 0,
					'wrapper' => array (
						'width' => '',
						'class' => '',
						'id' => '',
					),
					'default_value' => '',
					'placeholder' => '',
					'prepend' => '',
					'append' => '',
					'maxlength' => '',
					'readonly' => 0,
					'disabled' => 0,
				),
				array (
					'key' => 'field_57850e681e823',
					'label' => 'Exact',
					'name' => 'exact',
					'prefix' => '',
					'type' => 'true_false',
					'instructions' => 'Disables the merge similar styles feature when used. This is needed for some CSS inheritance issues such as text-decoration for underline/strikethough.',
					'required' => 0,
					'conditional_logic' => 0,
					'wrapper' => array (
						'width' => '',
						'class' => '',
						'id' => '',
					),
					'message' => '',
					'default_value' => 0,
				),
				array (
					'key' => 'field_57850c681e81d',
					'label' => 'Wrapper',
					'name' => 'wrapper',
					'prefix' => '',
					'type' => 'true_false',
					'instructions' => 'State that tells that the current format is a container format for block elements. For example a div wrapper or blockquote.',
					'required' => 0,
					'conditional_logic' => 0,
					'wrapper' => array (
						'width' => '',
						'class' => '',
						'id' => '',
					),
					'message' => '',
					'default_value' => 0,
				),
			),
		),
	),
	'location' => array (
		array (
			array (
				'param' => 'options_page',
				'operator' => '==',
				'value' => 'acf-options-wysiwyg-formats',
			),
		),
	),
	'menu_order' => 0,
	'position' => 'normal',
	'style' => 'default',
	'label_placement' => 'top',
	'instruction_placement' => 'field',
	'hide_on_screen' => '',
));

endif;

// CUSTOM WYSIWYG FORMATS
add_filter('mce_css', 'chips_editor_format');
function chips_editor_format($url) {
	if ( !empty($url) )
		$url .= ',';
	// Retrieves the plugin directory URL
	// Change the path here if using different directories
	$url .= trailingslashit( get_stylesheet_directory_uri() ) . '/css/editor-style.css';
	return $url;
}
/** Add "Styles" drop-down */
add_filter( 'mce_buttons_2', 'chips_format_buttons' );

function chips_format_buttons( $buttons ) {
	array_unshift( $buttons, 'styleselect' );
	return $buttons;
}

/** Add styles/classes to the "Styles" drop-down */
add_filter( 'tiny_mce_before_init', 'chips_mce_before_init' );

function chips_mce_before_init( $settings ) {

	$style_formats = array();

    $formats = get_field('formats','options');

    if($formats){
    	foreach($formats as $frmt){
	    	$fArray = array();

	    	$fArray['title'] = $frmt['title'];
	    	if($frmt['type'] === 'inline' && $frmt['type'] !== null){
	    		$fArray['inline'] = $frmt['inline'];
	    	} else {
	    		$fArray['block'] = $frmt['block'];
	    	}

	    	if($frmt['classes'] !== '' && $frmt['classes'] !== null){
	    		$fArray['classes'] = $frmt['classes'];
	    	}

	    	if($frmt['selector'] !== '' && $frmt['selector'] !== null){
	    		$fArray['selector'] = $frmt['selector'];
	    	}

	    	if($frmt['exact']){
	    		$fArray['exact'] = true;
	    	}

	    	if($frmt['wrapper']){
	    		$fArray['wrapper'] = true;
	    	}

			$style_formats[] = $fArray;
		}
    }

	$settings['style_formats'] = json_encode( $style_formats );

	return $settings;

}