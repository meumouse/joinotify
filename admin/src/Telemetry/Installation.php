<?php

namespace MeuMouse\Joinotify\Telemetry;

use MeuMouse\Joinotify\Admin\Admin;
use MeuMouse\Joinotify\Core\Debug_Log;
use MeuMouse\Joinotify\Integrations\Integrations_Base;

// Exit if accessed directly.
defined('ABSPATH') || exit;

/**
 * Who this site is, and what it is running.
 *
 * The identity is a random identifier generated here and stored here — never derived from
 * the site address. A hash of `home_url()` would look anonymous and would not be: domains
 * come from a finite, public, enumerable set, so anyone holding a list could reverse it.
 * Sending the address disguised is worse than sending it plainly, because it also breaks
 * the promise the setup wizard makes on our behalf.
 *
 * The identifier survives switching the setting off and on again. Regenerating it would
 * be the more private choice and was considered; it would also make one site look like
 * many, which turns the install count — the whole reason this exists — into fiction.
 * That trade is stated in the consent screen rather than hidden.
 *
 * The environment is only ever built at dispatch time or for the consent preview. It
 * costs several option reads and a post count, and neither belongs in a page request.
 *
 * @since 2.5.0
 * @package MeuMouse\Joinotify\Telemetry
 * @author MeuMouse.com
 */
class Installation {

    /**
     * Option holding the random installation identifier. Autoload disabled.
     *
     * @since 2.5.0
     * @var string
     */
    const ID_OPTION = 'joinotify_telemetry_id';


    /**
     * Client name, as the service's enum spells it.
     *
     * @since 2.5.0
     * @var string
     */
    const CLIENT = 'wordpress';


    /**
     * The identifier for this installation, generating it on first use.
     *
     * @since 2.5.0
     * @return string
     */
    public static function id() {
        $id = get_option( self::ID_OPTION, '' );

        if ( is_string( $id ) && '' !== $id ) {
            return $id;
        }

        $id = wp_generate_uuid4();

        add_option( self::ID_OPTION, $id, '', 'no' );

        return $id;
    }


    /**
     * The identifier if one exists, without creating it.
     *
     * Building the settings screen must not be what brings an installation into being:
     * `id()` writes an option, and a site that will never consent has no business getting
     * one because somebody opened a tab.
     *
     * @since 2.5.0
     * @return string Empty when telemetry has never run here.
     */
    public static function peek() {
        $id = get_option( self::ID_OPTION, '' );

        return is_string( $id ) ? $id : '';
    }


    /**
     * Forget the identifier, so the next report starts a new installation.
     *
     * Only wired to the settings reset — never to the consent toggle.
     *
     * @since 2.5.0
     * @return void
     */
    public static function reset_id() {
        delete_option( self::ID_OPTION );
    }


    /**
     * The installation block of the request body.
     *
     * @since 2.5.0
     * @param bool $opted_out | Whether to mark this report as an opt-out notice.
     * @return array<string,mixed>
     */
    public static function snapshot( $opted_out = false ) {
        global $wp_version;

        $snapshot = array(
            'id' => self::id(),
            'client' => self::CLIENT,
            'clientVersion' => Normalizer::version( defined('JOINOTIFY_VERSION') ? JOINOTIFY_VERSION : '' ),
            'platform' => self::CLIENT,
            'platformVersion' => Normalizer::version( isset( $wp_version ) ? (string) $wp_version : get_bloginfo('version') ),
            'runtime' => 'php',
            'runtimeVersion' => self::php_version(),
            'locale' => Normalizer::slug( function_exists('determine_locale') ? determine_locale() : get_locale() ),
            'timezone' => self::timezone(),
            'environment' => self::environment(),
        );

        if ( $opted_out ) {
            $snapshot['optOut'] = true;
        }

        // A field the service would reject is worse than a field it never saw: the whole
        // batch comes back 422 and the buffer stops draining. Anything that failed
        // normalization is dropped here rather than argued about there.
        return array_filter( $snapshot, function( $value ) {
            return null !== $value && array() !== $value;
        } );
    }


