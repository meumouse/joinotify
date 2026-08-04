<?php

namespace MeuMouse\Joinotify\Api;

use MeuMouse\Joinotify\Core\Helpers;
use MeuMouse\Joinotify\Core\Logger;
use MeuMouse\Joinotify\Core\Notification_Queue;

// Exit if accessed directly.
defined('ABSPATH') || exit;

/**
 * Client for the official WhatsApp Cloud API exposed by Joinotify.
 *
 * This is the migration target that replaces the Evolution/slots-manager relay
 * in Api\Controller. Authentication is a single account-level bearer token
 * (`sk_live_...`), the origin number is a `phone_number_id`, and every business
 * initiated message outside the 24h session window requires an approved
 * template (see send_template()).
 *
 * The send methods keep the same signatures and return contract as their
 * Controller counterparts (int response code, or the normalized details array
 * when $return_details is true) so Api\Transport can route to either transport
 * transparently. Shared history/retry plumbing comes from Message_Dispatch.
 *
 * @since 1.4.8
 * @package MeuMouse\Joinotify\Api
 * @author MeuMouse.com
 */
class Cloud_Client {

    use Message_Dispatch;

    /**
     * Base URL for the Cloud API.
     *
     * @since 1.4.8
     * @return string
     */
    public static function base_url() {
        $base = defined('JOINOTIFY_CLOUD_API_BASE_URL') ? JOINOTIFY_CLOUD_API_BASE_URL : 'https://api.joinotify.com';

        /**
         * Filter the Cloud API base URL.
         *
         * @since 1.4.8
         * @param string $base
         */
        return untrailingslashit( (string) apply_filters( 'Joinotify/Cloud_Api/Base_Url', $base ) );
    }


    /**
     * Whether verbose dev logging is on.
     *
     * @since 1.4.8
     * @return bool
     */
    protected static function dev_mode() {
        return defined('JOINOTIFY_DEV_MODE') && JOINOTIFY_DEV_MODE;
    }


    /**
     * Resolve the origin `phone_number_id` for a sender.
     *
     * Until the sender list carries per-number ids (sender model migration),
     * this returns the account-level default phone_number_id. A future mapping
     * of phone => phone_number_id is honored automatically when present.
     *
     * @since 1.4.8
     * @param string $sender | Sender phone number (digits).
     * @return string
     */
    public static function resolve_phone_number_id( $sender ) {
        $sender = preg_replace( '/\D/', '', (string) $sender );

        if ( class_exists( '\MeuMouse\Joinotify\Core\Phone_Manager' ) && method_exists( '\MeuMouse\Joinotify\Core\Phone_Manager', 'get_phone_number_id' ) ) {
            $mapped = \MeuMouse\Joinotify\Core\Phone_Manager::get_phone_number_id( $sender );

            if ( is_string( $mapped ) && '' !== $mapped ) {
                return $mapped;
            }
        }

        return Helpers::cloud_phone_number_id();
    }


    /**
     * Perform an authenticated request against the Cloud API.
     *
     * @since 1.4.8
     * @param string $method | HTTP method.
     * @param string $path | Path beginning with a slash (e.g. '/messages/send/text').
     * @param array|null $body | JSON body for write requests.
     * @param int $timeout | Request timeout in seconds.
     * @return array|\WP_Error | Raw wp_remote_* response or WP_Error.
     */
    public static function request( $method, $path, $body = null, $timeout = 30 ) {
        $token = Helpers::cloud_api_token();

        if ( '' === $token ) {
            return new \WP_Error( 'joinotify_cloud_no_token', __( 'No WhatsApp Cloud API token is configured.', 'joinotify' ) );
        }

        $args = array(
            'method' => strtoupper( $method ),
            'headers' => array(
                'Authorization' => 'Bearer ' . $token,
                'Content-Type' => 'application/json',
                'Accept' => 'application/json',
            ),
            'timeout' => $timeout,
        );

        if ( null !== $body ) {
            $args['body'] = wp_json_encode( $body );
        }

        return wp_remote_request( self::base_url() . $path, $args );
    }


