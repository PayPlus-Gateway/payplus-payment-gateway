<?php
defined('ABSPATH') or die('Hey, You can\'t access this file!'); // Exit if accessed directly

class WC_PayPlus_HostedFields extends WC_PayPlus
{
    private $order_id;
    private $order;
    private $initiated = false;
    protected static $instance = null;
    public $options;
    public $testMode;
    public $url;
    public $apiKey;
    public $secretKey;
    public $paymentPageUid;
    public $apiUrl;


    /**
     *
     */
    public function __construct($order_id = "000", $order = null)
    {
        $this->options = get_option('woocommerce_payplus-payment-gateway_settings');
        $this->testMode = boolval($this->options['api_test_mode'] === 'yes');
        $this->url = $this->testMode ? PAYPLUS_PAYMENT_URL_DEV . 'Transactions/updateMoreInfos' : PAYPLUS_PAYMENT_URL_PRODUCTION . 'Transactions/updateMoreInfos';
        $this->apiKey = $this->testMode ? $this->options['dev_api_key'] : $this->options['api_key'];
        $this->secretKey = $this->testMode ? $this->options['dev_secret_key'] : $this->options['secret_key'];
        $this->paymentPageUid = $this->testMode ? $this->options['dev_payment_page_id'] : $this->options['payment_page_id'];
        $this->order_id = $order_id;
        $this->order = $order;

        define('API_KEY', $this->apiKey);
        define('SECRET_KEY', $this->secretKey);
        define('PAYMENT_PAGE_UID', $this->paymentPageUid);
        define('ORIGIN_DOMAIN', site_url());
        define('SUCCESS_URL', site_url() . '?wc-api=payplus_gateway&hostedFields=true');
        define('FAILURE_URL', site_url() . "/error-payment-payplus/");
        define('CANCEL_URL', 'https://www.example.com/cancel');

        /**
         * PAYPLUS_API_URL_DEV is the URL of the API in the development environment.
         */
        define('PAYPLUS_API_URL_DEV', 'https://restapidev.payplus.co.il/api/v1.0/PaymentPages/generateLink');

        /**
         * PAYPLUS_API_URL_PROD is the URL of the API in the production environment.
         */
        define('PAYPLUS_API_URL_PROD', 'https://restapi.payplus.co.il/api/v1.0/PaymentPages/generateLink');

        $this->apiUrl = $this->testMode ? PAYPLUS_API_URL_DEV : PAYPLUS_API_URL_PROD;

        $available_gateways = WC()->payment_gateways()->get_available_payment_gateways();
        if (WC()->cart->get_subtotal() <= 0 || empty($available_gateways)) {
            WC()->session->__unset('hostedPayload');
            WC()->session->__unset('page_request_uid');
            WC()->session->__unset('hostedResponse');
            WC()->session->__unset('order_awaiting_payment');
            return;
        }

        // $this->create_order_if_not_exists();
        $hostedResponse = $this->hostedFieldsData($this->order_id);

        if (isset($hostedResponse) && $hostedResponse && json_decode($hostedResponse, true)['results']['status'] === "success") {
            $script_version = filemtime(plugin_dir_path(__DIR__) . 'assets/js/hostedFieldsScript.js');
            $template_path = plugin_dir_path(__DIR__) . 'templates/hostedFields.php';

            if (file_exists($template_path)) {
                wp_enqueue_style('hosted-css', PAYPLUS_PLUGIN_URL . 'assets/css/hostedFields.css', [], $script_version);
                include $template_path;
            }
            wp_enqueue_script('payplus-hosted-fields-js', PAYPLUS_PLUGIN_URL . 'assets/js/payplus-hosted-fields/dist/payplus-hosted-fields.min.js', array('jquery'), '1.0', true);
            wp_register_script('payplus-hosted', PAYPLUS_PLUGIN_URL . 'assets/js/hostedFieldsScript.js', array('jquery'), '1.0', true);
            wp_localize_script(
                'payplus-hosted',
                'payplus_script',
                [
                    "hostedResponse" => $hostedResponse,
                    'ajax_url' => admin_url('admin-ajax.php'),
                    'frontNonce' => wp_create_nonce('frontNonce'),
                    'payPlusLogo' => PAYPLUS_PLUGIN_URL . 'assets/images/PayPlusLogo.svg',
                ]
            );
            wp_enqueue_script('payplus-hosted');
        }
    }

    /**
     * @return null
     */
    public static function get_instance()
    {
        if (!isset(static::$instance)) {
            static::$instance = new static;
        }
        return static::$instance;
    }

