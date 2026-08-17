<?php

namespace MeuMouse\Joinotify\Core;

use MeuMouse\Joinotify\Admin\Admin;
use MeuMouse\Joinotify\Api\Cloud_Client;
use MeuMouse\Joinotify\Telemetry\Dispatcher;
use MeuMouse\Joinotify\Telemetry\Event_Catalog;
use MeuMouse\Joinotify\Telemetry\Installation;

// Exit if accessed directly.
defined('ABSPATH') || exit;

/**
 * Opt-in usage reporting — the public face of the telemetry module.
 *
 * Two rules shape this class. First, nothing is collected unless the site owner turned it
 * on: the setting starts at 'no' on every install, and no request leaves the site before
 * it is 'yes'. Second, whatever is collected has to be something the owner can read for
 * themselves before agreeing — the setup wizard renders `preview()` verbatim, so that
 * payload is the whole story.
 *
 * That rules out anything identifying: no site URL, no admin e-mail, no phone numbers, no
 * contacts, no message content, no workflow contents. What is left is the environment a
 * bug report needs, plus counts, plus a list of named events drawn from a fixed catalog.
 *
 * The site is identified by a random value generated here (see `Telemetry\Installation`)
 * and by the API key it already uses to send messages. The address is never sent — which
 * also means support cannot look a site up by domain, and is why the identifier is shown
 * in the settings screen for people to quote in a ticket.
 *
 * The machinery lives in `MeuMouse\Joinotify\Telemetry`; this class stays as the stable
 * surface other code and third-party filters already point at.
 *
 * @since 2.4.0
 * @version 2.5.0
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
     * Where a report is sent.
     *
     * An empty string still disables delivery entirely, and that remains the documented
     * escape hatch for a host that wants the plugin to talk to nothing.
     *
     * @since 2.4.0
     * @version 2.5.0
     * @return string
     */
    public static function endpoint() {
        $default = class_exists( Cloud_Client::class ) ? Cloud_Client::base_url() . '/telemetry' : '';

        /**
         * Filter the usage-reporting endpoint.
         *
         * @since 2.4.0
         * @param string $endpoint Absolute URL, or an empty string to disable.
         */
        $endpoint = apply_filters( 'Joinotify/Telemetry/Endpoint', $default );

        return is_string( $endpoint ) ? trim( $endpoint ) : '';
    }


    /**
     * Build the installation block of a report.
     *
     * Safe to call regardless of consent — the caller decides what to do with it. The
     * wizard uses it to show the user exactly what they are agreeing to.
     *
     * The shape changed in 2.5.0, from an ad-hoc snapshot to the object the service
     * accepts. Anything a filter adds is put through the same allow-list as the rest: a
     * key the service does not know would cost the whole batch, not just that key.
     *
     * @since 2.4.0
     * @version 2.5.0
     * @return array<string,mixed>
     */
    public static function collect() {
        $payload = Installation::snapshot();

        /**
         * Filter the collected usage payload.
         *
         * Extensions may add their own counters here. Never add anything that identifies
         * the site, its visitors or its customers. Keys outside the service's catalog are
         * dropped before the request is built.
         *
         * @since 2.4.0
         * @param array<string,mixed> $payload
         */
        $payload = apply_filters( 'Joinotify/Telemetry/Payload', $payload );

        if ( ! is_array( $payload ) ) {
            return Installation::snapshot();
        }

        if ( isset( $payload['environment'] ) ) {
            $payload['environment'] = Event_Catalog::filter_environment( $payload['environment'] );
        }

        return $payload;
    }


    /**
     * Human-readable preview of what would be sent, for the consent screen.
     *
     * @since 2.4.0
     * @version 2.5.0
     * @return array{collected:array<string,mixed>,sample_events:array<int,array<string,mixed>>,never_collected:array<int,string>,identified_by:string}
     */
    public static function preview() {
        return array(
            'collected' => self::collect(),
            'sample_events' => Event_Catalog::samples(),
            'never_collected' => array(
                esc_html__( 'Your site address, name or admin e-mail', 'joinotify' ),
                esc_html__( 'Phone numbers, contacts or customer data', 'joinotify' ),
                esc_html__( 'Message content, media or message history', 'joinotify' ),
                esc_html__( 'API keys, tokens or any other credential', 'joinotify' ),
                esc_html__( 'The content of your workflows', 'joinotify' ),
            ),
            /**
             * Stated plainly because the list above would otherwise be true and
             * misleading at the same time: the report is authenticated with the same key
             * the site already uses to send messages, so the service knows which account
             * it belongs to.
             */
            'identified_by' => esc_html__( 'A random identifier generated on this site, plus the Joinotify API key you already use to send messages. Your site address is never sent.', 'joinotify' ),
        );
    }


    /**
     * Send a report, if and only if consent was given and a destination exists.
     *
     * @since 2.4.0
     * @version 2.5.0
     * @return bool Whether a request was actually made.
     */
    public static function maybe_send() {
        return Dispatcher::dispatch();
    }
}
