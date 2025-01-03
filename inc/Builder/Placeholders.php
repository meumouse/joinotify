<?php

namespace MeuMouse\Joinotify\Builder;

use MeuMouse\Joinotify\Validations\Conditions;
use MeuMouse\Joinotify\Integrations\Woocommerce;
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
        $current_user = wp_get_current_user();

        $placeholders_list = array(
            'wordpress' => array(
                '{{ first_name }}' => array(
                    'triggers' => array(), // is global
                    'description' => esc_html__( 'Para recuperar o primeiro nome do usuário', 'joinotify' ),
                    'replacement' => array(
                        'production' => $current_user->exists() ? $current_user->first_name : __( 'Não foi possível recuperar o nome do usuário', 'joinotify' ),
                        'sandbox' => $current_user->exists() ? $current_user->first_name : esc_html__( 'João', 'joinotify' ),
                    ),
                ),
                '{{ last_name }}' => array(
                    'triggers' => array(), // is global
                    'description' => esc_html__( 'Para recuperar o sobrenome do usuário', 'joinotify' ),
                    'replacement' => array(
                        'production' => $current_user->exists() ? $current_user->last_name : __( 'Não foi possível recuperar o sobrenome do usuário', 'joinotify' ),
                        'sandbox' => $current_user->exists() ? $current_user->last_name : esc_html__( 'da Silva', 'joinotify' ),
                    ),
                ),
                '{{ email }}' => array(
                    'triggers' => array(), // is global
                    'description' => esc_html__( 'Para recuperar o e-mail do usuário', 'joinotify' ),
                    'replacement' => array(
                        'production' => $current_user->exists() ? $current_user->user_email : esc_html__( 'Não foi possível recuperar o e-mail do usuário', 'joinotify' ),
                        'sandbox' => $current_user->exists() ? $current_user->user_email : esc_html__( 'usuario@exemplo.com', 'joinotify' ),
                    ),
                ),
                '{{ site_url }}' => array(
                    'triggers' => array(), // is global
                    'description' => esc_html__( 'Para recuperar a URL do site', 'joinotify' ),
                    'replacement' => array(
                        'production' => get_site_url(),
                        'sandbox' => get_site_url(),
                    ),
                ),
                '{{ site_name }}' => array(
                    'triggers' => array(), // is global
                    'description' => esc_html__( 'Para recuperar o nome do site', 'joinotify' ),
                    'replacement' => array(
                        'production' => get_bloginfo('name'),
                        'sandbox' => get_bloginfo('name'),
                    ),
                ),
                '{{ current_date }}' => array(
                    'triggers' => array(), // is global
                    'description' => esc_html__( 'Para recuperar a data atual', 'joinotify' ),
                    'replacement' => array(
                        'production' => date( get_option('date_format') ),
                        'sandbox' => date( get_option('date_format') ),
                    ),
                ),
                '{{ post_id }}' => array(
                    'triggers' => array(), // is global
                    'description' => esc_html__( 'Para recuperar o ID do post', 'joinotify' ),
                    'replacement' => array(
                        'production' => get_the_ID(),
                        'sandbox' => esc_html__( '12345', 'joinotify' ),
                    ),
                ),
            ),
        );

        $placeholders = apply_filters( 'Joinotify/Builder/Placeholders_List', $placeholders_list, $context );

        // check if integration is specified and exists
        if ( ! empty( $integration ) && isset( $placeholders_list[ $integration ] ) ) {
            $integration_placeholders = $placeholders_list[ $integration ];
        } elseif ( ! empty( $integration ) ) {
            // if the integration not has defined placeholders, return all array or empty
            $integration_placeholders = array();
        } else {
            // if none integration specified, get all the placeholders from all integrations
            $integration_placeholders = array();

            foreach ( $placeholders_list as $int_key => $int_placeholders ) {
                $integration_placeholders = array_merge( $integration_placeholders, $int_placeholders );
            }
        }

        // filter the placeholders based on trigger
        $filtered_placeholders = array();

        foreach ( $integration_placeholders as $placeholder => $details ) {
            // if empty 'triggers', is global and must be included
            if ( empty( $details['triggers'] ) ) {
                $filtered_placeholders[ $placeholder ] = $details;
                continue;
            }

            // if a specified trigger and it is in the placeholders triggers, include
            if ( ! empty( $trigger ) && in_array( $trigger, $details['triggers'] ) ) {
                $filtered_placeholders[ $placeholder ] = $details;
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

            return $matches[0]; // if the field is not found, the original placeholder is returned
        }, $message);

        // Now handles static placeholders
        $integration = isset( $context['type'] ) ? $context['type'] : '';
        $trigger = isset( $context['trigger'] ) ? $context['trigger'] : '';
        $placeholders = self::get_placeholders_list( $integration, $trigger, $context );

        if ( JOINOTIFY_DEBUG_MODE ) {
            Logger::register_log( "replace_placeholders() message after field_id substitution: " . $message );
            Logger::register_log( "replace_placeholders() context: " . print_r( $context, true ) );
            Logger::register_log( "replace_placeholders() mode: " . $mode );
            Logger::register_log( "replace_placeholders() placeholders: " . print_r( $placeholders, true ) );
        }

        foreach ( $placeholders as $placeholder => $details ) {
            if ( isset( $details['replacement'][ $mode ] ) ) {
                $replacement = $details['replacement'][ $mode ];

                // if the replacement is a callback function, run it
                if ( is_callable( $replacement ) ) {
                    $replacement = call_user_func( $replacement, $context );
                }

                $message = str_replace( $placeholder, $replacement, $message );
            }
        }

        return $message;
    }
}