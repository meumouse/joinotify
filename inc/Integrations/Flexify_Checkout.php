<?php

namespace MeuMouse\Joinotify\Integrations;

use MeuMouse\Joinotify\Admin\Admin;
use MeuMouse\Joinotify\Builder\Triggers;

// Exit if accessed directly.
defined('ABSPATH') || exit;

/**
 * Add integration with Flexify Checkout for WooCommerce plugin
 * 
 * @since 1.0.0
 * @version 1.1.0
 * @package MeuMouse.com
 */
class Flexify_Checkout extends Integrations_Base {

    /**
     * Construct function
     * 
     * @since 1.0.0
     * @version 1.1.0
     * @return void
     */
    public function __construct() {
        // check if Flexify Checkout is active
        if ( class_exists('\MeuMouse\Flexify_Checkout\Flexify_Checkout') ) {
            // add triggers
            add_filter( 'Joinotify/Builder/Get_All_Triggers', array( $this, 'add_triggers' ), 10, 1 );

            // add trigger tab
            add_action( 'Joinotify/Builder/Triggers', array( $this, 'add_triggers_tab' ), 30 );

            // add trigger content
            add_action( 'Joinotify/Builder/Triggers_Content', array( $this, 'add_triggers_content' ) );

            // add placeholders
            add_filter( 'Joinotify/Builder/Placeholders_List', array( $this, 'add_placeholders' ), 10, 1 );
        }
    }


    /**
     * Add Flexify Checkout triggers
     * 
     * @since 1.1.0
     * @param array $triggers | Current triggers
     * @return array
     */
    public function add_triggers( $triggers ) {
        $triggers['flexify_checkout'] = array(
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
        );

        return $triggers;
    }


    /**
     * Add Flexify Checkout triggers on sidebar
     * 
     * @since 1.0.0
     * @version 1.1.0
     * @return void
     */
    public function add_triggers_tab() {
        $integration_slug = 'flexify_checkout';
        $integration_name = esc_html__( 'Flexify Checkout', 'joinotify' );
        $icon_svg = '<svg class="joinotify-tab-icon" xmlns="http://www.w3.org/2000/svg" viewBox="0 0 945.76 891.08"><path d="M514,116.38c-234.22,0-424.08,189.87-424.08,424.07S279.74,964.53,514,964.53,938,774.67,938,540.45,748.17,116.38,514,116.38Zm171.38,426.1c-141.76.37-257.11,117.69-257.4,259.45H339.72c0-191.79,153.83-347.42,345.62-347.42Zm0-176.64c-141.76.19-266.84,69.9-346,176.13V410.6C431,328.12,551.92,277.5,685.34,277.5Z" transform="translate(-89.88 -73.45)"/><circle cx="779.75" cy="166.01" r="166.01" style="fill:#fff"/><path d="M785.1,285.69c-9.31-37.24-14-55.85-4.19-68.37s29-12.52,67.35-12.52h50.25c38.38,0,57.57,0,67.34,12.52s5.12,31.13-4.18,68.37c-5.93,23.68-8.89,35.52-17.72,42.42s-21,6.89-45.44,6.89H848.26c-24.41,0-36.62,0-45.45-6.89S791,309.37,785.1,285.69Z" transform="translate(-89.88 -73.45)" style="fill:none;stroke:#141d26;stroke-miterlimit:133.33332824707;stroke-width:15px"/><path d="M954.76,210.22,947.05,182c-3-10.9-4.45-16.35-7.5-20.45a27.08,27.08,0,0,0-11.91-9.09c-4.76-1.86-10.41-1.86-21.7-1.86M792,210.22l7.7-28.27c3-10.9,4.46-16.35,7.51-20.45a27.11,27.11,0,0,1,11.9-9.09c4.77-1.86,10.42-1.86,21.71-1.86" transform="translate(-89.88 -73.45)" style="fill:none;stroke:#141d26;stroke-miterlimit:133.33332824707;stroke-width:15px"/><path d="M840.83,150.55a10.85,10.85,0,0,1,10.85-10.85h43.41a10.85,10.85,0,1,1,0,21.7H851.68A10.85,10.85,0,0,1,840.83,150.55Z" transform="translate(-89.88 -73.45)" style="fill:none;stroke:#141d26;stroke-miterlimit:133.33332824707;stroke-width:15px"/><path d="M830,248.2v43.4" transform="translate(-89.88 -73.45)" style="fill:none;stroke:#141d26;stroke-linecap:round;stroke-linejoin:round;stroke-width:15px"/><path d="M916.79,248.2v43.4" transform="translate(-89.88 -73.45)" style="fill:none;stroke:#141d26;stroke-linecap:round;stroke-linejoin:round;stroke-width:15px"/><path d="M873.38,248.2v43.4" transform="translate(-89.88 -73.45)" style="fill:none;stroke:#141d26;stroke-linecap:round;stroke-linejoin:round;stroke-width:15px"/></svg>';

        $this->render_integration_trigger_tab( $integration_slug, $integration_name, $icon_svg );
    }


    /**
     * Add content tab
     * 
     * @since 1.0.0
     * @version 1.1.0
     * @return void
     */
    public function add_triggers_content() {
        $this->render_integration_trigger_content('flexify_checkout');
    }


    /**
     * Add Flexify Checkout placeholders on workflow builder
     * 
     * @since 1.0.0
     * @version 1.1.0
     * @param array $placeholders | Current placeholders
     * @return array
     */
    public function add_placeholders( $placeholders ) {
        if ( class_exists('Module_Inter_Bank') ) {
            $trigger_names = Triggers::get_trigger_names('flexify_checkout');

            $placeholders['flexify_checkout'] = array(
                '{{ fc_pix_copia_cola }}' => array(
                    'triggers' => $trigger_names,
                    'description' => esc_html__( 'Para recuperar o código Pix Copia e Cola do pedido. Através da integração Flexify Checkout - Inter addon', 'joinotify' ),
                    'replacement' => array(
                        'production' => '',
                        'sandbox' => '',
                    ),
                ),
                '{{ fc_pix_expiration_time }}' => array(
                    'triggers' => $trigger_names,
                    'description' => esc_html__( 'Para recuperar o tempo de expiração do Pix Copia e Cola. Através da integração Flexify Checkout - Inter addon', 'joinotify' ),
                    'replacement' => array(
                        'production' => '',
                        'sandbox' => '',
                    ),
                ),
            );
        }

        return $placeholders;
    }
}