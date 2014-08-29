<?php
/***********************************************************************************/
/* Define Constants */
/***********************************************************************************/

define('THEMEROOT', get_stylesheet_directory_uri());
define('SCRIPTS', get_stylesheet_directory_uri()) . "/js";
define('IMAGES', THEMEROOT . '/images');
define('FUNC_PATH', $_SERVER['DOCUMENT_ROOT'] . '/wp-content/themes/ensign_custom/functions');


/***********************************************************************************/
/* Menus */
/***********************************************************************************/

function register_my_menus() {
	register_nav_menus(array(
		'top-menu' => __('Top Menu', 'sb'),
		'footer-menu' => __('Footer Menu', 'sb')
	));
}
add_action('init', 'register_my_menus'); 

/***********************************************************************************/
/* Theme Supports */
/***********************************************************************************/

add_theme_support( 'post-thumbnails' ); 


/***********************************************************************************/
/* Register Sidebars */
/***********************************************************************************/

if(function_exists('register_sidebar')) {

	// Page Sidebar Left
	register_sidebar(array(
		'name' => __('Blog Sidebar', 'sb'),
		'id' => 'blog-sidebar',
		'description' => __('Custom sidebar built for the blog', 'sb'),
		'before_widget' => '<ul class="sidebar-widget">',
		'after_widget' => '</ul> <!-- end sidebar widget -->',
		'before_title' => '<h4>',
		'after_title' => '</h4>'
	));

	// Page Sidebar Left
	register_sidebar(array(
		'name' => __('Page Sidebar 1', 'sb'),
		'id' => 'page-sidebar-1',
		'description' => __('Sidebar 1 for standard pages', 'sb'),
		'before_widget' => '<div class="sidebar-widget">',
		'after_widget' => '</div> <!-- end sidebar widget -->',
		'before_title' => '<h4>',
		'after_title' => '</h4>'
	));
	
	// Page Sidebar Right
	register_sidebar(array(
		'name' => __('Page Sidebar 2', 'sb'),
		'id' => 'page-sidebar-2',
		'description' => __('Sidebar 2 for standard pages', 'sb'),
		'before_widget' => '<div class="sidebar-widget">',
		'after_widget' => '</div> <!-- end sidebar widget -->',
		'before_title' => '<h4>',
		'after_title' => '</h4>'
	));
	
	// Area 3, located in the footer. Empty by default.
	register_sidebar( array(
		'name' => __( 'First Footer Widget Area', 'sb' ),
		'id' => 'footer-widget-area-1',
		'description' => __( 'The first footer widget area', 'sb' ),
		'before_widget' => '<div id="%1$s" class="widget-container %2$s">',
		'after_widget' => '</div>',
		'before_title' => '<h3 class="widget-title">',
		'after_title' => '</h3>',
	) );

	// Area 4, located in the footer. Empty by default.
	register_sidebar( array(
		'name' => __( 'Second Footer Widget Area', 'sb' ),
		'id' => 'footer-widget-area-2',
		'description' => __( 'The second footer widget area', 'sb' ),
		'before_widget' => '<div id="%1$s" class="widget-container %2$s">',
		'after_widget' => '</div>',
		'before_title' => '<h3 class="widget-title">',
		'after_title' => '</h3>',
	) );

	// Area 5, located in the footer. Empty by default.
	register_sidebar( array(
		'name' => __( 'Third Footer Widget Area', 'sb' ),
		'id' => 'footer-widget-area-3',
		'description' => __( 'The third footer widget area', 'sb' ),
		'before_widget' => '<div id="%1$s" class="widget-container %2$s">',
		'after_widget' => '</div>',
		'before_title' => '<h3 class="widget-title">',
		'after_title' => '</h3>',
	) );
	
	// Area 5, located in the footer. Empty by default.
	register_sidebar( array(
		'name' => __( 'Fourth Footer Widget Area', 'sb' ),
		'id' => 'footer-widget-area-4',
		'description' => __( 'The Fourth footer widget area', 'sb' ),
		'before_widget' => '<div id="%1$s" class="widget-container %2$s">',
		'after_widget' => '</div>',
		'before_title' => '<h3 class="widget-title">',
		'after_title' => '</h3>',
	) );
	
	// Area 6, located in the footer. Empty by default.
	register_sidebar( array(
		'name' => __( 'Copyright Text', 'sb' ),
		'id' => 'copyright-widget',
		'description' => __( 'Copyright Text', 'sb' ),
		'before_widget' => '<span id="%1$s" class="widget-container %2$s">',
		'after_widget' => '</span>',
		'before_title' => '<h3 class="widget-title">',
		'after_title' => '</h3>',
	) );
	
}

/***********************************************************************************/
/* Extensions */
/***********************************************************************************/

