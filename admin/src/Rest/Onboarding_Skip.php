<?php

namespace MeuMouse\Joinotify\Rest;

use MeuMouse\Joinotify\Core\Onboarding;
use WP_REST_Request;

defined('ABSPATH') || exit;

/**
 * Stop prompting for the setup wizard without marking it as finished.
 *
 * @since 2.4.0
 */
class Onboarding_Skip extends Abstract_Route {

    /**
     * Route path.
     *
     * @var string
     */
    protected $route = '/admin/onboarding/skip';

    /**
     * Allowed HTTP methods.
     *
     * @var string
     */
    protected $methods = 'POST';


    /**
     * Handle the request.
     *
     * @since 2.4.0
     * @param WP_REST_Request $request REST request instance.
     * @return \WP_REST_Response
     */
    public function handle( WP_REST_Request $request ) {
        Onboarding::dismiss();

        return $this->success_response( array(
            'message' => esc_html__( 'You can run the setup wizard again at any time.', 'joinotify' ),
            'state' => Onboarding::get_state(),
        ) );
    }
}
