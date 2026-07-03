<?php

namespace MeuMouse\Joinotify\Rest;

use MeuMouse\Joinotify\Admin\Queue\Registry;
use WP_REST_Request;

defined('ABSPATH') || exit;

/**
 * Cancel a scheduled segment (by id) or every pending segment (all), then refresh.
 *
 * @since 2.0.0
 */
class Queue_Cancel extends Abstract_Route {

	/**
	 * Route path.
	 *
	 * @var string
	 */
	protected $route = '/admin/queue/cancel';

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
		$id = isset( $params['id'] ) ? sanitize_text_field( (string) $params['id'] ) : '';

		if ( $clear_all ) {
			$cancelled = Registry::cancel_all();
			$success = true;
			$message = sprintf(
				/* translators: %d: number of cancelled scheduled items */
				_n( '%d scheduled item was cancelled.', '%d scheduled items were cancelled.', $cancelled, 'joinotify' ),
				$cancelled
			);
		} elseif ( '' !== $id ) {
			list( $success, $message ) = Registry::cancel( $id );
		} else {
			return $this->error_response( esc_html__( 'No scheduled item selected.', 'joinotify' ) );
		}

		$list = Registry::get_list_state( array() );

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
