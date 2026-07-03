<?php

namespace MeuMouse\Joinotify\Rest;

use MeuMouse\Joinotify\Admin\Builder\Registry;
use WP_REST_Request;

defined('ABSPATH') || exit;

/**
 * Return the workflow builder bootstrap payload.
 *
 * @since 1.4.7
 */
class Builder_Bootstrap extends Abstract_Route {

	/**
	 * Route path.
	 *
	 * @var string
	 */
	protected $route = '/admin/builder';

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
		$post_id = absint( $request->get_param( 'id' ) );

		return rest_ensure_response( Registry::get_bootstrap_data( $post_id ) );
	}
}
