jQuery(function($) {
	wlmstripevals = get_stripe_vars();
	$('.update-payment-info').live('click', function(ev) {
		$(this).parent().find('div.update-stripe-info').show('slow');
		return false;
	});

	$('.update-payment-info-cancel').live('click', function(ev) {
		$(this).parent().parent().parent().hide('slow');
		return false;
	});

	$('.stripe-cancel').live('click', function(ev) {
		return confirm(wlmstripevals.cancelmessage);
	});

	$('.stripe_invoices').live('click', function(ev) {
		ev.preventDefault();
		var  url = $(this).attr('href');
		var data = {
			nonce: wlmstripevals.nonceinvoices,
			stripe_action: 'invoices',
			txn_id: $(this).attr('data-id')
		}
		if(!$('#stripe-invoice-list').is(':visible')) {
			$('.stripe-waiting').show();
			$.post(url, data, function(response) {
				$('#stripe-invoice-list').html(response);
				$('#stripe-invoice-list').show('slow');
				$('.stripe-invoice-detail').leanModal({closeButton: ".stripe-close"});
				$('.stripe-waiting').hide();
			});
		} else {
			$('#stripe-invoice-list').hide('slow');
		}
	});

	$('.stripe-invoice-detail').live('click', function(ev) {
		ev.preventDefault();
		var  url = wlmstripevals.stripethankyouurl;
		var data = {
			nonce: wlmstripevals.nonceinvoicedetail,
			stripe_action: 'invoice',
			txn_id: $(this).attr('data-id')
		}

		$('#stripe-invoice-content').hide();
		$('#stripe-invoice-detail .stripe-waiting').show();
		$.post(url, data, function(response) {
			$('#stripe-invoice-content').html(response);
			$('#stripe-invoice-content').show('slow');
			$('#stripe-invoice-detail .stripe-waiting').hide();
		});
	});

	$('.stripe-invoices-close').live('click', function(ev) {
		ev.preventDefault();
		$('#stripe-invoice-list').hide('slow');
	});

	$('.stripe-invoice-print').live('click', function(ev) {
		ev.preventDefault();
		window.frames["print_frame"].document.body.innerHTML= $('#stripe-invoice-content').html();
		window.frames["print_frame"].window.focus();
		window.frames["print_frame"].window.print();
	});


	var dots = window.setInterval( function() {
		$('.stripe-waiting').each(function(i, e) {
			var el = $(this);
			if(el.html().length > 3) {
				el.html(".");
			} else {
				el.html(el.html() + ".");
			}
		});
	}, 300);
});