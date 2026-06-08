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
     * Returns published posts whose trigger fires on a specific hook.
     *
     * Primary lookup uses the dedicated, indexed meta key `_joinotify_trigger_hook`
     * written on save (see Admin\Builder\Registry). For workflows that have not yet
     * been migrated/saved since the upgrade, a `LIKE` fallback over the serialized
     * content is kept so no live workflow is missed. The migration on
     * `Joinotify/Upgraded` backfills the index, after which only the exact match runs.
     *
     * @since 1.0.0
     * @version 2.0.0
     * @param string $hook_name | Name of the hook to search for
     * @return array | List of posts that have the specified hook
     */
    public static function get_workflows_by_hook( $hook_name ) {
        $args = array(
            'post_type' => 'joinotify-workflow',
            'post_status' => 'publish',
            'numberposts' => -1,
            'meta_query' => array(
                'relation' => 'OR',
                // Fast path: exact match on the indexed trigger-hook meta.
                array(
                    'key' => '_joinotify_trigger_hook',
                    'value' => $hook_name,
                    'compare' => '=',
                ),
                // Legacy fallback: workflow not migrated yet (no index meta) -> content LIKE.
                array(
                    'relation' => 'AND',
                    array(
                        'key' => '_joinotify_trigger_hook',
                        'compare' => 'NOT EXISTS',
                    ),
                    array(
                        'key' => 'joinotify_workflow_content',
                        'value' => $hook_name,
                        'compare' => 'LIKE',
                    ),
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

        if ( ! is_array( $workflow_content ) ) {
            return;
        }

        // resolve the trigger node (guard against malformed/trigger-less content)
        $trigger_data = self::get_trigger_node( $workflow_content );

        if ( empty( $trigger_data ) ) {
            Logger::register_log( sprintf( 'Workflow %d skipped: no trigger node found.', $post_id ), 'WARNING' );

            return;
        }

        // only run when this trigger actually matches the runtime payload
        if ( ! self::matches_trigger( $trigger_data, $payload ) ) {
            return;
        }

        // top-level linear actions (siblings); conditions carry their own branches
        $workflow_actions = array_values( array_filter( $workflow_content, function ( $item ) {
            return isset( $item['type'] ) && $item['type'] !== 'trigger';
        }));

        // per-instance idempotency state (null = stateless; cron continuation handles resume)
        $state_key = self::get_state_meta_key( $payload );
        $state = self::load_state( $post_id, $state_key );

        // Always walk from the top; processed_actions skips leaves already executed
        // for the same instance, so re-firing a trigger never double-sends.
        $state['pending_actions'] = $workflow_actions;
        self::persist_state( $post_id, $state_key, $state );

        // run the flow through the unified walker
        self::run_segment( $workflow_actions, $post_id, $payload, $state, $state_key );
    }


    /**
     * Extract the (single) trigger node from a workflow content array.
     *
     * @since 2.0.0
     * @param array $workflow_content | Workflow content
     * @return array Trigger node, or empty array when absent.
     */
    protected static function get_trigger_node( $workflow_content ) {
        foreach ( $workflow_content as $item ) {
            if ( isset( $item['type'] ) && $item['type'] === 'trigger' ) {
                return $item;
            }
        }

        return array();
    }


    /**
     * Decide whether a workflow's trigger matches the runtime payload.
     *
     * The built-in integrations (WooCommerce/WPForms/WordPress/Elementor) declare
     * their matching rules here as the default. Third parties can refine or replace
     * the decision for their own triggers through the filter below, keeping the
     * runtime matching extensible instead of hardcoded per integration.
     *
     * @since 2.0.0
     * @param array $trigger_data | Trigger node ({id,type,data})
     * @param array $payload | Runtime trigger payload
     * @return bool
     */
    protected static function matches_trigger( $trigger_data, $payload ) {
        $integration = $payload['integration'] ?? '';
        $settings = $trigger_data['data']['settings'] ?? array();

        $trigger_hook = isset( $trigger_data['data']['trigger'] ) ? (string) $trigger_data['data']['trigger'] : '';
        $payload_hook = isset( $payload['hook'] ) ? (string) $payload['hook'] : '';

        // Authoritative gate: the workflow only runs when its trigger node matches
        // the fired hook. This makes the _joinotify_trigger_hook index a pure
        // optimization and prevents any content-LIKE false positive from dispatching.
        $match = ( '' !== $trigger_hook && $trigger_hook === $payload_hook );

        if ( $integration === 'woocommerce' ) {
            if ( isset( $payload['order_id'] ) ) {
                $order = wc_get_order( $payload['order_id'] );

                if ( ! $order ) {
                    $match = false;
                } elseif ( isset( $payload['hook'] ) && $payload['hook'] === 'woocommerce_order_status_changed' ) {
                    $order_status = str_replace( 'wc-', '', $order->get_status() );
                    $trigger_order_status = isset( $settings['order_status'] ) && is_scalar( $settings['order_status'] )
                        ? str_replace( 'wc-', '', (string) $settings['order_status'] )
                        : '';

                    // "none" => any status
                    if ( $trigger_order_status !== 'none' && $trigger_order_status !== $order_status ) {
                        $match = false;
                    }
                }
            }
        } elseif ( $integration === 'wpforms' ) {
            if ( ! isset( $payload['id'] ) || (int) $payload['id'] !== absint( $settings['form_id'] ?? 0 ) ) {
                $match = false;
            }
        } elseif ( $integration === 'wordpress' ) {
            if ( isset( $payload['hook'], $payload['post_id'], $payload['post_status'], $settings['post_status'] )
                && ( $payload['hook'] === 'change_post_status' || $payload['hook'] === 'transition_post_status' )
                && get_post_type( $payload['post_id'] ) === 'post'
            ) {
                $trigger_post_status = $settings['post_status'];

                if ( $trigger_post_status !== 'none' && $payload['post_status'] !== $trigger_post_status ) {
                    $match = false;
                }
            }
        } elseif ( $integration === 'elementor' ) {
            $trigger_form_id = $settings['form_id'] ?? '';

            if ( empty( $trigger_form_id ) || (string) ( $payload['id'] ?? '' ) !== (string) $trigger_form_id ) {
                $match = false;
            }
        }

        /**
         * Filter the trigger matching decision.
         *
         * Lets third-party integrations declare whether their registered trigger
         * should fire for the current payload, without editing the core matcher.
         *
         * @since 2.0.0
         * @param bool  $match        Current match decision from the built-in rules.
         * @param array $trigger_data Trigger node ({id,type,data}).
         * @param array $payload      Runtime trigger payload.
         */
        return (bool) apply_filters( 'Joinotify/Workflow_Processor/Trigger_Matches', $match, $trigger_data, $payload );
    }


    /**
     * Run a list of actions through the unified flow walker.
     *
     * This single engine powers both the initial trigger run and the cron resume.
     * It treats the flow as an ordered queue:
     *   - a `condition` node is evaluated and its chosen branch is prepended to the
     *     queue, so branch actions (and any nested conditions) run in place;
     *   - a `time_delay` node schedules the ENTIRE remaining queue as its
     *     continuation and stops the segment, so nothing after a delay is ever lost,
     *     even when the delay sits inside a condition branch;
     *   - any other (leaf) action is dispatched immediately via handle_action().
     *
     * @since 2.0.0
     * @param array  $queue      Ordered list of action nodes to process.
     * @param int    $post_id    Workflow post ID.
     * @param array  $payload    Runtime payload (passed by reference so dynamic
     *                           placeholders/AI variables propagate to later actions).
     * @param array  $state      Idempotency state (processed_actions/pending_actions), by ref.
     * @param string|null $state_key Post-meta key for persistence, or null when stateless.
     * @return void
     */
    protected static function run_segment( array $queue, $post_id, &$payload, array &$state, $state_key ) {
        while ( ! empty( $queue ) ) {
            // a previous action requested the funnel to stop
            if ( self::$funnel_stopped ) {
                break;
            }

            $node = array_shift( $queue );

            if ( ! is_array( $node ) ) {
                continue;
            }

            $node_id = $node['id'] ?? null;
            $node_data = $node['data'] ?? array();
            $action = $node_data['action'] ?? '';

            // skip leaves already executed for this instance (idempotency)
            if ( $state_key && $node_id && in_array( $node_id, $state['processed_actions'], true ) ) {
                continue;
            }

            // delay: schedule the remaining queue as continuation, then stop here
            if ( $action === 'time_delay' ) {
                $delay = Schedule::resolve_delay_seconds( $node_data );

                // the continuation is everything still queued after this delay
                $node['data']['next_actions'] = array_values( $queue );

                self::schedule_continuation( $post_id, $payload, $delay, $node, $state_key );

                // remember what is still pending; the delay node is marked processed
                // only when the continuation actually resumes (process_scheduled_action)
                $state['pending_actions'] = array_values( $queue );
                self::persist_state( $post_id, $state_key, $state );

                return;
            }

            // condition: evaluate and splice the chosen branch into the queue
            if ( $action === 'condition' ) {
                $branch = self::evaluate_condition( $node, $post_id, $payload );
                $queue = array_merge( array_values( $branch ), $queue );

                if ( $node_id ) {
                    $state['processed_actions'][] = $node_id;
                    self::persist_state( $post_id, $state_key, $state );
                }

                continue;
            }

            // leaf action: dispatch immediately
            if ( self::handle_action( $node, $post_id, $payload ) ) {
                if ( $node_id ) {
                    $state['processed_actions'][] = $node_id;
                    self::persist_state( $post_id, $state_key, $state );
                }
            }
        }
    }


    /**
     * Schedule a delayed continuation, guarding against an inactive WP-Cron.
     *
     * @since 2.0.0
     * @param int    $post_id    Workflow post ID.
     * @param array  $payload    Runtime payload (serialized into the cron event).
     * @param int    $delay      Delay in seconds from now.
     * @param array  $node       The time_delay node (carries data.next_actions).
     * @param string|null $state_key Instance state key, used to derive a stable cron key.
     * @return bool
     */
    protected static function schedule_continuation( $post_id, $payload, $delay, $node, $state_key ) {
        // Only warn when there is no reliable scheduler at all: Action Scheduler
        // absent AND WP-Cron inactive.
        if ( ! Schedule::is_action_scheduler_available() && ! Schedule::is_wp_cron_active() ) {
            Logger::register_log(
                sprintf( 'No active scheduler (Action Scheduler unavailable and WP-Cron inactive); scheduled action for workflow %d may not fire on time. Consider a real system cron.', $post_id ),
                'WARNING'
            );
        }

        // a stable key (instance + node) so re-scheduling the same continuation
        // replaces the previous event instead of duplicating it
        $unique_key = ( $state_key ?: 'run' ) . ':' . ( $node['id'] ?? uniqid( 'delay_', true ) );

        return Schedule::schedule_actions( $post_id, $payload, $delay, $node, $unique_key );
    }


    /**
     * Resolve the idempotency state meta key for a payload.
     *
     * Returns null for stateless runs (the cron continuation already carries the
     * remaining work, so resume never needs the DB). Currently persists state only
     * for the opt-in WooCommerce "ignore processed actions" feature, keyed by order.
     * Centralized here so coverage can be extended to other integrations with a
     * stable instance key without touching the engine.
     *
     * @since 2.0.0
     * @param array $payload | Runtime payload
     * @return string|null
     */
    protected static function get_state_meta_key( $payload ) {
        $integration = $payload['integration'] ?? '';

        if ( $integration === 'woocommerce'
            && ! empty( $payload['order_id'] )
            && Admin::get_setting( 'enable_ignore_processed_actions' ) === 'yes'
        ) {
            return 'joinotify_workflow_state_' . $payload['order_id'];
        }

        return null;
    }


    /**
     * Load (or initialize) the idempotency state for a workflow instance.
     *
     * @since 2.0.0
     * @param int $post_id | Workflow post ID
     * @param string|null $state_key | Meta key, or null for a fresh in-memory state
     * @return array
     */
    protected static function load_state( $post_id, $state_key ) {
        $default = array(
            'processed_actions' => array(),
            'pending_actions' => array(),
        );

        if ( ! $state_key ) {
            return $default;
        }

        $state = get_post_meta( $post_id, $state_key, true );

        if ( ! is_array( $state ) ) {
            return $default;
        }

        $state['processed_actions'] = isset( $state['processed_actions'] ) && is_array( $state['processed_actions'] ) ? $state['processed_actions'] : array();
        $state['pending_actions'] = isset( $state['pending_actions'] ) && is_array( $state['pending_actions'] ) ? $state['pending_actions'] : array();

        return $state;
    }


    /**
     * Persist the idempotency state when running with a state key.
     *
     * @since 2.0.0
     * @param int $post_id | Workflow post ID
     * @param string|null $state_key | Meta key, or null to skip persistence
     * @param array $state | State to persist
     * @return void
     */
    protected static function persist_state( $post_id, $state_key, $state ) {
        if ( $state_key ) {
            update_post_meta( $post_id, $state_key, $state );
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

        // Delays are normally owned by run_segment() (which captures the continuation).
        // This branch is a safety net for any direct/legacy call to handle_action():
        // it still schedules with a delay computed at runtime (never the stale,
        // save-time delay_timestamp), preserving any continuation already attached.
        if ( $action_data['action'] === 'time_delay' ) {
            $delay = Schedule::resolve_delay_seconds( $action_data );

            return Schedule::schedule_actions( $post_id, $event_data, $delay, $action );
        }

        // AI smart variable: generate a value and store it on the payload (by reference) so the
        // following actions in this segment can reference it through {{ ai:NAME }}. Handled before
        // the closure map because it must mutate the shared payload, not a captured copy.
        if ( $action_data['action'] === 'dynamic_placeholder' ) {
            return self::execute_dynamic_placeholder( $action_data, $event_data );
        }

        /**
         * Required configuration keys for each built-in action.
         *
         * If any of the listed keys is missing or empty in the action data, the action is
         * silently skipped at runtime (a warning is logged) instead of dispatching with an
         * incomplete configuration and risking a fatal error. Third parties can register
         * requirements for their custom action slugs through this filter.
         *
         * @since 2.0.0
         * @param array $required_config Map of action_slug => array of required data keys.
         * @param array $action          Full action item ({id,type,data,children}).
         * @param int   $post_id         Workflow post ID.
         * @param array $event_data      Runtime trigger payload.
         */
        $required_config = apply_filters( 'Joinotify/Workflow_Processor/Action_Required_Config', array(
            'send_whatsapp_message_text' => array( 'sender', 'receiver', 'message' ),
            'send_whatsapp_message_media' => array( 'sender', 'receiver', 'media_type', 'media_url' ),
            'create_coupon' => array( 'settings' ),
        ), $action, $post_id, $event_data );

        // skip the action when any required configuration is missing, avoiding runtime failures
        if ( isset( $required_config[ $action_data['action'] ] ) ) {
            foreach ( $required_config[ $action_data['action'] ] as $required_key ) {
                if ( self::is_config_value_empty( $action_data[ $required_key ] ?? null ) ) {
                    Logger::register_log( sprintf( 'Skipping action "%s": missing required configuration "%s".', $action_data['action'], $required_key ), 'WARNING' );

                    return false;
                }
            }
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
            'create_coupon' => fn() => self::execute_wc_coupon_action( $action_data, $event_data, $post_id ),
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
     * Check whether a configuration value should be treated as missing/empty.
     *
     * Handles the common runtime value shapes: null, empty/whitespace strings and
     * empty arrays all count as "not configured".
     *
     * @since 2.0.0
     * @param mixed $value | Configuration value to check
     * @return bool True when the value is considered empty.
     */
    protected static function is_config_value_empty( $value ) {
        if ( $value === null ) {
            return true;
        }

        if ( is_string( $value ) ) {
            return trim( $value ) === '';
        }

        if ( is_array( $value ) ) {
            return empty( $value );
        }

        return false;
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
        // Evaluate then run the chosen branch through the unified walker so that
        // delays/nested conditions inside the branch behave consistently. Kept as a
        // backward-compatible entry point; the main flow uses evaluate_condition()
        // directly and splices the branch into its own queue.
        $branch = self::evaluate_condition( $action, $post_id, $payload );

        $state = array(
            'processed_actions' => array(),
            'pending_actions' => array(),
        );

        self::run_segment( array_values( $branch ), $post_id, $payload, $state, null );

        return true;
    }


    /**
     * Evaluate a condition node and return the matching branch's action list.
     *
     * Pure decision: it never executes the branch. The walker prepends the returned
     * list onto its queue so branch actions run in place. Works on a local copy of
     * the payload for the comparison so condition metadata never leaks into the
     * payload seen by later actions.
     *
     * @since 2.0.0
     * @param array $action | Condition node ({id,type,data,children})
     * @param int $post_id | Workflow post ID
     * @param array $payload | Runtime payload (by value)
     * @return array Chosen branch (action_true or action_false), possibly empty.
     */
    protected static function evaluate_condition( $action, $post_id, $payload ) {
        if ( JOINOTIFY_DEV_MODE ) {
            error_log( "Processing condition action: " . print_r( $action, true ) );
            error_log( "Post ID: " . print_r( $post_id, true ) );
            error_log( "Payload: " . print_r( $payload, true ) );
        }

        $action_data = $action['data'] ?? array();

        // No condition configured: fall back to the "false" branch instead of
        // dropping the rest of the flow silently.
        if ( empty( $action_data['condition_content'] ) ) {
            return $action['children']['action_false'] ?? array();
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

        return is_array( $next_actions ) ? $next_actions : array();
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

        // Never resume a workflow that is no longer published (trashed/draft/
        // deleted): a pending delay must not fire for a disabled automation.
        if ( get_post_status( $post_id ) !== 'publish' ) {
            Logger::register_log( sprintf( 'Skipping scheduled action: workflow %d is not published.', $post_id ), 'WARNING' );

            return;
        }

        $state_key = self::get_state_meta_key( $payload );
        $state = self::load_state( $post_id, $state_key );

        $delay_id = $action_data['id'] ?? null;

        // Guard against a duplicate cron fire resuming the same continuation twice.
        if ( $state_key && $delay_id && in_array( $delay_id, $state['processed_actions'], true ) ) {
            return;
        }

        // The delay node is considered processed now that its continuation resumes.
        if ( $delay_id ) {
            $state['processed_actions'][] = $delay_id;
            self::persist_state( $post_id, $state_key, $state );
        }

        // Resume with the continuation captured at schedule time. The same unified
        // walker handles further delays (reschedules) and conditions consistently.
        $next_actions = $action_data['data']['next_actions'] ?? array();

        self::run_segment( array_values( $next_actions ), $post_id, $payload, $state, $state_key );
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

        /**
         * Master switch to allow/deny PHP snippet execution.
         *
         * Returning false here disables the eval()-based snippet action entirely,
         * letting security-conscious sites turn it off without code changes.
         *
         * @since 2.0.0
         * @param bool   $allow       Whether snippet execution is allowed.
         * @param string $snippet_php The snippet source.
         * @param array  $payload     Runtime payload.
         */
        if ( ! apply_filters( 'Joinotify/Snippet/Allow_Execution', true, $snippet_php, $payload ) ) {
            Logger::register_log( 'PHP snippet execution is disabled by filter.', 'WARNING' );

            return false;
        }

        // remove the `<?php` tag if it exists
        $snippet_php = preg_replace( '/^\s*<\?php\s*/', '', $snippet_php );

        /**
         * Dangerous functions blocked inside snippets (command execution, file I/O,
         * network sockets, dynamic invocation and code-eval vectors). The previous
         * list only blocked a handful and was trivially bypassable (e.g. fopen,
         * call_user_func, include were allowed).
         *
         * @since 2.0.0
         * @param array $blocked Function names to block.
         */
        $blocked_functions = apply_filters( 'Joinotify/Snippet/Blocked_Functions', array(
            // command execution
            'exec', 'shell_exec', 'system', 'passthru', 'proc_open', 'popen', 'proc_nice', 'pcntl_exec',
            // code evaluation / dynamic definition
            'eval', 'assert', 'create_function',
            // dynamic invocation (blocklist-bypass vectors)
            'call_user_func', 'call_user_func_array',
            // environment / module loading
            'dl', 'putenv',
            // filesystem
            'file_get_contents', 'file_put_contents', 'fopen', 'fwrite', 'fputs', 'fread',
            'unlink', 'rename', 'copy', 'rmdir', 'mkdir', 'chmod', 'chown', 'symlink', 'link',
            // network
            'fsockopen', 'pfsockopen', 'stream_socket_client', 'curl_exec', 'curl_multi_exec',
        ) );

        foreach ( $blocked_functions as $function ) {
            // match `name(` even with whitespace, ignoring case
            if ( preg_match( '/\b' . preg_quote( $function, '/' ) . '\s*\(/i', $snippet_php ) ) {
                Logger::register_log( "Attempt to execute blocked function: $function", 'ERROR' );

                return false;
            }
        }

        // block include/require language constructs (with or without parentheses)
        if ( preg_match( '/\b(include|require)(_once)?\b/i', $snippet_php ) ) {
            Logger::register_log( 'Attempt to use include/require in a PHP snippet.', 'ERROR' );

            return false;
        }

        // block backtick shell execution
        if ( strpos( $snippet_php, '`' ) !== false ) {
            Logger::register_log( 'Attempt to use shell execution (backticks) in a PHP snippet.', 'ERROR' );

            return false;
        }

        // block variable / dynamic function calls like $fn(...) which bypass the blocklist
        if ( preg_match( '/\$\w+\s*\(/', $snippet_php ) ) {
            Logger::register_log( 'Attempt to use a variable function call in a PHP snippet.', 'ERROR' );

            return false;
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
    public static function execute_wc_coupon_action( $action_data, $payload, $post_id = 0 ) {
        $settings = isset( $action_data['settings'] ) && is_array( $action_data['settings'] ) ? $action_data['settings'] : array();

        $create_coupon = Woocommerce::generate_wc_coupon( $settings );

        // Bail out cleanly when the coupon could not be created (missing data,
        // duplicate code, etc.) instead of treating a WP_Error as an array.
        if ( is_wp_error( $create_coupon ) ) {
            Logger::register_log( 'Failed to create coupon: ' . $create_coupon->get_error_message(), 'ERROR' );

            return false;
        }

        // expose the generated coupon details to the message placeholders
        $payload['settings'] = $settings;
        $payload['settings']['coupon_code'] = $create_coupon['coupon_code'] ?? '';

        // The message config is stored as {sender, receiver, message} under
        // settings.message, which is exactly the shape send_whatsapp_message_text()
        // expects as its action_data argument.
        $message = isset( $settings['message'] ) && is_array( $settings['message'] ) ? $settings['message'] : array();

        if ( self::is_config_value_empty( $message['sender'] ?? null ) || self::is_config_value_empty( $message['receiver'] ?? null ) ) {
            Logger::register_log( 'Coupon created but notification skipped: missing sender/receiver.', 'WARNING' );

            return true;
        }

        // pass the workflow post ID so the dispatch is tagged in the message history
        self::send_whatsapp_message_text( $message, $payload, $post_id );

        if ( defined('JOINOTIFY_DEBUG_MODE') && JOINOTIFY_DEBUG_MODE ) {
            $coupon_id = (int) ( $create_coupon['coupon_id'] ?? 0 );

            Logger::register_log( $coupon_id > 0 ? 'Coupon created successfully.' : 'Failed to create coupon.', $coupon_id > 0 ? 'INFO' : 'ERROR' );
        }

        return true;
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
