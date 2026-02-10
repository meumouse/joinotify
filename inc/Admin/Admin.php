<?php

namespace MeuMouse\Joinotify\Admin;

// Exit if accessed directly.
defined('ABSPATH') || exit;

/**
 * Admin actions class
 * 
 * @since 1.0.0
 * @version 1.4.6
 * @package MeuMouse\Joinotify\Admin
 * @author MeuMouse.com
 */
class Admin {

    /**
     * Construct function
     * 
     * @since 1.0.0
     * @version 1.4.6
     * @return void
     */
    public function __construct() {
        // update default options on admin_init
        add_action( 'admin_init', array( $this, 'update_default_options' ), 99 );
    }


    /**
     * Gets the items from the array and inserts them into the option if it is empty,
     * or adds new items with default value to the option
     * 
     * @since 1.0.0
     * @version 1.3.0
     * @return void
     */
    public function update_default_options() {
        $get_options = Default_Options::set_default_options();
        $default_options = get_option('joinotify_settings', array());

        if ( empty( $default_options ) ) {
            update_option( 'joinotify_settings', $get_options );
        } else {
            foreach ( $get_options as $key => $value ) {
                if ( ! isset( $default_options[$key] ) ) {
                    $default_options[$key] = $value;
                }
            }

            update_option( 'joinotify_settings', $default_options );
        }
    }


    /**
     * Checks if the option exists and returns the indicated array item
     * 
     * @since 1.0.0
     * @param string $key | Option key
     * @return mixed | string or false
     */
    public static function get_setting( $key ) {
        $options = get_option( 'joinotify_settings', array() );

        // check if array key exists and return key
        if ( isset( $options[$key] ) ) {
            return $options[$key];
        }

        return false;
    }
}