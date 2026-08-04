<?php

namespace MeuMouse\Joinotify\Licensing\Drivers;

use MeuMouse\Joinotify\Core\Logger;
use MeuMouse\Joinotify\Licensing\Contracts\Driver;
use MeuMouse\Joinotify\Licensing\Dto\License_Result;
use MeuMouse\Joinotify\Licensing\Http\Transport;
use MeuMouse\Joinotify\Licensing\Support\Crypto;
use MeuMouse\Joinotify\Licensing\Support\Site;

// Exit if accessed directly.
defined('ABSPATH') || exit;

/**
 * The original licensing protocol, served from api.meumouse.com.
 *
 * Payloads are AES encrypted with a per-product key in both directions, and the
 * license object itself arrives encrypted a second time under the site URL. The
 * protocol has no read-only check: re-activating is how a site re-validates,
 * which is why validate() and activate() are the same call.
 *
 * @since 2.1.0
 * @package MeuMouse\Joinotify\Licensing
 * @author MeuMouse.com
 */
class Legacy_Driver implements Driver {

    /**
     * Driver identifier.
     *
     * @since 2.1.0
     * @var string
     */
    const ID = 'legacy';

    /**
     * Base URL of the licensing server.
     *
     * @since 2.1.0
     * @var string
     */
    protected $server_host;

    /**
     * Product identifier on the licensing server.
     *
     * @since 2.1.0
     * @var string
     */
    protected $product_id;

    /**
     * Product slug on the licensing server.
     *
     * @since 2.1.0
     * @var string
     */
    protected $product_base;

    /**
     * Shared secret used to encrypt request and response payloads.
     *
     * @since 2.1.0
     * @var string
     */
    protected $product_key;

    /**
     * HTTP transport.
     *
     * @since 2.1.0
     * @var Transport
     */
    protected $transport;

    /**
     * Construct the driver for whichever product the key belongs to.
     *
     * @since 2.1.0
     * @param string $license_key | License key, used to pick the product
     * @param Transport|null $transport | Transport, injected in tests
     * @return void
     */
    public function __construct( $license_key = '', $transport = null ) {
        $product = self::resolve_product( $license_key );

        $this->product_id = $product['id'];
        $this->product_base = $product['base'];
        $this->product_key = $product['key'];

        /**
         * Filters the legacy licensing server base URL.
         *
         * @since 2.1.0
         * @param string $server_host | Base URL
         */
        $this->server_host = apply_filters( 'Joinotify/Licensing/Legacy/Server_Host', 'https://api.meumouse.com/wp-json/license/' );

        $this->transport = $transport instanceof Transport ? $transport : new Transport( 15 );
    }


    /**
     * Product catalogue as registered on the legacy server.
     *
     * Keys prefixed with "CM-" belong to the Clube M subscription, which is a
     * separate product with its own encryption key.
     *
     * @since 2.1.0
     * @param string $license_key | License key
     * @return array
     */
    public static function resolve_product( $license_key ) {
        $license_key = is_string( $license_key ) ? $license_key : '';

        if ( 0 === strpos( $license_key, 'CM-' ) ) {
            return array(
                'id' => '7',
                'base' => 'clube-m',
                'key' => 'B729F2659393EE27',
            );
        }

        return array(
            'id' => '8',
            'base' => 'joinotify',
            'key' => 'E63390D3F50B70F0',
        );
    }


    /**
     * Driver identifier.
     *
     * @since 2.1.0
     * @return string
     */
    public function id() {
        return self::ID;
    }


    /**
     * Activate a license key for this site.
     *
     * @since 2.1.0
     * @param string $license_key | License key
     * @return License_Result
     */
    public function activate( $license_key ) {
        $response = $this->request( 'product/active/' . $this->product_id, $this->request_params( $license_key ) );

        if ( $response instanceof License_Result ) {
            return $response;
        }

        return $this->parse_license_object( $response, $license_key );
    }


    /**
     * Re-check an already activated key.
     *
     * The legacy protocol offers no read-only check, so this repeats the
     * activation call. The server treats a repeat for the same site as
     * idempotent.
     *
     * @since 2.1.0
     * @param string $license_key | License key
     * @return License_Result
     */
    public function validate( $license_key ) {
        return $this->activate( $license_key );
    }


    /**
     * Release this site's activation.
     *
     * @since 2.1.0
     * @param string $license_key | License key
     * @return License_Result
     */
    public function deactivate( $license_key ) {
        $response = $this->request( 'product/deactive/' . $this->product_id, $this->request_params( $license_key ) );

        if ( $response instanceof License_Result ) {
            return $response;
        }

        // A populated "code" is how this server reports a refusal.
        if ( ! empty( $response->code ) ) {
            return License_Result::business_failure( isset( $response->message ) ? (string) $response->message : '' );
        }

        return License_Result::success( isset( $response->msg ) ? (string) $response->msg : '' );
    }


