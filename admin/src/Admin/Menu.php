<?php

namespace MeuMouse\Joinotify\Admin;

use MeuMouse\Joinotify\Api\License;
use MeuMouse\Joinotify\Core\Workflows_Table;

// Exit if accessed directly.
defined('ABSPATH') || exit;

/**
 * Register plugin menu
 * 
 * @since 1.4.6
 * @package MeuMouse\Joinotify\Admin
 * @author MeuMouse.com
 */
class Menu {

    /**
     * Construct function
     * 
     * @since 1.4.6
     * @return void
     */
    public function __construct() {
        // add admin menu
        add_action( 'admin_menu', array( $this, 'add_admin_menu' ), 99 );
    }


    /**
     * Add admin menu
     * 
     * @since 1.0.0
     * @version 1.4.5
     * @return void
     */
    public function add_admin_menu() {
        $hook = add_menu_page(
            esc_html__( 'Joinotify', 'joinotify' ), // label
            esc_html__( 'Joinotify', 'joinotify' ), // menu label
            'manage_options', // capatibilities
            'joinotify-workflows', // slug
            array( $this, 'all_workflows_page' ), // callback
            'data:image/svg+xml;base64,' . base64_encode('<svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 703 882.5"><path d="M908.66,248V666a126.5,126.5,0,0,1-207.21,97.41l-16.7-16.7L434.08,496.07l-62-62a47.19,47.19,0,0,0-72,30.86V843.36a47.52,47.52,0,0,0,69.57,35.22l19.3-19.3,56-56,81.19-81.19,10.44-10.44a47.65,47.65,0,0,1,67.63,65.05l-13,13L428.84,952.12l-9.59,9.59a128,128,0,0,1-213.59-95.18V413.17a124.52,124.52,0,0,1,199.78-82.54l22.13,22.13L674.45,599.64l46.22,46.22,17,17a47.8,47.8,0,0,0,71-31.44V270.19a48.19,48.19,0,0,0-75-40.05L720.43,243.4l-68.09,68.09L575.7,388.13a48.39,48.39,0,0,1-67.43-67.93L680,148.46A136,136,0,0,1,908.66,248Z" transform="translate(-205.66 -112.03)" style="fill:#fff"/></svg>'),
            5, // menu priority
        );

        // Main page as first submenu item with a different name
        add_submenu_page(
            'joinotify-workflows', // parent page slug
            esc_html__( 'Todos os fluxos', 'joinotify' ), // page title
            esc_html__( 'Todos os fluxos', 'joinotify' ), // submenu title
            'manage_options', // user capabilities
            'joinotify-workflows', // page slug (same as the main menu page)
            array( $this, 'all_workflows_page' ) // callback
        );

        // Register screen options on proper load hook.
        add_action( "load-{$hook}", array( $this, 'workflows_screen_options' ) );

        if ( License::is_valid() ) {
            // add new workflow
            add_submenu_page(
                'joinotify-workflows', // parent page slug
                esc_html__( 'Adicionar novo fluxo', 'joinotify' ), // page title
                esc_html__( 'Adicionar novo fluxo', 'joinotify' ), // submenu title
                'manage_options', // user capabilities
                'joinotify-workflows-builder', // page slug
                array( $this, 'render_builder_page' ) // callback
            );

            // settings page
            add_submenu_page(
                'joinotify-workflows', // parent page slug
                esc_html__( 'Configurações', 'joinotify' ), // page title
                esc_html__( 'Configurações', 'joinotify' ), // submenu title
                'manage_options', // user capabilities
                'joinotify-settings', // page slug
                array( $this, 'render_settings_page' ) // callback
            );
        }

        // license page
        add_submenu_page(
            'joinotify-workflows', // parent page slug
            esc_html__( 'Licença', 'joinotify' ), // page title
            esc_html__( 'Licença', 'joinotify' ), // submenu title
            'manage_options', // user capabilities
            'joinotify-license', // page slug
            array( $this, 'render_license_page' ) // callback
        );

        // prevent uncaught error when accessing the builder page
        if ( isset( $_GET['page'] ) && $_GET['page'] === 'joinotify-workflows-builder' ) {
            add_submenu_page(
                null, // ghost slug (no menu item)
                esc_html__( 'Editar fluxo', 'joinotify' ),
                esc_html__( 'Editar fluxo', 'joinotify' ),
                'manage_options', // user capabilities
                'joinotify-workflows-builder', // page slug
                array( $this, 'render_builder_page' ) // callback
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
            'label'   => __( 'Fluxos por página', 'joinotify' ),
            'default' => 20,
            'option'  => 'joinotify_workflows_per_page',
        ));
    }


    /**
     * Render new automations page builder
     * 
     * @since 1.0.0
     * @version 1.3.0
     * @return void
     */
    public function render_builder_page() {
        if ( ! current_user_can( 'manage_options' ) ) {
            wp_die( esc_html__( 'Você não tem permissão para acessar esta página.', 'joinotify' ) );
        }

        /**
         * Render the content page
         * 
         * @since 1.3.0
         */
        do_action('Joinotify/Admin/Builder_Page');
    }

    
    /**
     * Render menu page settings
     * 
     * @since 1.0.0
     * @return void
     */
    public function render_settings_page() {
        include JOINOTIFY_SRC . 'Views/Settings.php';
    }


    /**
     * Render license page settings
     * 
     * @since 1.0.0
     * @return void
     */
    public function render_license_page() {
        include JOINOTIFY_SRC . 'Views/License.php';
    }


    /**
     * Display table with all workflows
     * 
     * @since 1.0.0
     * @version 1.4.5
     * @return void
     */
    public function all_workflows_page() {
        $workflows_table = new Workflows_Table();
        $workflows_table->prepare_items();

        echo '<div class="wrap"><h1 class="wp-heading-inline">' . __('Gerenciar fluxos', 'joinotify') . '</h1>';
        echo '<a class="page-title-action" href="'. admin_url('admin.php?page=joinotify-workflows-builder') .'">'. __('Adicionar novo fluxo', 'joinotify') .'</a>';
        echo '<form method="post">';
            $workflows_table->display();
        echo '</form></div>';
    }
}
