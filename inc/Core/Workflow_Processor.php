<?php

namespace MeuMouse\Joinotify\Core;

use MeuMouse\Joinotify\Admin\Admin;
use MeuMouse\Joinotify\API\Controller;
use MeuMouse\Joinotify\Cron\Schedule;
use MeuMouse\Joinotify\Validations\Conditions;
use MeuMouse\Joinotify\Integrations\Woocommerce;

// Exit if accessed directly.
defined('ABSPATH') || exit;

/**
 * Process workflow content and send messages on fire hooks
 * 
 * @since 1.0.0
 * @version 1.4.0
 * @package MeuMouse.com
 */
class Workflow_Processor {

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
     * @version 1.4.0
     * @param array $workflow_content | Workflow content
     * @param int $post_id | Post ID
     * @param array $payload | Payload data
     * @return void
     */
    public static function process_workflow_content( $workflow_content, $post_id, $payload ) {
        if ( JOINOTIFY_DEV_MODE ) {
            error_log( 'Function process_workflow_content() fired' );
            error_log( 'Param $workflow_content: ' . print_r( $workflow_content, true ) );
            error_log( 'Param $post_id: ' . print_r( $post_id, true ) );
            error_log( 'Param $payload: ' . print_r( $payload, true ) );
        }

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
        $integration = $payload['integration'] ?? '';

        // get trigger data
        $trigger_data = array_filter( $workflow_content, function ( $item ) {
            return isset( $item['type'] ) && $item['type'] === 'trigger';
        });

        // get first array item
        $trigger_data = reset( $trigger_data );

        // check restrictions for woocommerce integration
        if ( $integration === 'woocommerce' ) {
            if ( Admin::get_setting('enable_ignore_processed_actions') === 'yes' ) {
                $state = get_post_meta( $post_id, 'joinotify_workflow_state_' . $payload['order_id'], true );
            }

            $order = wc_get_order( $payload['order_id'] );
            $order_status = str_replace( 'wc-', '', $order->get_status() ); // remove prefix "wc-"
            
            // check order status
            if ( isset( $payload['hook'] ) && $payload['hook'] === 'woocommerce_order_status_changed' ) {
                // remove prefix "wc-" fron workflow trigger settings
                $trigger_order_status = str_replace( 'wc-', '', $trigger_data['data']['settings']['order_status'] );

                // check order status only if is setted different of "none" => all statuses
                if ( $trigger_order_status !== 'none' && $trigger_order_status !== $order_status ) {
                    return;
                }
            }
        }
        
        // check restrictions for wpforms integration
        if ( $integration === 'wpforms' ) {
            // check wpforms form id
            if ( $payload['id'] !== absint( $trigger_data['data']['settings']['form_id'] ) ) {
                return;
            }
        }

        // check restrictions for wordpress integration
        if ( $integration === 'wordpress' ) {
            if ( isset( $payload['hook'], $payload['post_id'], $payload['post_status'], $trigger_data['data']['settings']['post_status'] ) 
                && ( $payload['hook'] === 'change_post_status' || $payload['hook'] === 'transition_post_status' ) 
                && get_post_type( $payload['post_id'] ) === 'post' 
            ) {
                $trigger_post_status = $trigger_data['data']['settings']['post_status'];

                if ( $trigger_post_status !== 'none' && $payload['post_status'] !== $trigger_post_status ) {
                    return;
                }
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
            $action_data = $action['data'] ?? array();

            // Ignore trigger or already processed actions for woocommerce hooks
            if ( $integration === 'woocommerce' && Admin::get_setting('enable_ignore_processed_actions') === 'yes' && in_array( $action_id, $state['processed_actions'], true ) ) {
                continue;
            }

            if ( $action_data['action'] === 'time_delay' ) {
                // Collect all actions after this delay
                $next_actions = array_slice( $state['pending_actions'], $index + 1 );
                $action['data']['next_actions'] = $next_actions;

                // Schedule the cron event with next_actions in payload
                Schedule::schedule_actions( $post_id, $payload, $action_data['delay_timestamp'], $action );

                // Mark this delay as processed and replace pending with next_actions
                $state['processed_actions'][] = $action_id;
                $state['pending_actions'] = $next_actions;

                break;
            }
    
            // For all non-delay actions, execute immediately
            if ( self::handle_action( $action, $post_id, $payload ) ) {
                // Mark as processed
                $state['processed_actions'][] = $action_id;

                // Remove from pending and reindex
                unset( $state['pending_actions'][$index] );
                $state['pending_actions'] = array_values( $state['pending_actions'] );

                // Update state in the database for woocommerce hooks
                if ( $payload['integration'] === 'woocommerce' ) {
                    update_post_meta( $post_id, 'joinotify_workflow_state_' . $payload['order_id'], $state );
                }
            }
        }
    }


    /**
     * Handle with workflow actions
     * 
     * @since 1.0.0
     * @version 1.3.1
     * @param array $action | Workflow actions array
     * @param int $post_id | Post ID
     * @param array $payload | Payload data
     * @return bool
     */
    public static function handle_action( $action, $post_id, $event_data ) {
        $action_data = $action['data'] ?? array();

        if ( empty( $action_data['action'] ) ) {
            return false;
        }

        // process time delay action before all the actions 
        if ( $action_data['action'] === 'time_delay' ) {
            // schedule the Cron, with next_actions array and return bool
            return Schedule::schedule_actions( $post_id, $event_data, $action_data['delay_timestamp'], $action );
        }

        /**
         * Filter for enqueue function callback for each action
         * 
         * @since 1.0.0
         * @param array $actions
         */
        $actions = apply_filters( 'Joinotify/Workflow_Processor/Handle_Actions', array(
            'condition' => fn() => self::process_condition_action( $action, $post_id, $event_data ),
            'send_whatsapp_message_text' => fn() => self::send_whatsapp_message_text( $action_data, $event_data ),
            'send_whatsapp_message_media' => fn() => self::send_whatsapp_message_media( $action_data, $event_data ),
            'create_coupon' => fn() => self::execute_wc_coupon_action( $action_data, $event_data ),
            'snippet_php' => fn() => self::execute_snippet_php( $action_data['snippet_php'], $event_data ),
        //  'dynamic_placeholder' => fn() => self::execute_dynamic_placeholder( $action_data, $event_data ),
        ));

        // check if is action
        if ( ! isset( $actions[ $action_data['action'] ] ) ) {
            return false;
        }

        // return action function processed
        return $actions[ $action_data['action'] ]();
    }
    
    
    /**
     * Processes a conditional action within a workflow
     *
     * @since 1.1.0
     * @version 1.2.2
     * @param array $action | Condition action data
     * @param int $post_id | Workflow post ID
     * @param array $payload | Payload context data
     * @return bool Returns true if the action is processed successfully
     */
    public static function process_condition_action( $action, $post_id, $payload ) {
        if ( JOINOTIFY_DEV_MODE ) {
            error_log( "Processing condition action: " . print_r( $action, true ) );
            error_log( "Post ID: " . print_r( $post_id, true ) );
            error_log( "Payload: " . print_r( $payload, true ) );
        }

        $action_data = $action['data'] ?? array();

        // Ensure that condition content exists
        if ( empty( $action_data['condition_content'] ) ) {
            return false;
        }

        // Extract condition details
        $get_condition = $action_data['condition_content']['condition'] ?? '';
        $condition_type = $action_data['condition_content']['type'] ?? '';
        $payload['condition_content'] = $action_data['condition_content'] ?? array();
        $condition_value = '';

        if ( isset( $payload['hook'] ) && $payload['hook'] === 'woocommerce_order_status_changed' ) {
            $condition_value = str_replace( 'wc-', '', $action_data['condition_content']['value'] );
        } else {
            $condition_value = $action_data['condition_content']['value'] ?? '';
        }

        // get meta key for user meta condition
        if ( $get_condition === 'user_meta' ) {
            $payload['condition_content']['meta_key'] = $action_data['condition_content']['meta_key'] ?? '';
        } elseif ( $get_condition === 'field_value' ) {
            $payload['condition_content']['field_id'] = $action_data['condition_content']['field_id'] ?? '';
        }

        // Retrieve the comparison value based on the event data
        $compare_value = Conditions::get_compare_value( $get_condition, $payload );

        // Check if the condition is met
        $condition_met = Conditions::check_condition( $condition_type, $compare_value, $condition_value, $payload );

        // Determine the next actions based on whether the condition is met
        $next_actions = $condition_met ? ( $action['children']['action_true'] ?? array() ) : ( $action['children']['action_false'] ?? array() );

        if ( JOINOTIFY_DEV_MODE ) {
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
     * @version 1.3.1
     * @param int $post_id | Workflow ID
     * @param array $payload | Payload data
     * @param array $action_data | Action data
     * @return void
     */
    public static function process_scheduled_action( $post_id, $payload, $action_data ) {
        // get saved state for woocommerce hooks
        if ( $payload['integration'] === 'woocommerce' ) {
            $meta_key = 'joinotify_workflow_state_' . $payload['order_id'];
            $state = get_post_meta( $post_id, $meta_key, true );
        } else {
            $state = array();
        }

        if ( ! is_array( $state ) ) {
            $state = array(
                'processed_actions' => array(),
                'pending_actions' => array(),
            );
        }

        $action_id = $action_data['id'] ?? null;

        // if has processed, then stop
        if ( $action_id && in_array( $action_id, $state['processed_actions'], true ) ) {
            return;
        }

        // set this delay as processed
        if ( $action_id ) {
            $state['processed_actions'][] = $action_id;
        }

        // execute next actions
        $next_actions = $action_data['data']['next_actions'] ?? array();

        // loop for each next actions
        foreach ( $next_actions as $idx => $next_action ) {
            $next_id = $next_action['id'] ?? null;
            $next_type = $next_action['data']['action'] ?? '';

            if ( $next_type === 'time_delay' ) {
                // reschedule next delays, including only actions after this
                $remaining = array_slice( $next_actions, $idx + 1 );
                $next_action['data']['next_actions'] = $remaining;

                Schedule::schedule_actions( $post_id, $payload, $next_action['data']['delay_timestamp'], $next_action );

                // stop process loop, next actions be are processed on next cron event
                break;
            } else {
                // execute action right now
                self::handle_action( $next_action, $post_id, $payload );

                if ( $next_id ) {
                    $state['processed_actions'][] = $next_id;
                }
            }
        }

        // save state for woocommerce hooks
        if ( isset( $meta_key ) ) {
            update_post_meta( $post_id, $meta_key, $state );
        }
    }


    /**
     * Send message text on WhatsApp
     *
     * @since 1.0.0
     * @version 1.3.0
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

        if ( 201 !== $response ) {
            // check connection state and notify user if disconnected
            Controller::get_connection_state( $sender );
        }
        
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
     * @version 1.4.0
     * @param array $action_data | Action data
     * @param array $payload | Payload data
     * @return void
     */
    public static function send_whatsapp_message_media( $action_data, $payload ) {
        $sender = $action_data['sender'];
        $receiver = joinotify_prepare_receiver( $action_data['receiver'], $payload );
        $media_type = $action_data['media_type'];
        $media = $action_data['media_url'];
        $caption = joinotify_prepare_message( $action_data['caption'] ?? '', $payload );

        // send message
        $response = Controller::send_message_media( $sender, $receiver, $media_type, $media, $caption );

        if ( 201 !== $response ) {
            // check connection state and notify user if disconnected
            Controller::get_connection_state( $sender );
        }

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
     * Execute dynamic placeholder action
     * 
     * @since 1.3.0
     * @param array $action_data | Action data
     * @param array $payload | Payload data
     * @return void
     */
    public static function execute_dynamic_placeholder( $action_data, $payload ) {

    }
}