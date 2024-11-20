<?php

namespace MeuMouse\Joinotify\Integrations;

use MeuMouse\Joinotify\Core\Admin;
use MeuMouse\Joinotify\Builder\Core;

// Exit if accessed directly.
defined('ABSPATH') || exit;

/**
 * Add integration with Flexify Checkout for WooCommerce plugin
 * 
 * @since 1.0.0
 * @package MeuMouse.com
 */
class Flexify_Checkout extends Integrations_Base {

    /**
     * Construct function
     * 
     * @since 1.0.0
     * @return void
     */
    public function __construct() {
        // check if Flexify Checkout is active
        if ( class_exists('\MeuMouse\Flexify_Checkout\Flexify_Checkout') ) {
            add_action( 'Joinotify/Builder/Triggers', array( $this, 'add_flexify_checkout_triggers' ), 40 );
            add_action( 'Joinotify/Builder/Triggers_Content', array( $this, 'add_flexify_checkout_tab_content' ) );
        //    add_filter( 'Joinotify/Builder/Placeholders_List', array( $this, 'flexify_checkout_placeholders' ), 10, 1 );
        }
    }


    /**
     * Add Flexify Checkout triggers on sidebar
     * 
     * @since 1.0.0
     * @return void
     */
    public function add_flexify_checkout_triggers() {
        if ( Admin::get_setting('enable_flexify_checkout_integration') === 'yes' && Admin::get_setting('enable_woocommerce_integration') === 'yes' ) : ?>
            <a href="#flexify_checkout" class="nav-tab">
                <svg class="joinotify-tab-icon" xmlns="http://www.w3.org/2000/svg" viewBox="0 0 945.76 891.08"><path d="M514,116.38c-234.22,0-424.08,189.87-424.08,424.07S279.74,964.53,514,964.53,938,774.67,938,540.45,748.17,116.38,514,116.38Zm171.38,426.1c-141.76.37-257.11,117.69-257.4,259.45H339.72c0-191.79,153.83-347.42,345.62-347.42Zm0-176.64c-141.76.19-266.84,69.9-346,176.13V410.6C431,328.12,551.92,277.5,685.34,277.5Z" transform="translate(-89.88 -73.45)"/><circle cx="779.75" cy="166.01" r="166.01" style="fill:#fff"/><path d="M785.1,285.69c-9.31-37.24-14-55.85-4.19-68.37s29-12.52,67.35-12.52h50.25c38.38,0,57.57,0,67.34,12.52s5.12,31.13-4.18,68.37c-5.93,23.68-8.89,35.52-17.72,42.42s-21,6.89-45.44,6.89H848.26c-24.41,0-36.62,0-45.45-6.89S791,309.37,785.1,285.69Z" transform="translate(-89.88 -73.45)" style="fill:none;stroke:#141d26;stroke-miterlimit:133.33332824707;stroke-width:15px"/><path d="M954.76,210.22,947.05,182c-3-10.9-4.45-16.35-7.5-20.45a27.08,27.08,0,0,0-11.91-9.09c-4.76-1.86-10.41-1.86-21.7-1.86M792,210.22l7.7-28.27c3-10.9,4.46-16.35,7.51-20.45a27.11,27.11,0,0,1,11.9-9.09c4.77-1.86,10.42-1.86,21.71-1.86" transform="translate(-89.88 -73.45)" style="fill:none;stroke:#141d26;stroke-miterlimit:133.33332824707;stroke-width:15px"/><path d="M840.83,150.55a10.85,10.85,0,0,1,10.85-10.85h43.41a10.85,10.85,0,1,1,0,21.7H851.68A10.85,10.85,0,0,1,840.83,150.55Z" transform="translate(-89.88 -73.45)" style="fill:none;stroke:#141d26;stroke-miterlimit:133.33332824707;stroke-width:15px"/><path d="M830,248.2v43.4" transform="translate(-89.88 -73.45)" style="fill:none;stroke:#141d26;stroke-linecap:round;stroke-linejoin:round;stroke-width:15px"/><path d="M916.79,248.2v43.4" transform="translate(-89.88 -73.45)" style="fill:none;stroke:#141d26;stroke-linecap:round;stroke-linejoin:round;stroke-width:15px"/><path d="M873.38,248.2v43.4" transform="translate(-89.88 -73.45)" style="fill:none;stroke:#141d26;stroke-linecap:round;stroke-linejoin:round;stroke-width:15px"/></svg>
                <?php esc_html_e( 'Flexify Checkout', 'joinotify' ) ?>
            </a>
        <?php endif;
    }


    /**
     * Add content tab
     * 
     * @since 1.0.0
     * @return void
     */
    public function add_flexify_checkout_tab_content() {
        if ( Admin::get_setting('enable_flexify_checkout_integration') === 'yes' && Admin::get_setting('enable_woocommerce_integration') === 'yes' ) : ?>
            <div id="flexify_checkout" class="nav-content triggers-group">
                <?php foreach ( Core::get_triggers_by_context('flexify_checkout') as $trigger ) : ?>
                    <div class="trigger-item <?php echo ( isset( $trigger['class'] ) ? $trigger['class'] : '' ) ?>" data-context="flexify_checkout" data-trigger="<?php echo esc_attr( $trigger['data_trigger'] ); ?>">
                        <h4 class="title"><?php echo $trigger['title']; ?></h4>
                        <span class="description"><?php echo $trigger['description']; ?></span>

                        <?php if ( isset( $trigger['class'] ) && $trigger['class'] === 'locked' ) : ?>
                            <span class="fs-sm mt-3"><?php esc_html_e( 'Este recurso será liberado em breve', 'joinotify' ) ?></span>
                        <?php endif; ?>
                    </div>
                <?php endforeach; ?>
            </div>
        <?php endif;    
    }


    /**
     * Add Flexify Checkout placeholders on workflow builder
     * 
     * @since 1.0.0
     * @param array $placeholders | Current placeholders
     * @return array
     */
    public function flexify_checkout_placeholders( $placeholders ) {
        $placeholders['{{ fc_pix_copia_cola }}'] = array(
            'callback' => function( $payment_data ) {
                return isset( $payment_data['inter_pix_payload'] ) ? $payment_data['inter_pix_payload'] : '';
            },
            'description' => esc_html__( 'Para recuperar o código Pix Copia e Cola da integração banco Inter com Flexify Checkout', 'joinotify' ),
        );
    
        $placeholders['{{ fc_pix_expiration_time }}'] = array(
            'callback' => function( $payment_data ) {
                return isset( $payment_data['inter_pix_expires_in'] ) ? $payment_data['inter_pix_expires_in'] : '';
            },
            'description' => esc_html__( 'Para recuperar o código Pix Copia e Cola da integração banco Inter com Flexify Checkout', 'joinotify' ),
        );

        return $placeholders;
    }
}