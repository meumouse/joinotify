<?php

namespace MeuMouse\Joinotify\Otp_Login;

defined('ABSPATH') || exit;

/**
 * Utility helpers for phone normalization, lookup and UI masking.
 *
 * @since 2.0.0
 * @package MeuMouse\Joinotify\Otp_Login
 * @author MeuMouse.com
 */
class Phone_Utils {

    /**
     * Normalize a phone number to a simplified E.164-like format.
     *
     * @since 2.0.0
     * @param string $phone Raw phone number.
     * @return string Normalized phone prefixed with a plus sign, or an empty string.
     */
    public static function normalize( $phone ) {
        $digits = preg_replace( '/\D+/', '', (string) $phone );

        if ( empty( $digits ) ) {
            return '';
        }

        return '+' . $digits;
    }


    /**
     * Strip all non-digit characters from a phone number.
     *
     * @since 2.0.0
     * @param string $phone Raw phone number.
     * @return string Digits-only phone representation.
     */
    public static function digits_only( $phone ) {
        return preg_replace( '/\D+/', '', (string) $phone );
    }


    /**
     * Build a set of lookup variants for a phone number.
     *
     * The variants cover the full number and common shortened forms without
     * DDI/DDD prefixes so account lookup can match both complete and partial
     * numbers supplied by the user.
     *
     * @since 2.0.0
     * @param string $phone Raw or normalized phone number.
     * @return string[] Unique lookup variants.
     */
    public static function lookup_variants( $phone ) {
        $normalized = self::normalize( $phone );
        $digits = self::digits_only( $normalized );
        $variants = array_filter(
            array(
                $normalized,
                $digits,
            )
        );

        $country_code_lengths = self::get_known_country_code_lengths();

        foreach ( $country_code_lengths as $length ) {
            if ( strlen( $digits ) > $length ) {
                $variants[] = substr( $digits, $length );
            }
        }

        return array_values( array_unique( $variants ) );
    }


    /**
     * Get the known international country code lengths from Joinotify when available.
     *
     * @since 2.0.0
     * @return int[]
     */
    private static function get_known_country_code_lengths() {
        $lengths = array( 2, 4 );

        if ( class_exists( '\MeuMouse\Joinotify\Validations\Country_Codes' ) ) {
            $codes = \MeuMouse\Joinotify\Validations\Country_Codes::get_country_codes_for_validation();

            foreach ( (array) $codes as $code ) {
                $lengths[] = strlen( (string) $code );
            }
        }

        return array_values( array_unique( array_filter( $lengths ) ) );
    }


    /**
     * Check whether two phone numbers match using full and partial variants.
     *
     * @since 2.0.0
     * @param string $left First phone number.
     * @param string $right Second phone number.
     * @return bool True when both numbers match in any supported form.
     */
    public static function matches( $left, $right ) {
        $left_variants = self::lookup_variants( $left );
        $right_variants = self::lookup_variants( $right );

        return ! empty( array_intersect( $left_variants, $right_variants ) );
    }


    /**
     * Mask part of a phone number for safer frontend display.
     *
     * @since 2.0.0
     * @param string $phone Raw or normalized phone number.
     * @return string Masked phone number.
     */
    public static function mask( $phone ) {
        $normalized = self::normalize( $phone );
        $digits = self::digits_only( $normalized );

        if ( strlen( $digits ) <= 4 ) {
            return $normalized;
        }

        $visible_prefix = substr( $digits, 0, 4 );
        $visible_suffix = substr( $digits, -2 );
        $masked = str_repeat( '*', max( strlen( $digits ) - 6, 2 ) );

        return '+' . $visible_prefix . $masked . $visible_suffix;
    }
}
