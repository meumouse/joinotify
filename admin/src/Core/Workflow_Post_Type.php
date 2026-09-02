<?php

namespace MeuMouse\Joinotify\Core;

// Exit if accessed directly.
defined('ABSPATH') || exit;

/**
 * Process workflow content and send messages on fire hooks
 * 
 * @since 1.4.6
 * @package MeuMouse\Joinotify\Core
 * @author MeuMouse.com
 */
class Workflow_Post_Type {

    /**
     * Constructor
     * 
     * @since 1.4.6
     * @version 2.3.4
     * @return void
     */
    public function __construct() {
        // Init bootstraps this class on `init` at priority 10, so hooking
        // `init` at the same priority appended the callback to the array
        // WP_Hook was already iterating over -- and it never ran, leaving the
        // post type unregistered. Register it inline instead: `init` is exactly
        // where post types belong, and we are already inside it.
        if ( did_action('init') ) {
            $this->register_joinotify_workflow_post_type();

            return;
        }

        add_action( 'init', array( $this, 'register_joinotify_workflow_post_type' ) );
    }


    /**
     * Register "joinotify-workflow" post type
     * 
     * @since 1.0.0
     * @version 1.4.7
     * @return void
     */
    public function register_joinotify_workflow_post_type() {
        $labels = array(
            'name'               => _x( 'Flows', 'post type general name', 'joinotify' ),
            'singular_name'      => _x( 'Flow', 'post type singular name', 'joinotify' ),
            'menu_name'          => _x( 'Flows', 'admin menu', 'joinotify' ),
            'name_admin_bar'     => _x( 'Flow', 'add new on admin bar', 'joinotify' ),
            'add_new'            => _x( 'Add new', 'fluxo', 'joinotify' ),
            'add_new_item'       => __( 'Add new flow', 'joinotify' ),
            'new_item'           => __( 'New flow', 'joinotify' ),
            'edit_item'          => __( 'Edit flow', 'joinotify' ),
            'view_item'          => __( 'View flow', 'joinotify' ),
            'all_items'          => __( 'All flows', 'joinotify' ),
            'search_items'       => __( 'Search flows', 'joinotify' ),
            'parent_item_colon'  => __( 'Parent flow:', 'joinotify' ),
            'not_found'          => __( 'No flow found.', 'joinotify' ),
            'not_found_in_trash' => __( 'No flow found in trash.', 'joinotify' )
        );
    
        $args = array(
            'labels' => $labels,
            'description'        => __( 'WhatsApp automation workflows created with Joinotify.', 'joinotify' ),
            'public'             => true,
            'publicly_queryable' => true,
            'show_ui'            => true,
            'show_in_menu'       => false,
            'query_var'          => true,
            'capability_type'    => 'post',
            'capabilities'       => array(
                'edit_post'           => 'manage_options',
                'read_post'           => 'manage_options',
                'delete_post'         => 'manage_options',
                'edit_posts'          => 'manage_options',
                'edit_others_posts'   => 'manage_options',
                'publish_posts'       => 'manage_options',
                'read_private_posts'  => 'manage_options',
            ),
            'rewrite'            => array( 'slug' => '/workflows', 'with_front' => false ),
            'has_archive'        => true,
            'hierarchical'       => false,
            'menu_position'      => null,
            'supports'           => array( 'title', 'editor', 'author', 'thumbnail', 'custom-fields' )
        );
    
        register_post_type( 'joinotify-workflow', $args );

        // Flush rewrite rules once per plugin version instead of on every request.
        if ( get_option( 'joinotify_rewrite_rules_version' ) !== JOINOTIFY_VERSION ) {
            flush_rewrite_rules();
            update_option( 'joinotify_rewrite_rules_version', JOINOTIFY_VERSION );
        }
    }
}