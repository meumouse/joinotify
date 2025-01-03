<?php

namespace MeuMouse\Joinotify\Admin;

use MeuMouse\Joinotify\API\License;

// Exit if accessed directly.
defined('ABSPATH') || exit;

/**
 * Admin actions class
 * 
 * @since 1.0.0
 * @version 1.1.0
 * @package MeuMouse.com
 */
class Admin {

    /**
     * Construct function
     * 
     * @since 1.0.0
     * @return void
     */
    public function __construct() {
        // add admin menu
        add_action( 'admin_menu', array( $this, 'add_admin_menu' ) );

        // update default options on admin_init
        add_action( 'admin_init', array( $this, 'update_default_options' ) );

        // register new post type
        add_action( 'init', array( $this, 'register_joinotify_workflow_post_type' ) );
    }


    /**
     * Add admin menu
     * 
     * @since 1.0.0
     * @return void
     */
    public function add_admin_menu() {
        add_menu_page(
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
    }


    /**
     * Render new automations page builder
     * 
     * @since 1.0.0
     * @return void
     */
    public function render_builder_page() {
        include JOINOTIFY_INC . 'Views/Builder_Wrapper.php';
    }

    
    /**
     * Render menu page settings
     * 
     * @since 1.0.0
     * @return void
     */
    public function render_settings_page() {
        include JOINOTIFY_INC . 'Views/Settings.php';
    }


    /**
     * Display table with all workflows
     * 
     * @since 1.0.0
     * @return void
     */
    public function all_workflows_page() {
        $workflows_table = new \MeuMouse\Joinotify\Core\Workflows_Table();
        $workflows_table->prepare_items();

        echo '<div class="wrap"><h1 class="wp-heading-inline">' . __('Gerenciar fluxos', 'joinotify') . '</h1>';
        echo '<a class="page-title-action" href="'. admin_url('admin.php?page=joinotify-workflows-builder') .'">'. __('Adicionar novo fluxo', 'joinotify') .'</a>';
        echo '<form method="post">';
            $workflows_table->display();
        echo '</form></div>';
    }


    /**
     * Render license page settings
     * 
     * @since 1.0.0
     * @return void
     */
    public function render_license_page() {
        include JOINOTIFY_INC . 'Views/License.php';
    }


    /**
     * Set default options
     * 
     * @since 1.0.0
     * @version 1.1.0
     * @return array
     */
    public function set_default_options() {
        return apply_filters( 'Joinotify/Admin/Set_Default_Options', array(
            'enable_whatsapp_integration' => 'yes',
            'enable_woocommerce_integration' => 'yes',
            'enable_elementor_integration' => 'yes',
            'enable_wpforms_integration' => 'yes',
            'enable_flexify_checkout_integration' => 'yes',
            'enable_wordpress_integration' => 'yes',
            'joinotify_default_country_code' => '55',
            'test_number_phone' => '',
            'enable_proxy_api' => 'yes',
            'proxy_api_key' => '',
            'send_text_proxy_api_route' => 'send-message/text',
            'send_media_proxy_api_route' => 'send-message/media',
            'enable_debug_mode' => 'no',
        ));
    }


    /**
     * Gets the items from the array and inserts them into the option if it is empty,
     * or adds new items with default value to the option
     * 
     * @since 1.0.0
     * @return void
     */
    public function update_default_options() {
        $get_options = $this->set_default_options();
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
        $options = get_option('joinotify_settings', array());

        // check if array key exists and return key
        if ( isset( $options[$key] ) ) {
            return $options[$key];
        }

        return false;
    }


    /**
     * Register "joinotify-workflow" post type
     * 
     * @since 1.0.0
     * @return void
     */
    public function register_joinotify_workflow_post_type() {
        $labels = array(
            'name'               => _x( 'Fluxos', 'post type general name', 'joinotify' ),
            'singular_name'      => _x( 'Fluxo', 'post type singular name', 'joinotify' ),
            'menu_name'          => _x( 'Fluxos', 'admin menu', 'joinotify' ),
            'name_admin_bar'     => _x( 'Fluxo', 'add new on admin bar', 'joinotify' ),
            'add_new'            => _x( 'Adicionar novo', 'fluxo', 'joinotify' ),
            'add_new_item'       => __( 'Adicionar novo fluxo', 'joinotify' ),
            'new_item'           => __( 'Novo fluxo', 'joinotify' ),
            'edit_item'          => __( 'Editar fluxo', 'joinotify' ),
            'view_item'          => __( 'Ver fluxo', 'joinotify' ),
            'all_items'          => __( 'Todos os fluxos', 'joinotify' ),
            'search_items'       => __( 'Pesquisar fluxos', 'joinotify' ),
            'parent_item_colon'  => __( 'Fluxo pai:', 'joinotify' ),
            'not_found'          => __( 'Nenhum fluxo encontrado.', 'joinotify' ),
            'not_found_in_trash' => __( 'Nenhum fluxo encontrado na lixeira.', 'joinotify' )
        );
    
        $args = array(
            'labels' => $labels,
            'description'        => __( 'Descrição.', 'joinotify' ),
            'public'             => true,
            'publicly_queryable' => true,
            'show_ui'            => false,
            'show_in_menu'       => false,
            'query_var'          => true,
            'capability_type'    => 'post',
            'rewrite'            => array( 'slug' => '/workflows', 'with_front' => false ),
            'has_archive'        => true,
            'hierarchical'       => false,
            'menu_position'      => null,
            'supports'           => array( 'title', 'editor', 'author', 'thumbnail', 'custom-fields' )
        );
    
        register_post_type( 'joinotify-workflow', $args );

        // update permafluxos
        flush_rewrite_rules();
    }
}