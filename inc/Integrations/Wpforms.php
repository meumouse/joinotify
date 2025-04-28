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
 * @version 1.3.0
 * @package MeuMouse.com
 */
class Wpforms extends Integrations_Base {

    /**
     * Construct function
     * 
     * @since 1.0.0
     * @version 1.3.0
     * @return void
     */
    public function __construct() {
        // add integration on settings
        add_filter( 'Joinotify/Settings/Tabs/Integrations', array( $this, 'add_integration_item' ), 60, 1 );

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
     * Add integration item on settings
     * 
     * @since 1.3.0
     * @param array $integrations | Current integrations
     * @return array
     */
    public function add_integration_item( $integrations ) {
        $integrations['wpforms'] = array(
            'title' => esc_html__('WPForms', 'joinotify'),
            'description' => esc_html__('Automatize o envio de mensagens ao receber um formulário WPForms com telefone. Mantenha seus clientes informados em tempo real.', 'joinotify'),
            'icon' => '<svg xmlns="http://www.w3.org/2000/svg" width="80" height="80" fill="none"><mask id="a" width="80" height="80" x="0" y="0" maskUnits="userSpaceOnUse"><path fill="#fff" d="M40 80c22.091 0 40-17.909 40-40S62.091 0 40 0 0 17.909 0 40s17.909 40 40 40z"/></mask><g mask="url(#a)"><path fill="#7F3E13" d="M23.383 12.337a8.038 8.038 0 017.805 8.278 7.818 7.818 0 11-15.611 0 8.035 8.035 0 017.806-8.278z"/><path fill="#B85A1B" d="M23.383 14.623a5.8 5.8 0 00-5.519 5.992 5.538 5.538 0 1011.038 0 5.8 5.8 0 00-5.519-5.992z"/><path fill="#63300F" d="M23.384 16.91a3.488 3.488 0 00-3.233 3.706 3.263 3.263 0 106.465 0 3.515 3.515 0 00-3.232-3.706z"/><path fill="#7F3E13" d="M56.615 12.337a8.037 8.037 0 017.805 8.278 7.818 7.818 0 11-15.611 0 8.035 8.035 0 017.806-8.278z"/><path fill="#B85A1B" d="M56.615 14.623a5.8 5.8 0 00-5.519 5.992 5.538 5.538 0 1011.038 0 5.8 5.8 0 00-5.519-5.992z"/><path fill="#4F2800" d="M56.616 16.91a3.488 3.488 0 00-3.233 3.706 3.263 3.263 0 106.465 0 3.515 3.515 0 00-3.232-3.706z"/><path fill="#7F3E13" d="M36.827 16.2a2.484 2.484 0 110 4.968 2.484 2.484 0 010-4.968zm6.307 0a2.484 2.484 0 110 4.969 2.484 2.484 0 010-4.969z"/><path fill="#7F3E13" d="M68.875 26.528v15.374h.158a5.716 5.716 0 013.666 5.361v11.078l-32.68 10.8-32.68-10.485V47.343a5.716 5.716 0 013.666-5.361h.158V26.489c0-10.644 57.713-10.644 57.713 0l-.001.039z"/><path fill="#B85A1B" d="M13.765 27.12v16.6l-1.34.551a3.784 3.784 0 00-2.444 3.584v11.668l30.039 9.658 30.039-9.895v-11.43a3.824 3.824 0 00-2.444-3.588l-1.34-.552v-16.6c0-7.884-52.47-7.884-52.47 0l-.04.003z"/><path fill="#E1762F" d="M13.765 27.12v16.6l-1.34.552a3.784 3.784 0 00-2.444 3.583v11.669l30.039 9.658v-6.347c-10.407.039-20.814-6.78-18.055-20.262H40.02V21.246c-13.127 0-26.254 1.971-26.254 5.913l-.001-.039z"/><path fill="#E5895B" d="M20.506 39.065h39.066c5.559 29.33-45.016 29.093-39.066 0z"/><path fill="#E5895B" d="M22.438 41.351c-.552 4.533.434 8.91 3.784 12.3 3.35 3.391 8.949 5.085 13.876 5.085a19.008 19.008 0 0013.324-4.928c3.509-3.39 4.612-7.805 4.139-12.457H22.438z"/><path fill="#FAD395" d="M46.563 55.03c2.641 3.39 10.289 2.01 8-5.164l-8 5.164z"/><path fill="#4F2800" d="M44.159 54.281c2.957 3.784 12.536 1.537 10.328-6.662l-10.328 6.662z"/><path fill="#fff" d="M46.721 52.31a1.183 1.183 0 11-.749 1.418 1.143 1.143 0 01.749-1.419zm6.268-4.337a1.685 1.685 0 100 .04v-.04z"/><path fill="#AD6151" d="M50.584 55.74a5.976 5.976 0 004.218-5.046c-1.734-.237-4.651 2.247-4.218 5.046z"/><path fill="#FAD395" d="M24.527 39.065h30.988c4.372 20.933-35.679 20.736-30.988 0z"/><path fill="#4F2800" d="M40.019 50.97a7.332 7.332 0 011.459-3.469c3.706-.591 6.938-3.39 5.913-8.476a22.275 22.275 0 00-7.331-1.261l-1.5 4.809 1.5 8.476-.041-.079z"/><path fill="#63300F" d="M40.019 50.97a7.332 7.332 0 00-1.459-3.469c-3.706-.591-6.938-3.39-5.913-8.476a22.273 22.273 0 017.332-1.261V50.97h.04z"/><path fill="#AD6151" d="M34.539 39.774c3.603-.97 7.397-.97 11 0 1.497 3.824-12.695 3.785-11 0z"/><path fill="#fff" d="M34.106 29.13a4.14 4.14 0 110 8.278 4.14 4.14 0 010-8.278z"/><path fill="#1B1D23" d="M34.422 30.549a3.076 3.076 0 11-3.075 3.075 3.114 3.114 0 013.075-3.075z"/><path fill="#fff" d="M46.011 29.13a4.14 4.14 0 100 8.278 4.14 4.14 0 000-8.278z"/><path fill="#1B1D23" d="M45.696 30.549a3.076 3.076 0 103.075 3.075 3.114 3.114 0 00-3.075-3.075z"/><path fill="#63300F" d="M37.103 27.75a11.581 11.581 0 00-8.594 1.5c-.868-5.048 7.686-6.546 8.594-1.5z"/><path fill="#4F2800" d="M42.148 25.228a11.58 11.58 0 018.594 1.5c.867-5.088-7.687-6.586-8.594-1.5z"/><path fill="#7EAABA" d="M72.66 58.42v31.813a3.863 3.863 0 01-3.863 3.863H11.163A3.863 3.863 0 017.3 90.233V58.696l32.68 7.805 32.68-8.081z"/><path fill="#D3E8EF" d="M70.058 61.771l-30.039 7.411-30.078-7.174v27.595a1.932 1.932 0 001.932 1.932h56.254a1.932 1.932 0 001.932-1.932l-.001-27.832z"/><path fill="#fff" d="M40.02 69.182L9.981 62.007v27.595a1.932 1.932 0 001.932 1.932H40.02V69.182z"/><path fill="#036AAB" d="M40.02 76.988h25.269v3.39H40.02v-3.39zm0 7.017h25.269v3.39H40.02v-3.39z"/><path fill="#0399ED" d="M14.751 76.988H40.02v3.39H14.751v-3.39zM40.02 87.395v-3.39H14.751v3.39H40.02z"/><path fill="#fff" d="M25 74.78h3.193v15.453H25V74.78z"/><path fill="#7EAABA" d="M16.13 60.785l23.85 5.716-8.515 8.042c-5.519-3.233-10.999-6.82-15.335-13.758z"/><path fill="#fff" d="M22.871 65.082a44.747 44.747 0 008.239 6.229l3.587-3.39-11.826-2.839z"/><path fill="#7EAABA" d="M63.869 60.785l-23.85 5.716 8.515 8.042c5.519-3.233 10.959-6.82 15.335-13.758z"/><path fill="#fff" d="M57.088 65.082a44.743 44.743 0 01-8.239 6.229l-3.587-3.39 11.826-2.839z"/></g></svg>',
            'setting_key' => 'enable_wpforms_integration',
            'action_hook' => 'Joinotify/Settings/Tabs/Integrations/Wpforms',
            'is_plugin' => true,
            'plugin_active' => array(
                'wpforms/wpforms.php',
                'wpforms-lite/wpforms.php',
            ),
        );

        return $integrations;
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