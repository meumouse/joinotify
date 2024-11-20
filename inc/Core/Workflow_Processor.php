<?php

namespace MeuMouse\Joinotify\Core;

use MeuMouse\Joinotify\API\Controller;
use MeuMouse\Joinotify\Cron\Schedule;
use MeuMouse\Joinotify\Builder\Placeholders;
use MeuMouse\Joinotify\Core\Admin;
use MeuMouse\Joinotify\Validations\Conditions;

/**
 * Process workflow content and send messages
 * 
 * @since 1.0.0
 * @package MeuMouse.com
 */
class Workflow_Processor {

    /**
     * Construct function
     * 
     * @since 1.0.0
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
     * @param int $order_id  | Order ID
     * @return void
     */
    public function process_workflow_on_new_order( $order_id ) {
        self::process_workflows( 'woocommerce_new_order', $order_id );
    }


    /**
     * Process workflow when order status is processing
     * 
     * @since 1.0.0
     * @param int $order_id  | Order ID
     * @return void
     */
    public function process_workflow_order_processed( $order_id ) {
        self::process_workflows( 'woocommerce_checkout_order_processed', $order_id );
    }


    /**
     * Process workflow when order status is complete
     * 
     * @since 1.0.0
     * @param int $order_id  | Order ID
     * @return void
     */
    public function process_workflow_order_completed( $order_id ) {
        self::process_workflows( 'woocommerce_order_status_completed', $order_id );
    }


    /**
     * Process workflow when order is fully refunded
     * 
     * @since 1.0.0
     * @param int $order_id  | Order ID
     * @return void
     */
    public function process_workflow_order_fully_refunded( $order_id ) {
        self::process_workflows( 'woocommerce_order_fully_refunded', $order_id );
    }


    /**
     * Process workflow when order is partially refunded
     * 
     * @since 1.0.0
     * @param int $order_id  | Order ID
     * @return void
     */
    public function process_workflow_order_partially_refunded( $order_id ) {
        self::process_workflows( 'woocommerce_order_partially_refunded', $order_id );
    }
    

    /**
     * Process workflow when order has status changed
     * 
     * @since 1.0.0
     * @param int $order_id  | Order ID
     * @return void
     */
    public function process_workflow_order_status_changed( $order_id ) {
        self::process_workflows( 'woocommerce_order_status_changed', $order_id );
    }


    /**
     * Process workflow for each called hook
     * 
     * @since 1.0.0
     * @param string $hook | Hook for call actions
     * @param int $order_id | Order ID
     * @return void
     */
    public static function process_workflows( $hook, $order_id ) {
        $workflows = self::get_workflows_by_hook( $hook );

        if ( empty( $workflows ) ) {
            return;
        }

        foreach ( $workflows as $workflow ) {
            $workflow_content = get_post_meta( $workflow->ID, 'joinotify_workflow_content', true );

            if ( ! empty( $workflow_content ) && is_array( $workflow_content ) ) {
                $post_id = $workflow->ID;
                self::process_workflow_content( $workflow_content, $post_id, $order_id );
            }
        }
    }


