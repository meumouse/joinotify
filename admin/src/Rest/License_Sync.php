<?php

namespace MeuMouse\Joinotify\Rest;

use MeuMouse\Joinotify\Admin\Settings\Registry;
use MeuMouse\Joinotify\Api\License;
use MeuMouse\Joinotify\Core\Cache_Helper;
use WP_REST_Request;
use stdClass;

defined('ABSPATH') || exit;

/**
 * Sync the license status from the remote server.
 *
 * @since 1.4.7
 */
class License_Sync extends Abstract_Route {

    /**
     * Route path.
     *
     * @var string
     */
    protected $route = '/admin/settings/license/sync';

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
        $response_obj    = new stdClass();
        $license_message = '';
        $license_key     = get_option( 'joinotify_license_key', '' );
        $license_key     = is_string( $license_key ) ? sanitize_text_field( $license_key ) : '';

        if ( empty( $license_key ) ) {
            return $this->error_response( esc_html__( 'No license key found to sync.', 'joinotify' ) );
        }

        Cache_Helper::clear_license_cache();

        if ( License::check_license( $license_key, $license_message, $response_obj, JOINOTIFY_FILE ) ) {
            if ( $response_obj && ! empty( $response_obj->is_valid ) ) {
                update_option( 'joinotify_license_status', 'valid' );
                delete_option( 'joinotify_alternative_license_activation' );
            } else {
                update_option( 'joinotify_license_status', 'invalid' );
            }

            return $this->success_response( array(
                'message'      => esc_html__( 'License information updated successfully.', 'joinotify' ),
                'license_data' => Registry::get_license_state(),
            ) );
        }

        return $this->error_response(
            ! empty( $license_message ) ? $license_message : esc_html__( 'Could not sync the license information.', 'joinotify' )
        );
    }
}
