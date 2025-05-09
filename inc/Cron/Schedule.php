<?php

namespace MeuMouse\Joinotify\Cron;

use WP_Cron;

// Exit if accessed directly.
defined('ABSPATH') || exit;

/**
 * Schedule messages for a future time or date
 * 
 * @since 1.0.0
 * @version 1.3.0
 * @package MeuMouse.com
 */
class Schedule {

    /**
     * Construct function
     * 
     * @since 1.0.0
     * @version 1.3.0
     * @return void
     */
    public function __construct() {
        // process scheduled actions
        add_action( 'joinotify_scheduled_actions_event', array( '\MeuMouse\Joinotify\Core\Workflow_Processor', 'process_scheduled_action' ), 10, 3 );

        // update coupon expiration
        add_action( 'joinotify_update_coupon_expiration', array( $this, 'update_coupon_expiration' ), 10, 1 );
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
     * @version 1.3.0
     * @param string $post_id | Post ID
     * @param array $context | Context data
     * @param string $delay_time | Time to delay in seconds
     * @param array $action_data | Actions for execute
     * @return void
     */
    public static function schedule_actions( $post_id, $context, $delay_time, $action_data ) {
        $timestamp = time() + $delay_time;
        $hook = 'joinotify_scheduled_actions_event';
        $args = array(
            'post_id' => $post_id,
            'context' => $context,
            'action_data' => $action_data,
            'unique_key' => $action_data['id'] ?? uniqid('action_', true),
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
     * Update the coupon expiration date to the previous day after expiration
     *
     * @since 1.2.2
     * @param int $coupon_id | The WooCommerce coupon ID
     * @return void
     */
    public function update_coupon_expiration( $coupon_id ) {
        if ( ! $coupon_id ) {
            return;
        }
    
        $coupon = new \WC_Coupon( $coupon_id );
    
        // Set expiration to the previous day
        $new_expiry_date = strtotime('yesterday');
        $coupon->set_date_expires( $new_expiry_date );
        $coupon->save();
    
        if (  JOINOTIFY_DEV_MODE ) {
            error_log( "Coupon {$coupon_id} expiration updated to previous day" );
        }
    }
}