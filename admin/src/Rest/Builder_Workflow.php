<?php

namespace MeuMouse\Joinotify\Rest;

use MeuMouse\Joinotify\Admin\Builder\Registry;
use WP_REST_Request;

defined('ABSPATH') || exit;

/**
 * Load or save a workflow.
 *
 * @since 1.4.7
 */
class Builder_Workflow extends Abstract_Route {

	/**
	 * Route path.
	 *
	 * @var string
	 */
	protected $route = '/admin/builder/workflow';

	/**
	 * HTTP methods.
	 *
	 * @var string
	 */
	protected $methods = array( 'GET', 'POST' );


	/**
	 * Handle request.
	 *
	 * @param WP_REST_Request $request Request instance.
	 * @return \WP_REST_Response
	 */
	public function handle( WP_REST_Request $request ) {
		if ( 'GET' === $request->get_method() ) {
			$post_id = absint( $request->get_param( 'id' ) );
			$workflow_state = Registry::get_workflow_state( $post_id );
			$response = Registry::build_exported_workflow_file( $workflow_state, $post_id );
			$response['post_id'] = $post_id;

			return rest_ensure_response( $response );
		}

		$payload = $request->get_json_params();
		$post_id = absint( $payload['post_id'] ?? 0 );

		return rest_ensure_response( Registry::save_workflow( $post_id, is_array( $payload ) ? $payload : array() ) );
	}
}