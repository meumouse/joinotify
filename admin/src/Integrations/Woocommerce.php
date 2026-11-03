<?php

namespace MeuMouse\Joinotify\Integrations;

use MeuMouse\Joinotify\Admin\Admin;
use MeuMouse\Joinotify\Cron\Schedule;
use MeuMouse\Joinotify\Builder\Placeholders;
use MeuMouse\Joinotify\Builder\Triggers;
use MeuMouse\Joinotify\Core\Helpers;
use MeuMouse\Joinotify\Core\Logger;
use MeuMouse\Joinotify\Core\Upgrader;
use MeuMouse\Joinotify\Core\Workflow_Processor;

use WC_Session_Handler;
use WC_Cart;
use WC_Coupon;
use WC_Customer;
use WP_Error;
use WP_Query;

// Exit if accessed directly.
defined('ABSPATH') || exit;

/**
 * Add integration with WooCommerce
 * 
 * @since 1.0.0
 * @version 1.4.7
 * @package MeuMouse\Joinotify\Integrations
 * @author MeuMouse.com
 */
class Woocommerce extends Integrations_Base {

    /**
     * Order meta that records when the "Payment confirmed" trigger ran, so it runs once per order.
     *
     * @since 2.7.0
     * @var string
     */
    const META_PAYMENT_CONFIRMED = '_joinotify_payment_confirmed';

    /**
     * Customer accounts created in this request, dispatched on shutdown (user ID => context).
     *
     * @since 2.7.0
     * @var array<int,array<string,mixed>>
     */
    protected static $created_customers = array();

    /**
     * Products whose save is moving them from out of stock or on backorder to in stock.
     *
     * @since 2.7.0
     * @var array<int,bool>
     */
    protected static $restocking = array();

    /**
     * Products announced back in stock in this request: "self" when the product itself was,
     * "variation" for a variable product one of whose variations was.
     *
     * @since 2.7.0
     * @var array<int,string>
     */
    protected static $restocked_products = array();

    /**
     * Stock alerts already dispatched in this request (hook and product ID).
     *
     * @since 2.7.0
     * @var array<string,bool>
     */
    protected static $stock_alerts = array();

    /**
     * Back in stock notices already dispatched in this request (notice ID).
     *
     * @since 2.7.0
     * @var array<int,bool>
     */
    protected static $stock_notices = array();

    /**
     * Construct function
     * 
     * @since 1.0.0
     * @version 2.7.0
     * @return void
     */
    public function __construct() {
        // add integration on settings
        $this->register_settings_tab( 20 );

        // check if woocommerce is active
        if ( class_exists('WooCommerce') ) {
            // standard builder hooks (triggers, tab, content, placeholders, conditions)
            $this->register_builder_hooks( 20, 2 );

            // add coupon action
            if ( Admin::get_setting('enable_create_coupon_action') === 'yes' ) {
                add_filter( 'Joinotify/Builder/Actions', array( $this, 'add_coupon_action' ), 10, 1 );
                add_filter( 'Joinotify/Builder/Action_Categories', array( $this, 'add_woocommerce_category' ), 10, 1 );
            }

            // fire hooks if WooCommerce is active
            if ( Admin::get_setting('enable_woocommerce_integration') === 'yes' ) {
                // on receive new order
                // before hook used is "woocommerce_new_order", but products isent received
                add_action( 'woocommerce_checkout_order_processed', array( $this, 'process_workflow_on_new_order' ), 10, 1 );

                // the checkout block places orders through the Store API, which never fires the hook above
                add_action( 'woocommerce_store_api_checkout_order_processed', array( $this, 'process_workflow_on_block_checkout_order' ), 10, 1 );

                // when the payment of an order is confirmed: a gateway confirms it, or the order
                // moves from an unpaid status to a paid one (cash on delivery, a manual change)
                add_action( 'woocommerce_payment_complete', array( $this, 'process_workflow_payment_complete' ), 10, 2 );
                add_action( 'woocommerce_order_status_changed', array( $this, 'process_workflow_payment_status_changed' ), 10, 3 );

                // when a customer account is created, dispatched at the end of the request because the
                // checkout only saves the billing phone after WooCommerce fires this hook
                add_action( 'woocommerce_created_customer', array( $this, 'queue_customer_created' ), 10, 3 );

                // when the stock of a product reaches the low or the no stock threshold
                add_action( 'woocommerce_low_stock', array( $this, 'process_workflow_low_stock' ), 10, 1 );
                add_action( 'woocommerce_no_stock', array( $this, 'process_workflow_no_stock' ), 10, 1 );

                // when a product goes back in stock: the status before the save is read first, since
                // the stock status hooks also fire for a product created in stock
                add_action( 'woocommerce_before_product_object_save', array( $this, 'remember_restock' ), 10, 1 );
                add_action( 'woocommerce_before_product_variation_object_save', array( $this, 'remember_restock' ), 10, 1 );
                add_action( 'woocommerce_product_set_stock_status', array( $this, 'process_workflow_back_in_stock' ), 10, 3 );
                add_action( 'woocommerce_variation_set_stock_status', array( $this, 'process_workflow_back_in_stock' ), 10, 3 );

                // when WooCommerce notifies a customer who signed up for a back in stock notice. The
                // email has no action of its own, so the filter that asks whether it is enabled is
                // used: it runs once per notice, right before the email is sent
                add_filter( 'woocommerce_email_enabled_customer_stock_notification', array( $this, 'process_workflow_customer_stock_notification' ), 10, 2 );

                // when order is completed
                add_action( 'woocommerce_order_status_completed', array( $this, 'process_workflow_order_completed' ), 10, 3 );

                // when a order is fully refunded
                add_action( 'woocommerce_order_fully_refunded', array( $this, 'process_workflow_order_fully_refunded' ), 10, 2 );

                // when a order is partially refunded
                add_action( 'woocommerce_order_partially_refunded', array( $this, 'process_workflow_order_partially_refunded' ), 10, 2 );
                
                // when a order has status changed
                add_action( 'woocommerce_order_status_changed', array( $this, 'process_workflow_order_status_changed' ), 10, 3 );

                // when the download access of a digital product is granted
                // wc_downloadable_product_permissions() bails out when the access was already
                // granted, so this fires only once per order even though WooCommerce hooks it
                // on both the processing and completed statuses
                add_action( 'woocommerce_grant_product_download_permissions', array( $this, 'process_workflow_download_permissions_granted' ), 10, 1 );

                // when the customer downloads a digital file
                add_action( 'woocommerce_download_product', array( $this, 'process_workflow_download_product' ), 10, 6 );
            }
        }
    }


    /**
     * Add integration item on settings
     * 
     * @since 1.3.0
     * @param array $integrations | Current integrations
     * @return array
     */
    public function add_integration_item( $integrations ) {
        $integrations['woocommerce'] = self::build_integration_item(
            'woocommerce',
            esc_html__( 'WooCommerce', 'joinotify' ),
            esc_html__( 'Send messages regarding new orders, cancellations, refunds, and recovery of unpaid orders. Keep your customers updated.', 'joinotify' ),
            '<svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 1052 1052"><circle fill="#873eff" cx="526" cy="526" r="526"/><path fill="#fff" d="M201.11,657.84c26.54,0,47.83-13.1,63.89-43.25l35.71-66.84v56.68c0,33.42,21.63,53.41,55.05,53.41,26.21,0,45.54-11.47,64.21-43.25l82.24-138.92c18-30.47,5.25-53.41-34.4-53.41-21.3,0-35.06,6.89-47.51,30.15L363.62,558.89V464.2c0-28.17-13.43-41.94-38.33-41.94-19.66,0-35.39,8.52-47.51,32.11L224.37,558.89v-93.7c0-30.15-12.45-42.93-42.59-42.93h-61.6c-23.26,0-35.06,10.82-35.06,30.8s12.45,31.46,35.06,31.46h25.23V604.11C145.41,637.85,168,657.84,201.11,657.84Z" transform="translate(-14 -14)"/><path fill="#fff" fill-rule="evenodd" d="M622.48,422.26c-67.17,0-118.61,50.13-118.61,118s51.77,117.62,118.61,117.62,117.95-50.13,118.27-117.62C740.75,472.39,689.31,422.26,622.48,422.26Zm0,163.17c-25.23,0-42.6-19-42.6-45.21s17.37-45.55,42.6-45.55,42.59,19.34,42.59,45.55S648,585.43,622.48,585.43Z" transform="translate(-14 -14)"/><path fill="#fff" fill-rule="evenodd" d="M757.44,540.22c0-67.83,51.44-118,118.28-118S994,472.72,994,540.22,942.56,657.84,875.72,657.84,757.44,608,757.44,540.22Zm76,0c0,26.21,16.7,45.21,42.26,45.21,25.23,0,42.59-19,42.59-45.21S901,494.67,875.72,494.67,833.46,514,833.46,540.22Z" transform="translate(-14 -14)"/></svg>',
            array(
                'category' => 'ecommerce',
                'setting_key' => 'enable_woocommerce_integration',
                'action_hook' => 'Joinotify/Settings/Tabs/Integrations/Woocommerce',
                'is_plugin' => true,
                'plugin_active' => array(
                    'woocommerce/woocommerce.php',
                ),
                'settings' => self::get_integration_settings(),
                'modal' => array(
                    'title' => __( 'WooCommerce settings', 'joinotify' ),
                    'description' => __( 'Configure discount coupons and checkout field formatting with the native integration fields.', 'joinotify' ),
                    'button_label' => __( 'Configure', 'joinotify' ),
                ),
            )
        );

        return $integrations;
    }


    /**
     * Declarative settings rendered in the integrations modal.
     *
     * @since 1.4.7
     * @return array<int,array<string,mixed>>
     */
    public static function get_integration_settings() {
        return array(
            self::field_toggle(
                'enable_create_coupon_action',
                esc_html__( 'Activate discount coupon action', 'joinotify' ),
                esc_html__( 'Adds the coupon action to WooCommerce workflows so a workflow can create and send a discount coupon.', 'joinotify' )
            ),
            self::field_text(
                'create_coupon_prefix',
                esc_html__( 'Coupon prefix', 'joinotify' ),
                esc_html__( 'Prefix used when Joinotify generates a coupon code automatically.', 'joinotify' ),
                array(
                    'placeholder' => 'CUPOM_',
                )
            ),
            self::field_textarea(
                'woocommerce_billing_full_address_format',
                esc_html__( 'Billing full address format', 'joinotify' ),
                esc_html__( 'Defines the value used in the billing full address variable. You can combine placeholders like {{ address_1 }}, {{ number }}, {{ city }}, {{ state }}, and {{ postcode }}.', 'joinotify' ),
                array(
                    'placeholder' => '{{ address_1 }}, {{ number }}, {{ city }} - {{ state }} (CEP: {{ postcode }})',
                    'rows' => 3,
                )
            ),
            self::field_textarea(
                'woocommerce_shipping_full_address_format',
                esc_html__( 'Shipping full address format', 'joinotify' ),
                esc_html__( 'Defines the value used in the shipping full address variable. You can combine placeholders like {{ address_1 }}, {{ number }}, {{ city }}, {{ state }}, and {{ postcode }}.', 'joinotify' ),
                array(
                    'placeholder' => '{{ address_1 }}, {{ number }}, {{ city }} - {{ state }} (CEP: {{ postcode }})',
                    'rows' => 3,
                )
            ),
            self::field_toggle(
                'enable_ignore_processed_actions',
                esc_html__( 'Ignore actions already processed.', 'joinotify' ),
                esc_html__( 'Prevents the same action from being processed again when the trigger is repeated.', 'joinotify' )
            ),
        );
    }


