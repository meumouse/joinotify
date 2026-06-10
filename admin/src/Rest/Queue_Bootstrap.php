<?php

namespace MeuMouse\Joinotify\Rest;

use MeuMouse\Joinotify\Admin\Queue\Registry;
use WP_REST_Request;

defined('ABSPATH') || exit;

/**
 * Serve the bootstrap payload for the processing-queue Vue screen.
 *
 * @since 2.0.0
 */
class Queue_Bootstrap extends Abstract_Route {

	/**
	 * Route path.
	 *
	 * @var string
	 */
	protected $route = '/admin/queue/bootstrap';

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
