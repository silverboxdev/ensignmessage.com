<?php if(!is_front_page()) { ?>
<?php require_once("include/footer-bottom.php"); ?>
<?php } ?>

<footer class="footer">

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

	<!-- Subpage Footers -->

	<?php } else { ?>

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

<!-- Cookie Notice -->

<?php if($_COOKIE['message'] != "viewed") { ?>

<div class="cookie_notice">
	<a href="#" class="close_button"><img src="<?= IMAGES; ?>/close.png" alt="Close Notification Box" /></a>
	<div class="inside">
	<p>We use cookies on this website to enhance your experience of the site and help us understand how the site can be improved. If you continue using this site, we’ll assume you’re happy with this. You can read more about how cookies are used in our <a href="<?= get_page_link('292'); ?>">Privacy Policy</a>.</p>
	</div>
</div>

<?php } ?>


<?php wp_footer(); ?>

</body>
</html>