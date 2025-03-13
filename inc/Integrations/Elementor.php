<?php

namespace MeuMouse\Joinotify\Integrations;

use MeuMouse\Joinotify\Admin\Admin;
use MeuMouse\Joinotify\Builder\Triggers;
use MeuMouse\Joinotify\Core\Workflow_Processor;

// Exit if accessed directly.
defined('ABSPATH') || exit;

/**
 * Add integration with Elementor
 * 
 * @since 1.0.0
 * @version 1.2.2
 * @package MeuMouse.com
 */
class Elementor extends Integrations_Base {

    /**
     * Construct function
     * 
     * @since 1.0.0
     * @version 1.2.2
     * @return void
     */
    public function __construct() {
        if ( defined('ELEMENTOR_PATH') ) {
            // add triggers
            add_filter( 'Joinotify/Builder/Get_All_Triggers', array( $this, 'add_triggers' ), 10, 1 );

            // add trigger tab
            add_action( 'Joinotify/Builder/Triggers', array( $this, 'add_triggers_tab' ), 40 );

            // add trigger content
            add_action( 'Joinotify/Builder/Triggers_Content', array( $this, 'add_triggers_content' ) );

            // add placeholders
            add_filter( 'Joinotify/Builder/Placeholders_List', array( $this, 'add_placeholders' ), 10, 1 );

            // add conditions
            add_filter( 'Joinotify/Validations/Get_Action_Conditions', array( $this, 'add_conditions' ), 10, 1 );

            // fire hook if Elementor is active
            if ( Admin::get_setting('enable_elementor_integration') === 'yes' ) {
                // when a Elementor form receive a new record
                add_action( 'elementor_pro/forms/new_record', array( $this, 'process_workflow_elementor_form' ), 10, 2 );
            }
        }
    }


    /**
     * Add Elementor triggers
     * 
     * @since 1.1.0
     * @param array $triggers | Current triggers
     * @return array
     */
    public function add_triggers( $triggers ) {
        $triggers['elementor'] = array(
            array(
                'data_trigger' => 'elementor_pro/forms/new_record',
                'title' => esc_html__( 'Formulário é enviado', 'joinotify' ),
                'description' => esc_html__( 'Este acionamento é disparado quando um formulário do Elementor é enviado.', 'joinotify' ),
                'class' => '',
                'require_settings' => true,
            ),
        );

        return $triggers;
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


    /**
     * Add conditions for Elementor triggers
     * 
     * @since 1.2.0
     * @param array $conditions | Current conditions
     * @return array
     */
    public function add_conditions( $conditions ) {
        $elementor_conditions = array(
            'elementor_pro/forms/new_record' => array(
                'field_value' => array(
                    'title' => __( 'Valor de um campo do formulário', 'joinotify' ),
                    'description' => __( 'Permite verificar um valor específico de um campo do formulário enviado.', 'joinotify' ),
                ),
                'user_meta' => array(
                    'title' => __( 'Meta dados do usuário', 'joinotify' ),
                    'description' => __( 'Permite verificar metadados específicos do usuário que solicitou a redefinição de senha.', 'joinotify' ),
                ),
            ),
        );

        return array_merge( $conditions, $elementor_conditions );
    }


    /**
     * Process workflow content on Elementor form submission
     * 
     * @since 1.1.0
     * @version 1.2.2
     * @param object $record | The record submitted
     * @return void
     */
    public function process_workflow_elementor_form( $record, $handler ) {
        // get form records
        $form_settings = $record->get('form_settings');
        $form_id = isset( $form_settings['form_id'] ) ? $form_settings['form_id'] : '';

        // get form fields
        $raw_fields = $record->get('fields');
        $fields = array();

        foreach ( $raw_fields as $id => $field ) {
            $fields[$id] = $field['value'];
        }

        if ( JOINOTIFY_DEBUG_MODE ) {
            Logger::register_log( 'Function process_workflow_elementor_form() fired' );
        }
    
        $payload = array(
            'integration' => 'elementor',
            'hook' => 'elementor_pro/forms/new_record',
            'type' => 'trigger',
            'id' => $form_id,
            'fields' => $fields,
            'record' => $record,
            'handler' => $handler,
        );
    
        Workflow_Processor::process_workflows( $payload );
    }
}