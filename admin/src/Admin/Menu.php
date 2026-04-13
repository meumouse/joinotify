<?php

namespace MeuMouse\Joinotify\Admin;

use MeuMouse\Joinotify\Api\License;
use WP_Query;

// Exit if accessed directly.
defined('ABSPATH') || exit;

/**
 * Register plugin menu.
 *
 * @since 1.4.6
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
            esc_html__( 'Todos os fluxos', 'joinotify' ),
            esc_html__( 'Todos os fluxos', 'joinotify' ),
            'manage_options',
            'joinotify-workflows',
            array( $this, 'all_workflows_page' )
        );

        if ( License::is_valid() ) {
            add_submenu_page(
                'joinotify-workflows',
                esc_html__( 'Adicionar novo fluxo', 'joinotify' ),
                esc_html__( 'Adicionar novo fluxo', 'joinotify' ),
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
     * @since 1.4.8
     * @return void
     */
    public function all_workflows_page() {
        if ( ! current_user_can( 'manage_options' ) ) {
            wp_die( esc_html__( 'You do not have permission to access this page.', 'joinotify' ) );
        }

        $bootstrap = $this->get_workflows_bootstrap();

        include JOINOTIFY_SRC . 'Views/Workflows.php';
    }


    /**
     * Build the initial data payload for the workflows Vue screen.
     *
     * @since 1.4.8
     * @return array<string,mixed>
     */
    private function get_workflows_bootstrap() {
        $status = isset( $_GET['post_status'] ) ? sanitize_text_field( wp_unslash( $_GET['post_status'] ) ) : 'publish';
        $status = in_array( $status, array( 'publish', 'draft', 'trash' ), true ) ? $status : 'publish';
        $current_page = isset( $_GET['paged'] ) ? max( 1, absint( $_GET['paged'] ) ) : 1;
        $per_page = 20;

        $query = new WP_Query(
            array(
                'post_type'      => 'joinotify-workflow',
                'post_status'    => $status,
                'posts_per_page' => $per_page,
                'paged'          => $current_page,
                'orderby'        => 'date',
                'order'          => 'DESC',
            )
        );

        $counts = wp_count_posts( 'joinotify-workflow' );
        $workflows = array();

        foreach ( $query->posts as $post ) {
            $workflows[] = array(
                'id'                   => (int) $post->ID,
                'name'                 => get_the_title( $post ),
                'created_at'           => get_post_time( 'Y-m-d H:i:s', false, $post ),
                'status'               => $post->post_status,
                'edit_url'             => admin_url( 'admin.php?page=joinotify-workflows-builder&id=' . (int) $post->ID ),
                'delete_url'           => admin_url( 'admin.php?page=joinotify-workflows&action=delete&id=' . (int) $post->ID ),
                'restore_url'          => admin_url( 'admin.php?page=joinotify-workflows&action=restore&id=' . (int) $post->ID ),
                'delete_permanently_url' => admin_url( 'admin.php?page=joinotify-workflows&action=delete_permanently&id=' . (int) $post->ID ),
            );
        }

        return array(
            'page'         => 'workflows',
            'title'        => __( 'Gerenciar fluxos', 'joinotify' ),
            'create_url'   => admin_url( 'admin.php?page=joinotify-workflows-builder' ),
            'active_status'=> $status,
            'loading_delay' => 350,
            'workflows'    => $workflows,
            'counts'       => array(
                'publish' => isset( $counts->publish ) ? (int) $counts->publish : 0,
                'draft'   => isset( $counts->draft ) ? (int) $counts->draft : 0,
                'trash'   => isset( $counts->trash ) ? (int) $counts->trash : 0,
            ),
            'pagination'   => array(
                'current_page' => $current_page,
                'per_page'     => $per_page,
                'total_items'  => (int) $query->found_posts,
                'total_pages'  => (int) max( 1, ceil( $query->found_posts / $per_page ) ),
            ),
        );
    }
}
