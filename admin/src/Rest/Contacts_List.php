<?php

namespace MeuMouse\Joinotify\Rest;

use MeuMouse\Joinotify\Api\Cloud_Contacts;
use WP_REST_Request;

defined('ABSPATH') || exit;

/**
 * List (filter + paginate) the account's contacts for the "Audiences & Contacts" screen.
 *
 * @since 2.5.0
 */
class Contacts_List extends Abstract_Cloud_Route {

	/**
	 * Route path.
	 *
	 * @var string
	 */
	protected $route = '/admin/contacts';

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
		$args = array(
			'page' => max( 1, (int) $request->get_param( 'page' ) ),
			'per_page' => (int) $request->get_param( 'per_page' ) ?: 25,
			'sort' => (string) $request->get_param( 'sort' ),
			'search' => (string) $request->get_param( 'search' ),
			'tag_id' => (string) $request->get_param( 'tag_id' ),
			'opt_in_status' => (string) $request->get_param( 'opt_in_status' ),
			'source' => (string) $request->get_param( 'source' ),
			'audience_id' => (string) $request->get_param( 'audience_id' ),
		);

		$envelope = Cloud_Contacts::list_contacts( $args );

		if ( ! $envelope['ok'] ) {
			return $this->envelope_error( $envelope );
		}

		return $this->success_response( array(
			'items' => is_array( $envelope['data'] ) ? $envelope['data'] : array(),
			'pagination' => Cloud_Contacts::pagination( $envelope, $args['page'], $args['per_page'] ),
		) );
	}
}
