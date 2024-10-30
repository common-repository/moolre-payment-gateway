<?php
// phpcs:ignore WordPress.Files.FileName.InvalidClassFileName
/**
 * Plugin Name: Moolre Payment Gateway for WooCommerce
 * Plugin URI: https://docs.moolre.com/wordpress
 * Description: Moolre Payment gateway for WooCommerce
 * Version: 1.2.0
 * License: GPL-2.0+
 * License URL: http://www.gnu.org/licenses/gpl-2.0.txt
 * Author: Moolre Development Team
 * Author URI: https://github.com/agamics
 * Tags: moolre, woocommerce, payment gateway, cedi, naira
 * text-domain: moolre-commerce
 * Requires at least: 4.7
 * Requires PHP: 5.6
 * Stable tag: 1.2.0
 * WC requires at least: 3.0.0
 * WC tested up to: 6.5.3
 *
 * License: MIT
 */


if (!defined('ABSPATH')) {
    exit;
}

/**
 * Declare compatibility with WooCommerce HPOS
 */
add_action('before_woocommerce_init', function () {
    if (class_exists(\Automattic\WooCommerce\Utilities\FeaturesUtil::class)) {
        \Automattic\WooCommerce\Utilities\FeaturesUtil::declare_compatibility('custom_order_tables', __FILE__, true);
    }
});

/**
 * Custom function to declare compatibility with cart_checkout_blocks feature 
 */
function mpwp_moolre_declare_cart_checkout_blocks_compatibility()
{
    // Check if the required class exists
    if (class_exists('\Automattic\WooCommerce\Utilities\FeaturesUtil')) {
        // Declare compatibility for 'cart_checkout_blocks'
        \Automattic\WooCommerce\Utilities\FeaturesUtil::declare_compatibility('cart_checkout_blocks', __FILE__, true);
    }
}
// Hook the custom function to the 'before_woocommerce_init' action
add_action('before_woocommerce_init', 'mpwp_moolre_declare_cart_checkout_blocks_compatibility');

// Hook the custom function to the 'woocommerce_blocks_loaded' action
add_action('woocommerce_blocks_loaded ', 'mpwp_moolre_register_order_approval_payment_method_type');


/**
 * Custom function to register a payment method type
 */
function mpwp_moolre_register_order_approval_payment_method_type()
{
    // Check if the required class exists
    if (!class_exists('Automattic\WooCommerce\Blocks\Payments\Integrations\AbstractPaymentMethodType')) {
        return;
    }
    
    // Include the custom Blocks Checkout class
    require_once plugin_dir_path(__FILE__) . 'includes/class-mpwp-moolre-woocommerce-block-checkout.php';
 
    // Hook the registration function to the 'woocommerce_blocks_payment_method_type_registration' action
    add_action(
        'woocommerce_blocks_payment_method_type_registration',
        function (Automattic\WooCommerce\Blocks\Payments\PaymentMethodRegistry $payment_method_registry) {
            // Register an instance of MPWP_Moolre_Payment_Gateway_Blocks
            $payment_method_registry->register(new MPWP_Moolre_Payment_Gateway_Blocks());
        }
    );
}

if (!function_exists('mpwp_moolre_is_woocommerce_active')) {
    /**
     * Return true if Woocommerce plugin is active.
     *
     * @since 0.1
     * @return boolean
     */
    function mpwp_moolre_is_woocommerce_active()
    {
        if (in_array('woocommerce/woocommerce.php', apply_filters('active_plugins', get_option('active_plugins')), true)) {
            return true;
        }
        return false;
    }
}

if (!function_exists('mpwp_moolre_admin_notice_missing_woocommerce')) {
    /**
     * Echo admin notice HTML for missing WooCommerce plugin.
     *
     * @since 0.1
     */
    function mpwp_moolre_admin_notice_missing_woocommerce()
    {
        global $current_screen;
        if ($current_screen->parent_base === 'plugins') {
            ?>
<div class="notice notice-error">
    <p>
        <?php
        /* translators: %s: link to WooCommerce */
        echo sprintf(esc_html__('Please install and activate %s before activating the Moolre WooCommerce Payment Gateway!', 'moolre-commerce'), '<a href="http://www.woothemes.com/woocommerce/" target="_blank">WooCommerce</a>');
        ?>
    </p>
</div>
<?php
        }
    }
}

if (!mpwp_moolre_is_woocommerce_active()) {
    add_action('admin_notices', 'mpwp_moolre_admin_notice_missing_woocommerce');
    return;
}

if (!class_exists('MPWP_Moolre_Main')) {
    class MPWP_Moolre_Main
    {
        /**
         * Current plugin's version.
         *
         * @var string
         */
        const VERSION = '1.2.0';

        /**
         * Instance of the current class, null before first usage.
         *
         * @var MPWP_Moolre_Main
         */
        protected static $instance = null;

        /**
         * Class constructor.
         *
         * @codeCoverageIgnore
         * @since 0.1
         */
        protected function __construct()
        {
            require_once 'includes/class-mpwp-moolre-payment-gateway.php';
        }

        /**
         * Installation procedure.
         *
         * @static
         * @since 0.1
         */
        public static function install()
        {
            if (!current_user_can('activate_plugins')) {
                return false;
            }
        }

        /**
         * Uninstallation procedure.
         *
         * @static
         * @since 0.1
         */
        public static function uninstall()
        {
            if (!current_user_can('activate_plugins')) {
                return false;
            }

            // delete_option( 'woocommerce_neuralab-wcwspay_settings' );
            // wp_cache_flush();
        }

        /**
         * Return class instance.
         *
         * @static
         * @since 0.1
         * @return MPWP_Moolre_Main
         */
        public static function get_instance()
        {
            // @codeCoverageIgnoreStart
            if (is_null(self::$instance)) {
                self::$instance = new self();
            }
            // @codeCoverageIgnoreEnd
            return self::$instance;
        }

        /**
         * Cloning is forbidden.
         *
         * @since 0.1
         */
        public function __clone()
        {
            return wp_die('Cloning is forbidden!');
        }

        /**
         * Unserializing instances of this class is forbidden.
         *
         * @since 0.1
         */
        public function __wakeup()
        {
            return wp_die('Unserializing instances is forbidden!');
        }
    }
}

register_activation_hook(__FILE__, ['MPWP_Moolre_Main', 'install']);
register_uninstall_hook(__FILE__, ['MPWP_Moolre_Main', 'uninstall']);
add_action('plugins_loaded', ['MPWP_Moolre_Main', 'get_instance'], 0);