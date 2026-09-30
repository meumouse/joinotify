<?php

namespace MeuMouse\Joinotify\Rest;

use MeuMouse\Joinotify\Api\Cloud_Contacts;
use WP_REST_Request;

defined('ABSPATH') || exit;

/**
 * Erase a saved audience.
 *
 * @since 2.5.0
 */
class Audiences_Delete extends Abstract_Cloud_Route {

	/**
	 * Route path.
	 *
	 * @var string
	 */
	protected $route = '/admin/audiences/delete';

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
			return $this->invalid( __( 'Audience not found.', 'joinotify' ), 'not_found' );
		}

		$envelope = Cloud_Contacts::delete_audience( $id );

		if ( ! $envelope['ok'] ) {
			return $this->envelope_error( $envelope );
		}

		return $this->success_response( array(
			'message' => __( 'Audience erased.', 'joinotify' ),
		) );
	}
}
