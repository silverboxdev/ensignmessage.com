<?php
	
	// Main Blog Index Page
	
	get_header();
		
?>

<!-- Start Main Content -->

<section <?php post_class('default_page'); ?> id="post-<?php the_ID(); ?>">
			
	<div class="inside">
		
	<div class="feature">
		<?php //echo do_shortcode('[layerslider id="6"]'); ?>
	</div>	
					
	<article class="wp_content span_8" id="wp_content_<?php the_ID(); ?>">

		<?php if(have_posts()) : while(have_posts()) : the_post(); ?>
		
		<?php get_template_part('posts', get_post_format()); ?>
	
		<?php endwhile; endif; ?>
		
		<?php //wp_pagenavi(); ?>
			
	</article>

	
	<!-- If Right Sidebar -->
	<aside class="sidebar span_4 omega">
		<?php if (is_active_sidebar('blog-sidebar')) : ?>
			<?php dynamic_sidebar('blog-sidebar'); ?>
		<?php endif;?>
	</aside>
	
		
	</div> <!-- / inside -->
			
</section>


<?php

	get_footer();
	
?>