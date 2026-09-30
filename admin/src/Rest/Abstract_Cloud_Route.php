<?php

namespace MeuMouse\Joinotify\Rest;

use MeuMouse\Joinotify\Api\Cloud_Contacts;
use WP_REST_Request;

defined('ABSPATH') || exit;

/**
 * Base for the routes of the "Audiences & Contacts" screen, which relay to the Joinotify platform.
 *
 * They keep the plugin's REST convention — HTTP 200 with `status: success|error` — and add the
 * platform's error under `error` (`type`, `issues`, `retry_after`, `action_url`), so the screen can
 * point at the field that failed or at the panel page that unblocks the account.
 *
 * @since 2.5.0
 * @package MeuMouse\Joinotify\Rest
 * @author MeuMouse.com
 */
abstract class Abstract_Cloud_Route extends Abstract_Route {

	/**
	 * Answer with the envelope of a platform call.
	 *
	 * @since 2.5.0
	 * @param array $envelope Envelope returned by Cloud_Contacts.
	 * @param array $data     Body of a success; defaults to `data` (and `meta` of a list).
	 * @param array $extra    Keys merged into an error body.
	 * @return \WP_REST_Response
	 */
	protected function respond( array $envelope, $data = null, array $extra = array() ) {
		if ( $envelope['ok'] ) {
			if ( null === $data ) {
				$data = array( 'data' => $envelope['data'] );

				if ( ! empty( $envelope['meta'] ) ) {
					$data['meta'] = $envelope['meta'];
				}
			}

			return $this->success_response( $data );
		}

		return $this->envelope_error( $envelope, $extra );
	}


	/**
	 * Answer with the error of a failed platform call.
	 *
	 * A refusal that says the key lost its rights also drops the cached capability, so the
	 * screen's next load asks the platform again instead of trusting a stale "full".
	 *
	 * @since 2.5.0
	 * @param array $envelope Failed envelope.
	 * @param array $extra    Keys merged into the body.
	 * @return \WP_REST_Response
	 */
	protected function envelope_error( array $envelope, array $extra = array() ) {
		if ( in_array( (int) $envelope['status'], array( 401, 402, 403 ), true ) ) {
			Cloud_Contacts::flush_cache( array( 'mode' ) );
		}

		return $this->error_response( $envelope['error']['message'], array_merge( array(
			'error' => $envelope['error'],
		), $extra ) );
	}


	/**
	 * Refuse a request before reaching the platform.
	 *
	 * @since 2.5.0
	 * @param string $message Message for the screen.
	 * @param string $type    Error code.
	 * @return \WP_REST_Response
	 */
	protected function invalid( $message, $type = 'invalid_request' ) {
		return $this->error_response( $message, array(
			'error' => array(
				'type' => $type,
				'message' => $message,
				'issues' => array(),
				'retry_after' => 0,
			),
		) );
	}


	/**
	 * Read a platform id from the query or the JSON body.
	 *
	 * @since 2.5.0
	 * @param WP_REST_Request $request Request.
	 * @param string          $key     Parameter name.
	 * @return string The id, or '' when missing or malformed.
	 */
	protected function id_param( WP_REST_Request $request, $key = 'id' ) {
		$id = $request->get_param( $key );

		return Cloud_Contacts::is_id( $id ) ? $id : '';
	}


	/**
	 * The JSON body as an array.
	 *
	 * @since 2.5.0
	 * @param WP_REST_Request $request Request.
	 * @return array
	 */
	protected function body( WP_REST_Request $request ) {
		$body = $request->get_json_params();

		return is_array( $body ) ? $body : array();
	}
}
