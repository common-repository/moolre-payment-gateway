<?php

 if (!defined('ABSPATH')) {
    exit;
 }

 if (!class_exists('WC_Payment_Gateway')) {
    return;
 }

 /**
 * Register payment gateway's class as a new method of payment.
 *
 * @param array $methods
 * @return array
 */
 function mpwp_moolre_add_gateway($methods)
 {
    $methods[] = 'MPWP_Moolre_Payment_Gateway';
    return $methods;
 }
 
 add_filter('woocommerce_payment_gateways', 'mpwp_moolre_add_gateway');

 if (!class_exists('MPWP_Moolre_Payment_Gateway')) {

 class MPWP_Moolre_Payment_Gateway extends WC_Payment_Gateway 
  {

        // Setup our Gateway's id, description and other values
        public $mpwp_moolre_url;
        public $mpwp_pubKey;
        public $mpwp_description;
        public $mpwp_environment;
        public $mpwp_accountNumber;
        public $mpwp_reference;
        public $mpwp_currency;
        public $mpwp_feeBearer;
        public $mpwp_callBack;
        public $mpwp_autocomplete_order;

        /**
         * Class constructor with basic gateway's setup.
         *
         * @codeCoverageIgnore
         */

        public function __construct()
        {
            $this->id              = 'mpwp-moolre';
            $this->mpwp_moolre_url = 'https://api.moolre.com/embed/pay/start';
            $this->icon            = apply_filters('mpwp_woocommerce_moolre_icon', plugins_url('../assets/images/moolre-gh.png', __FILE__));
            $this->method_title    = __('Moolre Payments', 'moolre-commerce');
            $this->method_description = esc_attr__('Moolre Payment Gateway Plug-in for WooCommerce', 'moolre-commerce');
            $this->has_fields      = true;

            $this->init_form_fields();
            $this->init_settings();

            $this->title = esc_attr__('Moolre', 'moolre-commerce');

            // Turn these settings into variables we can use
            $this->mpwp_pubKey = $this->get_option('pubKey');
            $this->mpwp_accountNumber = $this->get_option('accountNumber');
            $this->mpwp_currency = $this->get_option('currency');
            $this->mpwp_feeBearer = $this->get_option('feeBearer');
            $this->mpwp_callBack = $this->get_option('callBack');
            $this->mpwp_description = $this->get_option('description');

            $this->add_actions();
        }
 
        // Build the administration fields for this Gateway
        public function init_form_fields()
        {
            $this->form_fields = array(
                'enabled' => array(
                    'title' => esc_attr__('Enable / Disable', 'moolre-commerce'),
                    'label' => esc_attr__('Enable this payment gateway', 'moolre-commerce'),
                    'type' => 'checkbox',
                    'default' => 'no',
                ),
                'title' => array(
                    'title' => esc_attr__('Title', 'moolre-commerce'),
                    'type' => 'text',
                    'desc_tip' => esc_attr__('Payment title the customer will see during the checkout process.', 'moolre-commerce'),
                    'default' => esc_attr__('Moolre', 'moolre-commerce'),
                ),
                'description' => array(
                    'title' => esc_attr__('Description', 'moolre-commerce'),
                    'type' => 'text',
                    'desc_tip' => esc_attr__('Payment description the customer will see during the checkout process.', 'moolre-commerce'),
                    'default' => esc_attr__('Pay securely using your Mobile Money Wallet.', 'moolre-commerce'),
                ),
                'pubKey' => array(
                    'title' => esc_attr__('Public Key', 'moolre-commerce'),
                    'type' => 'textarea',
                    'desc_tip' => esc_attr__('Public Key can be found on your Moolre dashboard.', 'moolre-commerce'),
                    'css' => 'max-width:400px;'
                ),
                'accountNumber' => array(
                    'title' => esc_attr__('Account Number', 'moolre-commerce'),
                    'type' => 'text',
                    'desc_tip' => esc_attr__('This is the account number seen on your wallet on the Moolre dashboard.', 'moolre-commerce'),
                ),
                'currency' => array(
                    'title' => esc_attr__('Currency', 'moolre-commerce'),
                    'type' => 'select',
                    'class' => 'my-field-class form-x  -wide', 
                    'options' => [
                        'GHS'  => esc_attr__('Ghana Cedi', 'moolre-commerce'),
                    ],
                    'desc_tip' => esc_attr__('This is the currency to be used for payments.', 'moolre-commerce'),
                ),
                'feeBearer' => array(
                    'title' => esc_attr__('Fee Bearer', 'moolre-commerce'),
                    'type' => 'select',
                    'class' => 'my-field-class form-row-wide',
                    'options' => [
                        'self'  => esc_attr__('Merchant', 'moolre-commerce'),
                        'customer'  => esc_attr__('Customer',  'moolre-commerce')
                    ],
                    'desc_tip' => esc_attr__('This is the bearer of fees on payments.', 'moolre-commerce'),
                    'default' => esc_attr__('This is the bearer of Payment Fees.', 'moolre-commerce'),
                ),
                'autocomplete_order' => array(
                    'title'       => esc_attr__('Autocomplete Order After Payment', 'moolre-commerce'),
                    'label'       => esc_attr__('Autocomplete Order', 'moolre-commerce'),
                    'type'        => 'checkbox',
                    'class'       => 'wc-moolre-autocomplete-order',
                    'description' => esc_attr__('If enabled, the order will be marked as complete after successful payment', 'moolre-commerce'),
                    'default'     => 'no',
                    'desc_tip'    => true,
                ),
            );
        }

        /**
         * Register different actions.
         *
         * @codeCoverageIgnore
         */
        private function add_actions()
        {
            // Lets check for SSL
            add_action('admin_notices', array($this, 'admin_notices'));
            
            // Payment Receipt
            add_action('woocommerce_receipt_' . $this->id, array($this, 'mpwp_receipt_page'));

            // Payment listener/API hook.
            add_action('woocommerce_api_mpwp_moolre_payment_gateway', array($this, 'mpwp_verify_moolre_transaction'));

            //register the callback hooks which we will use to receive the payment response from the gateway
            add_action('woocommerce_api_moolre_gateway', array($this, 'payment_callback'));
            // Save settings
            if (is_admin()) {
                // Versions over 2.0
                // Save our administration options. Since we are not going to be doing anything special
                // we have not defined 'process_admin_options' in this class so the method in the parent
                // class will be used instead
                add_action('woocommerce_update_options_payment_gateways_' . $this->id, array($this, 'process_admin_options'));
            }
        }

        /**
         * Display Moolre Description and Custom Nonce.
         */
        function payment_fields()
        {
            // Display the payment gateway description
            echo esc_html(wptexturize($this->mpwp_description));

            // Add the custom nonce field using the existing function
            $this->mpwp_add_custom_nonce();
        }

        /**
         * Add custom nonce field to the checkout form.
         */
        function mpwp_add_custom_nonce() {
            wp_nonce_field('mpwp_nonce_action', 'mpwp_nonce_field');
        }

        // Submit payment and handle response
        public function process_payment($order_id)
        {
            
            // Check if the nonce field is empty or invalid
            if (empty($_POST['mpwp_nonce_field']) || !wp_verify_nonce(sanitize_text_field(wp_unslash($_POST['mpwp_nonce_field'])), 'mpwp_nonce_action')) {
                wc_add_notice(esc_html__('Nonce verification failed. Please try again.', 'moolre-commerce'), 'error');
                return;
            }

            // Test environment setting
            $environment = 'TRUE';

            if ($environment == "TRUE") {
                // Get the order's information
                $order     = wc_get_order($order_id);
                $amount    = $order->get_total();
                $reference = $order_id . '_' . time();
                $callback  = WC()->api_request_url('MPWP_Moolre_Payment_Gateway');

                $order->update_meta_data('_moolre_ref', $reference);
                $order->save();

                // Prepare the data to send to the server
                $json_data = [
                    'state'         => 'starter',
                    'accountnumber' => $this->mpwp_accountNumber,
                    'reference'     => $reference,
                    'nonce_value'   => sanitize_text_field(wp_unslash($_POST['mpwp_nonce_field'])),
                    'email'         => $order->get_billing_email(),
                    'amount'        => $amount,
                    'currency'      => $this->mpwp_currency,
                    'callback'      => $callback,
                    'tx_source'     => 'woocommerce-plugin'
                ];

                $headers = [
                    'X-Api-Pubkey' => $this->mpwp_pubKey
                ];

                $args = [
                    'headers' => $headers,
                    'timeout' => 60,
                    'body'    => wp_json_encode($json_data)
                ];

                $request = wp_remote_post($this->mpwp_moolre_url, $args);

                if (!is_wp_error($request) && 200 === wp_remote_retrieve_response_code($request)) {
                    $moolre_response = json_decode(wp_remote_retrieve_body($request), true);
                    if ($moolre_response['status'] == 0 || $moolre_response['status'] == false) {
                        wc_add_notice(esc_html__('Payment failed. Please try again.', 'moolre-commerce'), 'error');
                    } else {
                        return [
                            'result'   => 'success',
                            'redirect' => $moolre_response['data']['authorization_url'],
                        ];
                    }
                } elseif (403 === wp_remote_retrieve_response_code($request)) {
                    throw new Exception(esc_html__('Error in Connection', 'moolre-commerce'));
                } else {
                    wc_add_notice(esc_html__('Unable to process payment. Please try again.', 'moolre-commerce'), 'error');
                    return;
                }
            } else {
                wc_add_notice(esc_html__('Payment cannot be processed in live mode.', 'moolre-commerce'), 'error');
            }
        }

        /**
         * Retrieve a transaction from Moolre.
        */
        private function get_mpwp_moolre_transaction($moolre_trans_ref)
        {
            // HTTP API
            $json_data = [
                'state' => 'confirm',
                'accountnumber' => $this->mpwp_accountNumber,
                'reference' => $moolre_trans_ref,
            ];

            $headers = [
                'X-Api-Pubkey' => $this->mpwp_pubKey,
            ];

            $args = [
                'headers' => $headers,
                'timeout' => 60,
                'body'    => wp_json_encode($json_data),
            ];

            $response = wp_remote_post($this->mpwp_moolre_url, $args);

            if (!is_wp_error($response) && 200 === wp_remote_retrieve_response_code($response)) {
                $moolre_response = json_decode(wp_remote_retrieve_body($response), true);

                if ($moolre_response['status'] == 0 || $moolre_response['status'] == false) {
                    throw new Exception(esc_attr__('Verification Failure!', 'moolre-commerce'));
                }

                return $moolre_response;
            } elseif (403 === wp_remote_retrieve_response_code($response)) {
                throw new Exception(esc_attr__('Error in Connection!', 'moolre-commerce'));
            } else {
                wc_add_notice(esc_attr__('Unable to process payment, try again', 'moolre-commerce'), 'error');
            }

            return false;
        }

        /**
         * Checks if autocomplete order is enabled for the payment method.
         */
        protected function mpwp_is_autocomplete_order_enabled($order_id)
        {
            if ($this->mpwp_autocomplete_order == 'yes') {
                $autocomplete_order = true;
            } else {
                $autocomplete_order = false;
            }
            return $autocomplete_order;
        }

        /**
         * Verify Moolre payment.
        */
        public function mpwp_verify_moolre_transaction()
        {
            if (isset($_REQUEST['reference']) && isset($_REQUEST['mpwp_nonce'])) {
                $mpwp_nonce = sanitize_text_field(wp_unslash($_REQUEST['mpwp_nonce']));
                $reference  = sanitize_text_field(wp_unslash($_REQUEST['reference']));

                if (wp_verify_nonce($mpwp_nonce, 'mpwp_nonce_action')) {
                    $value = esc_attr($reference);
                } else {
                    $value = ''; // Handle the case where nonce verification fails
                }
            } else {
                $value = ''; // Handle the case where reference or nonce is not set
            }

            if (!empty($value)) {
                $moolre_txn_ref = $value;
            } else {
                $moolre_txn_ref = false;
            }

            @ob_clean();

            if ($moolre_txn_ref) {
                $moolre_response = $this->get_mpwp_moolre_transaction($moolre_txn_ref);

                if (1 == $moolre_response['data']['status']) {
                    $order_details = explode('_', $moolre_response['data']['reference']);
                    $order_id = (int) $order_details[0];
                    $order = wc_get_order($order_id);

                    // Check if the order has already been processed
                    if (in_array($order->get_status(), array('processing', 'completed', 'on-hold'))) {
                        wp_redirect($this->get_return_url($order));
                        exit;
                    } else {
                        // Update the order status to processing or completed
                        $order->update_status('processing');
                        $order->payment_complete(); 

                        // Redirect to the return URL after processing
                        wp_redirect($this->get_return_url($order));
                        exit;
                    }
                }

                wp_redirect($this->get_return_url($order));
                exit;
            }

            wp_redirect(wc_get_page_permalink('cart'));
            exit;
        }

        public function mpwp_receipt_page($order_id)
        {
            $order = wc_get_order($order_id);

            echo '<div id="wc-moolre-form">';

            echo '<p>' . esc_html__('Thank you for your order, please click the button below to pay with Moolre.', 'moolre-commerce') . '</p>';

            echo '<div id="moolre_form"><form id="order_review" method="post" action="' . esc_url(WC()->api_request_url('MPWP_Moolre_Payment_Gateway')) . '"></form><button class="button" id="moolre-payment-button">' . esc_html__('Pay Now', 'moolre-commerce') . '</button>';

            echo '</div>';
        }

        // Check if Moolre's merchant details is filled.
        public function admin_notices()
        {

            if ($this->enabled == 'no') {
                return;
            }

            // Check required fields.
            if (!($this->mpwp_pubKey && $this->mpwp_accountNumber)) {
                echo '<div class="error"><p>' . sprintf(
                    // Translators: %s is a placeholder for the admin URL linking to the Moolre settings page.
                    esc_attr__(
                        'Please enter your Moolre\'s public key and account number <a href="%s">here</a> to be able to use the Moolre WooCommerce plugin.',
                        'moolre-commerce'
                    ),
                    esc_url(admin_url('admin.php?page=wc-settings&tab=checkout&section=moolre'))
                ) . '</p></div>';

                return;

            }
        }
  }
  
}