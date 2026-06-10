<?php

namespace MeuMouse\Joinotify\Admin\Queue;

use MeuMouse\Joinotify\Cron\Schedule;
use MeuMouse\Joinotify\Builder\Messages;
use MeuMouse\Joinotify\Core\Workflow_Processor;

// Exit if accessed directly.
defined('ABSPATH') || exit;

/**
 * Processing queue registry.
 *
 * Centralizes the read logic and UI formatting for the scheduled-segments screen
 * ("Processing queue"). A workflow with a time_delay node schedules the remaining
 * funnel as a continuation through Cron\Schedule::schedule_actions(), preferring
 * Action Scheduler and falling back to WP-Cron. This registry enumerates those
 * pending continuations from whichever backend holds them and exposes operations
 * to run a segment immediately (skipping the wait) or cancel it.
 *
 * @since 2.0.0
 * @package MeuMouse\Joinotify\Admin\Queue
 * @author MeuMouse.com
 */
class Registry {

    /**
     * Hook shared by every scheduled Joinotify segment.
     *
     * @var string
     */
    const HOOK = 'joinotify_scheduled_actions_event';

    /**
     * Default per-page used by the list screen.
     *
     * @var int
     */
    const PER_PAGE = 20;


    /**
     * Enumerate every pending scheduled segment across the available backends.
     *
     * Returns raw segment descriptors (not UI items) so callers can both build the
     * list and resolve a single segment for run/cancel by its opaque id.
     *
     * @since 2.0.0
     * @return array<int,array<string,mixed>> Each entry: {backend, identifier, args, timestamp}.
     */
    public static function get_pending_segments() {
        $segments = array();

        // Action Scheduler: pending actions stored in dedicated tables. Querying by
        // hook (without a group) returns every Joinotify segment regardless of the
        // per-post group it was scheduled under.
        if ( Schedule::is_action_scheduler_available() && function_exists('as_get_scheduled_actions') ) {
            $status = class_exists('\ActionScheduler_Store') ? \ActionScheduler_Store::STATUS_PENDING : 'pending';

            $actions = as_get_scheduled_actions( array(
                'hook'     => self::HOOK,
                'status'   => $status,
                'per_page' => -1,
                'orderby'  => 'date',
                'order'    => 'ASC',
            ) );

            if ( is_array( $actions ) ) {
                foreach ( $actions as $action_id => $action ) {
                    if ( ! is_object( $action ) || ! method_exists( $action, 'get_args' ) ) {
                        continue;
                    }

                    $segments[] = array(
                        'backend'    => 'action_scheduler',
                        'identifier' => (string) $action_id,
                        'args'       => (array) $action->get_args(),
                        'timestamp'  => self::resolve_as_timestamp( $action ),
                    );
                }
            }
        }

        // WP-Cron fallback: scan the cron array for events under the shared hook.
        $crons = _get_cron_array();

        if ( is_array( $crons ) ) {
            foreach ( $crons as $timestamp => $hooks ) {
                if ( empty( $hooks[ self::HOOK ] ) || ! is_array( $hooks[ self::HOOK ] ) ) {
                    continue;
                }

                foreach ( $hooks[ self::HOOK ] as $event ) {
                    $args = isset( $event['args'] ) && is_array( $event['args'] ) ? $event['args'] : array();

                    $segments[] = array(
                        'backend'    => 'wp_cron',
                        'identifier' => (int) $timestamp . ':' . md5( maybe_serialize( $args ) ),
                        'args'       => $args,
                        'timestamp'  => (int) $timestamp,
                    );
                }
            }
        }

        return $segments;
    }


    /**
     * Resolve the scheduled timestamp of an Action Scheduler action.
     *
     * @since 2.0.0
     * @param object $action Action Scheduler action instance.
     * @return int Unix timestamp (0 when undetermined).
     */
    protected static function resolve_as_timestamp( $action ) {
        if ( ! method_exists( $action, 'get_schedule' ) ) {
            return 0;
        }

        $schedule = $action->get_schedule();

        if ( ! is_object( $schedule ) ) {
            return 0;
        }

        $date = null;

        if ( method_exists( $schedule, 'get_date' ) ) {
            $date = $schedule->get_date();
        } elseif ( method_exists( $schedule, 'get_next' ) && function_exists('as_get_datetime_object') ) {
            $date = $schedule->get_next( as_get_datetime_object() );
        }

        return $date instanceof \DateTime ? $date->getTimestamp() : 0;
    }


    /**
     * Build the opaque id for a segment (encodes its backend + locator).
     *
     * @since 2.0.0
     * @param array<string,mixed> $segment Raw segment descriptor.
     * @return string
     */
    public static function build_segment_id( $segment ) {
        $prefix = 'action_scheduler' === ( $segment['backend'] ?? '' ) ? 'as' : 'cron';

        return $prefix . ':' . ( $segment['identifier'] ?? '' );
    }


