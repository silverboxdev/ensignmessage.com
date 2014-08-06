<div class="post_body">

	<div class="f_image">
	
		<?php echo the_post_thumbnail(array(414,414)); ?>

	</div>
	
	<div class="f_copy">
	
		<div class="post_meta">
			<span class="post_date"><?= get_the_date(); ?></span>
		</div>
	
		<h1><a href="<?= the_permalink(); ?>" class="post_title"><?= the_title(); ?></a></h1>
		
		<?php echo the_excerpt(); ?>
	
	</div>
		
</div>