$(document).ready(function() {

	var theme_path = "/wp-content/themes/ensign_custom";

	$('.mainNav li.menu-item-has-children').hover(function() {
		$(this).toggleClass('hover_nav');
		$('ul', this).stop(true, true).slideToggle('fast');		
	});
	
	// Accordion
	
	var allPanels = $('.accordion > .accord_row > dd').hide();
   
	$('.accordion > .accord_row > dt > a').click(function() {
		$('.accordion > .accord_row > dt > a').removeClass('active_question');
		$(this).addClass('active_question');
		allPanels.slideUp();
		$(this).parent().next().slideDown(600);
		return false;
	 });
	 
	 // On Page Load Open First Accordion
	 
	 $('.accordion > .accord_row > dt > a').first().trigger('click');
	 
	 // Trigger Click on H4
	 
	 $('.accordion > .accord_row > dt > h4').click(function() {
		 $(this).parent().children('a').trigger('click');
		 return false;
	 });
	 
	 // Mobile Nav Trigger
	 
	 $('.navTrigger').click(function(e) {
		 $(this).toggleClass('triggerActive');
		 $('.mobileNav').slideToggle(650);
		 if($('div.searchContain').is(':hidden')) {
			 $('div.searchContain')
			 	.show()
			 	.addClass('search_in_mobile');
		 }
		 else {
			 $('div.searchContain')
			 	.hide();
		 }
		 e.preventDefault();
	 });
	 
	 // Adds Search Button to Header
	 
	 $('.mainNav #menu-main-menu').append('<li><a href="#" class="searchTrigger">Search Me</a></li>');
	 
	 // Search Trigger Functions 
	 
	 $('.searchButton').click(function(e) {
		$('#search-form').submit();
		e.preventDefault();
	});
	
	$('.searchTrigger').click(function(e) {
		$('.searchContain').slideToggle('fast');
		e.preventDefault();
	});

	// Additional Footer Cookie Junk

	setTimeout(function() {
		$('.cookie_notice').fadeOut('fast');
		$.cookie('message', 'viewed', { expires: 7, path: '/' });
	}, 20000);

	$('a.close_button').click(function(e) {
		$('div.cookie_notice').slideUp(600);
		$.cookie('message', 'viewed', { expires: 7, path: '/' });
		e.preventDefault();
	});
	
	 var $window = $(window);
     
    function checkWidth() {
	    
	    var windowsize = $window.width();
        
        // IF window is Wide
        if (windowsize > 915) {        		
				$('.mainNav').show();
				$('.mobileNav').hide();
				//$('div.searchContain').hide();
				
			}
			// IF Window is Narrow
			else {
				$('.mainNav').hide();
			}
			
			if (windowsize < 769) {
				if($('.sidebar-widget h4').parent('a').size() == 0) {
					$('.sidebar-widget h4').wrap('<a href="#" class="recentTrigger"></a>');
				}				
			}
			
			if(windowsize > 769) {
				if($('.sidebar-widget h4').parent('a').length > 0) {
					$('.sidebar-widget h4').unwrap('a');
				}
				
				$('.sidebar-widget ul').show();
			}
			
			if(windowsize < 916) {
				var search = $('div.searchContain');
				
				if($(search).is(':visible')) {
					if($(search).hasClass('search_in_mobile')) {
						// Do Nothing
					}
					else {
						$('div.searchContain').hide();
					}
				}
			}
			
			else {
				$('div.searchContain').removeClass('search_in_mobile');
			}
			
    }
    
    // Execute on load
    checkWidth();
    // Bind event listener
    $(window).resize(checkWidth);
    
    $('body').on('click', '.recentTrigger', function() {
		$(this).next().slideToggle(600);
		return false;
	});
  

});

$(window).load(function() {

});