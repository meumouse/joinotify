<?php

namespace MeuMouse\Joinotify\Rest;

use MeuMouse\Joinotify\Admin\Settings\Registry;
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
     * The screens rehydrate their local copy from this answer, so it goes back
     * through the same write-only filter the bootstrap uses. Echoing `$saved`
     * raw would hand the browser the very credential the bootstrap withheld.
     *
     * @version 2.4.0
     * @param WP_REST_Request $request REST request instance.
     * @return \WP_REST_Response
     */
    public function handle( WP_REST_Request $request ) {
        $payload = $request->get_json_params();
        $settings = isset( $payload['settings'] ) && is_array( $payload['settings'] ) ? $payload['settings'] : array();
        $saved = Repository::save_settings( $settings );

        return $this->success_response( array(
            'message'  => __( 'Settings saved.', 'joinotify' ),
            'settings' => Registry::get_settings_for_client( $saved ),
        ) );
    }
}
