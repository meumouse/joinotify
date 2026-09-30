<?php

namespace MeuMouse\Joinotify\Rest;

use MeuMouse\Joinotify\Admin\Contacts\Registry;
use MeuMouse\Joinotify\Api\Cloud_Contacts;
use WP_REST_Request;

defined('ABSPATH') || exit;

/**
 * Add a phone or BSUID to the suppression list.
 *
 * Body: `{ identity, note? }`.
 *
 * @since 2.5.0
 */
class Suppressions_Save extends Abstract_Cloud_Route {

	/**
	 * Route path.
	 *
	 * @var string
	 */
	protected $route = '/admin/suppressions/save';

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
		$identity = Cloud_Contacts::text( $body['identity'] ?? '', 160 );

		if ( strlen( $identity ) < 3 ) {
			return $this->invalid( __( 'Type the phone number to suppress.', 'joinotify' ) );
		}

		$envelope = Cloud_Contacts::add_suppression( $identity, (string) ( $body['note'] ?? '' ), Registry::default_country() );

		if ( ! $envelope['ok'] ) {
			return $this->envelope_error( $envelope );
		}

		return $this->success_response( array(
			'message' => 200 === $envelope['status']
				? __( 'This number was already on the suppression list.', 'joinotify' )
				: __( 'Number added to the suppression list.', 'joinotify' ),
			'suppression' => $envelope['data'],
		) );
	}
}
