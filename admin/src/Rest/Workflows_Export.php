<?php

namespace MeuMouse\Joinotify\Rest;

use MeuMouse\Joinotify\Admin\Workflows\Registry;
use WP_REST_Request;

defined('ABSPATH') || exit;

/**
 * Export one or more workflows from the list screen as a JSON file.
 *
 * @since 2.4.2
 */
class Workflows_Export extends Abstract_Route {

	/**
	 * Route path.
	 *
	 * @var string
	 */
	protected $route = '/admin/workflows/export';

	/**
	 * HTTP methods.
	 *
	 * @var string
	 */
	protected $methods = 'POST';


	/**
	 * Handle request.
	 *
	 * Answers with the suggested `filename` and the `payload` to write into it;
	 * the screen turns them into the download.
	 *
	 * @since 2.4.2
	 * @param WP_REST_Request $request Request instance.
	 * @return \WP_REST_Response
	 */
	public function handle( WP_REST_Request $request ) {
		$params = $request->get_json_params();
		$ids = isset( $params['ids'] ) && is_array( $params['ids'] ) ? $params['ids'] : array();

		if ( empty( $ids ) ) {
			return $this->error_response( esc_html__( 'No workflows selected.', 'joinotify' ) );
		}

		$export = Registry::export_items( $ids );

		if ( null === $export ) {
			return $this->error_response( esc_html__( 'Nothing to export.', 'joinotify' ) );
		}

		return $this->success_response( $export );
	}
}
