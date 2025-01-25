<?php

namespace MeuMouse\Joinotify\Integrations;

use MeuMouse\Joinotify\Admin\Admin;
use MeuMouse\Joinotify\Builder\Triggers;

// Exit if accessed directly.
defined('ABSPATH') || exit;

/**
 * Add integration with Elementor
 * 
 * @since 1.0.0
 * @version 1.1.0
 * @package MeuMouse.com
 */
class Elementor extends Integrations_Base {

    /**
     * Construct function
     * 
     * @since 1.0.0
     * @version 1.1.0
     * @return void
     */
    public function __construct() {
        if ( defined('ELEMENTOR_PATH') ) {
            // add trigger tab
            add_action( 'Joinotify/Builder/Triggers', array( $this, 'add_triggers_tab' ), 40 );

            // add trigger content
            add_action( 'Joinotify/Builder/Triggers_Content', array( $this, 'add_triggers_content' ) );

            // add placeholders
            add_filter( 'Joinotify/Builder/Placeholders_List', array( $this, 'add_placeholders' ), 10, 1 );
        }
    }


    /**
     * Add Elementor triggers on sidebar
     * 
     * @since 1.0.0
     * @version 1.1.0
     * @return void
     */
    public function add_triggers_tab() {
        $integration_slug = 'elementor';
        $integration_name = esc_html__( 'Elementor', 'joinotify' );
        $icon_svg = '<svg class="joinotify-tab-icon" viewBox="0 0 400 400" xmlns="http://www.w3.org/2000/svg"><g clip-path="url(#a)"><path d="M200 0C89.532 0 0 89.532 0 200c0 110.431 89.532 200 200 200s200-89.532 200-200C399.964 89.532 310.431 0 200 0Zm-49.991 283.306h-33.315V116.658h33.315v166.648Zm133.297 0h-99.982v-33.315h99.982v33.315Zm0-66.667h-99.982v-33.315h99.982v33.315Zm0-66.666h-99.982v-33.315h99.982v33.315Z"/></g><defs><clipPath><path fill="#fff" d="M0 0h400v400H0z"/></clipPath></defs></svg>';

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
        $this->render_integration_trigger_content('elementor');
    }


    /**
     * Add Elementor placeholders on workflow builder
     * 
     * @since 1.1.0
     * @param array $placeholders | Current placeholders
     * @return array
     */
    public function add_placeholders( $placeholders ) {
        $placeholders['elementor'] = array(
            '{{ field_id=[FIELD_ID] }}' => array(
                'triggers' => Triggers::get_trigger_names('elementor'),
                'description' => esc_html__( 'Para recuperar a informação de um campo do formulário do Elementor. Substitua FIELD_ID pelo id respectivo.', 'joinotify' ),
                'replacement' => array(),
            ),
        );

        return $placeholders;
    }
}