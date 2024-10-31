<?php
/*
Plugin Name: Planyo - WooCommerce Integration
Plugin URI: http://www.planyo.com/woocommerce-reservation-system/
Description: This plugin integration the Planyo.com online reservation system with Woocommerce. Before using it, you'll need to create an account at planyo.com. Please see <a href='http://www.planyo.com/woocommerce-reservation-system'>http://www.planyo.com/woocommerce-reservation-system</a> for more info.
Version: 1.0
Author: Xtreeme GmbH
Author URI: http://www.planyo.com/
*/

/*  Copyright 2018 Xtreeme GmbH  (email : planyo@xtreeme.com)

    This program is free software; you can redistribute it and/or modify
    it under the terms of the GNU General Public License as published by
    the Free Software Foundation; either version 2 of the License, or
    (at your option) any later version.

    This program is distributed in the hope that it will be useful,
    but WITHOUT ANY WARRANTY; without even the implied warranty of
    MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.  See the
    GNU General Public License for more details.

    You should have received a copy of the GNU General Public License
    along with this program; if not, write to the Free Software
    Foundation, Inc., 51 Franklin St, Fifth Floor, Boston, MA  02110-1301  USA
*/

function planyowc_menu() {
  add_options_page('Planyo WooCommerce Integration Options', 'Planyo WooCommerce Integration', 'administrator', 'planyowc', 'planyowc_options');
  add_action('admin_init', 'register_planyowc_settings');
}

function register_planyowc_settings() {
  register_setting('planyowc-settings-group', 'site_id');
  register_setting('planyowc-settings-group', 's_reserve');
  register_setting('planyowc-settings-group', 'login_integration_code');

  $args = array('post_type' => 'product', 'posts_per_page' => -1);
  $loop = new WP_Query($args);

  global $planyowc_products;
  $planyowc_products = array();
	while ($loop->have_posts()) : $loop->the_post();
		$id = get_the_ID();
    $title = get_the_title();
    $planyowc_products [$id] = array('name'=>$title);
    register_setting('planyowc-settings-group', 'mapping_'.$id);
  endwhile;
       
  register_setting('planyowc-settings-group', 'mapping_ids');
  wp_reset_query(); 
}

function planyowc_output_select_option ($value, $text, $option, $selected = false) {
  echo "<option value='$value' ";
  echo (get_option($option)==$value || ($selected && !get_option($option))) ? "selected='selected'" : "";
  echo ">$text</option>";
}

function planyowc_options() {
?>
<div class="wrap">
<h2>Planyo WooCommerce Integration Settings</h2>

<form method="post" action="options.php">
    <?php settings_fields('planyowc-settings-group'); ?>
    <table class="form-table">
        <tr valign="top">
        <th scope="row">Planyo site ID</th>
        <td><input type="text" name="site_id" value="<?php echo get_option('site_id') ? get_option('site_id') : 'demo'; ?>" /><br/>
        <span class='description'>ID of your planyo site. If you do not have a planyo site yet, create one first at www.planyo.com. The default value (demo) will use a demonstration site.</span>
        </td>
        </tr>

<?php
  global $planyowc_products;
  if ($planyowc_products) {
    $mapping_ids = "";
    foreach($planyowc_products as $id=>$product) {
      $mapping_ids .= ($mapping_ids ? "," : "") . $id;
?>
        <tr valign="top">
        <th scope="row">Mapping for '<?php echo $product['name'];?>'</th>
        <td><input type="text" name="mapping_<?php echo $id;?>" value="<?php echo get_option('mapping_'.$id) ? get_option('mapping_'.$id) : ''; ?>" /><br/>
        <span class='description'>Enter the ID of the planyo resource corresponding to this WooCommerce product.</span>
        </td>
        </tr>
<?php
    }
?>
        <input type="hidden" name="mapping_ids" value="<?php echo $mapping_ids;?>" />
<?php
  }
?>

        <tr valign="top">
        <th scope="row">Login integration code</th>
        <td><input type="text" name="login_integration_code" value="<?php echo get_option('login_integration_code'); ?>" /><br/>
        <span class='description'>You'll need to enter the login integration code which you'll find in <a href='https://www.planyo.com/integration-settings.php' target='_blank'>advanced integration settings</a> in the Planyo backend.</span>
        </td>
        </tr>

        <tr valign="top">
        <th scope="row">'Make reservation' button text</th>
        <td><input type="text" name="s_reserve" value="<?php echo get_option('s_reserve') ? get_option('s_reserve') : 'Make reservation'; ?>" /><br/>
        <span class='description'>Choose the text to be displayed on the 'Make reservation' button.</span>
        </td>
        </tr>

    </table>
    
    <p class="submit">
    <input type="submit" class="button-primary" value="<?php _e('Save Changes') ?>" />
    </p>

</form>
</div>
<?php
}

