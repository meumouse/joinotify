<?php

namespace MeuMouse\Joinotify\Rest;

use MeuMouse\Joinotify\Api\Cloud_Contacts;
use WP_REST_Request;

defined('ABSPATH') || exit;

/**
 * Put a tag on, or take it off, many contacts at once.
 *
 * Body: `{ tag_id, action: 'add'|'remove', contact_ids?: [...], filters?: {...} }`. The ids win;
 * without them the tag goes to every contact the listing filters select.
 *
 * @since 2.5.0
 */
class Contacts_Tag_Apply extends Abstract_Cloud_Route {

	/**
	 * Route path.
	 *
	 * @var string
	 */
	protected $route = '/admin/contacts/tags/apply';

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
		$tag_id = isset( $body['tag_id'] ) && Cloud_Contacts::is_id( $body['tag_id'] ) ? $body['tag_id'] : '';
		$action = isset( $body['action'] ) && 'remove' === $body['action'] ? 'remove' : 'add';
		$ids = isset( $body['contact_ids'] ) && is_array( $body['contact_ids'] ) ? $body['contact_ids'] : array();
		$filters = isset( $body['filters'] ) && is_array( $body['filters'] ) ? $body['filters'] : array();

		if ( '' === $tag_id ) {
			return $this->invalid( __( 'Pick a tag.', 'joinotify' ) );
		}

		$envelope = Cloud_Contacts::apply_tag( $tag_id, $action, $ids, $filters );

		if ( ! $envelope['ok'] ) {
			return $this->envelope_error( $envelope );
		}

		$affected = isset( $envelope['data']['affected'] ) ? (int) $envelope['data']['affected'] : 0;

		return $this->success_response( array(
			'affected' => $affected,
			'message' => 'remove' === $action
				/* translators: %d: number of contacts */
				? sprintf( _n( 'Tag removed from %d contact.', 'Tag removed from %d contacts.', $affected, 'joinotify' ), $affected )
				/* translators: %d: number of contacts */
				: sprintf( _n( 'Tag added to %d contact.', 'Tag added to %d contacts.', $affected, 'joinotify' ), $affected ),
		) );
	}
}
