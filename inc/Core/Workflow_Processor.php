<?php

namespace MeuMouse\Joinotify\Core;

use MeuMouse\Joinotify\Admin\Admin;
use MeuMouse\Joinotify\Core\Logger;
use MeuMouse\Joinotify\API\Controller;
use MeuMouse\Joinotify\Cron\Schedule;
use MeuMouse\Joinotify\Builder\Placeholders;
use MeuMouse\Joinotify\Validations\Conditions;
use MeuMouse\Joinotify\Integrations\Woocommerce;

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
            Logger::register_log( 'hook: ' . print_r( $hook, true ) );
            Logger::register_log( 'content: ' . print_r( $context, true ) );
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

        // get integration
        $integration = $context['integration'];

        // get trigger data
        $trigger_data = array_filter( $workflow_content, function ( $item ) {
            return isset( $item['type'] ) && $item['type'] === 'trigger';
        });

        // get first array item
        $trigger_data = reset( $trigger_data );

        if ( $integration === 'woocommerce' ) {
            $state = get_post_meta( $post_id, 'joinotify_workflow_state_' . $context['order_id'], true );
        } elseif ( $integration === 'wpforms' ) {
            // check wpforms form id
            if ( $context['id'] !== absint( $trigger_data['data']['settings']['form_id'] ) ) {
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
            if ( self::handle_action( $action, $post_id, $context ) ) {
                $state['processed_actions'][] = $action_id;

                // remove action from pending actions
                unset( $state['pending_actions'][$index] );

                // reindex array
                $state['pending_actions'] = array_values( $state['pending_actions'] );

                // Update state in the database for woocommerce orders
                if ( $context['integration'] === 'woocommerce' ) {
                    update_post_meta( $post_id, 'joinotify_workflow_state_' . $context['order_id'], $state );
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

        // debug params
        if ( JOINOTIFY_DEBUG_MODE ) {
            Logger::register_log( 'Function handle_action() fired' );
            Logger::register_log( 'Param $action: ' . print_r( $action, true ) );
            Logger::register_log( 'Param $post_id: ' . print_r( $post_id, true ) );
            Logger::register_log( 'Param $context: ' . print_r( $context, true ) );
        }

        // check action type
        switch ( $action_data['action'] ) {
            case 'time_delay':
                Schedule::schedule_actions( $post_id, $context, $action_data['delay_timestamp'], $action );

                return true;
            case 'condition':
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
            case 'send_whatsapp_message_text':
                self::send_whatsapp_message_text( $action_data, $context );

                return true;
            case 'send_whatsapp_message_media':
                self::send_whatsapp_message_media( $action_data, $context );

                return true;
            case 'create_coupon':
                self::execute_wc_coupon_action( $action_data, $context );

                return true;

            case 'snippet_php':
                // return boolean
                return self::execute_snippet_php( $action_data['snippet_php'] );
        }

        return false;
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
                Schedule::schedule_actions( $post_id, $context, $action['data']['delay_timestamp'], $action );

                break;
            }
    
            if ( self::handle_action( $action, $post_id, $context ) ) {
                // Mark as processed
                $state['processed_actions'][] = $action_id;
    
                if ( $context['integration'] === 'woocommerce' ) {
                    update_post_meta( $post_id, 'joinotify_workflow_state_' . $context['order_id'], $state );
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

        error_log( 'send_whatsapp_message_text() $message: ' . print_r( $message, true ) );

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
        $media_type = $action_data['media_type'];
        $media = $action_data['media_url'];
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
     * @return bool Returns true if executed successfully
     */
    public static function execute_snippet_php( $snippet_php ) {
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
            eval( $snippet_php ); // execute the snippet
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
     * @param array $context | Context data
     * @return void
     */
    public static function execute_wc_coupon_action( $action_data, $context ) {
        $create_coupon = Woocommerce::generate_wc_coupon( $action_data['settings'] );

        if ( JOINOTIFY_DEBUG_MODE ) {
            if ( $create_coupon > 0 ) {
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
     * @param array $fields | Sanitized entry field values/properties
     * @param array $entry | Original $_POST global
     * @param array $form_data | Form data and settings
     * @param int $entry_id | Entry ID Will return 0 if entry storage is disabled or using WPForms Lite
     * @return void
     * 
     * @link  https://wpforms.com/developers/wpforms_process_complete/
     */
    public function process_workflow_wpforms_form( $fields, $entry, $form_data, $entry_id ) {
        $context = array(
            'type' => 'trigger',
            'integration' => 'wpforms',
            'id' => absint( $form_data['id'] ),
            'fields' => $fields,
            'entry' => $entry,
            'form_data' => $form_data,
            'entry_id' => $entry_id,
        );

        self::process_workflows( 'wpforms_process_complete', $context );
    }


    /**
     * Fires when PayPal payment status notifies the site
     *
     * @since 1.1.0
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

        $context = array(
            'type' => 'trigger',
            'integration' => 'wpforms',
            'id' => absint( $form_data['id'] ),
            'fields' => $fields,
            'entry' => $entry,
            'form_data' => $form_data,
            'entry_id' => $entry_id,
        );

        self::process_workflows( 'wpforms_paypal_standard_process_complete', $context );
    }
}