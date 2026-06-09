<?php

namespace MeuMouse\Joinotify\Rest;

use MeuMouse\Joinotify\Admin\Workflows\Registry;
use WP_REST_Request;

defined('ABSPATH') || exit;

/**
 * Update the status of a single workflow from the list screen.
 *
 * Accepts the publish/draft toggle plus the single-row lifecycle actions
 * (trash, restore, delete_permanently).
 *
 * @since 2.0.0
 */
class Workflows_Status extends Abstract_Route {

	/**
	 * Route path.
	 *
	 * @var string
	 */
	protected $route = '/admin/workflows/status';

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
		$post_id = absint( $payload['id'] ?? 0 );
		$status = isset( $payload['status'] ) ? sanitize_key( (string) $payload['status'] ) : '';

		if ( ! $post_id || get_post_type( $post_id ) !== Registry::POST_TYPE ) {
			return $this->error_response( esc_html__( 'Workflow not found or invalid post type.', 'joinotify' ) );
		}

		if ( ! in_array( $status, Registry::ALLOWED_ACTIONS, true ) ) {
			return $this->error_response( esc_html__( 'Invalid workflow status.', 'joinotify' ) );
		}

		$result = Registry::apply_action( $status, array( $post_id ) );

		if ( empty( $result['processed'] ) ) {
			$message = isset( $result['error'] ) ? $result['error'] : esc_html__( 'Could not update the workflow.', 'joinotify' );

			return $this->error_response( $message );
		}

		return $this->success_response( array(
			'message' => __( 'Workflow status updated successfully.', 'joinotify' ),
			'workflow_status' => get_post_status( $post_id ) ?: 'deleted',
			'counts' => Registry::get_counts(),
		) );
	}
}
