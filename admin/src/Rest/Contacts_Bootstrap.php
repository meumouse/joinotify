<?php

namespace MeuMouse\Joinotify\Rest;

use MeuMouse\Joinotify\Admin\Contacts\Registry;
use WP_REST_Request;

defined('ABSPATH') || exit;

/**
 * Serve the bootstrap payload for the "Audiences & Contacts" Vue screen.
 *
 * @since 2.5.0
 */
class Contacts_Bootstrap extends Abstract_Route {

	/**
	 * Route path.
	 *
	 * @var string
	 */
	protected $route = '/admin/contacts/bootstrap';

	/**
	 * HTTP methods.
	 *
	 * @var string
	 */
	protected $methods = 'GET';


	/**
	 * Handle request.
	 *
	 * `refresh=1` asks the platform again what the key may do, e.g. after the site was
	 * reconnected in another tab.
	 *
	 * @since 2.5.0
	 * @param WP_REST_Request $request Request instance.
	 * @return \WP_REST_Response
	 */
	public function handle( WP_REST_Request $request ) {
		return rest_ensure_response( Registry::get_bootstrap_data( (bool) $request->get_param( 'refresh' ) ) );
	}
}