    public function isInitiated()
    {
        if (!$this->initiated) {
            $this->initiated = true;
            parent::__construct();
        }
    }

    function createUpdateHostedPaymentPageLink($payload)
    {
        $options = get_option('woocommerce_payplus-payment-gateway_settings');
        $testMode = boolval($options['api_test_mode'] === 'yes');
        $apiUrl = $testMode ? 'https://restapidev.payplus.co.il/api/v1.0/PaymentPages/generateLink' : 'https://restapi.payplus.co.il/api/v1.0/PaymentPages/generateLink';
        $apiKey = $testMode ? $options['dev_api_key'] : $options['api_key'];
        $secretKey = $testMode ? $options['dev_secret_key'] : $options['secret_key'];
        $payPlusGateWay = $this->get_main_payplus_gateway();

        $auth = json_encode([
            'api_key' => $apiKey,
            'secret_key' => $secretKey
        ]);
        $requestHeaders = [];
        $requestHeaders[] = 'Content-Type:application/json';
        $requestHeaders[] = 'Authorization: ' . $auth;


        $pageRequestUid = WC()->session->get('page_request_uid');

        if ($pageRequestUid) {
            $apiUrl = str_replace("/generateLink", "/Update/$pageRequestUid", $apiUrl);
        }

        $hostedResponse = $payPlusGateWay->post_payplus_ws($apiUrl, $payload, $method = "post");

        $hostedResponseArray = json_decode(wp_remote_retrieve_body($hostedResponse), true);

        if (isset($hostedResponseArray['data']['page_request_uid'])) {
            $pageRequestUid = $hostedResponseArray['data']['page_request_uid'];
            WC()->session->set('page_request_uid', $pageRequestUid);
        }

        WC()->session->set('hostedResponse', $hostedResponse);

        return wp_remote_retrieve_body($hostedResponse);
    }

    public function getHostedDataFromOrder($order)
    {
        // Initialize the result array
        $order_data = array(
            'items'    => array(),
            'shipping' => array(),
            'coupons'  => array(),
        );

        // Get all items (products) in the order
        foreach ($order->get_items() as $item_id => $item) {
            $product = $item->get_product();
            $order_data['items'][] = array(
                'name'        => $item->get_name(),
                'quantity'    => $item->get_quantity(),
                'total'       => $item->get_total(),
                'subtotal'    => $item->get_subtotal(),
                'tax'         => $item->get_total_tax(),
                'product_id'  => $product ? $product->get_id() : null,
                'sku'         => $product ? $product->get_sku() : null,
            );
        }

        // Get shipping data
        foreach ($order->get_items('shipping') as $item_id => $shipping_item) {
            $order_data['shipping'][] = array(
                'method_title' => $shipping_item->get_name(),
                'cost'         => $shipping_item->get_total(),
                'tax'          => $shipping_item->get_taxes(),
            );
        }

        // Get coupon data using $order->get_coupon_codes()
        $applied_coupons = $order->get_coupon_codes();

        foreach ($applied_coupons as $coupon_code) {
            // Load the WC_Coupon object
            $coupon = new WC_Coupon($coupon_code);

            // Get the discount amount and any other details as needed
            $coupon_discount = $order->get_discount_total(); // Total discount applied
            $coupon_discount_tax = $order->get_discount_tax(); // Total discount tax

            $order_data['coupons'][] = array(
                'code'         => $coupon_code,                // Coupon code
                'discount'     => $coupon_discount,            // Discount amount
                'discount_tax' => $coupon_discount_tax,        // Discount tax
                'coupon_type'  => $coupon->get_discount_type(), // Coupon type (fixed_cart, percent, etc.)
            );
        }

        // Output the final array (for debugging or further use)
        return $order_data;
    }

