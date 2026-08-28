<?php

namespace MeuMouse\Joinotify\Api;

use MeuMouse\Joinotify\Core\Logger;

// Exit if accessed directly.
defined('ABSPATH') || exit;

/**
 * Pair the site with a Joinotify account without ever handling a token by hand.
 *
 * The user is sent to the panel, authenticates there, runs Meta's Embedded
 * Signup if needed and comes back with a single-use `code`. The site then trades
 * that code for an API key server-to-server, so the secret never travels in a
 * URL, a browser history or a Referer header — the same rule the API itself
 * enforces for its token.
 *
 * The manual token field stays available as a fallback: pasting an `sk_live_`
 * key writes to the exact same setting this class writes to, so everything
 * downstream reads credentials through Helpers and never learns which path was
 * used.
 *
 * @since 2.3.0
 * @package MeuMouse\Joinotify\Api
 * @author MeuMouse.com
 */
class Connect {

    /**
     * Transient key holding the pending handshake state.
     *
     * @var string
     */
    const STATE_KEY = 'joinotify_connect_state';

    /**
     * How long a started handshake stays valid, in seconds.
     *
     * @var int
     */
    const STATE_TTL = 600;


    /**
     * Start a handshake and build the panel URL the user must open.
     *
     * @since 2.3.0
     * @return array{url:string,state:string,expires_in:int}
     */
    public static function start() {
        $state = wp_generate_password( 32, false, false );

        set_transient( self::STATE_KEY, array(
            'state' => $state,
            'user_id' => get_current_user_id(),
        ), self::STATE_TTL );

        $url = add_query_arg( array(
            'site' => rawurlencode( home_url() ),
            'state' => $state,
            'return' => rawurlencode( self::return_url() ),
        ), trailingslashit( JOINOTIFY_PANEL_URL ) . 'connect' );

        return array(
            'url' => apply_filters( 'Joinotify/Cloud_Api/Connect_Url', $url, $state ),
            'state' => $state,
            'expires_in' => self::STATE_TTL,
        );
    }


    /**
     * Finish a handshake: validate the state, trade the code for credentials and
     * store them.
     *
     * @since 2.3.0
     * @param string $code | Single-use code handed back by the panel.
     * @param string $state | State echoed back by the panel.
     * @return array|\WP_Error Stored credential summary, or the failure reason.
     */
    public static function finish( $code, $state ) {
        $code = sanitize_text_field( $code );
        $state = sanitize_text_field( $state );

        if ( '' === $code || '' === $state ) {
            return new \WP_Error( 'joinotify_connect_missing_code', __( 'The connection response was incomplete. Start the connection again.', 'joinotify' ) );
        }

        $pending = get_transient( self::STATE_KEY );

        if ( ! is_array( $pending ) || empty( $pending['state'] ) || ! hash_equals( (string) $pending['state'], $state ) ) {
            return new \WP_Error( 'joinotify_connect_invalid_state', __( 'This connection attempt expired or did not start here. Start the connection again.', 'joinotify' ) );
        }

        // Single use: consume the state before spending the code.
        delete_transient( self::STATE_KEY );

        $credentials = self::exchange( $code );

        if ( is_wp_error( $credentials ) ) {
            return $credentials;
        }

        self::store_credentials( $credentials );

        // Bring the connected numbers in right away so the site is usable
        // without a second manual step. They already came with the key, so only
        // an answer without them costs a second round trip.
        Sender_Sync::flush_cache();
        $senders = empty( $credentials['senders'] ) ? Sender_Sync::sync() : Sender_Sync::store( $credentials['senders'] );

        // Delivery reports are a bonus, not a requirement: a site that is not
        // publicly reachable over HTTPS still sends fine, it just never learns
        // what happened afterwards. So a failure here is reported, not fatal.
        $webhook = Webhooks::register();

        return array(
            'token' => $credentials['token'],
            'waba_id' => $credentials['waba_id'],
            'phone_number_id' => $credentials['phone_number_id'],
            'senders' => is_wp_error( $senders ) ? array() : $senders,
            'sync_error' => is_wp_error( $senders ) ? $senders->get_error_message() : '',
            'webhook_error' => is_wp_error( $webhook ) ? $webhook->get_error_message() : '',
        );
    }


