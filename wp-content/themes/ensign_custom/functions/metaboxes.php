<?php
/**
 * Registering meta boxes
 *
 * All the definitions of meta boxes are listed below with comments.
 * Please read them CAREFULLY.
 *
 * You also should read the changelog to know what has been changed before updating.
 *
 * For more information, please visit:
 * @link http://www.deluxeblogtips.com/meta-box/
 */


add_filter( 'rwmb_meta_boxes', 'silverbox_register_meta_boxes' );

/**
 * Register meta boxes
 *
 * @return void
 */
function silverbox_register_meta_boxes( $meta_boxes )
{
	/**
	 * Prefix of meta keys (optional)
	 * Use underscore (_) at the beginning to make keys hidden
	 * Alt.: You also can make prefix empty to disable it
	 */
	// Better has an underscore as last sign
	$prefix = 'silverbox_';

	// 1st meta box
	$meta_boxes[] = array(
		// Meta box id, UNIQUE per meta box. Optional since 4.1.5
		'id' => 'extras',

		// Meta box title - Will appear at the drag and drop handle bar. Required.
		'title' => __( 'Sidebar Options', 'rwmb' ),

		// Post types, accept custom post types as well - DEFAULT is array('post'). Optional.
		'pages' => array( 'post', 'page' ),

		// Where the meta box appear: normal (default), advanced, side. Optional.
		'context' => 'normal',

		// Order of meta box: high (default), low. Optional.
		'priority' => 'high',

		// Auto save: true, false (default). Optional.
		'autosave' => true,

		// List of meta fields
		'fields' => array(
			// SELECT BOX
			array(
				'name'     => __( 'Sidebar Position', 'rwmb' ),
				'id'       => "{$prefix}position",
				'type'     => 'select',
				// Array of 'value' => 'Label' pairs for select box
				'options'  => array(
					'left_sidebar' => __( 'Left Sidebar', 'rwmb' ),
					'right_sidebar' => __( 'Right Sidebar', 'rwmb' ),
					'no_sidebar' => __('Hide Sidebar', 'rwmb'),
				),
				// Select multiple values, optional. Default is false.
				'multiple'    => false,
				'std'         => '',
				'placeholder' => __( 'Select an Item', 'rwmb' ),
			),
			// SELECT BOX
			array(
				'name'     => __( 'Sidebar Selection', 'rwmb' ),
				'id'       => "{$prefix}selection",
				'type'     => 'select',
				// Array of 'value' => 'Label' pairs for select box
				'options'  => array(
					'page-sidebar-1' => __( 'Sidebar 1', 'rwmb' ),
					'page-sidebar-2' => __( 'Sidebar 2', 'rwmb' ),
					'blog-sidebar' => __('Blog Sidebar', 'rwmb'),
				),
				// Select multiple values, optional. Default is false.
				'multiple'    => false,
				'std'         => '',
				'placeholder' => __( 'Select an Item', 'rwmb' ),
			),
			// Layer slider
			array(
				// Field name - Will be used as label
				'name'  => __( 'Layer Slider ID', 'rwmb' ),
				// Field ID, i.e. the meta key
				'id'    => "{$prefix}slider",
				// Field description (optional)
				'desc'  => __( 'The number found in the layer slider embed code', 'rwmb' ),
				'type'  => 'text',
				// Default value (optional)
				'std'   => __( '', 'rwmb' ),
				// CLONES: Add to make the field cloneable (i.e. have multiple value)
				'clone' => false,
			),
			// SELECT BOX
			array(
				'name'     => __( 'Title Display', 'rwmb' ),
				'id'       => "{$prefix}title",
				'type'     => 'select',
				// Array of 'value' => 'Label' pairs for select box
				'options'  => array(
					'on' => __( 'on', 'rwmb' ),
					'off' => __( 'off', 'rwmb' ),
				),
				// Select multiple values, optional. Default is false.
				'multiple'    => false,
				'std'         => '',
				'placeholder' => __( 'Select an Item', 'rwmb' ),
			),
			
			
		),
	);

	
	return $meta_boxes;
}

