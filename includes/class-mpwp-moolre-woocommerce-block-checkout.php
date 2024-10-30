<?php

use Automattic\WooCommerce\Blocks\Payments\Integrations\AbstractPaymentMethodType;

final class MPWP_Moolre_Payment_Gateway_Blocks extends AbstractPaymentMethodType
{
    private  $gateway;
    protected $settings;
    protected $name = 'mpwp_payment_gateway'; // your payment gateway name

    const SCRIPT_VERSION = '1.0.0';

    public function __construct()
    {
        $this->settings = get_option('mpwp_woocommerce_mpwp_payment_gateway_settings', []);
        $this->gateway = new MPWP_Moolre_Payment_Gateway();
    }

    /**
     * Initialize the payment gateway settings.
     */
    public function initialize()
    {
        // Initialization logic if needed
    }

    /**
     * Check if the payment gateway is active.
     *
     * @return bool
     */
    public function is_active()
    {
        return $this->gateway->is_available();
    }

    /**
     * Get the script handles for the payment method.
     *
     * @return array
     */
    public function get_payment_method_script_handles()
    {
        wp_register_script(
            'mpwp_payment_gateway_blocks_integration',
            plugin_dir_url(__FILE__) . 'assets/js/mpwp-scripts.js',
            [
                'wc-blocks-registry',
                'wc-settings',
                'wp-element',
                'wp-html-entities',
                'wp-i18n',
            ],
            self::SCRIPT_VERSION,
            true
        );

        // Ensure script translations are available
        if (function_exists('wp_set_script_translations')) {
            wp_set_script_translations(
                'mpwp_payment_gateway_blocks_integration',
                'mpwp',
                plugin_dir_path(__FILE__) . 'languages/'
            );
        }

        return ['mpwp_payment_gateway_blocks_integration'];
    }

    /**
     * Get the payment method data.
     *
     * @return array
     */
    public function get_payment_method_data()
    {
        return [
            'title' => esc_html($this->gateway->title),
            'description' => esc_html($this->gateway->description),
        ];
    }
}