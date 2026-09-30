<?php

namespace MeuMouse\Joinotify\Rest;

use MeuMouse\Joinotify\Api\Cloud_Contacts;
use WP_REST_Request;

defined('ABSPATH') || exit;

/**
 * Create a custom field, or change one (label, options, position, archived).
 *
 * Body: `{ id?, field: {...} }`. An edit also sends the field's `type`, so the options are only
 * kept for the choice types.
 *
 * @since 2.5.0
 */
class Contacts_Field_Save extends Abstract_Cloud_Route {

	/**
	 * Route path.
	 *
	 * @var string
	 */
	protected $route = '/admin/contacts/fields/save';

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
		$input = isset( $body['field'] ) && is_array( $body['field'] ) ? $body['field'] : array();
		$id = isset( $body['id'] ) && Cloud_Contacts::is_id( $body['id'] ) ? $body['id'] : '';

		if ( '' !== $id ) {
			$envelope = Cloud_Contacts::update_field( $id, Cloud_Contacts::field_payload( $input, 'update' ) );
			$message = __( 'Custom field updated.', 'joinotify' );
		} else {
			$payload = Cloud_Contacts::field_payload( $input, 'create' );

			if ( empty( $payload['key'] ) ) {
				return $this->invalid( __( 'The key starts with a lowercase letter and holds only lowercase letters, digits and underscores, up to 40 characters.', 'joinotify' ) );
			}

			if ( empty( $payload['label'] ) || empty( $payload['type'] ) ) {
				return $this->invalid( __( 'Give the field a label and a type.', 'joinotify' ) );
			}

			if ( in_array( $payload['type'], array( 'select', 'multi_select' ), true ) && empty( $payload['options'] ) ) {
				return $this->invalid( __( 'A choice field needs at least one option.', 'joinotify' ) );
			}

			$envelope = Cloud_Contacts::create_field( $payload );
			$message = __( 'Custom field created.', 'joinotify' );
		}

		if ( ! $envelope['ok'] ) {
			return $this->envelope_error( $envelope );
		}

		return $this->success_response( array(
			'message' => $message,
			'field' => $envelope['data'],
		) );
	}
}
