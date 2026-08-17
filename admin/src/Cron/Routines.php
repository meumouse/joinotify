<?php

namespace MeuMouse\Joinotify\Cron;

use MeuMouse\Joinotify\Api\Controller;
use MeuMouse\Joinotify\Api\Transport;
use MeuMouse\Joinotify\Api\Workflow_Templates;
use MeuMouse\Joinotify\Admin\Admin;

// Exit if accessed directly.
defined('ABSPATH') || exit;

/**
 * Class for handle with Cron routines
 * 
 * @since 1.2.0
 * @version 1.4.7
 * @package MeuMouse\Joinotify\Cron
 * @author MeuMouse.com
 */
class Routines {

	/**
	 * Construct function
	 *
	 * @since 1.2.0
     * @version 1.4.7
	 * @return void
	 */
	public function __construct() {
		// register custom schedule
		add_filter( 'cron_schedules', array( __CLASS__, 'register_custom_schedules' ) );

		// Schedule the cron event if not already scheduled
		if ( ! wp_next_scheduled('joinotify_check_phone_connection_event') ) {
            wp_schedule_event( time(), 'six_hours', 'joinotify_check_phone_connection_event' );
        }

		// make request
		add_action( 'joinotify_check_phone_connection_event', array( __CLASS__, 'check_phone_connection' ) );

        // Updates are served by WordPress.org, so the plugin no longer polls an
        // update server of its own. Clear the events left behind by older builds.
        self::unschedule_legacy_update_events();

        // Schedule the cron event for get templates count
        if ( ! wp_next_scheduled('joinotify_update_templates_count') ) {
            wp_schedule_event( time(), 'daily', 'joinotify_update_templates_count' );
        }

        add_action( 'joinotify_update_templates_count', array( $this, 'fetch_templates_count' ) );
	}


	/**
     * Clear the update-polling events scheduled by pre-2.4.0 builds.
     *
     * Those events pointed at Api\Updater, which no longer exists. Left behind,
     * WP-Cron would keep firing a callback that resolves to nothing on every
     * daily run.
     *
     * @since 2.3.0
     * @return void
     */
    public static function unschedule_legacy_update_events() {
        $legacy_events = array(
            'joinotify_check_plugin_updates_event',
            'joinotify_auto_update_event',
            'joinotify_check_daily_update',
        );

        foreach ( $legacy_events as $event ) {
            $timestamp = wp_next_scheduled( $event );

            if ( $timestamp ) {
                wp_unschedule_event( $timestamp, $event );
            }
        }
    }


	/**
     * Register the custom schedule interval
     *
     * @since 1.2.0
     * @param array $schedules | Existing WordPress schedules
     * @return array Modified schedules with six_hours interval
     */
    public static function register_custom_schedules( $schedules ) {
        $schedules['six_hours'] = array(
            'interval' => 6 * HOUR_IN_SECONDS,
            'display' => __( 'Every 6 hours', 'joinotify' ),
        );

        $schedules['daily'] = array(
            'interval' => DAY_IN_SECONDS,
            'display' => __( 'Daily', 'joinotify' ),
        );

        return $schedules;
    }


	/**
     * Get phone numbers and check their connection state
     *
     * @since 1.2.0
     * @version 2.3.0
	 * @return void
     */
    public static function check_phone_connection() {
        // The slots manager only knows about Evolution instances; on the Cloud
        // API a number lives on the panel and has no connection state to poll.
        if ( Transport::is_cloud() ) {
            return;
        }

        $phones = get_option( 'joinotify_get_phones_senders', array() );

        if ( empty( $phones ) || ! is_array( $phones ) ) {
            return;
        }

        foreach ( $phones as $phone ) {
            Controller::get_connection_state( $phone );
        }
    }


    /**
     * Fetch templates count from Joinotify repository
     * 
     * @since 1.2.0
     * @return void
     */
    public function fetch_templates_count() {
        // get templates count from the templates API
        $template_count = Workflow_Templates::get_templates_count();

        if ( $template_count !== null ) {
            update_option( 'joinotify_get_templates_count', $template_count );
        }
    }
}
