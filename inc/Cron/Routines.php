<?php

namespace MeuMouse\Joinotify\Cron;

use MeuMouse\Joinotify\Controller;

// Exit if accessed directly.
defined('ABSPATH') || exit;

/**
 * Class for handle with Cron routines
 * 
 * @since 1.2.0
 * @package MeuMouse.com
 */
class Routines {

	/**
	 * Construct function
	 *
	 * @since 1.2.0
	 * @return void
	 */
	public function __construct() {
		// register routine
		add_filter( 'cron_schedules', array( __CLASS__, 'register_custom_schedules' ) );

		// schedule connection check
		add_action( 'wp', array( __CLASS__, 'schedule_connection_check' ) );
	}


	/**
     * Register the custom schedule interval.
     *
     * @since 1.2.0
     * @param array $schedules | Existing WordPress schedules
     * @return array Modified schedules with six_hours interval
     */
    public static function register_custom_schedules( $schedules ) {
        $schedules['six_hours'] = array(
            'interval' => 6 * HOUR_IN_SECONDS,
            'display' => __( 'A cada 6 horas', 'joinotify' ),
        );

        return $schedules;
    }


	/**
     * Schedule the cron event if not already scheduled
     *
     * @since 1.2.0
	 * @return void
     */
    public static function schedule_connection_check() {
        if ( ! wp_next_scheduled('joinotify_check_phone_connection_event') ) {
            wp_schedule_event( time(), 'six_hours', 'joinotify_check_phone_connection_event' );
        }
    }


	/**
     * Get phone numbers and check their connection state
     *
     * @since 1.2.0
	 * @return void
     */
    public static function check_phone_connection() {
        $phones = get_option( 'joinotify_get_phones_senders', array() );

        if ( empty( $phones ) || ! is_array( $phones ) ) {
            return;
        }

        foreach ( $phones as $phone ) {
            Controller::get_connection_state( $phone );
        }
    }
}