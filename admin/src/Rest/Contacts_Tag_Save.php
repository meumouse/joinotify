<?php

namespace MeuMouse\Joinotify\Rest;

use MeuMouse\Joinotify\Api\Cloud_Contacts;
use WP_REST_Request;

defined('ABSPATH') || exit;

/**
 * Create a contact tag, or rename one or change its description or color.
 *
 * Body: `{ id?, tag: { name, description?, color? } }`.
 *
 * @since 2.5.0
 */
class Contacts_Tag_Save extends Abstract_Cloud_Route {

	/**
	 * Route path.
	 *
	 * @var string
	 */
	protected $route = '/admin/contacts/tags/save';

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
		$payload = Cloud_Contacts::tag_payload( isset( $body['tag'] ) && is_array( $body['tag'] ) ? $body['tag'] : array() );
		$id = isset( $body['id'] ) && Cloud_Contacts::is_id( $body['id'] ) ? $body['id'] : '';

		if ( '' === $id && empty( $payload['name'] ) ) {
			return $this->invalid( __( 'Give the tag a name.', 'joinotify' ) );
		}

		$envelope = '' !== $id ? Cloud_Contacts::update_tag( $id, $payload ) : Cloud_Contacts::create_tag( $payload );

		if ( ! $envelope['ok'] ) {
			return $this->envelope_error( $envelope );
		}

		return $this->success_response( array(
			'message' => '' !== $id ? __( 'Tag updated.', 'joinotify' ) : __( 'Tag created.', 'joinotify' ),
			'tag' => $envelope['data'],
		) );
	}
}
