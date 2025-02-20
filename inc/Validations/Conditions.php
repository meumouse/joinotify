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
     * @param string $trigger | Trigger item
     * @return array returns an array of conditions for the given action
     */
    public static function get_conditions_by_trigger( $trigger ) {
        $conditions = array();

        switch ( $trigger ) {
            case 'user_register':
                $conditions = array(
                    'user_role' => array(
                        'title' => __( 'Função do usuário', 'joinotify' ),
                        'description' => __( 'Permite verificar a função do usuário que disparou o acionamento.', 'joinotify' ),
                    ),
                    'user_meta' => array(
                        'title' => __( 'Metadados específicos do usuário', 'joinotify' ),
                        'description' => __( 'Permite verificar metadados específicos do usuário que disparou o acionamento.', 'joinotify' ),
                    ),
                );
                
                break;

            case 'wp_login':
                $conditions = array(
                    'user_last_login' => array(
                        'title' => __( 'Tempo desde o último login', 'joinotify' ),
                        'description' => __( 'Permite verificar o tempo desde o último login do usuário.', 'joinotify' ),
                    ),
                    'user_role' => array(
                        'title' => __( 'Função do usuário', 'joinotify' ),
                        'description' => __( 'Permite verificar a função do usuário que fez o login.', 'joinotify' ),
                    ),
                );

                break;

            case 'password_reset':
                $conditions = array(
                    'user_meta' => array(
                        'title' => __( 'Metadados específicos do usuário', 'joinotify' ),
                        'description' => __( 'Permite verificar metadados específicos do usuário que solicitou a redefinição de senha.', 'joinotify' ),
                    ),
                );

                break;

            case 'transition_post_status':
                $conditions = array(
                    'post_type' => array(
                        'title' => __( 'Tipo de post', 'joinotify' ),
                        'description' => __( 'Permite verificar o tipo de post que foi publicado.', 'joinotify' ),
                    ),
                    'post_author' => array(
                        'title' => __( 'Autor do post', 'joinotify' ),
                        'description' => __( 'Permite verificar o autor do post publicado.', 'joinotify' ),
                    ),
                );

                break;

            case 'woocommerce_new_order':
                $conditions = array(
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
                );

                break;

            case 'woocommerce_checkout_order_processed':
                $conditions = array(
                    'order_total' => array(
                        'title' => __( 'Valor total do pedido', 'joinotify' ),
                        'description' => __( 'Permite verificar o valor total do pedido em processamento.', 'joinotify' ),
                    ),
                    'products_purchased' => array(
                        'title' => __( 'Produtos adquiridos', 'joinotify' ),
                        'description' => __( 'Permite verificar os produtos adquiridos no pedido em processamento.', 'joinotify' ),
                    ),
                );

                break;

            case 'woocommerce_order_status_completed':
                $conditions = array(
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
                );

                break;

            case 'woocommerce_order_partially_refunded':
                $conditions = array(
                    'refund_amount' => array(
                        'title' => __( 'Valor do reembolso', 'joinotify' ),
                        'description' => __( 'Permite verificar o valor do reembolso do pedido.', 'joinotify' ),
                    ),
                    'products_purchased' => array(
                        'title' => __( 'Produtos adquiridos', 'joinotify' ),
                        'description' => __( 'Permite verificar os produtos adquiridos no pedido.', 'joinotify' ),
                    ),
                );

                break;

            case 'woocommerce_order_fully_refunded':
                $conditions = array(
                    'products_purchased' => array(
                        'title' => __( 'Produtos adquiridos', 'joinotify' ),
                        'description' => __( 'Permite verificar os produtos adquiridos no pedido.', 'joinotify' ),
                    ),
                );

                break;

            case 'woocommerce_checkout_subscription_created':
                $conditions = array(
                    'subscription_status' => array(
                        'title' => __( 'Status da assinatura', 'joinotify' ),
                        'description' => __( 'Permite verificar o status da assinatura criada.', 'joinotify' ),
                    ),
                    'products_purchased' => array(
                        'title' => __( 'Produtos adquiridos', 'joinotify' ),
                        'description' => __( 'Permite verificar os produtos adquiridos no pedido.', 'joinotify' ),
                    ),
                );

                break;

            case 'woocommerce_subscription_payment_complete':
                $conditions = array(
                    'products_purchased' => array(
                        'title' => __( 'Produtos adquiridos', 'joinotify' ),
                        'description' => __( 'Permite verificar os produtos adquiridos no pedido.', 'joinotify' ),
                    ),
                );

                break;

            case 'flexify_checkout_cart_abandoned':
                $conditions = array(
                    'cart_total' => array(
                        'title' => __( 'Valor total do carrinho', 'joinotify' ),
                        'description' => __( 'Permite verificar o valor total do carrinho abandonado.', 'joinotify' ),
                    ),
                    'items_in_cart' => array(
                        'title' => __( 'Itens no carrinho', 'joinotify' ),
                        'description' => __( 'Permite verificar os itens presentes no carrinho abandonado.', 'joinotify' ),
                    ),
                );

                break;

            case 'elementor_pro/forms/new_record':
                $conditions = array(
                    'form_id' => array(
                        'title' => __( 'ID do formulário', 'joinotify' ),
                        'description' => __( 'Permite verificar o ID do formulário enviado.', 'joinotify' ),
                    ),
                    'field_value' => array(
                        'title' => __( 'Valores específicos dos campos do formulário', 'joinotify' ),
                        'description' => __( 'Permite verificar valores específicos dos campos do formulário enviado.', 'joinotify' ),
                    ),
                );

                break;

            case 'wpforms_process_complete':
                $conditions = array(
                    'form_id' => array(
                        'title' => __( 'ID do formulário', 'joinotify' ),
                        'description' => __( 'Permite verificar o ID do formulário enviado.', 'joinotify' ),
                    ),
                    'field_value' => array(
                        'title' => __( 'Valores específicos dos campos do formulário', 'joinotify' ),
                        'description' => __( 'Permite verificar valores específicos dos campos do formulário enviado.', 'joinotify' ),
                    ),
                );

                break;

            default:
                $conditions = array(
                    'no_action' => array(
                        'title' => __( 'Nenhuma condição disponível para esta ação', 'joinotify' ),
                        'description' => __( 'Nenhuma condição foi definida para esta ação.', 'joinotify' ),
                    ),
                );

                break;
        }

        return apply_filters( 'Joinotify/Validations/Get_Action_Conditions', $conditions );
    }


    /**
     * Check condition type and return allowed conditions type for condition key
     * 
     * @since 1.0.0
     * @param string $condition_type | Condition key
     * @return array
     */
    public static function check_condition_type( $condition_type ) {
        $allowed_conditions = array();

        switch ( $condition_type ) {
            case 'user_role':
                $allowed_conditions = array(
                    'is',
                    'is_not',
                );

                break;
            case 'user_meta':
                $allowed_conditions = array(
                    'contains',
                    'not_contain',
                    'is',
                    'is_not',
                    'empty',
                    'not_empty',
                );

                break;
            case 'user_last_login':
                $allowed_conditions = array(
                    'bigger_then',
                    'less_than',
                    'is',
                    'is_not',
                );

                break;
            case 'post_type':
                $allowed_conditions = array(
                    'is',
                    'is_not',
                );

                break;
            case 'post_author':
                $allowed_conditions = array(
                    'is',
                    'is_not',
                );

                break;
            case 'order_status':
                $allowed_conditions = array(
                    'is',
                    'is_not',
                );

                break;
            case 'order_total':
                $allowed_conditions = array(
                    'bigger_then',
                    'less_than',
                );

                break;
            case 'products_purchased':
                $allowed_conditions = array(
                    'contains',
                    'not_contain',
                );

                break;
            case 'customer_email':
                $allowed_conditions = array(
                    'contains',
                    'not_contain',
                    'is',
                    'is_not',
                );

                break;
            case 'refund_amount':
                $allowed_conditions = array(
                    'bigger_then',
                    'less_than',
                );

                break;
            case 'subscription_status':
                $allowed_conditions = array(
                    'is',
                    'is_not',
                );

                break;
            case 'renewal_payment':
                $allowed_conditions = array(
                    'is',
                    'is_not',
                );

                break;
            case 'cart_total':
                $allowed_conditions = array(
                    'bigger_then',
                    'less_than',
                );
                
                break;
            case 'items_in_cart':
                $allowed_conditions = array(
                    'bigger_then',
                    'less_than',
                    'is',
                    'is_not',
                );
                
                break;
            case 'form_id':
                $allowed_conditions = array(
                    'is',
                    'is_not',
                );
                
                break;
            case 'field_value':
                $allowed_conditions = array(
                    'contains',
                    'not_contain',
                    'is',
                    'is_not',
                    'empty',
                    'not_empty',
                );
                
                break;
        }

        return $allowed_conditions;
    }


    /**
     * Gets comparison value based on condition type and context
     *
     * @since 1.0.0
     * @param string $condition_type | Condition type (e.g. 'order_total', 'user_role')
     * @param mixed $context | Context type (e.g., order object, post object, or custom data array)
     * @param int|null $post_id | Optional Post ID or Order ID to retrieve data if context is not provided
     * @return mixed Returns the value for comparison or null if not found
     */
    public static function get_compare_value( $condition_type, $context = null, $post_id = null ) {
        // Ensure context is provided or retrieve it using the post ID
        if ( is_null( $context ) && ! is_null( $post_id ) ) {
            $context = get_post( $post_id );

            if ( function_exists('wc_get_order') && $context && get_post_type( $post_id ) === 'shop_order' ) {
                $context = wc_get_order( $post_id );
            }
        }

        switch ( $condition_type ) {
            case 'user_role':
                return is_object( $context ) && isset( $context->roles ) ? $context->roles : null;

            case 'user_meta':
                return is_object( $context ) && isset( $context->ID ) ? get_user_meta( $context->ID, 'meta_key', true ) : null;

            case 'user_last_login':
                return is_object( $context ) && isset( $context->ID ) ? get_user_meta( $context->ID, 'last_login', true ) : null;

            case 'post_type':
                return is_object( $context ) ? get_post_type( $context ) : null;

            case 'post_author':
                return is_object( $context ) ? get_the_author_meta( 'ID', $context->post_author ) : null;

            case 'order_status':
                return $context instanceof \WC_Order ? $context->get_status() : null;

            case 'order_total':
                return $context instanceof \WC_Order ? $context->get_total() : null;

            case 'products_purchased':
                if ( $context instanceof \WC_Order ) {
                    $purchased_products = array();

                    foreach ( $context->get_items() as $item ) {
                        $purchased_products[] = $item->get_product_id();
                    }

                    return $purchased_products;
                }

                return null;

            case 'customer_email':
                return $context instanceof \WC_Order ? $context->get_billing_email() : null;

            case 'refund_amount':
                return $context instanceof \WC_Order ? $context->get_total_refunded() : null;

            case 'subscription_status':
                return $context instanceof \WC_Subscription ? $context->get_status() : null;

            case 'renewal_payment':
                return $context instanceof \WC_Subscription ? $context->get_total() : null;

            case 'cart_total':
                return $context instanceof \WC_Cart ? $context->get_cart_contents_total() : null;

            case 'items_in_cart':
                return $context instanceof \WC_Cart ? count( $context->get_cart() ) : null;

            case 'form_id':
                return is_array( $context ) && isset( $context['id'] ) ? $context['id'] : null;

            case 'field_value':
                return is_array( $context ) && isset( $context['fields'] ) ? $context['fields'] : null;

            case 'post_meta':
                return ! is_null( $post_id ) ? get_post_meta( $post_id, 'meta_key', true ) : null;

            default:
                return null;
        }
    }


    /**
     * Check condition
     * 
     * @since 1.0.0
     * @param string $condition | Condition type
     * @param mixed $value | Value for check
     * @param mixed $value_compare | Optional value for compare with $value
     * @return bool
     */
    public static function check_condition( $condition, $value, $value_compare = '' ) {
        $value = is_scalar( $value ) ? (string) $value : $value;
        
        // If $value_compare is an array, convert it to string or adapt the processing
        if ( is_array( $value_compare ) ) {
            switch ( $condition ) {
                case 'contains':
                    // Checks if $value is in any of the elements of $value_compare
                    return in_array( $value, $value_compare );
                case 'not_contain':
                    return ! in_array( $value, $value_compare );
            }
        } else {
            $value_compare = is_scalar( $value_compare ) ? (string) $value_compare : $value_compare;
        }

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
                return is_string( $value ) && is_string( $value_compare ) && strpos( $value, $value_compare ) !== false;

            case 'not_contain':
                return is_string( $value ) && is_string( $value_compare ) && strpos( $value, $value_compare ) === false;

            case 'start_with':
                if ( is_string( $value ) && is_string( $value_compare ) ) {
                    if ( version_compare( PHP_VERSION, '8.0.0' ) >= 0 ) {
                        return str_starts_with( $value, $value_compare );
                    } else {
                        return strpos( $value, $value_compare ) === 0;
                    }
                }
                return false;

            case 'finish_with':
                if ( is_string( $value ) && is_string( $value_compare ) ) {
                    if ( version_compare( PHP_VERSION, '8.0.0' ) >= 0 ) {
                        return str_ends_with( $value, $value_compare );
                    } else {
                        $length = strlen( $value_compare );
                        return substr( $value, -$length ) === $value_compare;
                    }
                }
                return false;

            case 'bigger_then':
                return is_numeric( $value ) && is_numeric( $value_compare ) && $value > $value_compare;

            case 'less_than':
                return is_numeric( $value ) && is_numeric( $value_compare ) && $value < $value_compare;

            case '':
                return false;

            case 'none':
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