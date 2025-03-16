<?php

namespace MeuMouse\Joinotify\Validations;

// Exit if accessed directly.
defined('ABSPATH') || exit;

/**
 * Conditions class
 * 
 * @since 1.0.0
 * @version 1.2.0
 * @package MeuMouse.com
 */
class Conditions {

    /**
     * Get conditions for specific trigger item on builder
     * 
     * @since 1.0.0
     * @version 1.2.0
     * @param string $trigger | Trigger item
     * @return array returns an array of conditions for the given action
     */
    public static function get_conditions_by_trigger( $trigger ) {
        // Centralized conditions map
        $conditions_map = apply_filters( 'Joinotify/Validations/Get_Action_Conditions', array());
    
        // Default condition when no action is found
        $conditions = $conditions_map[$trigger] ?? array(
            'no_action' => array(
                'title' => __( 'Nenhuma condição disponível para esta ação', 'joinotify' ),
                'description' => __( 'Nenhuma condição foi definida para esta ação.', 'joinotify' ),
            ),
        );
    
        return $conditions;
    }


    /**
     * Check condition type and return allowed conditions type for condition key
     * 
     * @since 1.0.0
     * @version 1.2.0
     * @param string $condition_type | Condition key
     * @return array
     */
    public static function check_condition_type( $condition_type ) {
        // Map condition types to their allowed comparison types
        $allowed_conditions_map = apply_filters( 'Joinotify/Conditions/Check_Condition_Type', array(
            'user_role'           => array( 'is', 'is_not' ),
            'user_meta'           => array( 'contains', 'not_contain', 'is', 'is_not', 'empty', 'not_empty' ),
            'user_last_login'     => array( 'bigger_than', 'less_than', 'is', 'is_not' ),
            'post_type'           => array( 'is', 'is_not' ),
            'post_author'         => array( 'is', 'is_not' ),
            'order_status'        => array( 'is', 'is_not' ),
            'order_total'         => array( 'bigger_than', 'less_than' ),
            'order_paid'          => array( 'is', 'is_not' ),
            'products_purchased'  => array( 'contains', 'not_contain' ),
            'payment_method'      => array( 'is', 'is_not' ),
            'shipping_method'     => array( 'is', 'is_not' ),
            'customer_email'      => array( 'contains', 'not_contain', 'is', 'is_not' ),
            'refund_amount'       => array( 'bigger_than', 'less_than' ),
            'subscription_status' => array( 'is', 'is_not' ),
            'cart_total'          => array( 'bigger_than', 'less_than' ),
            'items_in_cart'       => array( 'bigger_than', 'less_than', 'is', 'is_not' ),
            'field_value'         => array( 'contains', 'not_contain', 'is', 'is_not', 'empty', 'not_empty' ),
            'cart_recovered'      => array( 'is', 'is_not' ),
        ));
    
        return isset( $allowed_conditions_map[ $condition_type ] ) ? $allowed_conditions_map[ $condition_type ] : array();
    }


