<?php
/**
 * Standalone test harness for Cron\Schedule's scheduled-segment args shape.
 *
 * Regression coverage for the PHP 8 named-argument fatal: when Action Scheduler
 * is unavailable, core WP-Cron resumes the delay via
 *   do_action_ref_array( 'joinotify_scheduled_actions_event', $event['args'] )
 * which forwards string-keyed args as NAMED parameters. The callback
 * Workflow_Processor::process_scheduled_action( $post_id, $payload, $action_data )
 * has no $context/$unique_key parameter, so an ASSOCIATIVE args array fatals with
 * "Unknown named parameter $context". The fix stores POSITIONAL args on the
 * WP-Cron path while keeping the readable named shape for Action Scheduler (which
 * fires positionally via array_values()).
 *
 * No WordPress bootstrap is required: the WP/Action-Scheduler functions that
 * Schedule.php calls are stubbed as shadows inside the MeuMouse\Joinotify\Cron
 * namespace, and Action-Scheduler availability is toggled at runtime through a
 * shadowed function_exists(). This exercises both backends in a single process.
 *
 * Run (Windows / Local):
 *   & "C:\path\to\Local\php.exe" tests/schedule-cron-args-test.php
 *
 * @since 2.0.0
 */

namespace {
    // Schedule.php guards with `defined('ABSPATH') || exit;`.
    define( 'ABSPATH', __DIR__ . '/' );

    // Runtime toggle for the shadowed function_exists() (Action Scheduler on/off).
    $GLOBALS['__as_available'] = false;

    // Capture buckets for the stubbed scheduler calls.
    $GLOBALS['__cron_scheduled']   = array(); // wp_schedule_single_event calls
    $GLOBALS['__cron_unscheduled'] = array(); // wp_unschedule_event calls
    $GLOBALS['__cron_cleared']     = array(); // wp_clear_scheduled_hook calls
    $GLOBALS['__as_scheduled']     = array(); // as_schedule_single_action calls
    $GLOBALS['__as_unscheduled']   = array(); // as_unschedule_all_actions calls
    $GLOBALS['__cron_array']       = array(); // _get_cron_array() return
    $GLOBALS['__received']         = null;    // args process_scheduled_action got

    $failures   = 0;
    $assertions = 0;

    /**
     * Assert a condition, tracking pass/fail counts.
     */
    function check( $label, $condition ) {
        global $failures, $assertions;
        $assertions++;

        if ( $condition ) {
            echo "  PASS  {$label}\n";
        } else {
            $failures++;
            echo "  FAIL  {$label}\n";
        }
    }

    /**
     * Reset capture buckets between test groups.
     */
    function reset_captures() {
        $GLOBALS['__cron_scheduled']   = array();
        $GLOBALS['__cron_unscheduled'] = array();
        $GLOBALS['__cron_cleared']     = array();
        $GLOBALS['__as_scheduled']     = array();
        $GLOBALS['__as_unscheduled']   = array();
        $GLOBALS['__received']         = null;
    }

    /**
     * Stand-in mirroring Workflow_Processor::process_scheduled_action's signature
     * (three positional params, no $context/$unique_key). Used to prove the stored
     * args dispatch cleanly through core's call semantics.
     */
    function jn_test_process_scheduled_action( $post_id, $payload, $action_data ) {
        $GLOBALS['__received'] = array( $post_id, $payload, $action_data );
    }

    /**
     * Mimic how WP_Hook dispatches an event: when the callback's accepted_args (3)
     * is below the stored arg count, core slices to accepted_args and calls
     * call_user_func_array(). Numeric-keyed (positional) args reindex cleanly;
     * string-keyed (associative) args remain named -> PHP 8 named-arg matching.
     *
     * @return \Throwable|null The error raised, or null on success.
     */
    function jn_simulate_wp_dispatch( array $args ) {
        $sliced = array_slice( $args, 0, 3, true );

        try {
            call_user_func_array( 'jn_test_process_scheduled_action', $sliced );

            return null;
        } catch ( \Throwable $e ) {
            return $e;
        }
    }
}

/**
 * Helpers stub in the namespace Schedule.php imports
 * (`use MeuMouse\Joinotify\Core\Helpers;`). strip_objects is a pass-through here.
 */
namespace MeuMouse\Joinotify\Core {
    class Helpers {
        public static function strip_objects( $context ) {
            return $context;
        }
    }
}

