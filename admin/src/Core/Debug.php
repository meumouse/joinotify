<?php

namespace MeuMouse\Joinotify\Core;

use MeuMouse\Joinotify\Admin\Admin;

// Exit if accessed directly.
defined('ABSPATH') || exit;

/**
 * Debug class
 * 
 * @since 1.0.0
 * @version 1.4.7
 * @package MeuMouse\Joinotify\Core
 * @author MeuMouse.com
 */
class Debug {
    
    /**
     * Construct function
     * 
     * @since 1.0.0
     * @version 1.4.7
     * @return void
     */
    public function __construct() {
        if ( Admin::get_setting('enable_debug_mode') === 'yes' ) {
            define( 'JOINOTIFY_DEBUG_MODE', true );

            add_action( 'admin_enqueue_scripts', array( $this, 'debug_mode' ) );
        } else {
            define( 'JOINOTIFY_DEBUG_MODE', false );
        }
    }


    /**
     * Add styles and scripts for debug
     *
     * Debug mode keeps the admin bar visible, so the builder chrome has to be
     * pushed down by its height. The rules are attached to an inline-only style
     * handle instead of being printed on `admin_head`.
     *
     * @since 1.0.0
     * @version 2.3.0
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

        body.admin-bar #joinotify_choose_template_container {
            height: calc(100% - 32px);
            margin-top: 32px;
        }

		<?php $css = wp_strip_all_tags( ob_get_clean() );

		// An inline-only handle (no `src`) is the supported way to ship a stylesheet
		// that exists purely as inline CSS.
		wp_register_style( 'joinotify-debug-mode', false, array(), JOINOTIFY_VERSION );
		wp_enqueue_style( 'joinotify-debug-mode' );
		wp_add_inline_style( 'joinotify-debug-mode', $css );
    }
}