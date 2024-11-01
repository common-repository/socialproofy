<?php

/**
 * @package: socialproofy
 */

/**
 * Plugin Name: Social Proofy
 * Description: Social Proofy is a social proof marketing platform that works with your Wordpress and WooCommerce websites out of the box
 * Version: 1.0.7
 * Author: Social Proofy
 * Author URI: https://www.socialproofy.io/
 * License: GPLv3 or later
 * Text Domain: socialproofy
 */

add_action('admin_menu', 'socialproofy_admin_menu');
add_action('admin_init', 'socialproofy_admin_init');
add_action('admin_notices', 'socialproofy_admin_notice_html');
add_action('wp_head', 'socialproofy_inject_code');

add_action('add_option_socialproofy_api_key', 'socialproofy_api_key_updated', 990, 0);
add_action('update_option_socialproofy_api_key', 'socialproofy_api_key_updated', 990, 0);
add_action('add_option_socialproofy_api_key', 'socialproofy_cronjob_init', 999, 0);
add_action('update_option_socialproofy_api_key', 'socialproofy_cronjob_init', 999, 0);

add_action('user_register', 'socialproofy_new_user');
add_action('profile_update', 'socialproofy_new_user');

add_action('woocommerce_checkout_order_processed', 'socialproofy_new_order', 999, 3);
add_action('woocommerce_update_product', 'socialproofy_update_product', 10, 1);
add_action('woocommerce_add_to_cart', 'socialproofy_add_to_cart', 9999, 1);
add_action('woocommerce_update_cart_action_cart_updated', 'socialproofy_cart_updated', 9999, 1);
add_action('woocommerce_cart_item_removed', 'socialproofy_cart_updated', 9999, 2);

function socialproofy_cronjob_init()
{
    $api_connected = socialproofy_check_api_key();
    if ($api_connected['status'] == true) {
        if (socialproofy_check_woocommerce_plugin() === true) {
            socialproofy_send_products();
            socialproofy_send_latest_orders();
            socialproofy_send_customers();
            update_option('socialproofy_product_offset', 0);
        }
    }
}

function socialproofy_admin_menu()
{
    add_menu_page(
        'Social Proofy Settings',
        'Social Proofy',
        'manage_options',
        'socialproofy',
        'socialproofy_admin_menu_page_html',
        plugin_dir_url(__FILE__) . 'assets/icon.png'
    );
}

function socialproofy_admin_init()
{
    register_setting('socialproofy_options', 'socialproofy_site_key');
    register_setting('socialproofy_options', 'socialproofy_api_key');
    register_setting('socialproofy_options', 'socialproofy_connected');
}

function socialproofy_admin_notice_html()
{
    socialproofy_api_key_updated();

    $api_connected = socialproofy_check_api_key();
    if ($api_connected['status'] == true) {
        return;
    }
    ?>
    <div class="notice notice-error is-dismissible">
        <p class="ps-error"><?php echo $api_connected['msg']; ?></p>
    </div>
    <?php
}

function socialproofy_admin_menu_page_html()
{
    if (!current_user_can('manage_options')) {
        return;
    }

    ?>
    <div class="wrap">
        <h2>Connect Social Proofy to your website</h2>
        <hr/>
        <form name="dofollow" action="options.php" method="post">
            <?php settings_errors(); ?>
            <?php settings_fields('socialproofy_options'); ?>
            <?php do_settings_sections('socialproofy_options'); ?>
            <table class="form-table">
                <tr>
                    <th>
                        <label for="socialproofy_site_key">Site Key</label>
                    </th>
                    <td>
                        <input id="socialproofy_site_key" class="regular-text code" name="socialproofy_site_key"
                               type="text" value="<?php echo esc_html(get_option('socialproofy_site_key')); ?>"/>
                        <p class="description">
                            Go to your the <a
                                    href="https://www.socialproofy.io/knowledgebase/how-to-install-social-proofy-with-woocommerce/"
                                    target="_blank" rel="noopener">Wordpress Integration page</a> for your current site
                            to find your Site Key.
                        </p>
                    </td>
                </tr>
                <tr>
                    <th>
                        <label for="socialproofy_api_key">API Key</label>
                    </th>
                    <td>
                        <input id="socialproofy_api_key" class="regular-text code" name="socialproofy_api_key"
                               type="text" value="<?php echo esc_html(get_option('socialproofy_api_key')); ?>"/>
                        <p class="description">
                            Go to your the <a
                                    href="https://www.socialproofy.io/knowledgebase/how-to-install-social-proofy-with-woocommerce/"
                                    target="_blank" rel="noopener">Wordpress Integration page</a> for your current site
                            to find your Api Key.
                        </p>
                    </td>
                </tr>
            </table>
            <?php submit_button('Save'); ?>
        </form>
    </div>
    <?php
}

