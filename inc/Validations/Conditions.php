<?php

namespace MeuMouse\Joinotify\Validations;

// Exit if accessed directly.
defined('ABSPATH') || exit;

/**
 * Conditions class
 * 
 * @since 1.0.0
 * @version 1.1.0
 * @package MeuMouse.com
 */
class Conditions {

    /**
     * Get conditions for specific trigger item on builder
     * 
     * @since 1.0.0
     * @version 1.1.0
     * @param string $trigger | Trigger item
     * @return array returns an array of conditions for the given action
     */
    public static function get_conditions_by_trigger( $trigger ) {
        // Centralized conditions map
        $conditions_map = array(
            'user_register' => array(
                'user_role' => array(
                    'title' => __( 'Função do usuário', 'joinotify' ),
                    'description' => __( 'Permite verificar a função do usuário que disparou o acionamento.', 'joinotify' ),
                ),
                'user_meta' => array(
                    'title' => __( 'Metadados específicos do usuário', 'joinotify' ),
                    'description' => __( 'Permite verificar metadados específicos do usuário que disparou o acionamento.', 'joinotify' ),
                ),
            ),
            'wp_login' => array(
                'user_role' => array(
                    'title' => __( 'Função do usuário', 'joinotify' ),
                    'description' => __( 'Permite verificar a função do usuário que fez o login.', 'joinotify' ),
                ),
            ),
            'password_reset' => array(
                'user_meta' => array(
                    'title' => __( 'Metadados específicos do usuário', 'joinotify' ),
                    'description' => __( 'Permite verificar metadados específicos do usuário que solicitou a redefinição de senha.', 'joinotify' ),
                ),
            ),
            'transition_post_status' => array(
                'post_type' => array(
                    'title' => __( 'Tipo de post', 'joinotify' ),
                    'description' => __( 'Permite verificar o tipo de post que foi publicado.', 'joinotify' ),
                ),
            ),
            'woocommerce_new_order' => array(
                'order_status' => array(
                    'title' => __( 'Status do pedido', 'joinotify' ),
                    'description' => __( 'Permite verificar o status do pedido recebido.', 'joinotify' ),
                ),
                'order_total' => array(
                    'title' => __( 'Valor total do pedido', 'joinotify' ),
                    'description' => __( 'Permite verificar o valor total do pedido.', 'joinotify' ),
                ),
                'products_purchased' => array(
                    'title' => __( 'Produtos adquiridos', 'joinotify' ),
                    'description' => __( 'Permite verificar os produtos adquiridos no pedido.', 'joinotify' ),
                ),
            ),
            'woocommerce_checkout_order_processed' => array(
                'order_total' => array(
                    'title' => __( 'Valor total do pedido', 'joinotify' ),
                    'description' => __( 'Permite verificar o valor total do pedido em processamento.', 'joinotify' ),
                ),
                'products_purchased' => array(
                    'title' => __( 'Produtos adquiridos', 'joinotify' ),
                    'description' => __( 'Permite verificar os produtos adquiridos no pedido em processamento.', 'joinotify' ),
                ),
            ),
            'woocommerce_order_status_completed' => array(
                'customer_email' => array(
                    'title' => __( 'E-mail do cliente', 'joinotify' ),
                    'description' => __( 'Permite verificar o e-mail do cliente que realizou o pedido.', 'joinotify' ),
                ),
                'order_total' => array(
                    'title' => __( 'Valor total do pedido', 'joinotify' ),
                    'description' => __( 'Permite verificar o valor total do pedido concluído.', 'joinotify' ),
                ),
                'products_purchased' => array(
                    'title' => __( 'Produtos adquiridos', 'joinotify' ),
                    'description' => __( 'Permite verificar os produtos adquiridos no pedido.', 'joinotify' ),
                ),
            ),
            'woocommerce_order_status_changed' => array(
                'order_status' => array(
                    'title' => __( 'Status do pedido', 'joinotify' ),
                    'description' => __( 'Permite verificar o status do pedido recebido.', 'joinotify' ),
                ),
                'order_total' => array(
                    'title' => __( 'Valor total do pedido', 'joinotify' ),
                    'description' => __( 'Permite verificar o valor total do pedido concluído.', 'joinotify' ),
                ),
                'products_purchased' => array(
                    'title' => __( 'Produtos adquiridos', 'joinotify' ),
                    'description' => __( 'Permite verificar os produtos adquiridos no pedido.', 'joinotify' ),
                ),
            ),
            'woocommerce_order_partially_refunded' => array(
                'refund_amount' => array(
                    'title' => __( 'Valor do reembolso', 'joinotify' ),
                    'description' => __( 'Permite verificar o valor do reembolso do pedido.', 'joinotify' ),
                ),
                'products_purchased' => array(
                    'title' => __( 'Produtos adquiridos', 'joinotify' ),
                    'description' => __( 'Permite verificar os produtos adquiridos no pedido.', 'joinotify' ),
                ),
            ),
            'woocommerce_order_fully_refunded' => array(
                'products_purchased' => array(
                    'title' => __( 'Produtos adquiridos', 'joinotify' ),
                    'description' => __( 'Permite verificar os produtos adquiridos no pedido.', 'joinotify' ),
                ),
            ),
            'woocommerce_checkout_subscription_created' => array(
                'subscription_status' => array(
                    'title' => __( 'Status da assinatura', 'joinotify' ),
                    'description' => __( 'Permite verificar o status da assinatura criada.', 'joinotify' ),
                ),
                'products_purchased' => array(
                    'title' => __( 'Produtos adquiridos', 'joinotify' ),
                    'description' => __( 'Permite verificar os produtos adquiridos no pedido.', 'joinotify' ),
                ),
            ),
            'flexify_checkout_cart_abandoned' => array(
                'cart_total' => array(
                    'title' => __( 'Valor total do carrinho', 'joinotify' ),
                    'description' => __( 'Permite verificar o valor total do carrinho abandonado.', 'joinotify' ),
                ),
                'items_in_cart' => array(
                    'title' => __( 'Itens no carrinho', 'joinotify' ),
                    'description' => __( 'Permite verificar os itens presentes no carrinho abandonado.', 'joinotify' ),
                ),
            ),
            'elementor_pro/forms/new_record' => array(
                'field_value' => array(
                    'title' => __( 'Valores específicos dos campos do formulário', 'joinotify' ),
                    'description' => __( 'Permite verificar valores específicos dos campos do formulário enviado.', 'joinotify' ),
                ),
            ),
            'wpforms_process_complete' => array(
                'field_value' => array(
                    'title' => __( 'Valores específicos dos campos do formulário', 'joinotify' ),
                    'description' => __( 'Permite verificar valores específicos dos campos do formulário enviado.', 'joinotify' ),
                ),
            ),
        );
    
        // Default condition when no action is found
        $conditions = $conditions_map[$trigger] ?? array(
            'no_action' => array(
                'title' => __( 'Nenhuma condição disponível para esta ação', 'joinotify' ),
                'description' => __( 'Nenhuma condição foi definida para esta ação.', 'joinotify' ),
            ),
        );
    
        return apply_filters( 'Joinotify/Validations/Get_Action_Conditions', $conditions );
    }


