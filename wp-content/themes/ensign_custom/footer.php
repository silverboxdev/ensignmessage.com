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

	<!-- Front Page Footer Data -->

	<?php if(is_front_page()) { ?>

		<div class="home_footer">

			<div class="f_left span_6">
				<?php dynamic_sidebar('copyright-widget'); ?>
			</div>

			<div class="f_right span_6 omega">
				<div class="fr">
					<nav class="footer_submenu">
						<?php wp_nav_menu(
							array(
							'theme_location' => 'footer-sub-menu',
							)
							);
						?>
					</nav>
					
				</div>
			</div>

		</div>

	<?php } else { ?>
	
	<div class="copyright">
	
		<div class="inside">
			
			<?php if (is_active_sidebar('copyright-widget')) : ?>
			<?php dynamic_sidebar('copyright-widget'); ?>
			<?php else: ?>
			Copyright <?= date('Y'); ?>
			<?php endif; ?>
			</p>

		</div>
		
	</div>

	<?php } ?>

</footer>

</div>


<?php wp_footer(); ?>

</body>
</html>