<?php

namespace MeuMouse\Joinotify\Rest;

use MeuMouse\Joinotify\Admin\Workflows\Registry;
use WP_REST_Request;

defined('ABSPATH') || exit;

/**
 * Apply a bulk action to multiple workflows from the list screen.
 *
 * @since 2.0.0
 */
class Workflows_Bulk extends Abstract_Route {

	/**
	 * Route path.
	 *
	 * @var string
	 */
	protected $route = '/admin/workflows/bulk';

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
		$payload = $request->get_json_params();
		$action = isset( $payload['action'] ) ? sanitize_key( (string) $payload['action'] ) : '';
		$ids = isset( $payload['ids'] ) && is_array( $payload['ids'] ) ? $payload['ids'] : array();

		if ( empty( $ids ) ) {
			return $this->error_response( esc_html__( 'No workflows selected.', 'joinotify' ) );
		}

		$result = Registry::apply_action( $action, $ids );

		if ( isset( $result['error'] ) ) {
			return $this->error_response( $result['error'] );
		}

		return $this->success_response( array(
			'message' => __( 'Bulk action completed.', 'joinotify' ),
			'processed' => (int) $result['processed'],
			'workflows' => Registry::get_items(),
			'counts' => Registry::get_counts(),
		) );
	}
}
