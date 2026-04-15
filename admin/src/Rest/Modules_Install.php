<?php

namespace MeuMouse\Joinotify\Rest;

use MeuMouse\Joinotify\Core\Modules;
use WP_REST_Request;

defined('ABSPATH') || exit;

/**
 * Install or upgrade a Joinotify extension/module plugin.
 *
 * @since 1.4.7
 */
class Modules_Install extends Abstract_Route {

    /**
     * Route path.
     *
     * @var string
     */
    protected $route = '/admin/modules/install';

    /**
     * HTTP methods.
     *
     * @var string
     */
    protected $methods = 'POST';


    /**
     * Handle request.
     *
     * @param WP_REST_Request $request Request instance.
     * @return \WP_REST_Response
     */
    public function handle( WP_REST_Request $request ) {
        $payload     = $request->get_json_params();
        $plugin_slug = isset( $payload['plugin_slug'] ) ? sanitize_text_field( $payload['plugin_slug'] ) : '';
        $plugin_zip  = isset( $payload['plugin_url'] ) ? esc_url_raw( $payload['plugin_url'] ) : '';

        if ( empty( $plugin_slug ) || empty( $plugin_zip ) ) {
            return rest_ensure_response( array(
                'status'  => 'error',
                'message' => esc_html__( 'Plugin slug and URL are required.', 'joinotify' ),
            ) );
        }

        ob_start();

        $installed = Modules::is_plugin_installed( $plugin_slug )
            ? Modules::upgrade_plugin( $plugin_slug )
            : Modules::install_plugin( $plugin_zip );

        ob_end_clean();

        if ( is_wp_error( $installed ) || ! $installed ) {
            return rest_ensure_response( array(
                'status'  => 'error',
                'message' => esc_html__( 'Failed to install or update the plugin.', 'joinotify' ),
            ) );
        }

        $plugin_file = WP_PLUGIN_DIR . '/' . $plugin_slug;
        $activate    = activate_plugin( $plugin_file );

        if ( is_wp_error( $activate ) ) {
            return rest_ensure_response( array(
                'status'  => 'error',
                'message' => esc_html__( 'The plugin was installed but could not be activated.', 'joinotify' ),
            ) );
        }

        return rest_ensure_response( array(
            'status'  => 'success',
            'message' => esc_html__( 'Plugin installed and activated successfully.', 'joinotify' ),
        ) );
    }
}
