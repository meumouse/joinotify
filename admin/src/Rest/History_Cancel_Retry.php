<?php

namespace MeuMouse\Joinotify\Rest;

use MeuMouse\Joinotify\Admin\History\Registry;
use WP_REST_Request;

defined('ABSPATH') || exit;

/**
 * Cancel the pending resend of selected history rows.
 *
 * A message the API refused for a reason worth repeating is parked in the retry
 * queue and comes back on its own. This is how that is called off: pick the rows
 * on the history screen and the queue items behind them are discarded, leaving
 * the records marked as cancelled.
 *
 * @since 2.4.0
 */
class History_Cancel_Retry extends Abstract_Route {

	/**
	 * Route path.
	 *
	 * @var string
	 */
	protected $route = '/admin/history/cancel-retry';

	/**
	 * HTTP methods.
	 *
	 * @var string
	 */
	protected $methods = 'POST';


	/**
	 * Handle request.
	 *
	 * @since 2.4.0
	 * @param WP_REST_Request $request Request instance.
	 * @return \WP_REST_Response
	 */
	public function handle( WP_REST_Request $request ) {
		$params = $request->get_json_params();
		$params = is_array( $params ) ? $params : array();

		$ids = isset( $params['ids'] ) ? (array) $params['ids'] : array();

		if ( empty( $ids ) ) {
			return $this->error_response( esc_html__( 'No records selected.', 'joinotify' ) );
		}

		$result = Registry::cancel_retry( $ids );
		$list = Registry::get_list_state( array() );

		if ( $result['cancelled'] < 1 ) {
			return $this->error_response(
				esc_html__( 'None of the selected records still had a resend pending.', 'joinotify' ),
				array(
					'items' => $list['items'],
					'counts' => $list['counts'],
					'pagination' => $list['pagination'],
				)
			);
		}

		$message = sprintf(
			/* translators: %d: number of messages whose resend was cancelled */
			_n( 'The resend of %d message was cancelled.', 'The resend of %d messages was cancelled.', $result['cancelled'], 'joinotify' ),
			$result['cancelled']
		);

		if ( $result['skipped'] > 0 ) {
			$message .= ' ' . sprintf(
				/* translators: %d: number of selected records that had no resend pending */
				_n( '%d selected record had no resend pending.', '%d selected records had no resend pending.', $result['skipped'], 'joinotify' ),
				$result['skipped']
			);
		}

		return $this->success_response( array(
			'message' => $message,
			'cancelled' => $result['cancelled'],
			'skipped' => $result['skipped'],
			'items' => $list['items'],
			'counts' => $list['counts'],
			'pagination' => $list['pagination'],
		) );
	}
}