/**
 * Function shadows in Schedule.php's own namespace. Unqualified calls inside the
 * class resolve here first (current namespace, then global), so these intercept
 * the WordPress / Action Scheduler calls without a WP bootstrap.
 */
namespace MeuMouse\Joinotify\Cron {
    function function_exists( $name ) {
        // Action Scheduler availability is driven by the test toggle so both
        // backends can be exercised in one process.
        if ( 'as_schedule_single_action' === $name || 'as_unschedule_all_actions' === $name ) {
            return (bool) $GLOBALS['__as_available'];
        }

        // Treat our namespaced stubs as "present" (e.g. wp_clear_scheduled_hook).
        return \function_exists( $name ) || \function_exists( __NAMESPACE__ . '\\' . $name );
    }

    function absint( $value ) {
        return abs( (int) $value );
    }

    function wp_schedule_single_event( $timestamp, $hook, $args = array() ) {
        $GLOBALS['__cron_scheduled'][] = compact( 'timestamp', 'hook', 'args' );

        return true;
    }

    function wp_clear_scheduled_hook( $hook, $args = array() ) {
        $GLOBALS['__cron_cleared'][] = compact( 'hook', 'args' );

        return 0;
    }

    function wp_next_scheduled( $hook, $args = array() ) {
        return false;
    }

    function wp_unschedule_event( $timestamp, $hook, $args = array() ) {
        $GLOBALS['__cron_unscheduled'][] = compact( 'timestamp', 'hook', 'args' );

        return true;
    }

    function _get_cron_array() {
        return $GLOBALS['__cron_array'];
    }

    function as_schedule_single_action( $timestamp, $hook, $args = array(), $group = '' ) {
        $GLOBALS['__as_scheduled'][] = compact( 'timestamp', 'hook', 'args', 'group' );

        return 555;
    }

    function as_unschedule_all_actions( $hook, $args = array(), $group = '' ) {
        $GLOBALS['__as_unscheduled'][] = compact( 'hook', 'args', 'group' );
    }
}

namespace {
    require __DIR__ . '/../admin/src/Cron/Schedule.php';

    use MeuMouse\Joinotify\Cron\Schedule;

    $post_id     = 123;
    $context     = array( 'receiver' => '5511999999999', 'order_id' => 42 );
    $action_data = array( 'id' => 'delay_node_1', 'data' => array( 'next_actions' => array() ) );
    $unique_key  = 'unique_abc';

    echo "== WP-Cron path: positional args ==\n";
    $GLOBALS['__as_available'] = false;
    reset_captures();

    $ok = Schedule::schedule_actions( $post_id, $context, 60, $action_data, $unique_key );

    check( 'schedule_actions returns true on the WP-Cron path', true === $ok );
    check( 'exactly one WP-Cron event scheduled', 1 === count( $GLOBALS['__cron_scheduled'] ) );

    $cron_args = $GLOBALS['__cron_scheduled'][0]['args'] ?? array();

    check( 'args are a positional list (keys 0..3)', array_keys( $cron_args ) === array( 0, 1, 2, 3 ) );
    check( 'no associative post_id key', ! array_key_exists( 'post_id', $cron_args ) );
    check( 'args[0] is the post_id', ( $cron_args[0] ?? null ) === $post_id );
    check( 'args[1] is the context payload', ( $cron_args[1] ?? null ) === $context );
    check( 'args[2] is the action_data', ( $cron_args[2] ?? null ) === $action_data );
    check( 'args[3] is the unique_key', ( $cron_args[3] ?? null ) === $unique_key );
    check( 'the ghost event was cleared before scheduling', 1 === count( $GLOBALS['__cron_cleared'] ) );
    check( 'no Action Scheduler call on the WP-Cron path', 0 === count( $GLOBALS['__as_scheduled'] ) );

    echo "\n== WP-Cron path: dispatch is fatal-free under PHP 8 ==\n";
    $error = jn_simulate_wp_dispatch( $cron_args );

    check( 'positional args dispatch without a fatal', null === $error );
    check( 'callback received the post_id positionally', ( $GLOBALS['__received'][0] ?? null ) === $post_id );
    check( 'callback received the context as $payload', ( $GLOBALS['__received'][1] ?? null ) === $context );
    check( 'callback received the action_data', ( $GLOBALS['__received'][2] ?? null ) === $action_data );

