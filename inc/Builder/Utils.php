<?php

namespace MeuMouse\Joinotify\Builder;

// Exit if accessed directly.
defined('ABSPATH') || exit;

/**
 * This class manages the functions helpers and utils for builder core
 * 
 * @since 1.1.0
 * @package MeuMouse.com
 */
class Utils {

    /**
     * Get context from post
     * 
     * @since 1.1.0
     * @param int $post_id | Post ID
     * @return string
     */
    public static function get_context_from_post( $post_id ) {
        // check post type
        if ( get_post_type( $post_id ) !== 'joinotify-workflow' ) {
            return null;
        }

        // get workflow content
        $workflow_content = get_post_meta( $post_id, 'joinotify_workflow_content', true );

        if ( is_array( $workflow_content ) && ! empty( $workflow_content ) ) {
            // get first item from array
            $first_item = reset( $workflow_content );

            // check if first item is a 'trigger' and has 'context' data
            if ( isset( $first_item['type'] ) && $first_item['type'] === 'trigger' && isset( $first_item['data']['context'] ) ) {
                return $first_item['data']['context'];
            }
        }

        return null; // return null if none context finded
    }


    /**
     * Check if the workflow content contains a specified type (trigger or action)
     * 
     * @since 1.0.0
     * @version 1.1.0
     * @param int $post_id | Post ID
     * @param string $type | Type to check for in the workflow content ('trigger' or 'action')
     * @return bool
     */
    public static function check_workflow_content( $post_id, $type = '' ) {
        // Check if $type is provided
        if ( empty( $type ) || ! is_string( $type ) ) {
            return false;
        }

        // Check post type
        if ( get_post_type( $post_id ) !== 'joinotify-workflow' ) {
            return false;
        }

        // Retrieve the workflow content from the post meta
        $workflow_data = get_post_meta( $post_id, 'joinotify_workflow_content', true );

        // If the workflow data is empty or not an array, return false
        if ( empty( $workflow_data ) || ! is_array( $workflow_data ) ) {
            return false;
        }

        // Iterate over the workflow data to check for the specified type
        foreach ( $workflow_data as $item ) {
            if ( isset( $item['type'] ) && $item['type'] === $type ) {
                return true; // Type found in workflow content
            }
        }

        // No specified type found in the workflow content
        return false;
    }


    /**
     * Find condition data by ID
     * 
     * @since 1.0.0
     * @version 1.1.0
     * @param array $workflow_data | Full workflow data array
     * @param string $condition_id | Condition ID to find
     * @return array|null
     */
    public static function find_condition_by_id( $post_id, $condition_id ) {
        $workflow_data = get_post_meta( $post_id, 'joinotify_workflow_content', true );

        foreach ( $workflow_data as $data ) {
            if ( isset( $data['id'] ) && $data['id'] === $condition_id ) {
                return $data;
            }
        }

        return null;
    }


    /**
     * Get condition item from action ID
     * 
     * @since 1.0.0
     * @version 1.1.0
     * @param int $post_id | Post ID
     * @param string $action_id | Action ID
     * @return string|null
     */
    public static function get_condition_item( $post_id, $action_id ) {
        if ( get_post_type( $post_id ) === 'joinotify-workflow' ) {
            $workflow_data = get_post_meta( $post_id, 'joinotify_workflow_content', true );
            
            // Checks if the workflow_data array was provided correctly
            if ( ! is_array( $workflow_data ) ) {
                return null;
            }

            foreach ( $workflow_data as $item ) {
                // Checks if the item type is 'action' and the id matches the provided action_id
                if ( isset( $item['type'] ) && $item['type'] === 'action' && isset( $item['id'] ) && $item['id'] === $action_id ) {
                    // Checks if the item has a condition
                    if ( isset( $item['data']['action'] ) && $item['data']['action'] === 'condition' && isset( $item['data']['condition'] ) ) {
                        return $item['data']['condition'];
                    }
                }
            }
        }

        return null;
    }


    /**
     * Get template categories for choose template on builder
     * 
     * @since 1.0.0
     * @version 1.1.0
     * @return array
     */
    public static function get_template_categories() {
        return apply_filters( 'Joinotify/Builder/Get_Template_Categories', array(
            'wordpress' => esc_html__( 'WordPress', 'joinotify' ),
            'woocommerce' => esc_html__( 'WooCommerce', 'joinotify' ),
            'flexify_checkout' => esc_html__( 'Flexify Checkout', 'joinotify' ),
            'elementor' => esc_html__( 'Elementor', 'joinotify' ),
            'wpforms' => esc_html__( 'WPForms', 'joinotify' ),
        ));
    }


    /**
     * Helper function to find the action or trigger by ID
     * Searches recursively in the entire workflow array
     * 
     * @since 1.1.0
     * @param array $workflow_content | Workflow content
     * @param string $item_id | Trigger or action ID
     * @return array
     */
    public static function find_workflow_item_by_id( $workflow_content, $item_id ) {
        foreach ( $workflow_content as $item ) {
            if ( isset( $item['id'] ) && $item['id'] === $item_id ) {
                return $item;
            }

            // verifica se há children
            if ( isset( $item['children'] ) && is_array( $item['children'] ) ) {
                // caso a ação seja uma condição com 'action_true' e 'action_false'
                if ( isset( $item['data']['action'] ) && $item['data']['action'] === 'condition' ) {
                    if ( isset( $item['children']['action_true'] ) ) {
                        $found = self::find_workflow_item_by_id( $item['children']['action_true'], $item_id );
                        
                        if ( $found ) {
                            return $found;
                        }
                    }

                    if ( isset( $item['children']['action_false'] ) ) {
                        $found = self::find_workflow_item_by_id( $item['children']['action_false'], $item_id );
                        
                        if ( $found ) {
                            return $found;
                        }
                    }
                } else {
                    // caso normal, apenas um array de ações filhas
                    $found = self::find_workflow_item_by_id( $item['children'], $item_id );

                    if ( $found ) {
                        return $found;
                    }
                }
            }
        }

        return null;
    }
}