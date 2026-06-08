<?php

namespace MeuMouse\Joinotify\Api;

use MeuMouse\Joinotify\Admin\Admin;
use MeuMouse\Joinotify\Core\Helpers;
use MeuMouse\Joinotify\Core\Logger;
use MeuMouse\Joinotify\Core\Notification_Queue;
use MeuMouse\Joinotify\Core\Message_History;

// Exit if accessed directly.
defined('ABSPATH') || exit;

/**
 * Controller for API requests
 * 
 * @since 1.0.0
 * @version 1.4.7
 * @package MeuMouse\Joinotify\API
 * @author MeuMouse.com
 */
class Controller {

    /**
     * Check debug mode
     * 
     * @since 1.3.0
     * @return bool
     */
    public static $debug_mode;

    /**
     * Check development mode
     * 
     * @since 1.3.0
     * @return bool
     */
    public static $dev_mode;

    /**
     * Get base API URL
     * 
     * @since 1.3.0
     * @return string
     */
    public static $base_api_url;

    /**
     * Get base API key
     * 
     * @since 1.3.0
     * @return string
     */
    public static $base_api_key;

    /**
     * Initialize runtime API flags and shared configuration.
     *
     * @since 1.0.0
     * @version 1.4.7
     * @return void
     */
    public function __construct() {
        self::$debug_mode = defined('JOINOTIFY_DEBUG_MODE') ? JOINOTIFY_DEBUG_MODE : false;
        self::$dev_mode = defined('JOINOTIFY_DEV_MODE') ? JOINOTIFY_DEV_MODE : false;
        self::$base_api_url = defined('JOINOTIFY_API_BASE_URL') ? JOINOTIFY_API_BASE_URL : '';
        self::$base_api_key = Helpers::slots_manager_api_key();
    }


