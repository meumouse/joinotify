<?php

namespace MeuMouse\Joinotify\Core;

use MeuMouse\Joinotify\Admin\Admin;
use MeuMouse\Joinotify\Integrations\Integrations_Base;

// Exit if accessed directly.
defined('ABSPATH') || exit;

/**
 * Opt-in usage reporting.
 *
 * Two rules shape this class. First, nothing is collected unless the site owner
 * turned it on: `collect()` is only ever reached through `is_enabled()`, and the
 * setting starts at 'no' on every install. Second, whatever is collected has to
 * be something the owner can read for themselves before agreeing — the setup
 * wizard renders `preview()` verbatim, so this payload is the whole story.
 *
 * That rules out anything identifying: no site URL, no admin e-mail, no phone
 * numbers, no contacts, no message content, no workflow contents. What is left
 * is the environment a bug report needs plus counts.
 *
 * Delivery is deliberately not wired up. `endpoint()` is empty by default, so
 * `maybe_send()` is a no-op until a destination is supplied through the filter.
 *
 * @since 2.4.0
 * @package MeuMouse\Joinotify\Core
 * @author MeuMouse.com
 */
class Telemetry {

    /**
     * Settings key holding the owner's consent.
     *
     * @since 2.4.0
     * @var string
     */
    const SETTING = 'enable_usage_tracking';


    /**
     * Whether the site owner agreed to share usage data.
     *
     * @since 2.4.0
     * @return bool
     */
    public static function is_enabled() {
        return 'yes' === Admin::get_setting( self::SETTING );
    }


    /**
     * Where a report would be sent.
     *
     * Empty by default: the plugin ships with no destination, so consent alone
     * never causes a request to leave the site.
     *
     * @since 2.4.0
     * @return string
     */
    public static function endpoint() {
        /**
         * Filter the usage-reporting endpoint.
         *
         * @since 2.4.0
         * @param string $endpoint Absolute URL, or an empty string to disable.
         */
        $endpoint = apply_filters( 'Joinotify/Telemetry/Endpoint', '' );

        return is_string( $endpoint ) ? trim( $endpoint ) : '';
    }


    /**
     * Build the report payload.
     *
     * Safe to call regardless of consent — the caller decides what to do with
     * it. The wizard uses it to show the user exactly what they are agreeing to.
     *
     * @since 2.4.0
     * @return array<string,mixed>
     */
    public static function collect() {
        global $wp_version;

        $payload = array(
            'plugin_version' => defined('JOINOTIFY_VERSION') ? JOINOTIFY_VERSION : '',
            'wordpress_version' => isset( $wp_version ) ? (string) $wp_version : get_bloginfo('version'),
            'php_version' => PHP_VERSION,
            'locale' => function_exists('determine_locale') ? determine_locale() : get_locale(),
            'is_multisite' => is_multisite(),
            'active_integrations' => self::active_integrations(),
            'workflow_counts' => self::workflow_counts(),
            'error_counts' => self::error_counts(),
        );

        /**
         * Filter the collected usage payload.
         *
         * Extensions may add their own counters here. Never add anything that
         * identifies the site, its visitors or its customers.
         *
         * @since 2.4.0
         * @param array<string,mixed> $payload
         */
        return apply_filters( 'Joinotify/Telemetry/Payload', $payload );
    }


    /**
     * Human-readable preview of the payload, for the consent screen.
     *
     * @since 2.4.0
     * @return array{collected:array<string,mixed>,never_collected:array<int,string>}
     */
    public static function preview() {
        return array(
            'collected' => self::collect(),
            'never_collected' => array(
                esc_html__( 'Your site address, name or admin e-mail', 'joinotify' ),
                esc_html__( 'Phone numbers, contacts or customer data', 'joinotify' ),
                esc_html__( 'Message content, media or message history', 'joinotify' ),
                esc_html__( 'API keys, tokens or any other credential', 'joinotify' ),
                esc_html__( 'The content of your workflows', 'joinotify' ),
            ),
        );
    }


    /**
     * Send a report, if and only if consent was given and a destination exists.
     *
     * @since 2.4.0
     * @return bool Whether a request was actually made.
     */
    public static function maybe_send() {
        if ( ! self::is_enabled() ) {
            return false;
        }

        $endpoint = self::endpoint();

        if ( '' === $endpoint ) {
            return false;
        }

        $response = wp_remote_post( $endpoint, array(
            'headers' => array(
                'Content-Type' => 'application/json',
                'Accept' => 'application/json',
            ),
            'body' => wp_json_encode( self::collect() ),
            'timeout' => 15,
            'blocking' => false,
        ) );

        if ( is_wp_error( $response ) ) {
            Logger::register_log( 'Joinotify: usage report could not be delivered: ' . $response->get_error_message(), 'WARNING' );

            return false;
        }

        return true;
    }


    /**
     * List the integration slugs currently switched on.
     *
     * @since 2.4.0
     * @return array<int,string>
     */
    private static function active_integrations() {
        $active = array();

        foreach ( Integrations_Base::integration_tab_items() as $slug => $item ) {
            $setting_key = is_array( $item ) && ! empty( $item['setting_key'] ) ? (string) $item['setting_key'] : '';

            if ( '' === $setting_key ) {
                continue;
            }

            if ( 'yes' === Admin::get_setting( $setting_key ) ) {
                $active[] = (string) $slug;
            }
        }

        sort( $active );

        return $active;
    }


    /**
     * Count workflows by post status.
     *
     * @since 2.4.0
     * @return array<string,int>
     */
    private static function workflow_counts() {
        $counts = wp_count_posts('joinotify-workflow');

        if ( ! is_object( $counts ) ) {
            return array();
        }

        return array(
            'publish' => isset( $counts->publish ) ? (int) $counts->publish : 0,
            'draft' => isset( $counts->draft ) ? (int) $counts->draft : 0,
        );
    }


    /**
     * Count recorded log entries by level.
     *
     * @since 2.4.0
     * @return array<string,int>
     */
    private static function error_counts() {
        if ( ! class_exists( Debug_Log::class ) || ! method_exists( Debug_Log::class, 'get_counts_by_level' ) ) {
            return array();
        }

        $counts = Debug_Log::get_counts_by_level();

        return is_array( $counts ) ? array_map( 'intval', $counts ) : array();
    }
}