    /**
     * Add WooCommerce triggers
     * 
     * @since 1.1.0
     * @param array $triggers | Current triggers
     * @return array
     */
    public function add_triggers( $triggers ) {
        $triggers['woocommerce'] = array(
            array(
                'data_trigger' => 'woocommerce_new_order',
                'title' => __( 'New order', 'joinotify' ),
                'description' => __( 'This trigger is activated when a new order is received in WooCommerce with any status.', 'joinotify' ),
                'require_settings' => false,
            ),
            array(
                'data_trigger' => 'woocommerce_payment_complete',
                'title' => __( 'Payment confirmed', 'joinotify' ),
                'description' => __( 'This trigger is activated once per order, when its payment is confirmed.', 'joinotify' ),
                'require_settings' => false,
            ),
            array(
                'data_trigger' => 'woocommerce_order_status_completed',
                'title' => __( 'Order completed', 'joinotify' ),
                'description' => __( "This action is triggered when an order's status is changed to completed.", 'joinotify' ),
                'require_settings' => false,
            ),
            array(
                'data_trigger' => 'woocommerce_order_fully_refunded',
                'title' => __( 'Order fully refunded', 'joinotify' ),
                'description' => __( 'This action is triggered when an order is fully refunded.', 'joinotify' ),
                'require_settings' => false,
            ),
            array(
                'data_trigger' => 'woocommerce_order_partially_refunded',
                'title' => __( 'Order partially refunded', 'joinotify' ),
                'description' => __( 'This action is triggered when an order is partially refunded.', 'joinotify' ),
                'require_settings' => false,
            ),
            array(
                'data_trigger' => 'woocommerce_order_status_changed',
                'title' => __( 'Order status changed', 'joinotify' ),
                'description' => __( "This action is triggered when an order's status changes.", 'joinotify' ),
                'require_settings' => true,
                'settings' => array(
                    array(
                        'key' => 'order_status',
                        'label' => __( 'Order status', 'joinotify' ),
                        'component' => 'select',
                        'required' => true,
                        'description' => __( 'Choose which order status should start this workflow. Select "Any status" to run on every status change.', 'joinotify' ),
                        'placeholder' => __( 'Select an order status', 'joinotify' ),
                        'options' => self::get_order_status_options(),
                    ),
                ),
            ),
            array(
                'data_trigger' => 'woocommerce_grant_product_download_permissions',
                'title' => __( 'Digital product access granted', 'joinotify' ),
                'description' => __( 'This trigger is activated when the customer gets access to download a digital product.', 'joinotify' ),
                'require_settings' => false,
                'settings' => array(
                    array(
                        'key' => 'product_id',
                        'label' => __( 'Digital product', 'joinotify' ),
                        'component' => 'select',
                        'required' => false,
                        'description' => __( 'Restrict this workflow to a single digital product. Leave "Any digital product" to run for every downloadable item in the order.', 'joinotify' ),
                        'placeholder' => __( 'Select a digital product', 'joinotify' ),
                        'options' => self::get_downloadable_product_options(),
                    ),
                ),
            ),
            array(
                'data_trigger' => 'woocommerce_download_product',
                'title' => __( 'Digital file downloaded', 'joinotify' ),
                'description' => __( 'This action is triggered when the customer actually downloads a digital file from the order.', 'joinotify' ),
                'require_settings' => false,
                'settings' => array(
                    array(
                        'key' => 'product_id',
                        'label' => __( 'Digital product', 'joinotify' ),
                        'component' => 'select',
                        'required' => false,
                        'description' => __( 'Restrict this workflow to a single digital product. Leave "Any digital product" to run for every downloaded file.', 'joinotify' ),
                        'placeholder' => __( 'Select a digital product', 'joinotify' ),
                        'options' => self::get_downloadable_product_options(),
                    ),
                ),
            ),
            array(
                'data_trigger' => 'woocommerce_created_customer',
                'title' => __( 'Customer account created', 'joinotify' ),
                'description' => __( 'This trigger is activated when WooCommerce creates a customer account.', 'joinotify' ),
                'require_settings' => false,
                'settings' => array(
                    array(
                        'key' => 'account_source',
                        'label' => __( 'Where the account was created', 'joinotify' ),
                        'component' => 'select',
                        'required' => false,
                        'description' => __( 'Restrict this workflow to the accounts created in one place. Leave "Anywhere" to run for every new customer account.', 'joinotify' ),
                        'placeholder' => __( 'Select where the account was created', 'joinotify' ),
                        'options' => self::get_account_source_options(),
                    ),
                ),
            ),
            array(
                'data_trigger' => 'woocommerce_low_stock',
                'title' => __( 'Product low in stock', 'joinotify' ),
                'description' => __( 'This trigger is activated when a sale brings a product to the low stock threshold.', 'joinotify' ),
                'require_settings' => false,
            ),
            array(
                'data_trigger' => 'woocommerce_no_stock',
                'title' => __( 'Product out of stock', 'joinotify' ),
                'description' => __( 'This trigger is activated when a sale leaves a product out of stock.', 'joinotify' ),
                'require_settings' => false,
            ),
            array(
                'data_trigger' => 'woocommerce_product_back_in_stock',
                'title' => __( 'Product back in stock', 'joinotify' ),
                'description' => __( 'This trigger is activated when a product that was out of stock is back in stock.', 'joinotify' ),
                'require_settings' => false,
            ),
            array(
                'data_trigger' => 'woocommerce_customer_stock_notification',
                'title' => __( 'Back in stock notice sent', 'joinotify' ),
                'description' => __( 'This trigger is activated for each customer WooCommerce notifies that a product is back in stock.', 'joinotify' ),
                'require_settings' => false,
            ),
        );

        return $triggers;
    }


    /**
     * Get the options of the "Customer account created" trigger setting.
     *
     * @since 2.7.0
     * @return array<int,array<string,string>>
     */
    public static function get_account_source_options() {
        return array(
            array(
                'label' => __( 'Anywhere', 'joinotify' ),
                'value' => 'none',
            ),
            array(
                'label' => __( 'My Account page', 'joinotify' ),
                'value' => 'my_account',
            ),
            array(
                'label' => __( 'Checkout', 'joinotify' ),
                'value' => 'checkout',
            ),
            array(
                'label' => __( 'Order confirmation page', 'joinotify' ),
                'value' => 'order_confirmation',
            ),
            array(
                'label' => __( 'Other (REST API, another plugin)', 'joinotify' ),
                'value' => 'other',
            ),
        );
    }


    /**
     * Triggers whose payload carries a customer and no order.
     *
     * @since 2.7.0
     * @return array<int,string>
     */
    public static function get_customer_trigger_names() {
        return array(
            'woocommerce_created_customer',
            'woocommerce_customer_stock_notification',
        );
    }


    /**
     * Triggers whose payload carries a product and no order.
     *
     * @since 2.7.0
     * @return array<int,string>
     */
    public static function get_product_trigger_names() {
        return array(
            'woocommerce_low_stock',
            'woocommerce_no_stock',
            'woocommerce_product_back_in_stock',
            'woocommerce_customer_stock_notification',
        );
    }


    /**
     * WooCommerce triggers that run for an order, the ones the order placeholders belong to.
     *
     * @since 2.7.0
     * @return array<int,string>
     */
    public static function get_order_trigger_names() {
        $without_order = array_merge( self::get_customer_trigger_names(), self::get_product_trigger_names() );

        return array_values( array_diff( Triggers::get_trigger_names('woocommerce'), $without_order ) );
    }


    /**
     * Get the downloadable product options used by the digital delivery trigger settings.
     *
     * The list is capped because it ships inside the builder bootstrap payload. Stores with more
     * downloadable products than the cap can still use the triggers through the "Any digital
     * product" option, which is why the product filter is optional.
     *
     * @since 2.1.0
     * @return array<int,array<string,string>>
     */
    public static function get_downloadable_product_options() {
        $options = array(
            array(
                'label' => __( 'Any digital product', 'joinotify' ),
                'value' => 'none',
            ),
        );

        if ( ! function_exists('wc_get_products') ) {
            return $options;
        }

        /**
         * Filter how many downloadable products are offered in the trigger settings
         *
         * @since 2.1.0
         * @param int $limit | Maximum number of products listed
         */
        $limit = (int) apply_filters( 'Joinotify/Integrations/Woocommerce/Downloadable_Products_Limit', 100 );

        $products = wc_get_products( array(
            'limit' => $limit,
            'status' => 'publish',
            'downloadable' => true,
            'orderby' => 'title',
            'order' => 'ASC',
            'return' => 'objects',
        ));

        foreach ( $products as $product ) {
            $options[] = array(
                'label' => $product->get_name(),
                'value' => (string) $product->get_id(),
            );
        }

        return $options;
    }


    /**
     * Get the order status options used by the "Order status changed" trigger settings.
     *
     * @since 1.4.8
     * @return array<int,array<string,string>>
     */
    public static function get_order_status_options() {
        $options = array(
            array(
                'label' => __( 'Any status', 'joinotify' ),
                'value' => 'none',
            ),
        );

        if ( function_exists('wc_get_order_statuses') ) {
            foreach ( wc_get_order_statuses() as $status_key => $status_label ) {
                $options[] = array(
                    'label' => $status_label,
                    'value' => $status_key,
                );
            }
        }

        return $options;
    }


    /**
     * Add Woocommerce triggers on sidebar
     * 
     * @since 1.0.0
     * @version 1.4.7
     * @return void
     */
    public function add_triggers_tab() {
        $integration_slug = 'woocommerce';
        $integration_name = __( 'WooCommerce', 'joinotify' );
        $icon_svg = '<svg class="joinotify-tab-icon" viewBox="0 -51.5 256 256" xmlns="http://www.w3.org/2000/svg" xmlns:xlink="http://www.w3.org/1999/xlink" preserveAspectRatio="xMidYMid" fill="#000000" stroke="#000000"><g stroke-width="0"></g><g stroke-linecap="round" stroke-linejoin="round"></g><g> <g fill-rule="evenodd"> <path d="M232.137523,0 L23.7592719,0 C10.5720702,0 -0.103257595,10.7799647 0.000639559736,23.862498 L0.000639559736,103.404247 C0.000639559736,116.591115 10.6767385,127.266612 23.8639402,127.266612 L122.558181,127.266612 L167.667206,152.384995 L157.410382,127.266612 L232.137523,127.266612 C245.325059,127.266612 255.999888,116.591115 255.999888,103.404247 L255.999888,23.862498 C255.999888,10.6752964 245.325059,0 232.137523,0 Z M19.3640061,18.4201481 C16.4334946,18.6294847 14.2355942,19.6761007 12.7703719,21.6645976 C11.3051496,23.5484931 10.781875,25.9556632 11.095813,28.6768382 C17.2707741,67.9247058 23.027062,94.4034429 28.3647436,108.113986 C30.4579757,113.137395 32.8651458,115.5451 35.690989,115.335764 C40.086656,115.021425 45.3196694,108.951332 51.4946305,97.1248185 C54.73908,90.4260478 59.7628237,80.3792294 66.5657276,66.9823567 C72.2170797,86.762992 79.9618646,101.625221 89.6956814,111.567705 C92.417057,114.393415 95.2427665,115.649434 97.9634733,115.440098 C100.371178,115.230761 102.254539,113.974742 103.510558,111.672039 C104.557241,109.683676 104.975914,107.380974 104.766578,104.764601 C104.138568,95.2407893 105.080917,81.9489193 107.69729,64.8892584 C110.417997,47.3063898 113.767382,34.6424627 117.849111,27.1069476 C118.686458,25.5370569 119.000128,23.9670994 118.895794,22.0832708 C118.686458,19.6760338 117.639775,17.6875369 115.651411,16.1176463 C113.663048,14.5477557 111.46468,13.8151445 109.057643,14.0244142 C106.022597,14.2337508 103.719895,15.6989731 102.150205,18.6294847 C95.6614396,30.4560654 91.0560348,49.6088916 88.335328,76.1924977 C84.3579328,66.145211 81.0085475,54.3185634 78.3921746,40.3987506 C77.2411578,34.2238564 74.4154483,31.2933449 69.8100434,31.6073497 C66.670329,31.8166194 64.0538892,33.9098515 61.9606572,37.8869122 L39.0401069,81.5302462 C35.2723159,66.3544807 31.7138615,47.8296643 28.4694119,25.9556632 C27.7368007,20.5133802 24.7016209,18.0014749 19.3640061,18.4201481 Z M221.044022,25.9559976 C228.475136,27.5259551 234.022221,31.5030159 237.789611,38.0965832 C241.138996,43.7482697 242.81302,50.5511737 242.81302,58.7146317 C242.81302,69.4943957 240.092314,79.3325464 234.649562,88.3333508 C228.370134,98.7995112 220.206676,104.032257 210.054854,104.032257 C208.275828,104.032257 206.391799,103.82292 204.402767,103.404247 C196.972321,101.834557 191.425236,97.857831 187.657177,91.264063 C184.308461,85.5076413 182.633768,78.6002028 182.633768,70.5410787 C182.633768,59.7612478 185.355144,49.923164 190.797226,41.0270948 C197.181658,30.5610681 205.345116,25.3280548 215.392603,25.3280548 C217.171629,25.3280548 219.055659,25.5373913 221.044022,25.9559976 Z M216.648622,82.5769291 C220.521015,79.1232099 223.137388,73.9947979 224.602744,67.0873594 C225.021417,64.6802562 225.335087,62.0637496 225.335087,59.3425746 C225.335087,56.3074616 224.707078,53.0630121 223.451058,49.8184957 C221.881368,45.7367667 219.788002,43.5389332 217.275963,43.0155917 C213.508574,42.2829805 209.845518,44.3762126 206.391799,49.5045577 C203.566089,53.4816184 201.786394,57.6680157 200.844713,61.9590813 C200.321038,64.3663182 200.111701,66.9830255 200.111701,69.5993984 C200.111701,72.6344445 200.739711,75.8788272 201.99573,79.1232099 C203.566089,83.2049389 205.658786,85.4026386 208.170825,85.9263145 C210.787198,86.4493215 213.612908,85.2983047 216.648622,82.5769291 Z M172.167608,38.0965832 C168.399549,31.5030159 162.74813,27.5259551 155.422019,25.9559976 C153.432987,25.5373913 151.549626,25.3280548 149.769931,25.3280548 C139.723112,25.3280548 131.559654,30.5610681 125.175223,41.0270948 C119.732472,49.923164 117.011765,59.7612478 117.011765,70.5410787 C117.011765,78.6002028 118.686458,85.5076413 122.035174,91.264063 C125.803233,97.857831 131.350318,101.834557 138.780763,103.404247 C140.769126,103.82292 142.653156,104.032257 144.432851,104.032257 C154.584672,104.032257 162.74813,98.7995112 169.027559,88.3333508 C174.47031,79.3325464 177.191017,69.4943957 177.191017,58.7146317 C177.191017,50.5511737 175.516324,43.7482697 172.167608,38.0965832 Z M158.980072,67.0873594 C157.515384,73.9947979 154.898343,79.1232099 151.02595,82.5769291 C147.990904,85.2983047 145.165195,86.4493215 142.548822,85.9263145 C140.036783,85.4026386 137.943417,83.2049389 136.373727,79.1232099 C135.117707,75.8788272 134.489698,72.6344445 134.489698,69.5993984 C134.489698,66.9830255 134.699034,64.3663182 135.22271,61.9590813 C136.16439,57.6680157 137.943417,53.4816184 140.769126,49.5045577 C144.223514,44.3762126 147.88657,42.2829805 151.65396,43.0155917 C154.165999,43.5389332 156.259365,45.7367667 157.829055,49.8184957 C159.085074,53.0630121 159.713084,56.3074616 159.713084,59.3425746 C159.713084,62.0637496 159.503748,64.6802562 158.980072,67.0873594 Z"></path></g></g></svg>';

        $this->render_integration_trigger_tab( $integration_slug, $integration_name, $icon_svg );
    }

    
    /**
     * Add content tab
     * 
     * @since 1.0.0
     * @version 1.4.7
     * @return void
     */
    public function add_triggers_content() {
        $this->render_integration_trigger_content('woocommerce');
    }