    /**
     * Connect straight from the license, without the panel round trip.
     *
     * The site already holds something that proves whose it is: the license key the
     * customer bought. Sending that person to create an account and authorize a site
     * the license already identifies is a step that protects nothing.
     *
     * The license only bootstraps. It is traded for an API key scoped to this site —
     * revocable on the panel on its own — and never used to send a message. That
     * matters because a license travels: it is in the purchase email, in support
     * tickets and in every backup of this database, and none of those are places for
     * a sending credential.
     *
     * @since 2.3.1
     * @return array|\WP_Error Stored credential summary, or the failure reason.
     */
    public static function connect_with_license() {
        $license_key = (string) get_option( 'joinotify_license_key', '' );

        if ( '' === trim( $license_key ) ) {
            return new \WP_Error( 'joinotify_connect_no_license', __( 'Activate your license before connecting.', 'joinotify' ) );
        }

        $endpoint = apply_filters( 'Joinotify/Cloud_Api/Bootstrap_Endpoint', Cloud_Client::base_url() . '/sites/bootstrap' );

        $response = wp_remote_post( $endpoint, array(
            'headers' => array(
                'Content-Type' => 'application/json',
                'Accept' => 'application/json',
            ),
            'body' => wp_json_encode( array(
                'licenseKey' => $license_key,
                'siteUrl' => home_url(),
            ) ),
            'timeout' => 30,
        ) );

        if ( is_wp_error( $response ) ) {
            Logger::register_log( $response, 'ERROR' );
            return $response;
        }

        $code_http = (int) wp_remote_retrieve_response_code( $response );
        $body = json_decode( wp_remote_retrieve_body( $response ), true );
        $body = is_array( $body ) ? $body : array();

        if ( $code_http < 200 || $code_http >= 300 ) {
            $message = $body['error']['message'] ?? __( 'Your license is not linked to a Joinotify Cloud account yet.', 'joinotify' );

            return new \WP_Error( 'joinotify_connect_license_failed', $message, array( 'status' => $code_http ) );
        }

        $data = isset( $body['data'] ) && is_array( $body['data'] ) ? $body['data'] : array();
        $token = (string) ( $data['apiKey'] ?? '' );

        if ( '' === $token ) {
            return new \WP_Error( 'joinotify_connect_no_key', __( 'The Joinotify API did not return a key for this site.', 'joinotify' ) );
        }

        $credentials = array(
            'token' => $token,
            'waba_id' => (string) ( $data['defaultWabaId'] ?? '' ),
            'phone_number_id' => (string) ( $data['defaultPhoneNumberId'] ?? '' ),
        );

        self::store_credentials( $credentials );

        // The listing came in the same response — the API knows this is the moment the
        // plugin needs it, because choosing the origin number is the next step of the
        // install. Storing it here saves a round trip and makes the settings screen
        // usable the instant this returns.
        $senders = isset( $data['senders'] ) && is_array( $data['senders'] ) ? $data['senders'] : array();

        Sender_Sync::flush_cache();
        $stored = empty( $senders ) ? Sender_Sync::sync() : Sender_Sync::store( $senders );

        return array(
            'token' => $credentials['token'],
            'waba_id' => $credentials['waba_id'],
            'phone_number_id' => $credentials['phone_number_id'],
            'account' => (string) ( $data['account']['name'] ?? '' ),
            'senders' => is_wp_error( $stored ) ? array() : $stored,
            'sync_error' => is_wp_error( $stored ) ? $stored->get_error_message() : '',
        );
    }


    /**
     * Adopt an API key the user pasted by hand.
     *
     * The key has to be stored before it can be tested — every Cloud_Client call
     * reads it from the settings — so this writes it, proves it works by asking
     * the API for the account's numbers, and puts the previous value back if it
     * does not. A site is never left holding a key that was just shown to fail.
     *
     * @since 2.3.0
     * @param string $token | API key issued for this site (sk_live_...).
     * @return array|\WP_Error Stored credential summary, or the failure reason.
     */
    public static function connect_with_key( $token ) {
        $token = sanitize_text_field( $token );

        if ( '' === $token ) {
            return new \WP_Error( 'joinotify_connect_empty_key', __( 'Paste the API key issued for this site on the Joinotify panel.', 'joinotify' ) );
        }

        $settings = get_option( 'joinotify_settings', array() );
        $settings = is_array( $settings ) ? $settings : array();
        $previous = isset( $settings['whatsapp_cloud_api_token'] ) ? (string) $settings['whatsapp_cloud_api_token'] : '';

        self::write_token( $settings, $token );

        // The listing is the proof: a key the API refuses cannot produce one.
        $senders = Sender_Sync::sync();

        if ( is_wp_error( $senders ) ) {
            self::write_token( $settings, $previous );

            return $senders;
        }

        $account = Cloud_Client::get_account();
        $account_name = '';

        if ( ! is_wp_error( $account ) && isset( $account['data'] ) && is_array( $account['data'] ) ) {
            $account_name = sanitize_text_field( (string) ( $account['data']['name'] ?? '' ) );
        }

        // sync() answers with the stored phone list; the full entries it just
        // cached are what carry the origin and business account ids, so the site
        // can default to the first number instead of asking the user to copy two
        // more ids from the panel. Reading them back is a cache hit, not a call.
        $numbers = Sender_Sync::fetch_numbers();
        $first = ! is_wp_error( $numbers ) && isset( $numbers[0] ) && is_array( $numbers[0] ) ? $numbers[0] : array();

        self::store_credentials( array(
            'token' => $token,
            'waba_id' => (string) ( $first['waba_id'] ?? '' ),
            'phone_number_id' => (string) ( $first['phone_number_id'] ?? '' ),
        ) );

        if ( '' !== (string) ( $first['phone_number_id'] ?? '' ) ) {
            /**
             * Fires when the site ends up with a sending number configured.
             *
             * Carries how it happened and nothing else — deliberately not the number.
             * The one consumer today is telemetry, and a phone number has no business
             * travelling on a hook that feeds it.
             *
             * @since 2.3.0
             * @param string $method How it was chosen: 'auto' or 'manual'.
             */
            do_action( 'Joinotify/Sender_Selected', 'auto' );
        }

        // Delivery reports are a bonus, not a requirement: a site that is not
        // publicly reachable over HTTPS still sends fine, it just never learns
        // what happened afterwards. So a failure here is reported, not fatal.
        $webhook = Webhooks::register();

        return array(
            'token' => $token,
            'account' => $account_name,
            'senders' => $senders,
            'webhook_error' => is_wp_error( $webhook ) ? $webhook->get_error_message() : '',
        );
    }


