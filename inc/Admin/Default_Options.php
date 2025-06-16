<?php

namespace MeuMouse\Joinotify\Admin;

// Exit if accessed directly.
defined('ABSPATH') || exit;

/**
 * Set default options for plugin settings
 * 
 * @since 1.3.0
 * @version 1.3.4
 * @package MeuMouse.com
 */
class Default_Options {

    /**
     * Set default options
     * 
     * @since 1.0.0
     * @version 1.3.4
     * @return array
     */
    public static function set_default_options() {
        return apply_filters( 'Joinotify/Admin/Set_Default_Options', array(
            'enable_whatsapp_integration' => 'yes',
            'enable_woocommerce_integration' => 'yes',
            'enable_elementor_integration' => 'yes',
            'enable_wpforms_integration' => 'yes',
            'enable_flexify_checkout_integration' => 'yes',
            'enable_wordpress_integration' => 'yes',
            'joinotify_default_country_code' => '55',
            'test_number_phone' => '',
            'enable_proxy_api' => 'yes',
            'proxy_api_key' => '',
            'send_text_proxy_api_route' => 'send-message/text',
            'send_media_proxy_api_route' => 'send-message/media',
            'enable_debug_mode' => 'no',
            'enable_auto_updates' => 'no',
            'enable_create_coupon_action' => 'yes',
            'create_coupon_prefix' => 'CUPOM_',
            'enable_ignore_processed_actions' => 'yes',
            'enable_developer_integration' => 'yes',
            'enable_send_disconnect_notifications' => 'yes',
            'enable_update_notice' => 'yes',
        ));
    }
}