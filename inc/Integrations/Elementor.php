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
            add_action( 'Joinotify/Builder/Triggers', array( $this, 'add_elementor_triggers' ), 40 );
            add_action( 'Joinotify/Builder/Triggers_Content', array( $this, 'add_elementor_tab_content' ) );
            add_filter( 'Joinotify/Builder/Placeholders_List', array( $this, 'elementor_placeholders' ), 10, 1 );
        }
    }


    /**
     * Add Elementor triggers on sidebar
     * 
     * @since 1.0.0
     * @return void
     */
    public function add_elementor_triggers() {
        if ( Admin::get_setting('enable_elementor_integration') === 'yes' ) : ?>
            <a href="#elementor" class="nav-tab">
                <svg class="joinotify-tab-icon" viewBox="0 0 400 400" xmlns="http://www.w3.org/2000/svg"><g clip-path="url(#a)"><path d="M200 0C89.532 0 0 89.532 0 200c0 110.431 89.532 200 200 200s200-89.532 200-200C399.964 89.532 310.431 0 200 0Zm-49.991 283.306h-33.315V116.658h33.315v166.648Zm133.297 0h-99.982v-33.315h99.982v33.315Zm0-66.667h-99.982v-33.315h99.982v33.315Zm0-66.666h-99.982v-33.315h99.982v33.315Z"/></g><defs><clipPath><path fill="#fff" d="M0 0h400v400H0z"/></clipPath></defs></svg>
                <?php esc_html_e( 'Elementor', 'joinotify' ) ?>
            </a>
        <?php endif;
    }


    /**
     * Add content tab
     * 
     * @since 1.0.0
     * @version 1.1.0
     * @return void
     */
    public function add_elementor_tab_content() {
        if ( Admin::get_setting('enable_elementor_integration') === 'yes' ) : ?>
            <div id="elementor" class="nav-content triggers-group">
                <?php foreach ( Triggers::get_triggers_by_context('elementor') as $trigger ) : ?>
                    <div class="trigger-item <?php echo ( isset( $trigger['class'] ) ? $trigger['class'] : '' ) ?>" data-context="elementor" data-trigger="<?php echo esc_attr( $trigger['data_trigger'] ); ?>">
                        <h4 class="title"><?php echo $trigger['title']; ?></h4>
                        <span class="description"><?php echo $trigger['description']; ?></span>
                    </div>
                <?php endforeach; ?>
            </div>
        <?php endif;
    }


    /**
     * Add Elementor placeholders on workflow builder
     * 
     * @since 1.1.0
     * @version 1.1.0
     * @param array $placeholders | Current placeholders
     * @return array
     */
    public function elementor_placeholders( $placeholders ) {
        if ( isset( $context ) && $context['type'] === 'elementor' ) {
            $placeholders['elementor'] = array(
                '{{ {{ field_id=[FIELD_ID] }}' => array(
                    'triggers' => Triggers::get_triggers_by_context('elementor'),
                    'description' => esc_html__( 'Para recuperar a informação de um campo do formulário do Elementor. Substitua FIELD_ID pelo id respectivo.', 'joinotify' ),
                    'replacement' => array(),
                ),
            );

            return $placeholders;
        }
    }
}