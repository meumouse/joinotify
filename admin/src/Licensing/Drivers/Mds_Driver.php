<?php

namespace MeuMouse\Joinotify\Licensing\Drivers;

use MeuMouse\Joinotify\Core\Logger;
use MeuMouse\Joinotify\Licensing\Contracts\Driver;
use MeuMouse\Joinotify\Licensing\Dto\License_Result;
use MeuMouse\Joinotify\Licensing\Support\Site;

use MeuMouse\MDS\SDK\Api\ApiException;
use MeuMouse\MDS\SDK\Api\Client as Mds_Client;
use MeuMouse\MDS\SDK\Config\Product;
use MeuMouse\MDS\SDK\Security\SignatureVerifier;
use MeuMouse\MDS\SDK\Support\Logger as Mds_Logger;

// Exit if accessed directly.
defined('ABSPATH') || exit;

/**
 * The Modular Distribution Service licensing backend.
 *
 * Wraps the MDS SDK's HTTP client rather than its license manager: the manager
 * keeps its own copy of the license state, which this plugin already owns. What
 * the SDK is used for here is the part worth not rewriting — signed-response
 * verification and a transport that already separates "the server refused" from
 * "the server never answered".
 *
 * Responses to license-critical calls must carry a valid ed25519 signature. An
 * unsigned answer is refused, which is what stops a patched build pointing at a
 * fake server from minting itself a valid license.
 *
 * @since 2.1.0
 * @package MeuMouse\Joinotify\Licensing
 * @author MeuMouse.com
 */
class Mds_Driver implements Driver {

    /**
     * Driver identifier.
     *
     * @since 2.1.0
     * @var string
     */
    const ID = 'mds';

    /**
     * Product slug as registered on the MDS server.
     *
     * @since 2.1.0
     * @var string
     */
    const PRODUCT_SLUG = 'joinotify';

    /**
     * SDK HTTP client, built lazily.
     *
     * @since 2.1.0
     * @var Mds_Client|null
     */
    protected $client;

    /**
     * Why the driver cannot be used, if it cannot.
     *
     * @since 2.1.0
     * @var string
     */
    protected $unavailable_reason = '';


    /**
     * Construct the driver.
     *
     * @since 2.1.0
     * @param Mds_Client|null $client | SDK client, injected in tests
     * @return void
     */
    public function __construct( $client = null ) {
        if ( null !== $client ) {
            $this->client = $client;

            return;
        }

        $this->unavailable_reason = $this->resolve_unavailable_reason();
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
        $activation = $this->post( '/v2/licenses/activate', array_merge(
            array( 'license_key' => $license_key ),
            $this->request_meta()
        ));

        if ( $activation instanceof License_Result ) {
            return $activation;
        }

        // The activation response describes the seat, not the license. Pull the
        // license details in the same shape every other path returns.
        return $this->validate( $license_key );
    }


    /**
     * Re-check an already activated key.
     *
     * @since 2.1.0
     * @param string $license_key | License key
     * @return License_Result
     */
    public function validate( $license_key ) {
        $data = $this->post( '/v2/updates/validate', array_merge(
            array(
                'license_key' => $license_key,
                'product_slug' => self::PRODUCT_SLUG,
            ),
            $this->request_meta()
        ));

        if ( $data instanceof License_Result ) {
            return $data;
        }

        $fields = $this->license_fields( $data, $license_key );
        $message = isset( $data['message'] ) ? (string) $data['message'] : '';

        if ( empty( $data['valid'] ) ) {
            return License_Result::business_failure( $message, $fields );
        }

        return License_Result::valid( $fields, $message );
    }


    /**
     * Release this site's activation.
     *
     * @since 2.1.0
     * @param string $license_key | License key
     * @return License_Result
     */
    public function deactivate( $license_key ) {
        $data = $this->post( '/v2/licenses/deactivate', array(
            'license_key' => $license_key,
            'domain' => Site::domain(),
        ), false );

        if ( $data instanceof License_Result ) {
            return $data;
        }

        return License_Result::success( __( 'License deactivated successfully.', 'joinotify' ) );
    }


