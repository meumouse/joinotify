<?php

namespace MeuMouse\Joinotify\Rest;

use MeuMouse\Joinotify\Admin\Onboarding\Registry;
use MeuMouse\Joinotify\Core\Onboarding;
use WP_REST_Request;

defined('ABSPATH') || exit;

/**
 * Persist the values of a single wizard step.
 *
 * @since 2.4.0
 */
class Onboarding_Step extends Abstract_Route {

    /**
     * Route path.
     *
     * @var string
     */
    protected $route = '/admin/onboarding/step';

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
        $payload = $request->get_json_params();
        $payload = is_array( $payload ) ? $payload : array();

        $step = isset( $payload['step'] ) ? sanitize_key( $payload['step'] ) : '';
        $values = isset( $payload['values'] ) && is_array( $payload['values'] ) ? $payload['values'] : array();

        // Registry::save_step_values() drops anything outside the wizard's own
        // key list and runs the rest through the settings sanitizers.
        $settings = Registry::save_step_values( $values );

        if ( '' !== $step ) {
            Onboarding::save_progress( $step );
        }

        return $this->success_response( array(
            'message' => __( 'Step saved.', 'joinotify' ),
            'settings' => Registry::visible_settings( $settings ),
        ) );
    }
}
