<?php

namespace MeuMouse\Joinotify\Core;

use MeuMouse\Joinotify\Admin\Admin;
use MeuMouse\Joinotify\Core\Logger;
use MeuMouse\Joinotify\API\Controller;
use MeuMouse\Joinotify\Cron\Schedule;
use MeuMouse\Joinotify\Builder\Placeholders;
use MeuMouse\Joinotify\Validations\Conditions;
use WC_Order;

/**
 * Process workflow content and send messages on fire hooks
 * 
 * @since 1.0.0
 * @version 1.1.0
 * @package MeuMouse.com
 */
class Workflow_Processor {

    /**
     * Construct function
     * 
     * @since 1.0.0
     * @version 1.1.0
     * @return void
     */
    public function __construct() {
        // fire hooks if WooCommerce is active
        if ( class_exists('WooCommerce') && Admin::get_setting('enable_woocommerce_integration') === 'yes' ) {
            // on receive new order
            add_action( 'woocommerce_new_order', array( $this, 'process_workflow_on_new_order' ), 10, 1 );

            // when order is processing
            add_action( 'woocommerce_checkout_order_processed', array( $this, 'process_workflow_order_processed' ), 10, 1 );

            // when order is completed
            add_action( 'woocommerce_order_status_completed', array( $this, 'process_workflow_order_completed' ), 10, 1 );

            // when a order is fully refunded
            add_action( 'woocommerce_order_fully_refunded', array( $this, 'process_workflow_order_fully_refunded' ), 10, 1 );

            // when a order is partially refunded
            add_action( 'woocommerce_order_partially_refunded', array( $this, 'process_workflow_order_partially_refunded' ), 10, 1 );
            
            // when a order has status changed
            add_action( 'woocommerce_order_status_changed', array( $this, 'process_workflow_order_status_changed' ), 10, 1 );
        }

        // fire hook if Elementor is active
        if ( defined('ELEMENTOR_PATH') && Admin::get_setting('enable_elementor_integration') === 'yes' ) {
            // when a Elementor form receive a new record
            add_action( 'elementor_pro/forms/new_record', array( $this, 'process_workflow_elementor_form' ), 10, 2 );
        }
    }


    /**
     * Returns published posts that have a specific hook in the content
     *
     * @since 1.0.0
     * @param string $hook_name | Name of the hook to search for
     * @return array | List of posts that have the specified hook
     */
    public static function get_workflows_by_hook( $hook_name ) {
        $args = array(
            'post_type' => 'joinotify-workflow',
            'post_status' => 'publish',
            'numberposts' => -1,
            'meta_query' => array(
                array(
                    'key' => 'joinotify_workflow_content',
                    'value' => $hook_name,
                    'compare' => 'LIKE',
                ),
            ),
        );

        // Returns the posts found
        return get_posts( $args );
    }


    /**
     * Process workflow on receive new order on WooCommerce
     * 
     * @since 1.0.0
     * @version 1.1.0
     * @param int $order_id  | Order ID
     * @return void
     */
    public function process_workflow_on_new_order( $order_id ) {
        $context = array(
            'type' => 'trigger',
            'integration' => 'woocommerce',
            'order_id' => $order_id,
        );

        self::process_workflows( 'woocommerce_new_order', $context );
    }


    /**
     * Process workflow when order status is processing
     * 
     * @since 1.0.0
     * @version 1.1.0
     * @param int $order_id  | Order ID
     * @return void
     */
    public function process_workflow_order_processed( $order_id ) {
        $context = array(
            'type' => 'trigger',
            'integration' => 'woocommerce',
            'order_id' => $order_id,
        );

        self::process_workflows( 'woocommerce_checkout_order_processed', $context );
    }


