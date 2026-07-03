<?php

namespace MeuMouse\Joinotify\Rest;

use MeuMouse\Joinotify\Admin\Settings\Repository;
use WP_REST_Request;

defined('ABSPATH') || exit;

/**
 * Persist the admin settings payload.
 */
class Settings_Save extends Abstract_Route {

    /**
     * Route path for saving settings.
     *
     * @var string
     */
    protected $route = '/admin/settings';

    /**
     * Allowed HTTP methods.
     *
     * @var string
     */
    protected $methods = 'POST';


    /**
     * Handle the request.
     *
     * @param WP_REST_Request $request REST request instance.
     * @return \WP_REST_Response
     */
    public function handle( WP_REST_Request $request ) {
        $payload = $request->get_json_params();
        $settings = isset( $payload['settings'] ) && is_array( $payload['settings'] ) ? $payload['settings'] : array();
        $saved = Repository::save_settings( $settings );

        return $this->success_response( array(
            'message'  => __( 'Settings saved.', 'joinotify' ),
            'settings' => $saved,
        ) );
    }
}