    /**
     * Write the API key to the setting every credential reader looks at.
     *
     * @since 2.3.0
     * @param array  $settings | Settings array to write into.
     * @param string $token    | Key to store ('' clears it).
     * @return void
     */
    protected static function write_token( $settings, $token ) {
        $settings['whatsapp_cloud_api_token'] = $token;

        update_option( 'joinotify_settings', $settings );

        Sender_Sync::flush_cache();
    }


    /**
     * Trade the single-use code for an API key, server to server.
     *
     * The answer carries the numbers the key reaches, exactly like the license
     * bootstrap does — there is no `wabaId`/`phoneNumberId` beside the key, so
     * the default origin is the first sender in that listing.
     *
     * @since 2.3.0
     * @version 2.4.0
     * @param string $code | Single-use code.
     * @return array|\WP_Error
     */
    protected static function exchange( $code ) {
        $endpoint = apply_filters( 'Joinotify/Cloud_Api/Exchange_Endpoint', Cloud_Client::base_url() . '/keys/exchange' );

        $response = wp_remote_post( $endpoint, array(
            'headers' => array(
                'Content-Type' => 'application/json',
                'Accept' => 'application/json',
            ),
            'body' => wp_json_encode( array(
                'code' => $code,
                'siteUrl' => home_url(),
            ) ),
            'timeout' => 30,
        ) );

        if ( is_wp_error( $response ) ) {
            Logger::register_log( $response, 'ERROR' );
            return $response;
        }

        $code_http = (int) wp_remote_retrieve_response_code( $response );
        $body = json_decode( wp_remote_retrieve_body( $response ), true );
        $body = is_array( $body ) ? $body : array();

        if ( $code_http < 200 || $code_http >= 300 ) {
            $message = $body['error']['message'] ?? __( 'The Joinotify panel refused this connection. Start the connection again.', 'joinotify' );

            return new \WP_Error( 'joinotify_connect_exchange_failed', $message, array( 'status' => $code_http ) );
        }

        $data = isset( $body['data'] ) && is_array( $body['data'] ) ? $body['data'] : $body;
        $token = (string) ( $data['apiKey'] ?? $data['secret'] ?? '' );

        if ( '' === $token ) {
            return new \WP_Error( 'joinotify_connect_no_key', __( 'The Joinotify panel did not return an API key.', 'joinotify' ) );
        }

        $senders = isset( $data['senders'] ) && is_array( $data['senders'] ) ? $data['senders'] : array();
        $first = isset( $senders[0] ) && is_array( $senders[0] ) ? $senders[0] : array();

        return array(
            'token' => $token,
            'waba_id' => (string) ( $first['wabaId'] ?? '' ),
            'phone_number_id' => (string) ( $first['phoneNumberId'] ?? '' ),
            'senders' => $senders,
        );
    }


    /**
     * Persist the credentials on the same settings the manual fields write to.
     *
     * @since 2.3.0
     * @param array $credentials | Result of exchange().
     * @return void
     */
    protected static function store_credentials( $credentials ) {
        $settings = get_option( 'joinotify_settings', array() );
        $settings = is_array( $settings ) ? $settings : array();

        $settings['whatsapp_cloud_api_token'] = $credentials['token'];

        if ( '' !== $credentials['waba_id'] ) {
            $settings['whatsapp_waba_id'] = $credentials['waba_id'];
        }

        if ( '' !== $credentials['phone_number_id'] ) {
            $settings['whatsapp_phone_number_id'] = $credentials['phone_number_id'];
        }

        update_option( 'joinotify_settings', $settings );

        do_action( 'Joinotify/Cloud_Api/Connected', $credentials );
    }


    /**
     * Admin URL the panel sends the user back to.
     *
     * @since 2.3.0
     * @return string
     */
    protected static function return_url() {
        return admin_url( 'admin.php?page=joinotify-settings' );
    }
}