    /**
     * Parse a send response into a normalized shape across both the simplified
     * endpoints (Joinotify envelope, 201 + data.messages[].id) and the Meta
     * mirror (Meta's raw body, 200 + messages[].id).
     *
     * @since 1.4.8
     * @param array|\WP_Error $response | wp_remote_* response.
     * @return array{code:int,wamid:string,error_type:string,meta_code:int,retry_after:int}
     */
    protected static function parse_send_response( $response ) {
        if ( is_wp_error( $response ) ) {
            return array( 'code' => 0, 'wamid' => '', 'error_type' => $response->get_error_message(), 'meta_code' => 0, 'retry_after' => 0 );
        }

        $code = (int) wp_remote_retrieve_response_code( $response );
        $body = json_decode( wp_remote_retrieve_body( $response ), true );
        $body = is_array( $body ) ? $body : array();

        // WAMID lives in data.messages[0].id (simplified) or messages[0].id (mirror).
        $messages = $body['data']['messages'] ?? ( $body['messages'] ?? array() );
        $wamid = ( is_array( $messages ) && isset( $messages[0]['id'] ) ) ? (string) $messages[0]['id'] : '';

        // Error type / Meta business-rule code, if any.
        $error_type = isset( $body['error']['type'] ) ? (string) $body['error']['type'] : '';
        $meta_code = 0;

        if ( isset( $body['error']['meta']['error']['code'] ) ) {
            $meta_code = (int) $body['error']['meta']['error']['code'];
        } elseif ( isset( $body['error']['code'] ) ) {
            // Meta mirror surfaces the raw Meta error directly.
            $meta_code = (int) $body['error']['code'];
        }

        $retry_after = (int) wp_remote_retrieve_header( $response, 'retry-after' );

        return array(
            'code' => $code,
            'wamid' => $wamid,
            'error_type' => $error_type,
            'meta_code' => $meta_code,
            'retry_after' => $retry_after,
        );
    }


    /**
     * Cloud-specific retry policy.
     *
     * Only transient failures are worth repeating: 429 (rate limit) and 5xx.
     * Authentication, permission, not-found, missing-number and validation
     * errors (401/403/404/409/422) never resolve on retry — the caller must
     * fix configuration or switch to a template.
     *
     * @since 1.4.8
     * @param int $response_code | HTTP response code.
     * @return bool
     */
    protected static function should_retry_response_code( $response_code ) {
        $response_code = (int) $response_code;

        if ( 0 === $response_code ) {
            return true; // transport failure (could not reach the API)
        }

        if ( 429 === $response_code ) {
            return true;
        }

        return $response_code >= 500;
    }


    /**
     * Human-readable, actionable error label for a failed send.
     *
     * @since 1.4.8
     * @param array $parsed | Result of parse_send_response().
     * @return string
     */
    protected static function describe_error( $parsed ) {
        // 24h window closed — a free-form message will never be delivered; a
        // template is required. This is the single most common production error.
        if ( 131047 === (int) $parsed['meta_code'] ) {
            return 'window_closed_requires_template';
        }

        if ( in_array( (int) $parsed['meta_code'], array( 132000, 132001, 132005, 132007, 132012, 132015, 132016 ), true ) ) {
            return 'template_error_' . $parsed['meta_code'];
        }

        if ( '' !== $parsed['error_type'] ) {
            return $parsed['error_type'];
        }

        return 'http_' . (int) $parsed['code'];
    }


