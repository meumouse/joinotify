<?php

namespace MeuMouse\Joinotify\Rest;

use MeuMouse\Joinotify\Admin\Workflows\Registry;
use WP_REST_Request;

defined('ABSPATH') || exit;

/**
 * List workflows for the Vue workflows screen.
 *
 * @since 2.0.0
 */
class Workflows_List extends Abstract_Route {

	/**
	 * Route path.
	 *
	 * @var string
	 */
	protected $route = '/admin/workflows';

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
		$search = sanitize_text_field( (string) $request->get_param( 'search' ) );

		return $this->success_response( Registry::get_list_state( $search ) );
	}
}
