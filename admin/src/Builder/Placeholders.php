<?php

namespace MeuMouse\Joinotify\Builder;

use MeuMouse\Joinotify\Core\Logger;

// Exit if accessed directly.
defined('ABSPATH') || exit;

/**
 * This class manages the builder placeholders
 * 
 * @since 1.0.0
 * @version 1.4.7
 * @package MeuMouse\Joinotify\Builder
 * @author MeuMouse.com
 */
class Placeholders {

    /**
     * Get placeholders list based on context and trigger type
     *
     * @since 1.0.0
     * @version 1.4.7
     * @param string $integration | Integration key
     * @param string $trigger | Trigger type to filter placeholders
     * @param array $context | Context data
     * @return array Filtered list of placeholders based on the trigger, or if empty return all
     */
    public static function get_placeholders_list( $integration = '', $trigger = '', $context = array() ) {
        $placeholders = apply_filters( 'Joinotify/Builder/Placeholders_List', array(), $context );
        $filtered_placeholders = array();

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
     * @version 1.4.7
     * @param string $message | The message containing placeholders
     * @param array $payload | The placeholder context with array data
     * @param string $mode | Mode ('sandbox' or 'production')
     * @return string The message with placeholders replaced
     */
    public static function replace_placeholders( $message, $payload = array(), $mode = 'production' ) {
        $message = is_scalar( $message ) ? (string) $message : '';
        $payload = is_array( $payload ) ? $payload : array();

        /**
         * Custom dynamic token resolvers registered by third parties.
         *
         * Lets add-ons resolve their own parametric/bracket-style tokens (e.g.
         * "{{ my_field=[id] }}") WITHOUT editing this method. The map is keyed by a
         * PCRE pattern; each value is a callable( array $matches, array $payload ) that
         * returns the replacement string, or null/non-scalar to leave the token
         * untouched (so an unresolved token never blanks out the message).
         *
         * Registered resolvers run FIRST, so an add-on can also override the built-in
         * bracket handlers below for its own context. The built-in branches remain as
         * a backward-compatible fallback for any token a resolver did not replace.
         *
         * @since 2.0.0
         * @param array $resolvers Map of PCRE pattern => callable( array $matches, array $payload ): string|null.
         * @param array $payload   Runtime placeholder payload.
         */
        $custom_resolvers = apply_filters( 'Joinotify/Builder/Resolve_Dynamic_Token', array(), $payload );

        if ( is_array( $custom_resolvers ) ) {
            foreach ( $custom_resolvers as $pattern => $resolver ) {
                if ( ! is_string( $pattern ) || '' === $pattern || ! is_callable( $resolver ) ) {
                    continue;
                }

                $replaced = preg_replace_callback( $pattern, function( $matches ) use ( $resolver, $payload ) {
                    $resolved = call_user_func( $resolver, $matches, $payload );

                    return is_scalar( $resolved ) ? (string) $resolved : $matches[0];
                }, $message );

                // preg_replace_callback returns null on error (e.g. bad pattern); keep the previous message in that case.
                if ( is_string( $replaced ) ) {
                    $message = $replaced;
                }
            }
        }

        // First, replace field placeholders dynamically
        $message = preg_replace_callback('/\{\{\s*field_id=\[(.+?)\]\s*\}\}/', function( $matches ) use ( $payload ) {
            $field_id = $matches[1];

            // check integration
            if ( $payload['integration'] === 'wpforms' ) {
                if ( isset( $payload['fields'][$field_id]['value'] ) ) {
                    return $payload['fields'][$field_id]['value'];
                }
            } else {
                if ( isset( $payload['fields'][$field_id] ) ) {
                    return $payload['fields'][$field_id];
                }
            }

            return $matches[0]; // if the field is not found, returns the original placeholder
        }, $message);

        // handles static placeholders
        $integration = isset( $payload['integration'] ) ? $payload['integration'] : '';
        $trigger = isset( $payload['trigger'] ) ? $payload['trigger'] : '';

        // get placeholders based on trigger and context
        $placeholders = self::get_placeholders_list( $integration, $trigger, $payload );
        
        // iterate for each placeholder
        foreach ( $placeholders as $placeholder => $details ) {
            if ( isset( $details['replacement'][ $mode ] ) ) {
                $replacement = $details['replacement'][ $mode ];

                // if the replacement is a callback function, execute it
                if ( is_callable( $replacement ) ) {
                    $replacement = call_user_func( $replacement, $payload );
                }

                if ( ! is_scalar( $replacement ) ) {
                    $replacement = '';
                }

                // Exact match first (backward compatible): the builder inserts tokens as "{{ token }}".
                $message = str_replace( $placeholder, $replacement, $message );

                // Then a whitespace-tolerant pass so "{{token}}" and "{{  token  }}" also resolve,
                // matching the tolerance the parametric bracket branches below already have. The
                // replacement is returned via a callback so "$" / "\" inside values are never treated
                // as regex backreferences.
                if ( preg_match( '/^\{\{\s*(.+?)\s*\}\}$/', (string) $placeholder, $token_match ) ) {
                    $tolerant_pattern = '/\{\{\s*' . preg_quote( $token_match[1], '/' ) . '\s*\}\}/';
                    $replacement_value = (string) $replacement;

                    $tolerant_message = preg_replace_callback( $tolerant_pattern, function() use ( $replacement_value ) {
                        return $replacement_value;
                    }, $message );

                    if ( is_string( $tolerant_message ) ) {
                        $message = $tolerant_message;
                    }
                }
            }
        }

        // replace for checkout placeholders {{ wc_checkout_field=[FIELD_ID] }}
        $message = preg_replace_callback('/\{\{\s*wc_checkout_field=\[(.+?)\]\s*\}\}/', function( $matches ) use ( $payload ) {
            $field_id = $matches[1];
            $field_id = is_string( $field_id ) ? $field_id : '';
            $internal_map = array(
                '_billing_first_name' => 'get_billing_first_name',
                '_billing_last_name' => 'get_billing_last_name',
                '_billing_company' => 'get_billing_company',
                '_billing_address_1' => 'get_billing_address_1',
                '_billing_address_2' => 'get_billing_address_2',
                '_billing_city' => 'get_billing_city',
                '_billing_state' => 'get_billing_state',
                '_billing_postcode' => 'get_billing_postcode',
                '_billing_country' => 'get_billing_country',
                '_billing_email' => 'get_billing_email',
                '_billing_phone' => 'get_billing_phone',
                '_shipping_first_name' => 'get_shipping_first_name',
                '_shipping_last_name' => 'get_shipping_last_name',
                '_shipping_company' => 'get_shipping_company',
                '_shipping_address_1' => 'get_shipping_address_1',
                '_shipping_address_2' => 'get_shipping_address_2',
                '_shipping_city' => 'get_shipping_city',
                '_shipping_state' => 'get_shipping_state',
                '_shipping_postcode' => 'get_shipping_postcode',
                '_shipping_country' => 'get_shipping_country',
                '_shipping_phone' => 'get_shipping_phone',
            );

            // check if 'order_id' has on context array
            if ( isset( $payload['order_id'] ) ) {
                $order_id = $payload['order_id'];
                $order = wc_get_order( $order_id );

                if ( $order ) {
                    // Retrieves the value of the specific field. Assuming it is a custom field stored as meta
                    $field_value = isset( $internal_map[ $field_id ] ) && method_exists( $order, $internal_map[ $field_id ] ) ? $order->{$internal_map[ $field_id ]}() : $order->get_meta( $field_id );

                    // If the value is empty, check standard WooCommerce fields
                    if ( empty( $field_value ) ) {
                        if ( method_exists( $order, "get_{$field_id}" ) ) {
                            $field_value = call_user_func( array( $order, "get_{$field_id}" ) );
                        }
                    }

                    // Checks if field value is found
                    if ( ! empty( $field_value ) ) {
                        return $field_value;
                    }
                }
            }

            return $matches[0]; // returns the original placeholder if not found
        }, $message );

        // Replace for user meta placeholders {{ user_meta[META_KEY] }}
        $message = preg_replace_callback('/\{\{\s*user_meta\[(.+?)\]\s*\}\}/', function( $matches ) use ( $payload ) {
            $meta_key = $matches[1];

            // Check if 'user_id' exists in payload
            if ( isset( $payload['user_id'] ) ) {
                $user_id = $payload['user_id'];
            } elseif ( isset( $payload['order_id'] ) ) {
                // Get order object
                $order = wc_get_order( $payload['order_id'] );
                
                if ( $order ) {
                    $billing_email = $order->get_billing_email();

                    if ( ! empty( $billing_email ) ) {
                        $user = get_user_by( 'email', $billing_email );
                        
                        if ( $user ) {
                            $user_id = $user->ID;
                        }
                    }
                }
            }

            // If no user ID was found, use the current logged-in user
            if ( empty( $user_id ) ) {
                $user_id = get_current_user_id();
            }

            // Retrieve the user meta
            $meta_value = get_user_meta( $user_id, $meta_key, true );

            // If the meta key has a value, return it
            if ( ! empty( $meta_value ) ) {
                return is_array( $meta_value ) ? implode(', ', $meta_value) : $meta_value;
            }

            // if not found, returns the original placeholder
            return $matches[0];

        }, $message);

        return $message;
    }
}
