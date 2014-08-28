<?php
	
	// Search Results Page
	
	get_header();
		
?>

<!-- Start Main Content -->

<div class="feature">
	<?php echo do_shortcode('[layerslider id="2"]'); ?>
</div>

<div class="inside">

	<section class="default_page left_sidebar" id="post-search">
			
		<aside class="sidebar span_4">
			<?php if (is_active_sidebar('blog-sidebar')) : ?>
				<?php dynamic_sidebar('blog-sidebar'); ?>
			<?php endif;?>
		</aside>
						
		<article class="wp_content span_8 omega" id="wp_content_search">
		
			<h1>Search Results for : <span class="search_string">"<?= $_GET['s']; ?>"...</span></h1>
	
			<?php if(have_posts()) : while(have_posts()) : the_post(); ?>
			
			<?php get_template_part('posts', get_post_format()); ?>
		
			<?php endwhile; else: ?>
			
			<p>We're sorry but there were no results.</p>
			
			<?php endif; ?>
			
			<?php wp_pagenavi(); ?>
				
		</article>
		
		<!-- Sidebar Bottom -->
		<?php require_once("include/sidebar-bottom.php"); ?>
						
	</section>
	
</div> <!-- / inside -->

<?php

	get_footer();
	
?>