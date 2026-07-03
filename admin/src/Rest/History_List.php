<?php

namespace MeuMouse\Joinotify\Rest;

use MeuMouse\Joinotify\Admin\History\Registry;
use WP_REST_Request;

defined('ABSPATH') || exit;

/**
 * List (filter + paginate) message history rows for the Vue screen.
 *
 * @since 2.0.0
 */
class History_List extends Abstract_Route {

	/**
	 * Route path.
	 *
	 * @var string
	 */
	protected $route = '/admin/history';

	/**
	 * HTTP methods.
	 *
	 * @var string
	 */
	protected $methods = 'GET';


	/**
	 * Handle request.
	 *
	 * @param WP_REST_Request $request Request instance.
	 * @return \WP_REST_Response
	 */
	public function handle( WP_REST_Request $request ) {
		$args = array(
			'status' => (string) $request->get_param( 'status' ),
			'source' => (string) $request->get_param( 'source' ),
			'search' => (string) $request->get_param( 'search' ),
			'date_from' => (string) $request->get_param( 'date_from' ),
			'date_to' => (string) $request->get_param( 'date_to' ),
			'page' => (int) $request->get_param( 'page' ),
			'per_page' => (int) $request->get_param( 'per_page' ),
		);

		return $this->success_response( Registry::get_list_state( $args ) );
	}
}
