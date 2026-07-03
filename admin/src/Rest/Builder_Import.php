<?php

namespace MeuMouse\Joinotify\Rest;

use MeuMouse\Joinotify\Admin\Builder\Registry;
use WP_REST_Request;
use WP_REST_Response;
use WP_REST_Server;

defined('ABSPATH') || exit;

/**
 * Import workflow route.
 *
 * @since 1.4.7
 */
class Builder_Import {

	/**
	 * Register route hooks.
	 *
	 * @since 1.4.7
	 * @return void
	 */
	public function register_routes() {
		register_rest_route( 'joinotify/v1', '/admin/builder/import', array(
			array(
				'methods' => WP_REST_Server::CREATABLE,
				'callback' => array( $this, 'import_workflow' ),
				'permission_callback' => array( $this, 'permissions' ),
			),
		) );
	}

	/**
	 * Permission callback.
	 *
	 * @since 1.4.7
	 * @return bool
	 */
	public function permissions() {
		return current_user_can( 'manage_options' );
	}

	/**
	 * Import a workflow from payload.
	 *
	 * @since 1.4.7
	 * @param WP_REST_Request $request Request object.
	 * @return WP_REST_Response
	 */
	public function import_workflow( WP_REST_Request $request ) {
		$response = Registry::import_workflow( $request->get_json_params() );

		return rest_ensure_response( $response );
	}
}
