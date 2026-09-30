<?php

namespace MeuMouse\Joinotify\Rest;

use MeuMouse\Joinotify\Api\Cloud_Contacts;
use WP_REST_Request;

defined('ABSPATH') || exit;

/**
 * Record a contact's opt-in, with its evidence, or its opt-out.
 *
 * Body: `{ id, action: 'opt_in', evidence }` or `{ id, action: 'opt_out', reason? }`.
 *
 * @since 2.5.0
 */
class Contacts_Consent extends Abstract_Cloud_Route {

	/**
	 * Route path.
	 *
	 * @var string
	 */
	protected $route = '/admin/contacts/consent';

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
		$id = isset( $body['id'] ) && Cloud_Contacts::is_id( $body['id'] ) ? $body['id'] : '';
		$action = isset( $body['action'] ) ? (string) $body['action'] : '';

		if ( '' === $id ) {
			return $this->invalid( __( 'Contact not found.', 'joinotify' ), 'not_found' );
		}

		if ( 'opt_in' === $action ) {
			$evidence = Cloud_Contacts::text( $body['evidence'] ?? '', 500 );

			if ( strlen( $evidence ) < 3 ) {
				return $this->invalid( __( 'Describe how the contact gave consent.', 'joinotify' ) );
			}

			$envelope = Cloud_Contacts::opt_in( $id, $evidence );
			$message = __( 'Consent recorded.', 'joinotify' );
		} elseif ( 'opt_out' === $action ) {
			$envelope = Cloud_Contacts::opt_out( $id, (string) ( $body['reason'] ?? '' ) );
			$message = __( 'Opt-out recorded. The contact will not receive marketing messages.', 'joinotify' );
		} else {
			return $this->invalid( __( 'Unknown consent action.', 'joinotify' ) );
		}

		if ( ! $envelope['ok'] ) {
			return $this->envelope_error( $envelope );
		}

		return $this->success_response( array(
			'message' => $message,
			'contact' => $envelope['data'],
		) );
	}
}
