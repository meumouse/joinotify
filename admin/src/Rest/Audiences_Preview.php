<?php

namespace MeuMouse\Joinotify\Rest;

use MeuMouse\Joinotify\Api\Cloud_Contacts;
use WP_REST_Request;

defined('ABSPATH') || exit;

/**
 * Count who an audience filter selects, by consent, with up to ten sample contacts. Saves
 * nothing.
 *
 * Body: `{ filter: { op, rules } }`.
 *
 * @since 2.5.0
 */
class Audiences_Preview extends Abstract_Cloud_Route {

	/**
	 * Route path.
	 *
	 * @var string
	 */
	protected $route = '/admin/audiences/preview';

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
		$filter = Cloud_Contacts::sanitize_filter( $body['filter'] ?? null );

		if ( null === $filter ) {
			return $this->invalid( __( 'The filter is not valid: use up to 3 levels of groups and 30 conditions.', 'joinotify' ) );
		}

		$envelope = Cloud_Contacts::preview_audience( $filter );

		return $this->respond( $envelope, $envelope['ok'] ? array( 'preview' => $envelope['data'] ) : null );
	}
}
