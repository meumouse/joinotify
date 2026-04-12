<?php
/**
 * Repository source file.
 *
 * @since 1.4.7
 * @version 1.4.7
 */

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

            if ( isset( $definition['type'] ) && 'toggle' === $definition['type'] ) {
                $sanitized[ $key ] = self::sanitize_toggle( $value );
                continue;
            }

            if ( isset( $definition['type'] ) && 'select' === $definition['type'] ) {
                $sanitized[ $key ] = sanitize_text_field( (string) $value );
                continue;
            }

            if ( in_array( $key, array( 'test_number_phone', 'proxy_api_key' ), true ) ) {
                $sanitized[ $key ] = sanitize_text_field( (string) $value );
                continue;
            }

            if ( in_array( $key, array( 'send_text_proxy_api_route', 'send_media_proxy_api_route', 'create_coupon_prefix' ), true ) ) {
                $sanitized[ $key ] = sanitize_text_field( (string) $value );
                continue;
            }

            if ( in_array( $key, array( 'woocommerce_billing_full_address_format', 'woocommerce_shipping_full_address_format' ), true ) ) {
                $sanitized[ $key ] = sanitize_textarea_field( (string) $value );
                continue;
            }

            if ( $key === 'joinotify_default_country_code' ) {
                $sanitized[ $key ] = sanitize_text_field( (string) $value );
                continue;
            }

            $sanitized[ $key ] = sanitize_text_field( (string) $value );
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
}
