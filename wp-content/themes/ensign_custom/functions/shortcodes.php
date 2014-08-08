<?php
/***********************************************************************************/
/* Shortcodes */
/***********************************************************************************/
/**
 * Don't auto-p wrap shortcodes that stand alone
 *
 * Ensures that shortcodes are not wrapped in <<p>>...<</p>>.
 *
 * @since 2.9.0
 *
 * @param string $content The content.
 * @return string The filtered content.
 */
function shortcode_unautop2( $content ) {
	global $shortcode_tags;

	if ( empty( $shortcode_tags ) || !is_array( $shortcode_tags ) ) {
		return $content;
	}

	$tagregexp = join( '|', array_map( 'preg_quote', array_keys( $shortcode_tags ) ) );

	$pattern =
		  '/'
		. '<p>'                              // Opening paragraph
		. '\\s*+'                            // Optional leading whitespace
		. '('                                // 1: The shortcode
		.     '\\['                          // Opening bracket
		.     "($tagregexp)"                 // 2: Shortcode name
		.     '(?![\\w-])'                   // Not followed by word character or hyphen
		                                     // Unroll the loop: Inside the opening shortcode tag
		.     '[^\\]\\/]*'                   // Not a closing bracket or forward slash
		.     '(?:'
		.         '\\/(?!\\])'               // A forward slash not followed by a closing bracket
		.         '[^\\]\\/]*'               // Not a closing bracket or forward slash
		.     ')*?'
		.     '(?:'
		.         '\\/\\]'                   // Self closing tag and closing bracket
		.     '|'
		.         '\\]'                      // Closing bracket
		.         '(?:'                      // Unroll the loop: Optionally, anything between the opening and closing shortcode tags
		.             '[^\\[]*+'             // Not an opening bracket
		.             '(?:'
		.                 '\\[(?!\\/\\2\\])' // An opening bracket not followed by the closing shortcode tag
		.                 '[^\\[]*+'         // Not an opening bracket
		.             ')*+'
		.             '\\[\\/\\2\\]'         // Closing shortcode tag
		.         ')?'
		.     ')'
		. ')'
		. '\\s*+'                            // optional trailing whitespace
		. '<\\/p>'                           // closing paragraph
		. '/s';

	return preg_replace( $pattern, '$1', $content );
}

remove_filter( 'the_content', 'wpautop' );
add_filter( 'the_content', 'wpautop' , 99);
add_filter( 'the_content', 'shortcode_unautop2',100 );


// Columns
function column_build($atts, $content=null) {
	extract(shortcode_atts(array(
		'span' => '',
		'last' => '',
		'shift' => ''
	), $atts, 'column_build') );
	
	if($last == "yes") {
		$omega = "omega";
	}
	else {
		$omega = "";
	}
	
	if($shift != "") {
		$shift = "shift_" . $shift;
	}
	
	else {
		$shift = "";
	}
		
	return '
		<div class="span_' . $span . ' ' . $omega . ' ' . $shift . '">' . do_shortcode($content) . "</div>";
}

add_shortcode('column', 'column_build');

// Wrappers
function wrapper_build($atts, $content = null) {
   extract(shortcode_atts(array('class' => 'div'), $atts));
   return '<div class="' . $class . '">' . do_shortcode($content) . '</div>';
}
add_shortcode('div', 'wrapper_build');

// Accordion Wrappers
function accordion($atts, $content = null) {
   return '<dl class="accordion">' . do_shortcode($content) . '</dl>';
}
add_shortcode('accordion', 'accordion');

// Accordion Questions
function question($atts, $content = null) {
   extract(shortcode_atts(array('title' => 'Enter Your Title Here'), $atts));
   return '<div class="accord_row"><dt><a href="" class="faq_icon">FAQ Icon</a><h4 class="faq_title">' . do_shortcode($title) . '</h4></dt><dd>' . do_shortcode($content) . '</dd></div>';
}
add_shortcode('question', 'question');

// Buttons
function button($atts, $content = null) {
	 extract(shortcode_atts(array('title' => 'Button Needs Text', 'class' => "dark_blue_button", 'link' => '#'), $atts));
	 $button_title = $title;
	 $button_class = $class;
	 $button_link = $link;

	return '<p><a href="' . $button_link . '" class="button ' . $class . '">' . $title . '</a></p>';
}
add_shortcode('button', 'button');

?>