    /**
     * Add WooCommerce placeholders on workflow builder
     * 
     * @since 1.0.0
     * @version 2.7.0
     * @param array $placeholders | Current placeholders
     * @param array $payload | Payload data
     * @return array
     */
    public function add_placeholders( $placeholders, $payload ) {
        $order = isset( $payload['order_id'] ) ? wc_get_order( $payload['order_id'] ) : null;
        $current_user = wp_get_current_user();
        $all_trigger_names = Triggers::get_trigger_names('woocommerce');
        $trigger_names = self::get_order_trigger_names();
        $download_triggers = self::get_download_trigger_names();
        $hook = is_array( $payload ) && isset( $payload['hook'] ) && is_string( $payload['hook'] ) ? $payload['hook'] : '';

        // only loaded for the triggers that use them, since every message resolves this whole list
        $customer = in_array( $hook, self::get_customer_trigger_names(), true ) ? self::get_payload_customer( $payload ) : null;
        $product = in_array( $hook, self::get_product_trigger_names(), true ) ? self::get_payload_product( $payload ) : null;

        // No first-party trigger puts a refund in order_id, but a third-party payload still can
        // and a refund exposes no billing data of its own, so resolve against the parent order.
        if ( $order instanceof \WC_Order_Refund ) {
            $order = wc_get_order( $order->get_parent_id() ); 
        }
    
        // add woocommerce placeholders
        $placeholders['woocommerce'] = array(
            '{{ wc_billing_first_name }}' => array(
                'triggers' => $trigger_names,
                'description' => __( 'To retrieve the customer billing first name from the WooCommerce order', 'joinotify' ),
                'replacement' => array(
                    'production' => $order ? $order->get_billing_first_name() : '',
                    'sandbox' => $current_user->exists() ? $current_user->first_name : esc_html__( 'John', 'joinotify' ),
                ),
            ),
            '{{ wc_billing_last_name }}' => array(
                'triggers' => $trigger_names,
                'description' => __( 'To retrieve the customer billing last name from the WooCommerce order', 'joinotify' ),
                'replacement' => array(
                    'production' => $order ? $order->get_billing_last_name() : '',
                    'sandbox' => $current_user->exists() ? $current_user->last_name : esc_html__( 'Doe', 'joinotify' ),
                ),
            ),
            '{{ wc_billing_email }}' => array(
                'triggers' => $trigger_names,
                'description' => __( 'To retrieve the customer billing email from the WooCommerce order', 'joinotify' ),
                'replacement' => array(
                    'production' => $order ?  $order->get_billing_email() : '',
                    'sandbox' => $current_user->exists() ? $current_user->user_email : esc_html__( 'user@example.com', 'joinotify' ),
                ),
            ),
            '{{ wc_billing_phone }}' => array(
                'triggers' => $trigger_names,
                'description' => __( 'To retrieve the billing phone from the WooCommerce order', 'joinotify' ),
                'replacement' => array(
                    'production' => $order ? $order->get_billing_phone() : '',
                    'sandbox' => __( '+55 11 91234-5678', 'joinotify' ),
                ),
            ),
            '{{ wc_shipping_phone }}' => array(
                'triggers' => $trigger_names,
                'description' => __( 'To retrieve the shipping phone from the WooCommerce order', 'joinotify' ),
                'replacement' => array(
                    'production' => $order ? $order->get_shipping_phone() : '',
                    'sandbox' => __( '+55 41 91234-5678', 'joinotify' ),
                ),
            ),
            '{{ wc_order_number }}' => array(
                'triggers' => $trigger_names,
                'description' => __( 'To retrieve the WooCommerce order number', 'joinotify' ),
                'replacement' => array(
                    'production' => $order ? $order->get_order_number() : '',
                    'sandbox' => __( '12345', 'joinotify' ),
                ),
            ),
            '{{ wc_order_status }}' => array(
                'triggers' => $trigger_names,
                'description' => __( 'To retrieve the WooCommerce order status', 'joinotify' ),
                'replacement' => array(
                    'production' => $order ?  wc_get_order_status_name( $order->get_status() ) : '',
                    'sandbox' => __( 'Completed', 'joinotify' ),
                ),
            ),
            '{{ wc_order_date }}' => array(
                'triggers' => $trigger_names,
                'description' => __( 'To retrieve the WooCommerce order date', 'joinotify' ),
                'replacement' => array(
                    'production' => $order ? date_i18n( get_option('date_format'), strtotime( $order->get_date_created() ) ) : '',
                    'sandbox' => wp_date( get_option('date_format') ),
                ),
            ),
            '{{ wc_billing_full_address }}' => array(
                'triggers' => $trigger_names,
                'description' => __( 'Retrieve the user\'s full billing address (format configurable in the WooCommerce options).', 'joinotify' ),
                'replacement' => array(
                    'production' => $order ? self::get_full_address( $order, 'billing' ) : '',
                    'sandbox' => __( 'Flower Street, 123 - Curitiba/PR - Brazil (ZIP: 80000-000)', 'joinotify' ),
                ),
            ),
            '{{ wc_shipping_full_address }}' => array(
                'triggers' => $trigger_names,
                'description' => __( 'Retrieve the user\'s full shipping address (format configurable in the WooCommerce options).', 'joinotify' ),
                'replacement' => array(
                    'production' => $order ? self::get_full_address( $order, 'shipping' ) : '',
                    'sandbox' => __( 'Daisy Avenue, 450 - Curitiba/PR - Brazil (ZIP: 80000-100)', 'joinotify' ),
                ),
            ),
            '{{ wc_purchased_items }}' => array(
                'triggers' => $trigger_names,
                'description' => __( 'To retrieve each purchased product and quantity from the order, separated by line', 'joinotify' ),
                'replacement' => array(
                    'production' => $order ? self::get_purchased_items( $order ) : '',
                    'sandbox' => sprintf( '%s %s %s', esc_html__( '1x - Men\'s cotton T-shirt (Sample product)', 'joinotify' ), "\n",  esc_html__( '1x - Sunglasses with UV protection (Sample product)', 'joinotify' ) ),
                ),
            ),
            '{{ wc_review_links }}' => array(
                'triggers' => $trigger_names,
                'description' => __( 'To retrieve the review link for each purchased product from the order, separated by line', 'joinotify' ),
                'replacement' => array(
                    'production' => $order ? self::get_review_links( $order ) : '',
                    'sandbox' => sprintf( '%s %s %s',
                        /* translators: %s: site URL */
                        sprintf( esc_html__( 'Men\'s cotton T-shirt (Sample product): %s/product/sample-t-shirt/#reviews', 'joinotify' ), get_site_url() ),
                        "\n",
                        /* translators: %s: site URL */
                        sprintf( esc_html__( 'Sunglasses with UV protection (Sample product): %s/product/sample-sunglasses/#reviews', 'joinotify' ), get_site_url() )
                    ),
                ),
            ),
            '{{ wc_first_product_review_path }}' => array(
                'triggers' => $trigger_names,
                'description' => __( 'To retrieve the review link of the first purchased product without the site address (e.g. product/slug/#reviews), to complete the link of a URL button', 'joinotify' ),
                'replacement' => array(
                    'production' => $order ? self::get_first_product_review_path( $order ) : '',
                    'sandbox' => 'product/sample-t-shirt/#reviews',
                ),
            ),
            '{{ wc_payment_url }}' => array(
                'triggers' => $trigger_names,
                'description' => __( 'To retrieve the order payment URL', 'joinotify' ),
                'replacement' => array(
                    'production' => $order ? $order->get_checkout_payment_url() : '',
                    /* translators: %s: site URL */
                    'sandbox' => sprintf( esc_html__( '%s/checkout/pay/order/12345', 'joinotify' ), get_site_url() ),
                ),
            ),
            '{{ wc_currency_symbol }}' => array(
                'triggers' => $trigger_names,
                'description' => __( 'To retrieve the order currency symbol', 'joinotify' ),
                'replacement' => array(
                    'production' => $order ? joinotify_format_plain_text( get_woocommerce_currency_symbol( $order->get_currency() ) ) : '',
                    'sandbox' => joinotify_format_plain_text( get_woocommerce_currency_symbol() ),
                ),
            ),
            '{{ wc_order_total }}' => array(
                'triggers' => $trigger_names,
                'description' => __( 'To retrieve the WooCommerce order total', 'joinotify' ),
                'replacement' => array(
                    'production' => $order ? joinotify_format_price( $order->get_total(), $order->get_currency() ) : '',
                    'sandbox' => joinotify_format_price( 150 ),
                ),
            ),
            '{{ wc_total_discount }}' => array(
                'triggers' => $trigger_names,
                'description' => __( 'To retrieve the WooCommerce total discount', 'joinotify' ),
                'replacement' => array(
                    'production' => $order ? joinotify_format_price( $order->get_total_discount(), $order->get_currency() ) : '',
                    'sandbox' => joinotify_format_price( 20 ),
                ),
            ),
            '{{ wc_total_tax }}' => array(
                'triggers' => $trigger_names,
                'description' => __( 'To retrieve the WooCommerce total tax', 'joinotify' ),
                'replacement' => array(
                    'production' => $order ? joinotify_format_price( $order->get_total_tax(), $order->get_currency() ) : '',
                    'sandbox' => joinotify_format_price( 15 ),
                ),
            ),
            '{{ wc_total_refunded }}' => array(
                'triggers' => $trigger_names,
                'description' => __( 'To retrieve the WooCommerce total refunded amount', 'joinotify' ),
                'replacement' => array(
                    'production' => $order ? joinotify_format_price( $order->get_total_refunded(), $order->get_currency() ) : '',
                    'sandbox' => joinotify_format_price( 10 ),
                ),
            ),
            '{{ wc_coupon_codes }}' => array(
                'triggers' => $trigger_names,
                'description' => __( 'To retrieve the coupon codes used in the WooCommerce order', 'joinotify' ),
                'replacement' => array(
                    'production' => $order ? implode(', ', $order->get_coupon_codes()) : '',
                    'sandbox' => __( 'CUPOM10, FREESHIPPING', 'joinotify' ),
                ),
            ),
            '{{ wc_payment_method_title }}' => array(
                'triggers' => $trigger_names,
                'description' => __( 'To retrieve the WooCommerce order payment method title', 'joinotify' ),
                'replacement' => array(
                    'production' => $order ? (function ( $order ) {
                        $id = $order->get_payment_method();
                        $gateways = WC()->payment_gateways()->payment_gateways();
                        
                        return isset( $gateways[ $id ] )
                            ? joinotify_format_plain_text( $gateways[ $id ]->get_title() )
                            : '';
                    })( $order ) : '',
                    'sandbox' => __( 'Credit card', 'joinotify' ),
                ),
            ),
            '{{ wc_shipping_method_total }}' => array(
                'triggers' => $trigger_names,
                'description' => __( 'To retrieve the shipping cost and method of the WooCommerce order, for example "$20.00 via Express shipping". Returns only the method name when the shipping is free.', 'joinotify' ),
                'replacement' => array(
                    'production' => $order ? self::get_shipping_method_total( $order ) : '',
                    'sandbox' => self::get_sample_shipping_method_total(),
                ),
            ),
            // Legacy name: it has always resolved to the shipping cost and method, never to the
            // address. The value is kept so saved workflows send what they always sent; the
            // description steers new mappings to the tokens that match what the name suggests.
            '{{ wc_shipping_address }}' => array(
                'triggers' => $trigger_names,
                /* translators: 1: shipping address placeholder, 2: shipping cost and method placeholder */
                'description' => sprintf( __( 'Legacy name kept for existing workflows: it returns the shipping cost and method, NOT the address. Use %1$s for the delivery address, or %2$s for the same value under a clearer name.', 'joinotify' ), '{{ wc_shipping_full_address }}', '{{ wc_shipping_method_total }}' ),
                'replacement' => array(
                    'production' => $order ? self::get_shipping_method_total( $order ) : '',
                    'sandbox' => self::get_sample_shipping_method_total(),
                ),
            ),
            '{{ wc_checkout_field=[FIELD_ID] }}' => array(
                'triggers' => $trigger_names,
                'description' => __( 'To retrieve the value of a specific checkout field in the WooCommerce order. Replace FIELD_ID with the checkout field ID, for example: billing_email.', 'joinotify' ),
                'replacement' => array(), // dynamic replacement is make on Placeholders::replace_placeholders()
            ),
            '{{ wc_downloadable_items }}' => array(
                'triggers' => $download_triggers,
                'description' => __( 'To retrieve the name of each downloadable product in the WooCommerce order, separated by line', 'joinotify' ),
                'replacement' => array(
                    'production' => $order ? self::get_downloadable_item_names( $order ) : '',
                    'sandbox' => sprintf( "%s\n%s", esc_html__( 'Complete e-book (Sample product)', 'joinotify' ), esc_html__( 'Video lessons pack (Sample product)', 'joinotify' ) ),
                ),
            ),
            '{{ wc_download_links }}' => array(
                'triggers' => $download_triggers,
                'description' => __( 'To retrieve the download name and link of each digital file in the WooCommerce order, separated by line. Only available after the download access has been granted.', 'joinotify' ),
                'replacement' => array(
                    'production' => $order ? self::get_download_links( $order ) : '',
                    'sandbox' => sprintf( "%s: %s\n%s: %s",
                        esc_html__( 'Complete e-book (Sample product)', 'joinotify' ),
                        get_site_url() . '/?download_file=123&order=wc_order_sample&key=sample',
                        esc_html__( 'Video lessons pack (Sample product)', 'joinotify' ),
                        get_site_url() . '/?download_file=456&order=wc_order_sample&key=sample'
                    ),
                ),
            ),
            '{{ wc_download_urls }}' => array(
                'triggers' => $download_triggers,
                'description' => __( 'To retrieve only the download links of the WooCommerce order, without the file name, separated by line', 'joinotify' ),
                'replacement' => array(
                    'production' => $order ? self::get_download_urls( $order ) : '',
                    'sandbox' => sprintf( "%s\n%s",
                        get_site_url() . '/?download_file=123&order=wc_order_sample&key=sample',
                        get_site_url() . '/?download_file=456&order=wc_order_sample&key=sample'
                    ),
                ),
            ),
            '{{ wc_downloads_expiry }}' => array(
                'triggers' => $download_triggers,
                'description' => __( 'To retrieve the expiration date of the download access. Returns the closest date when the files expire on different dates.', 'joinotify' ),
                'replacement' => array(
                    'production' => $order ? self::get_downloads_expiry( $order ) : '',
                    'sandbox' => esc_html__( 'Never expires', 'joinotify' ),
                ),
            ),
            '{{ wc_downloads_remaining }}' => array(
                'triggers' => $download_triggers,
                'description' => __( 'To retrieve how many downloads the customer still has available. Returns the smallest amount when the files have different limits.', 'joinotify' ),
                'replacement' => array(
                    'production' => $order ? self::get_downloads_remaining( $order ) : '',
                    'sandbox' => esc_html__( 'Unlimited', 'joinotify' ),
                ),
            ),
            '{{ wc_account_downloads_url }}' => array(
                'triggers' => $trigger_names,
                'description' => __( 'To retrieve the link to the downloads area in the customer My Account page', 'joinotify' ),
                'replacement' => array(
                    'production' => self::get_account_downloads_url(),
                    'sandbox' => self::get_account_downloads_url(),
                ),
            ),
            '{{ wc_download_link=[PRODUCT_ID] }}' => array(
                'triggers' => $download_triggers,
                'description' => __( 'To retrieve the download links of a specific product in the WooCommerce order. Replace PRODUCT_ID with the product ID, for example: 128.', 'joinotify' ),
                'replacement' => array(), // dynamic replacement is make on Placeholders::replace_placeholders()
            ),
        );

        $customer_triggers = self::get_customer_trigger_names();
        $product_triggers = self::get_product_trigger_names();
        $guest_email = isset( $payload['user_email'] ) && is_scalar( $payload['user_email'] ) ? (string) $payload['user_email'] : '';

        // placeholders of the triggers that carry a customer and no order
        $placeholders['woocommerce'] += array(
            '{{ wc_customer_first_name }}' => array(
                'triggers' => $customer_triggers,
                'description' => __( 'To retrieve the first name of the WooCommerce customer', 'joinotify' ),
                'replacement' => array(
                    'production' => $customer ? ( $customer->get_billing_first_name() ?: $customer->get_first_name() ) : '',
                    'sandbox' => esc_html__( 'John', 'joinotify' ),
                ),
            ),
            '{{ wc_customer_last_name }}' => array(
                'triggers' => $customer_triggers,
                'description' => __( 'To retrieve the last name of the WooCommerce customer', 'joinotify' ),
                'replacement' => array(
                    'production' => $customer ? ( $customer->get_billing_last_name() ?: $customer->get_last_name() ) : '',
                    'sandbox' => esc_html__( 'Doe', 'joinotify' ),
                ),
            ),
            '{{ wc_customer_email }}' => array(
                'triggers' => $customer_triggers,
                'description' => __( 'To retrieve the email of the WooCommerce customer', 'joinotify' ),
                'replacement' => array(
                    'production' => $customer ? $customer->get_email() : $guest_email,
                    'sandbox' => esc_html__( 'user@example.com', 'joinotify' ),
                ),
            ),
            '{{ wc_customer_phone }}' => array(
                'triggers' => $customer_triggers,
                'description' => __( 'To retrieve the billing phone of the WooCommerce customer account. Empty when the account has no billing phone yet.', 'joinotify' ),
                'replacement' => array(
                    'production' => $customer ? $customer->get_billing_phone() : '',
                    'sandbox' => __( '+55 11 91234-5678', 'joinotify' ),
                ),
            ),
            '{{ wc_my_account_url }}' => array(
                'triggers' => $customer_triggers,
                'description' => __( 'To retrieve the link to the customer My Account page', 'joinotify' ),
                'replacement' => array(
                    'production' => self::get_my_account_url(),
                    'sandbox' => self::get_my_account_url(),
                ),
            ),
            '{{ wc_product_name }}' => array(
                'triggers' => $product_triggers,
                'description' => __( 'To retrieve the name of the WooCommerce product, with the attributes of a variation', 'joinotify' ),
                'replacement' => array(
                    'production' => $product ? wp_strip_all_tags( $product->get_name() ) : '',
                    'sandbox' => __( 'Example product', 'joinotify' ),
                ),
            ),
            '{{ wc_product_url }}' => array(
                'triggers' => $product_triggers,
                'description' => __( 'To retrieve the link to the WooCommerce product page', 'joinotify' ),
                'replacement' => array(
                    'production' => $product ? $product->get_permalink() : '',
                    'sandbox' => get_site_url() . '/product/example-product/',
                ),
            ),
            '{{ wc_product_sku }}' => array(
                'triggers' => $product_triggers,
                'description' => __( 'To retrieve the SKU of the WooCommerce product', 'joinotify' ),
                'replacement' => array(
                    'production' => $product ? $product->get_sku() : '',
                    'sandbox' => 'SKU-001',
                ),
            ),
            '{{ wc_product_stock_quantity }}' => array(
                'triggers' => $product_triggers,
                'description' => __( 'To retrieve the stock quantity of the WooCommerce product. Empty when the product does not manage its stock.', 'joinotify' ),
                'replacement' => array(
                    'production' => $product ? self::get_product_stock_quantity( $product ) : '',
                    'sandbox' => '3',
                ),
            ),
        );

        // check if the payload has a 'settings' key
        if ( isset( $payload['settings'] ) ) {
            $coupon_settings = $payload['settings'];
            $coupon_placeholders = self::get_coupon_placeholders( $coupon_settings );
    
            // iterate over the coupon placeholders and add them to the placeholders array
            foreach ( $coupon_placeholders as $placeholder_key => $placeholder_data ) {
                $placeholders['woocommerce'][$placeholder_key] = array(
                    'triggers' => $all_trigger_names,
                    'description' => $placeholder_data['description'],
                    'replacement' => array(
                        'production' => $placeholder_data['replacement'],
                        'sandbox' => $placeholder_data['replacement'],
                    ),
                );
            }
        }

        return $placeholders;
    }