    /**
     * Process workflow when order status is complete
     * 
     * @since 1.0.0
     * @version 1.1.0
     * @param int $order_id  | Order ID
     * @return void
     */
    public function process_workflow_order_completed( $order_id ) {
        $context = array(
            'type' => 'trigger',
            'integration' => 'woocommerce',
            'order_id' => $order_id,
        );

        self::process_workflows( 'woocommerce_order_status_completed', $context );
    }


    /**
     * Process workflow when order is fully refunded
     * 
     * @since 1.0.0
     * @version 1.1.0
     * @param int $order_id  | Order ID
     * @return void
     */
    public function process_workflow_order_fully_refunded( $order_id ) {
        $context = array(
            'type' => 'trigger',
            'integration' => 'woocommerce',
            'order_id' => $order_id,
        );

        self::process_workflows( 'woocommerce_order_fully_refunded', $context );
    }


    /**
     * Process workflow when order is partially refunded
     * 
     * @since 1.0.0
     * @version 1.1.0
     * @param int $order_id  | Order ID
     * @return void
     */
    public function process_workflow_order_partially_refunded( $order_id ) {
        $context = array(
            'type' => 'trigger',
            'integration' => 'woocommerce',
            'order_id' => $order_id,
        );

        self::process_workflows( 'woocommerce_order_partially_refunded', $context );
    }
    

    /**
     * Process workflow when order has status changed
     * 
     * @since 1.0.0
     * @version 1.1.0
     * @param int $order_id  | Order ID
     * @return void
     */
    public function process_workflow_order_status_changed( $order_id ) {
        $context = array(
            'type' => 'trigger',
            'integration' => 'woocommerce',
            'order_id' => $order_id,
        );

        self::process_workflows( 'woocommerce_order_status_changed', $context );
    }


    /**
     * Process workflow content on Elementor form submission
     * 
     * @since 1.1.0
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
    
        $context = array(
            'integration' => 'elementor',
            'type' => 'trigger',
            'id' => uniqid('elementor_form_'),
            'fields' => $fields,
        );
    
        self::process_workflows( 'elementor_pro/forms/new_record', $context );
    }


    /**
     * Process workflow for each called hook
     * 
     * @since 1.0.0
     * @version 1.1.0
     * @param string $hook | Hook for call actions
     * @param array $context | Context data
     * @return void
     */
    public static function process_workflows( $hook, $context ) {
        $workflows = self::get_workflows_by_hook( $hook );

        if ( JOINOTIFY_DEBUG_MODE ) {
            Logger::register_log( 'Function process_workflows() fired' );
            Logger::register_log( 'Param $hook: ' . print_r( $hook, true ) );
            Logger::register_log( 'Param $context: ' . print_r( $context, true ) );
        }

        if ( empty( $workflows ) ) {
            return;
        }

        foreach ( $workflows as $workflow ) {
            $workflow_content = get_post_meta( $workflow->ID, 'joinotify_workflow_content', true );

            if ( ! empty( $workflow_content ) && is_array( $workflow_content ) ) {
                $post_id = $workflow->ID; // for get workflow content
                self::process_workflow_content( $workflow_content, $post_id, $context );
            }
        }
    }