// Replaces the excerpt "more" text by a link
function new_excerpt_more($more) {
       global $post;
	return '...<p class="read_more"><a class="button blue_button" href="'. get_permalink($post->ID) . '">Read More</a></p>';
}
add_filter('excerpt_more', 'new_excerpt_more');


function my_excerpt_protected( $excerpt ) {
    if ( post_password_required() )
        $excerpt = '<p>This article is password protected. If you are subscriber <a href="/contact">contact</a> the editor to receive your password.  If not, <a href="/subscriptions">subscribe</a> today.</p>';
    return $excerpt;
}
add_filter( 'the_excerpt', 'my_excerpt_protected' );

function my_password_form() {
    global $post;
    $label = 'pwbox-'.( empty( $post->ID ) ? rand() : $post->ID );
    $o = '<form action="' . esc_url( site_url( 'wp-login.php?action=postpass', 'login_post' ) ) . '" method="post">
    ' . __( 'This article is password protected. To view it please enter your password below. If you are subscriber <a href="/contact">contact</a> the editor to receive your password.  If not, <a href="/subscriptions">subscribe</a> today.</p>') . '
   <p><label for="' . $label . '">' . __( "Password:" ) . ' </label><input name="post_password" id="' . $label . '" type="password" size="20" maxlength="20" /><input type="submit" name="Submit" value="' . esc_attr__( "Submit" ) . '" class="button blue_button" />
    </p></form>
    ';
    return $o;
}
add_filter( 'the_password_form', 'my_password_form' );

function the_title_trim($title) {
	$title = attribute_escape($title);
	$findthese = array(
		'#Protected:#',
		'#Private:#'
	);
	$replacewith = array(
		'', // What to replace "Protected:" with
		'' // What to replace "Private:" with
	);
	$title = preg_replace($findthese, $replacewith, $title);
	return $title;
}
add_filter('the_title', 'the_title_trim');

/***********************************************************************************/
/* Enqueue Styles */
/***********************************************************************************/

/*function wptuts_styles_with_the_lot()
{
    // Register the style like this for a plugin:
    wp_register_style( 'custom-style', plugins_url( '/css/custom-style.css', __FILE__ ), array(), '20120208', 'all' );
    // or
    // Register the style like this for a theme:
    wp_register_style( 'custom-style', get_template_directory_uri() . '/css/custom-style.css', array(), '20120208', 'all' );
 
    // For either a plugin or a theme, you can then enqueue the style:
    wp_enqueue_style( 'custom-style' );
}
add_action( 'wp_enqueue_scripts', 'wptuts_styles_with_the_lot' );*/

/***********************************************************************************/
/* Enqueue Scripts */
/***********************************************************************************/

function wptuts_scripts_with_jquery()
{

	// Deregister the included library
    wp_deregister_script( 'jquery' );
     
    // Register the library again from Google's CDN
    wp_register_script( 'jquery', '//code.jquery.com/jquery-1.11.1.min.js', array(), null, false );
    
    // Register the script like this for a theme:
    wp_register_script( 'html5shiv', get_template_directory_uri() . '/js/html5shiv.js', array( 'jquery' ) );
    
    wp_register_script( 'general', get_template_directory_uri() . '/js/general.js', array( 'jquery' ));
 
    // For either a plugin or a theme, you can then enqueue the script:
    wp_enqueue_script( 'general' );
    wp_enqueue_script( 'html5shiv' );
}

add_action( 'wp_enqueue_scripts', 'wptuts_scripts_with_jquery' );

/**
 * TypeKit Fonts
 *
 * @since Theme 1.0
 */
function theme_typekit() {
    wp_enqueue_script( 'theme_typekit', '//use.typekit.net/tsk0zxz.js');
}
add_action( 'wp_enqueue_scripts', 'theme_typekit' );
function theme_typekit_inline() {
  if ( wp_script_is( 'theme_typekit', 'done' ) ) { ?>
  	<script type="text/javascript">try{Typekit.load();}catch(e){}</script>
<?php }
}
add_action( 'wp_head', 'theme_typekit_inline' );

/***********************************************************************************/
/* Silverbox Image */
/***********************************************************************************/

function change_my_wp_login_image() {
echo "
<style>
body.login #login h1 a {
background: url('http://briandominey.com/images/bdd-wp.png') 0 0 no-repeat transparent;
height:76px;
width:325px;
position: relative;
left: 0px;
 }
</style>
";
}
add_action("login_head", "change_my_wp_login_image");

function my_login_logo_url() {
    return 'http://silverboxdev.com/contact';
}
add_filter( 'login_headerurl', 'my_login_logo_url' );

function my_login_logo_url_title() {
    return 'Silverbox Development';
}
add_filter( 'login_headertitle', 'my_login_logo_url_title' );


/***********************************************************************************/
/* Included Functions */
/***********************************************************************************/

require_once(FUNC_PATH . "/shortcodes.php");
require_once(FUNC_PATH . "/metaboxes.php");

?>