<?php

namespace MeuMouse\Joinotify\Rest;

use MeuMouse\Joinotify\Admin\Onboarding\Registry;
use WP_REST_Request;

defined('ABSPATH') || exit;

/**
 * Hydrate the setup wizard.
 *
 * @since 2.3.0
 */
class Onboarding_Bootstrap extends Abstract_Route {

    /**
     * Route path.
     *
     * @var string
     */
    protected $route = '/admin/onboarding';

    /**
     * Allowed HTTP methods.
     *
     * @var string
     */
    protected $methods = 'GET';


    /**
     * Handle the request.
     *
     * @since 2.3.0
     * @param WP_REST_Request $request REST request instance.
     * @return \WP_REST_Response
     */
    public function handle( WP_REST_Request $request ) {
        return rest_ensure_response( Registry::get_bootstrap_data() );
    }
}
