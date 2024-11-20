<?php

namespace MeuMouse\Joinotify\Builder;

use MeuMouse\Joinotify\Validations\Conditions;
use MeuMouse\Joinotify\Integrations\Woocommerce;

// Exit if accessed directly.
defined('ABSPATH') || exit;

/**
 * This class manages the builder placeholders
 * 
 * @since 1.0.0
 * @package MeuMouse.com
 */
class Placeholders {

    /**
     * Get placeholders list based on trigger type for WhatsApp messages
     *
     * @since 1.0.0
     * @param string $trigger | Trigger type to filter placeholders
     * @return array Filtered list of placeholders based on the trigger
     */
    public static function get_placeholders_list( $trigger = '' ) {
        $placeholders = array(
            '{{ br }}' => array(
                'description' => esc_html__( 'Para quebrar uma linha na mensagem de texto', 'joinotify' ),
            ),
            '{{ first_name }}' => array(
                'description' => esc_html__( 'Para recuperar o primeiro nome do usuário', 'joinotify' ),
            ),
            '{{ last_name }}' => array(
                'description' => esc_html__( 'Para recuperar o sobrenome do usuário', 'joinotify' ),
            ),
            '{{ phone }}' => array(
                'description' => esc_html__( 'Para recuperar o telefone do usuário', 'joinotify' ),
            ),
            '{{ email }}' => array(
                'description' => esc_html__( 'Para recuperar o e-mail do usuário', 'joinotify' ),
            ),
            '{{ site_url }}' => array(
                'description' => esc_html__( 'Para recuperar a URL do site', 'joinotify' ),
            ),
            '{{ site_name }}' => array(
                'description' => esc_html__( 'Para recuperar o nome do site', 'joinotify' ),
            ),
            '{{ current_date }}' => array(
                'description' => esc_html__( 'Para recuperar a data atual', 'joinotify' ),
            ),
            '{{ wc_order_number }}' => array(
                'description' => esc_html__( 'Para recuperar o número do pedido do WooCommerce', 'joinotify' ),
            ),
            '{{ wc_order_status }}' => array(
                'description' => esc_html__( 'Para recuperar o status do pedido do WooCommerce', 'joinotify' ),
            ),
            '{{ wc_purchased_items }}' => array(
                'description' => esc_html__( 'Para recuperar os itens adquiridos no pedido', 'joinotify' ),
            ),
            '{{ wc_payment_url }}' => array(
                'description' => esc_html__( 'Para recuperar a URL de pagamento do pedido', 'joinotify' ),
            ),
        );

        // Universal placeholders available for all triggers
        $universal_placeholders = array(
            '{{ first_name }}',
            '{{ last_name }}',
            '{{ phone }}',
            '{{ email }}',
            '{{ br }}',
            '{{ site_url }}',
            '{{ site_name }}',
            '{{ current_date }}',
        );

        // Specific placeholders for each trigger
        $trigger_specific_placeholders = Conditions::get_conditional_placeholders();

        // Merge universal placeholders with trigger-specific placeholders
        $allowed_placeholders = $universal_placeholders;

        if ( ! empty( $trigger ) && is_string( $trigger ) && isset( $trigger_specific_placeholders[$trigger] ) ) {
            $allowed_placeholders = array_merge( $allowed_placeholders, $trigger_specific_placeholders[$trigger] );
        }

        // Filter placeholders based on allowed ones
        $filtered_placeholders = array_filter( $placeholders, function( $key ) use ( $allowed_placeholders ) {
            return in_array( $key, $allowed_placeholders, true );
        }, ARRAY_FILTER_USE_KEY );

        return apply_filters( 'Joinotify/Builder/Placeholders_List', $filtered_placeholders, $trigger );
    }


