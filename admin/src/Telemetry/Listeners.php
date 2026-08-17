<?php

namespace MeuMouse\Joinotify\Telemetry;

use MeuMouse\Joinotify\Api\Transport;
use MeuMouse\Joinotify\Builder\Triggers;
use MeuMouse\Joinotify\Core\Telemetry;
use MeuMouse\Joinotify\Core\Upgrader;
use MeuMouse\Joinotify\Integrations\Integrations_Base;

// Exit if accessed directly.
defined('ABSPATH') || exit;

/**
 * Everything the telemetry module hooks into, in one place.
 *
 * The constructor is the whole cost calculation. On a site that never agreed — which is
 * most of them, since the setting ships off and the wizard has to be answered — it
 * registers exactly one listener, the one that notices the answer changing, and returns.
 * Nothing else is added to any hook, so the module costs a string comparison per request.
 *
 * The listeners themselves never make requests. They call `Recorder::record()`, which
 * appends to an array; the batch goes out from cron.
 *
 * @since 2.3.0
 * @package MeuMouse\Joinotify\Telemetry
 * @author MeuMouse.com
 */
class Listeners {

    /**
     * Register the hooks this site actually needs.
     *
     * @since 2.3.0
     */
    public function __construct() {
        // Always: the moment consent is given or withdrawn has to be observed even when
        // telemetry is off, because that is the transition that turns it on.
        add_action( 'Joinotify/Settings/Saved', array( __CLASS__, 'on_settings_saved' ), 10, 2 );

        if ( ! Telemetry::is_enabled() ) {
            return;
        }

        add_action( Dispatcher::HOOK, array( __CLASS__, 'on_dispatch' ) );
        add_action( 'admin_init', array( __CLASS__, 'on_admin_init' ), 120 );

        // A reconnection is the only thing that can revive a dispatch paused by a dead
        // or revoked key.
        add_action( 'Joinotify/Cloud_Api/Connected', array( Dispatcher::class, 'resume' ) );

        // Lifecycle. `Joinotify/Upgraded` carries both versions, so no state of our own
        // is needed to tell an update from a fresh install.
        add_action( 'Joinotify/Upgraded', array( __CLASS__, 'on_upgraded' ), 10, 2 );

        // Install funnel.
        add_action( 'Joinotify/Sender_Selected', array( __CLASS__, 'on_sender_selected' ) );

        // Delivery, on the one hook that sees both outcomes for every channel.
        add_action( 'Joinotify/Notifications/Message_Sent', array( __CLASS__, 'on_message_sent' ), 10, 3 );
        add_action( 'Joinotify/Notification_Queue/Item_Retried', array( __CLASS__, 'on_queue_retried' ) );

        // Feature usage.
        add_action( 'Joinotify/Workflow_Processor/Process_Workflows', array( __CLASS__, 'on_workflows_processed' ), 10, 3 );

        // Failures the plugin raised itself.
        add_action( 'Joinotify/Debug_Log/Recorded', array( __CLASS__, 'on_error_recorded' ), 10, 2 );
    }


    /**
     * Record that this site now has a sending number.
     *
     * @since 2.3.0
     * @param string $method | 'auto' or 'manual'.
     * @return void
     */
    public static function on_sender_selected( $method ) {
        Recorder::record( 'onboarding.sender_selected', array( 'method' => $method ) );
    }


    /**
     * Record the outcome of a delivery.
     *
     * Note what this hook does not see: the three failures `Channel_Manager::dispatch()`
     * returns early — no channel, not configured, unsupported — never reach it. That is
     * fine here, because none of them is a delivery attempt; they are configuration
     * problems, and they arrive through the error log instead.
     *
     * @since 2.3.0
     * @param object $result | Channel_Result.
     * @param object $message | Notification_Message.
     * @param object $channel | Channel_Interface implementation.
     * @return void
     */
    public static function on_message_sent( $result, $message, $channel ) {
        $transport = self::transport_of( $channel );

        // Telegram and Resend are real channels that this event's enum cannot express.
        // Half an event — a delivery with no transport — would be worse than none, since
        // it would be counted next to WhatsApp ones and quietly skew them.
        if ( '' === $transport ) {
            return;
        }

        $type = self::message_type_of( $message );
        $source = isset( $message->context['source'] ) ? (string) $message->context['source'] : '';

        if ( 'test' === $source ) {
            $type = 'test';
        }

        if ( $result->is_success() ) {
            Recorder::record( 'message.sent', array(
                'transport' => $transport,
                'type' => $type,
                'scheduled' => ( (int) $message->delay > 0 ) || 'queue' === $source,
            ) );

            return;
        }

        $props = array(
            'code' => Normalizer::error_code( $result->error ),
            'transport' => $transport,
            'type' => $type,
        );

        // A transport failure reports status 0. Sending it as a number would put a
        // meaningless zero next to real statuses; the catalog rejects it, and leaving the
        // property out says "no HTTP answer", which is what happened.
        if ( (int) $result->response_code > 0 ) {
            $props['httpStatus'] = (int) $result->response_code;
        }

        Recorder::record( 'message.failed', $props );
    }