    /**
     * Triggers where the download permissions already exist on the order
     *
     * WooCommerce only grants the download access inside wc_downloadable_product_permissions(),
     * which runs on the "completed" and "processing" order statuses. Before that the download
     * placeholders would resolve to an empty string, so they are only offered on the triggers
     * that run after the access has been granted.
     *
     * @since 2.1.0
     * @return array<int,string>
     */
    public static function get_download_trigger_names() {
        return apply_filters( 'Joinotify/Integrations/Woocommerce/Download_Triggers', array(
            'woocommerce_grant_product_download_permissions',
            'woocommerce_download_product',
            'woocommerce_order_status_completed',
            'woocommerce_payment_complete',
            'woocommerce_order_status_changed',
        ));
    }


    /**
     * Resolve the customer a customer-trigger payload is about.
     *
     * Reads the account of the payload user ID and, for a back in stock notice signed up
     * without being logged in, the account registered with the notice email, if any.
     *
     * @since 2.7.0
     * @param array $payload | Runtime trigger payload
     * @return \WC_Customer|null
     */
    public static function get_payload_customer( $payload ) {
        if ( ! is_array( $payload ) || ! class_exists('WC_Customer') ) {
            return null;
        }

        $user_id = isset( $payload['user_id'] ) ? absint( $payload['user_id'] ) : 0;

        if ( ! $user_id && ! empty( $payload['user_email'] ) && is_string( $payload['user_email'] ) ) {
            $user = get_user_by( 'email', $payload['user_email'] );
            $user_id = $user ? (int) $user->ID : 0;
        }

        if ( ! $user_id ) {
            return null;
        }

        try {
            $customer = new WC_Customer( $user_id );
        } catch ( \Exception $e ) {
            return null;
        }

        return $customer->get_id() ? $customer : null;
    }


    /**
     * Resolve the product a product-trigger payload is about.
     *
     * @since 2.7.0
     * @param array $payload | Runtime trigger payload
     * @return \WC_Product|null
     */
    public static function get_payload_product( $payload ) {
        if ( ! is_array( $payload ) || empty( $payload['product_id'] ) || ! function_exists('wc_get_product') ) {
            return null;
        }

        $product = wc_get_product( absint( $payload['product_id'] ) );

        return $product instanceof \WC_Product ? $product : null;
    }


    /**
     * Stock quantity of a product as text, empty when it does not manage its stock.
     *
     * @since 2.7.0
     * @param \WC_Product $product | Product
     * @return string
     */
    public static function get_product_stock_quantity( $product ) {
        $quantity = $product->get_stock_quantity();

        return null === $quantity ? '' : (string) wc_stock_amount( $quantity );
    }


    /**
     * Returns the link to the customer My Account page
     *
     * @since 2.7.0
     * @return string
     */
    public static function get_my_account_url() {
        if ( ! function_exists('wc_get_page_permalink') ) {
            return '';
        }

        return (string) wc_get_page_permalink('myaccount');
    }


    /**
     * Returns the link to the downloads area of the customer My Account page
     *
     * @since 2.1.0
     * @return string
     */
    public static function get_account_downloads_url() {
        if ( ! function_exists('wc_get_account_endpoint_url') ) {
            return '';
        }

        return wc_get_account_endpoint_url('downloads');
    }


    /**
     * Returns the downloadable files granted for the order
     *
     * Wraps WC_Order::get_downloadable_items(), which already skips fully refunded items and
     * returns both the permission URL and the underlying file for each granted download.
     *
     * @since 2.1.0
     * @param \WC_Order $order | The object of the request
     * @return array<int,array<string,mixed>>
     */
    public static function get_downloadable_items( $order ) {
        if ( ! $order || ! method_exists( $order, 'get_downloadable_items' ) ) {
            return array();
        }

        $items = $order->get_downloadable_items();

        return is_array( $items ) ? $items : array();
    }


    /**
     * Returns the name of each downloadable product in the order
     *
     * @since 2.1.0
     * @param \WC_Order $order | The object of the request
     * @return string Product names formatted on separate lines
     */
    public static function get_downloadable_item_names( $order ) {
        $names = array();

        foreach ( self::get_downloadable_items( $order ) as $item ) {
            $product_name = isset( $item['product_name'] ) ? (string) $item['product_name'] : '';

            // one line per product, even when the product ships several files
            if ( '' !== $product_name && ! in_array( $product_name, $names, true ) ) {
                $names[] = $product_name;
            }
        }

        return ! empty( $names ) ? implode( "\n", $names ) : esc_html__( 'No downloadable item in this order.', 'joinotify' );
    }


    /**
     * Returns the name and permission link of each downloadable file in the order
     *
     * @since 2.1.0
     * @param \WC_Order $order | The object of the request
     * @return string Download links formatted on separate lines
     */
    public static function get_download_links( $order ) {
        $links = array();

        foreach ( self::get_downloadable_items( $order ) as $item ) {
            $url = isset( $item['download_url'] ) ? (string) $item['download_url'] : '';

            if ( '' === $url ) {
                continue;
            }

            $label = isset( $item['download_name'] ) && '' !== (string) $item['download_name']
                ? (string) $item['download_name']
                : (string) ( $item['product_name'] ?? '' );

            $links[] = '' !== $label ? sprintf( '%s: %s', $label, $url ) : $url;
        }

        return ! empty( $links ) ? implode( "\n", $links ) : esc_html__( 'No download available for this order.', 'joinotify' );
    }


    /**
     * Returns only the permission links of the downloadable files in the order
     *
     * @since 2.1.0
     * @param \WC_Order $order | The object of the request
     * @return string Download URLs formatted on separate lines
     */
    public static function get_download_urls( $order ) {
        $urls = array();

        foreach ( self::get_downloadable_items( $order ) as $item ) {
            $url = isset( $item['download_url'] ) ? (string) $item['download_url'] : '';

            if ( '' !== $url && ! in_array( $url, $urls, true ) ) {
                $urls[] = $url;
            }
        }

        return ! empty( $urls ) ? implode( "\n", $urls ) : esc_html__( 'No download available for this order.', 'joinotify' );
    }


