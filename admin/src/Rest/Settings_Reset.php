<?php

namespace MeuMouse\Joinotify\Rest;

use MeuMouse\Joinotify\Admin\Settings\Registry;
use MeuMouse\Joinotify\Admin\Settings\Repository;
use WP_REST_Request;

defined('ABSPATH') || exit;

/**
 * Reset the admin settings to their defaults.
 */
class Settings_Reset extends Abstract_Route {

    /**
     * Route path for settings reset.
     *
     * @var string
     */
    protected $route = '/admin/settings/reset';

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
        $deleted = Repository::reset_settings();

        return rest_ensure_response( array(
            'status' => $deleted ? 'success' : 'error',
            'message' => $deleted
                ? esc_html__( 'Settings reset successfully!', 'joinotify' )
                : esc_html__( 'An error occurred while resetting the settings.', 'joinotify' ),
            'bootstrap' => Registry::get_bootstrap_data(),
        ) );
    }
}
