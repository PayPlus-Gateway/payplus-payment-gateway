<?php
defined('ABSPATH') || exit; // Exit if accessed directly

abstract class WC_PayPlus_Subgateway extends WC_PayPlus_Gateway
{
    public $id;
    public $payplus_default_charge_method;
    public $iconURL;
    public $method_title_text;
    public $method_descrition_text;
    public $pay_with_text;
    public $default_description_settings_text;
    public $hide_other_charge_methods;
    public $allPayment;
    public $allTypePayment;

    /**
     *
     */
    public function __construct()
    {

        parent::__construct();
        if ($this->hide_icon == "no") {
            $this->icon = PAYPLUS_PLUGIN_URL . $this->iconURL;
        }

        $this->allPayment = array(
            __('Pay with bit via PayPlus', 'payplus-payment-gateway'),
            __('Pay with Google Pay via PayPlus', 'payplus-payment-gateway'),
            __('Pay with Apple Pay via PayPlus', 'payplus-payment-gateway'),
            __('Pay With MULTIPASS via PayPlus', 'payplus-payment-gateway'),
            __('Pay with PayPal via PayPlus', 'payplus-payment-gateway'),
            __('Pay with Tav Zahav via PayPlus', 'payplus-payment-gateway'),
            __('Pay with Valuecard via PayPlus', 'payplus-payment-gateway'),
            __('Pay with finitiOne via PayPlus', 'payplus-payment-gateway')

        );
        $this->allTypePayment = array(
            __('bit', 'payplus-payment-gateway'),
            __('Google Pay', 'payplus-payment-gateway'),
            __('Apple Pay', 'payplus-payment-gateway'),
            __('MULTIPASS', 'payplus-payment-gateway'),
            __('PayPal', 'payplus-payment-gateway'),
            __('Tav Zahav', 'payplus-payment-gateway'),
            __('Valuecard', 'payplus-payment-gateway'),
            __('finitiOne', 'payplus-payment-gateway')
        );
        $this->method_title = __($this->method_title_text, 'payplus-payment-gateway');
        $this->description = $this->get_option('description');

        $this->default_charge_method = $this->payplus_default_charge_method;
        add_action('woocommerce_receipt_' . $this->id, [$this, 'receipt_page']);
        if ($this->settings['enabled'] === null) {
            $this->enabled = 'no';
        }

        if ($this->hide_other_charge_methods === "1") {
            $this->settings['sub_hide_other_charge_methods'] = "1";
        } else {
            $this->settings['sub_hide_other_charge_methods'] = "2";
        }
    }

    /**
     * @return void
     */
    public function init_form_fields()
    {

        $this->form_fields = [
            'enabled' => [
                'title' => __('Enable/Disable', 'payplus-payment-gateway'),
                'type' => 'checkbox',
                'label' => __('Enable PayPlus+ Payment', 'payplus-payment-gateway'),
                'default' => 'yes'
            ],
            'title' => [
                'title' => __('Title', 'payplus-payment-gateway'),
                'type' => 'text',
                'description' => __('This controls the title which the user sees during checkout', 'payplus-payment-gateway'),
                'default' => __($this->pay_with_text, 'payplus-payment-gateway'),
                'desc_tip' => true,
            ],
            'description' => [
                'title' => __('Description', 'payplus-payment-gateway'),
                'type' => 'text',
                'default' => __($this->default_description_settings_text, 'payplus-payment-gateway')
            ],
            'display_mode' => [
                'title' => __('Display Mode', 'payplus-payment-gateway'),
                'type' => 'select',
                'options' => [
                    'default' => __('Use global default', 'payplus-payment-gateway'),
                    'redirect' => __('Redirect', 'payplus-payment-gateway'),
                    'iframe' => __('iFrame', 'payplus-payment-gateway'),
                    'samePageIframe' => __('iFrame on the same page', 'payplus-payment-gateway'),
                    'popupIframe' => __('iFrame in a Popup', 'payplus-payment-gateway'),
                ],
                'default' => 'redirect',
            ],
            'iframe_height' => [
                'title' => __('iFrame Height', 'payplus-payment-gateway'),
                'type' => 'number',
                'default' => 700,
            ],
            'hide_icon' => [
                'title' => __('Hide Payment Method Icon In The Checkout Page', 'payplus-payment-gateway'),
                'type' => 'checkbox',
                'label' => __('Hide Payment Method Icon In The Checkout Page', 'payplus-payment-gateway'),
                'default' => 'no'
            ],
            'sub_hide_other_charge_methods' => [
                'title' => __('Hide other payment types on payment page', 'payplus-payment-gateway'),
                'type' => 'select',
                'options' => [
                    '0' => __('No', 'payplus-payment-gateway'),
                    '1' => __('Yes', 'payplus-payment-gateway'),
                    '2' => __('Use global default', 'payplus-payment-gateway'),
                ],
                'default' => '1',
            ],
        ];
    }

