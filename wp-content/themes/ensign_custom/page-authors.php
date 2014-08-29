<?php
	/* Template Name: Authors List Page */
	get_header();
	
	// Layer Slider
	$layer_slide_id = rwmb_meta('silverbox_slider');
	
?>

<?php if(have_posts()) : while(have_posts()) : the_post(); ?>

	
	<div class="feature">
	
		<?php layerslider(2); ?>
		
	</div>
	
	
	<!-- Start Main Content -->
	
	<div class="inside">
	
	<section <?php post_class('default_page left_sidebar'); ?> id="post-<?php the_ID(); ?>">
	
		<!-- WP Content -->
		
		<aside class="sidebar span_4">
			
			<?php if (is_active_sidebar('blog-sidebar')) : ?>
				<?php dynamic_sidebar('blog-sidebar'); ?>
			<?php endif;?>
			
		</aside>
						
		<article class="wp_content span_8 omega" id="wp_content_<?php the_ID(); ?>">
					
					
			<h1 class="pg_title">Author Archives</h1>
			
			<ul class="archive_list">
				<?php $args = array(
				    'orderby'       => 'name', 
				    'order'         => 'ASC', 
				    'number'        => null,
				    'optioncount'   => true, 
				    'exclude_admin' => true, 
				    'show_fullname' => true,
				    'hide_empty'    => true,
				    'echo'          => true,
				    'feed_image'    =>'',
				    'feed_type'     => '',
				    'style'         => 'list',
				    'html'          => true,
				    'exclude'       =>'',
				    'include'       => '');
				 
					 // The Query
					 wp_list_authors($args);
				?>
			</ul>			
		</article>	
		
		<!-- Sidebar Bottom -->
		<?php require_once("include/sidebar-bottom.php"); ?>
			
	</section>
	
	</div>
	
<?php endwhile; endif; ?>

<?php

	get_footer();
	
?>