    public function hostedFieldsData($order_id)
    {
        // $order_id = WC()->session->get('order_awaiting_payment');
        if ($order_id !== "000") {
            $order = wc_get_order($order_id);

            if (! $order) {
                return;
            }
        }



        $WC_PayPlus_Gateway = $this->get_main_payplus_gateway();
        $discountPrice = 0;
        $products = array();
        $merchantCountryCode = substr(get_option('woocommerce_default_country'), 0, 2);
        WC()->customer->set_shipping_country($merchantCountryCode);
        WC()->cart->calculate_totals();
        $wc_tax_enabled = wc_tax_enabled();

        $cart = WC()->cart->get_cart();
        if (count($cart)) {
            foreach ($cart as $cart_item_key => $cart_item) {
                $productId = $cart_item['product_id'];

                if (!empty($cart_item['variation_id'])) {
                    $product = new WC_Product_Variable($productId);
                    $productData = $product->get_available_variation($cart_item['variation_id']);
                    $tax = (WC()->cart->get_total_tax()) ? WC()->cart->get_total_tax() / $cart_item['quantity'] : 0;
                    $tax = round($tax, $WC_PayPlus_Gateway->rounding_decimals);
                    $priceProductWithTax = round($productData['display_price'] + $tax, ROUNDING_DECIMALS);
                    $priceProductWithoutTax = round($productData['display_price'], ROUNDING_DECIMALS);
                } else {
                    $product = new WC_Product($productId);
                    $priceProductWithTax = round(wc_get_price_including_tax($product), ROUNDING_DECIMALS);
                    $priceProductWithoutTax = round(wc_get_price_excluding_tax($product), ROUNDING_DECIMALS);
                }
                $productVat = $product->get_tax_status() === 'taxable' && !$wc_tax_enabled ? 0 : 2;
                $productVat = 0 && $wc_tax_enabled ? 1 : $productVat;
                $products[] = array(
                    'title' => $product->get_title(),
                    'priceProductWithTax' => $priceProductWithTax,
                    'priceProductWithoutTax' => $priceProductWithoutTax,
                    'quantity' => $cart_item['quantity'],
                    'vat_type' => !$wc_tax_enabled ? 0 : 1,
                    'org_product_tax' => $product->get_tax_status(),
                );
            }

            if (WC()->cart->get_total_discount()) {
                $discountPrice = round(floatval(WC()->cart->get_discount_total()), ROUNDING_DECIMALS);
            }
        }

        // $totalAll = WC()->cart->get_totals();
        // $subTotalAll = WC()->cart->get_subtotal();
        // $taxGlobal = round(WC()->cart->get_total_tax() - WC()->cart->get_shipping_tax(), ROUNDING_DECIMALS);
        // $error = $totalAll['total'] == 0;

        // echo wp_json_encode(array("error" => $error, "total" => $totalAll['total'], "products" => $products, "total_without_tax" => $subTotalAll, 'discountPrice' => $discountPrice ? $discountPrice : 0, "taxGlobal" => $taxGlobal));


        // this will be the create initial order data function that calls the curl to create at it's end.
        $checkout = WC()->checkout();

        // Get posted checkout data
        $billing_first_name = $checkout->get_value('billing_first_name');
        $billing_last_name  = $checkout->get_value('billing_last_name');
        $billing_email      = $checkout->get_value('billing_email');
        $shipping_address   = $checkout->get_value('shipping_address_1');
        $phone   = $checkout->get_value('billing_phone');


        // Building sample request to create a payment page
        $data = new stdClass();
        $data->payment_page_uid = PAYMENT_PAGE_UID;
        $data->refURL_success = SUCCESS_URL;
        $data->refURL_failure = FAILURE_URL;
        $data->refURL_cancel = CANCEL_URL;
        $data->create_token = true;
        $data->currency_code = get_woocommerce_currency();
        $data->charge_method = intval($WC_PayPlus_Gateway->settings['transaction_type']);

        /**
         * Origin domain is the domain of the page that is requesting the payment page.
         * This is necessary for the hosted fields to be able to communicate with the client website.
         */
        $data->refURL_origin = ORIGIN_DOMAIN;
        /**
         * Also notice that we set hosted_fields to true.
         */
        $data->hosted_fields = true;

        $data->customer = new stdClass();
        $data->customer->customer_name = "$billing_first_name $billing_last_name";
        $data->customer->email = $billing_email;
        $data->customer->phone = $phone;

        foreach ($products as $product) {
            $item = new stdClass();
            $item->name = $product['title'];
            $item->quantity = $product['quantity'];
            $item->price = $product['priceProductWithTax'];
            $item->vat_type = $product['vat_type'];
            $data->items[] = $item;
        }

        if ($order_id !== "000") {
            $data->more_info = $order_id;
            WC()->session->set('order_awaiting_payment', $order_id);
            $shipping_items = $order->get_items('shipping');
            // Check if there are shipping items
            if (! empty($shipping_items)) {
                foreach ($shipping_items as $shipping_item) {
                    // Get the shipping method ID (e.g., 'flat_rate:1')
                    $method_id = $shipping_item->get_method_id();

                    // Get the shipping method title (e.g., 'Flat Rate')
                    $method_title = $shipping_item->get_method_title();
                    $shipping_cost = $shipping_item->get_total();

                    $item = new stdClass();
                    $item->name = $method_title;
                    $item->quantity = 1;
                    $item->price = $shipping_cost;
                    $item->vat_type = !$wc_tax_enabled ? 0 : 1;
                    $data->items[] = $item;
                }
            }

            $coupons = $order->get_coupon_codes();
            $totalFromOrder = $order->get_total();

            if (! empty($coupons)) {
                foreach ($coupons as $coupon_code) {
                    // Get the WC_Coupon object
                    $coupon = new WC_Coupon($coupon_code);

                    // Get the coupon discount amount
                    $coupon_value = $coupon->get_amount();
                }
                if ($coupon_value > 0) {
                    $item = new stdClass();
                    $item->name = "coupon_discount";
                    $item->quantity = 1;
                    $item->price = -$coupon_value;
                    $item->vat_type = !$wc_tax_enabled ? 0 : 1;
                    $data->items[] = $item;
                }
            }
        }

        $totalAmount = 0;
        foreach ($data->items as $item) {
            $totalAmount += $item->price * $item->quantity;
        }

        $data->amount = number_format($totalAmount, 2, '.', '');

        // $linkRedirect = html_entity_decode(esc_url($this->payplus_gateway->get_return_url($order)));

        $payload = wp_json_encode($data);
        WC()->session->set('hostedPayload', $payload);

        $hostedResponse = $this->createUpdateHostedPaymentPageLink($payload);
        $hostedResponseArray = json_decode($hostedResponse, true);

        if ($hostedResponseArray['results']['status'] === "error") {
            WC()->session->__unset('page_request_uid');
            $hostedResponse = $this->createUpdateHostedPaymentPageLink($payload);
        }

        return $hostedResponse;
    }