    /**
     * Record a queued message going back in line.
     *
     * @since 2.3.0
     * @param array $item | The queued item, after its attempt count was incremented.
     * @return void
     */
    public static function on_queue_retried( $item ) {
        $attempt = isset( $item['attempts'] ) ? (int) $item['attempts'] : 0;

        // The queue allows 120 attempts, so an item that is truly stuck would report 120
        // times and drown everything else. The first few say "this is failing", and the
        // powers of two after them say "it is still failing" without the noise.
        if ( $attempt > 5 && 0 !== ( $attempt & ( $attempt - 1 ) ) ) {
            return;
        }

        Recorder::record( 'queue.retried', array(
            'reason' => Normalizer::error_code( isset( $item['last_error'] ) ? $item['last_error'] : '' ),
            'attempt' => $attempt,
        ) );
    }


    /**
     * Record that a trigger actually drove a workflow.
     *
     * @since 2.3.0
     * @param string $hook | Trigger hook that fired.
     * @param array $payload | Trigger payload.
     * @param array $workflows | Workflows matched for that hook.
     * @return void
     */
    public static function on_workflows_processed( $hook, $payload, $workflows ) {
        // Every integration calls this on every one of its hooks, matched or not. Without
        // this guard the event would answer "what happens on this site" instead of "what
        // this plugin is used for", and on a busy store those are wildly different sizes.
        if ( empty( $workflows ) ) {
            return;
        }

        $integration = isset( $payload['integration'] ) ? (string) $payload['integration'] : '';

        Recorder::record( 'feature.used', array(
            'feature' => self::known_integration( $integration ),
            'trigger' => self::known_trigger( $integration, (string) $hook ),
            'transport' => Transport::is_cloud() ? 'cloud' : 'evolution',
        ) );
    }


    /**
     * Record a failure the plugin raised itself.
     *
     * @since 2.3.0
     * @param array $entry | Log entry, with file, line and channel resolved.
     * @param string $level | 'error' or 'critical'.
     * @return void
     */
    public static function on_error_recorded( $entry, $level ) {
        $code = Normalizer::error_code( isset( $entry['code'] ) ? $entry['code'] : '' );

        // `empty()` and not `isset()`: the entry usually carries the key with an empty
        // string rather than omitting it, and an empty channel fails the slug shape — the
        // property would be dropped and the error would arrive with no context at all.
        $channel = empty( $entry['channel'] ) ? 'general' : (string) $entry['channel'];

        $props = array(
            'code' => $code,
            'context' => $channel,
            // Never a hash of the message: messages carry order numbers, wamids and
            // timestamps, so that set never stops growing — and the service counts this.
            'fingerprint' => Normalizer::fingerprint(
                $code,
                $channel,
                isset( $entry['file'] ) ? $entry['file'] : '',
                isset( $entry['line'] ) ? $entry['line'] : 0
            ),
        );

        if ( isset( $entry['response_code'] ) && (int) $entry['response_code'] > 0 ) {
            $props['httpStatus'] = (int) $entry['response_code'];
        }

        Recorder::record( 'plugin.error', $props );
    }


    /**
     * Map a channel id onto the transport enum, or '' when it does not fit.
     *
     * @since 2.3.0
     * @param object $channel | Channel_Interface implementation.
     * @return string
     */
    private static function transport_of( $channel ) {
        if ( ! is_object( $channel ) || ! method_exists( $channel, 'get_id' ) ) {
            return '';
        }

        $map = array(
            'whatsapp_cloud' => 'cloud',
            'whatsapp' => 'evolution',
        );

        $id = (string) $channel->get_id();

        return isset( $map[ $id ] ) ? $map[ $id ] : '';
    }


    /**
     * Map a message type onto the enum the service accepts.
     *
     * @since 2.3.0
     * @param object $message | Notification_Message.
     * @return string
     */
    private static function message_type_of( $message ) {
        $type = is_object( $message ) && isset( $message->type ) ? (string) $message->type : 'text';

        // Audio is a media message everywhere it matters; splitting it would add a value
        // the service does not know, and the property would be dropped on arrival.
        return 'audio' === $type ? 'media' : $type;
    }


