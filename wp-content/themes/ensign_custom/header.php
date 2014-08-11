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
		<link rel="shortcut icon" type="image/x-icon" href="/favicon.ico" />
		
		<?php wp_head(); ?>
		
	</head>
	
	<body>
		
		<div class="body_wrapper">
		
		<?php if(!is_front_page()) { ?>
		
			<!-- Header -->
				
			<header class="masthead">
			
				<div class="inside">
				
					<div class="logo">
						<h1><a href="/"><?= get_bloginfo('name'); ?></a></h1>
					</div>
					
					<div class="tagline">
						<h2><?= get_bloginfo('description'); ?></h2>
					</div>
								
				</div>
				
			</header>
			
			<!-- Main Navigation -->
			<nav class="mainNav">
				<div class="inside">
				<?php 
					wp_nav_menu(array(
						'theme_location' => 'top-menu'
					)); 
				?>	
				</div>

			</nav>
			
			<div class="m_search_contain">
			
				<div class="triggerContainer">
					<a href="#" class="navTrigger mobile_only">Trigger</a>
				</div>
	
				
				<!-- Main Navigation -->
				<nav class="mobileNav">
					<?php 
						wp_nav_menu(array(
							'theme_location' => 'top-menu'
						)); 
					?>	
				</nav>
				
				<div class="searchContain">
					<div class="inside">
					<form action="<?php echo home_url(); ?>" id="search-form" method="get">
						<a href="#" class="searchButton"><img src="<?= IMAGES; ?>/btn-search-big.png" alt="Search" /></a>
						<input type="hidden" value="submit" />
						<input type="text" name="s" id="s" value="Search For..." onblur="if(this.value=='')this.value='Search For...'" onfocus="if(this.value=='Search For...')this.value=''" />
					</form>
					</div>
				</div>
			
			</div>
			
			<? } ?>
			
			
	
