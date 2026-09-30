<?php

namespace MeuMouse\Joinotify\Rest;

use MeuMouse\Joinotify\Api\Cloud_Contacts;
use WP_REST_Request;

defined('ABSPATH') || exit;

/**
 * Export contacts: the base (or what the filters select) as CSV, or, with an `id`, everything the
 * account keeps about one contact as JSON (the LGPD access request).
 *
 * The file travels inside the JSON answer and the screen saves it, so it goes through the same
 * nonce-checked route as everything else.
 *
 * @since 2.5.0
 */
class Contacts_Export extends Abstract_Cloud_Route {

	/**
	 * Route path.
	 *
	 * @var string
	 */
	protected $route = '/admin/contacts/export';

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
		$date = gmdate( 'Y-m-d' );

		if ( '' !== $id ) {
			$envelope = Cloud_Contacts::export_contact( $id );

			if ( ! $envelope['ok'] ) {
				return $this->envelope_error( $envelope );
			}

			return $this->success_response( array(
				'filename' => "joinotify-contact-{$id}-{$date}.json",
				'format' => 'json',
				'content' => $envelope['data'],
			) );
		}

		$envelope = Cloud_Contacts::export_contacts( array(
			'search' => (string) $request->get_param( 'search' ),
			'tag_id' => (string) $request->get_param( 'tag_id' ),
			'opt_in_status' => (string) $request->get_param( 'opt_in_status' ),
			'source' => (string) $request->get_param( 'source' ),
			'audience_id' => (string) $request->get_param( 'audience_id' ),
		) );

		if ( ! $envelope['ok'] ) {
			return $this->envelope_error( $envelope );
		}

		return $this->success_response( array(
			'filename' => "joinotify-contacts-{$date}.csv",
			'format' => 'csv',
			'content' => is_string( $envelope['data'] ) ? $envelope['data'] : '',
		) );
	}
}
