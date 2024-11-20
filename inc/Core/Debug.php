<?php

namespace MeuMouse\Joinotify\Core;

// Exit if accessed directly.
defined('ABSPATH') || exit;

/**
 * Debug class
 * 
 * @since 1.0.0
 * @package MeuMouse.com
 */
class Debug {
    
    /**
     * Construct function
     * 
     * @since 1.0.0
     * @return void
     */
    public function __construct() {
        if ( defined('JOINOTIFY_DEBUG_MODE') && JOINOTIFY_DEBUG_MODE === true ) {
            add_action( 'admin_head', array( $this, 'debug_mode' ) );
        }
    }


    /**
     * Add styles and scripts for debug
     * 
     * @since 1.0.0
     * @return void
     */
    public function debug_mode() {
        ob_start(); ?>

		#wpadminbar {
            display: block !important;
        }

        .builder-navbar {
            margin-top: 32px;
        }

        #joinotify_actions_group,
        #joinotify_builder_funnel,
        #joinotify_triggers_group {
            margin-top: calc(5rem + 32px);
            height: calc(100% - 6rem);
        }

        #joinotify-automations-builder {
            z-index: 1089 !important;
        }

        .offcanvas,
        .offcanvas-lg,
        .offcanvas-md,
        .offcanvas-sm,
        .offcanvas-xl,
        .offcanvas-xxl {
            margin-top: calc(5rem + 32px);
        }

        .triggers-content-container {
            top: 7rem;
            max-height: calc(100% - 7rem);
        }

        #joinotify_builder_funnel {
            height: calc(100% - 8rem);
        }

		<?php $css = ob_get_clean();
		$css = wp_strip_all_tags( $css );

		printf( __('<style>%s</style>'), $css );
    }
}