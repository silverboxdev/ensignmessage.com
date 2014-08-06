<!DOCTYPE html>
<html <?php language_attributes(); ?>>

	<head>
	
		<title><?php wp_title('|', 1, 'right') ?><?php bloginfo('name'); ?></title>
		
		<!-- Meta -->
		<meta http-equiv="Content-Type" content="text/html; charset=utf-8" />
		<meta name="description" content="<?php bloginfo('description'); ?>">
		<meta name="keywords" content="" />
		<meta name="author" content="Silverbox Development">
		<meta name="robots" content="index,follow" />
		
		<!-- Mobile Specific Meta -->
		<meta name="viewport" content="width=device-width, initial-scale=1, maximum-scale=1">
		
		<!-- Styles -->
		<link rel="stylesheet" href="<?php bloginfo('stylesheet_url'); ?>" />
		
		<!-- Typekit -->
		
		<?php wp_head(); ?>
		
	</head>
	
	<body>
		
		<div class="body_wrapper">
		
			<!-- Header -->
				
			<header class="masthead">
				
				<div class="logo">
					<a href="/">Main Logo Goes Here</a>
				</div>
				
				<!-- Main Navigation -->
				<nav class="mainNav">
					<ul>
						<?php 
							wp_nav_menu(array(
								'theme_location' => 'top-menu'
							)); 
						?>	
					</ul>
				</nav>
				
				<a href="#" class="navTrigger mobile_only">Trigger</a>
				
			</header>
			
			<div class="searchContain">
				<div class="inside">
				<form action="<?php echo home_url(); ?>" id="search-form" method="get">
					<a href="#" class="searchButton"><img src="<?= IMAGES; ?>/btn-search-big.png" alt="Search" /></a>
					<input type="hidden" value="submit" />
					<input type="text" name="s" id="s" value="Search For..." onblur="if(this.value=='')this.value='Search For...'" onfocus="if(this.value=='Search For...')this.value=''" />
				</form>
				</div>
			</div>
			
			<!-- Main Navigation -->
			<nav class="mobileNav">
				<ul>
					<?php 
						wp_nav_menu(array(
							'theme_location' => 'top-menu'
						)); 
					?>	
				</ul>
			</nav>
			
			
	
