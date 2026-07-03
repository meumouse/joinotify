<?php

namespace MeuMouse\Joinotify\Rest;

use MeuMouse\Joinotify\AI\Providers\OpenAI_Provider;
use WP_REST_Request;

defined('ABSPATH') || exit;

/**
 * Return the OpenAI model list for the settings dropdown.
 *
 * Supports a `refresh` flag to bypass the cache and fetch a fresh list from the
 * OpenAI API so newly released models become available on demand.
 *
 * @since 2.0.0
 * @package MeuMouse\Joinotify\Rest
 * @author MeuMouse.com
 */
class Ai_Openai_Models extends Abstract_Route {

    /**
     * Route path.
     *
     * @var string
     */
    protected $route = '/admin/ai/openai-models';

    /**
     * HTTP methods.
     *
     * @var string
     */
    protected $methods = 'GET';


    /**
     * Handle request.
     *
     * @since 2.0.0
     * @param WP_REST_Request $request | Request instance.
     * @return \WP_REST_Response
     */
    public function handle( WP_REST_Request $request ) {
        $provider = new OpenAI_Provider();
        $force = filter_var( $request->get_param('refresh'), FILTER_VALIDATE_BOOLEAN );

        if ( $force ) {
            $result = $provider->fetch_remote_models( true );

            if ( is_wp_error( $result ) ) {
                return rest_ensure_response( array(
                    'status'  => 'error',
                    'message' => $result->get_error_message(),
                    'models'  => $provider->get_models(),
                ) );
            }
        }

        return rest_ensure_response( array(
            'status' => 'success',
            'models' => $provider->get_models(),
        ) );
    }
}
