
<footer class="footer">
	
	<?php if(!is_front_page()) { ?>
	
	<div class="footer_widgets">
		<?php get_sidebar('footer'); ?>
	</div>
	
	<nav class="footerNav">
		<div class="inside">
		<?php 
			wp_nav_menu(array(
				'theme_location' => 'footer-menu'
			)); 
		?>	
		</div>
	</nav>
	
	<? } ?>
	
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