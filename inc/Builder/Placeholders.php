<?php

namespace MeuMouse\Joinotify\Builder;

use MeuMouse\Joinotify\Core\Logger;

// Exit if accessed directly.
defined('ABSPATH') || exit;

/**
 * This class manages the builder placeholders
 * 
 * @since 1.0.0
 * @version 1.1.0
 * @package MeuMouse.com
 */
class Placeholders {

    /**
     * Get placeholders list based on context and trigger type
     *
     * @since 1.0.0
     * @version 1.1.0
     * @param string $integration | Integration key
     * @param string $trigger | Trigger type to filter placeholders
     * @param array $context | Context data
     * @return array Filtered list of placeholders based on the trigger, or if empty return all
     */
    public static function get_placeholders_list( $integration = '', $trigger = '', $context = array() ) {
        $placeholders = apply_filters( 'Joinotify/Builder/Placeholders_List', array(), $context );

        // initialize the global placeholders (empty triggers array)
        foreach ( $placeholders as $group_key => $group_placeholders ) {
            foreach ( $group_placeholders as $placeholder => $details ) {
                if ( empty( $details['triggers'] ) ) {
                    $filtered_placeholders[ $placeholder ] = $details;
                }
            }
        }

        // if a specific integration and exists on filtered placeholders
        if ( ! empty( $integration ) && isset( $placeholders[ $integration ] ) ) {
            if ( ! empty( $trigger ) ) {
                // include only placeholders corresponding the specific trigger
                foreach ( $placeholders[ $integration ] as $placeholder => $details ) {
                    if ( in_array( $trigger, $details['triggers'], true ) ) {
                        $filtered_placeholders[ $placeholder ] = $details;
                    }
                }
            } else {
                // include all the placeholders from specific integration
                foreach ( $placeholders[ $integration ] as $placeholder => $details ) {
                    $filtered_placeholders[ $placeholder ] = $details;
                }
            }
        }

        return $filtered_placeholders;
    }


    /**
     * Replace placeholders with actual values in the message
     *
     * @since 1.0.0
     * @version 1.1.0
     * @param string $message | The message containing placeholders
     * @param array $context | The placeholder context with array data
     * @param string $mode | Mode ('sandbox' or 'production')
     * @return string The message with placeholders replaced
     */
    public static function replace_placeholders( $message, $context = array(), $mode = 'production' ) {
        // First, replace field placeholders dynamically
        $message = preg_replace_callback('/\{\{\s*field_id=\[(.+?)\]\s*\}\}/', function( $matches ) use ( $context ) {
            $field_id = $matches[1];

            if ( isset( $context['fields'][$field_id] ) ) {
                return $context['fields'][$field_id];
            }

            return $matches[0]; // if the field is not found, returns the original placeholder
        }, $message);

        // Now handles static placeholders
        $integration = isset( $context['integration'] ) ? $context['integration'] : '';
        $trigger = isset( $context['trigger'] ) ? $context['trigger'] : '';
        $placeholders = self::get_placeholders_list( $integration, $trigger, $context );

        foreach ( $placeholders as $placeholder => $details ) {
            if ( isset( $details['replacement'][ $mode ] ) ) {
                $replacement = $details['replacement'][ $mode ];

                // if the replacement is a callback function, execute it
                if ( is_callable( $replacement ) ) {
                    $replacement = call_user_func( $replacement, $context );
                }

                $message = str_replace( $placeholder, $replacement, $message );
            }
        }

        // replace for checkout placeholders {{ wc_checkout_field=[] }}
        $message = preg_replace_callback('/\{\{\s*wc_checkout_field=\[(.+?)\]\s*\}\}/', function( $matches ) use ( $context ) {
            $field_id = $matches[1];

            // check if 'order_id' has on context array
            if ( isset( $context['order_id'] ) ) {
                $order_id = $context['order_id'];
                $order = wc_get_order( $order_id );

                if ( $order ) {
                    // Retrieves the value of the specific field. Assuming it is a custom field stored as meta
                    $field_value = $order->get_meta( $field_id );

                    // Checks if field value is found
                    if ( ! empty( $field_value ) ) {
                        return $field_value;
                    }
                }
            }

            return $matches[0]; // returns the original placeholder if not found
        }, $message );

        return $message;
    }
}