    /**
     * Gets comparison value based on condition type and context
     *
     * @since 1.0.0
     * @version 1.2.2
     * @param string $condition_type | Condition type (e.g. 'order_total', 'user_role')
     * @param array $payload | Payload data
     * @return mixed Returns the value for comparison or null if not found
     */
    public static function get_compare_value( $condition_type, $payload ) {
        $context = null;
        $field_value = null;
        $condition_content = $payload['condition_content'];
        $field_id = $condition_content['field_id'] ?? '';

        // Get object context based on condition type
        if ( isset( $payload['order_id'] ) ) {
            $context = wc_get_order( $payload['order_id'] );
        } elseif ( isset( $payload['user_id'] ) ) {
            $context = get_userdata( $payload['user_id'] );
        }

        // check integration
        if ( isset( $payload['integration'] ) ) {
            if ( $payload['integration'] === 'wpforms' ) {
                $field_value = isset( $payload['fields'][$field_id]['value'] ) ? $payload['fields'][$field_id]['value'] : null;
            } elseif ( $payload['integration'] === 'elementor' ) {
                $field_value = isset( $payload['fields'][$field_id] ) ? $payload['fields'][$field_id] : null;
            }
        }
    
        // Map condition types to their respective value retrieval methods
        $value_map = apply_filters( 'Joinotify/Conditions/Get_Compare_Value', array(
            'user_role'             => $context instanceof \WP_User ? ( $context->roles[0] ?? null ) : null,
            'user_meta'             => $context instanceof \WP_User ? get_user_meta( $payload['user_id'], $condition_content['meta_key'] ?? '', true ) : null,
            'user_last_login'       => $context instanceof \WP_User ? get_user_meta( $payload['user_id'], 'last_login', true ) : null,
            'post_type'             => is_object( $context ) ? get_post_type( $payload['post_type'] ?? '' ) : null,
            'post_author'           => is_object( $context ) && isset( $context->post_author ) ? get_the_author_meta( 'ID', $context->post_author ) : null,
            'order_status'          => $context instanceof \WC_Order ? $context->get_status() : null,
            'order_total'           => $context instanceof \WC_Order ? $context->get_total() : null,
            'order_paid'            => $context instanceof \WC_Order ? $context->is_paid() : null,
            'products_purchased'    => $context instanceof \WC_Order ? array_map( fn( $item ) => $item->get_product_id(), $context->get_items() ) : null,
            'customer_email'        => $context instanceof \WC_Order ? $context->get_billing_email() : null,
            'refund_amount'         => $context instanceof \WC_Order ? $context->get_total_refunded() : null,
            'subscription_status'   => $context instanceof \WC_Subscription ? $context->get_status() : null,
            'cart_total'            => $context instanceof \WC_Cart ? $context->get_cart_contents_total() : null,
            'items_in_cart'         => $context instanceof \WC_Cart ? count( $context->get_cart() ) : null,
            'payment_method'        => $context instanceof \WC_Order ? $context->get_payment_method() : null,
            'shipping_method'       => $context instanceof \WC_Order ? $context->get_shipping_method() : null,
            'field_value'           => $field_value,
            'cart_recovered'        => isset( $payload['cart_id'] ) ? get_post_meta( $payload['cart_id'], '_fcrc_purchased', true ) : null,
        ));
    
        return $value_map[$condition_type] ?? null;
    }


    /**
     * Check condition
     * 
     * @since 1.0.0
     * @version 1.2.0
     * @param string $condition | Condition type
     * @param mixed $value | Value for check
     * @param mixed $value_compare | Optional value for compare with $value
     * @return bool
     */
    public static function check_condition( $condition, $value, $value_compare = '' ) {
        switch ( $condition ) {
            case 'is':
                return $value === $value_compare;
    
            case 'is_not':
                return $value !== $value_compare;
    
            case 'empty':
                return empty( $value );
    
            case 'not_empty':
                return ! empty( $value );
    
            case 'contains':
                return is_string( $value ) && strpos( $value, (string) $value_compare ) !== false;
    
            case 'not_contain':
                return is_string( $value ) && strpos( $value, (string) $value_compare ) === false;
    
            case 'bigger_than':
                return floatval( $value ) > floatval( $value_compare );
    
            case 'less_than':
                return floatval( $value ) < floatval( $value_compare );
    
            default:
                return false;
        }
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
     * Get condition content from action ID
     * 
     * @since 1.0.0
     * @version 1.1.0
     * @param int $post_id | Post ID
     * @param string $action_id | Action ID
     * @return string|null
     */
    public static function get_condition_content( $post_id, $action_id ) {
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
                    if ( isset( $item['data']['action'] ) && $item['data']['action'] === 'condition' && isset( $item['data']['condition_content'] ) ) {
                        return $item['data'];
                    }
                }
            }
        }

        return null;
    }
}