    public function create_order_if_not_exists()
    {
        if (! is_checkout()) {
            return; // Only run on the checkout page
        }

        // Check if an order already exists in the session
        if (WC()->session->get('order_awaiting_payment')) {
            return; // Order already exists, no need to create another
        }

        // Create a new order using the WC_Checkout object
        $checkout = WC()->checkout();

        // Get posted billing and shipping data from the checkout form
        $billing_first_name  = $checkout->get_value('billing_first_name');
        $billing_last_name   = $checkout->get_value('billing_last_name');
        $billing_email       = $checkout->get_value('billing_email');
        $billing_phone       = $checkout->get_value('billing_phone');
        $billing_address_1   = $checkout->get_value('billing_address_1');
        $billing_city        = $checkout->get_value('billing_city');
        $billing_postcode    = $checkout->get_value('billing_postcode');
        $billing_country     = $checkout->get_value('billing_country');

        // Shipping data
        $shipping_first_name = $checkout->get_value('shipping_first_name');
        $shipping_last_name  = $checkout->get_value('shipping_last_name');
        $shipping_address_1  = $checkout->get_value('shipping_address_1');
        $shipping_city       = $checkout->get_value('shipping_city');
        $shipping_postcode   = $checkout->get_value('shipping_postcode');
        $shipping_country    = $checkout->get_value('shipping_country');

        // Get available payment gateways
        $available_gateways = WC()->payment_gateways()->get_available_payment_gateways();
        $chosen_payment_method = key($available_gateways); // Select the first available gateway, or set your own logic

        // Populate checkout data with necessary fields
        $data = array(
            // Billing data
            'billing_first_name' => $billing_first_name,
            'billing_last_name'  => $billing_last_name,
            'billing_email'      => $billing_email,
            'billing_phone'      => $billing_phone,
            'billing_address_1'  => $billing_address_1,
            'billing_city'       => $billing_city,
            'billing_postcode'   => $billing_postcode,
            'billing_country'    => $billing_country,

            // Shipping data
            'shipping_first_name' => $shipping_first_name,
            'shipping_last_name'  => $shipping_last_name,
            'shipping_address_1'  => $shipping_address_1,
            'shipping_city'       => $shipping_city,
            'shipping_postcode'   => $shipping_postcode,
            'shipping_country'    => $shipping_country,

            // Payment method
            'payment_method' => $chosen_payment_method, // Set the payment method
        );

        try {
            // Create a new order using the checkout data
            $order_id = $checkout->create_order($data);

            // Set the order awaiting payment in the session
            WC()->session->set('order_awaiting_payment', $order_id);

            // You can manipulate or save additional order data here
            return $order_id; // Returns the newly created order ID
        } catch (Exception $e) {
            wc_add_notice(__('Error creating order: ') . $e->getMessage(), 'error');
        }
    }
}

// WC_PayPlus_HostedFields::get_instance();