    /**
     * PHP version, without the distribution suffix.
     *
     * `PHP_VERSION` on a Debian-derived host reads like
     * '8.2.20-1+ubuntu22.04.1+deb.sury.org', which is longer than the service accepts and
     * says nothing the three numbers do not.
     *
     * @since 2.5.0
     * @return string|null
     */
    private static function php_version() {
        $version = PHP_MAJOR_VERSION . '.' . PHP_MINOR_VERSION . '.' . PHP_RELEASE_VERSION;

        return Normalizer::version( $version );
    }


    /**
     * IANA time zone, or nothing.
     *
     * `wp_timezone_string()` falls back to a UTC offset like '-03:00' when the site was
     * configured that way. That is not a time zone, it fails the service's slug shape,
     * and guessing a zone from an offset would be wrong twice a year.
     *
     * @since 2.5.0
     * @return string|null
     */
    private static function timezone() {
        $timezone = function_exists('wp_timezone_string') ? wp_timezone_string() : '';

        if ( ! is_string( $timezone ) || false === strpos( $timezone, '/' ) ) {
            return null;
        }

        return Normalizer::slug( str_replace( '/', '.', $timezone ) );
    }


    /**
     * What is installed alongside the plugin.
     *
     * @since 2.5.0
     * @return array<string,mixed>
     */
    public static function environment() {
        $environment = array(
            'multisite' => is_multisite(),
            'httpsEnabled' => 0 === strpos( (string) get_option( 'home', '' ), 'https://' ),
            'cronDisabled' => defined('DISABLE_WP_CRON') && DISABLE_WP_CRON,
            // The plugin being active, not our integration toggle being on: the question
            // this answers is "what runs on this site", and a store that never switched
            // the integration on is still a store.
            'wooActive' => class_exists('WooCommerce'),
            'wooVersion' => defined('WC_VERSION') ? WC_VERSION : null,
            // `defined()` rather than `did_action('elementor/loaded')`, which is false in
            // cron — and cron is where this snapshot is usually built.
            'elementorActive' => defined('ELEMENTOR_VERSION'),
            'activeIntegrations' => self::active_integrations(),
            'workflowsPublished' => self::workflow_count('publish'),
            'workflowsDraft' => self::workflow_count('draft'),
        );

        return Event_Catalog::filter_environment( $environment );
    }


    /**
     * Integration slugs currently switched on.
     *
     * @since 2.5.0
     * @return array<int,string>
     */
    private static function active_integrations() {
        $active = array();

        if ( ! class_exists( Integrations_Base::class ) ) {
            return $active;
        }

        foreach ( Integrations_Base::integration_tab_items() as $slug => $item ) {
            $setting_key = is_array( $item ) && ! empty( $item['setting_key'] ) ? (string) $item['setting_key'] : '';

            if ( '' === $setting_key ) {
                continue;
            }

            if ( 'yes' === Admin::get_setting( $setting_key ) ) {
                $active[] = (string) $slug;
            }
        }

        return $active;
    }


    /**
     * Count workflows in a given post status.
     *
     * @since 2.5.0
     * @param string $status | Post status.
     * @return int
     */
    private static function workflow_count( $status ) {
        $counts = wp_count_posts('joinotify-workflow');

        if ( ! is_object( $counts ) || ! isset( $counts->$status ) ) {
            return 0;
        }

        return (int) $counts->$status;
    }


    /**
     * Counts of recorded log entries by level, for the consent preview.
     *
     * Not part of the request body: the service derives failure counts from the events
     * themselves, and sending the same number twice would let the two disagree.
     *
     * @since 2.5.0
     * @return array<string,int>
     */
    public static function error_counts() {
        if ( ! class_exists( Debug_Log::class ) || ! method_exists( Debug_Log::class, 'get_counts_by_level' ) ) {
            return array();
        }

        $counts = Debug_Log::get_counts_by_level();

        return is_array( $counts ) ? array_map( 'intval', $counts ) : array();
    }
}
