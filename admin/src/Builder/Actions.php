<?php

namespace MeuMouse\Joinotify\Builder;

// Exit if accessed directly.
defined('ABSPATH') || exit;

/**
 * This class handles with actions functions
 * 
 * @since 1.1.0
 * @version 1.4.7
 * @package MeuMouse\Joinotify\Builder
 * @author MeuMouse.com
 */
class Actions {

    /**
     * Get all actions
     * 
     * This function returns all actions for uses on connect triggers
     * 
     * @since 1.0.0
     * @version 1.4.7
     * @param string $context | Trigger context (eg: woocommerce)
     * @return array
     */
    public static function get_all_actions( $context = '' ) {
        $actions = apply_filters( 'Joinotify/Builder/Actions', array(
            array(
                'action' => 'time_delay',
                'title' => __( 'Delay', 'joinotify' ),
                'description' => __( 'Allows you to set a waiting time before the action is executed.', 'joinotify' ),
                'context' => array(), // available for all the contexts
                'category' => 'general',
                'icon' => '<svg class="icon icon-lg icon-dark" xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24"><path d="M12 2C6.486 2 2 6.486 2 12s4.486 10 10 10 10-4.486 10-10S17.514 2 12 2zm0 18c-4.411 0-8-3.589-8-8s3.589-8 8-8 8 3.589 8 8-3.589 8-8 8z"></path><path d="M13 7h-2v5.414l3.293 3.293 1.414-1.414L13 11.586z"></path></svg>',
                'external_icon' => false,
                'has_settings' => true,
                'priority' => 10,
                'is_expansible' => false,
            ),
            array(
                'action' => 'condition',
                'title' => __( 'Condition', 'joinotify' ),
                'description' => __( 'It allows you to define a condition for an action to be executed.', 'joinotify' ),
                'context' => array(), // available for all the contexts
                'category' => 'general',
                'icon' => '<svg class="icon icon-lg icon-dark condition" xmlns="http://www.w3.org/2000/svg" xmlns:xlink="http://www.w3.org/1999/xlink" version="1.1" width="512" height="512" x="0" y="0" viewBox="0 0 25 24" style="enable-background:new 0 0 512 512" xml:space="preserve"><g><clipPath id="a"><path d="M.602 0h24v24h-24z" fill="#000000" opacity="1" data-original="#000000"/></clipPath><g clip-path="url(#a)"><path fill="#000000" d="M24.564 16.811a.484.484 0 0 0-.115-.17l-2.994-2.994a.5.5 0 0 0-.707.707l2.145 2.145h-9.79a4.506 4.506 0 0 1-4.5-4.5c0-2.48 2.018-4.5 4.5-4.5h9.792l-2.147 2.147a.5.5 0 0 0 .707.708l3-3c.014-.014.02-.032.031-.046.03-.036.06-.073.077-.117.018-.043.022-.089.027-.134.003-.02.012-.038.012-.059 0-.047-.015-.089-.027-.132-.005-.018-.004-.038-.01-.056a.483.483 0 0 0-.116-.17l-2.994-2.994a.5.5 0 0 0-.707.707l2.145 2.145h-9.79a5.506 5.506 0 0 0-5.476 5H1.102a.5.5 0 0 0 0 1h6.525c.254 2.8 2.61 5 5.475 5h9.793l-2.147 2.147a.5.5 0 0 0 .707.708l3-3c.014-.014.02-.032.031-.046.03-.036.06-.073.077-.117.018-.043.022-.089.027-.134.003-.02.012-.037.012-.058 0-.047-.015-.089-.027-.132-.005-.018-.004-.038-.01-.055z" opacity="1" data-original="#000000"/></g></g></svg>',
                'external_icon' => false,
                'has_settings' => true,
                'priority' => 20,
                'is_expansible' => true,
            ),
            array(
                'action' => 'loop',
                'title' => __( 'Loop', 'joinotify' ),
                'description' => __( 'Iterates over a collection (order files, order items, or a list) and runs the actions inside it once per item.', 'joinotify' ),
                'context' => array(), // available for all the contexts
                'category' => 'general',
                'icon' => '<svg class="icon icon-lg icon-dark loop" xmlns="http://www.w3.org/2000/svg" width="24" height="24" fill="currentColor" viewBox="0 0 24 24" ><path d="M14 7 9 3v3H8c-3.31 0-6 2.69-6 6s2.69 6 6 6v-2c-2.21 0-4-1.79-4-4s1.79-4 4-4h1v3zm2-1v2c2.21 0 4 1.79 4 4s-1.79 4-4 4h-1v-3l-5 4 5 4v-3h1c3.31 0 6-2.69 6-6s-2.69-6-6-6"></path></svg>',
                'external_icon' => false,
                'has_settings' => true,
                'priority' => 25,
                'is_expansible' => true,
            ),
            array(
                'action' => 'stop_funnel',
                'title' => __( 'Stop automation', 'joinotify' ),
                'description' => __( 'No action will be taken upon reaching this point.', 'joinotify' ),
                'context' => array(), // available for all the contexts
                'category' => 'general',
                'icon' => '<svg class="icon icon-lg icon-dark" xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24"><path d="M12 2C6.486 2 2 6.486 2 12s4.486 10 10 10 10-4.486 10-10S17.514 2 12 2zm0 18c-4.411 0-8-3.589-8-8s3.589-8 8-8 8 3.589 8 8-3.589 8-8 8z"></path><path d="M9 9h6v6H9z"></path></svg>',
                'external_icon' => false,
                'has_settings' => false,
                'priority' => 30,
                'is_expansible' => false,
            ),
        ));

        // if empty context argument, return all the registered actions
        if ( empty( $context ) ) {
            return $actions;
        }

        // filter actions based on context
        $filtered_actions = array_filter( $actions, function( $action ) use ( $context ) {
            return empty( $action['context'] ) || in_array( $context, $action['context'] );
        });

        return array_values( $filtered_actions ); // reindex array before return
    }