    /**
     * Returns the permission links of a specific product in the order
     *
     * @since 2.1.0
     * @param \WC_Order $order | The object of the request
     * @param int $product_id | Product ID to filter the downloads
     * @return string Download URLs formatted on separate lines, empty when the product has no download
     */
    public static function get_download_links_by_product( $order, $product_id ) {
        $product_id = absint( $product_id );
        $urls = array();

        if ( ! $product_id ) {
            return '';
        }

        foreach ( self::get_downloadable_items( $order ) as $item ) {
            if ( absint( $item['product_id'] ?? 0 ) !== $product_id ) {
                continue;
            }

            $url = isset( $item['download_url'] ) ? (string) $item['download_url'] : '';

            if ( '' !== $url && ! in_array( $url, $urls, true ) ) {
                $urls[] = $url;
            }
        }

        return implode( "\n", $urls );
    }


    /**
     * Returns the expiration date of the download access
     *
     * @since 2.1.0
     * @param \WC_Order $order | The object of the request
     * @return string Closest expiration date, or a "never expires" label
     */
    public static function get_downloads_expiry( $order ) {
        $expires_at = null;

        foreach ( self::get_downloadable_items( $order ) as $item ) {
            $access_expires = $item['access_expires'] ?? null;

            // a file without expiration date never limits the access of the others
            if ( empty( $access_expires ) ) {
                continue;
            }

            $timestamp = $access_expires instanceof \WC_DateTime
                ? $access_expires->getTimestamp()
                : strtotime( (string) $access_expires );

            if ( ! $timestamp ) {
                continue;
            }

            // keep the closest date so the customer is told the earliest deadline
            if ( null === $expires_at || $timestamp < $expires_at ) {
                $expires_at = $timestamp;
            }
        }

        return null !== $expires_at
            ? date_i18n( get_option('date_format'), $expires_at )
            : esc_html__( 'Never expires', 'joinotify' );
    }


    /**
     * Returns how many downloads the customer still has available
     *
     * @since 2.1.0
     * @param \WC_Order $order | The object of the request
     * @return string Smallest remaining amount, or an "unlimited" label
     */
    public static function get_downloads_remaining( $order ) {
        $remaining = null;

        foreach ( self::get_downloadable_items( $order ) as $item ) {
            $downloads_remaining = $item['downloads_remaining'] ?? '';

            // WooCommerce stores an empty string when the download has no limit
            if ( '' === $downloads_remaining || null === $downloads_remaining ) {
                continue;
            }

            $value = absint( $downloads_remaining );

            // keep the smallest amount so the customer is told the tightest limit
            if ( null === $remaining || $value < $remaining ) {
                $remaining = $value;
            }
        }

        return null !== $remaining ? (string) $remaining : esc_html__( 'Unlimited', 'joinotify' );
    }


    /**
     * Returns the items purchased in the order formatted
     *
     * @since 1.0.0
     * @version 1.4.7
     * @param \WC_Order $order | The object of the request
     * @return string Items formatted on separate lines
     */
    public static function get_purchased_items( $order ) {
        $items = $order->get_items();
        $purchased_items = array();

        foreach ( $items as $item ) {
            $product_name = $item->get_name();
            $variation_details = '';
            $product = $item->get_product();
            
            if ( $product && $product->is_type('variation') ) {
                $variation_attributes = $product->get_variation_attributes();
                $variation_details = ' (' . implode( ', ', $variation_attributes ) . ')';
            }

            $purchased_items[] = sprintf( '%dx - %s%s', $item->get_quantity(), $product_name, $variation_details );
        }

        return ! empty( $purchased_items ) ? implode( "\n", $purchased_items ) : esc_html__( 'No item purchased.', 'joinotify' );
    }


    /**
     * Returns the review link for each purchased product in the order
     *
     * @since 2.0.0
     * @version 2.6.1
     * @param \WC_Order $order | The object of the request
     * @return string Review links formatted on separate lines
     */
    public static function get_review_links( $order ) {
        $review_links = array();

        foreach ( self::get_reviewable_products( $order ) as $product_id => $product ) {
            $review_url = get_permalink( $product_id ) . '#reviews';
            $review_links[] = sprintf( '%s: %s', $product->get_name(), $review_url );
        }

        return ! empty( $review_links ) ? implode( "\n", $review_links ) : esc_html__( 'No item purchased.', 'joinotify' );
    }


    /**
     * Returns the review link of the first purchased product, relative to the site address
     *
     * Meant to complete a URL button whose base is the site address, e.g.
     * "product/shirt/#reviews". The first product that accepts reviews wins; when none
     * does, the first purchased product that still exists is used anyway.
     *
     * @since 2.6.1
     * @param \WC_Order $order | The object of the request
     * @return string Relative review link, or empty when no purchased product exists
     */
    public static function get_first_product_review_path( $order ) {
        $product_id = array_key_first( self::get_reviewable_products( $order ) );

        if ( ! $product_id ) {
            foreach ( $order->get_items() as $item ) {
                $candidate = $item->get_product_id();

                if ( $candidate && wc_get_product( $candidate ) ) {
                    $product_id = $candidate;
                    break;
                }
            }
        }

        if ( ! $product_id ) {
            return '';
        }

        return Placeholders::strip_home_url( get_permalink( $product_id ) . '#reviews' );
    }


    /**
     * Returns the purchased products whose review page a customer can be sent to
     *
     * Variations count as their parent product, each product appears once, and
     * products that no longer exist or don't accept reviews are left out.
     *
     * @since 2.6.1
     * @param \WC_Order $order | The object of the request
     * @return array Map of product ID => \WC_Product, in the order of the items
     */
    public static function get_reviewable_products( $order ) {
        $products = array();
        $processed = array();

        foreach ( $order->get_items() as $item ) {
            // use the parent product id so variations point to the same review page
            $product_id = $item->get_product_id();

            // skip empty or already processed products to avoid duplicate links
            if ( ! $product_id || in_array( $product_id, $processed, true ) ) {
                continue;
            }

            $processed[] = $product_id;

            $product = wc_get_product( $product_id );

            // skip products that no longer exist or don't accept reviews
            if ( ! $product || ! comments_open( $product_id ) ) {
                continue;
            }

            $products[ $product_id ] = $product;
        }

        return $products;
    }


    /**
     * Get the full address of the order
     *
     * @since 1.0.0
     * @version 1.4.7
     * @param \WC_Order $order | WooCommerce Order object
     * @return string Full address as a formatted string
     */
    public static function get_full_address( $order, $type = 'billing' ) {
        $format_setting = 'billing' === $type ? 'woocommerce_billing_full_address_format' : 'woocommerce_shipping_full_address_format';
        $format = Admin::get_setting( $format_setting );
        $address_data = self::get_address_data( $order, $type );

        if ( ! empty( $format ) ) {
            return trim( self::format_address_template( $format, $address_data, $order, $type ) );
        }

        $address = array(
            $address_data['address_1'],
            $address_data['address_2'],
            $address_data['city'],
            $address_data['state'],
            $address_data['postcode'],
            $address_data['country'],
        );

        // Filter out empty values and join with a comma.
        return implode( ', ', array_filter( $address ) );
    }


    /**
     * Get the shipping cost and method of the order as plain text
     *
     * Wraps WC_Order::get_shipping_to_display(), which returns price markup with the cost and
     * method (e.g. "R$ 20,00 via SEDEX"), only the method name when the shipping is free, or
     * "Free!" when the order has no shipping method. It never returns the address — that is
     * get_full_address( $order, 'shipping' ).
     *
     * @since 2.4.2
     * @param \WC_Order $order | WooCommerce Order object
     * @return string
     */
    public static function get_shipping_method_total( $order ) {
        return trim( joinotify_format_plain_text( $order->get_shipping_to_display() ) );
    }


    /**
     * Get the sample shipping cost and method shown by the builder preview and test sends
     *
     * @since 2.4.2
     * @return string
     */
    protected static function get_sample_shipping_method_total() {
        /* translators: %s: sample shipping cost, already formatted with the store currency */
        return sprintf( __( '%s via Express shipping', 'joinotify' ), joinotify_format_price( 20 ) );
    }


    /**
     * Get address data for placeholder replacement (with custom fields support)
     *
     * @since 1.4.5
     * @param \WC_Order $order | WooCommerce Order object
     * @param string   $type  | Address type (billing or shipping)
     * @return array
     */
    protected static function get_address_data( $order, $type ) {
        $data = array(
            'first_name' => $order->{"get_{$type}_first_name"}(),
            'last_name'  => $order->{"get_{$type}_last_name"}(),
            'company'    => $order->{"get_{$type}_company"}(),
            'address_1'  => $order->{"get_{$type}_address_1"}(),
            'address_2'  => $order->{"get_{$type}_address_2"}(),
            'city'       => $order->{"get_{$type}_city"}(),
            'state'      => $order->{"get_{$type}_state"}(),
            'postcode'   => $order->{"get_{$type}_postcode"}(),
            'country'    => $order->{"get_{$type}_country"}(),
        );

        if ( 'billing' === $type ) {
            $data['email'] = $order->get_billing_email();
            $data['phone'] = $order->get_billing_phone();
        }

        if ( method_exists( $order, "get_{$type}_phone" ) ) {
            $data['phone'] = $order->{"get_{$type}_phone"}();
        }

        // Include custom checkout fields (billing/shipping) as placeholders without prefix.
        $fields = self::export_checkout_fields( $type );

        if ( ! empty( $fields ) && is_array( $fields ) ) {
            foreach ( $fields as $field_id => $field ) {
                $field_id = is_string( $field_id ) ? $field_id : '';
                $placeholder_id = is_string( $field_id ) ? $field_id : '';

                if ( 'billing' === $type && strpos( $field_id, 'billing_' ) === 0 ) {
                    $placeholder_id = substr( $field_id, 8 );
                } elseif ( 'shipping' === $type && strpos( $field_id, 'shipping_' ) === 0 ) {
                    $placeholder_id = substr( $field_id, 9 );
                }

                // Don’t overwrite core keys already set.
                if ( isset( $data[ $placeholder_id ] ) && $data[ $placeholder_id ] !== '' ) {
                    continue;
                }

                $value = self::get_order_meta_fallback_value( $order, array(
                    // Most common:
                    "{$type}_{$placeholder_id}",      // billing_number
                    $field_id,                        // billing_number (original)
                    $placeholder_id                   // number
                ) );

                if ( $value !== '' ) {
                    $data[ $placeholder_id ] = $value;
                }
            }
        }

        /**
         * Filter address data before replace.
         *
         * @since 1.4.5
         * @param array    $data
         * @param \WC_Order $order
         * @param string   $type
         */
        return apply_filters( 'Joinotify/WooCommerce/Address_Data', $data, $order, $type );
    }


    /**
     * Get value for internal WooCommerce meta key using official order getters.
     *
     * @since 1.4.5
     * @param \WC_Order $order | Order object
     * @param string    $meta_key | Meta key
     * @return string
     */
    protected static function get_internal_order_value_by_meta_key( $order, $meta_key ) {
        $meta_key = (string) $meta_key;

        $internal_map = array(
            '_billing_first_name' => 'get_billing_first_name',
            '_billing_last_name' => 'get_billing_last_name',
            '_billing_company' => 'get_billing_company',
            '_billing_address_1' => 'get_billing_address_1',
            '_billing_address_2' => 'get_billing_address_2',
            '_billing_city' => 'get_billing_city',
            '_billing_state' => 'get_billing_state',
            '_billing_postcode' => 'get_billing_postcode',
            '_billing_country' => 'get_billing_country',
            '_billing_email' => 'get_billing_email',
            '_billing_phone' => 'get_billing_phone',
            '_shipping_first_name' => 'get_shipping_first_name',
            '_shipping_last_name' => 'get_shipping_last_name',
            '_shipping_company' => 'get_shipping_company',
            '_shipping_address_1' => 'get_shipping_address_1',
            '_shipping_address_2' => 'get_shipping_address_2',
            '_shipping_city' => 'get_shipping_city',
            '_shipping_state' => 'get_shipping_state',
            '_shipping_postcode' => 'get_shipping_postcode',
            '_shipping_country' => 'get_shipping_country',
            '_shipping_phone' => 'get_shipping_phone',
        );

        if ( ! isset( $internal_map[ $meta_key ] ) ) {
            return '';
        }

        $getter = $internal_map[ $meta_key ];

        if ( ! method_exists( $order, $getter ) ) {
            return '';
        }

        $value = $order->{$getter}();

        if ( is_array( $value ) ) {
            $value = implode( ', ', array_filter( array_map( 'strval', $value ) ) );
        } elseif ( is_object( $value ) ) {
            $value = method_exists( $value, '__toString' ) ? (string) $value : '';
        } else {
            $value = (string) $value;
        }

        return trim( wp_strip_all_tags( $value ) );
    }


    /**
     * Get meta value trying multiple keys (supports arrays and objects).
     *
     * @since 1.4.5
     * @param \WC_Order $order | Order object
     * @param array     $keys
     * @return string
     */
    protected static function get_order_meta_fallback_value( $order, $keys = array() ) {
        foreach ( (array) $keys as $meta_key ) {
            if ( empty( $meta_key ) ) {
                continue;
            }

            $meta_key = (string) $meta_key;
            $value = self::get_internal_order_value_by_meta_key( $order, $meta_key );

            if ( '' === $value ) {
                $value = $order->get_meta( $meta_key, true );

                if ( is_array( $value ) ) {
                    $value = implode( ', ', array_filter( array_map( 'strval', $value ) ) );
                } elseif ( is_object( $value ) ) {
                    $value = method_exists( $value, '__toString' ) ? (string) $value : '';
                } else {
                    $value = (string) $value;
                }

                $value = trim( wp_strip_all_tags( $value ) );
            }

            if ( '' !== $value ) {
                return $value;
            }
        }

        return '';
    }