function planyowc_is_mapped_product($id) {
  return get_option('mapping_'.$id);
}

function planyowc_get_product_from_resource($resource_id) {
  $ids_o = get_option('mapping_ids');
  if($ids_o && $resource_id) {
    $ids = explode(",", $ids_o);
    if ($ids) {
      foreach($ids as $id) {
        if(get_option('mapping_'.$id) == $resource_id) {
          return $id;
        }
      }
    }
  }
  return null;
}

/* Replace default Add to cart buttons with Make reservation buttons */

function planyowc_include_lightbox_code() {
  static $code_printed = false;
  if (!$code_printed) {
    $code_printed = true;
    wp_enqueue_script('planyowc-li-script', "https://www.planyo.com/li.js");
    wp_enqueue_style('planyowc-li-style', "https://www.planyo.com/li.css");
  }
  return "";
}

add_filter('woocommerce_loop_add_to_cart_link', 'planyowc_replace_ajax_button', 10, 2);

function planyowc_replace_ajax_button($link, $product) {
  global $woocommerce;
  $cart_url = $woocommerce->cart->get_cart_url();
  if (planyowc_is_mapped_product($product->id)) {
    planyowc_include_lightbox_code();
    return "<a href=\"javascript:planyowc_show_booking_form('".$product->id."','".get_option('mapping_' . $product->id)."','".$cart_url."','".get_option('site_id')."')\" class='button product_type_simple add_to_cart_button'>".get_option("s_reserve")."</a>";
  }
  return $link;
}

function planyowc_replace_single_product_button(){
	global $product; //get the product object
  global $woocommerce;
  $cart_url = $woocommerce->cart->get_cart_url();
  if (isset($product) && planyowc_is_mapped_product($product->id)) {
    planyowc_include_lightbox_code();
    echo "<a href=\"javascript:planyowc_show_booking_form('".$product->id."','".get_option('mapping_' . $product->id)."','".$cart_url."','".get_option('site_id')."')\" class='button product_type_simple add_to_cart_button'>".get_option("s_reserve")."</a>";
  }
  else {
    woocommerce_template_single_add_to_cart();
  }
}
add_action('woocommerce_single_product_summary','planyowc_replace_single_product_button', 40);

function planyowc_enqueue() {
  global $woocommerce;
    wp_enqueue_script('planyowc-ajax-script', plugin_dir_url( __FILE__ ) . "planyowc.js", array('jquery'));
    wp_localize_script('planyowc-ajax-script', 'planyowc_ajax_object',
                       array('ajax_url' => admin_url('admin-ajax.php'),
                             'cart_url' => $woocommerce->cart->get_cart_url()));
}
add_action('wp_enqueue_scripts', 'planyowc_enqueue');

/* Ajax functions */

add_action('wp_ajax_planyowc_reserve', 'planyowc_reserve');
add_action('wp_ajax_nopriv_planyowc_reserve', 'planyowc_reserve');
function planyowc_reserve() {
  global $planyowc_reservation_id;
  global $planyowc_reservation_label;
  global $planyowc_total_price;

  $planyowc_total_price = (float) $_REQUEST['total_price'];
  $planyowc_reservation_id = (int) $_REQUEST['reservation_id'];
  $planyowc_reservation_label = substr(wp_strip_all_tags(esc_html($_REQUEST['reservation_label'])), 0, 100);
  $resource_id = (int) $_REQUEST['resource_id'];
  $product_id = (int) $_REQUEST['wc_id'];
  if (!$product_id && $resource_id) {
    $product_id = planyowc_get_product_from_resource($resource_id);
    if (!$product_id)
      exit("ERROR");
  }

  global $woocommerce;
  if(strpos($planyowc_reservation_id, ",") !== false) {
    $ids = explode(",", $planyowc_reservation_id);
    $prices = explode(",", $planyowc_total_price);
    $labels = explode(",", $planyowc_reservation_label);
    for($i = 0; $i < count($ids) && $i < count($prices); $i++) {
      $planyowc_total_price = $prices[$i];
      $planyowc_reservation_id = $ids[$i];
      $planyowc_reservation_label = $labels[$i];
      $woocommerce->cart->add_to_cart($product_id);
    }
  }
  else {
    $woocommerce->cart->add_to_cart($product_id);
  }

  exit ("OK".$planyowc_total_price);
}