    /**
     * Expiration timestamp for a key.
     *
     * @since 2.1.0
     * @param string $license_key | License key
     * @return int|null
     */
    public function expires_at( $license_key ) {
        $result = $this->validate( $license_key );

        // A transport failure says nothing about the expiry date, and answering
        // "null" would be indistinguishable from a lifetime license.
        if ( $result->is_transport_failure() ) {
            return null;
        }

        $expire_date = $result->get( 'expire_date', '' );

        if ( '' === $expire_date || 'No expiry' === $expire_date ) {
            return null;
        }

        $timestamp = strtotime( $expire_date );

        return $timestamp ? $timestamp : null;
    }


    /**
     * POST to the API and unwrap the envelope.
     *
     * @since 2.1.0
     * @param string $path | API path
     * @param array $body | Request payload
     * @param bool $require_signature | Whether an unsigned answer is refused
     * @return array|License_Result Response data, or a failure result
     */
    protected function post( $path, $body, $require_signature = true ) {
        $client = $this->client();

        if ( ! $client instanceof Mds_Client ) {
            return License_Result::transport_failure( $this->unavailable_reason );
        }

        try {
            $response = $client->post( $path, $body, $require_signature );
        } catch ( ApiException $e ) {
            return $this->from_exception( $e );
        } catch ( \Throwable $e ) {
            // Anything the SDK did not anticipate is still "we have no answer",
            // never "the license is bad".
            Logger::register_log( 'MDS licensing call failed: ' . $e->getMessage(), 'ERROR' );

            return License_Result::transport_failure( $e->getMessage() );
        }

        return $response->data();
    }


    /**
     * Translate an SDK exception into a result.
     *
     * @since 2.1.0
     * @param ApiException $e | Exception raised by the SDK client
     * @return License_Result
     */
    protected function from_exception( ApiException $e ) {
        if ( $e->is_transport() ) {
            return License_Result::transport_failure( $e->getMessage() );
        }

        // An answer we cannot verify is not an answer. Treating it as a refusal
        // would let anything able to intercept the response revoke a license.
        if ( 'INVALID_SIGNATURE' === $e->error_code() || 'MALFORMED_RESPONSE' === $e->error_code() ) {
            Logger::register_log( 'MDS licensing response rejected: ' . $e->error_code(), 'ERROR' );

            return License_Result::transport_failure( $e->getMessage() );
        }

        return License_Result::business_failure( $e->getMessage() );
    }


    /**
     * Map the validate payload onto the plugin's field names.
     *
     * @since 2.1.0
     * @param array $data | Response data
     * @param string $license_key | License key
     * @return array
     */
    protected function license_fields( $data, $license_key ) {
        $expires_at = isset( $data['expires_at'] ) ? $data['expires_at'] : null;
        $support_expires_at = isset( $data['support_expires_at'] ) ? $data['support_expires_at'] : null;

        return array(
            'license_key' => $license_key,
            // The stored license object is read by the settings screen, which
            // expects the same sentinel the legacy server used for "never".
            'license_title' => $this->plan_label( $data ),
            'expire_date' => $expires_at ? $expires_at : 'No expiry',
            'support_end' => $support_expires_at ? $support_expires_at : 'Unlimited',
            'renew_link' => isset( $data['renew_url'] ) && $data['renew_url'] ? (string) $data['renew_url'] : '',
            'request_duration' => (int) apply_filters( 'Joinotify/Licensing/Mds/Request_Duration', 12 ),
            'reason' => isset( $data['reason'] ) ? $data['reason'] : null,
            'plan' => isset( $data['plan'] ) ? $data['plan'] : '',
            'max_activations' => isset( $data['max_activations'] ) ? (int) $data['max_activations'] : 0,
            'used_activations' => isset( $data['used_activations'] ) ? (int) $data['used_activations'] : 0,
            // WhatsApp Cloud API credentials, when the MDS server provisions them.
            'api_token' => isset( $data['api_token'] ) ? (string) $data['api_token'] : '',
            'phone_number_id' => isset( $data['phone_number_id'] ) ? (string) $data['phone_number_id'] : '',
            'waba_id' => isset( $data['waba_id'] ) ? (string) $data['waba_id'] : '',
        );
    }


    /**
     * Human readable plan name for the settings screen.
     *
     * @since 2.1.0
     * @param array $data | Response data
     * @return string
     */
    protected function plan_label( $data ) {
        if ( ! empty( $data['plan_name'] ) ) {
            return (string) $data['plan_name'];
        }

        if ( ! empty( $data['plan'] ) ) {
            return ucwords( str_replace( array( '-', '_' ), ' ', (string) $data['plan'] ) );
        }

        return esc_html__( 'Not available', 'joinotify' );
    }