    echo "\n== Regression guard: the old associative shape WAS fatal ==\n";
    $legacy_assoc = array(
        'post_id'     => $post_id,
        'context'     => $context,
        'action_data' => $action_data,
        'unique_key'  => $unique_key,
    );
    $legacy_error = jn_simulate_wp_dispatch( $legacy_assoc );

    check( 'associative args raise a Throwable on dispatch', $legacy_error instanceof \Throwable );
    check(
        'the error is the "Unknown named parameter $context" fatal',
        $legacy_error instanceof \Throwable && false !== strpos( $legacy_error->getMessage(), 'Unknown named parameter $context' )
    );

    echo "\n== clear_scheduled_for_post: WP-Cron branch matches by post_id ==\n";
    $GLOBALS['__as_available'] = false;
    reset_captures();

    // Seed the cron array with a positional event for #123, a positional event for
    // another workflow (#999), and a legacy associative event for #123 (queued
    // before the fix) to confirm both shapes are matched for cleanup.
    $GLOBALS['__cron_array'] = array(
        1000 => array(
            'joinotify_scheduled_actions_event' => array(
                'sigA' => array( 'args' => array( 123, $context, $action_data, 'k1' ) ),
                'sigB' => array( 'args' => array( 999, $context, $action_data, 'k2' ) ),
            ),
        ),
        2000 => array(
            'joinotify_scheduled_actions_event' => array(
                'sigC' => array( 'args' => array( 'post_id' => 123, 'context' => $context, 'action_data' => $action_data, 'unique_key' => 'k3' ) ),
            ),
        ),
    );

    Schedule::clear_scheduled_for_post( 123 );

    $unscheduled_posts = array_map( static function ( $entry ) {
        $a = $entry['args'];

        return $a['post_id'] ?? ( $a[0] ?? null );
    }, $GLOBALS['__cron_unscheduled'] );

    check( 'two events unscheduled for post 123', 2 === count( $GLOBALS['__cron_unscheduled'] ) );
    check( 'every unscheduled event belongs to post 123', array( 123, 123 ) === array_map( 'intval', $unscheduled_posts ) );
    check( 'the unrelated workflow (#999) was left scheduled', ! in_array( 999, array_map( 'intval', $unscheduled_posts ), true ) );

    echo "\n== Action Scheduler path: named args preserved ==\n";
    $GLOBALS['__as_available'] = true;
    reset_captures();

    $ok_as = Schedule::schedule_actions( $post_id, $context, 60, $action_data, $unique_key );

    check( 'schedule_actions returns true on the Action Scheduler path', true === $ok_as );
    check( 'exactly one Action Scheduler action scheduled', 1 === count( $GLOBALS['__as_scheduled'] ) );
    check( 'no WP-Cron event scheduled when Action Scheduler is active', 0 === count( $GLOBALS['__cron_scheduled'] ) );

    $as_args = $GLOBALS['__as_scheduled'][0]['args'] ?? array();

    check( 'Action Scheduler args keep the named post_id', ( $as_args['post_id'] ?? null ) === $post_id );
    check( 'Action Scheduler args keep the named context', ( $as_args['context'] ?? null ) === $context );
    check( 'Action Scheduler args keep the named action_data', ( $as_args['action_data'] ?? null ) === $action_data );
    check( 'Action Scheduler args keep the named unique_key', ( $as_args['unique_key'] ?? null ) === $unique_key );
    check( 'scheduled under the per-post group', ( $GLOBALS['__as_scheduled'][0]['group'] ?? '' ) === 'joinotify_123' );
    check( 'a dedup unschedule ran before scheduling', 1 === count( $GLOBALS['__as_unscheduled'] ) );

    // Action Scheduler fires positionally via array_values(); confirm the named
    // shape still maps to (post_id, payload, action_data) once flattened.
    $as_positional = array_values( $as_args );
    $as_error = jn_simulate_wp_dispatch( $as_positional );

    check( 'array_values() of AS args dispatch without a fatal', null === $as_error );
    check( 'flattened AS args[0] is the post_id', ( $GLOBALS['__received'][0] ?? null ) === $post_id );

    echo "\n== summary ==\n";
    echo "  {$assertions} assertions, {$failures} failures\n";

    exit( $failures > 0 ? 1 : 0 );
}