function planyowc_update_cart_items() {
  global $woocommerce;
  foreach ($woocommerce->cart->get_cart() as $key => $value) {
    $product_id = $value['data']->get_id();
    if (planyowc_is_mapped_product($product_id) && isset($value['reservation_id']) && isset($value['reservation_price'])) {
      $planyowc_total_price = $value['reservation_price'];
      $value['data']->set_price($planyowc_total_price);
      $woocommerce->cart->set_quantity($key, 1, false);
    }
  }
}
add_action('woocommerce_before_calculate_totals', 'planyowc_update_cart_items', 99);

function planyowc_item_removed($cart_item_key, $cart) {
  global $woocommerce;
  $cart_items = $woocommerce->cart->get_cart();

  if (isset($cart_items[$cart_item_key]['reservation_id'])) {
    $login_integration_code = get_option('login_integration_code');
    $site_id = get_option('site_id');
    $reservation_id = $cart_items[$cart_item_key]['reservation_id'];
    $url = "https://www.planyo.com/wordpress-reservation-system/planyowc-checkout.php?action=remove_item&calendar=".$site_id."&rental_id=".$reservation_id."&v=".sha1($site_id . $reservation_id . $login_integration_code);
    $retval = wp_remote_fopen($url);
  }
}
add_action('woocommerce_remove_cart_item', 'planyowc_item_removed', 10, 2);

function planyowc_add_item_data($cart_item_data, $product_id) {
  if (!planyowc_is_mapped_product($product_id))
    return $cart_item_data;

  global $planyowc_reservation_id;
  global $planyowc_total_price;
  global $planyowc_reservation_label;
  $option = $planyowc_reservation_id;
  $new_value = array('reservation_id' => $option, 'reservation_price' => $planyowc_total_price, 'reservation_label' => $planyowc_reservation_label);

  if(empty($cart_item_data))
    return $new_value;
  else
    return array_merge($cart_item_data,$new_value);
}
add_filter('woocommerce_add_cart_item_data','planyowc_add_item_data', 1, 2);

function planyowc_add_meta ($itemId, $values, $key) {
  if (isset($values['reservation_id']))
    wc_add_order_item_meta($itemId, 'reservation_id', $values['reservation_id']);
  if (isset($values['reservation_label']))
    wc_add_order_item_meta($itemId, 'reservation_label', $values['reservation_label']);
  if (isset($values['reservation_price']))
    wc_add_order_item_meta($itemId, 'reservation_price', $values['reservation_price']);
}
add_action('woocommerce_add_order_item_meta', 'planyowc_add_meta', 10, 3);

function planyowc_item_data($data, $cartItem) {
  if (isset($cartItem['reservation_id'])) {
    $data[] = array(
                    'name' => 'Reservation ID',
                    'value' => 'R'.$cartItem['reservation_id'],
                    );
  }
  if (isset($cartItem['reservation_label'])) {
    $data[] = array(
                    'name' => 'Details',
                    'value' => $cartItem['reservation_label'],
                    );
  }
  
  return $data;
}
add_filter('woocommerce_get_item_data', 'planyowc_item_data', 10, 2);

function planyowc_before_order_item($item_id, $item, $product){
  if (isset($item['reservation_id']))
    echo " &nbsp;&nbsp; <a target='_blank' href='https://www.planyo.com/rental.php?id=".$item['reservation_id']."'>View in Planyo backend</a>";
}
add_action('woocommerce_before_order_itemmeta', 'planyowc_before_order_item', 10, 3);

/* checkout */