    /**
     * Format address template with order data (now uses $address_data only).
     *
     * @since 1.4.5
     * @param string    $format
     * @param array     $address_data
     * @param \WC_Order $order
     * @param string    $type
     * @return string
     */
    protected static function format_address_template( $format, $address_data, $order, $type ) {
        return preg_replace_callback( '/\{\{\s*([a-zA-Z0-9_\-]+)\s*\}\}/', function( $matches ) use ( $address_data ) {
            $key = $matches[1];

            return isset( $address_data[ $key ] ) ? (string) $address_data[ $key ] : '';
        }, $format );
    }


    /**
     * Register the "WooCommerce" category on the builder actions library
     *
     * @since 1.4.7
     * @param array $categories | Current categories
     * @return array
     */
    public function add_woocommerce_category( $categories ) {
        $categories[] = array(
            'id' => 'woocommerce',
            'label' => __( 'WooCommerce', 'joinotify' ),
            'icon' => '<svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" fill="currentColor"><path d="M7 18c-1.105 0-2 .895-2 2s.895 2 2 2 2-.895 2-2-.895-2-2-2zm10 0c-1.105 0-2 .895-2 2s.895 2 2 2 2-.895 2-2-.895-2-2-2zM7.334 14h9.591c.748 0 1.404-.494 1.611-1.213l1.917-6.708A.5.5 0 0 0 19.96 5.4H6.21l-.94-2.342A1 1 0 0 0 4.34 2.4H2v2h1.66l3.6 8.99-1.35 2.448C5.16 17.227 5.99 18.6 7.334 18.6H19v-2H7.334l1.1-2z"></path></svg>',
            'priority' => 30,
        );

        return $categories;
    }


    /**
     * Add coupon action in sidebar list on builder
     *
     * @since 1.1.0
     * @param array $actions | Current actions
     * @return array
     */
    public function add_coupon_action( $actions ) {
        $actions[] = array(
            'action' => 'create_coupon',
            'title' => __( 'Discount coupon', 'joinotify' ),
            'description' => __( 'Send a discount coupon to your user via WhatsApp text message.', 'joinotify' ),
            'context' => array(
                'woocommerce',
            ),
            'category' => 'woocommerce',
            'icon' => '<svg class="icon icon-lg icon-dark coupon" viewBox="0 0 24 24" fill="none" xmlns="http://www.w3.org/2000/svg"><g stroke-width="0"></g><g stroke-linecap="round" stroke-linejoin="round"></g><g><path fill-rule="evenodd" clip-rule="evenodd" d="M3.75 6.75L4.5 6H20.25L21 6.75V10.7812H20.25C19.5769 10.7812 19.0312 11.3269 19.0312 12C19.0312 12.6731 19.5769 13.2188 20.25 13.2188H21V17.25L20.25 18L4.5 18L3.75 17.25V13.2188H4.5C5.1731 13.2188 5.71875 12.6731 5.71875 12C5.71875 11.3269 5.1731 10.7812 4.5 10.7812H3.75V6.75ZM5.25 7.5V9.38602C6.38677 9.71157 7.21875 10.7586 7.21875 12C7.21875 13.2414 6.38677 14.2884 5.25 14.614V16.5L9 16.5L9 7.5H5.25ZM10.5 7.5V16.5L19.5 16.5V14.614C18.3632 14.2884 17.5312 13.2414 17.5312 12C17.5312 10.7586 18.3632 9.71157 19.5 9.38602V7.5H10.5Z"></path></g></svg>',
            'external_icon' => false,
            'has_settings' => true,
            'priority' => 60,
        );

        return $actions;
    }


    /**
     * Generate WooCommerce discount coupon
     *
     * @since 1.1.0
     * @version 1.4.7
     * @param array $coupon_data | Coupon settings data
     * @return mixed int|WP_Error Coupon ID or error
     */
    public static function generate_wc_coupon( $coupon_data ) {
        if ( empty( $coupon_data ) || ! isset( $coupon_data['discount_type'], $coupon_data['coupon_amount'] ) ) {
            Logger::register_log( 'Coupon data is empty or missing required fields.' );

            return new WP_Error( 'missing_data', __( 'Insufficient data to create the coupon.', 'joinotify' ) );
        }

        // Get prefix
        $coupon_prefix = Admin::get_setting('create_coupon_prefix');

        // Set coupon code
        $coupon_code = ( isset( $coupon_data['generate_coupon'] ) && $coupon_data['generate_coupon'] === 'yes' )
            ? $coupon_prefix . strtoupper( wp_generate_password( 6, false ) )
            : $coupon_data['coupon_code'];

        // Check if coupon already exists
        $query = new WP_Query( array(
            'post_type' => 'shop_coupon',
            'title' => $coupon_code,
            'post_status' => 'publish',
            'posts_per_page' => 1,
            'fields' => 'ids',
        ));

        if ( $query->have_posts() ) {
            Logger::register_log( 'Coupon already exists.' );
            
            return new WP_Error( 'duplicate_coupon', __( 'The coupon already exists.', 'joinotify' ) );
        }

        // reset query
        wp_reset_postdata();

        // Create coupon
        $coupon = new WC_Coupon();
        $coupon->set_code( $coupon_code );
        $coupon->set_description( isset( $coupon_data['coupon_description'] ) ? $coupon_data['coupon_description'] : '' );

        // General settings
        $coupon->set_discount_type( $coupon_data['discount_type'] ); // Discount type (fixed_cart, percent, fixed_product)
        $coupon->set_amount( floatval( $coupon_data['coupon_amount'] ) ); // Discount value
        $coupon->set_free_shipping( isset( $coupon_data['free_shipping'] ) && $coupon_data['free_shipping'] === 'yes' );

        // Set coupon expiration
        $original_expiry_date = null;
        $expiry_date = null;

        if ( isset( $coupon_data['coupon_expiry'] ) && $coupon_data['coupon_expiry'] === 'yes' ) {
            if ( isset( $coupon_data['expiry_data']['type'] ) ) {
                if ( $coupon_data['expiry_data']['type'] === 'date' ) {
                    // Specific date
                    $date = $coupon_data['expiry_data']['date_value'];
                    $time = $coupon_data['expiry_data']['time_value'];

                    if ( ! empty( $date ) ) {
                        $original_expiry_date = ! empty( $time ) ? strtotime( $date . ' ' . $time ) : strtotime( $date );
                    }
                } elseif ( $coupon_data['expiry_data']['type'] === 'period' ) {
                    // Delay expiration
                    $delay_value = isset( $coupon_data['expiry_data']['delay_value'] ) ? intval( $coupon_data['expiry_data']['delay_value'] ) : 0;
                    $delay_period = isset( $coupon_data['expiry_data']['delay_period'] ) ? $coupon_data['expiry_data']['delay_period'] : '';
                    
                    if ( $delay_value > 0 && ! empty( $delay_period ) ) {
                        $original_expiry_date = time() + Schedule::get_delay_timestamp( $delay_value, $delay_period );
                    }
                }
            }

            // Define o expiry_date como a data original primeiro
            $expiry_date = $original_expiry_date;

            // Se a expiração for menor que 24 horas, ajusta para o início do próximo dia (23:59:59)
            if ( $expiry_date && ( $expiry_date - time() ) < DAY_IN_SECONDS ) {
                $expiry_date = strtotime( 'tomorrow 23:59:59' ); // Fim do dia seguinte
            }

            if ( $expiry_date ) {
                $coupon->set_date_expires( $expiry_date );
            }
        }

        // Save coupon
        $coupon->save();

        // Schedule an event to update expiration to the previous day usando a **data original**
        if ( ! empty( $original_expiry_date ) ) {
            wp_schedule_single_event( $original_expiry_date, 'joinotify_update_coupon_expiration', array( $coupon->get_id() ) );
        }

        return array(
            'coupon_id' => $coupon->get_id(),
            'coupon_code' => $coupon_code,
        );
    }


    /**
     * Get the list of placeholders for the coupon
     * 
     * @since 1.1.0
     * @param array $settings | The settings for the coupon
     * @return array The list of placeholders
     */
    public static function get_coupon_placeholders( $settings = array() ) {
        // get coupon code
        $coupon_code = isset( $settings['coupon_code'] ) ? $settings['coupon_code'] : '';
        $formatted_discount = '';
        $formatted_expires = '';

        // format the discount amount
        if ( isset( $settings['discount_type'] ) && $settings['discount_type'] === 'percent' ) {
            $discount_amount = isset( $settings['coupon_amount'] ) ? $settings['coupon_amount'] : '0';

            /* translators: %s: percentage discount amount */
            $formatted_discount = sprintf( __( '%s%%', 'joinotify' ), $discount_amount );
        } elseif ( isset( $settings['discount_type'] ) && $settings['discount_type'] === 'fixed_cart' ) {
            $discount = floatval( $settings['coupon_amount'] );
            $formatted_discount = joinotify_format_price( $discount );
        }

        // add coupon expires replacement
        if ( isset( $settings['coupon_expiry'] ) && $settings['coupon_expiry'] === 'yes' ) {
            if ( $settings['expiry_data']['type'] === 'period' ) {
                $time_value = $settings['expiry_data']['delay_value'] ?? '';
                $time_unit = $settings['expiry_data']['delay_period'] ?? '';

                // Format time unit: singular/plural
                $formatted_time = ( $time_value > 1 ) ? Helpers::format_time_unit( $time_unit, true ) : Helpers::format_time_unit( $time_unit, false );
                /* translators: 1: delay amount, 2: time unit (minutes, hours, days) */
                $formatted_expires = sprintf( esc_html__( 'Expires in %1$s %2$s', 'joinotify' ), $time_value, $formatted_time );
            } elseif ( $settings['expiry_data']['type'] === 'date' ) {
                $date_value = $settings['expiry_data']['date_value'] ?? '';
                $time_value = $settings['expiry_data']['time_value'] ?? '';

                if ( ! empty( $time_value ) ) {
                    /* translators: 1: date, 2: time of day */
                    $formatted_expires = sprintf( esc_html__( 'Expires in %1$s - %2$s', 'joinotify' ), $date_value, $time_value );
                } else {
                    /* translators: %s: date */
                    $formatted_expires = sprintf( esc_html__( 'Expires in %s', 'joinotify' ), $date_value );
                }
            }
        } else {
            $formatted_expires = esc_html__( 'Coupon does not expire', 'joinotify' );
        }

        // add coupon placeholders on array
        $placeholders = apply_filters( 'Joinotify/Builder/Components/Coupon_Placeholders', array(
            '{{ joinotify_coupon_code }}' => array(
                'description' => __( 'To retrieve the coupon discount code.', 'joinotify' ),
                'replacement' => $coupon_code,
            ),
            '{{ joinotify_coupon_description }}' => array(
                'description' => __( 'To retrieve the coupon description.', 'joinotify' ),
                'replacement' => isset( $settings['coupon_description'] ) ? $settings['coupon_description'] : '',
            ),
            '{{ joinotify_coupon_discount_type }}' => array(
                'description' => __( 'To retrieve the coupon discount type. Example: Percentage or fixed amount.', 'joinotify' ),
                'replacement' => isset( $settings['discount_type'] ) && $settings['discount_type'] === 'percent' ? esc_html__( 'Percentage', 'joinotify' ) : esc_html__( 'Fixed amount', 'joinotify' ),
            ),
            '{{ joinotify_coupon_discount_value }}' => array(
                'description' => __( 'To retrieve the coupon discount amount.', 'joinotify' ),
                'replacement' => isset( $settings['coupon_amount'] ) ? $settings['coupon_amount'] : '',
            ),
            '{{ joinotify_coupon_discount_formatted }}' => array(
                'description' => __( 'To retrieve the coupon discount amount formatted with a currency symbol or percentage. Example: 10%.', 'joinotify' ),
                'replacement' => $formatted_discount,
            ),
            '{{ joinotify_coupon_expires }}' => array(
                'description' => __( 'To retrieve the coupon expiration. Example: Expires in 1 hour.', 'joinotify' ),
                'replacement' => $formatted_expires,
            ),
        ));

        return $placeholders;
    }