    /**
     * Keep an integration slug only when the plugin actually declares it.
     *
     * A third-party integration registered through the extension API would otherwise put
     * an unbounded set of slugs into a counter name.
     *
     * @since 2.3.0
     * @param string $integration | Slug from the trigger payload.
     * @return string
     */
    private static function known_integration( $integration ) {
        static $known = null;

        if ( null === $known ) {
            $known = class_exists( Integrations_Base::class )
                ? array_keys( Integrations_Base::integration_tab_items() )
                : array();
        }

        return in_array( $integration, $known, true ) ? $integration : 'custom';
    }


    /**
     * Keep a trigger hook only when it belongs to that integration's catalog.
     *
     * @since 2.3.0
     * @param string $integration | Integration slug.
     * @param string $hook | Trigger hook that fired.
     * @return string
     */
    private static function known_trigger( $integration, $hook ) {
        static $known = array();

        if ( ! isset( $known[ $integration ] ) ) {
            // `get_all_triggers()` is a filter over arrays with no I/O behind it, but it
            // runs once per request here rather than once per workflow: on a store firing
            // several triggers per order, the difference is the whole point of caching it.
            $known[ $integration ] = class_exists( Triggers::class )
                ? (array) Triggers::get_trigger_names( $integration )
                : array();
        }

        return in_array( $hook, $known[ $integration ], true ) ? $hook : 'custom';
    }


    /**
     * Run a scheduled dispatch.
     *
     * @since 2.3.0
     * @return void
     */
    public static function on_dispatch() {
        Dispatcher::dispatch();
    }


    /**
     * Keep the schedule alive, and cover for a broken cron.
     *
     * @since 2.3.0
     * @return void
     */
    public static function on_admin_init() {
        Dispatcher::ensure_scheduled();
        Dispatcher::maybe_dispatch_late();
    }


    /**
     * Record a version change.
     *
     * @since 2.3.0
     * @param string $from | Version the site was on.
     * @param string $to | Version it is on now.
     * @return void
     */
    public static function on_upgraded( $from, $to ) {
        // A fresh install reports `0.0.0` as the previous version. That is an
        // installation, not an update, and counting it as one would make every new site
        // look like an upgrade in the adoption numbers.
        if ( ! is_string( $from ) || Upgrader::FRESH_INSTALL_VERSION === $from ) {
            return;
        }

        Recorder::record( 'plugin.updated', array(
            'previousVersion' => $from,
            'newVersion' => $to,
        ) );
    }


    /**
     * React to consent being given or withdrawn.
     *
     * @since 2.3.0
     * @param array $saved | Settings as stored.
     * @param array $previous | Settings as they were.
     * @return void
     */
    public static function on_settings_saved( $saved, $previous ) {
        $key = Telemetry::SETTING;

        $now_on = isset( $saved[ $key ] ) && 'yes' === $saved[ $key ];
        $was_on = isset( $previous[ $key ] ) && 'yes' === $previous[ $key ];

        if ( $now_on !== $was_on ) {
            Recorder::refresh();

            if ( ! $now_on ) {
                self::on_opt_out();

                return;
            }

            // First contact is deliberately not immediate: an install that is switched on
            // and off again while somebody explores the wizard should not produce a
            // request for every toggle.
            Dispatcher::schedule_next( HOUR_IN_SECONDS );
        }

        self::record_changed_settings( $saved, $previous );
    }


    /**
     * Record which settings changed — the keys, never the values.
     *
     * The key alone is not a secret and is what the question needs: whether people find a
     * setting at all. A value could be a token, a phone number or a message template, and
     * there is no version of this event that wants any of them, so none is ever read.
     *
     * @since 2.3.0
     * @param array $saved | Settings as stored.
     * @param array $previous | Settings as they were.
     * @return void
     */
    private static function record_changed_settings( $saved, $previous ) {
        if ( ! is_array( $saved ) || ! is_array( $previous ) ) {
            return;
        }

        foreach ( $saved as $key => $value ) {
            $before = array_key_exists( $key, $previous ) ? $previous[ $key ] : null;

            if ( $before === $value ) {
                continue;
            }

            Recorder::record( 'settings.changed', array( 'setting' => $key ) );
        }
    }


    /**
     * Stop, forget, and tell the service to stop counting this installation.
     *
     * Order matters: the notice goes out before the buffer is cleared, so that a site
     * whose last batch never arrived still gets removed from the counts.
     *
     * @since 2.3.0
     * @return void
     */
    public static function on_opt_out() {
        Recorder::discard();
        Dispatcher::dispatch( true );
        Dispatcher::unschedule();
        Buffer::clear();
        Policy::reset();
    }
}
