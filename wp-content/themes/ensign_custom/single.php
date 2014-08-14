<?php
	
	// Single Blog or Custom Post Type Post
	
	get_header();
	
	// Page Title
	$title_display = rwmb_meta('silverbox_title');
		
?>

<!-- Start Main Content -->

<div class="feature">
	<?php echo do_shortcode('[layerslider id="2"]'); ?>
</div>

<div class="inside">

	<section <?php post_class('default_page left_sidebar'); ?> id="post-<?php the_ID(); ?>">
			
		<aside class="sidebar span_4">
			<?php if (is_active_sidebar('blog-sidebar')) : ?>
				<?php dynamic_sidebar('blog-sidebar'); ?>
			<?php endif;?>
		</aside>
						
		<article class="wp_content span_8 omega" id="wp_content_<?php the_ID(); ?>">
	
			<?php if(have_posts()) : while(have_posts()) : the_post(); ?>
			
			<?php 
				if($title_display != "off") { ?>
					<h1 class="pg_title"><?= the_title(); ?></h1>
			<?php } ?>
			
			<p class="author_meta">By <? echo the_author_posts_link(); ?></p>
			
			<?php the_content(); ?>
			
			<div class="post_links"><?php previous_post_link('%link', 'Previous Post'); ?> | <?php next_post_link('%link', 'Next Post'); ?></div>
		
			<?php endwhile; endif; ?>
			
				
		</article>
		
		<!-- Sidebar Bottom -->
		<?php require_once("include/sidebar-bottom.php"); ?>
						
	</section>
	
</div> <!-- / inside -->

<?php

	get_footer();
	
?>