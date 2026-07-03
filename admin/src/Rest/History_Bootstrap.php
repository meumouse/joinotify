<?php

namespace MeuMouse\Joinotify\Rest;

use MeuMouse\Joinotify\Admin\History\Registry;
use WP_REST_Request;

defined('ABSPATH') || exit;

/**
 * Serve the bootstrap payload for the history Vue screen.
 *
 * @since 2.0.0
 */
class History_Bootstrap extends Abstract_Route {

	/**
	 * Route path.
	 *
	 * @var string
	 */
	protected $route = '/admin/history/bootstrap';

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
		return rest_ensure_response( Registry::get_bootstrap_data() );
	}
}
