<?php

namespace MeuMouse\Joinotify\Admin\Settings;

use MeuMouse\Joinotify\Admin\Default_Options;
use MeuMouse\Joinotify\Core\Helpers;

defined('ABSPATH') || exit;

/**
 * Storage and sanitization helpers for Joinotify settings.
 */
class Repository {

    /**
     * Read current settings merged with defaults.
     *
     * @return array<string,mixed>
     */
    public static function get_settings() {
        return wp_parse_args( get_option( 'joinotify_settings', array() ), Default_Options::set_default_options() );
    }


    /**
     * Persist a settings payload and return the sanitized result.
     *
     * @param array<string,mixed> $incoming
     * @return array<string,mixed>
     */
    public static function save_settings( $incoming ) {
        $defaults = Default_Options::set_default_options();
        $definitions = Registry::get_field_definitions();
        $current = self::get_settings();
        $sanitized = $current;

        foreach ( $defaults as $key => $default_value ) {
            $definition = $definitions[ $key ] ?? array();
            $value = array_key_exists( $key, $incoming ) ? $incoming[ $key ] : $default_value;
            $sanitized[ $key ] = self::sanitize_setting_value( $key, $value, $definition );
        }

        foreach ( Helpers::get_switch_options() as $switch_key ) {
            if ( ! array_key_exists( $switch_key, $incoming ) ) {
                $sanitized[ $switch_key ] = 'no';
            }
        }

        update_option( 'joinotify_settings', $sanitized );

        return $sanitized;
    }


    /**
     * Reset Joinotify settings to a pristine state.
     *
     * @return bool
     */
	public static function reset_settings() {
		$phones_senders = get_option( 'joinotify_get_phones_senders', array() );
		$phones_senders = is_array( $phones_senders ) ? $phones_senders : array();

		foreach ( $phones_senders as $phone ) {
			$phone = preg_replace( '/\D+/', '', (string) $phone );

			if ( empty( $phone ) ) {
				continue;
			}

			delete_option( 'joinotify_status_connection_' . $phone );
			delete_transient( 'joinotify_server_details_' . md5( $phone ) );
		}

		$deleted_settings = delete_option( 'joinotify_settings' );
		$deleted_senders = delete_option( 'joinotify_get_phones_senders' );

		delete_option( 'joinotify_alternative_license_activation' );
		delete_transient( 'joinotify_api_request_cache' );
		delete_transient( 'joinotify_api_response_cache' );
		delete_transient( 'joinotify_license_status_cached' );
		delete_user_meta( get_current_user_id(), 'joinotify_dismiss_placeholders_tip_user_meta' );

		return (bool) ( $deleted_settings || $deleted_senders );
	}


    /**
     * Convert a switch value to yes/no.
     *
     * @param mixed $value
     * @return string
     */
    private static function sanitize_toggle( $value ) {
        return in_array( $value, array( 'yes', '1', 1, true, 'true', 'on' ), true ) ? 'yes' : 'no';
    }


    /**
     * Sanitize a setting based on its field definition.
     *
     * @param string $key
     * @param mixed $value
     * @param array<string,mixed> $definition
     * @return mixed
     */
    private static function sanitize_setting_value( $key, $value, $definition ) {
        $type = isset( $definition['type'] ) ? (string) $definition['type'] : 'text';

        if ( 'toggle' === $type ) {
            return self::sanitize_toggle( $value );
        }

        if ( 'textarea' === $type ) {
            return sanitize_textarea_field( (string) $value );
        }

        if ( in_array( $type, array( 'select', 'text', 'phone' ), true ) ) {
            return sanitize_text_field( (string) $value );
        }

        if ( in_array( $key, array( 'joinotify_default_country_code', 'test_number_phone', 'proxy_api_key', 'send_text_proxy_api_route', 'send_media_proxy_api_route', 'create_coupon_prefix' ), true ) ) {
            return sanitize_text_field( (string) $value );
        }

        if ( in_array( $key, array( 'woocommerce_billing_full_address_format', 'woocommerce_shipping_full_address_format' ), true ) ) {
            return sanitize_textarea_field( (string) $value );
        }

        return sanitize_text_field( (string) $value );
    }
}
