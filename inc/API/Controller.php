<?php

namespace MeuMouse\Joinotify\API;

use MeuMouse\Joinotify\Admin\Admin;
use MeuMouse\Joinotify\Core\Helpers;
use MeuMouse\Joinotify\Core\Logger;
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
 * @version 1.2.0
 * @package MeuMouse.com
 */
class Controller {

    /**
     * WhatsApp API key
     * 
     * @since 1.1.0
     * @return string
     */
    private static $whatsapp_api_key;

    /**
     * Construct function
     * 
     * @since 1.0.0
     * @version 1.1.0
     * @return void
     */
    public function __construct() {
        self::$whatsapp_api_key = Helpers::whatsapp_api_key();

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
     * Send a text message on WhatsApp from Proxy API
     *
     * @since 1.0.0
     * @version 1.2.0
     * @param WP_REST_Request $request
     * @return WP_REST_Response
     */
    public static function send_text_message( WP_REST_Request $request ) {
        $sender = $request->get_param('sender');
        $receiver = $request->get_param('receiver');
        $message = $request->get_param('message');
        $delay = $request->get_param('delay');
        $delay = is_numeric( $delay ) ? (int) $delay : 0;
        $response_code = self::send_message_text( $sender, $receiver, $message, $delay );

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
     * Send message media on WhatsApp from Proxy API
     *
     * @since 1.0.0
     * @version 1.2.0
     * @param WP_REST_Request $request
     * @return WP_REST_Response
     */
    public static function send_media_message( WP_REST_Request $request ) {
        $sender = $request->get_param('sender' );
        $receiver = $request->get_param('receiver');
        $media_type = $request->get_param('media_type');
        $media_url = $request->get_param('media_url');
        $delay = $request->get_param('delay');
        $delay = is_numeric( $delay ) ? (int) $delay : 0;
        $response_code = self::send_message_media( $sender, $receiver, $media_type, $media_url, $delay );

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

        // Check response body
        if ( JOINOTIFY_DEV_MODE ) {
            error_log( 'get_numbers() response body: ' . print_r( $response_body, true ) );
        }

        return json_decode( $response_body, true );
    }


    /**
     * Get connection state of an instance
     *
     * @since 1.0.0
     * @version 1.2.0
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

        // Check response body
        if ( JOINOTIFY_DEV_MODE ) {
            error_log( 'get_connection_state() response body: ' . print_r( $response_body, true ) );
        }

        $phone_status = json_decode( $response_body, true );

        if ( isset( $phone_status['status'] ) && $phone_status['status'] === 'success' ) {
            if ( $phone_status['connection'] === 'connected' ) {
                $status = 'connected';
            } elseif ( $phone_status['connection'] === 'disconnected' ) {
                $status = 'disconnected';
            }

            if ( JOINOTIFY_DEBUG_MODE ) {
                Logger::register_log( "Connection state for $phone is $status", 'INFO' );
            }

            update_option( 'joinotify_status_connection_'. $phone, $status );
        }

        // retrieve the response body that associative array
        return $phone_status;
    }


    /**
     * Send messsage text on WhatsApp
     * 
     * @since 1.0.0
     * @version 1.2.0
     * @param string $sender | Instance phone number
     * @param string $receiver | Phone number for receive message
     * @param string $message | Message text for send
     * @param int $timestamp_delay | Delay in miliseconds for send message
     * @return int
     */
    public static function send_message_text( $sender, $receiver, $message, $timestamp_delay = 0 ) {
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
            'number' => joinotify_prepare_receiver( $receiver ),
            'linkPreview' => $link_preview,
            'text' => $message,
            'delay' => $timestamp_delay,
        ));

        if ( ! License::is_valid() ) {
            return;
        }

        $response = wp_remote_post( $api_url, array(
            'headers' => array(
                'Content-Type' => 'application/json',
                'apikey' => self::$whatsapp_api_key,
            ),
            'body' => $payload,
            'timeout' => 10,
        ));

        if ( is_wp_error( $response ) ) {
            Logger::register_log( $response, 'ERROR' );
        }

        $response_body = wp_remote_retrieve_body( $response );

        // Check response body
        if ( JOINOTIFY_DEV_MODE ) {
            error_log( 'send_message_text() response body: ' . print_r( $response_body, true ) );
        }

        return wp_remote_retrieve_response_code( $response );
    }


