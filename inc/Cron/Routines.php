<?php

namespace MeuMouse\Joinotify\Cron;

use MeuMouse\Joinotify\API\Controller;
use MeuMouse\Joinotify\API\Workflow_Templates;
use MeuMouse\Joinotify\Core\Updater;
use MeuMouse\Joinotify\Admin\Admin;

// Exit if accessed directly.
defined('ABSPATH') || exit;

/**
 * Class for handle with Cron routines
 * 
 * @since 1.2.0
 * @version 1.3.0
 * @package MeuMouse.com
 */
class Routines {

	/**
	 * Construct function
	 *
	 * @since 1.2.0
     * @version 1.3.0
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

        // Schedule the cron event if not already scheduled
        if ( ! wp_next_scheduled('joinotify_check_plugin_updates_event') ) {
            wp_schedule_event( time(), 'daily', 'joinotify_check_plugin_updates_event' );
        }

        add_action( 'joinotify_check_plugin_updates_event', array( '\MeuMouse\Joinotify\Core\Updater', 'check_daily_updates' ) );

        // enable auto updates
        if ( Admin::get_setting('enable_auto_updates') === 'yes' ) {
            // Schedule the cron event if not already scheduled
            if ( ! wp_next_scheduled('joinotify_auto_update_event') ) {
                wp_schedule_event( time(), 'daily', 'joinotify_auto_update_event' );
            }

            // auto update plugin action
            add_action( 'joinotify_auto_update_event', array( '\MeuMouse\Joinotify\Core\Updater', 'auto_update_plugin' ) );
        }

        // schedule daily updates
        if ( ! wp_next_scheduled('joinotify_check_daily_update') ) {
            wp_schedule_event( time(), 'daily', 'joinotify_check_daily_update' );
        }

        // check daily updates
        add_action( 'joinotify_check_daily_update', array( '\MeuMouse\Joinotify\Core\Updater', 'check_daily_updates' ) );

        // Schedule the cron event for get templates count
        if ( ! wp_next_scheduled('joinotify_update_templates_count') ) {
            wp_schedule_event( time(), 'daily', 'joinotify_update_templates_count' );
        }

        add_action( 'joinotify_update_templates_count', array( $this, 'fetch_templates_count' ) );
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
            'display' => __( 'A cada 6 horas', 'joinotify' ),
        );

        $schedules['daily'] = array(
            'interval' => DAY_IN_SECONDS,
            'display' => __( 'Diariamente', 'joinotify' ),
        );

        return $schedules;
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


    /**
     * Fetch templates count from Joinotify repository
     * 
     * @since 1.2.0
     * @return void
     */
    public function fetch_templates_count() {
        // get templates count
        $template_count = Workflow_Templates::get_templates_count( 'meumouse', 'joinotify', 'dist/templates', 'main', null );

        if ( $template_count !== null ) {
            update_option( 'joinotify_get_templates_count', $template_count );
        }
    }
}