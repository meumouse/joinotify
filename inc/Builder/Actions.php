<?php

namespace MeuMouse\Joinotify\Builder;

use MeuMouse\Joinotify\Core\Logger;
use MeuMouse\Joinotify\Builder\Components as Builder_Components;
use MeuMouse\Joinotify\Builder\Messages;

// Exit if accessed directly.
defined('ABSPATH') || exit;

/**
 * This class handles with actions functions
 * 
 * @since 1.1.0
 * @package MeuMouse.com
 */
class Actions {

    /**
     * Get all actions
     * 
     * This function returns all actions for uses on connect triggers
     * 
     * @since 1.0.0
     * @version 1.1.0
     * @param string $context | Trigger context (eg: woocommerce)
     * @return array
     */
    public static function get_all_actions( $context = '' ) {
        $actions = apply_filters( 'Joinotify/Builder/Actions', array(
            array(
                'action' => 'time_delay',
                'title' => esc_html__( 'Tempo de espera', 'joinotify' ),
                'description' => esc_html__( 'Permite definir um tempo de espera antes da ação ser executada.', 'joinotify' ),
                'context' => array(), // available for all the contexts
                'icon' => '<svg class="icon icon-lg icon-dark" xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24"><path d="M12 2C6.486 2 2 6.486 2 12s4.486 10 10 10 10-4.486 10-10S17.514 2 12 2zm0 18c-4.411 0-8-3.589-8-8s3.589-8 8-8 8 3.589 8 8-3.589 8-8 8z"></path><path d="M13 7h-2v5.414l3.293 3.293 1.414-1.414L13 11.586z"></path></svg>',
                'external_icon' => false,
                'has_settings' => true,
                'settings' => Builder_Components::time_delay_action_settings(),
                'priority' => 10,
                'is_expansible' => false,
            ),
            array(
                'action' => 'condition',
                'title' => esc_html__( 'Condição', 'joinotify' ),
                'description' => esc_html__( 'Permite definir uma condição para uma ação ser executada.', 'joinotify' ),
                'context' => array(), // available for all the contexts
                'icon' => '<svg class="icon icon-lg icon-dark condition" xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24"><path d="M17.5 4C15.57 4 14 5.57 14 7.5c0 1.554 1.025 2.859 2.43 3.315-.146.932-.547 1.7-1.23 2.323-1.946 1.773-5.527 1.935-7.2 1.907V8.837c1.44-.434 2.5-1.757 2.5-3.337C10.5 3.57 8.93 2 7 2S3.5 3.57 3.5 5.5c0 1.58 1.06 2.903 2.5 3.337v6.326c-1.44.434-2.5 1.757-2.5 3.337C3.5 20.43 5.07 22 7 22s3.5-1.57 3.5-3.5c0-.551-.14-1.065-.367-1.529 2.06-.186 4.657-.757 6.409-2.35 1.097-.997 1.731-2.264 1.904-3.768C19.915 10.438 21 9.1 21 7.5 21 5.57 19.43 4 17.5 4zm-12 1.5C5.5 4.673 6.173 4 7 4s1.5.673 1.5 1.5S7.827 7 7 7s-1.5-.673-1.5-1.5zM7 20c-.827 0-1.5-.673-1.5-1.5a1.5 1.5 0 0 1 1.482-1.498l.13.01A1.495 1.495 0 0 1 7 20zM17.5 9c-.827 0-1.5-.673-1.5-1.5S16.673 6 17.5 6s1.5.673 1.5 1.5S18.327 9 17.5 9z"></path></svg>',
                'external_icon' => false,
                'has_settings' => true,
                'settings' => '',
                'priority' => 20,
                'is_expansible' => false,
            ),
            array(
                'action' => 'stop_funnel',
                'title' => esc_html__( 'Parar automação aqui', 'joinotify' ),
                'description' => esc_html__( 'Nenhuma ação será executada ao chegar nesse ponto.', 'joinotify' ),
                'context' => array(), // available for all the contexts
                'icon' => '<svg class="icon icon-lg icon-dark" xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24"><path d="M12 2C6.486 2 2 6.486 2 12s4.486 10 10 10 10-4.486 10-10S17.514 2 12 2zm0 18c-4.411 0-8-3.589-8-8s3.589-8 8-8 8 3.589 8 8-3.589 8-8 8z"></path><path d="M9 9h6v6H9z"></path></svg>',
                'external_icon' => false,
                'has_settings' => false,
                'priority' => 30,
                'is_expansible' => false,
            ),
            array(
                'action' => 'snippet_php',
                'title' => esc_html__( 'Snippet PHP', 'joinotify' ),
                'description' => esc_html__( 'Adicione uma função, conjunto de ações e ou filtros em PHP.', 'joinotify' ),
                'context' => array(), // available for all the contexts
                'icon' => '<svg class="icon icon-lg icon-dark" xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24"><path d="M2.15 16.78h1.57a.14.14 0 0 0 .14-.12l.35-1.82h1.22a4.88 4.88 0 0 0 1.51-.2A2.79 2.79 0 0 0 8 14a3.18 3.18 0 0 0 .67-.85 3.43 3.43 0 0 0 .36-1 2.43 2.43 0 0 0-.41-2.16 2.64 2.64 0 0 0-2.09-.78h-3a.16.16 0 0 0-.15.13L2 16.6a.19.19 0 0 0 0 .13.17.17 0 0 0 .15.05zM5 10.62h1a1.45 1.45 0 0 1 1.08.29c.17.18.2.52.11 1a1.81 1.81 0 0 1-.57 1.12 2.17 2.17 0 0 1-1.33.33h-.8zm9.8-.95a2.7 2.7 0 0 0-1.88-.51h-1.19l.33-1.76a.15.15 0 0 0 0-.13.16.16 0 0 0-.11 0h-1.57a.14.14 0 0 0-.14.12l-1.38 7.27a.13.13 0 0 0 0 .12.13.13 0 0 0 .11.06h1.54a.14.14 0 0 0 .14-.13l.77-4.07h1.11c.45 0 .61.1.66.16a.81.81 0 0 1 0 .62l-.61 3.24a.13.13 0 0 0 0 .12.14.14 0 0 0 .11.06h1.56a.16.16 0 0 0 .15-.13l.64-3.4a1.7 1.7 0 0 0-.24-1.64zm4.52-.51h-3.13a.14.14 0 0 0-.15.13l-1.46 7.31a.16.16 0 0 0 0 .13.14.14 0 0 0 .11.05h1.63a.14.14 0 0 0 .15-.12l.37-1.82h1.27a5.28 5.28 0 0 0 1.56-.2 3 3 0 0 0 1.18-.64 3.31 3.31 0 0 0 .7-.85 3.45 3.45 0 0 0 .37-1 2.38 2.38 0 0 0-.42-2.16 2.81 2.81 0 0 0-2.18-.83zm.62 2.77a1.83 1.83 0 0 1-.6 1.12 2.28 2.28 0 0 1-1.37.33h-.8l.54-2.76h1a1.6 1.6 0 0 1 1.13.29c.16.18.16.52.1 1.02z"></path></svg>',
                'external_icon' => false,
                'has_settings' => true,
                'settings' => Builder_Components::snippet_php_action_settings(),
                'priority' => 55,
                'is_expansible' => true,
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
     * Helper function to find the action by ID
     * Searches recursively in the entire workflow array
     * 
     * @since 1.1.0
     * @param array $workflow_content | Workflow content
     * @param string $action_id | Action ID
     * @return array
     */
    public static function find_action_by_id( $workflow_content, $action_id ) {
        foreach ( $workflow_content as $item ) {
            if ( isset( $item['id'] ) && $item['id'] === $action_id ) {
                return $item;
            }

            // verifica se há children
            if ( isset( $item['children'] ) && is_array( $item['children'] ) ) {
                // caso a ação seja uma condição com 'action_true' e 'action_false'
                if ( isset( $item['data']['action'] ) && $item['data']['action'] === 'condition' ) {
                    if ( isset( $item['children']['action_true'] ) ) {
                        $found = self::find_action_by_id( $item['children']['action_true'], $action_id );
                        
                        if ( $found ) {
                            return $found;
                        }
                    }

                    if ( isset( $item['children']['action_false'] ) ) {
                        $found = self::find_action_by_id( $item['children']['action_false'], $action_id );
                        
                        if ( $found ) {
                            return $found;
                        }
                    }
                } else {
                    // caso normal, apenas um array de ações filhas
                    $found = self::find_action_by_id( $item['children'], $action_id );

                    if ( $found ) {
                        return $found;
                    }
                }
            }
        }

        return null;
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
        }

        // Reindex the array to avoid index failures
        return array_values( $workflow_content );
    }


    /**
     * Update a action inside workflow by ID
     *
     * @since 1.1.0
     * @param array &$workflow_item | Reference the item from workflow
     * @param string $action_id | Action ID for update
     * @param array $new_action_data | New data action
     * @return bool return true if action is updated, false otherwise
     */
    public static function update_action_by_id( &$workflow_item, $action_id, $new_action_data ) {
        if ( isset( $workflow_item['id'] ) && $workflow_item['id'] === $action_id ) {
            // update only necessary data keeping the original structure
            $workflow_item['data'] = array_merge( $workflow_item['data'], $new_action_data['data'] );

            // update action description
            if ( isset( $workflow_item['data']['action'] ) ) {
                $workflow_item['data']['description'] = Messages::build_workflow_action_message( $workflow_item );
            }

            return true;
        }

        // if has children items, find recursive
        if ( isset( $workflow_item['children'] ) && is_array( $workflow_item['children'] ) ) {
            foreach ( $workflow_item['children'] as &$child ) {
                if ( self::update_action_by_id( $child, $action_id, $new_action_data ) ) {
                    return true;
                }
            }
        }

        return false;
    }
}