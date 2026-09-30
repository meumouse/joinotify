<?php

namespace MeuMouse\Joinotify\Rest;

use MeuMouse\Joinotify\Api\Cloud_Contacts;
use WP_REST_Request;

defined('ABSPATH') || exit;

/**
 * Read a contact's history: creation, edits, tags, consent, merges and campaign clicks.
 *
 * @since 2.5.0
 */
class Contacts_Activity extends Abstract_Cloud_Route {

	/**
	 * Route path.
	 *
	 * @var string
	 */
	protected $route = '/admin/contacts/activity';

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
		$id = $this->id_param( $request );

		if ( '' === $id ) {
			return $this->invalid( __( 'Contact not found.', 'joinotify' ), 'not_found' );
		}

		$page = max( 1, (int) $request->get_param( 'page' ) );
		$per_page = (int) $request->get_param( 'per_page' ) ?: 25;
		$envelope = Cloud_Contacts::contact_activity( $id, $page, $per_page );

		if ( ! $envelope['ok'] ) {
			return $this->envelope_error( $envelope );
		}

		return $this->success_response( array(
			'items' => is_array( $envelope['data'] ) ? $envelope['data'] : array(),
			'pagination' => Cloud_Contacts::pagination( $envelope, $page, $per_page ),
		) );
	}
}
