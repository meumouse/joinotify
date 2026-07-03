<?php

namespace MeuMouse\Joinotify\Otp_Login;

defined('ABSPATH') || exit;

/**
 * Generate a luminance-based color palette for the login UI theme.
 *
 * Produces the same Tailwind-style shade scale the frontend derives, so the
 * server-rendered login form can be themed with CSS custom properties that
 * match the color the admin picked in the integration modal.
 *
 * @since 2.0.0
 * @package MeuMouse\Joinotify\Otp_Login
 * @author MeuMouse.com
 */
class Color_Scheme {

    /**
     * Tailwind-style steps used to generate the palette scale.
     *
     * @since 2.0.0
     * @var int[]
     */
    public static $steps = array( 0, 50, 100, 200, 300, 400, 500, 600, 700, 800, 900, 950 );

    /**
     * Generate a luminance-based palette from a single base color.
     *
     * @since 2.0.0
     * @param string $hex_color Base hex color.
     * @return array<int,array{step:string,color:string}> Generated palette rows.
     */
    public static function generate_palette( $hex_color ) {
        $hex_color = self::sanitize_hex( $hex_color );

        if ( empty( $hex_color ) ) {
            $hex_color = '#4f46e5';
        }

        $input_color = self::hex_to_rgb( $hex_color );
        $input_luminance = self::luminance( $input_color[0], $input_color[1], $input_color[2] );
        $lightest_color = array( 245, 245, 245 );
        $darkest_color = array( 8, 8, 8 );
        $lightest_luminance = self::luminance( 245, 245, 245 );
        $darkest_luminance = self::luminance( 8, 8, 8 );
        $luminance_range = $lightest_luminance - $darkest_luminance;
        $colors = array(
            array(
                'step' => '0',
                'color' => '#ffffff',
            ),
        );

        foreach ( self::$steps as $step ) {
            if ( 0 === (int) $step ) {
                continue;
            }

            $target_luminance = $lightest_luminance - ( $step / 1000 ) * $luminance_range;

            if ( $target_luminance > $input_luminance ) {
                $factor = ( $target_luminance - $input_luminance ) / max( $lightest_luminance - $input_luminance, 0.0001 );
                $result_color = self::interpolate_color( $input_color, $lightest_color, $factor );
            } else {
                $factor = ( $input_luminance - $target_luminance ) / max( $input_luminance - $darkest_luminance, 0.0001 );
                $result_color = self::interpolate_color( $input_color, $darkest_color, $factor );
            }

            $colors[] = array(
                'step' => (string) $step,
                'color' => self::rgb_to_hex( $result_color ),
            );
        }

        return $colors;
    }

    /**
     * Normalize a hex color value to the format expected by the palette builder.
     *
     * @since 2.0.0
     * @param string $hex_color Submitted color.
     * @return string Sanitized hex color or an empty string.
     */
    public static function sanitize_hex( $hex_color ) {
        $hex_color = sanitize_hex_color( $hex_color );

        return $hex_color ? $hex_color : '';
    }

    /**
     * Convert a hex color to RGB channels.
     *
     * @since 2.0.0
     * @param string $hex_color Hex color value.
     * @return int[] RGB channels.
     */
    private static function hex_to_rgb( $hex_color ) {
        $hex_color = ltrim( (string) $hex_color, '#' );

        return array(
            hexdec( substr( $hex_color, 0, 2 ) ),
            hexdec( substr( $hex_color, 2, 2 ) ),
            hexdec( substr( $hex_color, 4, 2 ) ),
        );
    }

    /**
     * Calculate relative luminance for an RGB color.
     *
     * @since 2.0.0
     * @param int $r Red channel.
     * @param int $g Green channel.
     * @param int $b Blue channel.
     * @return float Relative luminance.
     */
    private static function luminance( $r, $g, $b ) {
        $channels = array( $r, $g, $b );
        $adjusted = array_map(
            function( $value ) {
                $value = $value / 255;
                return $value <= 0.03928 ? $value / 12.92 : pow( ( $value + 0.055 ) / 1.055, 2.4 );
            },
            $channels
        );

        return $adjusted[0] * 0.2126 + $adjusted[1] * 0.7152 + $adjusted[2] * 0.0722;
    }

    /**
     * Interpolate between two colors by a bounded factor.
     *
     * @since 2.0.0
     * @param int[] $color1 Source color.
     * @param int[] $color2 Target color.
     * @param float $factor Interpolation factor from 0 to 1.
     * @return int[] Interpolated RGB color.
     */
    private static function interpolate_color( array $color1, array $color2, $factor ) {
        $factor = max( 0, min( 1, (float) $factor ) );
        $result = array();

        foreach ( $color1 as $index => $channel ) {
            $result[] = (int) round( $channel + ( $color2[ $index ] - $channel ) * $factor );
        }

        return $result;
    }

    /**
     * Convert RGB channels back to a hex string.
     *
     * @since 2.0.0
     * @param int[] $color RGB channels.
     * @return string Hex color string.
     */
    private static function rgb_to_hex( array $color ) {
        $color = array_map(
            function( $value ) {
                return max( 0, min( 255, (int) $value ) );
            },
            $color
        );

        return sprintf( '#%02x%02x%02x', $color[0], $color[1], $color[2] );
    }
}