function socialproofy_inject_code()
{
    $api_connected = socialproofy_check_api_key();
    if ($api_connected['status'] == false) {
        return;
    }

    $site_key = get_option('socialproofy_site_key');
    $pixel_url = "https://app.socialproofy.io/pixel/" . $site_key;
    if (class_exists('WooCommerce')) {
        $session_id = WC()->session->get_customer_id();
    } else {
        $session_id = wp_get_session_token();
    }
    ?>
    <script async src="<?php echo esc_html($pixel_url . '?SP_SSID=' . $session_id); ?>"></script>
    <?php
}

function socialproofy_check_api_key()
{
    $site_key = get_option('socialproofy_site_key');
    $api_key = get_option('socialproofy_api_key');
    if (empty($site_key) || empty($api_key)) {
        return [
            'status' => false,
            'msg' => '<p>You need to complete your Social Proofy set-up <a href="admin.php?page=socialproofy">Complete set-up</a></p>'
        ];
    } else {
        $socialproofy_connected = get_option('socialproofy_connected');
        if ($socialproofy_connected != 1) {
            return [
                'status' => false,
                'msg' => '<p>Social Proofy is not connected! Check your credentials.</p>'
            ];
        } else {
            return [
                'status' => true,
            ];
        }
    }
}

function socialproofy_check_woocommerce_plugin()
{
    if (class_exists('WooCommerce')) {
        return true;
    } else {
        return false;
    }
}

function socialproofy_api_key_updated()
{
    $site_key = get_option('socialproofy_site_key');
    $api_key = get_option('socialproofy_api_key');

    if (empty($site_key) || empty($api_key)) {
        update_option('socialproofy_connected', 0);
        return false;
    }

    $site_url = get_home_url();
    $post_data = array(
        'domain' => $site_url,
        'site_key' => $site_key,
        'api_key' => $api_key,
        'type' => 'woocommerce',
    );
    $wpCurlArgs = array(
        'method' => 'POST',
        'body' => $post_data,
    );
    $response = wp_remote_post('https://app.socialproofy.io/integrate/woocommerce/check', $wpCurlArgs);
    $response_data = json_decode(wp_remote_retrieve_body($response), TRUE);
    if ($response_data['status'] == "success") {
        update_option('socialproofy_connected', 1);
        return true;
    } else {
        update_option('socialproofy_connected', 0);
        return false;
    }
}

function socialproofy_prepare_product_array($product_id)
{
    $products = array();

    $product = wc_get_product($product_id);
    $image = null;
    $permalink = null;

    if ($product) {
        $permalink = $product->get_permalink();
        $images_arr = wp_get_attachment_image_src($product->get_image_id(), array('72', '72'), false);
        $price = $product->get_price();
    }

    if ($images_arr !== null && $images_arr[0] !== null) {
        $image = $images_arr[0];
        if (is_ssl()) {
            $image = str_replace('http://', 'https://', $image);
        }
    }

    if ($product->is_type('variable')) {
        $variations = $product->get_available_variations();
        foreach ($variations as $variation) {
            $variation_id = $variation['variation_id'];
            $variation_obj = new WC_Product_variation($variation_id);
            $variation_name = $variation_obj->get_formatted_name();
            $price = $variation_obj->get_regular_price();
            $stock = $variation_obj->get_stock_quantity();
            $products[] = array(
                'id' => $product_id,
                'variation_id' => $variation_id,
                'stock' => $stock,
                'is_in_stock' => $variation['is_in_stock'],
                'name' => $variation_name,
                'image' => $image,
                'permalink' => $permalink,
                'price' => $price,
            );
        }
    } else {
        $stock = $product->get_stock_quantity();
        $is_in_stock = ($product->get_stock_status() == "instock" ? 1 : 0);
        $products[] = array(
            'id' => $product_id,
            'stock' => $stock,
            'is_in_stock' => $is_in_stock,
            'name' => $product->get_name(),
            'image' => $image,
            'permalink' => $permalink,
            'price' => $price,
        );
    }

    return $products;
}

