<?php

namespace MeuMouse\Joinotify\Rest;

use MeuMouse\Joinotify\Api\Cloud_Contacts;
use WP_REST_Request;

defined('ABSPATH') || exit;

/**
 * The custom fields and tags every tab of the "Audiences & Contacts" screen reads.
 *
 * Both are cached for a few minutes; `refresh=1` asks the platform again and `archived=1`
 * includes the archived fields.
 *
 * @since 2.5.0
 */
class Contacts_Definitions extends Abstract_Cloud_Route {

	/**
	 * Route path.
	 *
	 * @var string
	 */
	protected $route = '/admin/contacts/definitions';

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
		$force = (bool) $request->get_param( 'refresh' );
		$fields = Cloud_Contacts::list_fields( (bool) $request->get_param( 'archived' ), $force );

		if ( ! $fields['ok'] ) {
			return $this->envelope_error( $fields );
		}

		$tags = Cloud_Contacts::list_tags( $force );

		if ( ! $tags['ok'] ) {
			return $this->envelope_error( $tags );
		}

		return $this->success_response( array(
			'fields' => is_array( $fields['data'] ) ? $fields['data'] : array(),
			'tags' => is_array( $tags['data'] ) ? $tags['data'] : array(),
		) );
	}
}
