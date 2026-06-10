<?php

namespace MeuMouse\Joinotify\Cron;

use MeuMouse\Joinotify\Core\Helpers;

use WP_Cron;

// Exit if accessed directly.
defined('ABSPATH') || exit;

/**
 * Schedule messages for a future time or date
 * 
 * @since 1.0.0
 * @version 1.4.7
 * @package MeuMouse\Joinotify\Cron
 * @author MeuMouse.com
 */
class Schedule {

    /**
     * Action Scheduler group used for all Joinotify scheduled segments.
     *
     * @since 2.0.0
     * @var string
     */
    const AS_GROUP = 'joinotify';


    /**
     * Construct function
     *
     * @since 1.0.0
     * @version 1.4.7
     * @return void
     */
    public function __construct() {
        // process scheduled actions
        add_action( 'joinotify_scheduled_actions_event', array( '\MeuMouse\Joinotify\Core\Workflow_Processor', 'process_scheduled_action' ), 10, 3 );

        // update coupon expiration
        add_action( 'joinotify_update_coupon_expiration', array( $this, 'update_coupon_expiration' ), 10, 1 );

        // Legacy workflow conversion + trigger-hook index rebuild on upgrade is
        // owned by Core\Upgrader (runs Workflow_Migrator::migrate_stored_workflows
        // as a tracked, retryable routine), so it is no longer hooked here.

        // cancel pending scheduled segments when a workflow is disabled or removed
        add_action( 'transition_post_status', array( $this, 'clear_on_status_change' ), 10, 3 );
        add_action( 'before_delete_post', array( $this, 'clear_on_delete' ), 10, 1 );
    }


    /**
     * Cancel a workflow's pending scheduled segments when it leaves the
     * published state (unpublished or trashed).
     *
     * @since 2.0.0
     * @param string $new_status | New post status
     * @param string $old_status | Previous post status
     * @param \WP_Post $post | Post object
     * @return void
     */
    public function clear_on_status_change( $new_status, $old_status, $post ) {
        if ( ! $post instanceof \WP_Post || 'joinotify-workflow' !== $post->post_type ) {
            return;
        }

        if ( 'publish' === $old_status && 'publish' !== $new_status ) {
            self::clear_scheduled_for_post( $post->ID );
        }
    }


    /**
     * Cancel a workflow's pending scheduled segments before it is deleted.
     *
     * @since 2.0.0
     * @param int $post_id | Post ID
     * @return void
     */
    public function clear_on_delete( $post_id ) {
        if ( 'joinotify-workflow' === get_post_type( $post_id ) ) {
            self::clear_scheduled_for_post( $post_id );
        }
    }


    /**
     * Check if WordPress CRON is active
     * 
     * @since 1.0.0
     * @return bool
     */
    public static function is_wp_cron_active() {
        // Check if WordPress Cron is disabled
        if ( defined('DISABLE_WP_CRON') && DISABLE_WP_CRON ) {
            return false;
        }

        // Check if there are any scheduled events
        if ( wp_next_scheduled('some_scheduled_event_hook') ) {
            return true;
        }

        // As a fallback, check if there are any scheduled events in general
        $crons = _get_cron_array();

        if ( ! empty( $crons ) ) {
            return true;
        }

        return false;
    }


    /**
     * Whether Action Scheduler is available in this install.
     *
     * Action Scheduler ships with WooCommerce (and many other plugins). When it
     * is present we prefer it over WP-Cron for scheduling delayed segments: it
     * stores actions in dedicated tables, dedupes reliably and keeps running even
     * when DISABLE_WP_CRON is set (via its own queue runner / system cron).
     *
     * @since 2.0.0
     * @return bool
     */
    public static function is_action_scheduler_available() {
        return function_exists('as_schedule_single_action') && function_exists('as_unschedule_all_actions');
    }