    /**
     * Check condition type and return allowed conditions type for condition key
     * 
     * @since 1.0.0
     * @version 1.1.0
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
            'products_purchased'  => array( 'contains', 'not_contain' ),
            'customer_email'      => array( 'contains', 'not_contain', 'is', 'is_not' ),
            'refund_amount'       => array( 'bigger_than', 'less_than' ),
            'subscription_status' => array( 'is', 'is_not' ),
            'cart_total'          => array( 'bigger_than', 'less_than' ),
            'items_in_cart'       => array( 'bigger_than', 'less_than', 'is', 'is_not' ),
            'field_value'         => array( 'contains', 'not_contain', 'is', 'is_not', 'empty', 'not_empty' ),
        ));
    
        return isset( $allowed_conditions_map[ $condition_type ] ) ? $allowed_conditions_map[ $condition_type ] : array();
    }


    /**
     * Gets comparison value based on condition type and context
     *
     * @since 1.0.0
     * @version 1.1.0
     * @param string $condition_type | Condition type (e.g. 'order_total', 'user_role')
     * @param mixed $context | Context type (e.g., order object, post object, or custom data array)
     * @param int|null $post_id | Optional Post ID or Order ID to retrieve data if context is not provided
     * @return mixed Returns the value for comparison or null if not found
     */
    public static function get_compare_value( $condition_type, $context ) {
        // Ensure context is provided or retrieve it using the post ID
        if ( is_null( $context ) && ! is_null( $post_id ) ) {
            $context = get_post( $post_id );

            if ( function_exists('wc_get_order') && $context && get_post_type( $post_id ) === 'shop_order' ) {
                $context = wc_get_order( $post_id );
            }
        }

        $value_map = apply_filters( 'Joinotify/Conditions/Get_Compare_Value', array(
            'user_role'          => $context->roles ?? null,
            'user_meta'          => get_user_meta( $context->ID ?? null, 'meta_key', true ),
            'user_last_login'    => get_user_meta( $context->ID ?? null, 'last_login', true ),
            'post_type'          => get_post_type( $context ),
            'post_author'        => get_the_author_meta( 'ID', $context->post_author ?? null ),
            'order_status'       => $context instanceof \WC_Order ? $context->get_status() : null,
            'order_total'        => $context instanceof \WC_Order ? $context->get_total() : null,
            'products_purchased' => $context instanceof \WC_Order ? array_map( fn($item) => $item->get_product_id(), $context->get_items() ) : null,
            'customer_email'     => $context instanceof \WC_Order ? $context->get_billing_email() : null,
            'refund_amount'      => $context instanceof \WC_Order ? $context->get_total_refunded() : null,
            'subscription_status'=> $context instanceof \WC_Subscription ? $context->get_status() : null,
            'cart_total'         => $context instanceof \WC_Cart ? $context->get_cart_contents_total() : null,
            'items_in_cart'      => $context instanceof \WC_Cart ? count( $context->get_cart() ) : null,
            'field_value'        => $context['fields'] ?? null,
        ));

        return $value_map[$condition_type] ?? null;
    }


    /**
     * Check condition
     * 
     * @since 1.0.0
     * @version 1.1.0
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
                return is_numeric( $value ) && is_numeric( $value_compare ) && $value > $value_compare;
    
            case 'less_than':
                return is_numeric( $value ) && is_numeric( $value_compare ) && $value < $value_compare;
    
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
                        return $item['data']['condition_content'];
                    }
                }
            }
        }

        return null;
    }
}