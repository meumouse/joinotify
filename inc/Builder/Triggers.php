<?php

namespace MeuMouse\Joinotify\Builder;

use MeuMouse\Joinotify\Core\Logger;

// Exit if accessed directly.
defined('ABSPATH') || exit;

/**
 * This class handles with triggers functions
 * 
 * @since 1.1.0
 * @package MeuMouse.com
 */
class Triggers {

    /**
     * Get all triggers
     * 
     * This function returns all triggers from different contexts (woocommerce, wpforms, etc.).
     * Triggers are filtered by 'apply_filters' to allow dynamic extensions.
     * 
     * @since 1.0.0
     * @version 1.1.0
     * @return array
     */
    public static function get_all_triggers() {
        return apply_filters( 'Joinotify/Builder/Get_All_Triggers', array() );
    }


    /**
     * Get triggers by context
     * 
     * @since 1.0.0
     * @version 1.1.0
     * @param string $context | Nome do contexto (woocommerce, elementor, etc.)
     * @return array | return triggers array from context
     */
    public static function get_triggers_by_context( $context ) {
        $all_triggers = self::get_all_triggers();

        // check if has context on triggers array
        if ( isset( $all_triggers[$context] ) ) {
            return $all_triggers[$context];
        }

        // if context not found, return empty array
        return array();
    }


    /**
     * Get specific trigger based on context and data_trigger
     * 
     * @since 1.0.0
     * @version 1.1.0
     * @param string $context | Context name (woocommerce, elementor, etc.)
     * @param string $data_trigger | Trigger name (ex: 'order_completed')
     * @return array|null | return the trigger or null if not found
     */
    public static function get_trigger( $context, $data_trigger ) {
        $triggers = self::get_triggers_by_context( $context );

        foreach ( $triggers as $trigger ) {
            if ( $trigger['data_trigger'] === $data_trigger ) {
                return $trigger;
            }
        }

        // If trigger is not found, return null
        return null;
    }


    /**
     * Get trigger from a specific post ID
     * 
     * @since 1.0.0
     * @version 1.1.0
     * @param int $post_id | Post ID
     * @return array|null | Trigger data if found, or null if not
     */
    public static function get_trigger_from_post( $post_id ) {
        if ( get_post_type( $post_id ) === 'joinotify-workflow' ) {
            $workflow_content = get_post_meta( $post_id, 'joinotify_workflow_content', true );

            if ( is_array( $workflow_content ) ) {
                foreach ( $workflow_content as $item ) {
                    if ( isset( $item['type'] ) && $item['type'] === 'trigger' ) {
                        return $item['data']['trigger']; // Returns the found trigger
                    }
                }
            }
        }

        return null; // Returns null if no trigger is found.
    }


    /**
     * Get triggers names by context
     * 
     * @since 1.1.0
     * @param string $integration | Integration context
     * @return array
     */
    public static function get_trigger_names( $integration ) {
        $get_array = self::get_triggers_by_context( $integration );

        return array_column( $get_array, 'data_trigger' );
    }
}