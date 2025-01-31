<?php

namespace MeuMouse\Joinotify\API;

use MeuMouse\Joinotify\Core\Helpers;
use MeuMouse\Joinotify\Admin\Admin;
use MeuMouse\Joinotify\Core\Logger;
use MeuMouse\Joinotify\API\License;
use MeuMouse\Joinotify\Builder\Placeholders;

use WP_REST_Controller;
use WP_REST_Request;
use WP_REST_Response;

// Exit if accessed directly.
defined('ABSPATH') || exit;

/**
 * Controller for API requests
 * 
 * @since 1.0.0
 * @version 1.1.0
 * @package MeuMouse.com
 */
class Controller {

    /**
     * Construct function
     * 
     * @since 1.0.0
     * @return void
     */
    public function __construct() {
        if ( Admin::get_setting('enable_proxy_api') === 'yes' && License::is_valid() ) {
            add_action( 'rest_api_init', array( $this, 'register_routes' ) );
        }
    }


    /**
     * Records the routes of API endpoints
     * 
     * @since 1.0.0
     * @return void
     */
    public function register_routes() {
        register_rest_route( 'joinotify/v1/', Admin::get_setting('send_text_proxy_api_route'), array(
            'methods' => 'POST',
            'callback' => array( $this, 'send_text_message' ),
            'permission_callback' => array( $this, 'validate_api_key' ),
            'args' => array(
                'sender' => array(
                    'required' => true,
                    'validate_callback' => array( $this, 'validate_string' ),
                ),
                'receiver' => array(
                    'required' => true,
                    'validate_callback' => array( $this, 'validate_string' ),
                ),
                'message' => array(
                    'required' => true,
                    'validate_callback' => array( $this, 'validate_string' ),
                ),
            ),
        ));

        register_rest_route( 'joinotify/v1/', Admin::get_setting('send_media_proxy_api_route'), array(
            'methods' => 'POST',
            'callback' => array( $this, 'send_media_message' ),
            'permission_callback' => array( $this, 'validate_api_key' ),
            'args' => array(
                'sender' => array(
                    'required' => true,
                    'validate_callback' => array( $this, 'validate_string' ),
                ),
                'receiver' => array(
                    'required' => true,
                    'validate_callback' => array( $this, 'validate_string' ),
                ),
                'media_type' => array(
                    'required' => true,
                    'validate_callback' => array( $this, 'validate_string' ),
                ),
                'media_url' => array(
                    'required' => true,
                    'validate_callback' => array( $this, 'validate_string' ),
                ),
            ),
        ));
    }


    /**
     * Validates the API key sent in the request
     *
     * @since 1.0.0
     * @param WP_REST_Request $request
     * @return bool
     */
    public function validate_api_key( WP_REST_Request $request ) {
        // Get API key from 'X-API-Key' header
        $api_key = $request->get_header('X-API-Key');

        if ( $api_key && $api_key === Admin::get_setting('proxy_api_key') ) {
            return true;
        }
        
        return new \WP_Error(
            'rest_forbidden',
            __('Chave de API inválida ou ausente.', 'joinotify'),
            array('status' => 403)
        );
    }


    /**
     * Validate if the given parameter is a string
     *
     * @since 1.0.0
     * @param mixed $param
     * @return bool
     */
    public function validate_string( $param ) {
        return is_string( $param );
    }


    /**
     * Get endpoint for Proxy API send text message
     * 
     * @since 1.1.0
     * @return string
     */
    public static function text_message_proxy_endpoint() {
        return get_home_url() . '/wp-json/joinotify/v1/' . Admin::get_setting('send_text_proxy_api_route');
    }


    /**
     * Get endpoint for Proxy API send media message
     * 
     * @since 1.1.0
     * @return string
     */
    public static function media_message_proxy_endpoint() {
        return get_home_url() . '/wp-json/joinotify/v1/' . Admin::get_setting('send_media_proxy_api_route');
    }


    /**
     * Send a text message on WhatsApp
     *
     * @since 1.0.0
     * @param WP_REST_Request $request
     * @return WP_REST_Response
     */
    public static function send_text_message( WP_REST_Request $request ) {
        $sender = $request->get_param('sender');
        $receiver = $request->get_param('receiver');
        $message = $request->get_param('message');
        $response_code = self::send_message_text( $sender, $receiver, $message );

        if ( 201 === $response_code ) {
            return new WP_REST_Response( array(
                'status' => 'success',
                'message' => __( 'Mensagem de texto enviada com sucesso.', 'joinotify' ),
            ), 200 );
        } else {
            return new WP_REST_Response( array(
                'status' => 'error',
                'message' => __( 'Falha ao enviar mensagem de texto.', 'joinotify' ),
            ), 500 );
        }
    }


