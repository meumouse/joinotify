<?php

namespace MeuMouse\Joinotify\Admin;

use MeuMouse\Joinotify\Integrations\Integrations_Base;

// Exit if accessed directly.
defined('ABSPATH') || exit;

/**
 * Set default options for plugin settings
 * 
 * @since 1.3.0
 * @version 1.4.7
 * @package MeuMouse\Joinotify\Admin
 * @author MeuMouse.com
 */
class Default_Options {

    /**
     * Set default options
     * 
     * @since 1.0.0
     * @version 1.4.7
     * @return array
     */
    public static function set_default_options() {
        $defaults = array(
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
            'enable_ignore_processed_actions' => 'no',
            'enable_developer_integration' => 'yes',
            'enable_send_disconnect_notifications' => 'yes',
            'enable_message_history' => 'yes',
            'message_history_retention_days' => '90',
            'enable_update_notice' => 'yes',
            'woocommerce_billing_full_address_format' => '{{ address_1 }}, {{ number }}, {{ city }} - {{ state }} (CEP: {{ postcode }})',
            'woocommerce_shipping_full_address_format' => '{{ address_1 }}, {{ number }}, {{ city }} - {{ state }} (CEP: {{ postcode }})',
            'enable_openai_integration' => 'no',
            'ai_provider' => 'openai',
            'openai_api_key' => '',
            'openai_default_model' => 'gpt-4o-mini',
            'openai_default_temperature' => '0.7',
            'ai_global_system_prompt' => '',
        );

        $defaults = array_merge( $defaults, self::get_integration_default_options() );

        return apply_filters( 'Joinotify/Admin/Set_Default_Options', $defaults );
    }


    /**
     * Collect default options declared by registered integrations.
     *
     * @since 1.4.7
     * @return array<string,mixed>
     */
    private static function get_integration_default_options() {
        $defaults = array();

        foreach ( Integrations_Base::integration_tab_items() as $slug => $item ) {
            if ( ! is_array( $item ) ) {
                continue;
            }

            foreach ( $item['defaults'] ?? array() as $key => $value ) {
                if ( '' === $key || ! is_string( $key ) && ! is_int( $key ) ) {
                    continue;
                }

                $defaults[ (string) $key ] = $value;
            }

            if ( ! empty( $item['setting_key'] ) && ! isset( $defaults[ (string) $item['setting_key'] ] ) ) {
                $defaults[ (string) $item['setting_key'] ] = 'no';
            }
        }

        return $defaults;
    }
}