    /**
     * Extract the (post_id, context, action_data) triplet from stored cron args.
     *
     * Handles both the named-key shape written by Schedule::schedule_actions() and
     * a positional fallback, so a run/cancel never depends on how the scheduler
     * serialized the args.
     *
     * @since 2.0.0
     * @param array<mixed> $args Stored event args.
     * @return array{0:int,1:array,2:array}
     */
    protected static function extract_args( $args ) {
        $post_id = (int) ( $args['post_id'] ?? ( $args[0] ?? 0 ) );
        $context = $args['context'] ?? ( $args[1] ?? array() );
        $action_data = $args['action_data'] ?? ( $args[2] ?? array() );

        return array(
            $post_id,
            is_array( $context ) ? $context : array(),
            is_array( $action_data ) ? $action_data : array(),
        );
    }


    /**
     * Build a normalized list item from a raw segment.
     *
     * @since 2.0.0
     * @param array<string,mixed> $segment Raw segment descriptor.
     * @return array<string,mixed>
     */
    public static function build_item( $segment ) {
        list( $post_id, $context, $action_data ) = self::extract_args( $segment['args'] ?? array() );

        $timestamp = (int) ( $segment['timestamp'] ?? 0 );
        $scheduled_gmt = $timestamp ? gmdate( 'Y-m-d H:i:s', $timestamp ) : '';
        $scheduled_local = $scheduled_gmt ? get_date_from_gmt( $scheduled_gmt ) : '';

        $next_actions = $action_data['data']['next_actions'] ?? array();
        $next_actions = is_array( $next_actions ) ? array_values( $next_actions ) : array();
        $first = $next_actions[0] ?? null;

        return array(
            'id'                => self::build_segment_id( $segment ),
            'backend'           => (string) ( $segment['backend'] ?? '' ),
            'workflow_id'       => $post_id,
            'workflow_title'    => $post_id ? get_the_title( $post_id ) : '',
            'workflow_exists'   => $post_id ? ( get_post_status( $post_id ) !== false ) : false,
            'workflow_published'=> $post_id ? ( get_post_status( $post_id ) === 'publish' ) : false,
            'workflow_edit_url' => $post_id ? admin_url( 'admin.php?page=joinotify-workflows-builder&id=' . $post_id ) : '',
            'scheduled_at'      => $scheduled_local,
            'scheduled_at_gmt'  => $scheduled_gmt,
            'timestamp'         => $timestamp,
            'is_due'            => $timestamp > 0 && $timestamp <= time(),
            'delay_label'       => self::build_delay_label( $action_data['data'] ?? array() ),
            'next_action'       => $first ? (string) ( $first['data']['action'] ?? '' ) : '',
            'next_action_label' => $first ? self::describe_action( $first ) : '',
            'pending_count'     => count( $next_actions ),
            'receiver'          => self::guess_receiver( $context ),
        );
    }


    /**
     * Human-readable summary of the delay that scheduled this segment.
     *
     * @since 2.0.0
     * @param array<string,mixed> $data time_delay node data.
     * @return string
     */
    protected static function build_delay_label( $data ) {
        $type = isset( $data['delay_type'] ) ? (string) $data['delay_type'] : 'period';

        if ( 'date' === $type ) {
            $date = isset( $data['date_value'] ) ? (string) $data['date_value'] : '';
            $time = isset( $data['time_value'] ) ? (string) $data['time_value'] : '';

            return trim( $date . ' ' . $time );
        }

        $value = isset( $data['delay_value'] ) ? (int) $data['delay_value'] : 0;
        $period = isset( $data['delay_period'] ) ? (string) $data['delay_period'] : 'seconds';

        $labels = array(
            'seconds' => __( 'second(s)', 'joinotify' ),
            'minute'  => __( 'minute(s)', 'joinotify' ),
            'hours'   => __( 'hour(s)', 'joinotify' ),
            'day'     => __( 'day(s)', 'joinotify' ),
            'week'    => __( 'week(s)', 'joinotify' ),
            'month'   => __( 'month(s)', 'joinotify' ),
            'year'    => __( 'year(s)', 'joinotify' ),
        );

        $period_label = $labels[ $period ] ?? $period;
        $summary = trim( $value . ' ' . $period_label );

        if ( 'scheduled' === $type && ! empty( $data['time_value'] ) ) {
            /* translators: 1: relative offset (e.g. "1 day(s)"), 2: time of day */
            return sprintf( __( '%1$s at %2$s', 'joinotify' ), $summary, (string) $data['time_value'] );
        }

        return $summary;
    }


