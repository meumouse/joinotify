<?php

namespace MeuMouse\Joinotify\Core;

// Exit if accessed directly.
defined('ABSPATH') || exit;

if ( ! class_exists('WP_List_Table') ) {
    require_once ABSPATH . 'wp-admin/includes/class-wp-list-table.php';
}

use WP_List_Table;

/**
 * Workflows table class
 * 
 * @since 1.0.0
 * @version 1.4.0
 * @package MeuMouse.com
 */
class Workflows_Table extends WP_List_Table {

    /**
     * Construct function
     * 
     * @since 1.0.0
     * @return void
     */
    public function __construct() {
        parent::__construct( array(
            'singular' => __('Fluxo', 'joinotify'),
            'plural' => __('Fluxos', 'joinotify'),
            'ajax' => false,
        ));
    }


    /**
     * Display navigation tabs for different post statuses with post count
     * 
     * @since 1.0.0
     * @version 1.3.5
     * @return void
     */
    public function display_navigation_tabs() {
        global $wpdb;
    
        // Obtenha a contagem de posts por status
        $counts = wp_count_posts('joinotify-workflow');
    
        // Defina a guia padrão como "publish"
        $tab = isset( $_GET['post_status'] ) ? $_GET['post_status'] : 'publish'; ?>

        <ul class="subsubsub">
            <li><a href="<?php echo admin_url('admin.php?page=joinotify-workflows&post_status=publish'); ?>" class="<?php echo ($tab == 'publish') ? 'current' : ''; ?>">
                <?php _e('Ativos', 'joinotify'); ?>
                <span class="count">(<?php echo $counts->publish; ?>)</span>
            </a> | </li>
            
            <li><a href="<?php echo admin_url('admin.php?page=joinotify-workflows&post_status=draft'); ?>" class="<?php echo ($tab == 'draft') ? 'current' : ''; ?>">
                <?php _e('Inativos', 'joinotify'); ?>
                <span class="count">(<?php echo $counts->draft; ?>)</span>
            </a> | </li>
            
            <li><a href="<?php echo admin_url('admin.php?page=joinotify-workflows&post_status=trash'); ?>" class="<?php echo ($tab == 'trash') ? 'current' : ''; ?>">
                <?php _e('Lixeira', 'joinotify'); ?>
                <span class="count">(<?php echo $counts->trash; ?>)</span>
            </a></li>
        </ul>
        <?php
    }


    /**
     * Render the navigation tabs and the table
     * 
     * @since 1.0.0
     * @return void
     */
    public function display() {
        $this->display_navigation_tabs();
        parent::display();
    }


    /**
     * Set table columns
     * 
     * @since 1.0.0
     * @return array
     */
    public function get_columns() {
        $columns = array(
            'cb' => '<input type="checkbox" />',
            'name' => __('Nome', 'joinotify'),
            'created_at'=> __('Criado', 'joinotify'),
            'status' => __('Status', 'joinotify'),
        );

        return $columns;
    }


    /**
     * Add column with checkbox for bulk selection
     * 
     * @since 1.0.0
     * @param object $item | WP_Post object
     * @return string
     */
    public function column_cb( $item ) {
        return sprintf( '<input type="checkbox" name="joinotify_workflow[]" value="%s" />', $item->ID );
    }


    /**
     * Render column name with post title and status
     * 
     * @since 1.0.0
     * @param object $item | WP_Post object
     * @return string
     */
    public function column_name( $item ) {
        $post_status = isset( $_GET['post_status'] ) ? $_GET['post_status'] : 'publish';

        if ( $post_status === 'trash' ) {
            $actions = array(
                'delete_permanently' => sprintf(
                    '<a href="?page=joinotify-workflows&action=delete_permanently&id=%s">%s</a>',
                    $item->ID,
                    __('Excluir permanentemente', 'joinotify')
                ),
                'restore' => sprintf(
                    '<a href="?page=joinotify-workflows&action=restore&id=%s">%s</a>',
                    $item->ID,
                    __('Restaurar', 'joinotify')
                ),
            );
        } else {
            $actions = array(
                'edit' => sprintf(
                    '<a href="admin.php?page=joinotify-workflows-builder&id=%s">%s</a>',
                    $item->ID,
                    __('Editar', 'joinotify')
                ),
                'delete' => sprintf(
                    '<a href="?page=joinotify-workflows&action=delete&id=%s">%s</a>',
                    $item->ID,
                    __('Mover para lixeira', 'joinotify')
                ),
            );
        }
    
        // Adds "Draft" label next to title if post is in draft
        $status_display = '';

        if ( $item->post_status === 'draft' ) {
            $status_display = '<span class="post-state"> — ' . esc_html__( 'Rascunho', 'joinotify' ) . '</span>';
        }
    
        return sprintf(
            '%1$s %2$s %3$s',
            '<strong><a class="row-title" href="admin.php?page=joinotify-workflows-builder&id=' . $item->ID . '">' . esc_html( $item->post_title ) . '</a></strong>',
            $status_display,
            $this->row_actions( $actions )
        );
    }    


