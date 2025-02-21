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
    public static function build_workflow_action_description( $workflow_action ) {
        $message = '';

        switch ( $workflow_action['data']['action'] ) {
            case 'time_delay':
                $message = self::build_time_delay_description( $workflow_action['data'] );

                break;
            case 'condition':
                $message = self::build_condition_description( $workflow_action['data'] );

                break;
            case 'send_whatsapp_message_text':
                $message = self::build_whatsapp_text_description( $workflow_action['data'] );

                break;
            case 'send_whatsapp_message_media':
                $message = self::build_whatsapp_media_description( $workflow_action['data'] );

                break;
            case 'create_coupon':
                $message = self::build_coupon_description( $workflow_action['data'] );

                break;
            case 'snippet_php':
                $message = self::build_snippet_php_description( $workflow_action['data'] );

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
    public static function build_time_delay_description( $data ) {
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
    public static function build_condition_description( $data ) {
        $title = isset( $data['title'] ) ? $data['title'] : '';
        $condition_text = isset( $data['condition_content']['type_text'] ) ? $data['condition_content']['type_text'] : '';
        $condition_value = isset( $data['condition_content']['value_text'] ) ? $data['condition_content']['value_text'] : '';

        $description = '<div class="condition-description">';
            $description .= sprintf( __( '%s %s:' ), $title, mb_strtolower( $condition_text, 'UTF-8' ) );
            // ADICIONAR NOVA LINHA COM CADA ITEM DE CONDIÇÃO
        $description .= '</div>';

        return $description;
    }


    /**
     * Build a message for WhatsApp text actions
     * 
     * @since 1.0.0
     * @version 1.1.0
     * @param array $data | Message data
     * @return string
     */
    public static function build_whatsapp_text_description( $data ) {
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
    public static function build_whatsapp_media_description( $data ) {
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


    /**
     * Build workflow description
     * 
     * @since 1.1.0
     * @param array $data | Message data
     * @return string
     */
    public static function build_coupon_description( $data ) {
        $message = '<div class="coupon-message-preview">';
            $coupon_code = $data['settings']['generate_coupon'] === 'yes' ? esc_html__( 'Gerado automaticamente', 'joinotify' ) : $data['settings']['coupon_code'];
            $discount_type = $data['settings']['discount_type'] === 'percent' ? esc_html__( 'Percentual', 'joinotify' ) : esc_html__( 'Valor fixo', 'joinotify' );
            $free_shipping = $data['settings']['free_shipping'] === 'yes' ? esc_html__( 'Sim', 'joinotify' ) : esc_html__( 'Não', 'joinotify' );
            $coupon_expires = $data['settings']['coupon_expiry'] === 'yes' ? esc_html__( 'Sim', 'joinotify' ) : esc_html__( 'Não', 'joinotify' );

            $message .= sprintf( '<div class="coupon-message coupon-code">'. __( 'Cupom de desconto: <span class="builder-placeholder">%s</span>', 'joinotify' ) .'</div>', $coupon_code );
            $message .= sprintf( '<div class="coupon-message discount-type">'. __( 'Tipo: %s', 'joinotify' ) .'</div>', $discount_type );
            $message .= sprintf( '<div class="coupon-message discount-value">'. __( 'Desconto: %s', 'joinotify' ) .'</div>', $data['settings']['coupon_amount'] );
            $message .= sprintf( '<div class="coupon-message free-shipping">'. __( 'Frete grátis: %s', 'joinotify' ) .'</div>', $free_shipping );
            $message .= sprintf( '<div class="coupon-message coupon-expires">'. __( 'Cupom expira: %s', 'joinotify' ) .'</div>', $coupon_expires );

            // add coupon expiry message
            if ( $data['settings']['coupon_expiry'] === 'yes' ) {
                if ( $data['settings']['expiry_data']['type'] === 'period' ) {
                    $time_value = $data['settings']['expiry_data']['delay_value'] ?? '';
                    $time_unit = $data['settings']['expiry_data']['delay_period'] ?? '';
    
                    // Format time unit: singular/plural
                    $formatted_time = ( $time_value > 1 ) ? Helpers::format_time_unit( $time_unit, true ) : Helpers::format_time_unit( $time_unit, false );
                    $message .= sprintf( '<div class="coupon-message coupon-expires-period">'. __( 'Expira em %s %s', 'joinotify' ) .'</div>', $time_value, $formatted_time );
                } elseif ( $data['settings']['expiry_data']['type'] === 'date' ) {
                    $date_value = $data['settings']['expiry_data']['date_value'] ?? '';
                    $time_value = $data['settings']['expiry_data']['time_value'] ?? '';
    
                    if ( ! empty( $time_value ) ) {
                        $message .= sprintf( '<div class="coupon-message coupon-expires-date">'. __( 'Expira em %s - %s', 'joinotify' ) .'</div>', $date_value, $time_value );
                    } else {
                        $message .= sprintf( '<div class="coupon-message coupon-expires-date">'. __( 'Expira em %s', 'joinotify' ) .'</div>', $date_value );
                    }
                }
            }
        $message .= '</div>';

        return $message;
    }


    /**
     * Build Snippet PHP workflow description
     * 
     * @since 1.1.0
     * @param array $data | Message data
     * @return string
     */
    public static function build_snippet_php_description( $data ) {
        if ( empty( $data['snippet_php'] ) ) {
            return '<div class="joinotify-code-preview"><p>' . esc_html__( 'Nenhum código disponível.', 'joinotify' ) . '</p></div>';
        }
    
        // break the code into lines
        $get_code = explode( "\n", trim( $data['snippet_php'] ) );

        /**
         * Display Snippet PHP lines filter
         * 
         * @since 1.1.0
         * @param int $lines_filter | Default 20
         * @return int
         */
        $lines_filter = apply_filters( 'Joinotify/Builder/Messages/Snippet_PHP_Lines', 20 );
    
        // get only the first 20 lines
        $snippet_excerpt = array_slice( $get_code, 0, $lines_filter );
    
        // join the lines back together
        $formatted_code = implode( "\n", $snippet_excerpt );
    
        // build element preview
        return '<textarea class="joinotify-code-preview">'. esc_textarea( $formatted_code ) .'</textarea>';
    }
}