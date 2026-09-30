<?php

namespace MeuMouse\Joinotify\Rest;

use MeuMouse\Joinotify\Api\Cloud_Contacts;
use WP_REST_Request;

defined('ABSPATH') || exit;

/**
 * Create a contact by hand, or change one.
 *
 * Body: `{ id?, contact: {...}, upsert? }`. Without an id the contact is created — `upsert`
 * updates the one that already has the phone instead of refusing it. With an id, only the keys
 * sent change and an empty value clears it.
 *
 * @since 2.5.0
 */
class Contacts_Save extends Abstract_Cloud_Route {

	/**
	 * Route path.
	 *
	 * @var string
	 */
	protected $route = '/admin/contacts/save';

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
		$body = $this->body( $request );
		$input = isset( $body['contact'] ) && is_array( $body['contact'] ) ? $body['contact'] : array();
		$id = isset( $body['id'] ) && Cloud_Contacts::is_id( $body['id'] ) ? $body['id'] : '';

		if ( '' !== $id ) {
			$envelope = Cloud_Contacts::update_contact( $id, Cloud_Contacts::contact_payload( $input, 'update' ) );
			$message = __( 'Contact updated.', 'joinotify' );
		} else {
			$payload = Cloud_Contacts::contact_payload( $input, 'create' );

			if ( empty( $payload['phone'] ) ) {
				return $this->invalid( __( 'Type the phone number of the contact.', 'joinotify' ) );
			}

			$envelope = Cloud_Contacts::create_contact( $payload, ! empty( $body['upsert'] ) );
			$message = 200 === $envelope['status'] ? __( 'The existing contact was updated.', 'joinotify' ) : __( 'Contact added.', 'joinotify' );
		}

		if ( ! $envelope['ok'] ) {
			return $this->envelope_error( $envelope );
		}

		// A contact written with other tags changes their counts.
		Cloud_Contacts::flush_cache( array( 'tags' ) );

		return $this->success_response( array(
			'message' => $message,
			'contact' => $envelope['data'],
		) );
	}
}