    /**
     * Action Scheduler group for a given workflow.
     *
     * A per-post group lets us cancel every pending segment of a single workflow
     * precisely (Action Scheduler cannot reliably match on a partial args set).
     *
     * @since 2.0.0
     * @param int $post_id | Workflow post ID (0 for the base group)
     * @return string
     */
    public static function get_group( $post_id = 0 ) {
        $post_id = absint( $post_id );

        return $post_id ? self::AS_GROUP . '_' . $post_id : self::AS_GROUP;
    }


    /**
     * Cancel every pending scheduled segment for a workflow.
     *
     * Used when a workflow is unpublished, trashed or deleted so its delayed
     * continuations never fire. Precise on Action Scheduler (per-post group);
     * the WP-Cron fallback scans the cron array for events whose post_id matches.
     *
     * @since 2.0.0
     * @param int $post_id | Workflow post ID
     * @return void
     */
    public static function clear_scheduled_for_post( $post_id ) {
        $post_id = absint( $post_id );

        if ( ! $post_id ) {
            return;
        }

        $hook = 'joinotify_scheduled_actions_event';

        if ( self::is_action_scheduler_available() ) {
            // Empty args => match any; the per-post group scopes it to this workflow.
            as_unschedule_all_actions( $hook, array(), self::get_group( $post_id ) );

            return;
        }

        // WP-Cron fallback: unschedule every matching event by post_id.
        $crons = _get_cron_array();

        if ( empty( $crons ) ) {
            return;
        }

        foreach ( $crons as $timestamp => $hooks ) {
            if ( empty( $hooks[ $hook ] ) ) {
                continue;
            }

            foreach ( $hooks[ $hook ] as $event ) {
                $event_args = isset( $event['args'] ) && is_array( $event['args'] ) ? $event['args'] : array();
                $event_post = $event_args['post_id'] ?? ( $event_args[0] ?? null );

                if ( null !== $event_post && (int) $event_post === $post_id ) {
                    wp_unschedule_event( $timestamp, $hook, $event_args );
                }
            }
        }
    }


    /**
     * Schedule a message for a future time or date
     * 
     * @since 1.0.0
     * @version 1.4.7
     * @param string $post_id | Post ID
     * @param array $context | Context data
     * @param string $delay_time | Time to delay in seconds
     * @param array $action_data | Actions for execute
     * @return bool
     */
    public static function schedule_actions( $post_id, $context, $delay_time, $action_data, $unique_key = null ) {
        // clean all that object from context payload
        $context = Helpers::strip_objects( $context );

        $timestamp = time() + max( 0, (int) $delay_time );
        $hook = 'joinotify_scheduled_actions_event';

        // A stable, caller-provided key lets re-scheduling the same continuation
        // replace the previous event (cleared below) instead of duplicating it.
        if ( null === $unique_key || '' === $unique_key ) {
            $unique_key = $action_data['id'] ?? uniqid( 'action_', true );
        }

        // Prefer Action Scheduler when available: more reliable execution and
        // dedup than WP-Cron, and it survives DISABLE_WP_CRON. Action Scheduler
        // fires the hook positionally (via array_values()), so a NAMED-key args
        // array is safe here and lets us dedupe/cancel by readable keys.
        if ( self::is_action_scheduler_available() ) {
            $args = array(
                'post_id' => $post_id,
                'context' => $context,
                'action_data' => $action_data, // next actions
                'unique_key' => $unique_key,
            );

            $group = self::get_group( $post_id );

            // Replace any identical pending continuation before re-scheduling so a
            // re-fired trigger resets the timer instead of stacking duplicates.
            as_unschedule_all_actions( $hook, $args, $group );

            return as_schedule_single_action( $timestamp, $hook, $args, $group ) > 0;
        }

        // WP-Cron fallback: store POSITIONAL args. Core resumes the event via
        // do_action_ref_array( $hook, $event['args'] ), which on PHP 8 forwards
        // string-keyed args as NAMED parameters — those would fatally fail to
        // match process_scheduled_action( $post_id, $payload, $action_data )
        // ("Unknown named parameter $context"). Positional args map cleanly to
        // ($post_id, $context => $payload, $action_data); the trailing
        // $unique_key keeps the event signature distinct for dedup and is
        // dropped by the callback's accepted_args (3).
        $args = array( $post_id, $context, $action_data, $unique_key );

        // Clear the ghost event, then schedule a single event.
        if ( function_exists('wp_clear_scheduled_hook') ) {
            wp_clear_scheduled_hook( $hook, $args );
        } else {
            if ( $existing = wp_next_scheduled( $hook, $args ) ) {
                wp_unschedule_event( $existing, $hook, $args );
            }
        }

        return (bool) wp_schedule_single_event( $timestamp, $hook, $args );
    }


