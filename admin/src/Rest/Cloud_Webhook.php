<?php

namespace MeuMouse\Joinotify\Rest;

use MeuMouse\Joinotify\Api\Webhook_Handler;
use MeuMouse\Joinotify\Api\Webhooks;
use WP_REST_Request;

defined('ABSPATH') || exit;

/**
 * Receive the events the Joinotify account delivers to this site.
 *
 * The URL is public by necessity, so the signature is the authentication: a
 * request that does not carry a valid, fresh `X-Joinotify-Signature-256` is
 * refused before anything is read out of the body.
 *
 * @since 2.3.0
 */
class Cloud_Webhook extends Abstract_Route {

    /**
     * Route path.
     *
     * @var string
     */
    protected $route = '/cloud/webhook';

    /**
     * Allowed HTTP methods.
     *
     * @var string
     */
    protected $methods = 'POST';


    /**
     * The API delivers here without a WordPress session; the signature is what
     * authenticates the request, and it is checked in handle().
     *
     * @since 2.3.0
     * @param WP_REST_Request $request REST request instance.
     * @return bool
     */
    public function permission( WP_REST_Request $request ) {
        return true;
    }


    /**
     * Handle the request.
     *
     * @since 2.3.0
     * @param WP_REST_Request $request REST request instance.
     * @return \WP_REST_Response
     */
    public function handle( WP_REST_Request $request ) {
        if ( ! Webhooks::is_registered() ) {
            return new \WP_REST_Response( array( 'status' => 'error' ), 401 );
        }

        // The signature covers the exact bytes that were sent, so the body must
        // be read raw — a re-encoded payload never matches.
        $body = (string) $request->get_body();
        $signature = (string) $request->get_header( 'x_joinotify_signature_256' );
        $timestamp = (string) $request->get_header( 'x_joinotify_timestamp' );

        if ( ! Webhooks::verify_signature( $body, $signature, $timestamp ) ) {
            return new \WP_REST_Response( array( 'status' => 'error' ), 401 );
        }

        $payload = json_decode( $body, true );

        if ( ! is_array( $payload ) ) {
            return new \WP_REST_Response( array( 'status' => 'error' ), 400 );
        }

        foreach ( self::extract_events( $payload ) as $event ) {
            Webhook_Handler::handle( $event );
        }

        return new \WP_REST_Response( array( 'status' => 'success' ), 200 );
    }


    /**
     * Normalize the delivery envelope into a flat list of events.
     *
     * A delivery carries either a single `{field, value}` event or Meta's nested
     * `entry[].changes[]` structure.
     *
     * @since 2.3.0
     * @param array $payload | Decoded delivery body.
     * @return array
     */
    protected static function extract_events( $payload ) {
        if ( isset( $payload['field'] ) ) {
            return array( $payload );
        }

        $events = array();

        foreach ( (array) ( $payload['entry'] ?? array() ) as $entry ) {
            if ( ! is_array( $entry ) ) {
                continue;
            }

            foreach ( (array) ( $entry['changes'] ?? array() ) as $change ) {
                if ( is_array( $change ) && isset( $change['field'] ) ) {
                    $events[] = $change;
                }
            }
        }

        return $events;
    }
}