    /**
     * Expiration timestamp for a key.
     *
     * Uses the server's own license viewer rather than the activation endpoint,
     * so the scheduled expiry check can read the date without touching the
     * stored activation.
     *
     * @since 2.1.0
     * @param string $license_key | License key
     * @return int|null
     */
    public function expires_at( $license_key ) {
        // The route lives inside the "license" namespace, hence the repetition.
        $response = wp_remote_post( $this->server_host . 'license/view', array(
            'headers' => array(
                'Content-Type' => 'application/x-www-form-urlencoded',
            ),
            'body' => array(
                'api_key' => '41391199-FE02BDAA-3E8E3920-CDACDE2F',
                'license_code' => $license_key,
            ),
            'timeout' => 10,
        ));

        if ( is_wp_error( $response ) ) {
            Logger::register_log( 'Error getting license expiration time: ' . $response->get_error_message(), 'ERROR' );

            return null;
        }

        $decoded = json_decode( wp_remote_retrieve_body( $response ), true );

        if ( ! is_array( $decoded ) || empty( $decoded['data']['expiry_time'] ) ) {
            Logger::register_log( 'Invalid response from license API: ' . print_r( $decoded, true ), 'ERROR' );

            return null;
        }

        $timestamp = strtotime( $decoded['data']['expiry_time'] );

        return $timestamp ? $timestamp : null;
    }


    /**
     * Payload every endpoint expects.
     *
     * @since 2.1.0
     * @param string $license_key | License key
     * @return object
     */
    protected function request_params( $license_key ) {
        $params = new \stdClass();

        $params->license_key = $license_key;
        $params->email = defined('JOINOTIFY_ADMIN_EMAIL') ? JOINOTIFY_ADMIN_EMAIL : get_option('admin_email');
        $params->domain = Site::url();
        $params->app_version = defined('JOINOTIFY_VERSION') ? JOINOTIFY_VERSION : '';
        $params->product_id = $this->product_id;
        $params->product_base = $this->product_base;

        return $params;
    }


    /**
     * Send an encrypted request and decode the envelope.
     *
     * @since 2.1.0
     * @param string $endpoint | Endpoint relative to the server host
     * @param object $params | Payload to encrypt
     * @return object|License_Result Decoded envelope, or a failure result
     */
    protected function request( $endpoint, $params ) {
        $url = rtrim( $this->server_host, '/' ) . '/' . ltrim( $endpoint, '/' );
        $body = Crypto::encrypt( wp_json_encode( $params ), $this->product_key );

        $response = $this->transport->post( $url, $body );

        if ( ! empty( $response['transport_error'] ) ) {
            return License_Result::transport_failure( $response['error'] );
        }

        $decrypted = Crypto::decrypt( $response['body'], $this->product_key );

        // A body we cannot decrypt means whatever answered is not this server
        // any more: a parked domain, a WAF page or an error page. That is a
        // transport-level failure, not a verdict about the license.
        if ( '' === $decrypted ) {
            return License_Result::transport_failure( __( 'Could not read the response from the license server.', 'joinotify' ) );
        }

        $decoded = json_decode( $decrypted );

        if ( JSON_ERROR_NONE !== json_last_error() ) {
            return License_Result::transport_failure( sprintf( __( 'JSON Error: %s', 'joinotify' ), json_last_error_msg() ) );
        }

        if ( ! is_object( $decoded ) ) {
            return License_Result::transport_failure( __( 'Unexpected response from the license server.', 'joinotify' ) );
        }

        return $decoded;
    }


    /**
     * Turn a successful envelope into a license result.
     *
     * @since 2.1.0
     * @param object $response | Decoded envelope
     * @param string $license_key | License key
     * @return License_Result
     */
    protected function parse_license_object( $response, $license_key ) {
        if ( ! empty( $response->code ) ) {
            return License_Result::business_failure( isset( $response->message ) ? (string) $response->message : '' );
        }

        $message = isset( $response->msg ) ? (string) $response->msg : '';

        if ( empty( $response->status ) ) {
            return License_Result::business_failure( $message );
        }

        if ( empty( $response->data ) ) {
            return License_Result::business_failure( __( 'Invalid data.', 'joinotify' ) );
        }

        // The license object is encrypted a second time, under the site URL.
        $license = maybe_unserialize( Crypto::decrypt( $response->data, Site::url() ) );

        if ( ! is_object( $license ) ) {
            return License_Result::transport_failure( __( 'Could not read the license details.', 'joinotify' ) );
        }

        if ( empty( $license->is_valid ) ) {
            return License_Result::business_failure( $message, $this->license_fields( $license, $license_key ) );
        }

        return License_Result::valid( $this->license_fields( $license, $license_key ), $message );
    }


    /**
     * Map the server's license object onto the plugin's field names.
     *
     * @since 2.1.0
     * @param object $license | Decrypted license object
     * @param string $license_key | License key
     * @return array
     */
    protected function license_fields( $license, $license_key ) {
        return array(
            'license_key' => $license_key,
            'license_title' => isset( $license->license_title ) ? $license->license_title : '',
            'expire_date' => isset( $license->expire_date ) ? $license->expire_date : '',
            'support_end' => isset( $license->support_end ) ? $license->support_end : '',
            'renew_link' => ! empty( $license->renew_link ) ? $license->renew_link : '',
            'request_duration' => isset( $license->request_duration ) ? (int) $license->request_duration : 0,
            // WhatsApp Cloud API credentials, when the license server provisions them.
            'api_token' => isset( $license->api_token ) ? $license->api_token : '',
            'phone_number_id' => isset( $license->phone_number_id ) ? $license->phone_number_id : '',
            'waba_id' => isset( $license->waba_id ) ? $license->waba_id : '',
        );
    }
}
