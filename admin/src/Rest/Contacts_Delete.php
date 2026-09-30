<?php

namespace MeuMouse\Joinotify\Rest;

use MeuMouse\Joinotify\Api\Cloud_Contacts;
use WP_REST_Request;

defined('ABSPATH') || exit;

/**
 * Erase a contact for good (the LGPD erasure request).
 *
 * @since 2.5.0
 */
class Contacts_Delete extends Abstract_Cloud_Route {

	/**
	 * Route path.
	 *
	 * @var string
	 */
	protected $route = '/admin/contacts/delete';

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
			return $this->invalid( __( 'Contact not found.', 'joinotify' ), 'not_found' );
		}

		$envelope = Cloud_Contacts::delete_contact( $id );

		if ( ! $envelope['ok'] ) {
			return $this->envelope_error( $envelope );
		}

		Cloud_Contacts::flush_cache( array( 'tags' ) );

		return $this->success_response( array(
			'message' => __( 'Contact erased.', 'joinotify' ),
		) );
	}
}
