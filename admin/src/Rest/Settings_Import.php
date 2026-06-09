<?php

namespace MeuMouse\Joinotify\Rest;

use MeuMouse\Joinotify\Admin\Settings\Registry;
use MeuMouse\Joinotify\Admin\Settings\Transfer;
use WP_REST_Request;

defined('ABSPATH') || exit;

/**
 * Import a previously exported settings payload, merging it into the config.
 *
 * @since 2.0.0
 */
class Settings_Import extends Abstract_Route {

    /**
     * Route path for importing settings.
     *
     * @var string
     */
    protected $route = '/admin/settings/import';

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
        $body = $request->get_json_params();
        $payload = isset( $body['payload'] ) ? $body['payload'] : $body;

        $result = Transfer::import( $payload );

        if ( empty( $result['success'] ) ) {
            return $this->error_response( $result['message'] );
        }

        return $this->success_response( array(
            'message'   => $result['message'],
            'imported'  => $result['imported'] ?? array(),
            'bootstrap' => Registry::get_bootstrap_data(),
        ) );
    }
}