    /**
     * Send a plain text message (only deliverable inside the 24h window).
     *
     * @since 1.4.8
     * @param string $sender | Origin phone number.
     * @param string $receiver | Recipient phone number.
     * @param string $message | Message body.
     * @param int $timestamp_delay | Ignored on the Cloud API (kept for signature parity).
     * @param bool $queue_on_failure | Enqueue retry item on failure.
     * @param bool $return_details | Return normalized details instead of response code.
     * @return int|array
     */
    public static function send_message_text( $sender, $receiver, $message, $timestamp_delay = 0, $queue_on_failure = true, $return_details = false ) {
        $sender = preg_replace( '/\D/', '', (string) $sender );
        $receiver = joinotify_prepare_receiver( $receiver );
        $message = joinotify_convert_html_to_whatsapp( $message );

        $fields = array(
            'message_type' => 'text',
            'sender' => $sender,
            'receiver' => $receiver,
            'content' => $message,
        );

        $guard = self::guard_send( $sender, $return_details, $fields, $queue_on_failure, 'text', array(
            'sender' => $sender,
            'receiver' => $receiver,
            'message' => $message,
            'delay' => $timestamp_delay,
        ));

        if ( null !== $guard ) {
            return $guard;
        }

        $body = array(
            'to' => $receiver,
            'body' => $message,
            'previewUrl' => (bool) apply_filters( 'Joinotify/API/Send_Message_Text/Link_Preview', true ),
        );

        $from = self::resolve_phone_number_id( $sender );

        if ( '' !== $from ) {
            $body['from'] = $from;
        }

        $response = self::request( 'POST', '/messages/send/text', $body );

        return self::finish_send( $response, $fields, $return_details, $queue_on_failure, 'text', array(
            'sender' => $sender,
            'receiver' => $receiver,
            'message' => $message,
            'delay' => $timestamp_delay,
        ));
    }


    /**
     * Send a media message (image, video, document) through the Meta mirror.
     *
     * Audio is routed to send_whatsapp_audio() to match Controller semantics.
     *
     * @since 1.4.8
     * @param string $sender | Origin phone number.
     * @param string $receiver | Recipient phone number.
     * @param string $media_type | image|video|document|audio.
     * @param string $media | Public media URL.
     * @param string $caption | Optional caption.
     * @param int $timestamp_delay | Ignored on the Cloud API.
     * @param bool $queue_on_failure | Enqueue retry item on failure.
     * @param bool $return_details | Return normalized details instead of response code.
     * @param string $file_name | Optional document file name.
     * @return int|array
     */
    public static function send_message_media( $sender, $receiver, $media_type, $media, $caption = '', $timestamp_delay = 0, $queue_on_failure = true, $return_details = false, $file_name = '' ) {
        $sender = preg_replace( '/\D/', '', (string) $sender );
        $receiver = joinotify_prepare_receiver( $receiver );
        $caption = joinotify_convert_html_to_whatsapp( $caption );

        if ( 'audio' === $media_type ) {
            return self::send_whatsapp_audio( $sender, $receiver, $media, $timestamp_delay, $queue_on_failure, $return_details );
        }

        $fields = array(
            'message_type' => 'media',
            'sender' => $sender,
            'receiver' => $receiver,
            'media_type' => $media_type,
            'media_url' => $media,
            'content' => $caption,
        );

        $queue_payload = array(
            'sender' => $sender,
            'receiver' => $receiver,
            'media_type' => $media_type,
            'media' => $media,
            'caption' => $caption,
            'delay' => $timestamp_delay,
            'file_name' => $file_name,
        );

        $guard = self::guard_send( $sender, $return_details, $fields, $queue_on_failure, 'media', $queue_payload );

        if ( null !== $guard ) {
            return $guard;
        }

        // The Meta mirror requires the phone_number_id in the path.
        $phone_number_id = self::resolve_phone_number_id( $sender );

        if ( '' === $phone_number_id ) {
            $details = self::build_response_details( 0, false, false, 'missing_phone_number_id' );
            return self::record_and_return( $fields, $details, $return_details );
        }

        // The Cloud API only accepts a public URL or a pre-uploaded mediaId — never
        // raw bytes. A workflow attachment for a local file arrives here base64
        // encoded (see Workflow_Processor::send_whatsapp_attachments), so it must
        // be uploaded first to obtain a mediaId.
        if ( preg_match( '#^https?://#i', (string) $media ) ) {
            $media_object = array( 'link' => $media );
        } else {
            $bytes = base64_decode( (string) $media, true );

            if ( false === $bytes || '' === $bytes ) {
                $details = self::build_response_details( 0, false, false, 'invalid_media' );
                return self::record_and_return( $fields, $details, $return_details );
            }

            $upload = self::upload_media( $phone_number_id, $bytes, self::guess_mime( $file_name, $media_type ), '' !== $file_name ? $file_name : 'file' );

            if ( is_wp_error( $upload ) ) {
                $queued = $queue_on_failure ? (bool) Notification_Queue::enqueue( 'media', $queue_payload, $upload->get_error_message() ) : false;
                $details = self::build_response_details( 0, false, true, $upload->get_error_message(), $queued );
                return self::record_and_return( $fields, $details, $return_details );
            }

            $media_object = array( 'id' => $upload );
        }

        if ( in_array( $media_type, array( 'image', 'video', 'document' ), true ) && '' !== $caption ) {
            $media_object['caption'] = $caption;
        }

        if ( 'document' === $media_type && '' !== $file_name ) {
            $media_object['filename'] = $file_name;
        }

        $body = array(
            'messaging_product' => 'whatsapp',
            'recipient_type' => 'individual',
            'to' => $receiver,
            'type' => $media_type,
            $media_type => $media_object,
        );

        $response = self::request( 'POST', '/v1/' . rawurlencode( $phone_number_id ) . '/messages', $body, 60 );

        return self::finish_send( $response, $fields, $return_details, $queue_on_failure, 'media', $queue_payload );
    }


