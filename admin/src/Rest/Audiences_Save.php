<?php

namespace MeuMouse\Joinotify\Rest;

use MeuMouse\Joinotify\Api\Cloud_Contacts;
use WP_REST_Request;

defined('ABSPATH') || exit;

/**
 * Create an audience, or change its name, description or filter, or archive it.
 *
 * Body: `{ id?, audience: { name, description?, filter, archived? } }`.
 *
 * @since 2.5.0
 */
class Audiences_Save extends Abstract_Cloud_Route {

	/**
	 * Route path.
	 *
	 * @var string
	 */
	protected $route = '/admin/audiences/save';

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
		$input = isset( $body['audience'] ) && is_array( $body['audience'] ) ? $body['audience'] : array();
		$id = isset( $body['id'] ) && Cloud_Contacts::is_id( $body['id'] ) ? $body['id'] : '';
		$payload = Cloud_Contacts::audience_payload( $input, '' !== $id ? 'update' : 'create' );

		if ( isset( $input['filter'] ) && ! isset( $payload['filter'] ) ) {
			return $this->invalid( __( 'The filter is not valid: use up to 3 levels of groups and 30 conditions.', 'joinotify' ) );
		}

		if ( '' === $id && ( empty( $payload['name'] ) || empty( $payload['filter'] ) ) ) {
			return $this->invalid( __( 'Give the audience a name and at least one condition.', 'joinotify' ) );
		}

		$envelope = '' !== $id ? Cloud_Contacts::update_audience( $id, $payload ) : Cloud_Contacts::create_audience( $payload );

		if ( ! $envelope['ok'] ) {
			return $this->envelope_error( $envelope );
		}

		return $this->success_response( array(
			'message' => '' !== $id ? __( 'Audience updated.', 'joinotify' ) : __( 'Audience created.', 'joinotify' ),
			'audience' => $envelope['data'],
		) );
	}
}
