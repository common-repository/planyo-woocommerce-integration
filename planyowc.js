/*
Planyo - WooCommerce Integration javascript
Version: 1.0
Author: Xtreeme GmbH
Author URI: http://www.planyo.com/
*/

function planyowc_show_booking_form(wc_id, planyo_id, cart_url, site_id) {
    document.planyowc_cart_url = cart_url;
    document.planyowc_resource_id = planyo_id;
    document.planyowc_product_id = wc_id;
    planyo_show_plugin_lightbox("https://www.planyo.com/booking.php?mode=resource_list&calendar="+site_id+"&ppp_show_quick_add=" + planyo_id);
}

function planyowc_on_msg(event) {
    if (!event || !event.data || event.data.length < 6 || typeof(event.data) != "string")
        return;

    if (event.data.substr(0, 6) == 'PWCITM') { // add to cart completed
        var data = event.data.substr(6).split('/');
        var reservation_id = data[0];
        var total_price = data[1];
        var cart_url = document.planyowc_cart_url;
        var label = data[2];

        var ajax_data = {
			      'action': 'planyowc_reserve',
			      'resource_id': document.planyowc_resource_id,
            'reservation_id': reservation_id,
            'total_price': total_price,
            'wc_id': document.planyowc_product_id,
            'reservation_label': label
		    };
		    jQuery.post(planyowc_ajax_object.ajax_url, ajax_data, function(response) {
            window.location = document.planyowc_cart_url;
		    });
    }
    else if (event.data.substr(0, 6) == 'PWCCAN') { // add to cart cancelled
        planyo_li_window_close();
    }
}

function on_planyo_form_loaded (event) {
    if(event == 'add_to_cart_done' && document.reserved_resource_id) {
        var ajax_data = {
			      'action': 'planyowc_reserve',
			      'resource_id': document.reserved_resource_id,
            'reservation_id': document.reservation_id,
            'total_price': document.reservation_price,
            'wc_id': null,
            'reservation_label': document.reservation_label
		    };
		    jQuery.post(planyowc_ajax_object.ajax_url, ajax_data);
    }
    else if (event == 'show_cart') {
        window.location = planyowc_ajax_object.cart_url;
    }
}

if (window.addEvent)
    window.addEvent('message', planyowc_on_msg);
else if (window.addEventListener)
    window.addEventListener('message', planyowc_on_msg, false);