    /**
     * Send an audio message (voice note when OGG/OPUS) through the Meta mirror.
     *
     * @since 1.4.8
     * @param string $sender | Origin phone number.
     * @param string $receiver | Recipient phone number.
     * @param string $audio | Public audio URL.
     * @param int $timestamp_delay | Ignored on the Cloud API.
     * @param bool $queue_on_failure | Enqueue retry item on failure.
     * @param bool $return_details | Return normalized details instead of response code.
     * @return int|array
     */
    public static function send_whatsapp_audio( $sender, $receiver, $audio, $timestamp_delay = 0, $queue_on_failure = true, $return_details = false ) {
        $sender = preg_replace( '/\D/', '', (string) $sender );
        $receiver = joinotify_prepare_receiver( $receiver );

        $fields = array(
            'message_type' => 'audio',
            'sender' => $sender,
            'receiver' => $receiver,
            'media_type' => 'audio',
            'media_url' => $audio,
        );

        $queue_payload = array(
            'sender' => $sender,
            'receiver' => $receiver,
            'audio' => $audio,
            'delay' => $timestamp_delay,
        );

        $guard = self::guard_send( $sender, $return_details, $fields, $queue_on_failure, 'audio', $queue_payload );

        if ( null !== $guard ) {
            return $guard;
        }

        $phone_number_id = self::resolve_phone_number_id( $sender );

        if ( '' === $phone_number_id ) {
            $details = self::build_response_details( 0, false, false, 'missing_phone_number_id' );
            return self::record_and_return( $fields, $details, $return_details );
        }

        $body = array(
            'messaging_product' => 'whatsapp',
            'recipient_type' => 'individual',
            'to' => $receiver,
            'type' => 'audio',
            'audio' => array( 'link' => $audio ),
        );

        $response = self::request( 'POST', '/v1/' . rawurlencode( $phone_number_id ) . '/messages', $body );

        return self::finish_send( $response, $fields, $return_details, $queue_on_failure, 'audio', $queue_payload );
    }