    /**
     * Records the routes of API endpoints
     * 
     * @since 1.0.0
     * @version 1.4.7
     * @return void
     */
    public function register_routes() {
        $send_text_endpoint = '/'. Admin::get_setting('send_text_proxy_api_route');

        register_rest_route( 'joinotify/v1', $send_text_endpoint, array(
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

        $send_media_endpoint = '/'. Admin::get_setting('send_media_proxy_api_route');

        register_rest_route( 'joinotify/v1', $send_media_endpoint, array(
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
     * Validate if the given parameter is a string
     *
     * @since 1.0.0
     * @version 1.4.7
     * @param mixed            $param  The value to validate.
     * @param WP_REST_Request $request Optional. The REST request object.
     * @param string          $key     Optional. Parameter name.
     *
     * @return bool True if the parameter is a string, false otherwise.
     */
    public function validate_string( $param, ?WP_REST_Request $request = null, $key = '' ) {
        return is_string( $param );
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
            __('Invalid or missing API key.', 'joinotify'),
            array('status' => 403)
        );
    }


    /**
     * Get full API URL
     * 
     * @since 1.3.0
     * @param string $route | Partial route
     * @param string $endpoint | Endpoint
     * @param string $query_param | Optional partial route
     * @return string
     */
    public static function get_api_url( $route, $endpoint, $query_param = '' ) {
        return self::$base_api_url . $route . $endpoint . $query_param;
    }


    /**
     * Get server details from phone number (with 1 week cache)
     * 
     * @since 1.3.0
     * @param string $phone | Phone number
     * @return string|WP_Error | Server URL or WP_Error on failure
     */
    public static function get_server_details( $phone ) {
        $cache_key = 'joinotify_server_details_' . md5( $phone );
        $cached = get_transient( $cache_key );

        if ( false !== $cached ) {
            return $cached;
        }

        // get api url from slots manager api
        $api_url = self::get_api_url( '/servers', '/get-server-by-phone/', $phone );

        // send request
        $response = wp_remote_get( $api_url, array(
            'headers' => array(
                'Content-Type' => 'application/json',
                'apikey' => self::$base_api_key,
            ),
            'timeout' => 30,
        ));

        if ( is_wp_error( $response ) ) {
            error_log( print_r( $response, true ) );
            return $response;
        }

        $response_body = wp_remote_retrieve_body( $response );

        if ( self::$debug_mode ) {
            error_log( "get_server_details() response: " . $response_body );
        }

        $data = json_decode( $response_body, true );

        // set response cahce for 1 week
        set_transient( $cache_key, $data, WEEK_IN_SECONDS );

        return $data;
    }


    /**
     * Get instance route URL for registered server
     * 
     * @since 1.3.0
     * @param string $route | API route
     * @param string $phone | Instance phone number
     * @return string
     */
    public static function get_instance_route_url( $route, $phone ) {
        $server_details = self::get_server_details( $phone );
        $base_api_url = $server_details['server']['link'] ?? '';
        
        return $base_api_url . $route . $phone;
    }


    /**
     * Send a text message on WhatsApp from Proxy API
     *
     * @since 1.0.0
     * @version 1.4.7
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
                'message' => __( 'Text message sent successfully.', 'joinotify' ),
            ), 200 );
        } else {
            return new WP_REST_Response( array(
                'status' => 'error',
                'message' => __( 'Failed to send text message.', 'joinotify' ),
            ), 500 );
        }
    }


    /**
     * Send message media on WhatsApp from Proxy API
     *
     * @since 1.0.0
     * @version 1.4.7
     * @param WP_REST_Request $request
     * @return WP_REST_Response
     */
    public static function send_media_message( WP_REST_Request $request ) {
        $sender = $request->get_param('sender' );
        $receiver = $request->get_param('receiver');
        $media_type = $request->get_param('media_type');
        $media_url = $request->get_param('media_url');
        $caption = $request->get_param('caption');
        $delay = $request->get_param('delay');
        $delay = is_numeric( $delay ) ? (int) $delay : 0;
        $response_code = self::send_message_media( $sender, $receiver, $media_type, $media_url, $caption, $delay );

        if ( 201 === $response_code ) {
            return new \WP_REST_Response( array(
                'status' => 'success',
                'message' => __( 'Media message sent successfully.', 'joinotify' ),
            ), 200 );
        } else {
            return new \WP_REST_Response( array(
                'status' => 'error',
                'message' => __( 'Failed to send media message.', 'joinotify' ),
            ), 500 );
        }
    }


    /**
     * Get numbers registered on slots
     *
     * @since 1.0.0
     * @version 1.4.7
     * @return array
     */
    public static function get_numbers() {
        $license = get_option('joinotify_license_key') ?? '';
        $api_url = self::get_api_url( '/slots', '/get-all-phones/', $license );

        $response = wp_remote_get( $api_url, array(
            'timeout' => 30,
        ));

        if ( is_wp_error( $response ) ) {
            Logger::register_log( $response, 'ERROR' );
        }

        $response_body = wp_remote_retrieve_body( $response );

        // Check response body
        if ( self::$dev_mode ) {
            error_log( 'get_numbers() response body: ' . print_r( $response_body, true ) );
        }

        return json_decode( $response_body, true );
    }


    /**
     * Get connection state of an instance
     *
     * @since 1.0.0
     * @version 1.4.7
     * @param string $phone | Phone number
     * @return array|WP_Error Response from API or WP_Error on failure
     */
    public static function get_connection_state( $phone ) {
        $api_url = self::get_api_url( '/slots', '/check-phone-connection/', $phone );

        // send request
        $response = wp_remote_get( $api_url, array(
            'timeout' => 30,
        ));

        if ( is_wp_error( $response ) ) {
            Logger::register_log( $response, 'ERROR' );
        }

        $response_body = wp_remote_retrieve_body( $response );

        // Check response body
        if ( self::$dev_mode ) {
            error_log( 'get_connection_state() response body: ' . print_r( $response_body, true ) );
        }

        $phone_status = json_decode( $response_body, true );

        if ( isset( $phone_status['status'] ) && $phone_status['status'] === 'success' ) {
            if ( $phone_status['connection'] === 'connected' ) {
                $status = 'connected';
            } elseif ( $phone_status['connection'] === 'disconnected' ) {
                $status = 'disconnected';

                self::notify_disconnected_phone( $phone );
            }

            if ( self::$debug_mode ) {
                Logger::register_log( "Connection state for $phone is $status", 'INFO' );
            }
        } else {
            $status = 'disconnected';

            self::notify_disconnected_phone( $phone );
        }

        update_option( 'joinotify_status_connection_'. $phone, $status );

        // retrieve the response body that associative array
        return $phone_status;
    }


    /**
     * Build normalized response details.
     *
     * @since 1.4.7
     * @param int $response_code | HTTP response code.
     * @param bool $success | Operation status.
     * @param bool $retryable | If failure can be retried.
     * @param string $error | Failure reason.
     * @param bool $queued | If item was enqueued.
     * @return array
     */
    private static function build_response_details( $response_code, $success, $retryable = false, $error = '', $queued = false ) {
        return array(
            'response_code' => (int) $response_code,
            'success' => (bool) $success,
            'retryable' => (bool) $retryable,
            'error' => (string) $error,
            'queued' => (bool) $queued,
        );
    }


    /**
     * Check if a response code should be retried.
     *
     * @since 1.4.7
     * @param int $response_code | HTTP response code.
     * @return bool
     */
    private static function should_retry_response_code( $response_code ) {
        $response_code = (int) $response_code;

        if ( 0 === $response_code ) {
            return true;
        }

        if ( $response_code >= 500 ) {
            return true;
        }

        return in_array( $response_code, array( 408, 409, 425, 429 ), true );
    }


    /**
     * Record a dispatch in the message history and return the original value.
     *
     * Centralizes history logging across every return path of the send methods,
     * so success, queued and failed dispatches are all captured uniformly while
     * preserving each method's original return contract (details array or code).
     *
     * @since 2.0.0
     * @param array $fields | Message fields (sender, receiver, message_type, media_type, content, media_url, attempts).
     * @param array $details | Normalized response details from build_response_details().
     * @param bool $return_details | Whether the caller expects the details array.
     * @return int|array
     */
    private static function record_and_return( $fields, $details, $return_details ) {
        if ( ! empty( $details['success'] ) ) {
            $status = 'sent';
        } elseif ( ! empty( $details['queued'] ) ) {
            $status = 'queued';
        } else {
            $status = 'failed';
        }

        Message_History::record( array_merge( $fields, array(
            'status' => $status,
            'response_code' => (int) ( $details['response_code'] ?? 0 ),
            'error' => (string) ( $details['error'] ?? '' ),
        )));

        return $return_details ? $details : (int) ( $details['response_code'] ?? 0 );
    }


    /**
     * Send messsage text on WhatsApp
     *
     * @since 1.0.0
     * @version 1.4.7
     * @param string $sender | Instance phone number
     * @param string $receiver | Phone number for receive message
     * @param string $message | Message text for send
     * @param int $timestamp_delay | Delay in miliseconds for send message
     * @param bool $queue_on_failure | Enqueue retry item on failure
     * @param bool $return_details | Return normalized details instead response code
     * @return int|array
     */
    public static function send_message_text( $sender, $receiver, $message, $timestamp_delay = 0, $queue_on_failure = true, $return_details = false ) {
        $sender = preg_replace( '/\D/', '', $sender );
        $receiver = joinotify_prepare_receiver( $receiver );

        // history fields recorded on every return path
        $fields = array(
            'message_type' => 'text',
            'sender' => $sender,
            'receiver' => $receiver,
            'content' => $message,
        );

        // check if sender is registered
        if ( ! Helpers::allowed_sender( $sender ) ) {
            if ( self::$debug_mode ) {
                Logger::register_log( "Message not sent. Sender's phone number not registered.", 'INFO' );
            }

            $details = self::build_response_details( 0, false, false, 'invalid_sender' );
            return self::record_and_return( $fields, $details, $return_details );
        }

        if ( ! License::is_valid() ) {
            if ( self::$debug_mode ) {
                Logger::register_log( 'Stopping send message text because license is invalid', 'INFO' );
            }

            $queued = false;

            if ( $queue_on_failure ) {
                $queued = (bool) Notification_Queue::enqueue( 'text', array(
                    'sender' => $sender,
                    'receiver' => $receiver,
                    'message' => $message,
                    'delay' => $timestamp_delay,
                ), 'license_invalid' );
            }

            $details = self::build_response_details( 0, false, true, 'license_invalid', $queued );
            return self::record_and_return( $fields, $details, $return_details );
        }

        $server_details = self::get_server_details( $sender );

        if ( is_wp_error( $server_details ) ) {
            $queued = false;

            if ( $queue_on_failure ) {
                $queued = (bool) Notification_Queue::enqueue( 'text', array(
                    'sender' => $sender,
                    'receiver' => $receiver,
                    'message' => $message,
                    'delay' => $timestamp_delay,
                ), $server_details->get_error_message() );
            }

            $details = self::build_response_details( 0, false, true, $server_details->get_error_message(), $queued );
            return self::record_and_return( $fields, $details, $return_details );
        }

        // get endpoint for send message text
        $api_url = self::get_instance_route_url( '/message/sendText/', $sender );

        // send request
        $response = wp_remote_post( $api_url, array(
            'headers' => array(
                'Content-Type' => 'application/json',
                'apikey' => $server_details['server']['token'] ?? '',
            ),
            'body' => wp_json_encode( array(
                'number' => $receiver,
                'linkPreview' => apply_filters( 'Joinotify/API/Send_Message_Text/Link_Preview', true ),
                'text' => $message,
                'delay' => $timestamp_delay,
            )),
            'timeout' => 30,
        ));

        if ( is_wp_error( $response ) ) {
            Logger::register_log( $response, 'ERROR' );

            $queued = false;

            if ( $queue_on_failure ) {
                $queued = (bool) Notification_Queue::enqueue( 'text', array(
                    'sender' => $sender,
                    'receiver' => $receiver,
                    'message' => $message,
                    'delay' => $timestamp_delay,
                ), $response->get_error_message() );
            }

            $details = self::build_response_details( 0, false, true, $response->get_error_message(), $queued );
            return self::record_and_return( $fields, $details, $return_details );
        }

        $response_body = wp_remote_retrieve_body( $response );
        $response_code = (int) wp_remote_retrieve_response_code( $response );

        // Check response body
        if ( self::$debug_mode ) {
            Logger::register_log( 'send_message_text() response body: ' . print_r( $response_body, true ) );
        }

        $success = ( 201 === $response_code );
        $retryable = ( ! $success && self::should_retry_response_code( $response_code ) );
        $queued = false;

        if ( $queue_on_failure && $retryable ) {
            $queued = (bool) Notification_Queue::enqueue( 'text', array(
                'sender' => $sender,
                'receiver' => $receiver,
                'message' => $message,
                'delay' => $timestamp_delay,
            ), 'api_unavailable_' . $response_code );
        }

        $details = self::build_response_details( $response_code, $success, $retryable, $success ? '' : 'http_' . $response_code, $queued );

        return self::record_and_return( $fields, $details, $return_details );
    }


    /**
     * Send messsage media on WhatsApp
     * 
     * @since 1.0.0
     * @version 1.4.7
     * @param string $sender | Instance phone number
     * @param string $receiver | Phone number for receive message
     * @param string $media_type | Media type (image, audio, video or document)
     * @param string $media | Media URL
     * @param string $caption | Media caption (optional)
     * @param int $timestamp_delay | Delay in miliseconds for send message (optional)
     * @param bool $queue_on_failure | Enqueue retry item on failure
     * @param bool $return_details | Return normalized details instead response code
     * @return int|array
     */
    public static function send_message_media( $sender, $receiver, $media_type, $media, $caption = '', $timestamp_delay = 0, $queue_on_failure = true, $return_details = false ) {
        $sender = preg_replace( '/\D/', '', $sender );
        $receiver = joinotify_prepare_receiver( $receiver );

        // history fields recorded on every return path
        $fields = array(
            'message_type' => 'media',
            'sender' => $sender,
            'receiver' => $receiver,
            'media_type' => $media_type,
            'media_url' => $media,
            'content' => $caption,
        );

        // check if sender is registered
        if ( ! Helpers::allowed_sender( $sender ) ) {
            if ( self::$debug_mode ) {
                Logger::register_log( "Message not sent. Sender's phone number not registered.", 'INFO' );
            }

            $details = self::build_response_details( 0, false, false, 'invalid_sender' );
            return self::record_and_return( $fields, $details, $return_details );
        }

        // Chek if media type is audio and change request url
        if ( $media_type === 'audio' ) {
            return self::send_whatsapp_audio( $sender, $receiver, $media, $timestamp_delay, $queue_on_failure, $return_details );
        }

        if ( ! License::is_valid() ) {
            if ( self::$debug_mode ) {
                Logger::register_log( 'Stopping send message media because license is invalid', 'INFO' );
            }

            $queued = false;

            if ( $queue_on_failure ) {
                $queued = (bool) Notification_Queue::enqueue( 'media', array(
                    'sender' => $sender,
                    'receiver' => $receiver,
                    'media_type' => $media_type,
                    'media' => $media,
                    'caption' => $caption,
                    'delay' => $timestamp_delay,
                ), 'license_invalid' );
            }

            $details = self::build_response_details( 0, false, true, 'license_invalid', $queued );
            return self::record_and_return( $fields, $details, $return_details );
        }

        $server_details = self::get_server_details( $sender );

        if ( is_wp_error( $server_details ) ) {
            $queued = false;

            if ( $queue_on_failure ) {
                $queued = (bool) Notification_Queue::enqueue( 'media', array(
                    'sender' => $sender,
                    'receiver' => $receiver,
                    'media_type' => $media_type,
                    'media' => $media,
                    'caption' => $caption,
                    'delay' => $timestamp_delay,
                ), $server_details->get_error_message() );
            }

            $details = self::build_response_details( 0, false, true, $server_details->get_error_message(), $queued );
            return self::record_and_return( $fields, $details, $return_details );
        }

        // get endpoint for send message media
        $api_url = self::get_instance_route_url( '/message/sendMedia/', $sender );

        // send request
        $response = wp_remote_post( $api_url, array(
            'headers' => array(
                'Content-Type' => 'application/json',
                'apikey' => $server_details['server']['token'] ?? '',
            ),
            'body' => wp_json_encode( array(
                'number' => $receiver,
                'mediatype' => $media_type,
                'caption' => $caption,
                'media' => $media,
                'delay' => $timestamp_delay,
            )),
            'timeout' => 30,
        ));

        if ( is_wp_error( $response ) ) {
            Logger::register_log( $response, 'ERROR' );

            $queued = false;

            if ( $queue_on_failure ) {
                $queued = (bool) Notification_Queue::enqueue( 'media', array(
                    'sender' => $sender,
                    'receiver' => $receiver,
                    'media_type' => $media_type,
                    'media' => $media,
                    'caption' => $caption,
                    'delay' => $timestamp_delay,
                ), $response->get_error_message() );
            }

            $details = self::build_response_details( 0, false, true, $response->get_error_message(), $queued );
            return self::record_and_return( $fields, $details, $return_details );
        }

        $response_body = wp_remote_retrieve_body( $response );
        $response_code = (int) wp_remote_retrieve_response_code( $response );

        // Check response body
        if ( self::$debug_mode ) {
            Logger::register_log( 'send_message_media() response body: ' . $response_body );
        }

        $success = ( 201 === $response_code );
        $retryable = ( ! $success && self::should_retry_response_code( $response_code ) );
        $queued = false;

        if ( $queue_on_failure && $retryable ) {
            $queued = (bool) Notification_Queue::enqueue( 'media', array(
                'sender' => $sender,
                'receiver' => $receiver,
                'media_type' => $media_type,
                'media' => $media,
                'caption' => $caption,
                'delay' => $timestamp_delay,
            ), 'api_unavailable_' . $response_code );
        }

        $details = self::build_response_details( $response_code, $success, $retryable, $success ? '' : 'http_' . $response_code, $queued );

        return self::record_and_return( $fields, $details, $return_details );
    }


    /**
     * Send messsage audio on WhatsApp
     * 
     * @since 1.1.0
     * @version 1.4.7
     * @param string $sender | Instance phone number
     * @param string $receiver | Phone number for receive message
     * @param string $audio | Audio URL
     * @param int $timestamp_delay | Delay in miliseconds for send message
     * @param bool $queue_on_failure | Enqueue retry item on failure
     * @param bool $return_details | Return normalized details instead response code
     * @return int|array
     */
    public static function send_whatsapp_audio( $sender, $receiver, $audio, $timestamp_delay = 0, $queue_on_failure = true, $return_details = false ) {
        $sender = preg_replace( '/\D/', '', $sender );
        $receiver = joinotify_prepare_receiver( $receiver );

        // history fields recorded on every return path
        $fields = array(
            'message_type' => 'audio',
            'sender' => $sender,
            'receiver' => $receiver,
            'media_type' => 'audio',
            'media_url' => $audio,
        );

        // check if sender is registered
        if ( ! Helpers::allowed_sender( $sender ) ) {
            if ( self::$debug_mode ) {
                Logger::register_log( "Message not sent. Sender's phone number not registered.", 'INFO' );
            }

            $details = self::build_response_details( 0, false, false, 'invalid_sender' );
            return self::record_and_return( $fields, $details, $return_details );
        }

        if ( ! License::is_valid() ) {
            if ( self::$debug_mode ) {
                Logger::register_log( 'Stopping send message audio because license is invalid', 'INFO' );
            }

            $queued = false;

            if ( $queue_on_failure ) {
                $queued = (bool) Notification_Queue::enqueue( 'audio', array(
                    'sender' => $sender,
                    'receiver' => $receiver,
                    'audio' => $audio,
                    'delay' => $timestamp_delay,
                ), 'license_invalid' );
            }

            $details = self::build_response_details( 0, false, true, 'license_invalid', $queued );
            return self::record_and_return( $fields, $details, $return_details );
        }

        $server_details = self::get_server_details( $sender );

        if ( is_wp_error( $server_details ) ) {
            $queued = false;

            if ( $queue_on_failure ) {
                $queued = (bool) Notification_Queue::enqueue( 'audio', array(
                    'sender' => $sender,
                    'receiver' => $receiver,
                    'audio' => $audio,
                    'delay' => $timestamp_delay,
                ), $server_details->get_error_message() );
            }

            $details = self::build_response_details( 0, false, true, $server_details->get_error_message(), $queued );
            return self::record_and_return( $fields, $details, $return_details );
        }

        // get endpoint for send message audio
        $api_url = self::get_instance_route_url( '/message/sendWhatsAppAudio/', $sender );

        // send request
        $response = wp_remote_post( $api_url, array(
            'headers' => array(
                'Content-Type' => 'application/json',
                'apikey' => $server_details['server']['token'] ?? '',
            ),
            'body' => wp_json_encode( array(
                'number' => $receiver,
                'audio' => $audio,
                'delay' => $timestamp_delay,
                'encoding' => apply_filters( 'Joinotify/API/Send_Whatsapp_Audio/Encoding', true ),
            )),
            'timeout' => 30,
        ));

        if ( is_wp_error( $response ) ) {
            Logger::register_log( $response, 'ERROR' );

            $queued = false;

            if ( $queue_on_failure ) {
                $queued = (bool) Notification_Queue::enqueue( 'audio', array(
                    'sender' => $sender,
                    'receiver' => $receiver,
                    'audio' => $audio,
                    'delay' => $timestamp_delay,
                ), $response->get_error_message() );
            }

            $details = self::build_response_details( 0, false, true, $response->get_error_message(), $queued );
            return self::record_and_return( $fields, $details, $return_details );
        }

        $response_body = wp_remote_retrieve_body( $response );
        $response_code = (int) wp_remote_retrieve_response_code( $response );

        // Check response body
        if ( self::$dev_mode ) {
            error_log( 'send_whatsapp_audio() response body: ' . print_r( $response_body, true ) );
        }

        $success = ( 201 === $response_code );
        $retryable = ( ! $success && self::should_retry_response_code( $response_code ) );
        $queued = false;

        if ( $queue_on_failure && $retryable ) {
            $queued = (bool) Notification_Queue::enqueue( 'audio', array(
                'sender' => $sender,
                'receiver' => $receiver,
                'audio' => $audio,
                'delay' => $timestamp_delay,
            ), 'api_unavailable_' . $response_code );
        }

        $details = self::build_response_details( $response_code, $success, $retryable, $success ? '' : 'http_' . $response_code, $queued );

        return self::record_and_return( $fields, $details, $return_details );
    }
    /**
     * Send OTP messsage text on WhatsApp
     * 
     * @since 1.0.0
     * @version 1.4.7
     * @param string $phone | Phone number
     * @param string $otp | OTP code
     * @return int
     */
    public static function send_validation_otp( $phone, $otp ) {
        $api_url = self::get_api_url( '/utils', '/send-otp-message' );

        // send request
        $response = wp_remote_post( $api_url, array(
            'headers' => array(
                'Content-Type' => 'application/json',
            ),
            'body' => wp_json_encode( array(
                'phone' => joinotify_prepare_receiver( $phone ),
                'code' => $otp,
            )),
            'timeout' => 30,
        ));

        // Check if the response is an error
        if ( self::$dev_mode ) {
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
     * @version 1.4.7
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
        $query_param = '?getParticipants=' . $get_participants;
        $api_url = self::get_instance_route_url( '/group/fetchAllGroups/', $sender . $query_param );
        $server_details = self::get_server_details( $sender );

        // send request
        $response = wp_remote_get( $api_url, array(
            'headers' => array(
                'Content-Type' => 'application/json',
                'apikey' => $server_details['server']['token'] ?? '',
            ),
            'timeout' => 30,
        ));

        if ( is_wp_error( $response ) ) {
            Logger::register_log( $response, 'ERROR' );
        }

        // Check if the response is an error
        if ( self::$dev_mode ) {
            error_log( 'fetch_all_groups() response: ' . print_r( $response, true ) );
        }

        $response_body = wp_remote_retrieve_body( $response );

        // record the response body for debug
        if ( self::$debug_mode ) {
            Logger::register_log( "fetch_all_groups() response body: " . $response_body );
        }

        return json_decode( $response_body, true );
    }


    /**
     * Notify user when phone is disconnected
     * 
     * @since 1.3.0
     * @version 1.4.7
     * @param string $phone | Phone number
     * @return int
     */
    public static function notify_disconnected_phone( $phone ) {
        // check if the notification is enabled
        if ( Admin::get_setting('enable_send_disconnect_notifications') !== 'yes' ) {
            return;
        }

        $api_url = self::get_api_url( '/utils', '/notify-disconnected-phone' );

        // send request
        $response = wp_remote_post( $api_url, array(
            'headers' => array(
                'Content-Type' => 'application/json',
            ),
            'body' => wp_json_encode( array(
                'phone' => joinotify_prepare_receiver( $phone ),
                'site' => License::get_domain(),
            )),
            'timeout' => 30,
        ));

        // Check if the response is an error
        if ( self::$dev_mode ) {
            error_log( 'notify_disconnected_phone() response: ' . print_r( $response, true ) );
        }

        if ( is_wp_error( $response ) ) {
            Logger::register_log( $response, 'ERROR' );
        }

        $response_body = wp_remote_retrieve_body( $response );

        return wp_remote_retrieve_response_code( $response );
    }
}

