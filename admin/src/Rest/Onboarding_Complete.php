<?php

namespace MeuMouse\Joinotify\Rest;

use MeuMouse\Joinotify\Core\Onboarding;
use WP_REST_Request;

defined('ABSPATH') || exit;

/**
 * Mark the setup wizard as finished.
 *
 * @since 2.3.0
 */
class Onboarding_Complete extends Abstract_Route {

    /**
     * Route path.
     *
     * @var string
     */
    protected $route = '/admin/onboarding/complete';

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
        Onboarding::complete();

        return $this->success_response( array(
            'message' => esc_html__( 'Setup finished.', 'joinotify' ),
            'state' => Onboarding::get_state(),
        ) );
    }
}