    /**
     * Send an approved template message. This is the only way to reach a
     * recipient outside the 24h session window.
     *
     * @since 1.4.8
     * @param string $sender | Origin phone number.
     * @param string $receiver | Recipient phone number.
     * @param string $template_name | Approved template name.
     * @param string $language | Template language code (e.g. pt_BR).
     * @param array $components | Meta components array (parameters already resolved).
     * @param int $timestamp_delay | Ignored on the Cloud API.
     * @param bool $queue_on_failure | Enqueue retry item on failure.
     * @param bool $return_details | Return normalized details instead of response code.
     * @return int|array
     */
    public static function send_message_template( $sender, $receiver, $template_name, $language = 'pt_BR', $components = array(), $timestamp_delay = 0, $queue_on_failure = true, $return_details = false ) {
        $sender = preg_replace( '/\D/', '', (string) $sender );
        $receiver = joinotify_prepare_receiver( $receiver );
        $template_name = sanitize_text_field( (string) $template_name );
        $language = '' !== trim( (string) $language ) ? trim( (string) $language ) : 'pt_BR';

        $fields = array(
            'message_type' => 'template',
            'sender' => $sender,
            'receiver' => $receiver,
            'content' => $template_name,
        );

        $queue_payload = array(
            'sender' => $sender,
            'receiver' => $receiver,
            'template_name' => $template_name,
            'language' => $language,
            'components' => $components,
            'delay' => $timestamp_delay,
        );

        if ( '' === $template_name ) {
            $details = self::build_response_details( 0, false, false, 'missing_template_name' );
            return self::record_and_return( $fields, $details, $return_details );
        }

        $guard = self::guard_send( $sender, $return_details, $fields, $queue_on_failure, 'template', $queue_payload );

        if ( null !== $guard ) {
            return $guard;
        }

        $body = array(
            'to' => $receiver,
            'name' => $template_name,
            'language' => $language,
        );

        if ( is_array( $components ) && ! empty( $components ) ) {
            $body['components'] = $components;
        }

        $from = self::resolve_phone_number_id( $sender );

        if ( '' !== $from ) {
            $body['from'] = $from;
        }

        $response = self::request( 'POST', '/messages/send/template', $body );

        return self::finish_send( $response, $fields, $return_details, $queue_on_failure, 'template', $queue_payload );
    }


    /**
     * Shared pre-flight guard: sender allowed, license valid, token present.
     *
     * Returns null when the send may proceed, or the recorded return value
     * (details array or response code) when it must stop.
     *
     * @since 1.4.8
     * @param string $sender | Sender phone.
     * @param bool $return_details | Whether to return details.
     * @param array $fields | History fields.
     * @param bool $queue_on_failure | Enqueue on failure.
     * @param string $queue_type | Queue type for retry.
     * @param array $queue_payload | Queue payload for retry.
     * @return int|array|null
     */
    protected static function guard_send( $sender, $return_details, $fields, $queue_on_failure, $queue_type, $queue_payload ) {
        if ( ! Helpers::allowed_sender( $sender ) ) {
            $details = self::build_response_details( 0, false, false, 'invalid_sender' );
            return self::record_and_return( $fields, $details, $return_details );
        }

        if ( '' === Helpers::cloud_api_token() ) {
            $queued = $queue_on_failure ? (bool) Notification_Queue::enqueue( $queue_type, $queue_payload, 'cloud_no_token' ) : false;
            $details = self::build_response_details( 0, false, true, 'cloud_no_token', $queued );
            return self::record_and_return( $fields, $details, $return_details );
        }

        if ( class_exists( License::class ) && ! License::is_valid() ) {
            $queued = $queue_on_failure ? (bool) Notification_Queue::enqueue( $queue_type, $queue_payload, 'license_invalid' ) : false;
            $details = self::build_response_details( 0, false, true, 'license_invalid', $queued );
            return self::record_and_return( $fields, $details, $return_details );
        }

        return null;
    }