    /**
     * Render column created at
     * 
     * @since 1.0.0
     * @param object $item | WP_Post object
     * @return string
     */
    public function column_created_at( $item ) {
        return sprintf( '%s', date('d/m/Y - H:i:s', strtotime( $item->post_date ) ) );
    }


    /**
     * Render column status
     * 
     * @since 1.0.0
     * @param object $item | WP_Post object
     * @return string
     */
    public function column_status( $item ) {
        $checked = $item->post_status === 'publish' ? 'checked' : '';
    
        return sprintf( '<input type="checkbox" class="toggle-switch" data-id="%s" %s />', $item->ID, $checked );
    }


    /**
     * Set bulk actions
     * 
     * @since 1.0.0
     * @version 1.3.5
     * @return array
     */
    public function get_bulk_actions() {
        $post_status = isset( $_GET['post_status'] ) ? $_GET['post_status'] : 'publish';

        if ( $post_status === 'trash' ) {
            $actions = array(
                'delete_permanently' => __( 'Excluir permanentemente', 'joinotify' ),
                'restore' => __( 'Restaurar', 'joinotify' ),
            );
        } else {
            $actions = array(
                'trash' => __( 'Mover para lixeira', 'joinotify' ),
                'publish' => __( 'Marcar como ativo', 'joinotify' ),
                'draft' => __( 'Marcar como inativo', 'joinotify' ),
            );
        }
        
        return $actions;
    }


    /**
     * Bulk actions
     * 
     * @since 1.0.0
     * @return void
     */
    public function process_bulk_action() {
        if ( 'delete_permanently' === $this->current_action() ) {
            if ( isset( $_POST['joinotify_workflow'] ) ) {
                foreach ( $_POST['joinotify_workflow'] as $workflow_id ) {
                    wp_delete_post( $workflow_id, true );
                }
            }
        }

        if ( 'restore' === $this->current_action() ) {
            if ( isset( $_POST['joinotify_workflow'] ) ) {
                foreach ( $_POST['joinotify_workflow'] as $workflow_id ) {
                    wp_untrash_post( $workflow_id );
                }
            }
        }

        if ( 'publish' === $this->current_action() ) {
            if ( isset( $_POST['joinotify_workflow'] ) ) {
                foreach ( $_POST['joinotify_workflow'] as $workflow_id ) {
                    wp_update_post( array( 'ID' => $workflow_id, 'post_status' => 'publish' ) );
                }
            }
        }

        if ( 'draft' === $this->current_action() ) {
            if ( isset( $_POST['joinotify_workflow'] ) ) {
                foreach ( $_POST['joinotify_workflow'] as $workflow_id ) {
                    wp_update_post( array( 'ID' => $workflow_id, 'post_status' => 'draft' ) );
                }
            }
        }

        if ( 'trash' === $this->current_action() ) {
            if ( isset( $_POST['joinotify_workflow'] ) ) {
                foreach ( $_POST['joinotify_workflow'] as $workflow_id ) {
                    wp_trash_post( $workflow_id );
                }
            }
        }
    }    


    /**
     * Prepare items for display on the table
     * 
     * @since 1.0.0
     * @version 1.2.0
     * @return void
     */
    public function prepare_items() {
        $this->process_bulk_action();

        $per_page = 20;
        $current_page  = $this->get_pagenum();
        $post_status = isset( $_GET['post_status'] ) ? $_GET['post_status'] : 'publish';

        // Sets query arguments based on the selected tab
        $args = array(
            'post_type' => 'joinotify-workflow',
            'posts_per_page' => $per_page,
            'paged' => $current_page,
        );

        // Sets the status of posts based on the selected tab
        if ( $post_status != 'all' ) {
            $args['post_status'] = $post_status;
        } else {
            $args['post_status'] = array('publish', 'draft', 'trash');
        }

        $query = new \WP_Query( $args );
        $total_items = $query->found_posts;
        $this->items = $query->posts;
        $this->_column_headers = array( $this->get_columns(), array(), $this->get_sortable_columns() );

        $this->set_pagination_args( array(
            'total_items' => $total_items,
            'per_page' => $per_page,
            'total_pages' => ceil( $total_items / $per_page ),
        ));
    }
}