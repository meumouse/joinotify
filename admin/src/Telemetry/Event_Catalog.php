<?php

namespace MeuMouse\Joinotify\Telemetry;

// Exit if accessed directly.
defined('ABSPATH') || exit;

/**
 * Local mirror of the catalog the service accepts.
 *
 * The service already drops an event it does not know and strips a property it did not
 * declare, so this class is, strictly speaking, redundant. It exists anyway for one
 * reason: without it, "the service discarded something" would be normal background noise,
 * and a genuine mismatch between the two sides would be invisible inside it. With the
 * mirror in place, a non-zero `discarded` in the response means exactly one thing — the
 * two catalogs drifted apart — and that is worth a warning in the log.
 *
 * It also keeps anything rejected from ever reaching the buffer, so a client bug cannot
 * fill a site's options table with events that were never going to be accepted.
 *
 * Keep this file in step with `src/telemetry/registry.ts` on the service. A name added
 * here and not there is silently dropped; a name added there and not here is never sent.
 *
 * @since 2.5.0
 * @package MeuMouse\Joinotify\Telemetry
 * @author MeuMouse.com
 */
class Event_Catalog {

    /**
     * Transports that map onto the service enum.
     *
     * Telegram and Resend are deliberately absent: they are real channels, but they do
     * not fit this enum, and an event carrying an unknown transport would be stripped
     * down to a half-event. Listeners check this list and emit nothing instead.
     *
     * @since 2.5.0
     * @var array<int,string>
     */
    const TRANSPORTS = array( 'cloud', 'evolution' );


    /**
     * Message types that map onto the service enum.
     *
     * @since 2.5.0
     * @var array<int,string>
     */
    const MESSAGE_TYPES = array( 'text', 'template', 'media', 'test' );


    /**
     * Every event the service accepts, with its allowed properties.
     *
     * @since 2.5.0
     * @return array<string,array<string,mixed>>
     */
    public static function events() {
        $transport = array( 'kind' => 'enum', 'values' => self::TRANSPORTS );
        $message_type = array( 'kind' => 'enum', 'values' => self::MESSAGE_TYPES );
        $http_status = array( 'kind' => 'number', 'min' => 100, 'max' => 599 );
        $slug = array( 'kind' => 'slug' );

        return array(
            // Lifecycle. Protected from trimming: the service only records each of these
            // once, so losing one to a burst of errors loses it for good.
            'plugin.activated' => array( 'props' => array(), 'protected' => true ),
            'plugin.deactivated' => array( 'props' => array(), 'protected' => true ),
            'plugin.updated' => array(
                'props' => array(
                    'previousVersion' => array( 'kind' => 'version' ),
                    'newVersion' => array( 'kind' => 'version' ),
                ),
                'protected' => true,
            ),

            // Install funnel.
            'onboarding.sender_selected' => array(
                'props' => array( 'method' => array( 'kind' => 'enum', 'values' => array( 'auto', 'manual' ) ) ),
                'protected' => true,
            ),

            // Feature usage. Coalesced to one per day per distinct set of properties —
            // see Buffer::merge(). A busy store fires these thousands of times a day and
            // the answer we need from them ("which features run, on how many sites")
            // survives that reduction untouched.
            'feature.used' => array(
                'props' => array( 'feature' => $slug, 'trigger' => $slug, 'transport' => $transport ),
                'coalesce' => true,
            ),
            'message.sent' => array(
                'props' => array(
                    'transport' => $transport,
                    'type' => $message_type,
                    'scheduled' => array( 'kind' => 'boolean' ),
                ),
                'coalesce' => true,
            ),
            'settings.changed' => array(
                'props' => array( 'setting' => $slug ),
                'coalesce' => true,
            ),

            // Failures.
            'message.failed' => array(
                'props' => array(
                    'code' => $slug,
                    'transport' => $transport,
                    'type' => $message_type,
                    'httpStatus' => $http_status,
                ),
                'coalesce' => true,
            ),
            'plugin.error' => array(
                'props' => array(
                    'code' => $slug,
                    'context' => $slug,
                    'fingerprint' => $slug,
                    'httpStatus' => $http_status,
                ),
                'coalesce' => true,
            ),
            'queue.retried' => array(
                'props' => array(
                    'reason' => $slug,
                    'attempt' => array( 'kind' => 'number', 'min' => 1, 'max' => 100 ),
                ),
                'coalesce' => true,
            ),
        );
    }


