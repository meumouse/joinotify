<?php

namespace MeuMouse\Joinotify\Rest;

use MeuMouse\Joinotify\Core\Message_History;
use MeuMouse\Joinotify\Admin\History\Registry;
use WP_REST_Request;

defined('ABSPATH') || exit;

/**
 * Delete message history rows (by ID or all).
 *
 * @since 2.0.0
 */
class History_Delete extends Abstract_Route {

	/**
	 * Route path.
	 *
	 * @var string
	 */
	protected $route = '/admin/history/delete';

	/**
	 * HTTP methods.
	 *
	 * @var string
	 */
	protected $methods = 'POST';


	/**
	 * Handle request.
	 *
	 * @param WP_REST_Request $request Request instance.
	 * @return \WP_REST_Response
	 */
	public function handle( WP_REST_Request $request ) {
		$params = $request->get_json_params();
		$params = is_array( $params ) ? $params : array();

		$clear_all = ! empty( $params['all'] );
		$ids = isset( $params['ids'] ) ? (array) $params['ids'] : array();

		if ( $clear_all ) {
			$deleted = Message_History::clear_all();
		} elseif ( ! empty( $ids ) ) {
			$deleted = Message_History::delete_items( $ids );
		} else {
			return $this->error_response( esc_html__( 'No records selected.', 'joinotify' ) );
		}

		$list = Registry::get_list_state( array() );

		return $this->success_response( array(
			'deleted' => (int) $deleted,
			'items' => $list['items'],
			'counts' => $list['counts'],
			'pagination' => $list['pagination'],
		) );
	}
}
