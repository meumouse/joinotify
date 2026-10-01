<?php

namespace MeuMouse\Joinotify\Admin\Settings;

use MeuMouse\Joinotify\Admin\Default_Options;
use MeuMouse\Joinotify\Core\Helpers;
use MeuMouse\Joinotify\Core\Token_Vault;

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
     * The returned array is what was stored, so the API key in it is sealed —
     * pass it through Registry::get_settings_for_client() before it reaches the
     * browser, as every caller does.
     *
     * @version 2.6.0
     * @param array<string,mixed> $incoming
     * @return array<string,mixed>
     */
    public static function save_settings( $incoming ) {
        $defaults = Default_Options::set_default_options();
        $definitions = Registry::get_field_definitions();
        $current = self::get_settings();
        $sanitized = $current;
        $all_keys = array_unique( array_merge(
            array_keys( $defaults ),
            array_keys( $definitions ),
            array_keys( $current ),
            array_keys( is_array( $incoming ) ? $incoming : array() )
        ) );

        foreach ( $all_keys as $key ) {
            $definition = $definitions[ $key ] ?? array();

            if ( in_array( $key, Helpers::get_switch_options(), true ) && ! array_key_exists( $key, $incoming ) ) {
                $sanitized[ $key ] = 'no';
                continue;
            }

            if ( array_key_exists( $key, $incoming ) ) {
                $value = $incoming[ $key ];
            } elseif ( array_key_exists( $key, $current ) ) {
                $value = $current[ $key ];
            } elseif ( array_key_exists( $key, $defaults ) ) {
                $value = $defaults[ $key ];
            } else {
                continue;
            }

            $sanitized[ $key ] = self::sanitize_setting_value( $key, $value, $definition );
        }

        $sanitized = self::preserve_write_only( $sanitized, $current, Registry::get_write_only_keys() );
        $sanitized = self::seal_api_key( $sanitized, $current );

        // The `_is_set`/`_locked` flags are derived for the browser on every read
        // (Registry::get_settings_for_client) and come back with the whole form;
        // they describe the key, they are not settings of their own.
        foreach ( Registry::get_write_only_keys() as $write_only_key ) {
            unset( $sanitized[ $write_only_key . '_is_set' ], $sanitized[ $write_only_key . '_locked' ] );
        }

        update_option( 'joinotify_settings', $sanitized );

        /**
         * Fires after settings are stored, with both sides of the change.
         *
         * The previous values are passed along because the useful signal is almost never
         * the new value on its own — it is the transition. Consent being switched on, a
         * transport being changed, a key being removed: each of those is a moment, and a
         * listener that only saw the result would have to keep its own copy to spot it.
         *
         * @since 2.3.0
         * @param array $sanitized Settings as stored.
         * @param array $current Settings as they were before this save.
         */
        do_action( 'Joinotify/Settings/Saved', $sanitized, $current );

        return $sanitized;
    }


    /**
     * Keep stored credentials that the submitted payload left blank.
     *
     * Write-only settings (the Joinotify API key) are blanked before they reach
     * the browser, so the settings form always submits them empty. Without this
     * the first press of Save after connecting — through the wizard or through
     * the WhatsApp modal — would write that empty string back and silently
     * disconnect the site. Clearing one is done through its own control, which
     * writes the option directly.
     *
     * Pure helper (no WordPress calls) so the rule can be tested in isolation.
     *
     * @since 2.3.0
     * @param array<string,mixed> $sanitized Values about to be stored.
     * @param array<string,mixed> $current   Values currently stored.
     * @param array<int,string>   $keys      Write-only setting keys.
     * @return array<string,mixed>
     */
    public static function preserve_write_only( $sanitized, $current, $keys ) {
        foreach ( (array) $keys as $key ) {
            $key = (string) $key;
            $incoming = isset( $sanitized[ $key ] ) ? trim( (string) $sanitized[ $key ] ) : '';
            $stored = isset( $current[ $key ] ) ? trim( (string) $current[ $key ] ) : '';

            if ( '' === $incoming && '' !== $stored ) {
                $sanitized[ $key ] = $current[ $key ];
            }
        }

        return $sanitized;
    }


    /**
     * Keep the Joinotify API key sealed in the stored settings.
     *
     * A save usually hands back the sealed value it read (preserved above, or
     * merged in by the wizard and the import), which is kept as it is. A key in
     * the clear — the API key control puts the one it just connected with into
     * the form — is sealed, unless it is the key already stored, in which case
     * the stored value is kept instead of being sealed again with a new nonce.
     *
     * @since 2.6.0
     * @param array<string,mixed> $sanitized Values about to be stored.
     * @param array<string,mixed> $current   Values currently stored.
     * @return array<string,mixed>
     */
    public static function seal_api_key( $sanitized, $current ) {
        $key = 'whatsapp_cloud_api_token';

        if ( ! isset( $sanitized[ $key ] ) || ! is_scalar( $sanitized[ $key ] ) ) {
            return $sanitized;
        }

        $incoming = trim( (string) $sanitized[ $key ] );
        $stored = isset( $current[ $key ] ) && is_scalar( $current[ $key ] ) ? trim( (string) $current[ $key ] ) : '';

        if ( '' === $incoming || Token_Vault::is_encrypted( $incoming ) ) {
            return $sanitized;
        }

        if ( Token_Vault::is_encrypted( $stored ) && Token_Vault::decrypt( $stored ) === $incoming ) {
            $sanitized[ $key ] = $stored;

            return $sanitized;
        }

        $sanitized[ $key ] = Token_Vault::encrypt( $incoming );

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

		delete_transient( 'joinotify_api_request_cache' );
		delete_transient( 'joinotify_api_response_cache' );
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

        if ( 'color' === $type ) {
            return self::sanitize_color_value( $value );
        }

        if ( 'color-scale' === $type ) {
            return self::sanitize_color_scale_value( $value );
        }

        if ( is_array( $value ) ) {
            return self::sanitize_array_value( $value );
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


    /**
     * Sanitize a plain nested array setting.
     *
     * @param array<mixed> $value
     * @return array<mixed>
     */
    private static function sanitize_array_value( $value ) {
        $sanitized = array();

        foreach ( $value as $item_key => $item_value ) {
            if ( is_array( $item_value ) ) {
                $sanitized[ $item_key ] = self::sanitize_array_value( $item_value );
                continue;
            }

            if ( is_bool( $item_value ) ) {
                $sanitized[ $item_key ] = $item_value;
                continue;
            }

            if ( is_int( $item_value ) || is_float( $item_value ) ) {
                $sanitized[ $item_key ] = $item_value;
                continue;
            }

            $sanitized[ $item_key ] = sanitize_text_field( (string) $item_value );
        }

        return $sanitized;
    }


    /**
     * Sanitize a hex color value.
     *
     * @param mixed $value
     * @return string
     */
    private static function sanitize_color_value( $value ) {
        $value = is_string( $value ) ? trim( $value ) : '';

        if ( '' === $value ) {
            return '';
        }

        if ( function_exists( 'sanitize_hex_color' ) ) {
            $sanitized = sanitize_hex_color( $value );

            if ( null !== $sanitized ) {
                return $sanitized;
            }
        }

        return sanitize_text_field( $value );
    }


    /**
     * Sanitize a color scale payload.
     *
     * @param mixed $value
     * @return array<string,mixed>
     */
    private static function sanitize_color_scale_value( $value ) {
        $value = is_array( $value ) ? $value : array();

        $base_color = isset( $value['baseColor'] ) ? self::sanitize_color_value( $value['baseColor'] ) : '';
        $palette = array();

        if ( ! empty( $value['palette'] ) && is_array( $value['palette'] ) ) {
            foreach ( $value['palette'] as $palette_color ) {
                $sanitized_color = self::sanitize_color_value( $palette_color );

                if ( '' !== $sanitized_color ) {
                    $palette[] = $sanitized_color;
                }
            }
        }

        return array(
            'baseColor' => $base_color,
            'palette' => $palette,
        );
    }
}
