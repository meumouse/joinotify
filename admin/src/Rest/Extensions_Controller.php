<?php

namespace MeuMouse\Joinotify\Rest;

defined('ABSPATH') || exit;

/**
 * Register third-party REST routes declared through the "Joinotify/Rest/Routes" filter.
 *
 * Built-in routes are authored as Abstract_Route subclasses and listed in the hardcoded
 * controllers (Builder/Settings/Workflows). To let developers add endpoints with PHP only —
 * no class authoring, no core edits — this controller collects route descriptors from the
 * filter and registers each under the plugin REST namespace (joinotify/v1).
 *
 * @since 1.4.7
 * @package MeuMouse\Joinotify\Rest
 */
class Extensions_Controller {

	/**
	 * REST namespace shared with Abstract_Route.
	 *
	 * @var string
	 */
	const NAMESPACE = 'joinotify/v1';

	/**
	 * Hook the route registration on rest_api_init.
	 *
	 * @since 1.4.7
	 * @return void
	 */
	public function __construct() {
		add_action( 'rest_api_init', array( $this, 'register_routes' ) );
	}


	/**
	 * Register every route declared through the filter.
	 *
	 * @since 1.4.7
	 * @return void
	 */
	public function register_routes() {
		/**
		 * Filter the list of custom REST routes to register under joinotify/v1.
		 *
		 * Each entry is a descriptor array:
		 * - route      (string)        Path with leading slash, eg: '/admin/my-plugin/data'.
		 * - methods     (string|array) HTTP method(s). Default 'GET'.
		 * - callback    (callable)     Handler receiving the WP_REST_Request.
		 * - permission  (callable|null) Permission callback. Default: current_user_can('manage_options').
		 * - args        (array)        REST argument schema. Default empty.
		 *
		 * @since 1.4.7
		 * @param array<int,array<string,mixed>> $routes Custom route descriptors.
		 * @return array<int,array<string,mixed>>
		 */
		$routes = apply_filters( 'Joinotify/Rest/Routes', array() );

		if ( ! is_array( $routes ) ) {
			return;
		}

		foreach ( $routes as $route ) {
			if ( empty( $route['route'] ) || empty( $route['callback'] ) || ! is_callable( $route['callback'] ) ) {
				continue;
			}

			$permission = isset( $route['permission'] ) && is_callable( $route['permission'] )
				? $route['permission']
				: array( $this, 'default_permission' );

			register_rest_route( self::NAMESPACE, (string) $route['route'], array(
				'methods' => $route['methods'] ?? 'GET',
				'callback' => $route['callback'],
				'permission_callback' => $permission,
				'args' => isset( $route['args'] ) && is_array( $route['args'] ) ? $route['args'] : array(),
			) );
		}
	}


	/**
	 * Default permission for custom routes — mirrors Abstract_Route::permission().
	 *
	 * @since 1.4.7
	 * @return bool
	 */
	public function default_permission() {
		return current_user_can( 'manage_options' );
	}
}