    /**
     * @return void
     */
    public function admin_options()
    {
        parent::admin_options();
        echo __("Before enabling this option, please ensure you have proper PayPlus credentials and authorization", 'payplus-payment-gateway');
    }

    /**
     * @return void
     */
    public function init_settings()
    {
        $defaultOptions = [
            'enabled' => 'no',
            'title' => '',
            'description' => '',
            'display_mode' => 'default',
            'iframe_height' => 700,
            'hide_icon' => 'no',
            'hide_other_charge_methods' => '1',
        ];

        $subOptionsettings = get_option($this->get_option_key(), $defaultOptions);
        $this->settings = get_option('woocommerce_payplus-payment-gateway_settings', $defaultOptions);

        $this->enabled = $this->settings['enabled'] = $subOptionsettings['enabled'];
        $this->settings['description'] = $subOptionsettings['description'];
        $this->settings['title'] = (!empty($subOptionsettings['title'])) ? $subOptionsettings['title'] : __($this->method_descrition_text, 'payplus-payment-gateway');
        $this->settings['display_mode'] = $subOptionsettings['display_mode'];
        $this->settings['hide_icon'] = $subOptionsettings['hide_icon'];
        $this->settings['iframe_height'] = $subOptionsettings['iframe_height'];
        $this->settings['default_charge_method'] = $this->payplus_default_charge_method;
        $this->settings['sub_hide_other_charge_methods'] = isset($subOptionsettings['sub_hide_other_charge_methods']) ? $subOptionsettings['sub_hide_other_charge_methods'] : null;

        if ($this->settings['sub_hide_other_charge_methods'] != 2 && $this->settings['sub_hide_other_charge_methods'] !== null) {
            $this->settings['hide_other_charge_methods'] = $this->settings['sub_hide_other_charge_methods'];
        }
    }

    /**
     * @return void
     */
    public function payment_fields()
    {
        $description = $this->get_description();
        if ($description) {
            echo wpautop(wptexturize($description)); // @codingStandardsIgnoreLine.
        }

        if ($this->supports('default_credit_card_form')) {
            $this->credit_card_form(); // Deprecated, will be removed in a future version.
        }
    }

    /**
     * @return void
     */
    public function save_payment_method_checkbox()
    {
    }

    /**
     * @return void
     */
    public function msg_checkout_code()
    {
    }
}

class WC_PayPlus_Gateway_Bit extends WC_PayPlus_Subgateway
{
    public $id = 'payplus-payment-gateway-bit';
    public $method_title_text = 'PayPlus - bit';
    public $default_description_settings_text = 'Bit payment via PayPlus';
    public $method_descrition_text = 'Pay with bit via PayPlus';
    public $payplus_default_charge_method = 'bit';
    public $iconURL = 'assets/images/bitLogo.png';
    public $pay_with_text = 'Pay with bit';
}

