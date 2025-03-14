<?php

namespace MeuMouse\Joinotify\Integrations;

use MeuMouse\Joinotify\Admin\Admin;
use MeuMouse\Joinotify\Builder\Triggers;
use MeuMouse\Joinotify\Core\Workflow_Processor;

// Exit if accessed directly.
defined('ABSPATH') || exit;

/**
 * Add integration with WPForms plugin
 * 
 * @since 1.0.0
 * @version 1.2.2
 * @package MeuMouse.com
 */
class Wpforms extends Integrations_Base {

    /**
     * Construct function
     * 
     * @since 1.0.0
     * @version 1.2.2
     * @return void
     */
    public function __construct() {
        // check if WPForms is active
        if ( function_exists('wpforms') ) {
            // add triggers
            add_filter( 'Joinotify/Builder/Get_All_Triggers', array( $this, 'add_triggers' ), 10, 1 );

            // add trigger tab
            add_action( 'Joinotify/Builder/Triggers', array( $this, 'add_triggers_tab' ), 50 );

            // add trigger content
            add_action( 'Joinotify/Builder/Triggers_Content', array( $this, 'add_triggers_content' ) );

            // add placeholders
            add_filter( 'Joinotify/Builder/Placeholders_List', array( $this, 'add_placeholders' ), 10, 1 );

            // add conditions
            add_filter( 'Joinotify/Validations/Get_Action_Conditions', array( $this, 'add_conditions' ), 10, 1 );

            // fire hooks if WPForms is active
            if ( Admin::get_setting('enable_wpforms_integration') === 'yes' ) {
                // when a WPForms form receive a new record
                add_action( 'wpforms_process_complete', array( $this, 'process_workflow_wpforms_form' ), 10, 4 );

                // when a WPForms form paypal payment is fired
                add_action( 'wpforms_paypal_standard_process_complete', array( $this, 'process_workflow_wpforms_paypal' ), 10, 4 );
            }
        }
    }


    /**
     * Add WPForms triggers
     * 
     * @since 1.1.0
     * @version 1.2.0
     * @param array $triggers | Current triggers
     * @return array
     */
    public function add_triggers( $triggers ) {
        $triggers['wpforms'] = array(
            array(
                'data_trigger' => 'wpforms_process_complete',
                'title' => esc_html__( 'Formulário é enviado', 'joinotify' ),
                'description' => esc_html__( 'Este acionamento é disparado quando um formulário do WPForms é enviado.', 'joinotify' ),
                'require_settings' => true,
            ),
            array(
                'data_trigger' => 'wpforms_paypal_standard_process_complete',
                'title' => esc_html__( 'Pagamento processado pelo PayPal', 'joinotify' ),
                'description' => esc_html__( 'Este acionamento é disparado quando um formulário de pagamento do WPForms é processado usando PayPal.', 'joinotify' ),
                'require_settings' => true,
            ),
        );

        return $triggers;
    }


    /**
     * Add WPForms triggers on sidebar
     * 
     * @since 1.0.0
     * @version 1.1.0
     * @return void
     */
    public function add_triggers_tab() {
        $integration_slug = 'wpforms';
        $integration_name = esc_html__( 'WPForms', 'joinotify' );
        $icon_svg = '<svg class="joinotify-tab-icon" fill="#000000" viewBox="0 0 14 14" role="img" focusable="false" aria-hidden="true" xmlns="http://www.w3.org/2000/svg"><g stroke-width="0"></g><g stroke-linecap="round" stroke-linejoin="round"></g><g><path d="m 13,2.15688 0,9.68624 C 13,12.49386 12.491071,13 11.842857,13 L 2.1571429,13 C 1.5169643,12.99732 1,12.49922 1,11.84044 L 1,2.15688 C 1,1.51149 1.5035714,1 2.1571429,1 L 11.845536,1 C 12.488393,1 13,1.50346 13,2.15688 Z m -0.999107,9.68356 0,-9.68356 c 0,-0.0803 -0.06964,-0.15532 -0.155357,-0.15532 l -0.249107,0 L 8.6419643,3.99933 7,2.66302 5.3607143,3.99933 2.40625,1.99888 l -0.2491071,0 c -0.085714,0 -0.1553572,0.075 -0.1553572,0.15533 l 0,9.68623 c 0,0.0803 0.069643,0.15532 0.1553572,0.15532 l 9.6883931,0 c 0.08571,0.003 0.155357,-0.0723 0.155357,-0.15532 z m -6.9776787,-6.71636 0,0.99085 -1.96875,0 0,-0.99085 1.96875,0 z m 0,1.99241 0,0.99889 -1.96875,0 0,-0.99889 1.96875,0 z m 0.2973214,-3.94465 1.4464286,-1.17028 -3.1741072,0 1.7276786,1.17028 z m 5.6250003,1.95224 0,0.99085 -5.2500003,0 0,-0.99085 5.2500003,0 z m 0,1.99241 0,0.99889 -5.2500003,0 0,-0.99889 5.2500003,0 z M 8.6794643,3.17184 10.407143,2.00156 l -3.1714287,0 1.44375,1.17028 z m 2.2660717,5.94242 0,0.99888 -2.6625003,0 0,-0.99888 2.6625003,0 z"></path></g></svg>';

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
        $this->render_integration_trigger_content('wpforms');
    }


