<?php
	/* Template Name: Archives Page */
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
					
					
			<h1 class="pg_title">Article Archives</h1>
			
			<ul class="archive_list">
				<?php
				global $wpdb;
				$limit = 0;
				$year_prev = null;
				$months = $wpdb->get_results("SELECT DISTINCT MONTH( post_date ) AS month ,	YEAR( post_date ) AS year, COUNT( id ) as post_count FROM $wpdb->posts WHERE post_status = 'publish' and post_date <= now( ) and post_type = 'post' GROUP BY month , year ORDER BY post_date DESC");
				foreach($months as $month) :
					$year_current = $month->year;
					if ($year_current != $year_prev){
						if ($year_prev != null){?>
						
						<?php } ?>
					
					<hr />
					<li class="archive-year"><h2><a href="<?php bloginfo('url') ?>/articles/<?php echo $month->year; ?>/"><?php echo $month->year; ?></a></h2></li>
					
					<?php } ?>
					<li class="archive-month"><a href="<?php bloginfo('url') ?>/articles/<?php echo $month->year; ?>/<?php echo date("m", mktime(0, 0, 0, $month->month, 1, $month->year)) ?>"><span class="archive-month"><?php echo date_i18n("F", mktime(0, 0, 0, $month->month, 1, $month->year)) ?></span></a></li>
				<?php $year_prev = $year_current;
				
				if(++$limit >= 18) { break; }
				
				endforeach; ?>
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