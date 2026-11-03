<?php

namespace MeuMouse\Joinotify\Rest;

use MeuMouse\Joinotify\Admin\History\Registry;
use MeuMouse\Joinotify\Admin\History\Resend;
use WP_REST_Request;

defined('ABSPATH') || exit;

/**
 * Send selected history messages again.
 *
 * A message that failed for good — or whose automatic retry was called off —
 * can be picked on the history screen and sent once more, right away. Each
 * resend records a row of its own and the original stops offering it.
 *
 * @since 2.7.0
 */
class History_Resend extends Abstract_Route {

	/**
	 * Route path.
	 *
	 * @var string
	 */
	protected $route = '/admin/history/resend';

	/**
	 * HTTP methods.
	 *
	 * @var string
	 */
	protected $methods = 'POST';


	/**
	 * Handle request.
	 *
	 * The body may carry the screen's filters and `per_page` next to the IDs;
	 * the refreshed list honours them so the table keeps its view and size.
	 *
	 * @since 2.7.0
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

		$result = Resend::resend( $ids );

		if ( $result['over_limit'] ) {
			return $this->error_response( sprintf(
				/* translators: %d: most messages a single resend sends */
				esc_html__( 'Select at most %d messages to resend at once.', 'joinotify' ),
				Resend::get_limit()
			) );
		}

		$list = Registry::get_list_state( $params );
		$list_data = array(
			'items' => $list['items'],
			'counts' => $list['counts'],
			'pagination' => $list['pagination'],
		);

		if ( $result['resent'] < 1 && $result['failed'] < 1 ) {
			return $this->error_response( esc_html__( 'None of the selected records can be resent.', 'joinotify' ), $list_data );
		}

		$messages = array();

		if ( $result['resent'] > 0 ) {
			$messages[] = sprintf(
				/* translators: %d: number of messages sent again */
				_n( '%d message was sent again.', '%d messages were sent again.', $result['resent'], 'joinotify' ),
				$result['resent']
			);
		}

		if ( $result['failed'] > 0 ) {
			$messages[] = sprintf(
				/* translators: %d: number of messages whose resend failed */
				_n( '%d message failed again; its new record says why.', '%d messages failed again; their new records say why.', $result['failed'], 'joinotify' ),
				$result['failed']
			);
		}

		if ( $result['skipped'] > 0 ) {
			$messages[] = sprintf(
				/* translators: %d: number of selected records that cannot be resent */
				_n( '%d selected record cannot be resent.', '%d selected records cannot be resent.', $result['skipped'], 'joinotify' ),
				$result['skipped']
			);
		}

		$message = implode( ' ', $messages );

		// Nothing went out: the screen shows it as an error, with the refreshed
		// list so the new failed rows are already there.
		if ( $result['resent'] < 1 ) {
			return $this->error_response( $message, $list_data );
		}

		return $this->success_response( array_merge( $list_data, array(
			'message' => $message,
			'resent' => $result['resent'],
			'failed' => $result['failed'],
			'skipped' => $result['skipped'],
		) ) );
	}
}