class WC_PayPlus_Gateway_GooglePay extends WC_PayPlus_Subgateway
{
    public $id = 'payplus-payment-gateway-googlepay';
    public $method_title_text = 'PayPlus - Google Pay';
    public $default_description_settings_text = 'Google Pay payment via PayPlus';
    public $method_descrition_text = 'Pay with Google Pay via PayPlus';
    public $payplus_default_charge_method = 'google-pay';
    public $iconURL = 'assets/images/google-payLogo.png';
    public $pay_with_text = 'Pay with Google Pay';
}

class WC_PayPlus_Gateway_ApplePay extends WC_PayPlus_Subgateway
{
    public $id = 'payplus-payment-gateway-applepay';
    public $method_title_text = 'PayPlus - Apple Pay';
    public $default_description_settings_text = 'Apple1 Pay payment via PayPlus';
    public $method_descrition_text = 'Pay with Apple Pay via PayPlus';
    public $payplus_default_charge_method = 'apple-pay';
    public $iconURL = 'assets/images/apple-payLogo.png';
    public $pay_with_text = 'Pay with Apple Pay';
}

class WC_PayPlus_Gateway_Multipass extends WC_PayPlus_Subgateway
{
    public $id = 'payplus-payment-gateway-multipass';
    public $method_title_text = 'PayPlus - MULTIPASS';
    public $default_description_settings_text = 'BUYME payment via PayPlus';
    public $method_descrition_text = 'Pay With MULTIPASS via PayPlus';
    public $payplus_default_charge_method = 'multipass';
    public $iconURL = 'assets/images/multipassLogo.png';
    public $pay_with_text = 'Pay with MULTIPASS';
}

class WC_PayPlus_Gateway_Paypal extends WC_PayPlus_Subgateway
{
    public $id = 'payplus-payment-gateway-paypal';
    public $method_title_text = 'PayPlus - PayPal';
    public $default_description_settings_text = 'PayPal payment via PayPlus';
    public $method_descrition_text = 'Pay with PayPal via PayPlus';
    public $payplus_default_charge_method = 'paypal';
    public $iconURL = 'assets/images/paypalLogo.png';
    public $pay_with_text = 'Pay with PayPal';
}

class WC_PayPlus_Gateway_TavZahav extends WC_PayPlus_Subgateway
{
    public $id = 'payplus-payment-gateway-tavzahav';
    public $method_title_text = 'PayPlus - Tav Zahav';
    public $default_description_settings_text = 'Tav Zahav payment via PayPlus';
    public $method_descrition_text = 'Pay with Tav Zahav via PayPlus';
    public $payplus_default_charge_method = 'tav-zahav';
    public $iconURL = 'assets/images/verifoneLogo.png';
    public $pay_with_text = 'Pay with Tav Zahav';
}

class WC_PayPlus_Gateway_Valuecard extends WC_PayPlus_Subgateway
{
    public $id = 'payplus-payment-gateway-valuecard';
    public $method_title_text = 'PayPlus - Valuecard ';
    public $default_description_settings_text = 'Valuecard  payment via PayPlus';
    public $method_descrition_text = 'Pay with Valuecard via PayPlus';
    public $payplus_default_charge_method = 'valuecard';
    public $iconURL = 'assets/images/valuecardLogo.png';
    public $pay_with_text = 'Pay with  Valuecard ';
}

class WC_PayPlus_Gateway_FinitiOne extends WC_PayPlus_Subgateway
{
    public $id = 'payplus-payment-gateway-finitione';
    public $method_title_text = 'PayPlus - finitiOne ';
    public $default_description_settings_text = 'finitiOne  payment via PayPlus';
    public $method_descrition_text = 'Pay with finitiOne via PayPlus';
    public $payplus_default_charge_method = 'finitione';
    public $iconURL = 'assets/images/finitioneLogo.png';
    public $pay_with_text = 'Pay with Tav finitiOne';
}
