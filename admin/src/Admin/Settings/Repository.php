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
