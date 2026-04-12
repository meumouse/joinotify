<?php
/**
 * Settings_Bootstrap source file.
 *
 * @since 1.4.7
 * @version 1.4.7
 */

namespace MeuMouse\Joinotify\Rest;

use MeuMouse\Joinotify\Admin\Settings\Registry;
use WP_REST_Request;

defined('ABSPATH') || exit;

/**
 * Return the settings application bootstrap payload.
 */
class Settings_Bootstrap extends Abstract_Route {

    /**
     * Route path for the bootstrap payload.
     *
     * @var string
     */
    protected $route = '/admin/settings';

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
        return rest_ensure_response( Registry::get_bootstrap_data() );
    }
}