    /**
     * Plain-text description of the next action node.
     *
     * @since 2.0.0
     * @param array<string,mixed> $node Workflow action node.
     * @return string
     */
    protected static function describe_action( $node ) {
        $description = Messages::build_workflow_action_description( $node );
        $description = wp_strip_all_tags( (string) $description );
        $description = trim( preg_replace( '/\s+/', ' ', $description ) );

        return $description;
    }


    /**
     * Best-effort recipient extracted from the scheduled context payload.
     *
     * @since 2.0.0
     * @param array<string,mixed> $context Runtime payload captured at schedule time.
     * @return string
     */
    protected static function guess_receiver( $context ) {
        foreach ( array( 'receiver', 'phone', 'billing_phone', 'whatsapp', 'telephone' ) as $key ) {
            if ( ! empty( $context[ $key ] ) && is_scalar( $context[ $key ] ) ) {
                return (string) $context[ $key ];
            }
        }

        return '';
    }


    /**
     * Normalize incoming filter/pagination args.
     *
     * @since 2.0.0
     * @param array<string,mixed> $args Raw args.
     * @return array<string,mixed>
     */
    public static function normalize_args( $args ) {
        return array(
            'status'      => isset( $args['status'] ) ? sanitize_key( $args['status'] ) : '',
            'workflow_id' => isset( $args['workflow_id'] ) ? (int) $args['workflow_id'] : 0,
            'search'      => isset( $args['search'] ) ? sanitize_text_field( $args['search'] ) : '',
            'page'        => isset( $args['page'] ) ? max( 1, (int) $args['page'] ) : 1,
            'per_page'    => isset( $args['per_page'] ) ? max( 1, min( 200, (int) $args['per_page'] ) ) : self::PER_PAGE,
        );
    }


    /**
     * Build the full list state payload (items + counts + pagination).
     *
     * The backends already return the complete pending set, so filtering and
     * pagination happen in memory.
     *
     * @since 2.0.0
     * @param array<string,mixed> $args Filter + pagination args.
     * @return array<string,mixed>
     */
    public static function get_list_state( $args = array() ) {
        $args = self::normalize_args( $args );

        $items = array_map( array( __CLASS__, 'build_item' ), self::get_pending_segments() );

        // sort by scheduled time ascending (soonest first)
        usort( $items, static function( $a, $b ) {
            return ( $a['timestamp'] ?? 0 ) <=> ( $b['timestamp'] ?? 0 );
        } );

        $counts = array(
            'all'       => count( $items ),
            'due'       => count( array_filter( $items, static fn( $i ) => ! empty( $i['is_due'] ) ) ),
            'scheduled' => count( array_filter( $items, static fn( $i ) => empty( $i['is_due'] ) ) ),
        );

        // apply filters
        $filtered = array_values( array_filter( $items, static function( $item ) use ( $args ) {
            if ( 'due' === $args['status'] && empty( $item['is_due'] ) ) {
                return false;
            }

            if ( 'scheduled' === $args['status'] && ! empty( $item['is_due'] ) ) {
                return false;
            }

            if ( $args['workflow_id'] && (int) $item['workflow_id'] !== $args['workflow_id'] ) {
                return false;
            }

            if ( '' !== $args['search'] ) {
                $haystack = strtolower( $item['workflow_title'] . ' ' . $item['receiver'] . ' ' . $item['next_action_label'] );

                if ( false === strpos( $haystack, strtolower( $args['search'] ) ) ) {
                    return false;
                }
            }

            return true;
        } ) );

        $total = count( $filtered );
        $per_page = $args['per_page'];
        $offset = ( $args['page'] - 1 ) * $per_page;

        return array(
            'items'      => array_slice( $filtered, $offset, $per_page ),
            'counts'     => $counts,
            'pagination' => array(
                'current_page' => $args['page'],
                'per_page'     => $per_page,
                'total_items'  => $total,
                'total_pages'  => (int) max( 1, ceil( $total / $per_page ) ),
            ),
        );
    }


    /**
     * Distinct workflows currently represented in the queue (for the filter).
     *
     * @since 2.0.0
     * @return array<int,array<string,mixed>>
     */
    public static function get_workflow_options() {
        $options = array( array( 'value' => 0, 'label' => __( 'All workflows', 'joinotify' ) ) );
        $seen = array();

        foreach ( self::get_pending_segments() as $segment ) {
            list( $post_id ) = self::extract_args( $segment['args'] ?? array() );

            if ( ! $post_id || isset( $seen[ $post_id ] ) ) {
                continue;
            }

            $seen[ $post_id ] = true;

            $options[] = array(
                'value' => $post_id,
                'label' => get_the_title( $post_id ) ?: ( '#' . $post_id ),
            );
        }

        return $options;
    }


