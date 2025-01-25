<?php

namespace MeuMouse\Joinotify\Builder;

use MeuMouse\Joinotify\Core\Logger;

// Exit if accessed directly.
defined('ABSPATH') || exit;

/**
 * This class handles with triggers functions
 * 
 * @since 1.1.0
 * @package MeuMouse.com
 */
class Triggers {

    /**
     * Get all triggers
     * 
     * This function returns all triggers from different contexts (woocommerce, wpforms, etc.).
     * Triggers are filtered by 'apply_filters' to allow dynamic extensions.
     * 
     * @since 1.0.0
     * @version 1.1.0
     * @return array
     */
    public static function get_all_triggers() {
        return apply_filters( 'Joinotify/Builder/Get_All_Triggers', array(
            'woocommerce' => array(
                array(
                    'data_trigger' => 'woocommerce_new_order',
                    'title' => esc_html__( 'Novo pedido', 'joinotify' ),
                    'description' => esc_html__( 'Este acionamento é disparado quando um novo pedido é recebido no WooCommerce com qualquer status.', 'joinotify' ),
                    'require_settings' => false,
                ),
                array(
                    'data_trigger' => 'woocommerce_checkout_order_processed',
                    'title' => esc_html__( 'Novo pedido (Processando)', 'joinotify' ),
                    'description' => esc_html__( 'Este acionamento é disparado quando um novo pedido é recebido no WooCommerce com status processando.', 'joinotify' ),
                    'require_settings' => false,
                ),
                array(
                    'data_trigger' => 'woocommerce_order_status_completed',
                    'title' => esc_html__( 'Pedido concluído', 'joinotify' ),
                    'description' => esc_html__( 'Este acionamento é disparado quando um pedido tem o status alterado para concluído.', 'joinotify' ),
                    'require_settings' => false,
                ),
                array(
                    'data_trigger' => 'woocommerce_order_fully_refunded',
                    'title' => esc_html__( 'Pedido totalmente reembolsado', 'joinotify' ),
                    'description' => esc_html__( 'Este acionamento é disparado quando um pedido é totalmente reembolsado.', 'joinotify' ),
                    'require_settings' => false,
                ),
                array(
                    'data_trigger' => 'woocommerce_order_partially_refunded',
                    'title' => esc_html__( 'Pedido parcialmente reembolsado', 'joinotify' ),
                    'description' => esc_html__( 'Este acionamento é disparado quando um pedido é parcialmente reembolsado.', 'joinotify' ),
                    'require_settings' => false,
                ),
                array(
                    'data_trigger' => 'woocommerce_order_status_changed',
                    'title' => esc_html__( 'Status de um pedido alterado', 'joinotify' ),
                    'description' => esc_html__( 'Este acionamento é disparado quando um pedido tem seu status alterado.', 'joinotify' ),
                    'require_settings' => false,
                ),
            ),
            'wpforms' => array(
                array(
                    'data_trigger' => 'wpforms_process_complete',
                    'title' => esc_html__( 'Formulário é enviado', 'joinotify' ),
                    'description' => esc_html__( 'Este acionamento é disparado quando um formulário do WPForms é enviado.', 'joinotify' ),
                    'class' => 'locked',
                    'require_settings' => true,
                ),
                array(
                    'data_trigger' => 'wpforms_paypal_standard_process_complete',
                    'title' => esc_html__( 'Pagamento processado pelo PayPal', 'joinotify' ),
                    'description' => esc_html__( 'Este acionamento é disparado quando um formulário de pagamento do WPForms é processado usando PayPal.', 'joinotify' ),
                    'class' => 'locked',
                    'require_settings' => true,
                ),
            ),
            'elementor' => array(
                array(
                    'data_trigger' => 'elementor_pro/forms/new_record',
                    'title' => esc_html__( 'Formulário é enviado', 'joinotify' ),
                    'description' => esc_html__( 'Este acionamento é disparado quando um formulário do Elementor é enviado.', 'joinotify' ),
                    'class' => '',
                    'require_settings' => true,
                ),
            ),
            'flexify_checkout' => array(
                array(
                    'data_trigger' => 'flexify_checkout_cart_abandonment',
                    'title' => esc_html__( 'Abandono do carrinho', 'joinotify' ),
                    'description' => esc_html__( 'Este acionamento é disparado quando o usuário abandona o carrinho.', 'joinotify' ),
                    'class' => 'locked',
                    'require_settings' => false,
                ),
                array(
                    'data_trigger' => 'flexify_checkout_entry_step_1',
                    'title' => esc_html__( 'Ao entrar na etapa 1', 'joinotify' ),
                    'description' => esc_html__( 'Este acionamento é disparado quando o usuário entra na etapa 1 da finalização de compras.', 'joinotify' ),
                    'class' => 'locked',
                    'require_settings' => false,
                ),
                array(
                    'data_trigger' => 'flexify_checkout_entry_step_2',
                    'title' => esc_html__( 'Ao entrar na etapa 2', 'joinotify' ),
                    'description' => esc_html__( 'Este acionamento é disparado quando o usuário entra na etapa 2 da finalização de compras.', 'joinotify' ),
                    'class' => 'locked',
                    'require_settings' => false,
                ),
                array(
                    'data_trigger' => 'flexify_checkout_entry_step_3',
                    'title' => esc_html__( 'Ao entrar na etapa 3', 'joinotify' ),
                    'description' => esc_html__( 'Este acionamento é disparado quando o usuário entra na etapa 3 da finalização de compras.', 'joinotify' ),
                    'class' => 'locked',
                    'require_settings' => false,
                ),
            ),
            'wordpress' => array(
                array(
                    'data_trigger' => 'user_register',
                    'title' => esc_html__( 'Novo registro de usuário', 'joinotify' ),
                    'description' => esc_html__( 'Este acionamento é disparado quando um novo registro de usuário é recebido.', 'joinotify' ),
                    'class' => 'locked',
                    'require_settings' => false,
                ),
                array(
                    'data_trigger' => 'wp_login',
                    'title' => esc_html__( 'Login do usuário', 'joinotify' ),
                    'description' => esc_html__( 'Este acionamento é disparado quando um usuário fizer login no site.', 'joinotify' ),
                    'class' => 'locked',
                    'require_settings' => false,
                ),
                array(
                    'data_trigger' => 'password_reset',
                    'title' => esc_html__( 'Recuperação de senha do usuário', 'joinotify' ),
                    'description' => esc_html__( 'Este acionamento é disparado quando um usuário solicitar recuperação de senha no site.', 'joinotify' ),
                    'class' => 'locked',
                    'require_settings' => false,
                ),
                array(
                    'data_trigger' => 'transition_post_status',
                    'title' => esc_html__( 'Novo post é publicado', 'joinotify' ),
                    'description' => esc_html__( 'Este acionamento é disparado quando um novo post é publicado no site.', 'joinotify' ),
                    'class' => 'locked',
                    'require_settings' => false,
                ),
            ),
        ));
    }


