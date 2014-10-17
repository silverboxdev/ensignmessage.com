<?php if(!is_front_page()) { ?>
<?php require_once("include/footer-bottom.php"); ?>
<?php } ?>

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
	
	<?php } ?>
	
	<div class="copyright">
	
		<div class="inside">
			<?php if(is_front_page()) {?>
			<p style="float: right;"><a href="<?= get_page_link('292'); ?>" class="footer_privacy">Privacy Policy</a><a href="<?= get_page_link('9'); ?>" class="button blue_button" style="text-align: center; margin: 0 auto;">Enter</a></p>
			<?php } ?>
			
			
			<?php if(is_front_page()) {?>
				<p class="copyright_text front_page_copyright">
			<?php } else { ?>
				<p class="copyright_text">
			<?php } ?>
			
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


<?php wp_footer(); ?>

</body>
</html>