    /**
     * Add WPForms placeholders on workflow builder
     * 
     * @since 1.1.0
     * @param array $placeholders | Current placeholders
     * @return array
     */
    public function add_placeholders( $placeholders ) {
        $placeholders['wpforms'] = array(
            '{{ field_id=[FIELD_ID] }}' => array(
                'triggers' => Triggers::get_trigger_names('wpforms'),
                'description' => esc_html__( 'Para recuperar a informação de um campo do formulário do WPForms. Substitua FIELD_ID pelo id respectivo.', 'joinotify' ),
                'replacement' => array(), // dynamic replacement is make on Placeholders::replace_placeholders()
            ),
        );

        return $placeholders;
    }


    /**
     * Get all WPForms forms
     * 
     * @since 1.0.0
     * @return array
     */
    public static function get_forms() {
        // Initializes an empty array to store the forms
        $forms_list = array();

        $forms = wpforms()->form->get(
            '',  // Pass an empty string to fetch all forms
            array(
                'number' => -1,  // get all forms
                'orderby' => 'ID',  // Sort forms by ID (optional)
                'order' => 'ASC',  // Ascending order (optional)
            )
        );
    
        // Check if forms exist
        if ( ! empty( $forms ) ) {
            foreach ( $forms as $form ) {
                // Add each form to the forms array
                $forms_list[] = array(
                    'ID' => $form->ID,
                    'title' => $form->post_title,
                    'content' => $form->post_content,
                );
            }
        }
    
        return $forms_list;
    }


    /**
     * Add conditions for WPForms triggers
     * 
     * @since 1.2.0
     * @param array $conditions | Current conditions
     * @return array
     */
    public function add_conditions( $conditions ) {
        // Define reusable conditions
        $common_conditions = array(
            'field_value' => array(
                'title' => __( 'Valor de um campo do formulário', 'joinotify' ),
                'description' => __( 'Permite verificar um valor específico de um campo do formulário enviado.', 'joinotify' ),
            ),
            'user_meta' => array(
                'title' => __( 'Meta dados do usuário', 'joinotify' ),
                'description' => __( 'Permite verificar metadados específicos do usuário.', 'joinotify' ),
            ),
        );
    
        // Define triggers and their associated conditions
        $wpforms_conditions = array(
            'wpforms_process_complete' => array( 'field_value', 'user_meta' ),
            'wpforms_paypal_standard_process_complete' => array( 'field_value', 'user_meta' ),
        );
    
        // Build the final conditions array dynamically
        $formatted_conditions = array();
        
        foreach ( $wpforms_conditions as $trigger => $keys ) {
            foreach ( $keys as $key ) {
                $formatted_conditions[ $trigger ][ $key ] = $common_conditions[ $key ];
            }
        }
    
        return array_merge( $conditions, $formatted_conditions );
    }


    /**
     * This will fire at the very end of a (successful) form entry on WPForms
     *
     * @since 1.1.0
     * @version 1.2.2
     * @param array $fields | Sanitized entry field values/properties
     * @param array $entry | Original $_POST global
     * @param array $form_data | Form data and settings
     * @param int $entry_id | Entry ID Will return 0 if entry storage is disabled or using WPForms Lite
     * @return void
     * 
     * @link  https://wpforms.com/developers/wpforms_process_complete/
     */
    public function process_workflow_wpforms_form( $fields, $entry, $form_data, $entry_id ) {
        $payload = array(
            'type' => 'trigger',
            'hook' => 'wpforms_process_complete',
            'integration' => 'wpforms',
            'id' => absint( $form_data['id'] ),
            'fields' => $fields,
            'entry' => $entry,
            'form_data' => $form_data,
            'entry_id' => $entry_id,
        );

        Workflow_Processor::process_workflows( $payload );
    }


    /**
     * Fires when PayPal payment status notifies the site
     *
     * @since 1.1.0
     * @version 1.2.2
     * @param array $fields | Sanitized entry field values/properties
     * @param array $form_data | Form data and settings
     * @param int $payment_id | PayPal Payment ID
     * @param array $data | PayPal Web Accept Data
     * @return void
     * 
     * @link  https://wpforms.com/developers/wpforms_paypal_standard_process_complete/
     */
    public function process_workflow_wpforms_paypal( $fields, $form_data, $payment_id, $data ) {
        // Check if the payment status is not completed
        if ( empty( $data['payment_status'] ) || strtolower( $data['payment_status'] ) !== 'completed' ) {
            return;
        }

        $payload = array(
            'type' => 'trigger',
            'hook' => 'wpforms_paypal_standard_process_complete',
            'integration' => 'wpforms',
            'id' => absint( $form_data['id'] ),
            'fields' => $fields,
            'entry' => $entry,
            'form_data' => $form_data,
            'entry_id' => $entry_id,
        );

        Workflow_Processor::process_workflows( $payload );
    }
}