<?php

namespace MeuMouse\Joinotify\Builder;

use MeuMouse\Joinotify\Core\Helpers;

// Exit if accessed directly.
defined('ABSPATH') || exit;

/**
 * This class provides functions for handling messages from workflow builder
 * 
 * @since 1.1.0
 * @version 1.2.2
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
                $message = self::build_condition_description( $workflow_action );

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
     * @version 1.2.2
     * @param array $condition | Condition data
     * @return string
     */
    public static function build_condition_description( $condition ) {
        $condition_data = $condition['data'];
        $condition_content = $condition_data['condition_content'];
        $condition_type = $condition_content['type'];
        $get_condition = $condition_content['condition'];

        // open condition description message
        $description = '<div class="condition-description">';
            if ( $get_condition === 'products_purchased' ) {
                $product_titles = array_map( function( $product ) {
                    return $product['title'];
                }, $condition_content['products'] );
            
                $description .= sprintf( '%s: %s', $condition_content['type_text'], implode( ', ', $product_titles ) );
            } elseif ( $get_condition === 'order_paid' ) {
                $description .= $condition_type === 'is' ? esc_html__( 'Verificar se o pedido foi pago', 'joinotify' ) : esc_html__( 'Verificar se o pedido não foi pago', 'joinotify' );
            } elseif ( $get_condition === 'order_total' ) {
                $description .= $condition_type === 'bigger_than' ? sprintf( __( 'Maior que <span class="builder-placeholder">%s</span>', 'joinotify' ), joinotify_format_plain_text( wc_price( (float) $condition_content['value'] ?? '' ) ) ) : sprintf( __( 'Menor que <span class="builder-placeholder">%s</span>', 'joinotify' ), joinotify_format_plain_text( wc_price( (float) $condition_content['value'] ?? '' ) ) );
            } elseif ( $get_condition === 'field_value' ) {
                if ( $condition_type === 'empty' ) {
                    $description .= sprintf( __( 'Campo com ID <span class="builder-placeholder">%s</span> é vazio', 'joinotify' ), mb_strtolower( $condition_content['field_id'] ?? '', 'UTF-8' ) );
                } elseif ( $condition_type === 'not_empty' ) {
                    $description .= sprintf( __( 'Campo com ID <span class="builder-placeholder">%s</span> não é vazio', 'joinotify' ), mb_strtolower( $condition_content['field_id'] ?? '', 'UTF-8' ) );
                } else {
                    $description .= sprintf( __( 'Campo com ID <span class="builder-placeholder">%s</span> %s: <span class="builder-placeholder">%s</span>', 'joinotify' ), $condition_content['field_id'] ?? '', mb_strtolower( $condition_content['type_text'] ?? '', 'UTF-8' ), $condition_content['value_text'] ?? '' );
                }
            } elseif ( $get_condition === 'user_meta' ) {
                if ( $condition_type === 'empty' ) {
                    $description .= sprintf( __( '<span class="builder-placeholder">%s</span> é vazio', 'joinotify' ), mb_strtolower( $condition_content['meta_key'] ?? '', 'UTF-8' ) );
                } elseif ( $condition_type === 'not_empty' ) {
                    $description .= sprintf( __( '<span class="builder-placeholder">%s</span> não é vazio', 'joinotify' ), mb_strtolower( $condition_content['meta_key'] ?? '', 'UTF-8' ) );
                } else {
                    $description .= sprintf( __( '<span class="builder-placeholder">%s</span> %s: %s' ), $condition_content['meta_key'] ?? '', mb_strtolower( $condition_content['type_text'] ?? '', 'UTF-8' ), $condition_content['value_text'] ?? '' );
                }
            } else {
                $description .= sprintf( __( '%s %s: %s' ), $condition_data['title'] ?? '', mb_strtolower( $condition_content['type_text'] ?? '', 'UTF-8' ), $condition_content['value_text'] ?? '' );
            }
        $description .= '</div>';

        return $description;
    }


    /**
     * Build a message for WhatsApp text actions
     * 
     * @since 1.0.0
     * @version 1.2.2
     * @param array $data | Message data
     * @return string
     */
    public static function build_whatsapp_text_description( $data ) {
        $message = isset( $data['message'] ) ? $data['message'] : '';

        // apply WhatsApp formatting before adding line breaks
        $message = preg_replace([
            '/```([^`]+)```/', // Monospace
            '/(?<!\S)\*\*([^\s*][^*]+?[^\s*])\*\*(?!\S)/', // **Bold**
            '/(?<!\S)\*([^\s*][^*]+?[^\s*])\*(?!\S)/', // *Bold*
            '/(?<!\S)_([^\s_][^_]+?[^\s_])_(?!\S)/', // _Italic_
            '/(?<!\S)~([^\s~][^~]+?[^\s~])~(?!\S)/', // ~Strikethrough~
        ], [
            '<span style="font-family: monospace;">$1</span>', // ```monospace```
            '<span style="font-weight: bold;">$1</span>', // **Bold**
            '<span style="font-weight: bold;">$1</span>', // *Bold*
            '<span style="font-style: italic;">$1</span>', // _Italic_
            '<span style="text-decoration: line-through;">$1</span>', // ~Strikethrough~
        ], $message);

        // replace line breaks
        $message = str_replace(["\n", '\n', '{{ br }}'], '<br>', $message);

        // process placeholders
        $message = preg_replace_callback('/\{\{\s*(.*?)\s*\}\}/', function( $matches ) {
            return '<span class="builder-placeholder">{{ '. $matches[1] .' }}</span>';
        }, $message);

        return $message;
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
            case 'image':
                return '<img class="funnel-media image" src="'. esc_url( $media ) .'">';

                break;
            case 'video':
                return '<video class="funnel-media video" controls width="250"><source src="'. esc_url( $media ) .'"/></video>';

                break;
            case 'document':
                return '<embed class="funnel-media document" src="'. esc_url( $media ) .'" frameborder="0" allowfullscreen>';

                break;
            case 'audio':
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