    /**
     * Get all action categories used to group actions in the builder library
     *
     * Third parties can register their own categories (eg: a tab on the actions
     * marketplace modal) through the "Joinotify/Builder/Action_Categories" filter.
     *
     * @since 1.4.7
     * @return array
     */
    public static function get_action_categories() {
        /**
         * Filter the list of action categories displayed as tabs in the builder
         * actions library modal.
         *
         * Each category is an associative array:
         * - id       (string) Unique category slug, matched against each action 'category'.
         * - label    (string) Localized category name shown on the tab.
         * - icon     (string) Optional inline SVG markup shown next to the label.
         * - priority (int)    Optional sort order (lower comes first).
         *
         * @since 1.4.7
         * @param array $categories List of registered categories.
         * @return array
         */
        $categories = apply_filters( 'Joinotify/Builder/Action_Categories', array(
            array(
                'id' => 'general',
                'label' => __( 'General', 'joinotify' ),
                'icon' => '<svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" fill="currentColor"><path d="M10 3H4a1 1 0 0 0-1 1v6a1 1 0 0 0 1 1h6a1 1 0 0 0 1-1V4a1 1 0 0 0-1-1zm10 0h-6a1 1 0 0 0-1 1v6a1 1 0 0 0 1 1h6a1 1 0 0 0 1-1V4a1 1 0 0 0-1-1zM10 13H4a1 1 0 0 0-1 1v6a1 1 0 0 0 1 1h6a1 1 0 0 0 1-1v-6a1 1 0 0 0-1-1zm10 0h-6a1 1 0 0 0-1 1v6a1 1 0 0 0 1 1h6a1 1 0 0 0 1-1v-6a1 1 0 0 0-1-1z"></path></svg>',
                'priority' => 10,
            ),
            array(
                'id' => 'advanced',
                'label' => __( 'Advanced', 'joinotify' ),
                'icon' => '<svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" fill="currentColor"><path d="M3 17v2h6v-2H3zM3 5v2h10V5H3zm10 16v-2h8v-2h-8v-2h-2v6h2zM7 9v2H3v2h4v2h2V9H7zm14 4v-2H11v2h10zm-6-4h2V7h4V5h-4V3h-2v6z"></path></svg>',
                'priority' => 50,
            ),
        ));

        if ( ! is_array( $categories ) ) {
            return array();
        }

        return array_values( $categories );
    }


    /**
     * Recursive function to delete an action by ID
     * 
     * @since 1.1.0
     * @param array $workflow_content | Workflow content array
     * @param string $action_id | ID of the action or trigger to be deleted
     * @return array | Updated workflow
     */
    public static function delete_item_recursive( $workflow_content, $action_id ) {
        foreach ( $workflow_content as $key => &$item ) {
            // If it is the item to be deleted, remove
            if ( isset( $item['id'] ) && $item['id'] === $action_id ) {
                unset( $workflow_content[$key] );

                continue;
            }

            if ( isset( $item['children'] ) && is_array( $item['children'] ) ) {
                if ( self::is_branch_container( $item['children'] ) ) {
                    // condition (action_true/action_false) or loop (action_loop): recurse
                    // into each branch list present, whatever the branch keys are
                    foreach ( $item['children'] as $branch_key => $branch_nodes ) {
                        if ( is_array( $branch_nodes ) ) {
                            $item['children'][ $branch_key ] = self::delete_item_recursive( $branch_nodes, $action_id );
                        }
                    }
                } else {
                    $item['children'] = self::delete_item_recursive( $item['children'], $action_id );
                }
            }
        }

        // Reindex the array to avoid index failures
        return array_values( $workflow_content );
    }