    /**
     * Shared post-flight: parse the response, decide retry/queue, record it.
     *
     * @since 1.4.8
     * @param array|\WP_Error $response | wp_remote_* response.
     * @param array $fields | History fields.
     * @param bool $return_details | Whether to return details.
     * @param bool $queue_on_failure | Enqueue on failure.
     * @param string $queue_type | Queue type for retry.
     * @param array $queue_payload | Queue payload for retry.
     * @return int|array
     */
    protected static function finish_send( $response, $fields, $return_details, $queue_on_failure, $queue_type, $queue_payload ) {
        if ( is_wp_error( $response ) ) {
            Logger::register_log( $response, 'ERROR' );

            $queued = $queue_on_failure ? (bool) Notification_Queue::enqueue( $queue_type, $queue_payload, $response->get_error_message() ) : false;
            $details = self::build_response_details( 0, false, true, $response->get_error_message(), $queued );

            return self::record_and_return( $fields, $details, $return_details );
        }

        $parsed = self::parse_send_response( $response );

        if ( self::dev_mode() ) {
            error_log( 'Cloud_Client send response: ' . wp_remote_retrieve_body( $response ) );
        }

        $success = ( '' !== $parsed['wamid'] );
        $retryable = ( ! $success && self::should_retry_response_code( $parsed['code'] ) );
        $error = $success ? '' : self::describe_error( $parsed );
        $queued = false;

        if ( ! $success && $queue_on_failure && $retryable ) {
            $queued = (bool) Notification_Queue::enqueue( $queue_type, $queue_payload, 'api_unavailable_' . $parsed['code'] );
        }

        // Preserve the historical 201 "sent" contract for callers that compare
        // the response code strictly: report 201 on any successful WAMID.
        $response_code = $success ? 201 : (int) $parsed['code'];

        $details = self::build_response_details( $response_code, $success, $retryable, $error, $queued );

        return self::record_and_return( $fields, $details, $return_details );
    }


    /**
     * Upload media bytes to the account and return the Meta media id.
     *
     * Used when a workflow attachment references a local file (delivered here as
     * base64) and therefore cannot travel as a public link.
     *
     * @since 1.4.8
     * @param string $phone_number_id | Origin phone_number_id.
     * @param string $bytes | Raw file contents.
     * @param string $mime | File MIME type.
     * @param string $filename | File name.
     * @return string|\WP_Error | Media id or error.
     */
    public static function upload_media( $phone_number_id, $bytes, $mime, $filename ) {
        $token = Helpers::cloud_api_token();

        if ( '' === $token || '' === $phone_number_id ) {
            return new \WP_Error( 'joinotify_cloud_upload_config', __( 'Missing Cloud API token or phone number id for media upload.', 'joinotify' ) );
        }

        $boundary = 'joinotify' . md5( $filename . strlen( $bytes ) );
        $eol = "\r\n";

        $payload  = '--' . $boundary . $eol;
        $payload .= 'Content-Disposition: form-data; name="messaging_product"' . $eol . $eol . 'whatsapp' . $eol;
        $payload .= '--' . $boundary . $eol;
        $payload .= 'Content-Disposition: form-data; name="type"' . $eol . $eol . $mime . $eol;
        $payload .= '--' . $boundary . $eol;
        $payload .= 'Content-Disposition: form-data; name="file"; filename="' . $filename . '"' . $eol;
        $payload .= 'Content-Type: ' . $mime . $eol . $eol;
        $payload .= $bytes . $eol;
        $payload .= '--' . $boundary . '--' . $eol;

        $response = wp_remote_post( self::base_url() . '/v1/' . rawurlencode( $phone_number_id ) . '/media', array(
            'headers' => array(
                'Authorization' => 'Bearer ' . $token,
                'Content-Type' => 'multipart/form-data; boundary=' . $boundary,
            ),
            'body' => $payload,
            'timeout' => 60,
        ));

        if ( is_wp_error( $response ) ) {
            return $response;
        }

        $data = json_decode( wp_remote_retrieve_body( $response ), true );
        $id = is_array( $data ) && isset( $data['id'] ) ? (string) $data['id'] : '';

        if ( '' === $id ) {
            return new \WP_Error( 'joinotify_cloud_upload_failed', __( 'Media upload did not return an id.', 'joinotify' ) );
        }

        return $id;
    }


