<?php

namespace MeuMouse\Joinotify\Rest;

use MeuMouse\Joinotify\Admin\Settings\Registry as Settings_Registry;
use MeuMouse\Joinotify\Api\Cloud_Client;
use MeuMouse\Joinotify\Api\Sender_Sync;
use MeuMouse\Joinotify\Api\Webhooks;
use MeuMouse\Joinotify\Core\Logger;
use WP_REST_Request;

defined('ABSPATH') || exit;

/**
 * Validate and store the Joinotify API key pasted in the setup wizard.
 *
 * The key has to be written before it can be tested — every Cloud_Client call
 * reads it from the settings — so this route writes it, proves it works, and
 * puts the previous value back if it does not. A site is never left holding a
 * key that was just shown to fail.
 *
 * @since 2.4.0
 */
class Onboarding_Connect extends Abstract_Route {

    /**
     * Route path.
     *
     * @var string
     */
    protected $route = '/admin/onboarding/connect';

    /**
     * Allowed HTTP methods.
     *
     * @var string
     */
    protected $methods = 'POST';


    /**
     * Handle the request.
     *
     * @since 2.4.0
     * @param WP_REST_Request $request REST request instance.
     * @return \WP_REST_Response
     */
    public function handle( WP_REST_Request $request ) {
        $payload = $request->get_json_params();
        $payload = is_array( $payload ) ? $payload : array();
        $token = isset( $payload['api_key'] ) ? sanitize_text_field( $payload['api_key'] ) : '';

        if ( '' === $token ) {
            return $this->error_response( esc_html__( 'Paste the API key issued for this site on the Joinotify panel.', 'joinotify' ) );
        }

        $settings = get_option( 'joinotify_settings', array() );
        $settings = is_array( $settings ) ? $settings : array();
        $previous_token = isset( $settings['whatsapp_cloud_api_token'] ) ? (string) $settings['whatsapp_cloud_api_token'] : '';

        $this->store_token( $settings, $token );

        $senders = Sender_Sync::sync();

        if ( is_wp_error( $senders ) ) {
            // Put the site back exactly as it was before this attempt.
            $this->store_token( $settings, $previous_token );
            Sender_Sync::flush_cache();

            return $this->error_response( $senders->get_error_message() );
        }

        $account = Cloud_Client::get_account();
        $account_name = '';

        if ( ! is_wp_error( $account ) && isset( $account['data']['name'] ) ) {
            $account_name = sanitize_text_field( (string) $account['data']['name'] );
        }

        // Delivery reports are a bonus: a site that is not publicly reachable
        // over HTTPS still sends fine, it just never hears back. Report it, do
        // not fail on it.
        $webhook = Webhooks::register();
        $webhook_error = '';

        if ( is_wp_error( $webhook ) ) {
            $webhook_error = $webhook->get_error_message();
            Logger::register_log( 'Joinotify: webhook registration failed during setup: ' . $webhook_error, 'WARNING' );
        }

        return $this->success_response( array(
            'message' => esc_html__( 'Your Joinotify account is connected.', 'joinotify' ),
            'account' => $account_name,
            'senders' => $senders,
            'sender_count' => count( $senders ),
            'webhook_error' => $webhook_error,
            'phones' => Settings_Registry::get_phone_state(),
        ) );
    }


    /**
     * Write the API key to the same setting the settings screen writes to.
     *
     * @since 2.4.0
     * @param array<string,mixed> $settings Current settings array.
     * @param string              $token    Key to store ('' clears it).
     * @return void
     */
    private function store_token( $settings, $token ) {
        $settings['whatsapp_cloud_api_token'] = $token;

        update_option( 'joinotify_settings', $settings );

        Sender_Sync::flush_cache();
    }
}
