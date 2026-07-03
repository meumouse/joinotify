<?php

namespace MeuMouse\Joinotify\Rest;

use MeuMouse\Joinotify\Admin\Workflows\Registry;
use WP_REST_Request;

defined('ABSPATH') || exit;

/**
 * Return the workflows-list application bootstrap payload.
 *
 * @since 2.0.0
 */
class Workflows_Bootstrap extends Abstract_Route {

	/**
	 * Route path.
	 *
	 * @var string
	 */
	protected $route = '/admin/workflows/bootstrap';

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
		$status = sanitize_text_field( (string) $request->get_param( 'post_status' ) );

		return rest_ensure_response( Registry::get_bootstrap_data( $status ?: 'publish' ) );
	}
}
