<?php

namespace MeuMouse\Joinotify\Rest;

use MeuMouse\Joinotify\Api\Updater;
use WP_REST_Request;

defined('ABSPATH') || exit;

/**
 * Check for plugin updates on demand from the settings About tab.
 *
 * @since 2.0.0
 */
class Check_Updates extends Abstract_Route {

    /**
     * Route path for checking updates.
     *
     * @var string
     */
    protected $route = '/admin/settings/check-updates';

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
        $result = Updater::check_for_updates();

        if ( empty( $result['checked'] ) ) {
            return $this->error_response(
                esc_html__( 'Could not check for updates. Please try again later.', 'joinotify' ),
                array(
                    'current_version' => $result['current_version'],
                )
            );
        }

        if ( $result['update_available'] ) {
            $message = sprintf(
                /* translators: %s: latest available version number */
                esc_html__( 'A new version (%s) is available!', 'joinotify' ),
                $result['latest_version']
            );
        } else {
            $message = esc_html__( 'Joinotify is up to date.', 'joinotify' );
        }

        return $this->success_response( array(
            'message' => $message,
            'update_available' => $result['update_available'],
            'current_version' => $result['current_version'],
            'latest_version' => $result['latest_version'],
            'update_url' => $result['update_url'],
        ) );
    }
}
