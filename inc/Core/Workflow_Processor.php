<?php

namespace MeuMouse\Joinotify\Core;

use MeuMouse\Joinotify\Admin\Admin;
use MeuMouse\Joinotify\API\Controller;
use MeuMouse\Joinotify\Cron\Schedule;
use MeuMouse\Joinotify\Validations\Conditions;
use MeuMouse\Joinotify\Integrations\Woocommerce;

use WC_Order;

/**
 * Process workflow content and send messages on fire hooks
 * 
 * @since 1.0.0
 * @version 1.2.0
 * @package MeuMouse.com
 */
class Workflow_Processor {

    /**
     * Construct function
     * 
     * @since 1.0.0
     * @version 1.2.0
     * @return void
     */
    public function __construct() {
        // fire hooks if WordPress is active
        if ( Admin::get_setting('enable_wordpress_integration') === 'yes' ) {
            // on user register
            add_action( 'user_register', array( $this, 'process_workflow_user_register' ), 10, 2 );

            // on user login
            add_action( 'wp_login', array( $this, 'process_workflow_user_login' ), 10, 2 );

            // on password reset
            add_action( 'password_reset', array( $this, 'process_workflow_password_reset' ), 10, 2 );

            // on change post status
            add_action( 'transition_post_status', array( $this, 'process_workflow_change_post_status' ), 10, 3 );
        }

        // fire hooks if WooCommerce is active
        if ( class_exists('WooCommerce') && Admin::get_setting('enable_woocommerce_integration') === 'yes' ) {
            // on receive new order
            add_action( 'woocommerce_new_order', array( $this, 'process_workflow_on_new_order' ), 10, 2 );

            // when order is processing
            add_action( 'woocommerce_checkout_order_processed', array( $this, 'process_workflow_order_processed' ), 10, 3 );

            // when order is completed
            add_action( 'woocommerce_order_status_completed', array( $this, 'process_workflow_order_completed' ), 10, 3 );

            // when a order is fully refunded
            add_action( 'woocommerce_order_fully_refunded', array( $this, 'process_workflow_order_fully_refunded' ), 10, 2 );

            // when a order is partially refunded
            add_action( 'woocommerce_order_partially_refunded', array( $this, 'process_workflow_order_partially_refunded' ), 10, 2 );
            
            // when a order has status changed
            add_action( 'woocommerce_order_status_changed', array( $this, 'process_workflow_order_status_changed' ), 10, 3 );

            if ( class_exists('WC_Subscriptions') ) {
                // fire when a subscription is created
                add_action( 'woocommerce_checkout_subscription_created', array( $this, 'process_workflow_subscription_created' ), 10, 3 );

                // fire when a subscription status is active
                add_action( 'woocommerce_subscription_status_active', array( $this, 'process_workflow_subscription_status_active' ), 10, 3 );

                // fire when a subscription payment is complete
                add_action( 'woocommerce_subscription_payment_complete', array( $this, 'process_workflow_subscription_payment_complete', 10, 1 ) );

                // fire when a subscription payment is failed
                add_action( 'woocommerce_subscription_payment_failed', array( $this, 'process_workflow_subscription_payment_failed', 10, 2 ) );

                // fire when a subscription status is expired
                add_action( 'woocommerce_subscription_status_expired', array(  $this, 'process_workflow_subscription_status_expired' ), 10, 1 );

                // fire when a subscription status is cancelled
                add_action( 'woocommerce_subscription_status_cancelled', array( $this, 'process_workflow_subscription_status_cancelled' ), 10, 1 );
            }
        }

        // fire hook if Elementor is active
        if ( defined('ELEMENTOR_PATH') && Admin::get_setting('enable_elementor_integration') === 'yes' ) {
            // when a Elementor form receive a new record
            add_action( 'elementor_pro/forms/new_record', array( $this, 'process_workflow_elementor_form' ), 10, 2 );
        }

        // fire hooks if WPForms is active
        if ( function_exists('wpforms') && Admin::get_setting('enable_wpforms_integration') === 'yes' ) {
            // when a WPForms form receive a new record
            add_action( 'wpforms_process_complete', array( $this, 'process_workflow_wpforms_form' ), 10, 4 );

            // when a WPForms form paypal payment is fired
            add_action( 'wpforms_paypal_standard_process_complete', array( $this, 'process_workflow_wpforms_paypal' ), 10, 4 );
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
     * Processs workflow content on user register
     * 
     * @since 1.1.0
     * @version 1.2.0
     * @param int $user_id | User ID
     * @param array $userdata | User data
     * @return void
     */
    public function process_workflow_user_register( $user_id, $userdata ) {
        $payload = array(
            'type' => 'trigger',
            'hook' => 'user_register',
            'integration' => 'wordpress',
            'user_id' => $user_id,
            'user_data' => $userdata,
        );

        self::process_workflows( $payload );
    }


    /**
     * Processs workflow content on user login
     * 
     * @since 1.1.0
     * @version 1.2.0
     * @param string $user_login | User login
     * @param object $user | User object
     * @return void
     */
    public function process_workflow_user_login( $user_login, $user ) {
        $payload = array(
            'type' => 'trigger',
            'hook' => 'user_login',
            'integration' => 'wordpress',
            'user_id' => $user->ID,
            'user_data' => $user,
        );

        self::process_workflows( $payload );
    }


    /**
     * Processs workflow content on password reset
     * 
     * @since 1.1.0
     * @version 1.2.0
     * @param object $user | User object
     * @param string $new_pass | New password
     * @return void
     */
    public function process_workflow_password_reset( $user, $new_pass ) {
        $payload = array(
            'type' => 'trigger',
            'hook' => 'password_reset',
            'integration' => 'wordpress',
            'user_id' => $user->ID,
            'user_data' => $user,
        );

        self::process_workflows( $payload );
    }


    /**
     * Process workflow content on post status changed
     * 
     * @since 1.1.0
     * @version 1.2.0
     * @param string $new_status | New post status
     * @param string $old_status | Old post status
     * @param object $post | Post object
     * @return void
     */
    public function process_workflow_change_post_status( $new_status, $old_status, $post ) {
        $payload = array(
            'type' => 'trigger',
            'hook' => 'change_post_status',
            'integration' => 'wordpress',
            'post_id' => $post->ID,
            'post_type' => $post->post_type,
            'post_status' => $new_status,
            'old_post_status' => $old_status,
        );

        self::process_workflows( $payload );
    }


    /**
     * Process workflow on receive new order on WooCommerce
     * 
     * @since 1.0.0
     * @version 1.2.0
     * @param int $order_id  | Order ID
     * @param object $order | Order object
     * @return void
     */
    public function process_workflow_on_new_order( $order_id, $order ) {
        $payload = array(
            'type' => 'trigger',
            'hook' => 'woocommerce_new_order',
            'integration' => 'woocommerce',
            'order_id' => $order_id,
            'order_data' => $order,
        );

        self::process_workflows( $payload );
    }


    /**
     * Process workflow when order status is processing
     * 
     * @since 1.0.0
     * @version 1.2.0
     * @param int $order_id  | Order ID
     * @param array $posted_data | User submitted checkout data
     * @param object $order | Order object
     * @return void
     */
    public function process_workflow_order_processed( $order_id, $posted_data, $order ) {
        $payload = array(
            'type' => 'trigger',
            'hook' => 'woocommerce_checkout_order_processed',
            'integration' => 'woocommerce',
            'order_id' => $order_id,
            'posted_data' => $posted_data,
            'order_data' => $order,
        );

        self::process_workflows( $payload );
    }


    /**
     * Process workflow when order status is complete
     * 
     * @since 1.0.0
     * @version 1.2.0
     * @param int $order_id  | Order ID
     * @param object $order | Order object
     * @param array $status_transition | Status transition
     * @return void
     */
    public function process_workflow_order_completed( $order_id, $order, $status_transition ) {
        $payload = array(
            'type' => 'trigger',
            'hook' => 'woocommerce_order_status_completed',
            'integration' => 'woocommerce',
            'order_id' => $order_id,
            'order_data' => $order,
            'status_transition' => $status_transition,
        );

        self::process_workflows( $payload );
    }


    /**
     * Process workflow when order is fully refunded
     * 
     * @since 1.0.0
     * @version 1.2.0
     * @param int $order_id  | Order ID
     * @param int $refund_id | Refund ID
     * @return void
     */
    public function process_workflow_order_fully_refunded( $order_id, $refund_id ) {
        $payload = array(
            'type' => 'trigger',
            'hook' => 'woocommerce_order_fully_refunded',
            'integration' => 'woocommerce',
            'order_id' => $order_id,
            'refund_id' => $refund_id,
        );

        self::process_workflows( $payload );
    }


    /**
     * Process workflow when order is partially refunded
     * 
     * @since 1.0.0
     * @version 1.2.0
     * @param bool $is_partially_refunded | Is partially refunded
     * @param int $order_id  | Order ID
     * @return void
     */
    public function process_workflow_order_partially_refunded( $is_partially_refunded, $order_id ) {
        $payload = array(
            'type' => 'trigger',
            'hook' => 'woocommerce_order_partially_refunded',
            'integration' => 'woocommerce',
            'is_partially_refunded' => $is_partially_refunded,
            'order_id' => $order_id,
        );

        self::process_workflows( $payload );
    }
    

    /**
     * Process workflow when order has status changed
     * 
     * @since 1.0.0
     * @version 1.2.0
     * @param int $order_id  | Order ID
     * @param string $old_status | Old status
     * @param string $new_status | New status
     * @return void
     */
    public function process_workflow_order_status_changed( $order_id, $old_status, $new_status ) {
        $payload = array(
            'type' => 'trigger',
            'hook' => 'woocommerce_order_status_changed',
            'integration' => 'woocommerce',
            'order_id' => $order_id,
            'old_status' => $old_status,
            'new_status' => $new_status,
        );

        self::process_workflows( $payload );
    }


    /**
     * Process workflow content on Elementor form submission
     * 
     * @since 1.1.0
     * @version 1.2.0
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
    
        self::process_workflows( $payload );
    }


    /**
     * Process workflow for each called hook
     * 
     * @since 1.0.0
     * @version 1.2.0
     * @param array $payload | Payload data
     * @return void
     */
    public static function process_workflows( $payload ) {
        $hook = $payload['hook'];
        $workflows = self::get_workflows_by_hook( $hook );

        /**
         * Process workflows hook
         * 
         * @since 1.2.0
         * @param string $hook | Hook for call actions
         * @param array $payload | Payload data
         * @param array $workflows | Array of workflows
         */
        do_action( 'Joinotify/Workflow_Processor/Process_Workflows', $hook, $payload, $workflows );

        if ( JOINOTIFY_DEBUG_MODE ) {
            Logger::register_log( 'Function process_workflows() fired' );
            Logger::register_log( 'hook: ' . print_r( $hook, true ) );
            Logger::register_log( 'payload: ' . print_r( $payload, true ) );
        }

        if ( empty( $workflows ) ) {
            return;
        }

        // loop through workflows
        foreach ( $workflows as $workflow ) {
            $workflow_content = get_post_meta( $workflow->ID, 'joinotify_workflow_content', true );

            if ( ! empty( $workflow_content ) && is_array( $workflow_content ) ) {
                $post_id = $workflow->ID; // for get workflow content

                self::process_workflow_content( $workflow_content, $post_id, $payload );
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
     * @param array $payload | Payload data
     * @return void
     */
    public static function process_workflow_content( $workflow_content, $post_id, $payload ) {
        /*
        if ( JOINOTIFY_DEV_MODE ) {
            error_log( 'Function process_workflow_content() fired' );
            error_log( 'Param $workflow_content: ' . print_r( $workflow_content, true ) );
            error_log( 'Param $post_id: ' . print_r( $post_id, true ) );
            error_log( 'Param $payload: ' . print_r( $payload, true ) );
        }*/

        /**
         * Before process Joinotify workflows
         * 
         * @since 1.2.0
         * @param array $workflow_content | Workflow content
         * @param int $post_id | Post ID
         * @param array $payload | Payload data
         */
        do_action( 'Joinotify/Workflow_Processor/Process_Workflow_Content', $workflow_content, $post_id, $payload );

        // get integration
        $integration = $payload['integration'];

        // get trigger data
        $trigger_data = array_filter( $workflow_content, function ( $item ) {
            return isset( $item['type'] ) && $item['type'] === 'trigger';
        });

        // get first array item
        $trigger_data = reset( $trigger_data );

        if ( $integration === 'woocommerce' ) {
            $state = get_post_meta( $post_id, 'joinotify_workflow_state_' . $payload['order_id'], true );
            $order = wc_get_order( $payload['order_id'] );
            $order_status = str_replace( 'wc-', '', $order->get_status() ); // remove prefix "wc-"
            
            // check order status
            if ( isset( $payload['hook'] ) && $payload['hook'] === 'woocommerce_order_status_changed' ) {
                // remove prefix "wc-" fron workflow trigger settings
                $trigger_order_status = str_replace( 'wc-', '', $trigger_data['data']['settings']['order_status'] );

                if ( $trigger_order_status !== $order_status ) {
                    return;
                }
            }
        } elseif ( $integration === 'wpforms' ) {
            // check wpforms form id
            if ( $payload['id'] !== absint( $trigger_data['data']['settings']['form_id'] ) ) {
                return;
            }
        }

        // Remove triggers from flow content
        $workflow_actions = array_values( array_filter( $workflow_content, function ( $item ) {
            return isset( $item['type'] ) && $item['type'] !== 'trigger';
        }));

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
    
            // execute actions
            if ( self::handle_action( $action, $post_id, $payload ) ) {
                $state['processed_actions'][] = $action_id;

                // remove action from pending actions
                unset( $state['pending_actions'][$index] );

                // reindex array
                $state['pending_actions'] = array_values( $state['pending_actions'] );

                // Update state in the database for woocommerce orders
                if ( $payload['integration'] === 'woocommerce' ) {
                    update_post_meta( $post_id, 'joinotify_workflow_state_' . $payload['order_id'], $state );
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
     * @param array $payload | Payload data
     * @return bool
     */
    public static function handle_action( $action, $post_id, $event_data ) {
        $action_data = $action['data'] ?? [];
    
        if ( empty( $action_data['action'] ) ) {
            return false;
        }
    
        if ( JOINOTIFY_DEV_MODE ) {
            error_log( "Handling action: " . print_r( $action, true ) );
        }
    
        /**
         * Filter to add custom actions
         * 
         * @since 1.1.0
         * @param array $actions | Array of actions
         * @return array
         */
        $actions = apply_filters( 'Joinotify/Workflow_Processor/Handle_Actions', array(
            'time_delay' => fn() => Schedule::schedule_actions( $post_id, $event_data, $action_data['delay_timestamp'], $action ),
            'condition' => fn() => self::process_condition_action( $action, $post_id, $event_data ),
            'send_whatsapp_message_text' => fn() => self::send_whatsapp_message_text( $action_data, $event_data ),
            'send_whatsapp_message_media' => fn() => self::send_whatsapp_message_media( $action_data, $event_data ),
            'create_coupon' => fn() => self::execute_wc_coupon_action( $action_data, $event_data ),
            'snippet_php' => fn() => self::execute_snippet_php( $action_data['snippet_php'], $event_data ),
        ));
    
        return isset( $actions[ $action_data['action'] ] ) ? $actions[ $action_data['action'] ]() : false;
    }
    
    
    /**
     * Processes a conditional action within a workflow
     *
     * @since 1.1.0
     * @version 1.2.0
     * @param array $action | Condition action data
     * @param int $post_id | Workflow post ID
     * @param array $payload | Payload context data
     * @return bool Returns true if the action is processed successfully
     */
    public static function process_condition_action( $action, $post_id, $payload ) {
        $action_data = $action['data'] ?? array();

        // Ensure that condition content exists
        if ( empty( $action_data['condition_content'] ) ) {
            return false;
        }

        // Extract condition details
        $get_condition = $action_data['condition_content']['condition'] ?? '';
        $condition_type = $action_data['condition_content']['type'] ?? '';
        $condition_value = $action_data['condition_content']['value'] ?? '';

        // get meta key for user meta condition
        if ( $get_condition === 'user_meta' ) {
            $payload['condition_content']['meta_key'] = $action_data['condition_content']['meta_key'] ?? '';
        }

        // Retrieve the comparison value based on the event data
        $compare_value = Conditions::get_compare_value( $get_condition, $payload );

        // Check if the condition is met
        $condition_met = Conditions::check_condition( $condition_type, $compare_value, $condition_value );

        // Determine the next actions based on whether the condition is met
        $next_actions = $condition_met ? ( $action['children']['action_true'] ?? array() ) : ( $action['children']['action_false'] ?? array() );

        if ( JOINOTIFY_DEV_MODE ) {
            error_log( "Processing condition action: " . print_r( $action, true ) );
            error_log( "Condition content: " . print_r( $action_data['condition_content'], true ) );
            error_log( "Condition type: " . print_r( $condition_type, true ) );
            error_log( "Condition value: " . print_r( $condition_value, true ) );
            error_log( "Compare value: " . print_r( $compare_value, true ) );
            error_log( "Condition met: " . print_r( $condition_met, true ) );
            error_log( "Next actions: " . print_r( $next_actions, true ) );
        }

        // Process the resulting actions based on the condition outcome
        foreach ( $next_actions as $child_action ) {
            self::handle_action( $child_action, $post_id, $payload );
        }

        return true;
    }


    /**
     * Process scheduled actions
     * 
     * @since 1.0.0
     * @version 1.1.0
     * @param int $post_id | Workflow ID
     * @param array $payload | Payload data
     * @param array $action_data | Action data
     * @return void
     */
    public static function process_scheduled_action( $post_id, $payload, $action_data ) {
        if ( $payload['integration'] === 'woocommerce' ) {
            $state = get_post_meta( $post_id, 'joinotify_workflow_state_' . $payload['order_id'], true );
        }
    
        if ( ! is_array( $state ) ) {
            $state = array(
                'processed_actions' => array(),
                'pending_actions' => array(),
            );
        }
    
        // get workflow content
        $workflow_content = get_post_meta( $post_id, 'joinotify_workflow_content', true );
    
        // stop process if workflow content is empty
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
    
            // check if action is time delay
            if ( $action['data']['action'] === 'time_delay' ) {
                Schedule::schedule_actions( $post_id, $payload, $action['data']['delay_timestamp'], $action );

                break;
            }
    
            if ( self::handle_action( $action, $post_id, $payload ) ) {
                // Mark as processed
                $state['processed_actions'][] = $action_id;
    
                if ( $payload['integration'] === 'woocommerce' ) {
                    update_post_meta( $post_id, 'joinotify_workflow_state_' . $payload['order_id'], $state );
                }
            }
        }
    }


    /**
     * Send message text on WhatsApp
     *
     * @since 1.0.0
     * @version 1.2.0
     * @param array $action_data | Action data
     * @param array $payload | Payload data
     * @return void
     */
    public static function send_whatsapp_message_text( $action_data, $payload ) {
        $sender = $action_data['sender'];
        $receiver = joinotify_prepare_receiver( $action_data['receiver'], $payload );
        $message = joinotify_prepare_message( $action_data['message'], $payload );

        // send message
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
     * @version 1.2.0
     * @param array $action_data | Action data
     * @param array $payload | Payload data
     * @return void
     */
    public static function send_whatsapp_message_media( $action_data, $payload ) {
        $sender = $action_data['sender'];
        $receiver = joinotify_prepare_receiver( $action_data['receiver'], $payload );
        $media_type = $action_data['media_type'];
        $media = $action_data['media_url'];

        // send message
        $response = Controller::send_message_media( $sender, $receiver, $media_type, $media );

        if ( JOINOTIFY_DEBUG_MODE ) {
            if ( 201 === $response ) {
                Logger::register_log( "Message sent successfully to: $receiver" );
            } else {
                Logger::register_log( "Failed to send message. Response: " . print_r( $response, true ), 'ERROR' );
            }
        }
    }


    /**
     * Execute a PHP snippet from a workflow action
     *
     * @since 1.1.0
     * @param string $snippet_php | PHP Code to execute
     * @param array $payload | Payload data
     * @return bool Returns true if executed successfully
     */
    public static function execute_snippet_php( $snippet_php, $payload ) {
        if ( empty( $snippet_php ) ) {
            Logger::register_log( 'Empty PHP snippet, skipping execution.', 'WARNING' );

            return false;
        }

        // remove the `<?php` tag if it exists
        $snippet_php = preg_replace( '/^\s*<\?php\s*/', '', $snippet_php );

        // block execution of dangerous functions
        $blocked_functions = array( 'exec', 'shell_exec', 'system', 'passthru', 'proc_open', 'eval', 'popen', 'dl', 'file_get_contents' );

        foreach ( $blocked_functions as $function ) {
            if ( stripos( $snippet_php, $function . '(' ) !== false ) {
                Logger::register_log( "Attempt to execute blocked function: $function", 'ERROR' );

                return false;
            }
        }

        // register log before execution for debugging
        if ( JOINOTIFY_DEBUG_MODE ) {
            Logger::register_log( "Running PHP Snippet: \n" . $snippet_php );
        }

        try {
            // create a safe execution environment
            ob_start();

            // execute the snippet
            eval( $snippet_php );
            
            // get the output buffer contents
            $output = ob_get_clean();

            // result execution log
            if ( JOINOTIFY_DEBUG_MODE ) {
                Logger::register_log( "PHP Snippet result: \n" . print_r( $output, true ) );
            }

            return true;
        } catch ( \Throwable $e ) {
            Logger::register_log( "Error executing PHP snippet: " . $e->getMessage(), 'ERROR' );

            return false;
        }
    }


    /**
     * Execute create WooCommerce coupon action
     * 
     * @since 1.1.0
     * @param array $action_data | Action data
     * @param array $payload | Payload data
     * @return void
     */
    public static function execute_wc_coupon_action( $action_data, $payload ) {
        $create_coupon = Woocommerce::generate_wc_coupon( $action_data['settings'] );
        $payload['settings'] = $action_data['settings'];
        $payload['settings']['coupon_code'] = $create_coupon['coupon_code'];

        // send message
        $send_message = self::send_whatsapp_message_text( $action_data['settings']['message'], $payload );

        if ( JOINOTIFY_DEBUG_MODE ) {
            if ( $create_coupon['coupon_id'] > 0 ) {
                Logger::register_log( "Coupon created successfully." );
            } else {
                Logger::register_log( "Failed to create coupon.", 'ERROR' );
            }
        }
    }


    /**
     * This will fire at the very end of a (successful) form entry on WPForms
     *
     * @since 1.1.0
     * @version 1.2.0
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

        self::process_workflows( $payload );
    }


    /**
     * Fires when PayPal payment status notifies the site
     *
     * @since 1.1.0
     * @version 1.2.0
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

        self::process_workflows( $payload );
    }

    
    /**
     * Process workflow when subscription is created
     * 
     * @since 1.2.0
     * @param object|WC_Subscription $subscription | A WC_Subscription instance representing the subscription just created on checkout
     * @param object|WC_Order $order | A WC_Order instance representing the order for which subscriptions have been created
     * @param object|WC_Cart $recurring_cart | A WC_Cart instance representing the cart which stores the data used for creating this subscription
     */
    public function process_workflow_subscription_created( $subscription, $order, $recurring_cart ) {
        $payload = array(
            'type' => 'trigger',
            'hook' => 'woocommerce_checkout_subscription_created',
            'integration' => 'woocommerce',
            'subscription' => $subscription,
            'subscription_id' => $subscription->get_id(),
            'order' => $order,
            'order_id' => $order->get_id(),
            'recurring_cart' => $recurring_cart,
        );

        self::process_workflows( $payload );
    }


    /**
     * Process workflow when subscription is activated
     * 
     * @since 1.2.0
     * @param object|WC_Subscription $subscription | A WC_Subscription instance representing the subscription just created on checkout
     * @param string $new_status | The new status of the subscription
     * @param string $old_status | The old status of the subscription
     * @return void
     */
    public function process_workflow_subscription_status_active( $subscription, $new_status, $old_status ) {
        $payload = array(
            'type' => 'trigger',
            'hook' => 'woocommerce_subscription_status_active',
            'integration' => 'woocommerce',
            'subscription' => $subscription,
            'subscription_id' => $subscription->get_id(),
            'new_status' => $new_status,
            'old_status' => $old_status,
        );

        self::process_workflows( $payload );
    }


    /**
     * Process workflow when subscription payment is complete
     * 
     * @since 1.2.0
     * @param object|WC_Subscription $subscription | A WC_Subscription instance representing the subscription just created on checkout
     * @return void
     */
    public function process_workflow_subscription_payment_complete( $subscription ) {
        $payload = array(
            'type' => 'trigger',
            'hook' => 'woocommerce_subscription_payment_complete',
            'integration' => 'woocommerce',
            'subscription' => $subscription,
            'subscription_id' => $subscription->get_id(),
        );

        self::process_workflows( $payload );
    }


    /**
     * Process workflow when subscription payment is failed
     * 
     * @since 1.2.0
     * @param object|WC_Subscription $subscription | A WC_Subscription instance representing the subscription just created on checkout
     * @param string $new_status | The new status of the subscription
     * @return void
     */
    public function process_workflow_subscription_payment_failed( $subscription, $new_status ) {
        $payload = array(
            'type' => 'trigger',
            'hook' => 'woocommerce_subscription_payment_failed',
            'integration' => 'woocommerce',
            'subscription' => $subscription,
            'subscription_id' => $subscription->get_id(),
            'new_status' => $new_status,
        );

        self::process_workflows( $payload );
    }


    /**
     * Process workflow when subscription status is expired
     * 
     * @since 1.2.0
     * @param object|WC_Subscription $subscription | A WC_Subscription instance representing the subscription just created on checkout
     * @return void
     */
    public function process_workflow_subscription_status_expired( $subscription ) {
        $payload = array(
            'type' => 'trigger',
            'hook' => 'woocommerce_subscription_status_expired',
            'integration' => 'woocommerce',
            'subscription' => $subscription,
            'subscription_id' => $subscription->get_id(),
        );

        self::process_workflows( $payload );
    }


    /**
     * Process workflow when subscription status is cancelled
     * 
     * @since 1.2.0
     * @param object|WC_Subscription $subscription | A WC_Subscription instance representing the subscription just created on checkout
     * @return void
     */
    public function process_workflow_subscription_status_cancelled( $subscription ) {
        $payload = array(
            'type' => 'trigger',
            'hook' => 'woocommerce_subscription_status_cancelled',
            'integration' => 'woocommerce',
            'subscription' => $subscription,
            'subscription_id' => $subscription->get_id(),
        );

        self::process_workflows( $payload );
    }
}