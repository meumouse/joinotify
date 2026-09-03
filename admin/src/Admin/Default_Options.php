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
            // The Proxy API is deprecated and exposes message-sending endpoints
            // on this site, so it stays off until the owner turns it on.
            // WhatsApp transport switch: 'evolution' (legacy slots relay),
            // 'cloud' (official Meta Cloud API via Joinotify), or 'auto' (cloud
            // when a Cloud API token is available, otherwise evolution).
            // Manual override for the Cloud API credentials. When empty, the
            // values provisioned by the license activation are used instead
            // (see Helpers::cloud_api_token() and friends).
            'whatsapp_cloud_api_token' => '',
            'whatsapp_phone_number_id' => '',
            'whatsapp_waba_id' => '',
            // Template used to deliver passwordless login (OTP) codes on the
            // Cloud API, where free-form text is refused outside the 24h window.
            'otp_login_template_name' => '',
            'otp_login_template_language' => 'pt_BR',
            'enable_debug_mode' => 'no',
            // Usage data is strictly opt-in: nothing is collected or sent while
            // this is 'no', which is where every install starts.
            'enable_usage_tracking' => 'no',
            'enable_create_coupon_action' => 'yes',
            'create_coupon_prefix' => 'CUPOM_',
            'enable_ignore_processed_actions' => 'no',
            'enable_developer_integration' => 'yes',
            'enable_message_history' => 'yes',
            'message_history_retention_days' => '90',
            // Retry policy for a failed send: how many attempts it gets, and the
            // wait before the first one, which then doubles on every attempt
            // (30, 60, 120, 240, 480 minutes with these values).
            'message_retry_max_attempts' => '5',
            'message_retry_first_delay_minutes' => '30',
            'enable_debug_logs' => 'yes',
            'debug_logs_retention_days' => '30',
            'woocommerce_billing_full_address_format' => '{{ address_1 }}, {{ number }}, {{ city }} - {{ state }} (CEP: {{ postcode }})',
            'woocommerce_shipping_full_address_format' => '{{ address_1 }}, {{ number }}, {{ city }} - {{ state }} (CEP: {{ postcode }})',
            'enable_ai_integration' => 'no',
            'ai_provider' => 'wp_ai_client',
            'ai_default_temperature' => '0.7',
            'ai_global_system_prompt' => '',
            'enable_telegram_integration' => 'no',
            'telegram_bot_token' => '',
            'telegram_default_chat_id' => '',
            'enable_resend_integration' => 'no',
            'resend_api_key' => '',
            'resend_from_email' => '',
            'resend_from_name' => '',
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