    /**
     * Clear a scheduled message
     * 
     * @since 1.0.0
     * @param string $hook Hook name used in the schedule
     * @param int $post_id The ID of the post associated with the scheduled event
     * @param array $args Optional arguments for the action
     * @return void
     */
    public function clear_schedule( $hook, $post_id, $args = array() ) {
        // Get the next scheduled event timestamp for the given hook
        $timestamp = wp_next_scheduled( $hook, array( $post_id, $args ) );

        // Unschedule the event if it exists
        if ( $timestamp ) {
            wp_unschedule_event( $timestamp, $hook, array( $post_id, $args ) );
        }
    }


    /**
     * Check if a message is already scheduled
     * 
     * @since 1.0.0
     * @param string $hook Hook name used in the schedule
     * @param int $post_id The ID of the post associated with the scheduled event
     * @param array $args Optional arguments for the action
     * @return bool True if scheduled, false otherwise
     */
    public function is_scheduled( $hook, $post_id, $args = array() ) {
        return (bool) wp_next_scheduled( $hook, array( $post_id, $args ) );
    }


    /**
     * Convert delay period and value into seconds
     *
     * @since 1.0.5
     * @param int $delay_value | The value of the delay (e.g., 5, 10, etc.)
     * @param string $delay_period | The period type (e.g., 'seconds', 'minute', 'hours', 'day', 'week', 'month', 'year')
     * @return int The delay in seconds
     */
    public static function get_delay_timestamp( $delay_value, $delay_period ) {
        // Ensure delay_value is an integer
        $delay_value = intval( $delay_value );
    
        switch ( $delay_period ) {
            case 'minute':
                return $delay_value * 60;
            case 'hours':
                return $delay_value * 3600;
            case 'day':
                return $delay_value * 86400;
            case 'week':
                return $delay_value * 604800;
            case 'month':
                return $delay_value * 2592000; // Approximate: 30 days
            case 'year':
                return $delay_value * 31536000; // Approximate: 365 days
            default:
                return $delay_value; // Default is seconds
        }
    }


    /**
     * Calculate the relative delay (in seconds from now) for a scheduled offset:
     * advance the calendar by N period units from now, then anchor the execution
     * to a specific time of day on the resulting date (e.g. "+1 day at 09:00").
     *
     * Returns a RELATIVE delay (seconds from now) so it is consumed correctly by
     * Schedule::schedule_actions(), which fires at time() + delay.
     *
     * @since 2.0.0
     * @param int $delay_value | Number of units to advance (e.g. 1 = +1 day)
     * @param string $delay_period | Offset unit: 'day', 'week', 'month' or 'year'
     * @param string $time_value | Target time of day in HH:MM (24h)
     * @return int The delay in seconds from now (0 if the result is in the past)
     */
    public static function get_scheduled_delay_timestamp( $delay_value, $delay_period, $time_value ) {
        $delay_value = max( 0, intval( $delay_value ) );

        // only day-based offsets make sense with a fixed time of day; fall back to day
        $units = array(
            'day'   => 'day',
            'week'  => 'week',
            'month' => 'month',
            'year'  => 'year',
        );

        $unit = $units[ $delay_period ] ?? 'day';

        // normalize the time of day, default to midnight when missing/invalid
        if ( ! preg_match( '/^\d{1,2}:\d{2}(:\d{2})?$/', (string) $time_value ) ) {
            $time_value = '00:00';
        }

        $now = time();

        // advance the calendar date by the requested offset
        $base_date = strtotime( "+{$delay_value} {$unit}", $now );

        // anchor to the requested time of day on that date
        $target = $base_date ? strtotime( date( 'Y-m-d', $base_date ) . ' ' . $time_value ) : 0;

        if ( ! $target ) {
            return 0;
        }

        return max( 0, $target - $now );
    }