    /**
     * Get triggers by context
     * 
     * @since 1.0.0
     * @version 1.1.0
     * @param string $context | Nome do contexto (woocommerce, elementor, etc.)
     * @return array | return triggers array from context
     */
    public static function get_triggers_by_context( $context ) {
        $all_triggers = self::get_all_triggers();

        // check if has context on triggers array
        if ( isset( $all_triggers[$context] ) ) {
            return $all_triggers[$context];
        }

        // if context not found, return empty array
        return array();
    }


    /**
     * Get specific trigger based on context and data_trigger
     * 
     * @since 1.0.0
     * @version 1.1.0
     * @param string $context | Context name (woocommerce, elementor, etc.)
     * @param string $data_trigger | Trigger name (ex: 'order_completed')
     * @return array|null | return the trigger or null if not found
     */
    public static function get_trigger( $context, $data_trigger ) {
        $triggers = self::get_triggers_by_context( $context );

        foreach ( $triggers as $trigger ) {
            if ( $trigger['data_trigger'] === $data_trigger ) {
                return $trigger;
            }
        }

        // If trigger is not found, return null
        return null;
    }


    /**
     * Get trigger from a specific post ID
     * 
     * @since 1.0.0
     * @version 1.1.0
     * @param int $post_id | Post ID
     * @return array|null | Trigger data if found, or null if not
     */
    public static function get_trigger_from_post( $post_id ) {
        if ( get_post_type( $post_id ) === 'joinotify-workflow' ) {
            $workflow_content = get_post_meta( $post_id, 'joinotify_workflow_content', true );

            if ( is_array( $workflow_content ) ) {
                foreach ( $workflow_content as $item ) {
                    if ( isset( $item['type'] ) && $item['type'] === 'trigger' ) {
                        return $item['data']['trigger']; // Returns the found trigger
                    }
                }
            }
        }

        return null; // Returns null if no trigger is found.
    }


    /**
     * Get triggers names by context
     * 
     * @since 1.1.0
     * @param string $integration | Integration context
     * @return array
     */
    public static function get_trigger_names( $integration ) {
        $get_array = self::get_triggers_by_context( $integration );

        return array_column( $get_array, 'data_trigger' );
    }
}