function socialproofy_update_product($product_id)
{
    if (socialproofy_check_woocommerce_plugin() === true) {
        $updating_product_id = 'update_product_' . $product_id;
        if (false === ($updating_product = get_transient($updating_product_id))) {

            $products = socialproofy_prepare_product_array($product_id);

            $post_data = array(
                'type' => 'product_updated',
                'products' => $products
            );

            socialproofy_post($post_data);

            set_transient($updating_product_id, $product_id, 2);
        }
    }
}

function socialproofy_cart_updated()
{
    if (socialproofy_check_woocommerce_plugin() === true) {
        WC()->cart->calculate_totals();
        $cart_total = floatval(WC()->cart->get_subtotal());
        $session_id = WC()->session->get_customer_id();

        $post_data = array(
            'type' => 'basket',
            'cart_total' => $cart_total,
            'session_id' => $session_id,
        );
        socialproofy_post($post_data);
    }
}

function socialproofy_add_to_cart()
{
    if (socialproofy_check_woocommerce_plugin() === true) {
        $cart_total = floatval(WC()->cart->get_subtotal());
        $session_id = WC()->session->get_customer_id();

        $post_data = array(
            'type' => 'basket',
            'cart_total' => $cart_total,
            'session_id' => $session_id,
        );
        socialproofy_post($post_data);
    }
}

function socialproofy_generate_order_data($order_id)
{
    $order = new WC_Order($order_id);
    $order_data = $order->get_data();

    $currency = $order_data['currency'];
    $date = $order_data['date_created']->date('Y-m-d H:i:s');
    $billing_country = $order_data['billing']['country'];
    $billing_postcode = $order_data['billing']['postcode'];
    $billing_city = $order_data['billing']['city'];
    $billing_address1 = $order_data['billing']['address_1'];
    $billing_address2 = $order_data['billing']['address_2'];
    $billing_firstname = $order_data['billing']['first_name'];
    $billing_lastname = $order_data['billing']['last_name'];
    $billing_email = $order_data['billing']['email'];
    $billing_phone = $order_data['billing']['phone'];
    $site_url = get_home_url();
    $total = $order_data['total'];
    $shipping_total = $order_data['shipping_total'];
    $shipping_tax = $order_data['shipping_tax'];
    $discount_tax = $order_data['discount_tax'];
    $discount_total = $order_data['discount_total'];

    $post_data = array(
        "date" => $date,
        "order_id" => $order_id,
        "billing" => array(
            "first_name" => $billing_firstname,
            "last_name" => $billing_lastname,
            "address_1" => $billing_address1,
            "address_2" => $billing_address2,
            "city" => $billing_city,
            "postcode" => $billing_postcode,
            "country" => $billing_country,
            "email" => $billing_email,
            "phone" => $billing_phone,
        ),
        "site_url" => $site_url,
        "product" => array(),
        "currency" => $currency,
        "discount_total" => $discount_total,
        "discount_tax" => $discount_tax,
        "shipping_total" => $shipping_total,
        "shipping_tax" => $shipping_tax,
        "total" => $total
    );

    foreach ($order->get_items() as $item) {
        $product = wc_get_product($item->get_product_id());
        $product_id = $item->get_product_id();
        $item_name = $item->get_name();
        $permalink = null;
        $image = null;

        if ($product) {
            $permalink = $product->get_permalink();
            $images_arr = wp_get_attachment_image_src($product->get_image_id(), array('72', '72'), false);
        }

        if ($images_arr !== null && $images_arr[0] !== null) {
            $image = $images_arr[0];
            if (is_ssl()) {
                $image = str_replace('http://', 'https://', $image);
            }
        }

        $post_data['products'][] = array(
            "product_id" => $product_id,
            "product_name" => $item_name,
            "prodcut_image" => $image,
            "product_permalink" => $permalink,
        );
    }

    return $post_data;
}