    /**
     * Process workflow content
     * 
     * @since 1.0.0
     * @version 1.1.0
     * @param array $workflow_content | Workflow content
     * @param int $post_id | Post ID
     * @param array $context | Context data
     * @return void
     */
    public static function process_workflow_content( $workflow_content, $post_id, $context ) {
        if ( JOINOTIFY_DEBUG_MODE ) {
            Logger::register_log( 'Function process_workflow_content() fired' );
            Logger::register_log( 'Param $workflow_content: ' . print_r( $workflow_content, true ) );
            Logger::register_log( 'Param $post_id: ' . print_r( $post_id, true ) );
            Logger::register_log( 'Param $context: ' . print_r( $context, true ) );
        }

        if ( $context['integration'] === 'woocommerce' ) {
            $state = get_post_meta( $post_id, 'joinotify_workflow_state_' . $context['order_id'], true );
        } else {
            $state = get_post_meta( $post_id, 'joinotify_workflow_state_' . $context['id'], true );
        }

        // Remove triggers from flow content
        $workflow_actions = array_values( array_filter( $workflow_content, function ( $item ) {
            return isset( $item['type'] ) && $item['type'] !== 'trigger';
        } ) );

        if ( empty( $state ) || ! is_array( $state ) ) {
            $state = array(
                'processed_actions' => array(),
                'pending_actions' => $workflow_actions,
            );
        }
    
        // Processes pending actions
        foreach ( $state['pending_actions'] as $index => $action ) {
            $action_id = $action['id'] ?? null;

            // Ignore trigger or already processed actions
            if ( in_array( $action_id, $state['processed_actions'], true ) ) {
                continue;
            }
    
            // Executa a ação
            if ( self::handle_action( $action, $post_id, $context ) ) {
                $state['processed_actions'][] = $action_id;
                unset( $state['pending_actions'][$index] );
                $state['pending_actions'] = array_values( $state['pending_actions'] );

                // Update state in the database
                if ( $context['integration'] === 'woocommerce' ) {
                    update_post_meta( $post_id, 'joinotify_workflow_state_' . $context['order_id'], $state );
                } else {
                    update_post_meta( $post_id, 'joinotify_workflow_state_' . $context['id'], $state );
                }

                // Break after time delay action
                if ( $action['data']['action'] === 'time_delay' ) {
                    break;
                }
            }
        }
    }


    /**
     * Handle with workflow actions
     * 
     * @since 1.0.0
     * @version 1.1.0
     * @param array $action | Workflow actions array
     * @param int $post_id | Post ID
     * @param array $context | Context data
     * @return bool
     */
    public static function handle_action( $action, $post_id, $context ) {
        $action_data = $action['data'];

        if ( JOINOTIFY_DEBUG_MODE ) {
            Logger::register_log( 'Function handle_action() fired' );
            Logger::register_log( 'Param $action: ' . print_r( $action, true ) );
            Logger::register_log( 'Param $post_id: ' . print_r( $post_id, true ) );
            Logger::register_log( 'Param $context: ' . print_r( $context, true ) );
        }

        if ( $action_data['action'] === 'time_delay' ) {
            Schedule::schedule_actions( $post_id, $context, $action_data['delay_timestamp'], $action );

            return true;
        }

        if ( $action_data['action'] === 'condition' ) {
            $get_condition = $action_data['condition_content']['condition'] ?? '';
            $condition_type = $action_data['condition_content']['type'] ?? '';
            $condition_value = $action_data['condition_content']['value'] ?? '';

            $compare_value = Conditions::get_compare_value( $get_condition, $context );
            $condition_met = Conditions::check_condition( $condition_type, $condition_value, $compare_value );
            $next_actions = $condition_met ? $action['children']['action_true'] ?? array() : $action['children']['action_false'] ?? array();
            
            foreach ( $next_actions as $child_action ) {
                self::handle_action( $child_action, $post_id, $context );
            }
            
            return true;
        }

        self::execute_action( $action_data, $context );

        return true;
    }


    /**
     * Execute specific action
     *
     * @since 1.0.0
     * @version 1.1.0
     * @param array $action_data | Workflow data
     * @param array $context | Context data
     * @return void
     */
    public static function execute_action( $action_data, $context ) {
        if ( JOINOTIFY_DEBUG_MODE ) {
            Logger::register_log( 'Function execute_action() fired' );
            Logger::register_log( 'Param $action_data: ' . print_r( $action_data, true ) );
            Logger::register_log( 'Param $context: ' . print_r( $context, true ) );
        }

        if ( isset( $action_data['action'] ) ) {
            switch ( $action_data['action'] ) {
                case 'send_whatsapp_message_text':
                    self::send_whatsapp_message_text( $action_data, $context );

                    break;
                case 'send_whatsapp_message_media':
                    self::send_whatsapp_message_media( $action_data, $context );

                    break;
            }
        }
    }


