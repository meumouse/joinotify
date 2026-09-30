<?php

namespace MeuMouse\Joinotify\Rest;

use MeuMouse\Joinotify\Api\Cloud_Contacts;
use WP_REST_Request;

defined('ABSPATH') || exit;

/**
 * List the account's saved audiences for the "Audiences & Contacts" screen.
 *
 * @since 2.5.0
 */
class Audiences_List extends Abstract_Cloud_Route {

	/**
	 * Route path.
	 *
	 * @var string
	 */
	protected $route = '/admin/audiences';

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
		$page = max( 1, (int) $request->get_param( 'page' ) );
		$per_page = (int) $request->get_param( 'per_page' ) ?: 25;
		$envelope = Cloud_Contacts::list_audiences( array(
			'page' => $page,
			'per_page' => $per_page,
			'search' => (string) $request->get_param( 'search' ),
			'archived' => (bool) $request->get_param( 'archived' ),
		) );

		if ( ! $envelope['ok'] ) {
			return $this->envelope_error( $envelope );
		}

		return $this->success_response( array(
			'items' => is_array( $envelope['data'] ) ? $envelope['data'] : array(),
			'pagination' => Cloud_Contacts::pagination( $envelope, $page, $per_page ),
		) );
	}
}
