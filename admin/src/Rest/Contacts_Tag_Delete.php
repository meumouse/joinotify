<?php

namespace MeuMouse\Joinotify\Rest;

use MeuMouse\Joinotify\Api\Cloud_Contacts;
use WP_REST_Request;

defined('ABSPATH') || exit;

/**
 * Erase a contact tag and take it off every contact.
 *
 * @since 2.5.0
 */
class Contacts_Tag_Delete extends Abstract_Cloud_Route {

	/**
	 * Route path.
	 *
	 * @var string
	 */
	protected $route = '/admin/contacts/tags/delete';

	/**
	 * HTTP methods.
	 *
	 * @var string
	 */
	protected $methods = 'POST';


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
			return $this->invalid( __( 'Tag not found.', 'joinotify' ), 'not_found' );
		}

		$envelope = Cloud_Contacts::delete_tag( $id );

		if ( ! $envelope['ok'] ) {
			return $this->envelope_error( $envelope );
		}

		return $this->success_response( array(
			'message' => __( 'Tag erased.', 'joinotify' ),
		) );
	}
}
