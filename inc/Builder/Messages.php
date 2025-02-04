<?php

namespace MeuMouse\Joinotify\Builder;

use MeuMouse\Joinotify\Core\Helpers;

// Exit if accessed directly.
defined('ABSPATH') || exit;

/**
 * This class provides functions for handling messages from workflow builder
 * 
 * @since 1.1.0
 * @package MeuMouse.com
 */
class Messages {

    /**
     * Build the funnel action message based on workflow action type
     * 
     * @since 1.0.0
     * @version 1.1.0
     * @param array $workflow_action | Workflow action data
     * @return string
     */
    public static function build_workflow_action_message( $workflow_action ) {
        $message = '';

        switch ( $workflow_action['data']['action'] ) {
            case 'time_delay':
                $message = self::build_time_delay_message( $workflow_action['data'] );

                break;
            case 'condition':
                $message = self::build_condition_message( $workflow_action['data'] );

                break;
            case 'send_whatsapp_message_text':
                $message = self::build_whatsapp_text_message( $workflow_action['data'] );

                break;
            case 'send_whatsapp_message_media':
                $message = self::build_whatsapp_media_message( $workflow_action['data'] );

                break;
        }

        return $message;
    }
 

    /**
     * Build a message for time delay actions
     * 
     * @since 1.0.0
     * @version 1.1.0
     * @param array $data | Message data
     * @return string
     */
    public static function build_time_delay_message( $data ) {
        $message = '';

        if ( isset( $data['delay_type'] ) ) {
            if ( $data['delay_type'] === 'period' ) {
                $time_value = $data['delay_value'];
                $time_unit = $data['delay_period'];

                // Format time unit: singular/plural
                $formatted_time_unit = ( $time_value > 1 ) ? Helpers::format_time_unit( $time_unit, true ) : Helpers::format_time_unit( $time_unit, false );
                $message = sprintf( __( 'Esperar por %s %s', 'joinotify' ), $time_value, $formatted_time_unit );

            } elseif ( $data['delay_type'] === 'date' ) {
                $date_value = $data['date_value'];
                $time_value = isset( $data['time_value'] ) ? $data['time_value'] : '';

                if ( ! empty( $time_value ) ) {
                    $message = sprintf( __( 'Esperar até %s - %s', 'joinotify' ), $date_value, $time_value );
                } else {
                    $message = sprintf( __( 'Esperar até %s', 'joinotify' ), $date_value );
                }
            }
        }

        return $message;
    }

    
    /**
     * Build a message for condition actions
     * 
     * @since 1.0.0
     * @version 1.1.0
     * @param array $data | Message data
     * @return string
     */
    public static function build_condition_message( $data ) {
        $title = isset( $data['title'] ) ? $data['title'] : '';
        $condition_text = isset( $data['condition_content']['type_text'] ) ? $data['condition_content']['type_text'] : '';
        $condition_value = isset( $data['condition_content']['value_text'] ) ? $data['condition_content']['value_text'] : '';

        return sprintf( __( '%s %s %s' ), $title, mb_strtolower( $condition_text, 'UTF-8' ), mb_strtolower( $condition_value, 'UTF-8' ) );
    }


    /**
     * Build a message for WhatsApp text actions
     * 
     * @since 1.0.0
     * @version 1.1.0
     * @param array $data | Message data
     * @return string
     */
    public static function build_whatsapp_text_message( $data ) {
        $message = isset( $data['message'] ) ? $data['message'] : '';

        // If the text literally contains \n
        $message = str_replace( "\n", '<br>', $message );

        $message = str_replace( '\n', '<br>', $message );

        // Replace {{ br }} to break line HTML component
        $message = str_replace( '{{ br }}', '<br>', $message );

        // Regular expression to match variables like {{ variable_name }}
        $pattern = '/\{\{\s*(.*?)\s*\}\}/';

        // Callback function to wrap variables in the desired HTML
        $replacement = function( $matches ) {
            return '<span class="builder-placeholder">{{ ' . $matches[1] . ' }}</span>';
        };

        // Assign the processed message
        return preg_replace_callback( $pattern, $replacement, $message );
    }


    /**
     * Build a message for WhatsApp media actions
     * 
     * @since 1.0.0
     * @version 1.1.0
     * @param array $data | Message data
     * @return string
     */
    public static function build_whatsapp_media_message( $data ) {
        $media_type = isset( $data['media_type'] ) ? $data['media_type'] : '';
        $media = isset( $data['media_url'] ) ? $data['media_url'] : '';

        // check media type
        switch ( $media_type ) {
            case ( 'image' ) :
                return '<img class="funnel-media image" src="'. esc_url( $media ) .'">';

                break;
            case ( 'video' ) :
                return '<video class="funnel-media video" controls width="250"><source src="'. esc_url( $media ) .'"/></video>';

                break;
            case ( 'document' ) :
                return '<embed class="funnel-media document" src="'. esc_url( $media ) .'" frameborder="0" allowfullscreen>';

                break;
            case ( 'audio' ) :
                return '<audio class="funnel-media audio" controls><source src="'. esc_url( $media ) .'"></audio>';

                break;
        }
    }
}