function socialproofy_generate_customer_data($user_id)
{
    $customer_data = array_map(function ($a) {
        return $a[0];
    }, get_user_meta($user_id));

    if (!empty($customer_data)) {
        $customer = array(
            'customer_id' => $user_id,
            'nickname' => $customer_data['nickname'],
            'first_name' => ucfirst($customer_data['first_name']),
            'country' => $customer_data['billing_country'],
            'city' => $customer_data['billing_city'],
        );

        return $customer;
    }

    return null;
}

function socialproofy_new_order($order_id)
{
    if (socialproofy_check_woocommerce_plugin() === true) {
        $post_data = socialproofy_generate_order_data($order_id);
        $post_data['type'] = 'new_order';
        socialproofy_post($post_data);
    }
}

function socialproofy_new_user($user_id)
{
    if (socialproofy_check_woocommerce_plugin() === true) {
        $post_data = socialproofy_generate_customer_data($user_id);
        $post_data['type'] = 'new_customer';

        socialproofy_post($post_data);
    }
}

function socialproofy_send_products()
{
    $products = array();

    $product_count = 100;

    $offset = get_option('socialproofy_product_offset');
    if (empty($offset)) {
        $offset = 0;
        update_option('socialproofy_product_offset', $offset);
    }

    $products_query = get_posts(array(
        'post_type' => 'product',
        'numberposts' => $product_count,
        'offset' => $offset
    ));

    foreach ($products_query as $item) {
        $product_variation_lists = socialproofy_prepare_product_array($item->ID);
        foreach ($product_variation_lists as $element) {
            $products[] = $element;
        }
    }

    if (!empty($products)) {
        $post_data = array(
            'type' => 'all_products',
            'products' => $products
        );
        socialproofy_post($post_data);
        update_option('socialproofy_product_offset', ($offset + $product_count));
    }
}

function socialproofy_send_latest_orders()
{
    $orders = array();

    $latest_orders = get_posts(array(
        'numberposts' => 30,
        'post_type' => wc_get_order_types(),
        'post_status' => array_keys(wc_get_order_statuses()),
    ));

    foreach ($latest_orders as $order) {
        $orders[] = socialproofy_generate_order_data($order->ID);
    }

    socialproofy_post(array(
        'type' => 'latest_orders',
        'orders' => $orders,
    ));
}

function socialproofy_send_customers()
{
    $customers = array();
    $users_query = get_users();
    foreach ($users_query as $key => $item) {
        if ($key > 30)
            break;
        $customer = socialproofy_generate_customer_data($item->ID);
        $customers[] = $customer;
    }

    if (!empty($customers)) {
        $post_data = array(
            'type' => 'all_customers',
            'customers' => $customers
        );
        socialproofy_post($post_data);
    }
}

function socialproofy_post($post_data)
{
    $wpCurlArgs = array(
        'method' => 'POST',
        'headers' => array(
            'Content-Type' => 'application/json',
            'X-Site-Key' => get_option('socialproofy_site_key'),
            'X-Api-Key' => get_option('socialproofy_api_key'),
            'X-Type' => 'woocommerce'
        ),
        'body' => json_encode($post_data),
    );

    $response = wp_remote_post('https://app.socialproofy.io/integrate/woocommerce/data', $wpCurlArgs);
    if (is_wp_error($response)) {
        return $response->get_error_message();
    } else {
        $response_data = json_decode(wp_remote_retrieve_body($response), TRUE);
        return $response_data;
    }
}