    /**
     * Check whether a children payload contains branch collections.
     *
     * @since 1.4.7
     * @param array $children Children payload.
     * @return bool
     */
    private static function is_branch_container( $children ) {
        return is_array( $children ) && ( array_key_exists( 'action_true', $children ) || array_key_exists( 'action_false', $children ) || array_key_exists( 'action_loop', $children ) );
    }


    /**
     * Update a action inside workflow by ID
     *
     * @since 1.1.0
     * @version 1.4.7
     * @param array &$workflow_item | Reference the item from workflow
     * @param string $action_id | Action ID for update
     * @param array $new_action_data | New data action
     * @return bool return true if action is updated, false otherwise
     */
    public static function update_action_by_id( &$workflow_item, $action_id, $new_action_data ) {
        if ( isset( $workflow_item['id'] ) && $workflow_item['id'] === $action_id ) {
            // update only the necessary data keeping the original structure
            $workflow_item['data'] = array_merge( $workflow_item['data'], $new_action_data['data'] );
    
            // update action description
            if ( isset( $workflow_item['data']['action'] ) ) {
                $workflow_item['data']['description'] = Messages::build_workflow_action_description( $workflow_item );
            }
    
            return true;
        }
    
        // if there is children, loop recursively
        if ( isset( $workflow_item['children'] ) && is_array( $workflow_item['children'] ) ) {
            foreach ( $workflow_item['children'] as $key => &$child_group ) {
                // if is an array of actions (ex: action_true, action_false), loop each action inside it
                if ( is_array( $child_group ) ) {
                    foreach ( $child_group as &$child ) {
                        if ( self::update_action_by_id( $child, $action_id, $new_action_data ) ) {
                            return true;
                        }
                    }
                }
            }
        }
    
        return false;
    }


    /**
     * Extract all actions from workflow content
     * 
     * @since 1.2.0
     * @param array $workflow_content | Workflow content array
     * @return array | Array of actions
     */
    public static function extract_all_actions( $workflow_content ) {
        $actions = array();

        self::collect_actions( $workflow_content, $actions );

        return $actions;
    }


    /**
     * Recursively collect action nodes from a node list.
     *
     * Handles both children shapes: linear nested children ([ node, node, ... ])
     * and condition branch containers ({ action_true: [...], action_false: [...] }).
     *
     * @since 1.4.7
     * @param mixed $nodes | List of workflow nodes
     * @param array $actions | Accumulator (by reference)
     * @return void
     */
    private static function collect_actions( $nodes, &$actions ) {
        if ( ! is_array( $nodes ) ) {
            return;
        }

        foreach ( $nodes as $node ) {
            if ( ! is_array( $node ) ) {
                continue;
            }

            if ( isset( $node['type'] ) && $node['type'] === 'action' ) {
                $actions[] = $node;
            }

            if ( ! isset( $node['children'] ) || ! is_array( $node['children'] ) ) {
                continue;
            }

            $children = $node['children'];

            // branch containers: condition { action_true, action_false } or loop { action_loop }
            if ( array_key_exists( 'action_true', $children ) || array_key_exists( 'action_false', $children ) || array_key_exists( 'action_loop', $children ) ) {
                self::collect_actions( $children['action_true'] ?? array(), $actions );
                self::collect_actions( $children['action_false'] ?? array(), $actions );
                self::collect_actions( $children['action_loop'] ?? array(), $actions );
            } else {
                // linear nested children: [ node, node, ... ]
                self::collect_actions( $children, $actions );
            }
        }
    }


    /**
     * Fill the sender data for all actions recursively
     *
     * @since 1.3.2
     * @param array $items
     * @return void
     */
    public static function fill_sender_recursive( &$items ) {
        /**
         * Filter actions that should have sender auto-filled on import
         * 
         * @since 1.3.2
         * @param array $actions List of action slugs that require sender
         * @return array
         */
        $check_actions = apply_filters( 'Joinotify/Download_Template/Fill_Sender_Actions', array( 'send_whatsapp_message_text', 'send_whatsapp_message_media', 'send_whatsapp_message_template', 'create_coupon' ) );

        foreach ( $items as &$item ) {
            if ( isset( $item['type'], $item['data'] ) && $item['type'] === 'action' && isset( $item['data']['action'] ) && in_array( $item['data']['action'], $check_actions, true ) && empty( $item['data']['sender'] ) ) {
                $item['data']['sender'] = joinotify_get_first_sender();
            }

            // process recursively if there are children
            if ( isset( $item['children'] ) && is_array( $item['children'] ) ) {
                foreach ( $item['children'] as &$child_branch ) {
                    if ( is_array( $child_branch ) ) {
                        self::fill_sender_recursive( $child_branch );
                    }
                }

                // best practice to unset the reference
                unset( $child_branch );
            }
        }

        // best practice to unset the reference
        unset( $item );
    }
}
