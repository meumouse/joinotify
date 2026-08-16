<?php

namespace MeuMouse\Joinotify\Api;

use MeuMouse\Joinotify\Core\Logger;

// Exit if accessed directly.
defined('ABSPATH') || exit;

/**
 * Register this site as a webhook endpoint of the Joinotify account.
 *
 * A `201` at send time only says the API accepted the message. Whether it
 * reached the device, was opened or was rejected — and whether a contact wrote
 * first, which is what opens the 24-hour window — only arrives by webhook.
 *
 * @since 2.3.0
 * @package MeuMouse\Joinotify\Api
 * @author MeuMouse.com
 */
class Webhooks {

    /**
     * Option holding the registered endpoint id.
     *
     * @var string
     */
    const ENDPOINT_OPTION = 'joinotify_webhook_endpoint_id';

    /**
     * Option holding the signing secret. Shown by the API only once, on creation.
     *
     * @var string
     */
    const SECRET_OPTION = 'joinotify_webhook_secret';

    /**
     * Events this site subscribes to.
     *
     * @var string[]
     */
    const EVENTS = array(
        'messages',
        'message_template_status_update',
        'message_template_quality_update',
        'template_category_update',
        'phone_number_quality_update',
    );


    /**
     * Public URL the API delivers to.
     *
     * @since 2.3.0
     * @return string
     */
    public static function callback_url() {
        return rest_url( 'joinotify/v1/cloud/webhook' );
    }


    /**
     * Whether an endpoint is already registered for this site.
     *
     * @since 2.3.0
     * @return bool
     */
    public static function is_registered() {
        return '' !== (string) get_option( self::ENDPOINT_OPTION, '' ) && '' !== self::get_secret();
    }


    /**
     * Signing secret stored at registration time.
     *
     * @since 2.3.0
     * @return string
     */
    public static function get_secret() {
        return (string) get_option( self::SECRET_OPTION, '' );
    }


    /**
     * Reject the URLs the API refuses before spending a request on them.
     *
     * Production endpoints must be public HTTPS: `http://` and private hosts are
     * declined, which is exactly the case on a local development site.
     *
     * @since 2.3.0
     * @param string $url | Callback URL.
     * @return true|\WP_Error
     */
    public static function validate_url( $url ) {
        $host = (string) wp_parse_url( $url, PHP_URL_HOST );
        $scheme = (string) wp_parse_url( $url, PHP_URL_SCHEME );

        if ( 'https' !== $scheme ) {
            return new \WP_Error( 'joinotify_webhook_insecure', __( 'Delivery reports need a public HTTPS address. This site is served over HTTP, so the webhook cannot be registered.', 'joinotify' ) );
        }

        $private = array( 'localhost', '127.0.0.1', '::1' );

        $is_private = in_array( strtolower( $host ), $private, true )
            || preg_match( '/^(10\.|192\.168\.|172\.(1[6-9]|2[0-9]|3[01])\.)/', $host )
            || preg_match( '/\.(local|internal|test)$/i', $host );

        if ( $is_private ) {
            return new \WP_Error( 'joinotify_webhook_private_host', __( 'Delivery reports need a publicly reachable address. Local and private hosts cannot receive them.', 'joinotify' ) );
        }

        return true;
    }


    /**
     * Register this site with the account, storing the endpoint id and secret.
     *
     * @since 2.3.0
     * @return array|\WP_Error
     */
    public static function register() {
        $url = self::callback_url();
        $valid = self::validate_url( $url );

        if ( is_wp_error( $valid ) ) {
            return $valid;
        }

        $response = Cloud_Client::request( 'POST', '/webhook-endpoints', array(
            'name' => wp_parse_url( home_url(), PHP_URL_HOST ) . ' (Joinotify plugin)',
            'url' => $url,
            'events' => self::EVENTS,
        ) );

        if ( is_wp_error( $response ) ) {
            Logger::register_log( $response, 'ERROR' );
            return $response;
        }

        $code = (int) wp_remote_retrieve_response_code( $response );
        $body = json_decode( wp_remote_retrieve_body( $response ), true );
        $body = is_array( $body ) ? $body : array();

        if ( $code < 200 || $code >= 300 ) {
            $message = $body['error']['message'] ?? __( 'The webhook endpoint could not be registered.', 'joinotify' );

            return new \WP_Error( 'joinotify_webhook_register_failed', $message, array( 'status' => $code ) );
        }

        $data = isset( $body['data'] ) && is_array( $body['data'] ) ? $body['data'] : array();
        $secret = (string) ( $data['secret'] ?? '' );

        if ( '' === $secret ) {
            return new \WP_Error( 'joinotify_webhook_no_secret', __( 'The API did not return a signing secret for this endpoint.', 'joinotify' ) );
        }

        update_option( self::ENDPOINT_OPTION, (string) ( $data['id'] ?? '' ) );
        update_option( self::SECRET_OPTION, $secret );

        return $data;
    }


    /**
     * Remove the registered endpoint and forget its secret.
     *
     * @since 2.3.0
     * @return bool
     */
    public static function unregister() {
        $id = (string) get_option( self::ENDPOINT_OPTION, '' );

        if ( '' !== $id ) {
            Cloud_Client::request( 'DELETE', '/webhook-endpoints/' . rawurlencode( $id ) );
        }

        delete_option( self::ENDPOINT_OPTION );
        delete_option( self::SECRET_OPTION );

        return true;
    }


    /**
     * Verify a delivery signature.
     *
     * The timestamp is inside the HMAC on purpose: without it, a captured
     * delivery could be replayed forever. Anything older than the tolerance is
     * refused even when the signature itself matches.
     *
     * @since 2.3.0
     * @param string $body | Raw request body, before any parsing.
     * @param string $signature | `X-Joinotify-Signature-256` header.
     * @param string $timestamp | `X-Joinotify-Timestamp` header.
     * @return bool
     */
    public static function verify_signature( $body, $signature, $timestamp ) {
        $secret = self::get_secret();

        if ( '' === $secret || '' === $signature || '' === $timestamp ) {
            return false;
        }

        $tolerance = (int) apply_filters( 'Joinotify/Cloud_Api/Webhook_Tolerance', 300 );

        if ( abs( time() - (int) $timestamp ) > $tolerance ) {
            return false;
        }

        $expected = 'sha256=' . hash_hmac( 'sha256', $timestamp . '.' . $body, $secret );

        return hash_equals( $expected, $signature );
    }
}