    /**
     * Add conditions for WooCommerce triggers
     *
     * @since 1.2.0
     * @version 2.7.0
     * @param array $conditions | Current conditions
     * @return array
     */
    public function add_conditions( $conditions ) {
        // Define reusable conditions
        $common_conditions = array(
            'order_status' => array(
                'title' => __( 'Order status', 'joinotify' ),
                'description' => __( 'Allows checking the order status.', 'joinotify' ),
            ),
            'order_total' => array(
                'title' => __( 'Order total', 'joinotify' ),
                'description' => __( 'Allows checking the order total.', 'joinotify' ),
            ),
            'order_paid' => array(
                'title' => __( 'Order paid', 'joinotify' ),
                'description' => __( 'Allows checking whether the order was paid.', 'joinotify' ),
            ),
            'products_purchased' => array(
                'title' => __( 'Purchased products', 'joinotify' ),
                'description' => __( 'Allows checking the products purchased in the order.', 'joinotify' ),
            ),
            'payment_method' => array(
                'title' => __( 'Payment method', 'joinotify' ),
                'description' => __( 'Allows checking the order payment method.', 'joinotify' ),
            ),
            'shipping_method' => array(
                'title' => __( 'Shipping method', 'joinotify' ),
                'description' => __( 'Allows checking the order shipping method.', 'joinotify' ),
            ),
            'user_meta' => array(
                'title' => __( 'User metadata', 'joinotify' ),
                'description' => __( 'Allows checking specific user metadata.', 'joinotify' ),
            ),
            'user_role' => array(
                'title' => __( 'User role', 'joinotify' ),
                'description' => __( 'Allows checking the user role that fired the trigger.', 'joinotify' ),
            ),
            'customer_email' => array(
                'title' => __( 'Customer email', 'joinotify' ),
                'description' => __( 'Allows checking the customer email for the order.', 'joinotify' ),
            ),
            'refund_amount' => array(
                'title' => __( 'Refund amount', 'joinotify' ),
                'description' => __( 'Allows checking the order refund amount.', 'joinotify' ),
            ),
            'subscription_status' => array(
                'title' => __( 'Subscription status', 'joinotify' ),
                'description' => __( 'Allows checking the status of the created subscription.', 'joinotify' ),
            ),
        );
    
        // Define triggers and their associated conditions
        $woocommerce_conditions = array(
            'woocommerce_new_order' => array( 'order_total', 'order_paid', 'products_purchased', 'payment_method', 'shipping_method', 'user_meta', 'customer_email' ),
            'woocommerce_payment_complete' => array( 'order_total', 'products_purchased', 'payment_method', 'shipping_method', 'user_meta', 'customer_email' ),
            'woocommerce_created_customer' => array( 'user_role', 'user_meta' ),
            'woocommerce_order_status_completed' => array( 'customer_email', 'order_total', 'products_purchased', 'payment_method', 'shipping_method', 'user_meta' ),
            'woocommerce_order_status_changed' => array( 'order_status', 'order_total', 'order_paid', 'products_purchased', 'payment_method', 'shipping_method', 'user_meta', 'customer_email' ),
            'woocommerce_order_partially_refunded' => array( 'refund_amount', 'products_purchased', 'payment_method', 'shipping_method', 'user_meta', 'customer_email' ),
            'woocommerce_order_fully_refunded' => array( 'products_purchased', 'payment_method', 'shipping_method', 'user_meta', 'customer_email' ),
            'woocommerce_checkout_subscription_created' => array( 'order_paid', 'subscription_status', 'products_purchased', 'payment_method', 'shipping_method', 'user_meta', 'customer_email' ),
        );
    
        // Build the final conditions array dynamically
        $formatted_conditions = array();
        
        foreach ( $woocommerce_conditions as $trigger => $keys ) {
            foreach ( $keys as $key ) {
                $formatted_conditions[ $trigger ][ $key ] = $common_conditions[ $key ];
            }
        }
    
        return array_merge( $conditions, $formatted_conditions );
    }


    /**
     * Process workflow on receive new order placed through the checkout block
     *
     * @since 2.5.0
     * @param \WC_Order $order | Order object
     * @return void
     */
    public function process_workflow_on_block_checkout_order( $order ) {
        if ( $order instanceof \WC_Order ) {
            $this->process_workflow_on_new_order( $order->get_id() );
        }
    }


    /**
     * Process workflow on receive new order on WooCommerce
     *
     * @since 1.0.0
     * @version 1.4.7
     * @param int $order_id  | Order ID
     * @return void
     */
    public function process_workflow_on_new_order( $order_id ) {
        /**
         * Filter the payload before processing workflows
         * 
         * @since 1.3.0
         * @param array $payload | Payload to be processed
         */
        $payload = apply_filters( 'Joinotify/Process_Workflows/Woocommerce/New_Order', array(
            'type' => 'trigger',
            'hook' => 'woocommerce_new_order',
            'integration' => 'woocommerce',
            'order_id' => $order_id,
        ));

        Workflow_Processor::process_workflows( $payload );
    }


    /**
     * Process workflow when a payment gateway confirms the payment of an order
     *
     * @since 2.7.0
     * @param int $order_id | Order ID
     * @param string $transaction_id | Transaction ID of the gateway
     * @return void
     */
    public function process_workflow_payment_complete( $order_id, $transaction_id = '' ) {
        $this->process_workflow_payment_confirmed( $order_id, $transaction_id );
    }


    /**
     * Process workflow when an order moves from an unpaid status to a paid one
     *
     * Covers the payments no gateway confirms: cash on delivery, and an order an admin marks as
     * processing or completed. A gateway that confirms the payment moves the order to a paid
     * status too, which is why the trigger is recorded on the order and runs only once.
     *
     * @since 2.7.0
     * @param int $order_id | Order ID
     * @param string $old_status | Old status
     * @param string $new_status | New status
     * @return void
     */
    public function process_workflow_payment_status_changed( $order_id, $old_status, $new_status ) {
        $paid_statuses = function_exists('wc_get_is_paid_statuses') ? wc_get_is_paid_statuses() : array( 'processing', 'completed' );

        // processing -> completed is not a new payment
        if ( ! in_array( $new_status, $paid_statuses, true ) || in_array( $old_status, $paid_statuses, true ) ) {
            return;
        }

        $this->process_workflow_payment_confirmed( $order_id );
    }


    /**
     * Run the "Payment confirmed" workflows of an order, once
     *
     * @since 2.7.0
     * @param int $order_id | Order ID
     * @param string $transaction_id | Transaction ID of the gateway, when it sent one
     * @return void
     */
    protected function process_workflow_payment_confirmed( $order_id, $transaction_id = '' ) {
        $order = wc_get_order( $order_id );

        if ( ! $order instanceof \WC_Order || $order->get_meta( self::META_PAYMENT_CONFIRMED ) ) {
            return;
        }

        $order->update_meta_data( self::META_PAYMENT_CONFIRMED, time() );
        $order->save_meta_data();

        /**
         * Filter the payload before processing workflows
         *
         * @since 2.7.0
         * @param array $payload | Payload to be processed
         */
        $payload = apply_filters( 'Joinotify/Process_Workflows/Woocommerce/Payment_Complete', array(
            'type' => 'trigger',
            'hook' => 'woocommerce_payment_complete',
            'integration' => 'woocommerce',
            'order_id' => $order->get_id(),
            'transaction_id' => '' !== (string) $transaction_id ? (string) $transaction_id : $order->get_transaction_id(),
        ));

        Workflow_Processor::process_workflows( $payload );

        // Until the 2.7.0 upgrade routine renames it, a workflow saved with the "New order
        // (Processing)" trigger this one replaced still listens to the old slug.
        if ( ! self::is_payment_trigger_migrated() ) {
            Workflow_Processor::process_workflows( array_merge( $payload, array(
                'hook' => 'woocommerce_checkout_order_processed',
                'trigger' => 'woocommerce_payment_complete', // resolves the placeholders of the new trigger
            )));
        }
    }


    /**
     * Whether the stored workflows were moved from the "New order (Processing)" trigger
     *
     * @since 2.7.0
     * @return bool
     */
    protected static function is_payment_trigger_migrated() {
        $completed = get_option( Upgrader::COMPLETED_OPTION, array() );

        return is_array( $completed ) && in_array( Upgrader::PAYMENT_TRIGGER_ROUTINE, $completed, true );
    }


    /**
     * Queue a customer account created by WooCommerce
     *
     * WooCommerce fires the hook inside wc_create_new_customer(), before the checkout saves the
     * billing phone and address on the account, so the workflows run at the end of the request.
     *
     * @since 2.7.0
     * @param int $customer_id | Customer (user) ID
     * @param array $new_customer_data | Data the account was created with
     * @param bool $password_generated | Whether WooCommerce generated the password
     * @return void
     */
    public function queue_customer_created( $customer_id, $new_customer_data = array(), $password_generated = false ) {
        $customer_id = absint( $customer_id );

        if ( ! $customer_id ) {
            return;
        }

        self::$created_customers[ $customer_id ] = array(
            'account_source' => self::detect_account_source( $new_customer_data ),
            'password_generated' => (bool) $password_generated,
        );

        // Before the shutdown callbacks at priority 0 (the telemetry flush among them), so what
        // the workflows record in this request is still written.
        if ( ! has_action( 'shutdown', array( $this, 'process_workflow_customers_created' ) ) ) {
            add_action( 'shutdown', array( $this, 'process_workflow_customers_created' ), -10 );
        }
    }


    /**
     * Where WooCommerce created a customer account
     *
     * @since 2.7.0
     * @param array $new_customer_data | Data the account was created with
     * @return string One of my_account, checkout, order_confirmation or other.
     */
    public static function detect_account_source( $new_customer_data ) {
        $source = is_array( $new_customer_data ) && isset( $new_customer_data['source'] ) && is_scalar( $new_customer_data['source'] )
            ? (string) $new_customer_data['source']
            : '';

        // the checkout block and the order confirmation block name themselves
        if ( 'store-api' === $source ) {
            return 'checkout';
        }

        if ( 'delayed-account-creation' === $source ) {
            return 'order_confirmation';
        }

        // the classic checkout and the My Account form leave the source unset
        if ( did_action('woocommerce_checkout_process') ) {
            return 'checkout';
        }

        // phpcs:ignore WordPress.Security.NonceVerification.Missing -- Only tells where the account came from; WooCommerce verified this nonce before creating it.
        if ( isset( $_POST['woocommerce-register-nonce'] ) ) {
            return 'my_account';
        }

        return 'other';
    }


    /**
     * Process workflows for the customer accounts created in this request
     *
     * @since 2.7.0
     * @return void
     */
    public function process_workflow_customers_created() {
        $created = self::$created_customers;
        self::$created_customers = array();

        foreach ( $created as $customer_id => $context ) {
            // an account removed in the same request, by a failed checkout for example
            if ( ! get_userdata( $customer_id ) ) {
                continue;
            }

            /**
             * Filter the payload before processing workflows
             *
             * @since 2.7.0
             * @param array $payload | Payload to be processed
             */
            $payload = apply_filters( 'Joinotify/Process_Workflows/Woocommerce/Created_Customer', array(
                'type' => 'trigger',
                'hook' => 'woocommerce_created_customer',
                'integration' => 'woocommerce',
                'user_id' => $customer_id,
                'account_source' => $context['account_source'],
                'password_generated' => $context['password_generated'],
            ));

            Workflow_Processor::process_workflows( $payload );
        }
    }


    /**
     * Process workflow when the stock of a product reaches the low stock threshold
     *
     * @since 2.7.0
     * @param \WC_Product $product | Product
     * @return void
     */
    public function process_workflow_low_stock( $product ) {
        if ( ! self::claim_stock_alert( 'woocommerce_low_stock', $product ) ) {
            return;
        }

        /**
         * Filter the payload before processing workflows
         *
         * @since 2.7.0
         * @param array $payload | Payload to be processed
         */
        $payload = apply_filters( 'Joinotify/Process_Workflows/Woocommerce/Low_Stock', self::get_product_payload( 'woocommerce_low_stock', $product ) );

        Workflow_Processor::process_workflows( $payload );
    }


    /**
     * Process workflow when the stock of a product reaches the out of stock threshold
     *
     * @since 2.7.0
     * @param \WC_Product $product | Product
     * @return void
     */
    public function process_workflow_no_stock( $product ) {
        if ( ! self::claim_stock_alert( 'woocommerce_no_stock', $product ) ) {
            return;
        }

        /**
         * Filter the payload before processing workflows
         *
         * @since 2.7.0
         * @param array $payload | Payload to be processed
         */
        $payload = apply_filters( 'Joinotify/Process_Workflows/Woocommerce/No_Stock', self::get_product_payload( 'woocommerce_no_stock', $product ) );

        Workflow_Processor::process_workflows( $payload );
    }


    /**
     * Whether a stock alert has not run yet for the product in this request, claiming it
     *
     * @since 2.7.0
     * @param string $hook | Trigger hook
     * @param mixed $product | Product
     * @return bool
     */
    protected static function claim_stock_alert( $hook, $product ) {
        if ( ! $product instanceof \WC_Product || ! $product->get_id() ) {
            return false;
        }

        $key = $hook . ':' . $product->get_id();

        if ( isset( self::$stock_alerts[ $key ] ) ) {
            return false;
        }

        self::$stock_alerts[ $key ] = true;

        return true;
    }


    /**
     * Build the payload of a product trigger
     *
     * @since 2.7.0
     * @param string $hook | Trigger hook
     * @param \WC_Product $product | Product
     * @return array
     */
    protected static function get_product_payload( $hook, $product ) {
        return array(
            'type' => 'trigger',
            'hook' => $hook,
            'integration' => 'woocommerce',
            'product_id' => $product->get_id(),
        );
    }


    /**
     * Remember a product whose save moves it from out of stock or on backorder to in stock
     *
     * Runs before the save, while the product still holds its previous stock status: the
     * stock status hooks only carry the new one, and they also fire for a product created in
     * stock, which is not a restock.
     *
     * @since 2.7.0
     * @param \WC_Product $product | Product being saved
     * @return void
     */
    public function remember_restock( $product ) {
        if ( ! $product instanceof \WC_Product || ! $product->get_id() ) {
            return;
        }

        $changes = $product->get_changes();

        if ( ! isset( $changes['stock_status'] ) || 'instock' !== $changes['stock_status'] ) {
            return;
        }

        $data = $product->get_data();
        $previous = isset( $data['stock_status'] ) ? (string) $data['stock_status'] : '';

        if ( in_array( $previous, array( 'outofstock', 'onbackorder' ), true ) ) {
            self::$restocking[ $product->get_id() ] = true;
        }
    }