    /**
     * Process scheduled actions
     * 
     * @since 1.0.0
     * @version 1.1.0
     * @param int $post_id | Workflow ID
     * @param array $context | Context data
     * @param array $action_data | Action data
     * @return void
     */
    public static function process_scheduled_action( $post_id, $context, $action_data ) {
        if ( $context['integration'] === 'woocommerce' ) {
            $state = get_post_meta( $post_id, 'joinotify_workflow_state_' . $context['order_id'], true );
        } else {
            $state = get_post_meta( $post_id, 'joinotify_workflow_state_' . $context['id'], true );
        }
    
        if ( ! is_array( $state ) ) {
            $state = array(
                'processed_actions' => array(),
                'pending_actions' => array(),
            );
        }
    
        $workflow_content = get_post_meta( $post_id, 'joinotify_workflow_content', true );
    
        if ( empty( $workflow_content ) || ! is_array( $workflow_content ) ) {
            return;
        }
    
        // Remove triggers from flow content
        $workflow_actions = array_values( array_filter( $workflow_content, function ( $item ) {
            return isset( $item['type'] ) && $item['type'] !== 'trigger';
        } ) );
    
        // Iterate over all actions in the flow and process any that are not completed
        foreach ( $workflow_actions as $action ) {
            $action_id = $action['id'] ?? null;
    
            // Ignore already processed actions
            if ( $action_id && in_array( $action_id, $state['processed_actions'], true ) ) {
                continue;
            }
    
            if ( $action['data']['action'] === 'time_delay' ) {
                Schedule::schedule_actions( $post_id, $context, $action['data']['delay_timestamp'], $action );
                break;
            }
    
            if ( self::handle_action( $action, $post_id, $context ) ) {
                // Mark as processed
                $state['processed_actions'][] = $action_id;
    
                if ( $context['integration'] === 'woocommerce' ) {
                    update_post_meta( $post_id, 'joinotify_workflow_state_' . $context['order_id'], $state );
                } else {
                    update_post_meta( $post_id, 'joinotify_workflow_state_' . $context['id'], $state );
                }
            }
        }
    }


    /**
     * Send message text on WhatsApp
     *
     * @since 1.0.0
     * @version 1.1.0
     * @param array $action_data | Action data
     * @param array $context | Context data
     * @return void
     */
    public static function send_whatsapp_message_text( $action_data, $context ) {
        $sender = $action_data['sender'];
        $receiver = Controller::prepare_receiver( $action_data['receiver'], $context );
        $message = Placeholders::replace_placeholders( $action_data['message'], $context );
        $response = Controller::send_message_text( $sender, $receiver, $message );

        if ( JOINOTIFY_DEBUG_MODE ) {
            if ( 201 === $response ) {
                Logger::register_log( "Message sent successfully to: $receiver" );
            } else {
                Logger::register_log( "Failed to send message. Response: " . print_r( $response, true ), 'ERROR' );
            }
        }
    }


    /**
     * Executes a WhatsApp message action
     *
     * @since 1.0.0
     * @version 1.1.0
     * @param array $action_data | Action data
     * @param array $context | Context data
     * @return void
     */
    public static function send_whatsapp_message_media( $action_data, $context ) {
        $sender = $action_data['sender'];
        $receiver = Controller::prepare_receiver( $action_data['receiver'], $context );
        $media_type = $action_data['data']['media_type'];
        $media = $action_data['data']['media_url'];
        $response = Controller::send_message_media( $sender, $receiver, $media_type, $media );

        if ( JOINOTIFY_DEBUG_MODE ) {
            if ( 201 === $response ) {
                Logger::register_log( "Message sent successfully to: $receiver" );
            } else {
                Logger::register_log( "Failed to send message. Response: " . print_r( $response, true ), 'ERROR' );
            }
        }
    }
}