    /**
     * Send messsage media on WhatsApp
     * 
     * @since 1.0.0
     * @version 1.2.0
     * @param string $sender | Instance phone number
     * @param string $receiver | Phone number for receive message
     * @param string $media_type | Media type (image, audio, video or document)
     * @param string $media | Media URL
     * @param int $timestamp_delay | Delay in miliseconds for send message
     * @return int
     */
    public static function send_message_media( $sender, $receiver, $media_type, $media, $timestamp_delay = 0 ) {
        $sender = preg_replace( '/\D/', '', $sender );

        // Chek if media type is audio and change request url
        if ( $media_type === 'audio' ) {
            self::send_whatsapp_audio( $sender, $receiver, $media, $timestamp_delay );

            return;
        }

        $api_url = JOINOTIFY_API_BASE_URL . '/message/sendMedia/' . $sender;

        $payload = wp_json_encode( array(
            'number' => joinotify_prepare_receiver( $receiver ),
            'mediatype' => $media_type,
            'media' => $media,
            'delay' => $timestamp_delay,
        ));

        if ( ! License::is_valid() ) {
            return;
        }

        $response = wp_remote_post( $api_url, array(
            'headers' => array(
                'Content-Type' => 'application/json',
                'apikey' => self::$whatsapp_api_key,
            ),
            'body' => $payload,
            'timeout' => 10,
        ));

        if ( is_wp_error( $response ) ) {
            Logger::register_log( $response, 'ERROR' );
        }

        $response_body = wp_remote_retrieve_body( $response );

        // Check response body
        if ( JOINOTIFY_DEV_MODE ) {
            error_log( 'send_message_media() response body: ' . print_r( $response_body, true ) );
        }

        return wp_remote_retrieve_response_code( $response );
    }


    /**
     * Send messsage audio on WhatsApp
     * 
     * @since 1.1.0
     * @version 1.2.0
     * @param string $sender | Instance phone number
     * @param string $receiver | Phone number for receive message
     * @param string $audio | Audio URL
     * @param int $timestamp_delay | Delay in miliseconds for send message
     * @return int
     */
    public static function send_whatsapp_audio( $sender, $receiver, $audio, $timestamp_delay = 0 ) {
        $sender = preg_replace( '/\D/', '', $sender );
        $api_url = JOINOTIFY_API_BASE_URL . '/message/sendWhatsAppAudio/' . $sender;

        /**
         * Filter for encoding audio
         * 
         * @since 1.1.0
         * @return bool
         */
        $encoding = apply_filters( 'Joinotify/API/Send_Whatsapp_Audio/Encoding', true );

        $payload = wp_json_encode( array(
            'number' => joinotify_prepare_receiver( $receiver ),
            'audio' => $audio,
            'delay' => $timestamp_delay,
            'encoding' => $encoding,
        ));

        if ( ! License::is_valid() ) {
            return;
        }

        // send request
        $response = wp_remote_post( $api_url, array(
            'headers' => array(
                'Content-Type' => 'application/json',
                'apikey' => self::$whatsapp_api_key,
            ),
            'body' => $payload,
            'timeout' => 10,
        ));

        if ( is_wp_error( $response ) ) {
            Logger::register_log( $response, 'ERROR' );
        }

        $response_body = wp_remote_retrieve_body( $response );

        // Check response body
        if ( JOINOTIFY_DEV_MODE ) {
            error_log( 'send_whatsapp_audio() response body: ' . print_r( $response_body, true ) );
        }

        return wp_remote_retrieve_response_code( $response );
    }


    /**
     * Send OTP messsage text on WhatsApp
     * 
     * @since 1.0.0
     * @version 1.2.0
     * @param string $phone | Phone number
     * @param string $otp | OTP code
     * @return int
     */
    public static function send_validation_otp( $phone, $otp ) {
        $api_url = JOINOTIFY_API_BASE_URL . '/message/sendText/meumouse';
        $message = sprintf( esc_html__( 'Seu código de verificação do Joinotify é: %s', 'joinotify' ), $otp );

        $response = wp_remote_post( $api_url, array(
            'headers' => array(
                'Content-Type' => 'application/json',
                'apikey' => self::$whatsapp_api_key,
            ),
            'body' => wp_json_encode( array(
                'number' => joinotify_prepare_receiver( $phone ),
                'linkPreview' => false,
                'text' => $message,
            )),
            'timeout' => 10,
        ));

        // Check if the response is an error
        if ( JOINOTIFY_DEV_MODE ) {
            error_log( 'send_validation_otp() response: ' . print_r( $response, true ) );
        }

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
                'apikey' => self::$whatsapp_api_key,
            ),
            'timeout' => 30,
        ));

        if ( is_wp_error( $response ) ) {
            Logger::register_log( $response, 'ERROR' );
        }

        // Check if the response is an error
        if ( JOINOTIFY_DEV_MODE ) {
            error_log( 'fetch_all_groups() response: ' . print_r( $response, true ) );
        }

        $response_body = wp_remote_retrieve_body( $response );

        // record the response body for debug
        if ( JOINOTIFY_DEBUG_MODE ) {
            Logger::register_log( "fetch_all_groups() response body: " . $response_body );
        }

        return json_decode( $response_body, true );
    }
}