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
     * @return void
     */
    public function __construct() {
        // register new post type
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
            'description'        => __( 'DescriÃ§Ã£o.', 'joinotify' ),
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

        // update permalinks
        flush_rewrite_rules();
    }
}