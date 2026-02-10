<?php

namespace MeuMouse\Joinotify\Admin;

use MeuMouse\Joinotify\Admin\Components as Admin_Components;

// Exit if accessed directly.
defined('ABSPATH') || exit;

/**
 * Register plugin menu
 * 
 * @since 1.4.6
 * @package MeuMouse\Joinotify\Admin
 * @author MeuMouse.com
 */
class Settings {

    /**
     * Construct function
     * 
     * @since 1.4.6
     * @return void
     */
    public function __construct() {
        // render settings tabs
        add_action( 'Joinotify/Admin/Settings_Nav_Tabs', array( $this, 'render_settings_tabs' ) );
    }


    /**
     * Render settings nav tabs
     *
     * @since 1.1.0
     */
    public function render_settings_tabs() {
        $tabs = Admin_Components::get_settings_tabs();

        foreach ( $tabs as $tab ) {
            printf( '<a href="#%1$s" class="nav-tab">%2$s %3$s</a>', esc_attr( $tab['id'] ), $tab['icon'], $tab['label'] );
        }
    }
}