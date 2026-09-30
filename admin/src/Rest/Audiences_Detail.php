<?php

namespace MeuMouse\Joinotify\Rest;

use MeuMouse\Joinotify\Api\Cloud_Contacts;
use WP_REST_Request;

defined('ABSPATH') || exit;

/**
 * Read one audience, recounted now.
 *
 * @since 2.5.0
 */
class Audiences_Detail extends Abstract_Cloud_Route {

	/**
	 * Route path.
	 *
	 * @var string
	 */
	protected $route = '/admin/audiences/detail';

	/**
	 * HTTP methods.
	 *
	 * @var string
	 */
	protected $methods = 'GET';


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

		$envelope = Cloud_Contacts::get_audience( $id );

		return $this->respond( $envelope, $envelope['ok'] ? array( 'audience' => $envelope['data'] ) : null );
	}
}
