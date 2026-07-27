<?php

namespace MeuMouse\Joinotify\Licensing\Http;

use MeuMouse\Joinotify\Core\Logger;

// Exit if accessed directly.
defined('ABSPATH') || exit;

/**
 * HTTP transport shared by the licensing drivers.
 *
 * Deliberately stateless and cache-free: it sends a request and reports what
 * came back. Deciding what a given payload means, and whether the answer is
 * worth remembering, belongs to the driver that understands the protocol.
 *
 * @since 2.1.0
 * @package MeuMouse\Joinotify\Licensing
 * @author MeuMouse.com
 */
class Transport {

    /**
     * Body the legacy licensing server returns for an unknown route. It arrives
     * with a 200, so it has to be recognised by content rather than by status.
     *
     * @since 2.1.0
     * @var string
     */
    const NOT_FOUND_BODY = 'GET404';

    /**
     * Seconds to wait for a response.
     *
     * @since 2.1.0
     * @var int
     */
    protected $timeout;

    /**
     * Construct the transport.
     *
     * @since 2.1.0
     * @param int $timeout | Seconds to wait for a response
     * @return void
     */
    public function __construct( $timeout = 15 ) {
        $this->timeout = (int) $timeout;
    }


    /**
     * POST a body and report the outcome.
     *
     * A first failure is retried once with certificate verification disabled,
     * because a meaningful share of shared hosts ship outdated CA bundles and
     * would otherwise never reach the licensing server at all.
     *
     * @since 2.1.0
     * @param string $url | Absolute URL
     * @param string $body | Raw request body
     * @param array $headers | Request headers
     * @return array {
     *     @type string $body | Response body, empty when the request failed
     *     @type int $code | HTTP status code, zero when no response arrived
     *     @type string $error | Failure reason, empty on success
     *     @type bool $transport_error | Whether the server was never reached
     * }
     */
    public function post( $url, $body, $headers = array() ) {
        $args = array(
            'method' => 'POST',
            'sslverify' => true,
            'timeout' => $this->timeout,
            'redirection' => 5,
            'httpversion' => '1.0',
            'blocking' => true,
            'headers' => $headers,
            'body' => $body,
            'cookies' => array(),
        );

        $response = wp_remote_post( $url, $args );

        if ( is_wp_error( $response ) ) {
            $args['sslverify'] = false;
            $response = wp_remote_post( $url, $args );
        }

        if ( defined('JOINOTIFY_DEBUG_MODE') && JOINOTIFY_DEBUG_MODE ) {
            Logger::register_log( 'Licensing transport response: ' . print_r( $response, true ) );
        }

        if ( is_wp_error( $response ) ) {
            return $this->failure( $response->get_error_message() );
        }

        $code = (int) wp_remote_retrieve_response_code( $response );
        $response_body = (string) wp_remote_retrieve_body( $response );

        if ( 200 !== $code ) {
            return $this->failure( sprintf( 'HTTP %d', $code ), $code );
        }

        if ( '' === $response_body || self::NOT_FOUND_BODY === $response_body ) {
            return $this->failure( 'Empty or unrecognised response body.', $code );
        }

        return array(
            'body' => $response_body,
            'code' => $code,
            'error' => '',
            'transport_error' => false,
        );
    }


    /**
     * Build a failed outcome.
     *
     * @since 2.1.0
     * @param string $error | Failure reason
     * @param int $code | HTTP status code, when one was received
     * @return array
     */
    protected function failure( $error, $code = 0 ) {
        if ( defined('JOINOTIFY_DEBUG_MODE') && JOINOTIFY_DEBUG_MODE ) {
            Logger::register_log( 'Licensing transport failure: ' . $error, 'ERROR' );
        }

        return array(
            'body' => '',
            'code' => (int) $code,
            'error' => (string) $error,
            'transport_error' => true,
        );
    }
}
