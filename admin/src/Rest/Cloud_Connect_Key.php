<?php

namespace MeuMouse\Joinotify\Rest;

use MeuMouse\Joinotify\Admin\Settings\Registry;
use MeuMouse\Joinotify\Api\Connect;
use MeuMouse\Joinotify\Core\Helpers;
use WP_REST_Request;

defined('ABSPATH') || exit;

/**
 * Adopt, or forget, the Joinotify API key from the WhatsApp settings modal.
 *
 * The same handshake the setup wizard runs, reachable from Settings so a site
 * can rotate or revoke its key long after the wizard is done.
 *
 * @since 2.3.0
 */
class Cloud_Connect_Key extends Abstract_Route {

    /**
     * Route path.
     *
     * @var string
     */
    protected $route = '/admin/cloud/connect/key';

    /**
     * Allowed HTTP methods.
     *
     * @var string
     */
    protected $methods = 'POST';


    /**
     * Handle the request.
     *
     * @since 2.3.0
     * @param WP_REST_Request $request REST request instance.
     * @return \WP_REST_Response
     */
    public function handle( WP_REST_Request $request ) {
        $payload = $request->get_json_params();
        $payload = is_array( $payload ) ? $payload : array();

        if ( ! empty( $payload['disconnect'] ) ) {
            return $this->disconnect();
        }

        $result = Connect::connect_with_key( isset( $payload['api_key'] ) ? (string) $payload['api_key'] : '' );

        if ( is_wp_error( $result ) ) {
            return $this->error_response( $result->get_error_message() );
        }

        return $this->success_response( array(
            'message' => esc_html__( 'Your Joinotify account is connected.', 'joinotify' ),
            'connection' => self::connection_state(),
            'account' => $result['account'],
            'sender_count' => count( $result['senders'] ),
            'webhook_error' => $result['webhook_error'],
            'phones' => Registry::get_phone_state(),
        ) );
    }


    /**
     * Forget the stored key.
     *
     * The imported numbers are left alone on purpose: they are a record of what
     * this site was sending from, and wiping them would also wipe the message
     * history's ability to name the sender of past messages.
     *
     * @since 2.3.0
     * @return \WP_REST_Response
     */
    protected function disconnect() {
        $settings = get_option( 'joinotify_settings', array() );
        $settings = is_array( $settings ) ? $settings : array();

        $settings['whatsapp_cloud_api_token'] = '';
        $settings['whatsapp_phone_number_id'] = '';
        $settings['whatsapp_waba_id'] = '';

        update_option( 'joinotify_settings', $settings );

        do_action('Joinotify/Cloud_Api/Disconnected');

        return $this->success_response( array(
            'message' => esc_html__( 'This site is no longer connected to Joinotify.', 'joinotify' ),
            'connection' => self::connection_state(),
            'phones' => Registry::get_phone_state(),
        ) );
    }


    /**
     * Describe the connection without ever handing the key back to the browser.
     *
     * @since 2.3.0
     * @return array{connected:bool,key_prefix:string,phone_number_id:string,waba_id:string}
     */
    public static function connection_state() {
        $token = Helpers::cloud_api_token();

        return array(
            'connected' => '' !== $token,
            // The leading `sk_live_`/`sk_test_` segment is public and is enough
            // to tell two keys apart; the secret half never needs to be read back.
            'key_prefix' => '' !== $token ? substr( $token, 0, 14 ) : '',
            'phone_number_id' => Helpers::cloud_phone_number_id(),
            'waba_id' => Helpers::cloud_waba_id(),
        );
    }
}
