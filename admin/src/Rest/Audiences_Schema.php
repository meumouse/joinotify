<?php

namespace MeuMouse\Joinotify\Rest;

use MeuMouse\Joinotify\Api\Cloud_Contacts;
use WP_REST_Request;

defined('ABSPATH') || exit;

/**
 * The native fields an audience filter accepts and the operators of each field type, which the
 * filter builder of the screen is drawn from.
 *
 * @since 2.5.0
 */
class Audiences_Schema extends Abstract_Cloud_Route {

	/**
	 * Route path.
	 *
	 * @var string
	 */
	protected $route = '/admin/audiences/schema';

	/**
	 * HTTP methods.
	 *
	 * @var string
	 */
	protected $methods = 'GET';


	/**
	 * Handle request.
	 *
	 * @since 2.5.0
	 * @param WP_REST_Request $request Request instance.
	 * @return \WP_REST_Response
	 */
	public function handle( WP_REST_Request $request ) {
		$envelope = Cloud_Contacts::audience_schema( (bool) $request->get_param( 'refresh' ) );

		return $this->respond( $envelope, $envelope['ok'] ? array( 'schema' => is_array( $envelope['data'] ) ? $envelope['data'] : array() ) : null );
	}
}
