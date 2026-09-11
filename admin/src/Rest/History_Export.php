<?php

namespace MeuMouse\Joinotify\Rest;

use MeuMouse\Joinotify\Admin\History\Registry;
use WP_REST_Request;

defined('ABSPATH') || exit;

/**
 * Export message history records (by ID, or all matching the filters) as a JSON file.
 *
 * @since 2.4.2
 */
class History_Export extends Abstract_Route {

	/**
	 * Route path.
	 *
	 * @var string
	 */
	protected $route = '/admin/history/export';

	/**
	 * HTTP methods.
	 *
	 * @var string
	 */
	protected $methods = 'POST';


	/**
	 * Handle request.
	 *
	 * The body carries either `ids`, or `all` together with the screen's
	 * filters. Answers with the suggested `filename` and the `payload` to write
	 * into it; the screen turns them into the download.
	 *
	 * @since 2.4.2
	 * @param WP_REST_Request $request Request instance.
	 * @return \WP_REST_Response
	 */
	public function handle( WP_REST_Request $request ) {
		$params = $request->get_json_params();
		$params = is_array( $params ) ? $params : array();

		if ( empty( $params['all'] ) && empty( $params['ids'] ) ) {
			return $this->error_response( esc_html__( 'No records selected.', 'joinotify' ) );
		}

		$export = Registry::export_items( $params );

		if ( null === $export ) {
			return $this->error_response( esc_html__( 'Nothing to export.', 'joinotify' ) );
		}

		return $this->success_response( $export );
	}
}
