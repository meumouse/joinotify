<?php

namespace MeuMouse\Joinotify\Otp_Login;

defined('ABSPATH') || exit;

/**
 * Read OTP Login settings from Joinotify's shared options array.
 *
 * Every key lives inside the shared `joinotify_settings` option, alongside the
 * rest of the plugin settings, and is declared as the OTP Login integration
 * card defaults (see Integrations\Otp_Login).
 *
 * @since 2.0.0
 * @package MeuMouse\Joinotify\Otp_Login
 * @author MeuMouse.com
 */
class Settings {

    /**
     * Shared Joinotify options key.
     *
     * @since 2.0.0
     * @var string
     */
    const OPTION_NAME = 'joinotify_settings';

    /**
     * Return a single setting value with a default fallback.
     *
     * @since 2.0.0
     * @param string $key Option key.
     * @param mixed  $default Default value.
     * @return mixed
     */
    public static function get( $key, $default = null ) {
        $options = get_option( self::OPTION_NAME, array() );

        if ( is_array( $options ) && array_key_exists( $key, $options ) ) {
            return $options[ $key ];
        }

        return $default;
    }


    /**
     * Check whether the OTP login flow is enabled.
     *
     * @since 2.0.0
     * @return bool
     */
    public static function is_enabled() {
        return 'yes' === (string) self::get( 'enable_otp_login_integration', 'no' );
    }


    /**
     * Resolve the active delivery channel id.
     *
     * @since 2.0.0
     * @return string
     */
    public static function get_channel() {
        $channel = sanitize_key( (string) self::get( 'otp_login_channel', 'whatsapp' ) );

        return '' !== $channel ? $channel : 'whatsapp';
    }


    /**
     * Resolve the configured primary color.
     *
     * @since 2.0.0
     * @return string
     */
    public static function get_primary_color() {
        $color = Color_Scheme::sanitize_hex( (string) self::get( 'otp_login_primary_color', '#4f46e5' ) );

        return ! empty( $color ) ? $color : '#4f46e5';
    }


    /**
     * Resolve the configured border radius value.
     *
     * @since 2.0.0
     * @return string
     */
    public static function get_border_radius_value() {
        $value = self::get( 'otp_login_border_radius_value', '0.375' );
        $value = is_numeric( $value ) ? (string) $value : trim( (string) $value );

        if ( '' === $value ) {
            $value = '0.375';
        }

        return self::normalize_number_string( $value );
    }


    /**
     * Resolve the configured border radius unit.
     *
     * @since 2.0.0
     * @return string
     */
    public static function get_border_radius_unit() {
        $unit = strtolower( trim( (string) self::get( 'otp_login_border_radius_unit', 'rem' ) ) );
        $allowed_units = array( 'px', 'em', 'rem', '%' );

        if ( ! in_array( $unit, $allowed_units, true ) ) {
            $unit = 'rem';
        }

        return $unit;
    }


    /**
     * Resolve the configured border radius as a CSS value.
     *
     * @since 2.0.0
     * @return string
     */
    public static function get_border_radius() {
        return self::get_border_radius_value() . self::get_border_radius_unit();
    }


    /**
     * Resolve the palette rows, using stored overrides when available.
     *
     * @since 2.0.0
     * @return array<int,array{step:string,color:string}>
     */
    public static function get_palette_rows() {
        $palette = array();

        foreach ( Color_Scheme::generate_palette( self::get_primary_color() ) as $row ) {
            if ( ! is_array( $row ) || empty( $row['step'] ) || empty( $row['color'] ) ) {
                continue;
            }

            $palette[ (string) $row['step'] ] = (string) $row['color'];
        }

        $stored_palette = self::get( 'otp_login_palette', array() );

        if ( is_array( $stored_palette ) ) {
            foreach ( $stored_palette as $step => $color ) {
                $color = Color_Scheme::sanitize_hex( (string) $color );

                if ( empty( $color ) ) {
                    continue;
                }

                $palette[ (string) $step ] = $color;
            }
        }

        $rows = array();

        foreach ( self::palette_steps() as $step ) {
            $rows[] = array(
                'step' => (string) $step,
                'color' => isset( $palette[ (string) $step ] ) ? (string) $palette[ (string) $step ] : '#ffffff',
            );
        }

        return $rows;
    }


    /**
     * Convert the palette rows into a keyed map for the frontend theme.
     *
     * @since 2.0.0
     * @return array<string,string>
     */
    public static function get_palette_map() {
        $map = array();

        foreach ( self::get_palette_rows() as $row ) {
            if ( empty( $row['step'] ) || empty( $row['color'] ) ) {
                continue;
            }

            $map[ (string) $row['step'] ] = (string) $row['color'];
        }

        return $map;
    }


    /**
     * Get the configured sender phone number.
     *
     * @since 2.0.0
     * @return string
     */
    public static function get_sender_phone() {
        return sanitize_text_field( (string) self::get( 'otp_login_sender_phone', '' ) );
    }


    /**
     * Normalize the Joinotify sender list to a unique phone array.
     *
     * @since 2.0.0
     * @return string[]
     */
    public static function get_available_senders() {
        $senders = get_option( 'joinotify_get_phones_senders', array() );

        if ( ! is_array( $senders ) ) {
            $senders = array( $senders );
        }

        $normalized = array();

        foreach ( $senders as $sender ) {
            if ( is_array( $sender ) ) {
                $candidate = $sender['phone'] ?? $sender['number'] ?? $sender['value'] ?? $sender['sender'] ?? '';
            } else {
                $candidate = $sender;
            }

            $candidate = sanitize_text_field( (string) $candidate );

            if ( ! empty( $candidate ) ) {
                $normalized[] = $candidate;
            }
        }

        return array_values( array_unique( array_filter( $normalized ) ) );
    }


    /**
     * Resolve the selected sender, falling back to the first available one.
     *
     * @since 2.0.0
     * @return string
     */
    public static function get_selected_sender() {
        $available_senders = self::get_available_senders();

        if ( empty( $available_senders ) ) {
            return '';
        }

        $saved_sender = self::get_sender_phone();

        if ( ! empty( $saved_sender ) && in_array( $saved_sender, $available_senders, true ) ) {
            return $saved_sender;
        }

        return (string) reset( $available_senders );
    }


    /**
     * Fixed palette steps used by the modal and frontend theme map.
     *
     * @since 2.0.0
     * @return string[]
     */
    private static function palette_steps() {
        return array( '0', '50', '100', '200', '300', '400', '500', '600', '700', '800', '900', '950' );
    }


    /**
     * Normalize a numeric string so it is safe for CSS values.
     *
     * @since 2.0.0
     * @param string $value Numeric value.
     * @return string
     */
    private static function normalize_number_string( $value ) {
        $value = preg_replace( '/[^0-9.]/', '', (string) $value );

        if ( '' === $value ) {
            return '0.375';
        }

        if ( '.' === substr( $value, 0, 1 ) ) {
            $value = '0' . $value;
        }

        $value = rtrim( rtrim( $value, '0' ), '.' );

        return '' === $value ? '0' : $value;
    }
}