    /**
     * Replace placeholders with actual values in the message
     *
     * @since 1.0.0
     * @param string $message | The message containing placeholders
     * @param int $order_id | Order ID
     * @param string $mode | Mode ('sandbox' or 'production')
     * @return string The message with placeholders replaced
     */
    public static function replace_placeholders( $message, $order_id, $mode = 'production' ) {
        // Obter placeholders e substituições
        $placeholders = array_keys( self::get_placeholders_list() );
        $replacements = $mode === 'production' ? self::get_production_data( $order_id ) : self::get_sandbox_data( $message );

        // Substituir placeholders na mensagem
        foreach ( $placeholders as $placeholder ) {
            $replacement = $replacements[$placeholder] ?? $placeholder;
            $message = str_replace( $placeholder, $replacement, $message );
    
            if ( JOINOTIFY_DEBUG_MODE && $replacement === $placeholder ) {
                error_log( "Placeholder not found or not replaced: $placeholder" );
            }
        }
    
        return $message;
    }


    /**
     * Retrieve actual user or order data in production mode
     *
     * @since 1.0.0
     * @param int $order_id | Order ID
     * @return array Data for placeholder replacement
     */
    public static function get_production_data( $order_id ) {
        $order = wc_get_order( $order_id );

        if ( $order instanceof \WC_Order ) {
            $order_placeholders = apply_filters( 'Joinotify/Placeholders/Set_Replacement_Data', array(
                '{{ br }}' => "\n",
                '{{ first_name }}' => $order->get_billing_first_name(),
                '{{ last_name }}' => $order->get_billing_last_name(),
                '{{ phone }}' => $order->get_billing_phone(),
                '{{ email }}' => $order->get_billing_email(),
                '{{ wc_order_number }}' => $order->get_order_number(),
                '{{ wc_order_date }}' => date_i18n( get_option('date_format'), strtotime( $order->get_date_created() ) ),
                '{{ wc_full_address }}' => Woocommerce::get_full_address( $order ),
                '{{ wc_order_status }}' => wc_get_order_status_name( $order->get_status() ),
                '{{ wc_purchased_items }}' => Woocommerce::get_purchased_items( $order ),
                '{{ wc_payment_url }}' => $order->get_checkout_payment_url(),
                '{{ wc_currency_symbol }}' => get_woocommerce_currency_symbol( $order->get_currency() ),
                '{{ current_date }}' => date( get_option('date_format') ),
                '{{ site_url }}' => get_site_url(),
                '{{ site_name }}' => get_bloginfo('name'),
            ));
        }

        if ( JOINOTIFY_DEBUG_MODE ) {
            error_log( 'get_production_data(): ' . print_r( $order_placeholders, true ) );
        }

        return $order_placeholders;
    }


    /**
     * Retrieve placeholder values for sandbox mode
     *
     * @since 1.0.0
     * @param string $message The message containing placeholders
     * @return array Sample data for placeholders
     */
    public static function get_sandbox_data( $message ) {
        $current_user = wp_get_current_user();

        $real_user_data = array(
            '{{ br }}' => "\n",
            '{{ first_name }}' => $current_user->exists() ? $current_user->first_name : __( 'João', 'joinotify' ),
            '{{ last_name }}' => $current_user->exists() ? $current_user->last_name : __( 'da Silva', 'joinotify' ),
            '{{ phone }}' => $current_user->exists() ? get_user_meta( $current_user->ID, 'billing_phone', true ) : '+55 11 91234-5678',
            '{{ email }}' => $current_user->exists() ? $current_user->user_email : 'usuario@exemplo.com',
        );

        $sample_data = array(
            '{{ wc_order_number }}' => '12345',
            '{{ post_id }}' => '12345',
            '{{ wc_order_status }}' => __( 'Concluído', 'joinotify' ),
            '{{ wc_purchased_items }}' => sprintf( '%s %s %s', __( '1x - Camiseta de algodão masculina (Produto exemplo)', 'joinotify' ), "\n",  __( '1x - Óculos de sol com proteção UV (Produto exemplo)', 'joinotify' ) ),
            '{{ wc_payment_url }}' => sprintf( __( '%s/checkout/pay/order/12345', 'joinotify' ), get_site_url() ),
            '{{ wc_full_address }}' => __( 'Rua das Flores, 123 - Curitiba/PR - Brasil (CEP: 80000-000)', 'joinotify' ),
            '{{ wc_order_date }}' => date( get_option('date_format') ),
            '{{ current_date }}' => date( get_option('date_format') ),
            '{{ site_url }}' => get_site_url(),
            '{{ site_name }}' => get_bloginfo('name'),
        );

        return array_merge( $sample_data, $real_user_data );
    }
}