    /**
     * Envia uma mensagem de mídia no WhatsApp
     *
     * @since 1.0.0
     * @param WP_REST_Request $request
     * @return WP_REST_Response
     */
    public static function send_media_message( WP_REST_Request $request ) {
        $sender = $request->get_param('sender' );
        $receiver = $request->get_param('receiver');
        $media_type = $request->get_param('media_type');
        $media_url = $request->get_param('media_url');
        $response_code = self::send_message_media( $sender, $receiver, $media_type, $media_url );

        if ( 201 === $response_code ) {
            return new \WP_REST_Response( array(
                'status' => 'success',
                'message' => __( 'Mensagem de mídia enviada com sucesso.', 'joinotify' ),
            ), 200 );
        } else {
            return new \WP_REST_Response( array(
                'status' => 'error',
                'message' => __( 'Falha ao enviar mensagem de mídia.', 'joinotify' ),
            ), 500 );
        }
    }


    /**
     * Get numbers registered on slots
     *
     * @since 1.0.0
     * @version 1.1.0
     * @return array
     */
    public static function get_numbers() {
        $license = get_option('joinotify_license_key') ?: '';
        $api_url = 'https://joinotify-slots-manager.meumouse.com/slots/get-all-phones/' . $license;

        $response = wp_remote_get( $api_url, array(
            'timeout' => 10,
        ));

        if ( is_wp_error( $response ) ) {
            Logger::register_log( $response, 'ERROR' );
        }

        $response_body = wp_remote_retrieve_body( $response );

        // record the response body for debug
        if ( JOINOTIFY_DEBUG_MODE ) {
            Logger::register_log( $response_body );
        }

        return json_decode( $response_body, true );
    }


    /**
     * Get connection state of an instance
     *
     * @since 1.0.0
     * @version 1.1.0
     * @param string $phone | Phone number
     * @return array|WP_Error Response from API or WP_Error on failure
     */
    public static function get_connection_state( $phone ) {
        $api_url = 'https://joinotify-slots-manager.meumouse.com/slots/check-phone-connection/' . $phone;

        $response = wp_remote_get( $api_url, array(
            'timeout' => 10,
        ));

        if ( is_wp_error( $response ) ) {
            Logger::register_log( $response, 'ERROR' );
        }

        $response_body = wp_remote_retrieve_body( $response );

        // record the response body for debug
        if ( JOINOTIFY_DEBUG_MODE ) {
            Logger::register_log( "get_connection_state() response body: " . $response_body );
        }

        $phone_status = json_decode( $response_body, true );

        if ( isset( $phone_status['status'] ) && $phone_status['status'] === 'success' ) {
            if ( $phone_status['connection'] === 'connected' ) {
                $status = 'connected';
            } elseif ( $phone_status['connection'] === 'disconnected' ) {
                $status = 'disconnected';
            }

            update_option( 'joinotify_status_connection_'. $phone, $status );
        }

        // retrieve the response body that associative array
        return $phone_status;
    }


    /**
     * Prepare the receiver phone number with the correct format
     * 
     * @since 1.0.0
     * @version 1.1.0
     * @param string $receiver |  Receiver phone
     * @return string
     */
    public static function prepare_receiver( $receiver, $context = array() ) {
        // First, we replace all placeholders, including {{ field_id=[...] }}
        $receiver = Placeholders::replace_placeholders( $receiver, $context );
    
        // Keep only digits in the number
        $receiver = preg_replace( '/\D/', '', $receiver );
        $country_code = Admin::get_setting('joinotify_default_country_code');
    
        // add country code if needed
        if ( preg_match( '/^\d{10,11}$/', $receiver ) && strpos( $receiver, $country_code ) !== 0 ) {
            $receiver = $country_code . $receiver;
        }

        if ( JOINOTIFY_DEBUG_MODE ) {
            Logger::register_log( "prepare_receiver() receiver finished: " . $receiver );
        }
    
        return $receiver;
    }


    /**
     * Send messsage text on WhatsApp
     * 
     * @since 1.0.0
     * @version 1.1.0
     * @param string $sender | Instance phone number
     * @param string $receiver | Phone number for receive message
     * @param string $message | Message text for send
     * @return int
     */
    public static function send_message_text( $sender, $receiver, $message ) {
        $sender = preg_replace( '/\D/', '', $sender );
        $api_url = JOINOTIFY_API_BASE_URL . '/message/sendText/' . $sender;

        /**
         * Link preview for text messages
         * 
         * @since 1.1.0
         * @return bool
         */
        $link_preview = apply_filters( 'Joinotify/API/Send_Message_Text/Link_Preview', true );

        $payload = wp_json_encode( array(
            'number' => preg_replace( '/\D/', '', $receiver ),
            'linkPreview' => $link_preview,
            'text' => $message,
        ));

        if ( ! License::is_valid() ) {
            return;
        }

        $response = wp_remote_post( $api_url, array(
            'headers' => array(
                'Content-Type' => 'application/json',
                'apikey' => Helpers::whatsapp_api_key(),
            ),
            'body' => $payload,
            'timeout' => 10,
        ));

        if ( is_wp_error( $response ) ) {
            Logger::register_log( $response, 'ERROR' );
        }

        $response_body = wp_remote_retrieve_body( $response );

        // record the response body for debug
        if ( JOINOTIFY_DEBUG_MODE ) {
            Logger::register_log( "send_message_text() response body: " . $response_body );
        }

        return wp_remote_retrieve_response_code( $response );
    }


