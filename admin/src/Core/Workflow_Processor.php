<?php

namespace MeuMouse\Joinotify\Core;

use MeuMouse\Joinotify\Admin\Admin;
use MeuMouse\Joinotify\Api\Controller;
use MeuMouse\Joinotify\Cron\Schedule;
use MeuMouse\Joinotify\Validations\Conditions;
use MeuMouse\Joinotify\Integrations\Woocommerce;
use MeuMouse\Joinotify\AI\AI_Manager;
use MeuMouse\Joinotify\AI\AI_Request;

// Exit if accessed directly.
defined('ABSPATH') || exit;

/**
 * Process workflow content and send messages on fire hooks
 * 
 * @since 1.0.0
 * @version 1.4.7
 * @package MeuMouse\Joinotify\Core
 * @author MeuMouse.com
 */
class Workflow_Processor {

    /**
     * Whether a stop_funnel action has halted the current execution segment.
     *
     * Reset at the start of every workflow run and every scheduled segment, so
     * stopping one funnel never leaks into the next workflow processed on the
     * same request.
     *
     * @since 2.0.0
     * @var bool
     */
    private static $funnel_stopped = false;


    /**
     * Flag the current execution segment as stopped.
     *
     * Used as the handler for the `stop_funnel` action so that no subsequent
     * action in the funnel (linear, branch or scheduled) is dispatched.
     *
     * @since 2.0.0
     * @return bool
     */
    public static function stop_funnel() {
        self::$funnel_stopped = true;

        return true;
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
     * Process workflow for each called hook
     * 
     * @since 1.0.0
     * @version 1.4.7
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

        if ( defined('JOINOTIFY_DEBUG_MODE') && JOINOTIFY_DEBUG_MODE ) {
            Logger::register_log( 'Function process_workflows() fired' );
            Logger::register_log( 'hook: ' . print_r( $hook, true ) );
            Logger::register_log( 'payload: ' . print_r( $payload, true ) );
        }

        if ( empty( $workflows ) ) {
            return;
        }

        // loop through workflows
        foreach ( $workflows as $workflow ) {
            $workflow_content = Helpers::get_workflow_content_meta( $workflow->ID );

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
     * @version 1.4.7
     * @param array $workflow_content | Workflow content
     * @param int $post_id | Post ID
     * @param array $payload | Payload data
     * @return void
     */
    public static function process_workflow_content( $workflow_content, $post_id, $payload ) {
        // Start every workflow with a clean stop flag.
        self::$funnel_stopped = false;

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

        $state = null;
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

            if ( ! $order ) {
                return;
            }

            $order_status = str_replace( 'wc-', '', $order->get_status() ); // remove prefix "wc-"
            
            // check order status
            if ( isset( $payload['hook'] ) && $payload['hook'] === 'woocommerce_order_status_changed' ) {
                // remove prefix "wc-" fron workflow trigger settings
                $trigger_order_status = isset( $trigger_data['data']['settings']['order_status'] ) && is_scalar( $trigger_data['data']['settings']['order_status'] )
                    ? str_replace( 'wc-', '', (string) $trigger_data['data']['settings']['order_status'] )
                    : '';

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

        if ( $integration === 'elementor' ) {
            $trigger_form_id = $trigger_data['data']['settings']['form_id'] ?? '';

            if ( empty( $trigger_form_id ) || (string) $payload['id'] !== (string) $trigger_form_id ) {
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
            // A previous action requested the funnel to stop.
            if ( self::$funnel_stopped ) {
                break;
            }

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
     * @version 1.4.7
     * @param array $action | Workflow actions array
     * @param int $post_id | Post ID
     * @param array $payload | Payload data
     * @return bool
     */
    public static function handle_action( $action, $post_id, &$event_data ) {
        $action_data = $action['data'] ?? array();

        if ( empty( $action_data['action'] ) ) {
            return false;
        }

        // process time delay action before all the actions
        if ( $action_data['action'] === 'time_delay' ) {
            // schedule the Cron, with next_actions array and return bool
            return Schedule::schedule_actions( $post_id, $event_data, $action_data['delay_timestamp'], $action );
        }

        // AI smart variable: generate a value and store it on the payload (by reference) so the
        // following actions in this segment can reference it through {{ ai:NAME }}. Handled before
        // the closure map because it must mutate the shared payload, not a captured copy.
        if ( $action_data['action'] === 'dynamic_placeholder' ) {
            return self::execute_dynamic_placeholder( $action_data, $event_data );
        }

        /**
         * Filter for enqueue function callback for each action
         *
         * The map is keyed by action slug; each value is a callable returning bool. Third parties
         * can register a handler for a custom action slug here. The action item, workflow post ID
         * and the runtime event payload are passed as extra arguments so custom closures can run
         * with real data (see Api\Extensions::register_action_handler()).
         *
         * @since 1.0.0
         * @version 1.4.7
         * @param array $actions    Map of action_slug => callable.
         * @param array $action     Full action item ({id,type,data,children}).
         * @param int   $post_id    Workflow post ID.
         * @param array $event_data Runtime trigger payload.
         */
        $actions = apply_filters( 'Joinotify/Workflow_Processor/Handle_Actions', array(
            'condition' => fn() => self::process_condition_action( $action, $post_id, $event_data ),
            'send_whatsapp_message_text' => fn() => self::send_whatsapp_message_text( $action_data, $event_data, $post_id ),
            'send_whatsapp_message_media' => fn() => self::send_whatsapp_message_media( $action_data, $event_data, $post_id ),
            'send_whatsapp_ai_message' => fn() => self::send_whatsapp_ai_message( $action_data, $event_data, $post_id ),
            'create_coupon' => fn() => self::execute_wc_coupon_action( $action_data, $event_data ),
            'snippet_php' => fn() => self::execute_snippet_php( $action_data['snippet_php'], $event_data ),
            'stop_funnel' => fn() => self::stop_funnel(),
        //  'dynamic_placeholder' => fn() => self::execute_dynamic_placeholder( $action_data, $event_data ),
        ), $action, $post_id, $event_data );

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
     * @version 1.4.7
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
            $condition_value = isset( $action_data['condition_content']['value'] ) && is_scalar( $action_data['condition_content']['value'] )
                ? str_replace( 'wc-', '', (string) $action_data['condition_content']['value'] )
                : '';
        } else {
            $condition_value = isset( $action_data['condition_content']['value'] ) && is_scalar( $action_data['condition_content']['value'] )
                ? (string) $action_data['condition_content']['value']
                : '';
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

            // Stop dispatching this branch (and the outer funnel) on stop_funnel.
            if ( self::$funnel_stopped ) {
                break;
            }
        }

        return true;
    }


    /**
     * Process scheduled actions
     * 
     * @since 1.0.0
     * @version 1.4.7
     * @param int $post_id | Workflow ID
     * @param array $payload | Payload data
     * @param array $action_data | Action data
     * @return void
     */
    public static function process_scheduled_action( $post_id, $payload, $action_data ) {
        // Start every scheduled segment with a clean stop flag.
        self::$funnel_stopped = false;

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
            // A previous action requested the funnel to stop.
            if ( self::$funnel_stopped ) {
                break;
            }

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
     * @version 1.4.7
     * @param array $action_data | Action data
     * @param array $payload | Payload data
     * @return void
     */
    public static function send_whatsapp_message_text( $action_data, $payload, $post_id = 0 ) {
        $sender = $action_data['sender'];
        $receiver = joinotify_prepare_receiver( $action_data['receiver'], $payload );
        $message = joinotify_prepare_message( $action_data['message'], $payload );

        // tag the dispatch origin for the message history
        Message_History::set_context( array(
            'source' => 'workflow',
            'workflow_id' => $post_id,
        ));

        // send message
        $response = Controller::send_message_text( $sender, $receiver, $message );

        Message_History::clear_context();

        if ( 201 !== $response ) {
            // check connection state and notify user if disconnected
            Controller::get_connection_state( $sender );
        }
        
        if ( defined('JOINOTIFY_DEBUG_MODE') && JOINOTIFY_DEBUG_MODE ) {
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
     * @version 1.4.7
     * @param array $action_data | Action data
     * @param array $payload | Payload data
     * @return void
     */
    public static function send_whatsapp_message_media( $action_data, $payload, $post_id = 0 ) {
        $sender = $action_data['sender'];
        $receiver = joinotify_prepare_receiver( $action_data['receiver'], $payload );
        $media_type = $action_data['media_type'];
        $media = $action_data['media_url'];
        $caption = joinotify_prepare_message( $action_data['caption'] ?? '', $payload );

        // tag the dispatch origin for the message history
        Message_History::set_context( array(
            'source' => 'workflow',
            'workflow_id' => $post_id,
        ));

        // send message
        $response = Controller::send_message_media( $sender, $receiver, $media_type, $media, $caption );

        Message_History::clear_context();

        if ( 201 !== $response ) {
            // check connection state and notify user if disconnected
            Controller::get_connection_state( $sender );
        }

        if ( defined('JOINOTIFY_DEBUG_MODE') && JOINOTIFY_DEBUG_MODE ) {
            if ( 201 === $response ) {
                Logger::register_log( "Message sent successfully to: $receiver" );
            } else {
                Logger::register_log( "Failed to send message. Response: " . print_r( $response, true ), 'ERROR' );
            }
        }
    }


    /**
     * Generate a message with AI at trigger time and send it via WhatsApp.
     *
     * The prompt and system message support placeholders, so the trigger
     * context (customer name, order data, etc.) is injected before generation.
     *
     * @since 2.0.0
     * @param array $action_data | Action data
     * @param array $payload | Payload data
     * @param int $post_id | Workflow post ID
     * @return void
     */
    public static function send_whatsapp_ai_message( $action_data, $payload, $post_id = 0 ) {
        $sender = $action_data['sender'] ?? '';
        $receiver = joinotify_prepare_receiver( $action_data['receiver'] ?? '', $payload );

        // resolve placeholders so the trigger context is injected into the prompt
        $prompt = joinotify_prepare_message( $action_data['ai_prompt'] ?? '', $payload );
        $system = self::build_ai_system_message( $action_data, $payload );

        if ( '' === trim( $prompt ) ) {
            Logger::register_log( 'Skipping AI WhatsApp message: empty prompt.', 'WARNING' );

            return;
        }

        $temperature = isset( $action_data['ai_temperature'] ) && is_numeric( $action_data['ai_temperature'] )
            ? (float) $action_data['ai_temperature']
            : null;

        // generate the message text with the active AI provider
        $ai_response = AI_Manager::generate( new AI_Request( array(
            'system' => $system,
            'prompt' => $prompt,
            'model' => $action_data['ai_model'] ?? '',
            'temperature' => $temperature,
            'context' => array(
                'intent' => 'whatsapp_message',
                'workflow_id' => $post_id,
            ),
        )));

        if ( ! $ai_response->is_successful() ) {
            $error = $ai_response->get_error();
            $error_message = $error ? $error->get_error_message() : 'unknown error';

            Logger::register_log( "AI WhatsApp message generation failed: $error_message", 'ERROR' );

            return;
        }

        $message = $ai_response->get_text();

        // tag the dispatch origin for the message history
        Message_History::set_context( array(
            'source' => 'workflow',
            'workflow_id' => $post_id,
        ));

        // send the generated message
        $response = Controller::send_message_text( $sender, $receiver, $message );

        Message_History::clear_context();

        if ( 201 !== $response ) {
            // check connection state and notify user if disconnected
            Controller::get_connection_state( $sender );
        }

        if ( defined('JOINOTIFY_DEBUG_MODE') && JOINOTIFY_DEBUG_MODE ) {
            if ( 201 === $response ) {
                Logger::register_log( "AI message sent successfully to: $receiver" );
            } else {
                Logger::register_log( "Failed to send AI message. Response: " . print_r( $response, true ), 'ERROR' );
            }
        }
    }


    /**
     * Build the system message for an AI message action.
     *
     * Combines the node's system instructions (with placeholders resolved) and
     * the tone/length directives selected on the node.
     *
     * @since 2.0.0
     * @param array $action_data | Action data
     * @param array $payload | Payload data
     * @return string
     */
    protected static function build_ai_system_message( $action_data, $payload ) {
        $system = joinotify_prepare_message( $action_data['ai_system'] ?? '', $payload );

        $tone_map = array(
            'formal' => __( 'Use a formal and professional tone.', 'joinotify' ),
            'casual' => __( 'Use a casual and relaxed tone.', 'joinotify' ),
            'friendly' => __( 'Use a warm and friendly tone.', 'joinotify' ),
        );

        $length_map = array(
            'short' => __( 'Keep the message very short, one or two sentences.', 'joinotify' ),
            'medium' => __( 'Keep the message concise, around two to four sentences.', 'joinotify' ),
            'long' => __( 'You may write a longer, detailed message.', 'joinotify' ),
        );

        $tone = isset( $action_data['ai_tone'] ) ? sanitize_key( (string) $action_data['ai_tone'] ) : '';
        $length = isset( $action_data['ai_length'] ) ? sanitize_key( (string) $action_data['ai_length'] ) : '';

        $directives = array();

        if ( isset( $tone_map[ $tone ] ) ) {
            $directives[] = $tone_map[ $tone ];
        }

        if ( isset( $length_map[ $length ] ) ) {
            $directives[] = $length_map[ $length ];
        }

        // always produce plain WhatsApp-ready text
        $directives[] = __( 'Write a WhatsApp message in plain text. Reply with the message content only, without any preamble.', 'joinotify' );

        $parts = array_filter( array_merge( array( $system ), $directives ), static function( $part ) {
            return '' !== trim( (string) $part );
        });

        return implode( "\n\n", $parts );
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
        if ( defined('JOINOTIFY_DEBUG_MODE') && JOINOTIFY_DEBUG_MODE ) {
            Logger::register_log( "Running PHP Snippet: \n" . $snippet_php );
        }

        try {
            // create a safe execution environment
            ob_start();

            // execute the snippet
            eval( $snippet_php ); // phpcs:ignore WordPress.PHP.DiscouragedPHPFunctions.eval
            
            // get the output buffer contents
            $output = ob_get_clean();

            // result execution log
            if ( defined('JOINOTIFY_DEBUG_MODE') && JOINOTIFY_DEBUG_MODE ) {
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

        if ( defined('JOINOTIFY_DEBUG_MODE') && JOINOTIFY_DEBUG_MODE ) {
            if ( $create_coupon['coupon_id'] > 0 ) {
                Logger::register_log( "Coupon created successfully." );
            } else {
                Logger::register_log( "Failed to create coupon.", 'ERROR' );
            }
        }
    }


    /**
     * Execute the AI smart variable action.
     *
     * Generates a named value from a system message + prompt and stores it on
     * the payload under "ai_vars" (by reference), so later actions can reference
     * it with the {{ ai:NAME }} placeholder. The value travels with the payload,
     * so it survives time delays (the payload is serialized into the cron event).
     *
     * @since 2.0.0
     * @param array $action_data | Action data
     * @param array $payload | Payload data (by reference)
     * @return bool
     */
    public static function execute_dynamic_placeholder( $action_data, &$payload ) {
        $name = isset( $action_data['var_name'] ) ? sanitize_key( (string) $action_data['var_name'] ) : '';

        if ( '' === $name ) {
            Logger::register_log( 'Skipping AI variable: missing variable name.', 'WARNING' );

            return false;
        }

        $prompt = joinotify_prepare_message( $action_data['ai_prompt'] ?? '', $payload );
        $system = joinotify_prepare_message( $action_data['ai_system'] ?? '', $payload );

        if ( '' === trim( $prompt ) ) {
            Logger::register_log( "Skipping AI variable {$name}: empty prompt.", 'WARNING' );

            return false;
        }

        $temperature = isset( $action_data['ai_temperature'] ) && is_numeric( $action_data['ai_temperature'] )
            ? (float) $action_data['ai_temperature']
            : null;

        $response = AI_Manager::generate( new AI_Request( array(
            'system' => $system,
            'prompt' => $prompt,
            'model' => $action_data['ai_model'] ?? '',
            'temperature' => $temperature,
            'context' => array(
                'intent' => 'smart_variable',
                'variable' => $name,
            ),
        )));

        if ( ! $response->is_successful() ) {
            $error = $response->get_error();

            Logger::register_log(
                "AI variable {$name} generation failed: " . ( $error ? $error->get_error_message() : 'unknown error' ),
                'ERROR'
            );

            return false;
        }

        if ( ! isset( $payload['ai_vars'] ) || ! is_array( $payload['ai_vars'] ) ) {
            $payload['ai_vars'] = array();
        }

        $payload['ai_vars'][ $name ] = $response->get_text();

        return true;
    }
}
