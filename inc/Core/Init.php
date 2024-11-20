<?php

namespace MeuMouse\Joinotify\Core;

// Exit if accessed directly.
defined('ABSPATH') || exit;

/**
 * Initialize classes class
 * 
 * @since 1.0.0
 * @package MeuMouse.com
 */
class Init {

    /**
     * Construct function
     * 
     * @since 1.0.0
     * @return void
     */
    public function __construct() {
        self::instance_classes();
    }


    /**
     * Instance classes after load Composer
     * 
     * @since 1.0.0
     * @return void
     */
    public static function instance_classes() {
        $classes = apply_filters( 'Joinotify/Init/Instance_Classes', array(
            '\MeuMouse\Joinotify\Core\License',
            '\MeuMouse\Joinotify\Core\Admin',
            '\MeuMouse\Joinotify\Core\Assets',
            '\MeuMouse\Joinotify\Core\Ajax',
            '\MeuMouse\Joinotify\Core\Debug',
            '\MeuMouse\Joinotify\Builder\Core',
            '\MeuMouse\Joinotify\Integrations\Wordpress',
            '\MeuMouse\Joinotify\Integrations\Woocommerce',
            '\MeuMouse\Joinotify\Integrations\Woo_Subscriptions',
            '\MeuMouse\Joinotify\Integrations\Flexify_Checkout',
            '\MeuMouse\Joinotify\Integrations\Elementor',
            '\MeuMouse\Joinotify\Integrations\Wpforms',
            '\MeuMouse\Joinotify\Validations\Media_Types',
            '\MeuMouse\Joinotify\API\Controller',
            '\MeuMouse\Joinotify\Core\Workflow_Processor',
            '\MeuMouse\Joinotify\Cron\Schedule',
        	'\MeuMouse\Joinotify\Core\Updater',
        ));

        foreach ( $classes as $class ) {
            if ( class_exists( $class ) ) {
                new $class();
            }
        }
    }
}