    /**
     * Send messsage media on WhatsApp
     * 
     * @since 1.0.0
     * @version 1.1.0
     * @param string $sender | Instance phone number
     * @param string $receiver | Phone number for receive message
     * @param string $media_type | Media type (image, audio, video or document)
     * @param string $media | Media URL
     * @return int
     */
    public static function send_message_media( $sender, $receiver, $media_type, $media ) {
        $sender = preg_replace( '/\D/', '', $sender );
        $api_url = JOINOTIFY_API_BASE_URL . '/message/sendMedia/' . $sender;

        $payload = wp_json_encode( array(
            'number' => preg_replace( '/\D/', '', $receiver ),
            'mediatype' => $media_type,
            'media' => $media,
        ));

        // check request content
        if ( JOINOTIFY_DEBUG_MODE ) {
            Logger::register_log( "send_message_media() sender: " . $sender );
            Logger::register_log( "send_message_media() receiver: " . $receiver );
            Logger::register_log( "send_message_media() media_type: " . $media_type );
            Logger::register_log( "send_message_media() media: " . $media );
        }

        if ( ! License::is_valid() ) {
            return;
        }

        $response = wp_remote_post( $api_url, array(
            'headers' => array(
                'Content-Type' => 'application/json',
                'apikey' => Helpers::whatsapp_api_key(),
            ),
            'body' => $payload,
            'timeout' => 10,
        ));

        if ( is_wp_error( $response ) ) {
            Logger::register_log( $response, 'ERROR' );
        }

        $response_body = wp_remote_retrieve_body( $response );

        // record the response body for debug
        if ( JOINOTIFY_DEBUG_MODE ) {
            Logger::register_log( "send_message_media() response body: " . $response_body );
        }

        return wp_remote_retrieve_response_code( $response );
    }


    /**
     * Send OTP messsage text on WhatsApp
     * 
     * @since 1.0.0
     * @version 1.1.0
     * @param string $phone | Phone number
     * @param string $otp | OTP code
     * @return int
     */
    public static function send_validation_otp( $phone, $otp ) {
        $api_url = JOINOTIFY_API_BASE_URL . '/message/sendText/meumouse';
        $message = sprintf( __( 'Seu código de verificação do Joinotify é: %s', 'joinotify' ), $otp );

        $payload = wp_json_encode( array(
            'number' => $phone,
            'linkPreview' => false,
            'text' => $message,
        ));

        $response = wp_remote_post( $api_url, array(
            'headers' => array(
                'Content-Type' => 'application/json',
                'apikey' => Helpers::whatsapp_api_key(),
            ),
            'body' => $payload,
            'timeout' => 10,
        ));

        if ( is_wp_error( $response ) ) {
            Logger::register_log( $response, 'ERROR' );
        }

        $response_body = wp_remote_retrieve_body( $response );

        return wp_remote_retrieve_response_code( $response );
    }


    /**
     * Get all groups
     * 
     * @since 1.1.0
     * @param string $sender | Instance phone number
     */
    public static function fetch_all_groups( $sender ) {
        /**
         * Query param for fetch group partipants
         * 
         * @since 1.1.0
         * @return string
         */
        $get_participants = apply_filters( 'Joinotify/API/Fetch_Group_Participants', 'false' );

        // Ensure the value is a 'true' or 'false' string for the API
        $get_participants = filter_var( $get_participants, FILTER_VALIDATE_BOOLEAN ) ? 'true' : 'false';

        $sender = preg_replace( '/\D/', '', $sender );
        $api_url = JOINOTIFY_API_BASE_URL . '/group/fetchAllGroups/' . $sender . '?getParticipants=' . $get_participants;

        $response = wp_remote_get( $api_url, array(
            'headers' => array(
                'Content-Type' => 'application/json',
                'apikey' => Helpers::whatsapp_api_key(),
            ),
            'timeout' => 10,
        ));

        if ( is_wp_error( $response ) ) {
            Logger::register_log( $response, 'ERROR' );
        }

        $response_body = wp_remote_retrieve_body( $response );

        // record the response body for debug
        if ( JOINOTIFY_DEBUG_MODE ) {
            Logger::register_log( "fetch_all_groups() response body: " . $response_body );
        }

        return json_decode( $response_body, true );
    }
}