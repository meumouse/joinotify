<?php

namespace MeuMouse\Joinotify\Admin\Workflows;

defined('ABSPATH') || exit;

/**
 * Bootstrap Joinotify workflows-list REST endpoints.
 *
 * @since 2.0.0
 */
class Rest_Controller {

	/**
	 * Route classes used by the workflows list screen.
	 *
	 * @var string[]
	 */
	private $route_classes = array(
		'\MeuMouse\Joinotify\Rest\Workflows_List',
		'\MeuMouse\Joinotify\Rest\Workflows_Status',
		'\MeuMouse\Joinotify\Rest\Workflows_Bulk',
	);


	/**
	 * Register the route classes.
	 */
	public function __construct() {
		foreach ( $this->route_classes as $class ) {
			if ( class_exists( $class ) ) {
				new $class();
			}
		}
	}
}