    /**
     * Best-effort MIME resolution from a file name, falling back to the media type.
     *
     * @since 1.4.8
     * @param string $file_name | File name (may be empty).
     * @param string $media_type | image|video|document|audio.
     * @return string
     */
    protected static function guess_mime( $file_name, $media_type ) {
        if ( '' !== (string) $file_name ) {
            $checked = wp_check_filetype( $file_name );

            if ( ! empty( $checked['type'] ) ) {
                return $checked['type'];
            }
        }

        $fallback = array(
            'image' => 'image/jpeg',
            'video' => 'video/mp4',
            'audio' => 'audio/ogg',
            'document' => 'application/octet-stream',
        );

        return $fallback[ $media_type ] ?? 'application/octet-stream';
    }


    /**
     * List the WhatsApp Business Account templates (mirror-backed).
     *
     * @since 1.4.8
     * @param array $args | Optional query args (status, refresh).
     * @return array|\WP_Error
     */
    public static function list_templates( $args = array() ) {
        $query = array();

        if ( isset( $args['status'] ) && '' !== $args['status'] ) {
            $query['status'] = sanitize_text_field( $args['status'] );
        }

        // Serve the maintained mirror instantly for listing screens.
        $query['refresh'] = ! empty( $args['refresh'] ) ? 'true' : 'false';

        $path = '/templates' . ( ! empty( $query ) ? '?' . http_build_query( $query ) : '' );
        $response = self::request( 'GET', $path );

        if ( is_wp_error( $response ) ) {
            Logger::register_log( $response, 'ERROR' );
            return $response;
        }

        return json_decode( wp_remote_retrieve_body( $response ), true );
    }


    /**
     * Force a template mirror synchronization.
     *
     * @since 1.4.8
     * @return array|\WP_Error
     */
    public static function sync_templates() {
        $response = self::request( 'POST', '/templates/sync', array( 'all' => true ) );

        if ( is_wp_error( $response ) ) {
            Logger::register_log( $response, 'ERROR' );
            return $response;
        }

        return json_decode( wp_remote_retrieve_body( $response ), true );
    }


    /**
     * List the phone numbers connected to the account.
     *
     * Uses the Meta mirror (`GET /v1/{waba_id}/phone_numbers`) since the
     * simplified `/numbers` endpoint is not yet generally available.
     *
     * @since 1.4.8
     * @return array|\WP_Error
     */
    public static function list_numbers() {
        $waba_id = Helpers::cloud_waba_id();

        if ( '' === $waba_id ) {
            return new \WP_Error( 'joinotify_cloud_no_waba', __( 'No WhatsApp Business Account id is configured.', 'joinotify' ) );
        }

        $response = self::request( 'GET', '/v1/' . rawurlencode( $waba_id ) . '/phone_numbers' );

        if ( is_wp_error( $response ) ) {
            Logger::register_log( $response, 'ERROR' );
            return $response;
        }

        return json_decode( wp_remote_retrieve_body( $response ), true );
    }


    /**
     * Fetch the last known delivery status of a message by WAMID.
     *
     * @since 1.4.8
     * @param string $wamid | Message WAMID.
     * @return array|\WP_Error
     */
    public static function get_message_status( $wamid ) {
        $wamid = trim( (string) $wamid );

        if ( '' === $wamid ) {
            return new \WP_Error( 'joinotify_cloud_no_wamid', __( 'A message id is required.', 'joinotify' ) );
        }

        $response = self::request( 'GET', '/messages/status/' . rawurlencode( $wamid ) );

        if ( is_wp_error( $response ) ) {
            return $response;
        }

        return json_decode( wp_remote_retrieve_body( $response ), true );
    }
}