    /**
     * Resolve a delay action into seconds-from-now, computed AT RUNTIME.
     *
     * For absolute ('date') and time-of-day ('scheduled') delays the offset must be
     * calculated when the trigger fires — never baked at save time — otherwise a
     * workflow saved today but fired weeks later would anchor to the wrong moment.
     * Pure durations ('period') are time-invariant, so they resolve identically
     * whenever computed. A legacy node that only carries a precomputed
     * `delay_timestamp` (no raw fields) falls back to that value.
     *
     * @since 2.0.0
     * @param array $action_data | The time_delay node data
     * @return int Seconds from now (never negative).
     */
    public static function resolve_delay_seconds( $action_data ) {
        if ( ! is_array( $action_data ) ) {
            return 0;
        }

        $has_raw = isset( $action_data['delay_type'] ) || isset( $action_data['delay_value'] ) || isset( $action_data['date_value'] );

        // Legacy fallback: only a precomputed timestamp is available.
        if ( ! $has_raw && isset( $action_data['delay_timestamp'] ) && is_numeric( $action_data['delay_timestamp'] ) ) {
            return max( 0, (int) $action_data['delay_timestamp'] );
        }

        $delay_type = isset( $action_data['delay_type'] ) ? sanitize_text_field( (string) $action_data['delay_type'] ) : 'period';

        if ( 'date' === $delay_type ) {
            $date_value = isset( $action_data['date_value'] ) ? sanitize_text_field( (string) $action_data['date_value'] ) : '';
            $time_value = isset( $action_data['time_value'] ) ? sanitize_text_field( (string) $action_data['time_value'] ) : '00:00';
            $timestamp = $date_value ? strtotime( $date_value . ' ' . $time_value ) : 0;

            // Past dates resolve to 0 -> fire immediately rather than never.
            return $timestamp ? max( 0, (int) $timestamp - time() ) : 0;
        }

        $delay_value = isset( $action_data['delay_value'] ) ? (int) $action_data['delay_value'] : 0;
        $delay_period = isset( $action_data['delay_period'] ) ? sanitize_text_field( (string) $action_data['delay_period'] ) : 'seconds';

        if ( 'scheduled' === $delay_type ) {
            $time_value = isset( $action_data['time_value'] ) ? sanitize_text_field( (string) $action_data['time_value'] ) : '00:00';

            return (int) self::get_scheduled_delay_timestamp( $delay_value, $delay_period, $time_value );
        }

        return (int) self::get_delay_timestamp( $delay_value, $delay_period );
    }


    /**
     * Update the coupon expiration date to the previous day after expiration
     *
     * @since 1.2.2
     * @version 1.4.7
     * @param int $coupon_id | The WooCommerce coupon ID
     * @return void
     */
    public function update_coupon_expiration( $coupon_id ) {
        if ( ! $coupon_id ) {
            return;
        }
    
        $coupon = new \WC_Coupon( $coupon_id );
    
        // remove coupon
        wp_delete_post( $coupon_id, true );

        if ( JOINOTIFY_DEV_MODE ) {
            error_log( "Coupon {$coupon_id} deleted upon expiration" );
        }
    }
}