    /**
     * Process workflow content
     * 
     * @since 1.0.0
     * @param array $workflow_content | Workflow content
     * @param int $post_id | Post ID
     * @param int $order_id | Order ID
     * @return void
     */
    public static function process_workflow_content( $workflow_content, $post_id, $order_id ) {
        $state = get_post_meta( $post_id, 'joinotify_workflow_state_' . $order_id, true );

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
            if ( self::handle_action( $action, $post_id, $order_id ) ) {
                $state['processed_actions'][] = $action_id;
                unset( $state['pending_actions'][$index] );
                $state['pending_actions'] = array_values( $state['pending_actions'] );

                // Update state in the database
                update_post_meta( $post_id, 'joinotify_workflow_state_' . $order_id, $state );

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
     * @param array $action | Workflow actions array
     * @param int $post_id | Post ID
     * @param int $order_id | Order ID
     * @return bool
     */
    public static function handle_action( $action, $post_id, $order_id ) {
        $action_data = $action['data'];

        if ( $action_data['action'] === 'time_delay' ) {
            Schedule::schedule_actions( $post_id, $order_id, $action_data['delay_timestamp'], $action );

            return true;
        }

        if ( $action_data['action'] === 'condition' ) {
            $order = wc_get_order( $order_id );
            $get_condition = $action_data['condition_content']['condition'] ?? '';
            $condition_type = $action_data['condition_content']['type'] ?? '';
            $condition_value = $action_data['condition_content']['value'] ?? '';
            $compare_value = Conditions::get_compare_value( $get_condition, $order );
            $condition_met = Conditions::check_condition( $condition_type, $condition_value, $compare_value );
            $next_actions = $condition_met ? $action['children']['action_true'] ?? [] : $action['children']['action_false'] ?? [];
            
            foreach ( $next_actions as $child_action ) {
                self::handle_action( $child_action, $post_id, $order_id );
            }
            
            return true;
        }

        self::execute_action( $action_data, $order_id );

        return true;
    }


    /**
     * Execute specific action
     *
     * @since 1.0.0
     * @param array $action_data | Workflow data
     * @param int $order_id | Order ID
     * @return void
     */
    public static function execute_action( $action_data, $order_id ) {
        error_log('ação executada');
        error_log( 'action_data: ' . print_r( $action_data, true ) );

        if ( isset( $action_data['action'] ) ) {
            switch ( $action_data['action'] ) {
                case 'send_whatsapp_message_text':
                    self::send_whatsapp_message_text( $action_data, $order_id );

                    break;
                case 'send_whatsapp_message_media':
                    self::send_whatsapp_message_media( $action_data, $order_id );

                    break;
            }
        }
    }


    /**
     * Process scheduled actions
     * 
     * @since 1.0.0
     * @param int $post_id | Workflow ID
     * @param int $order_id | Order ID
     * @param array $action_data | Action data
     * @return void
     */
    public static function process_scheduled_action( $post_id, $order_id, $action_data ) {
        $state = get_post_meta( $post_id, 'joinotify_workflow_state_' . $order_id, true );
    
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
                Schedule::schedule_actions( $post_id, $order_id, $action['data']['delay_timestamp'], $action );
                break;
            }
    
            if ( self::handle_action( $action, $post_id, $order_id ) ) {
                // Mark as processed
                $state['processed_actions'][] = $action_id;
    
                update_post_meta( $post_id, 'joinotify_workflow_state_' . $order_id, $state );
            }
        }
    }


    /**
     * Send message text on WhatsApp
     *
     * @since 1.0.0
     * @param array $action_data | Action data
     * @param int $order_id | Order ID
     * @return void
     */
    public static function send_whatsapp_message_text( $action_data, $order_id ) {
        $sender = $action_data['sender'];
        $receiver = self::prepare_receiver( $action_data['receiver'], $order_id );
        $message = Placeholders::replace_placeholders( $action_data['message'], $order_id );
        $response = Controller::send_message_text( $sender, $receiver, $message );

        if ( JOINOTIFY_DEBUG_MODE ) {
            if (201 === $response) {
                error_log("Message sent successfully to: $receiver");
            } else {
                error_log( "Failed to send message. Response: " . print_r( $response, true ) );
            }
        }
    }


    /**
     * Executes a WhatsApp message action
     *
     * @since 1.0.0
     * @param array $action_data | Action data
     * @param array $params | Hook parameters
     * @return void
     */
    public static function send_whatsapp_message_media( $action_data, $params ) {
        $sender = $action_data['sender'];
        $receiver = self::prepare_receiver( $action_data['receiver'], $order_id );
        $media_type = $item['data']['media_type'];
        $media = $item['data']['media_url'];
        $response = Controller::send_message_media( $sender, $receiver, $media_type, $media );

        if ( JOINOTIFY_DEBUG_MODE ) {
            if (201 === $response) {
                error_log("Message sent successfully to: $receiver");
            } else {
                error_log("Failed to send message. Response: " . print_r($response, true));
            }
        }
    }


    /**
     * Prepare the receiver phone number with the correct format
     * 
     * @since 1.0.0
     * @param string $receiver |  Receiver phone
     * @param int $order_id | Order ID
     * @return string
     */
    public static function prepare_receiver( $receiver, $order_id ) {
        if ( strpos( $receiver, '{{ phone }}' ) !== false ) {
            $receiver = Placeholders::replace_placeholders( $receiver, $order_id );
        }

        $receiver = preg_replace( '/\D/', '', $receiver );
        $country_code = Admin::get_setting( 'joinotify_default_country_code' );

        if ( preg_match( '/^\d{10,11}$/', $receiver ) && strpos( $receiver, $country_code ) !== 0 ) {
            $receiver = $country_code . $receiver;
        }

        return $receiver;
    }
}