    /**
     * Build the bootstrap payload for the queue Vue screen.
     *
     * @since 2.0.0
     * @return array<string,mixed>
     */
    public static function get_bootstrap_data() {
        $list = self::get_list_state();

        return array(
            'page'        => 'queue',
            'title'       => __( 'Processing queue', 'joinotify' ),
            'date_format' => get_option( 'date_format' ),
            'time_format' => get_option( 'time_format' ),
            'backend'     => Schedule::is_action_scheduler_available() ? 'action_scheduler' : 'wp_cron',
            'workflows'   => self::get_workflow_options(),
            'items'       => $list['items'],
            'counts'      => $list['counts'],
            'pagination'  => $list['pagination'],
            'rest'        => array(
                'root'  => esc_url_raw( rest_url( 'joinotify/v1' ) ),
                'nonce' => wp_create_nonce( 'wp_rest' ),
            ),
        );
    }


    /**
     * Locate a single pending segment by its opaque id.
     *
     * @since 2.0.0
     * @param string $id Opaque segment id (as built by build_segment_id()).
     * @return array<string,mixed>|null
     */
    public static function find_segment( $id ) {
        $id = (string) $id;

        foreach ( self::get_pending_segments() as $segment ) {
            if ( self::build_segment_id( $segment ) === $id ) {
                return $segment;
            }
        }

        return null;
    }


    /**
     * Unschedule a segment from whichever backend holds it.
     *
     * @since 2.0.0
     * @param array<string,mixed> $segment Raw segment descriptor.
     * @return void
     */
    protected static function unschedule_segment( $segment ) {
        if ( 'action_scheduler' === ( $segment['backend'] ?? '' ) ) {
            $action_id = (int) $segment['identifier'];

            if ( $action_id && function_exists('ActionScheduler') ) {
                \ActionScheduler::store()->cancel_action( $action_id );
            } elseif ( $action_id && class_exists('\ActionScheduler_Store') ) {
                \ActionScheduler_Store::instance()->cancel_action( $action_id );
            }

            return;
        }

        // WP-Cron: identifier is "{timestamp}:{md5}"; unschedule by timestamp + args.
        $parts = explode( ':', (string) $segment['identifier'], 2 );
        $timestamp = (int) ( $parts[0] ?? 0 );

        if ( $timestamp ) {
            wp_unschedule_event( $timestamp, self::HOOK, $segment['args'] ?? array() );
        }
    }


    /**
     * Run a scheduled segment immediately, then remove it from the queue.
     *
     * The continuation is executed synchronously (the same call the scheduler
     * would make) so the user gets immediate feedback. Args are passed positionally
     * to avoid PHP 8 named-argument pitfalls with the stored key shape.
     *
     * @since 2.0.0
     * @param string $id Opaque segment id.
     * @return array{0:bool,1:string} [success, message]
     */
    public static function run_now( $id ) {
        $segment = self::find_segment( $id );

        if ( ! $segment ) {
            return array( false, __( 'This scheduled item no longer exists.', 'joinotify' ) );
        }

        list( $post_id, $context, $action_data ) = self::extract_args( $segment['args'] ?? array() );

        if ( ! $post_id || get_post_status( $post_id ) === false ) {
            // Workflow gone: just clear the orphaned schedule.
            self::unschedule_segment( $segment );

            return array( false, __( 'The related workflow no longer exists; the schedule was removed.', 'joinotify' ) );
        }

        if ( get_post_status( $post_id ) !== 'publish' ) {
            return array( false, __( 'The related workflow is not published, so it cannot run.', 'joinotify' ) );
        }

        // Remove the schedule first so a concurrent cron tick cannot double-run it.
        self::unschedule_segment( $segment );

        // Execute the continuation now (positional args).
        Workflow_Processor::process_scheduled_action( $post_id, $context, $action_data );

        return array( true, __( 'The scheduled actions were dispatched.', 'joinotify' ) );
    }


    /**
     * Cancel a scheduled segment without running it.
     *
     * @since 2.0.0
     * @param string $id Opaque segment id.
     * @return array{0:bool,1:string} [success, message]
     */
    public static function cancel( $id ) {
        $segment = self::find_segment( $id );

        if ( ! $segment ) {
            return array( false, __( 'This scheduled item no longer exists.', 'joinotify' ) );
        }

        self::unschedule_segment( $segment );

        return array( true, __( 'The scheduled item was cancelled.', 'joinotify' ) );
    }


    /**
     * Cancel every pending segment across all backends.
     *
     * @since 2.0.0
     * @return int Number of segments cancelled.
     */
    public static function cancel_all() {
        $segments = self::get_pending_segments();

        foreach ( $segments as $segment ) {
            self::unschedule_segment( $segment );
        }

        return count( $segments );
    }
}
