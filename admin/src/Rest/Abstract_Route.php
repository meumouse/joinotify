<?php

namespace MeuMouse\Joinotify\Rest;

use WP_REST_Request;

defined('ABSPATH') || exit;

/**
 * Base class for Joinotify REST endpoints.
 */
abstract class Abstract_Route {

    /**
     * REST route path, including the leading slash.
     *
     * @var string
     */
    protected $route = '';

    /**
     * Allowed HTTP method or method list.
     *
     * @var string
     */
    protected $methods = 'GET';

    /**
     * REST argument schema.
     *
     * @var array
     */
    protected $args = array();


    /**
     * Register the route when WordPress initializes REST endpoints.
     */
    public function __construct() {
        if ( $this->should_register() ) {
            add_action( 'rest_api_init', array( $this, 'register_route' ) );
        }
    }


    /**
     * Decide whether the route should be registered.
     *
     * @return bool
     */
    protected function should_register() {
        return true;
    }


    /**
     * Register the route with the WordPress REST API.
     *
     * @return void
     */
    public function register_route() {
        register_rest_route( 'joinotify/v1', $this->route, array(
            'methods' => $this->methods,
            'callback' => array( $this, 'handle' ),
            'permission_callback' => array( $this, 'permission' ),
            'args' => $this->args,
        ) );
    }


    /**
     * Default permission check for admin-only endpoints.
     *
     * @param WP_REST_Request $request REST request instance.
     * @return bool
     */
    public function permission( WP_REST_Request $request ) {
        return current_user_can( 'manage_options' );
    }


    /**
     * Handle the REST request.
     *
     * @param WP_REST_Request $request REST request instance.
     * @return mixed
     */
    abstract public function handle( WP_REST_Request $request );


    /**
     * Build a standardised success REST response.
     *
     * @since 1.4.7
     * @param array $data Additional key/value pairs to merge into the response body.
     * @return \WP_REST_Response
     */
    protected function success_response( array $data = array() ) {
        return rest_ensure_response( array_merge( array( 'status' => 'success' ), $data ) );
    }


    /**
     * Build a standardised error REST response.
     *
     * @since 1.4.7
     * @param string $message Human-readable error description (already escaped).
     * @param array  $data    Optional additional key/value pairs.
     * @return \WP_REST_Response
     */
    protected function error_response( $message, array $data = array() ) {
        return rest_ensure_response( array_merge( array(
            'status'  => 'error',
            'message' => $message,
        ), $data ) );
    }
}
