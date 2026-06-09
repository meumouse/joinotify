<?php

namespace MeuMouse\Joinotify\Rest;

use MeuMouse\Joinotify\Admin\Settings\Transfer;
use WP_REST_Request;

defined('ABSPATH') || exit;

/**
 * Return the plugin settings as a portable export payload.
 *
 * @since 2.0.0
 */
class Settings_Export extends Abstract_Route {

    /**
     * Route path for exporting settings.
     *
     * @var string
     */
    protected $route = '/admin/settings/export';

    /**
     * Allowed HTTP methods.
     *
     * @var string
     */
    protected $methods = 'GET';


    /**
     * Handle the request.
     *
     * @param WP_REST_Request $request REST request instance.
     * @return \WP_REST_Response
     */
    public function handle( WP_REST_Request $request ) {
        return $this->success_response( array(
            'filename' => Transfer::get_export_filename(),
            'payload'  => Transfer::export(),
        ) );
    }
}
