<?php

namespace MeuMouse\Joinotify\Rest;

use MeuMouse\Joinotify\Admin\Settings\Registry;
use MeuMouse\Joinotify\Api\License;
use WP_REST_Request;

defined('ABSPATH') || exit;

/**
 * Deactivate the current license.
 *
 * @since 1.4.7
 */
class License_Deactivate extends Abstract_Route {

    /**
     * Route path.
     *
     * @var string
     */
    protected $route = '/admin/settings/license/deactivate';

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
        $message = '';

        if ( License::deactive_license( JOINOTIFY_FILE, $message ) ) {
            return $this->success_response( array(
                'message'      => esc_html__( 'License deactivated successfully.', 'joinotify' ),
                'license_data' => Registry::get_license_state(),
            ) );
        }

        return $this->error_response( ! empty( $message ) ? $message : esc_html__( 'Could not deactivate the license.', 'joinotify' ) );
    }
}
