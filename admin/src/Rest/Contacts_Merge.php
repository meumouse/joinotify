<?php

namespace MeuMouse\Joinotify\Rest;

use MeuMouse\Joinotify\Api\Cloud_Contacts;
use WP_REST_Request;

defined('ABSPATH') || exit;

/**
 * Merge a duplicate into a contact.
 *
 * Body: `{ id, duplicate_id }`. The contact of `id` stays; the duplicate is erased once its
 * conversations, tags, history and campaigns moved.
 *
 * @since 2.5.0
 */
class Contacts_Merge extends Abstract_Cloud_Route {

	/**
	 * Route path.
	 *
	 * @var string
	 */
	protected $route = '/admin/contacts/merge';

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
		$duplicate = $this->id_param( $request, 'duplicate_id' );

		if ( '' === $id || '' === $duplicate || $id === $duplicate ) {
			return $this->invalid( __( 'Pick another contact to merge into this one.', 'joinotify' ) );
		}

		$envelope = Cloud_Contacts::merge_contacts( $id, $duplicate );

		if ( ! $envelope['ok'] ) {
			return $this->envelope_error( $envelope );
		}

		return $this->success_response( array(
			'message' => __( 'Contacts merged.', 'joinotify' ),
			'contact' => $envelope['data'],
		) );
	}
}
