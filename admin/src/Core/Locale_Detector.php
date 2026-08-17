<?php

namespace MeuMouse\Joinotify\Core;

use MeuMouse\Joinotify\Validations\Country_Codes;
use DateTimeZone;

// Exit if accessed directly.
defined('ABSPATH') || exit;

/**
 * Guess which country a site belongs to, so the setup wizard can pre-select the
 * fallback dial code instead of asking a question the site already answers.
 *
 * Three sources are consulted, strongest first. A WooCommerce store address is
 * a deliberate statement about where the business operates; the site locale is
 * a strong hint; the timezone is the last resort because a single zone can be
 * shared by several countries. The result is only a suggestion — the wizard
 * always shows the full list and the user can override it.
 *
 * @since 2.3.0
 * @package MeuMouse\Joinotify\Core
 * @author MeuMouse.com
 */
class Locale_Detector {

    /**
     * Dial code used when nothing can be inferred.
     *
     * @since 2.3.0
     * @var string
     */
    const FALLBACK_CODE = '55';


    /**
     * Suggest the default country dial code for this site.
     *
     * @since 2.3.0
     * @return array{code:string,iso:string,source:string} Dial code, ISO 3166-1
     *         alpha-2 code and which source produced them ('woocommerce',
     *         'locale', 'timezone' or 'fallback').
     */
    public static function suggest_country_code() {
        $resolvers = array(
            'woocommerce' => array( __CLASS__, 'iso_from_woocommerce' ),
            'locale' => array( __CLASS__, 'iso_from_locale' ),
            'timezone' => array( __CLASS__, 'iso_from_timezone' ),
        );

        foreach ( $resolvers as $source => $resolver ) {
            $iso = call_user_func( $resolver );

            if ( '' === $iso ) {
                continue;
            }

            $code = self::dial_code_for_iso( $iso );

            if ( '' === $code ) {
                continue;
            }

            $suggestion = array(
                'code' => $code,
                'iso' => $iso,
                'source' => $source,
            );

            /**
             * Filter the country suggested for a fresh install.
             *
             * @since 2.3.0
             * @param array<string,string> $suggestion {code, iso, source}.
             */
            return apply_filters( 'Joinotify/Core/Suggested_Country', $suggestion );
        }

        return apply_filters( 'Joinotify/Core/Suggested_Country', array(
            'code' => self::FALLBACK_CODE,
            'iso' => 'BR',
            'source' => 'fallback',
        ) );
    }


    /**
     * Read the store base country configured in WooCommerce.
     *
     * @since 2.3.0
     * @return string ISO alpha-2 code, or an empty string.
     */
    public static function iso_from_woocommerce() {
        if ( ! function_exists('wc_get_base_location') ) {
            return '';
        }

        $location = wc_get_base_location();
        $country = is_array( $location ) && isset( $location['country'] ) ? (string) $location['country'] : '';

        return self::normalize_iso( $country );
    }


    /**
     * Read the region out of the site locale (`pt_BR` -> `BR`).
     *
     * @since 2.3.0
     * @return string ISO alpha-2 code, or an empty string.
     */
    public static function iso_from_locale() {
        $locale = function_exists('determine_locale') ? determine_locale() : get_locale();

        if ( ! is_string( $locale ) || ! preg_match( '/^[a-z]{2,3}_([A-Z]{2})/', $locale, $matches ) ) {
            return '';
        }

        return self::normalize_iso( $matches[1] );
    }


    /**
     * Find which country owns the site timezone.
     *
     * PHP already ships the zone-to-country table, so the country is resolved by
     * asking each known country for its zones instead of maintaining a map here.
     * Zones shared by more than one country simply resolve to whichever known
     * country claims them first, which is acceptable for a suggestion.
     *
     * @since 2.3.0
     * @return string ISO alpha-2 code, or an empty string.
     */
    public static function iso_from_timezone() {
        $timezone = (string) get_option( 'timezone_string', '' );

        if ( '' === $timezone || ! class_exists('DateTimeZone') ) {
            return '';
        }

        foreach ( self::iso_to_dial_code_map() as $iso => $code ) {
            $zones = @DateTimeZone::listIdentifiers( DateTimeZone::PER_COUNTRY, $iso );

            if ( is_array( $zones ) && in_array( $timezone, $zones, true ) ) {
                return $iso;
            }
        }

        return '';
    }


    /**
     * Translate an ISO alpha-2 code into the dial code the plugin stores.
     *
     * @since 2.3.0
     * @param string $iso ISO 3166-1 alpha-2 code.
     * @return string Dial code without the plus sign, or an empty string.
     */
    public static function dial_code_for_iso( $iso ) {
        $iso = self::normalize_iso( $iso );

        if ( '' === $iso ) {
            return '';
        }

        $map = self::iso_to_dial_code_map();

        return isset( $map[ $iso ] ) ? $map[ $iso ] : '';
    }


    /**
     * Invert the plugin's dial-code table into an ISO-keyed lookup.
     *
     * The source table is `dial code => [ ISO => country name ]`, which is the
     * shape the settings select needs; this is the same data read the other way.
     *
     * @since 2.3.0
     * @return array<string,string> ISO alpha-2 code => dial code.
     */
    public static function iso_to_dial_code_map() {
        static $map = null;

        if ( null !== $map ) {
            return $map;
        }

        $map = array();

        foreach ( Country_Codes::get_country_codes_with_names() as $dial_code => $countries ) {
            if ( ! is_array( $countries ) ) {
                continue;
            }

            foreach ( array_keys( $countries ) as $iso ) {
                $iso = self::normalize_iso( $iso );

                // First writer wins: shared dial codes (+1 is US and CA) keep the
                // order declared in the table.
                if ( '' !== $iso && ! isset( $map[ $iso ] ) ) {
                    $map[ $iso ] = (string) $dial_code;
                }
            }
        }

        return $map;
    }


    /**
     * Normalize a country code to upper-case ISO alpha-2.
     *
     * @since 2.3.0
     * @param mixed $iso Raw value.
     * @return string
     */
    private static function normalize_iso( $iso ) {
        $iso = is_scalar( $iso ) ? strtoupper( trim( (string) $iso ) ) : '';

        return preg_match( '/^[A-Z]{2}$/', $iso ) ? $iso : '';
    }
}