    /**
     * Process workflow when a product goes back in stock
     *
     * A variation coming back also brings its variable product back, so the variable product
     * is only announced when none of its variations was, and a variation is skipped when the
     * variable product itself was already announced in this request.
     *
     * @since 2.7.0
     * @param int $product_id | Product ID
     * @param string $stock_status | New stock status
     * @param \WC_Product|null $product | Product
     * @return void
     */
    public function process_workflow_back_in_stock( $product_id, $stock_status, $product = null ) {
        $product_id = absint( $product_id );

        if ( 'instock' !== $stock_status || empty( self::$restocking[ $product_id ] ) ) {
            return;
        }

        unset( self::$restocking[ $product_id ] );

        $product = $product instanceof \WC_Product ? $product : wc_get_product( $product_id );

        if ( ! $product instanceof \WC_Product ) {
            return;
        }

        $parent_id = (int) $product->get_parent_id();

        if ( $parent_id ) {
            if ( isset( self::$restocked_products[ $parent_id ] ) && 'self' === self::$restocked_products[ $parent_id ] ) {
                return;
            }

            if ( ! isset( self::$restocked_products[ $parent_id ] ) ) {
                self::$restocked_products[ $parent_id ] = 'variation';
            }
        } elseif ( isset( self::$restocked_products[ $product_id ] ) ) {
            return;
        }

        self::$restocked_products[ $product_id ] = 'self';

        /**
         * Filter the payload before processing workflows
         *
         * @since 2.7.0
         * @param array $payload | Payload to be processed
         */
        $payload = apply_filters( 'Joinotify/Process_Workflows/Woocommerce/Product_Back_In_Stock', self::get_product_payload( 'woocommerce_product_back_in_stock', $product ) );

        Workflow_Processor::process_workflows( $payload );
    }


    /**
     * Process workflow when WooCommerce notifies a customer that a product is back in stock
     *
     * Hooked on the filter that asks whether the back in stock email is enabled, which
     * WooCommerce runs once per notice right before sending it, whether the email is enabled or
     * not. The filter value is always returned untouched.
     *
     * @since 2.7.0
     * @param bool $enabled | Whether the email is enabled
     * @param mixed $notification | The notice (Automattic\WooCommerce\Internal\StockNotifications\Notification)
     * @return bool
     */
    public function process_workflow_customer_stock_notification( $enabled, $notification = null ) {
        if ( ! is_object( $notification )
            || ! method_exists( $notification, 'get_id' )
            || ! method_exists( $notification, 'get_product_id' )
            || ! method_exists( $notification, 'get_user_id' )
            || ! method_exists( $notification, 'get_user_email' )
        ) {
            return $enabled;
        }

        $notification_id = (int) $notification->get_id();

        // the email preview uses a notice that was never saved
        if ( ! $notification_id || isset( self::$stock_notices[ $notification_id ] ) ) {
            return $enabled;
        }

        self::$stock_notices[ $notification_id ] = true;

        /**
         * Filter the payload before processing workflows
         *
         * @since 2.7.0
         * @param array $payload | Payload to be processed
         */
        $payload = apply_filters( 'Joinotify/Process_Workflows/Woocommerce/Customer_Stock_Notification', array(
            'type' => 'trigger',
            'hook' => 'woocommerce_customer_stock_notification',
            'integration' => 'woocommerce',
            'product_id' => (int) $notification->get_product_id(),
            'user_id' => (int) $notification->get_user_id(),
            'user_email' => (string) $notification->get_user_email(),
            'notification_id' => $notification_id,
        ));

        // WooCommerce cancels a notice whose sending throws, so a failing workflow must not reach it
        try {
            Workflow_Processor::process_workflows( $payload );
        } catch ( \Throwable $e ) {
            Logger::register_log( sprintf( 'Back in stock notice %d: workflows failed: %s', $notification_id, $e->getMessage() ), 'ERROR' );
        }

        return $enabled;
    }


    /**
     * Process workflow when order status is complete
     * 
     * @since 1.0.0
     * @version 1.4.7
     * @param int $order_id  | Order ID
     * @param object $order | Order object
     * @param array $status_transition | Status transition
     * @return void
     */
    public function process_workflow_order_completed( $order_id, $order, $status_transition ) {
        /**
         * Filter the payload before processing workflows
         * 
         * @since 1.3.0
         * @param array $payload | Payload to be processed
         */
        $payload = apply_filters( 'Joinotify/Process_Workflows/Woocommerce/Order_Status_Completed', array(
            'type' => 'trigger',
            'hook' => 'woocommerce_order_status_completed',
            'integration' => 'woocommerce',
            'order_id' => $order_id,
            'status_transition' => $status_transition,
        ));

        Workflow_Processor::process_workflows( $payload );
    }


    /**
     * Process workflow when the download access of a digital product is granted
     *
     * @since 2.1.0
     * @param int $order_id | Order ID
     * @return void
     */
    public function process_workflow_download_permissions_granted( $order_id ) {
        /**
         * Filter the payload before processing workflows
         *
         * @since 2.1.0
         * @param array $payload | Payload to be processed
         */
        $payload = apply_filters( 'Joinotify/Process_Workflows/Woocommerce/Grant_Product_Download_Permissions', array(
            'type' => 'trigger',
            'hook' => 'woocommerce_grant_product_download_permissions',
            'integration' => 'woocommerce',
            'order_id' => $order_id,
        ));

        Workflow_Processor::process_workflows( $payload );
    }


    /**
     * Process workflow when the customer downloads a digital file
     *
     * @since 2.1.0
     * @param string $user_email | Customer email
     * @param string $order_key  | Order key
     * @param int $product_id    | Product ID of the downloaded file
     * @param int $user_id       | Customer user ID
     * @param string $download_id | Download ID of the file
     * @param int $order_id      | Order ID
     * @return void
     */
    public function process_workflow_download_product( $user_email, $order_key, $product_id, $user_id, $download_id, $order_id ) {
        /**
         * Filter the payload before processing workflows
         *
         * @since 2.1.0
         * @param array $payload | Payload to be processed
         */
        $payload = apply_filters( 'Joinotify/Process_Workflows/Woocommerce/Download_Product', array(
            'type' => 'trigger',
            'hook' => 'woocommerce_download_product',
            'integration' => 'woocommerce',
            'order_id' => $order_id,
            'product_id' => $product_id,
            'download_id' => $download_id,
            'user_id' => $user_id,
            'user_email' => $user_email,
        ));

        Workflow_Processor::process_workflows( $payload );
    }


    /**
     * Process workflow when order is fully refunded
     *
     * @since 1.0.0
     * @version 1.4.7
     * @param int $order_id  | Order ID
     * @param int $refund_id | Refund ID
     * @return void
     */
    public function process_workflow_order_fully_refunded( $order_id, $refund_id ) {
        /**
         * Filter the payload before processing workflows
         * 
         * @since 1.3.0
         * @param array $payload | Payload to be processed
         */
        $payload = apply_filters( 'Joinotify/Process_Workflows/Woocommerce/Order_Fully_Refunded', array(
            'type' => 'trigger',
            'hook' => 'woocommerce_order_fully_refunded',
            'integration' => 'woocommerce',
            'order_id' => $order_id,
            'refund_id' => $refund_id,
        ));

        Workflow_Processor::process_workflows( $payload );
    }


    /**
     * Process workflow when order is partially refunded
     * 
     * @since 1.0.0
     * @version 2.1.0
     * @param int $order_id  | Order ID
     * @param int $refund_id | Refund ID
     * @return void
     */
    public function process_workflow_order_partially_refunded( $order_id, $refund_id ) {
        /**
         * Filter the payload before processing workflows
         * 
         * @since 1.3.0
         * @param array $payload | Payload to be processed
         */
        $payload = apply_filters( 'Joinotify/Process_Workflows/Woocommerce/Order_Partially_Refunded', array(
            'type' => 'trigger',
            'hook' => 'woocommerce_order_partially_refunded',
            'integration' => 'woocommerce',
            'order_id' => $order_id,
            'refund_id' => $refund_id,
        ));

        Workflow_Processor::process_workflows( $payload );
    }
    

    /**
     * Process workflow when order has status changed
     * 
     * @since 1.0.0
     * @version 1.4.7
     * @param int $order_id  | Order ID
     * @param string $old_status | Old status
     * @param string $new_status | New status
     * @return void
     */
    public function process_workflow_order_status_changed( $order_id, $old_status, $new_status ) {
        /**
         * Filter the payload before processing workflows
         * 
         * @since 1.3.0
         * @param array $payload | Payload to be processed
         */
        $payload = apply_filters( 'Joinotify/Process_Workflows/Woocommerce/Order_Status_Changed', array(
            'type' => 'trigger',
            'hook' => 'woocommerce_order_status_changed',
            'integration' => 'woocommerce',
            'order_id' => $order_id,
            'old_status' => $old_status,
            'new_status' => $new_status,
        ));

        Workflow_Processor::process_workflows( $payload );
    }


    /**
	 * Ensure WooCommerce checkout context (session/customer) for admin screens.
	 *
	 * @since 1.4.5
	 * @return void
	 */
	public static function ensure_wc_checkout_context() {
		if ( ! function_exists('WC') || ! WC() ) {
			return;
		}

		// Ensure session.
		if ( empty( WC()->session ) ) {
			WC()->session = new WC_Session_Handler();
			WC()->session->init();
		}

		// Ensure customer.
		if ( empty( WC()->customer ) ) {
			WC()->customer = new WC_Customer( get_current_user_id(), true );
		}

		// Ensure cart (optional but helps some setups).
		if ( empty( WC()->cart ) ) {
			WC()->cart = new WC_Cart();
		}
	}


	/**
	 * Get all checkout fields available (raw WooCommerce array).
	 *
	 * @since 1.4.5
	 * @return array
	 */
	public static function get_checkout_fields_on_admin() {
		if ( ! class_exists('WooCommerce') || ! function_exists('WC') || ! WC()->checkout ) {
			return array();
		}

		self::ensure_wc_checkout_context();

		return WC()->checkout->get_checkout_fields();
	}
    

	/**
	 * Export checkout fields grouped by section (billing/shipping).
	 *
	 * @since 1.4.5
	 * @param string $section billing|shipping|all
	 * @return array
	 */
	public static function export_checkout_fields( $section = 'all' ) {
		$get_fields = self::get_checkout_fields_on_admin();

		if ( empty( $get_fields ) ) {
			return array(
				'billing'  => array(),
				'shipping' => array(),
			);
		}

		$billing  = isset( $get_fields['billing'] ) ? $get_fields['billing'] : array();
		$shipping = isset( $get_fields['shipping'] ) ? $get_fields['shipping'] : array();

		$result = array(
			'billing'  => $billing,
			'shipping' => $shipping,
		);

		if ( 'billing' === $section ) {
			$result = $billing;
		} elseif ( 'shipping' === $section ) {
			$result = $shipping;
		}

		/**
		 * Filter exported checkout fields.
		 *
		 * @since 1.4.5
		 * @param array  $result
		 * @param string $section
		 */
		return apply_filters( 'Joinotify/WooCommerce/Export_Checkout_Fields', $result, $section );
	}


	/**
	 * Get placeholders for checkout fields (billing/shipping separated).
	 *
	 * Ex:
	 * - billing_first_name -> {{ billing_first_name }}
	 * - shipping_address_1 -> {{ shipping_address_1 }}
	 *
	 * @since 1.4.5
	 * @param string $section billing|shipping
	 * @return array
	 */
	public static function get_checkout_placeholders_by_section( $section = 'billing' ) {
		$fields = self::export_checkout_fields( $section );

		if ( empty( $fields ) || ! is_array( $fields ) ) {
			return array();
		}

		$placeholders = array();

		foreach ( $fields as $field_id => $field ) {
			// Remove prefix billing_ or shipping_ to show the "clean" placeholder id.
			$placeholder_id = $field_id;

			if ( 'billing' === $section && strpos( $field_id, 'billing_' ) === 0 ) {
				$placeholder_id = substr( $field_id, 8 );
			} elseif ( 'shipping' === $section && strpos( $field_id, 'shipping_' ) === 0 ) {
				$placeholder_id = substr( $field_id, 9 );
			}

			$label = isset( $field['label'] ) ? $field['label'] : $field_id;

			$placeholders[ $field_id ] = array(
				'field_id'          => $field_id,
				'placeholder_id'    => $placeholder_id,
				'placeholder_html'  => sprintf( '{{ %s }}', $placeholder_id ),
				/* translators: %s: checkout field label */
				'description'       => sprintf( __( 'To retrieve the value of %s', 'joinotify' ), $label ),
				'section'           => $section,
			);
		}

		/**
		 * Filter placeholders list by section.
		 *
		 * @since 1.4.5
		 * @param array  $placeholders
		 * @param string $section
		 */
		return apply_filters( 'Joinotify/WooCommerce/Checkout_Field_Placeholders', $placeholders, $section );
	}


	/**
	 * Convenience: get billing + shipping placeholders together (grouped).
	 *
	 * @since 1.4.5
	 * @return array
	 */
	public static function get_checkout_placeholders_grouped() {
		return array(
			'billing'  => self::get_checkout_placeholders_by_section( 'billing' ),
			'shipping' => self::get_checkout_placeholders_by_section( 'shipping' ),
		);
	}
}
