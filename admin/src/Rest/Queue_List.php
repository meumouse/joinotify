<?php

namespace MeuMouse\Joinotify\Rest;

use MeuMouse\Joinotify\Admin\Queue\Registry;
use WP_REST_Request;

defined('ABSPATH') || exit;

/**
 * List (filter + paginate) pending scheduled segments for the Vue screen.
 *
 * @since 2.0.0
 */
class Queue_List extends Abstract_Route {

	/**
	 * Route path.
	 *
	 * @var string
	 */
	protected $route = '/admin/queue';

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
			'status'      => (string) $request->get_param( 'status' ),
			'workflow_id' => (int) $request->get_param( 'workflow_id' ),
			'search'      => (string) $request->get_param( 'search' ),
			'page'        => (int) $request->get_param( 'page' ),
			'per_page'    => (int) $request->get_param( 'per_page' ),
		);

		return $this->success_response( Registry::get_list_state( $args ) );
	}
}
