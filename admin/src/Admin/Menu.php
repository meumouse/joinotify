<?php

namespace MeuMouse\Joinotify\Admin;

use MeuMouse\Joinotify\Api\License;

// Exit if accessed directly.
defined('ABSPATH') || exit;

/**
 * Register plugin menu.
 *
 * @since 1.4.6
 * @version 1.4.7
 * @package MeuMouse\Joinotify\Admin
 * @author MeuMouse.com
 */
class Menu {

    /**
     * Construct function.
     *
     * @since 1.4.6
     * @return void
     */
    public function __construct() {
        add_action( 'admin_menu', array( $this, 'add_admin_menu' ), 99 );
    }


    /**
     * Add admin menu.
     *
     * @since 1.0.0
     * @version 1.4.7
     * @return void
     */
    public function add_admin_menu() {
        $hook = add_menu_page(
            esc_html__( 'Joinotify', 'joinotify' ),
            esc_html__( 'Joinotify', 'joinotify' ),
            'manage_options',
            'joinotify-workflows',
            array( $this, 'all_workflows_page' ),
            'data:image/svg+xml;base64,' . base64_encode( '<svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 703 882.5"><path d="M908.66,248V666a126.5,126.5,0,0,1-207.21,97.41l-16.7-16.7L434.08,496.07l-62-62a47.19,47.19,0,0,0-72,30.86V843.36a47.52,47.52,0,0,0,69.57,35.22l19.3-19.3,56-56,81.19-81.19,10.44-10.44a47.65,47.65,0,0,1,67.63,65.05l-13,13L428.84,952.12l-9.59,9.59a128,128,0,0,1-213.59-95.18V413.17a124.52,124.52,0,0,1,199.78-82.54l22.13,22.13L674.45,599.64l46.22,46.22,17,17a47.8,47.8,0,0,0,71-31.44V270.19a48.19,48.19,0,0,0-75-40.05L720.43,243.4l-68.09,68.09L575.7,388.13a48.39,48.39,0,0,1-67.43-67.93L680,148.46A136,136,0,0,1,908.66,248Z" transform="translate(-205.66 -112.03)" style="fill:#fff"/></svg>' ),
            5
        );

        add_submenu_page(
            'joinotify-workflows',
            esc_html__( 'All workflows', 'joinotify' ),
            esc_html__( 'All workflows', 'joinotify' ),
            'manage_options',
            'joinotify-workflows',
            array( $this, 'all_workflows_page' )
        );

        if ( License::is_valid() ) {
            add_submenu_page(
                'joinotify-workflows',
                esc_html__( 'Add new workflow', 'joinotify' ),
                esc_html__( 'Add new workflow', 'joinotify' ),
                'manage_options',
                'joinotify-workflows-builder',
                array( $this, 'render_builder_page' )
            );
        }

        // The Vue settings shell must remain reachable even when the license is inactive.
        add_submenu_page(
            'joinotify-workflows',
            esc_html__( 'Settings', 'joinotify' ),
            esc_html__( 'Settings', 'joinotify' ),
            'manage_options',
            'joinotify-settings',
            array( $this, 'render_settings_page' )
        );

        add_submenu_page(
            'joinotify-workflows',
            esc_html__( 'License', 'joinotify' ),
            esc_html__( 'License', 'joinotify' ),
            'manage_options',
            'joinotify-license',
            array( $this, 'render_license_page' )
        );

        if ( isset( $_GET['page'] ) && $_GET['page'] === 'joinotify-workflows-builder' ) {
            add_submenu_page(
                null,
                esc_html__( 'Edit workflow', 'joinotify' ),
                esc_html__( 'Edit workflow', 'joinotify' ),
                'manage_options',
                'joinotify-workflows-builder',
                array( $this, 'render_builder_page' )
            );
        }
    }


    /**
     * Register screen options for workflows list table.
     *
     * @since 1.4.5
     * @return void
     */
    public function workflows_screen_options() {
        add_screen_option( 'per_page', array(
            'label'   => __( 'Workflows per page', 'joinotify' ),
            'default' => 20,
            'option'  => 'joinotify_workflows_per_page',
        ) );
    }


    /**
     * Render new automations page builder.
     *
     * @since 1.0.0
     * @version 1.4.7
     * @return void
     */
    public function render_builder_page() {
        if ( ! current_user_can( 'manage_options' ) ) {
            wp_die( esc_html__( 'You do not have permission to access this page.', 'joinotify' ) );
        }

        do_action( 'Joinotify/Admin/Builder_Page' );
    }


    /**
     * Render menu page settings.
     *
     * @since 1.0.0
     * @return void
     */
    public function render_settings_page() {
        include JOINOTIFY_SRC . 'Views/Settings.php';
    }


    /**
     * Render license page settings.
     *
     * @since 1.0.0
     * @return void
     */
    public function render_license_page() {
        wp_enqueue_style(
            'joinotify-license-styles',
            JOINOTIFY_URL . 'assets/admin/css/license/styles.css',
            array(),
            JOINOTIFY_VERSION
        );

        include JOINOTIFY_SRC . 'Views/License.php';
    }


    /**
     * Display the Vue workflows screen.
     *
     * @since 1.4.7
     * @return void
     */
    public function all_workflows_page() {
        if ( ! current_user_can( 'manage_options' ) ) {
            wp_die( esc_html__( 'You do not have permission to access this page.', 'joinotify' ) );
        }

        // The Vue app fetches its bootstrap payload over REST (admin/workflows/bootstrap).
        include JOINOTIFY_SRC . 'Views/Workflows.php';
    }
}
