<div class="post_body">
	
	<div class="f_copy">
	
		<h1><a href="<?= the_permalink(); ?>" class="post_title"><?= the_title(); ?></a></h1>
		
		<p class="author_meta">By <? echo the_author_posts_link(); ?></p>
		
		<?php echo the_excerpt(); ?>
	
	</div>
		
</div>