    /**
     * Environment keys the service accepts inside the installation block.
     *
     * @since 2.5.0
     * @return array<string,array<string,mixed>>
     */
    public static function environment_props() {
        return array(
            'multisite' => array( 'kind' => 'boolean' ),
            'httpsEnabled' => array( 'kind' => 'boolean' ),
            'cronDisabled' => array( 'kind' => 'boolean' ),
            'wooActive' => array( 'kind' => 'boolean' ),
            'wooVersion' => array( 'kind' => 'version' ),
            'elementorActive' => array( 'kind' => 'boolean' ),
            'activeIntegrations' => array( 'kind' => 'slug_list', 'max' => 30 ),
            'workflowsPublished' => array( 'kind' => 'number', 'min' => 0, 'max' => 100000 ),
            'workflowsDraft' => array( 'kind' => 'number', 'min' => 0, 'max' => 100000 ),
        );
    }


    /**
     * Whether the service knows this event name.
     *
     * @since 2.5.0
     * @param string $name | Event name.
     * @return bool
     */
    public static function has( $name ) {
        $events = self::events();

        return isset( $events[ $name ] );
    }


    /**
     * Whether this event survives buffer trimming.
     *
     * @since 2.5.0
     * @param string $name | Event name.
     * @return bool
     */
    public static function is_protected( $name ) {
        $events = self::events();

        return ! empty( $events[ $name ]['protected'] );
    }


    /**
     * Whether repeated occurrences of this event collapse into one per day.
     *
     * @since 2.5.0
     * @param string $name | Event name.
     * @return bool
     */
    public static function coalesces( $name ) {
        $events = self::events();

        return ! empty( $events[ $name ]['coalesce'] );
    }


    /**
     * Keep only the properties this event declares, in the shape it declares them.
     *
     * A key outside the allow-list is dropped and the event survives without it. Failing
     * the whole event instead would let one new key in the plugin take down all of its
     * telemetry until the service caught up — the opposite of what a diagnostic channel
     * is for.
     *
     * @since 2.5.0
     * @param string $name | Event name.
     * @param array $props | Raw properties.
     * @return array<string,mixed>
     */
    public static function filter_props( $name, $props ) {
        $events = self::events();

        if ( ! isset( $events[ $name ] ) || ! is_array( $props ) ) {
            return array();
        }

        return self::filter_against( $events[ $name ]['props'], $props );
    }


    /**
     * Keep only the environment keys the service declares.
     *
     * @since 2.5.0
     * @param array $environment | Raw environment.
     * @return array<string,mixed>
     */
    public static function filter_environment( $environment ) {
        return self::filter_against( self::environment_props(), is_array( $environment ) ? $environment : array() );
    }


    /**
     * Apply an allow-list of specs to a set of raw values.
     *
     * @since 2.5.0
     * @param array $allowed | Map of key => spec.
     * @param array $raw | Raw values.
     * @return array<string,mixed>
     */
    private static function filter_against( $allowed, $raw ) {
        $clean = array();

        foreach ( $allowed as $key => $spec ) {
            if ( ! array_key_exists( $key, $raw ) ) {
                continue;
            }

            $value = self::normalize( $spec, $raw[ $key ] );

            if ( null !== $value ) {
                $clean[ $key ] = $value;
            }
        }

        return $clean;
    }


    /**
     * Normalize one value against one spec.
     *
     * @since 2.5.0
     * @param array $spec | Property spec.
     * @param mixed $value | Raw value.
     * @return mixed|null
     */
    private static function normalize( $spec, $value ) {
        $kind = isset( $spec['kind'] ) ? $spec['kind'] : '';

        switch ( $kind ) {
            case 'boolean':
                return is_bool( $value ) ? $value : null;

            case 'number':
                return Normalizer::number( $value, $spec['min'], $spec['max'] );

            case 'enum':
                return Normalizer::enum( $value, $spec['values'] );

            case 'version':
                return Normalizer::version( $value );

            case 'slug':
                return Normalizer::slug( $value );

            case 'slug_list':
                return Normalizer::slug_list( $value, $spec['max'] );
        }

        return null;
    }


    /**
     * Filled-in examples for the consent screen.
     *
     * Shown verbatim next to the toggle, so the owner reads the actual shape of what
     * would be sent rather than a description of it.
     *
     * @since 2.5.0
     * @return array<int,array<string,mixed>>
     */
    public static function samples() {
        return array(
            array(
                'name' => 'feature.used',
                'props' => array(
                    'feature' => 'woocommerce',
                    'trigger' => 'woocommerce_order_status_completed',
                    'transport' => 'cloud',
                ),
            ),
            array(
                'name' => 'message.failed',
                'props' => array(
                    'code' => 'window_closed_requires_template',
                    'transport' => 'cloud',
                    'type' => 'text',
                    'httpStatus' => 400,
                ),
            ),
            array(
                'name' => 'plugin.updated',
                'props' => array( 'previousVersion' => '2.4.0', 'newVersion' => '2.5.0' ),
            ),
        );
    }
}
