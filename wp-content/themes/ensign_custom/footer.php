
<footer class="footer">
	
	<div class="footer_widgets">
		<?php get_sidebar('footer'); ?>
	</div>
	
	
	<div class="copyright">
	
		<div class="inside">
			<p class="copyright_text">
			<?php if (is_active_sidebar('copyright-widget')) : ?>
			<?php dynamic_sidebar('copyright-widget'); ?>
			<?php else: ?>
			Copyright <?= date('Y'); ?>
			<?php endif; ?>
			</p>
		</div>
		
	</div>

</footer>

</div>


<? wp_footer(); ?>

</body>
</html>