<?php
	get_header();
	
	// Grab Sidebar Selection
	
	$sidebar_selection = rwmb_meta( 'silverbox_selection' );
		
	// Grab Sidebar Position
	
	$sidebar_position = rwmb_meta( 'silverbox_position' );
	
	if($sidebar_position == "no_sidebar" || $sidebar_position == "") {
		$sidebar_class = "span_12 last";
		$page_class = "no_sidebar";
	}
	
	elseif($sidebar_position == "left_sidebar") {
		$sidebar_class = "span_8 last";
		$page_class = "left_sidebar";
	}
	
	else {
		$sidebar_class = "span_8";
		$page_class = "right_sidebar";
	}
	
	// Page Title
	$title_display = rwmb_meta('silverbox_title');
	
	// Layer Slider
	$layer_slide_id = rwmb_meta('silverbox_slider');
	
?>

<?php if(have_posts()) : while(have_posts()) : the_post(); ?>


	<?php if($layer_slide_id != "" && (isset($layer_slide_id))) { ?>
	
	<div class="feature">
	
		<?php layerslider($layer_slide_id); ?>
		
	</div>
	
	<?php } ?>
	
	
	<!-- Start Main Content -->
	
	<div class="inside">
	
	<section <?php post_class('default_page ' . $page_class); ?> id="post-<?php the_ID(); ?>">
	
		<!-- WP Content -->
		
		
		<!-- If Left Sidebar -->
		<?php if($sidebar_position == "left_sidebar") { ?>
		
			<aside class="sidebar span_4">
				
				<?php if (is_active_sidebar($sidebar_selection)) : ?>
					<?php dynamic_sidebar($sidebar_selection); ?>
				<?php endif;?>
				
			</aside>
		<?php } ?>
						
		<article class="wp_content <?= $sidebar_class; ?>" id="wp_content_<?php the_ID(); ?>">
			<?php 
				if(!empty($title_display) && $title_display != "off") { ?>
					<h1 class="pg_title"><?= the_title(); ?></h1>
			<?php } ?>		
			
			<?php
				the_content();
			?>
				
		</article>
		
		<!-- If Right Sidebar -->
		<?php if($sidebar_position == "right_sidebar") { ?>
			<aside class="sidebar span_4 last">
				<?php if (is_active_sidebar($sidebar_selection)) : ?>
					<?php dynamic_sidebar($sidebar_selection); ?>
				<?php endif;?>
			</aside>
		<?php } ?>
		
		<!-- Sidebar Bottom -->
		<?php require_once("include/sidebar-bottom.php"); ?>
			
	</section>
	
	</div>
	
<?php endwhile; endif; ?>

<?php

	get_footer();
	
?>