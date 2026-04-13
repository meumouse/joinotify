<?php

namespace MeuMouse\Joinotify\Rest;

use MeuMouse\Joinotify\Admin\Builder\Registry;
use WP_REST_Request;

defined('ABSPATH') || exit;

/**
 * Create a new workflow from scratch or from a template.
 *
 * @since 1.4.7
 */
class Builder_Create extends Abstract_Route {

	/**
	 * Route path.
	 *
	 * @var string
	 */
	protected $route = '/admin/builder/create';

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
		$mode = isset( $payload['mode'] ) ? sanitize_key( (string) $payload['mode'] ) : 'scratch';
		$title = isset( $payload['title'] ) ? sanitize_text_field( $payload['title'] ) : '';

		if ( 'template' === $mode ) {
			$template_file = isset( $payload['template_file'] ) ? sanitize_text_field( $payload['template_file'] ) : '';
			return rest_ensure_response( Registry::create_workflow_from_template( $template_file, $title ) );
		}

		return rest_ensure_response( Registry::create_blank_workflow( $title ) );
	}
}
