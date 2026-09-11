<?php

namespace MeuMouse\Joinotify\Rest;

use MeuMouse\Joinotify\Admin\Queue\Registry;
use WP_REST_Request;

defined('ABSPATH') || exit;

/**
 * Run a scheduled segment immediately (skip the wait), then refresh the list.
 *
 * @since 2.0.0
 */
class Queue_Run extends Abstract_Route {

	/**
	 * Route path.
	 *
	 * @var string
	 */
	protected $route = '/admin/queue/run';

	/**
	 * HTTP methods.
	 *
	 * @var string
	 */
	protected $methods = 'POST';


	/**
	 * Handle request.
	 *
	 * The body may carry the screen's filters and `per_page` next to the ID;
	 * the refreshed list honours them so the table keeps its view and size.
	 *
	 * @since 2.0.0
	 * @version 2.4.2
	 * @param WP_REST_Request $request Request instance.
	 * @return \WP_REST_Response
	 */
	public function handle( WP_REST_Request $request ) {
		$params = $request->get_json_params();
		$params = is_array( $params ) ? $params : array();

		$id = isset( $params['id'] ) ? sanitize_text_field( (string) $params['id'] ) : '';

		if ( '' === $id ) {
			return $this->error_response( esc_html__( 'No scheduled item selected.', 'joinotify' ) );
		}

		list( $success, $message ) = Registry::run_now( $id );

		$list = Registry::get_list_state( $params );

		if ( ! $success ) {
			return $this->error_response( $message, array(
				'items'      => $list['items'],
				'counts'     => $list['counts'],
				'pagination' => $list['pagination'],
			) );
		}

		return $this->success_response( array(
			'message'    => $message,
			'items'      => $list['items'],
			'counts'     => $list['counts'],
			'pagination' => $list['pagination'],
		) );
	}
}