function planyowc_checkout($order_id) {
  $order = wc_get_order($order_id);
  $email = $order->get_billing_email();
  $first = $order->get_billing_first_name();
  $last = $order->get_billing_last_name();
  $address = $order->get_billing_address_1().' '.$order->get_billing_address_2();
  $city = $order->get_billing_city();
  $state = $order->get_billing_state();
  $zip = $order->get_billing_postcode();
  $country = $order->get_billing_country();
  $phone = $order->get_billing_phone();
  $order_number = $order->get_order_number();
  $is_paid = $order->is_paid();
  $needs_processing = $order->needs_processing() || $order->needs_payment();
  $order_url = $order->get_view_order_url();

  $products = $order->get_items();
  $product_ids = "";
  $login_integration_code = get_option('login_integration_code');
  $site_id = get_option('site_id');
  $product_id = null;
  $reservation_id = null;
  foreach($products as $item_id=>$product) {
    $product_id = $product['product_id'];
    if (planyowc_is_mapped_product($product_id)) {
      $reservation_id = $order->get_item_meta($item_id, 'reservation_id', true);
      $product_ids .= $reservation_id.',';
    }
  }
  if ($product_id) {
    $locale = get_locale();
    if ($locale)
      $lang = strtoupper(substr($locale, 0, 2));
    if (!$lang)
      $lang = 'EN';
    $url = "https://www.planyo.com/wordpress-reservation-system/planyowc-checkout.php?action=update_user&calendar=".$site_id."&rental_id=".$reservation_id."&v=".sha1($site_id . $reservation_id . $login_integration_code)."&order=".urlencode($order_number)."&email=".urlencode($email)."&first=".urlencode($first)."&last=".urlencode($last)."&address=".urlencode($address)."&city=".urlencode($city)."&state=".urlencode($state)."&zip=".urlencode($zip)."&country=".urlencode($country)."&mobile_number_param=".urlencode($phone)."&paid=".($is_paid ? '1' : '')."&pending=".($needs_processing ? "1" : '')."&url=".urlencode($order_url)."&client_language=".$lang."&allr=".$product_ids;
    $retval = wp_remote_fopen($url);
  }
}
add_action('woocommerce_checkout_order_processed', 'planyowc_checkout', 1, 1); // woocommerce_new_order or woocommerce_checkout_order_processed

function planyowc_order_completed($order_id, $action = 'complete') {
  $order = wc_get_order($order_id);
  $is_paid = $order->is_paid();
  $products = $order->get_items();
  $product_ids = "";
  $login_integration_code = get_option('login_integration_code');
  $site_id = get_option('site_id');
  $product_id = null;
  $reservation_id = null;
  foreach($products as $item_id=>$product) {
    $product_id = $product['product_id'];
    if (planyowc_is_mapped_product($product_id)) {
      $reservation_id = $order->get_item_meta($item_id, 'reservation_id', true);
      $product_ids .= $reservation_id.',';
    }
  }
  if ($product_id) {
    $url = "https://www.planyo.com/wordpress-reservation-system/planyowc-checkout.php?action=".$action."&calendar=".$site_id."&rental_id=".$reservation_id."&v=".sha1($site_id . $reservation_id . $login_integration_code)."&paid=".($is_paid ? '1' : '')."&allr=".$product_ids;
    $retval = wp_remote_fopen($url);
  }
}
add_action('woocommerce_order_status_completed', 'planyowc_order_completed', 10, 1);

function planyowc_order_cancelled($order_id) {
  planyowc_order_completed($order_id, 'cancel');
}
add_action('woocommerce_order_status_cancelled', 'planyowc_order_cancelled', 10, 1);
add_action('woocommerce_order_status_refunded', 'planyowc_order_cancelled', 10, 1);
add_action('woocommerce_order_status_failed', 'planyowc_order_cancelled', 10, 1);

function planyowc_payment_complete($order_id) {
  planyowc_order_completed($order_id, 'payment');
}
add_action('woocommerce_payment_complete', 'planyowc_payment_complete');

function planyowc_get_price_html($price, $product) {
  if (planyowc_is_mapped_product($product->get_id()))
    $price = "<span class='woocommerce-Price-amount amount'>&nbsp;</span>";
  return $price;
}
add_filter('woocommerce_get_price_html', 'planyowc_get_price_html', 10, 2);

function planyowc_init() {
  add_action('admin_menu', 'planyowc_menu');
  remove_action('woocommerce_single_product_summary', 
                'woocommerce_template_single_add_to_cart', 30); // remove default button
  wp_register_style('planyowc_css', plugin_dir_url(__FILE__) . "planyowc.css");
  wp_enqueue_style('planyowc_css');
}

add_action('init', 'planyowc_init');
