<?php

namespace MeuMouse\Joinotify\Cron;

use WP_Cron;

// Exit if accessed directly.
defined('ABSPATH') || exit;

/**
 * Schedule messages for a future time or date
 * 
 * @since 1.0.0
 * @package MeuMouse.com
 */
class Schedule {

    /**
     * Construct function
     * 
     * @since 1.0.0
     * @return void
     */
    public function __construct() {
        add_action( 'joinotify_schedule_actions_event', array( '\MeuMouse\Joinotify\Core\Workflow_Processor', 'process_scheduled_action' ), 10, 3 );        
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
     * Schedule a message for a future time or date
     * 
     * @since 1.0.0
     * @param string $post_id | Post ID
     * @param string|int $order_id | Order ID
     * @param string $delay_time | Time to delay in seconds
     * @param array $action_data | Actions for execute
     * @return void
     */
    public static function schedule_actions( $post_id, $order_id, $delay_time, $action_data ) {
        $timestamp = time() + $delay_time;
        $hook = 'joinotify_schedule_actions_event';
        $args = array(
            'post_id' => $post_id,
            'order_id' => $order_id,
            'action_data' => $action_data,
        );

        // prevent duplicate events
        if ( ! wp_next_scheduled( $hook, $args ) ) {
            wp_schedule_single_event( $timestamp, $hook, $args );
        }
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
}