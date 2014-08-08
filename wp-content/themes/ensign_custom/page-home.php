<?php
	/* Template Name: Landing Page */
	
	get_header();
	
	// Layer Slider
	$layer_slide_id = rwmb_meta('silverbox_slider');
	
?>

<!-- Start Main Content -->

<section <?php post_class('landing_page'); ?> id="post-<?php the_ID(); ?>">
		
	<?php if(have_posts()) : while(have_posts()) : the_post(); ?>

	<!-- WP Content -->
	
	<article class="lp_content" id="wp_content_<?php the_ID(); ?>">
		
		<?php if($layer_slide_id != "" && (isset($layer_slide_id))) { ?>
		
		<div class="feature">
		
			<?php layerslider($layer_slide_id); ?>
			
		</div>
		
		<? } ?>
			
	</article>
	
	<?php endwhile; endif; ?>
		
</section>

<?php

	get_footer();
	
?>