    /**
     * Metadata sent with every call.
     *
     * @since 2.1.0
     * @return array
     */
    protected function request_meta() {
        global $wp_version;

        return array(
            'domain' => Site::domain(),
            'site_url' => Site::url(),
            'wp_version' => (string) $wp_version,
            'php_version' => PHP_VERSION,
            'plugin_version' => defined('JOINOTIFY_VERSION') ? JOINOTIFY_VERSION : '',
        );
    }


    /**
     * SDK client, built on first use.
     *
     * @since 2.1.0
     * @return Mds_Client|null
     */
    protected function client() {
        if ( null !== $this->client || '' !== $this->unavailable_reason ) {
            return $this->client;
        }

        try {
            $product = new Product( array(
                'product_slug' => self::PRODUCT_SLUG,
                'file' => defined('JOINOTIFY_BASENAME') ? JOINOTIFY_BASENAME : 'joinotify/joinotify.php',
                'current_version' => defined('JOINOTIFY_VERSION') ? JOINOTIFY_VERSION : '0.0.0',
                'api_base_url' => self::api_url(),
                'api_key' => self::api_key(),
                'public_key' => self::public_key(),
                'item_name' => 'Joinotify',
                'text_domain' => 'joinotify',
            ));

            $this->client = new Mds_Client(
                $product,
                new SignatureVerifier( $product->public_key() ),
                new Mds_Logger( self::PRODUCT_SLUG )
            );
        } catch ( \Throwable $e ) {
            $this->unavailable_reason = $e->getMessage();

            Logger::register_log( 'Could not build the MDS licensing client: ' . $e->getMessage(), 'ERROR' );
        }

        return $this->client;
    }


    /**
     * Why this driver cannot be used, checked before any request is attempted.
     *
     * @since 2.1.0
     * @return string Empty string when the driver is usable
     */
    protected function resolve_unavailable_reason() {
        if ( ! class_exists( Mds_Client::class ) ) {
            return __( 'The licensing SDK is not installed.', 'joinotify' );
        }

        // Without libsodium no response can be verified, and an unverified
        // license answer is one this plugin must not act on.
        if ( ! SignatureVerifier::is_supported() ) {
            return __( 'This server cannot verify signed license responses (the sodium extension is missing).', 'joinotify' );
        }

        if ( '' === self::api_key() || '' === self::public_key() ) {
            return __( 'The licensing service is not configured.', 'joinotify' );
        }

        return '';
    }


    /**
     * Whether this driver can be used at all on this installation.
     *
     * @since 2.1.0
     * @return bool
     */
    public static function is_configured() {
        return '' === ( new self() )->unavailable_reason;
    }


    /**
     * MDS API base URL.
     *
     * @since 2.1.0
     * @return string
     */
    protected static function api_url() {
        $url = defined('JOINOTIFY_MDS_API_URL') ? JOINOTIFY_MDS_API_URL : '';

        /**
         * Filters the MDS API base URL.
         *
         * @since 2.1.0
         * @param string $url | Base URL
         */
        return (string) apply_filters( 'Joinotify/Licensing/Mds/Api_Url', $url );
    }


    /**
     * Public, low-privilege product API key.
     *
     * @since 2.1.0
     * @return string
     */
    protected static function api_key() {
        $key = defined('JOINOTIFY_MDS_API_KEY') ? JOINOTIFY_MDS_API_KEY : '';

        /**
         * Filters the MDS product API key.
         *
         * @since 2.1.0
         * @param string $key | API key
         */
        return (string) apply_filters( 'Joinotify/Licensing/Mds/Api_Key', $key );
    }


    /**
     * Base64 ed25519 public key used to verify signed responses.
     *
     * @since 2.1.0
     * @return string
     */
    protected static function public_key() {
        $key = defined('JOINOTIFY_MDS_PUBLIC_KEY') ? JOINOTIFY_MDS_PUBLIC_KEY : '';

        /**
         * Filters the MDS response-signing public key.
         *
         * @since 2.1.0
         * @param string $key | Base64 encoded public key
         */
        return (string) apply_filters( 'Joinotify/Licensing/Mds/Public_Key', $key );
    }
}
