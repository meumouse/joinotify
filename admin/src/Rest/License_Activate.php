<?php

namespace MeuMouse\Joinotify\Rest;

use MeuMouse\Joinotify\Admin\Settings\Registry;
use MeuMouse\Joinotify\Api\License;
use MeuMouse\Joinotify\Core\Cache_Helper;
use WP_REST_Request;
use stdClass;

defined('ABSPATH') || exit;

/**
 * Activate a license key.
 *
 * @since 1.4.7
 */
class License_Activate extends Abstract_Route {

    /**
     * Route path.
     *
     * @var string
     */
    protected $route = '/admin/settings/license/activate';

    /**
     * HTTP methods.
     *
     * @var string
     */
    protected $methods = 'POST';


    /**
     * Handle request.
     *
     * @param WP_REST_Request $request Request instance.
     * @return \WP_REST_Response
     */
    public function handle( WP_REST_Request $request ) {
        $payload     = $request->get_json_params();
        $license_key = isset( $payload['license_key'] ) ? sanitize_text_field( $payload['license_key'] ) : '';

        if ( empty( $license_key ) ) {
            return $this->error_response( esc_html__( 'License key is required.', 'joinotify' ) );
        }

        $response_obj    = new stdClass();
        $license_message = '';

        Cache_Helper::clear_license_cache();

        update_option( 'joinotify_license_key', $license_key );
        update_option( 'joinotify_temp_license_key', $license_key );

        if ( License::check_license( $license_key, $license_message, $response_obj, JOINOTIFY_FILE ) ) {
            if ( $response_obj && $response_obj->is_valid ) {
                update_option( 'joinotify_license_status', 'valid' );
                delete_option( 'joinotify_temp_license_key' );
                delete_option( 'joinotify_alternative_license_activation' );
            } else {
                update_option( 'joinotify_license_status', 'invalid' );
            }

            if ( License::is_valid() ) {
                return $this->success_response( array(
                    'message'      => esc_html__( 'License activated successfully.', 'joinotify' ),
                    'license_data' => Registry::get_license_state(),
                ) );
            }
        }

        return $this->error_response(
            ! empty( $license_message ) ? $license_message : esc_html__( 'Could not activate the license.', 'joinotify' )
        );
    }
}
