<?php

namespace MeuMouse\Joinotify\Rest;

use MeuMouse\Joinotify\Admin\Builder\Registry;
use WP_REST_Request;

defined('ABSPATH') || exit;

/**
 * Return the workflow template catalog.
 *
 * @since 1.4.7
 */
class Builder_Templates extends Abstract_Route {

	/**
	 * Route path.
	 *
	 * @var string
	 */
	protected $route = '/admin/builder/templates';

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
		return rest_ensure_response( array(
			'templates' => Registry::get_templates_catalog(),
		) );
	}
}
