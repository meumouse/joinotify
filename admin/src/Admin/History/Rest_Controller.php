<?php

namespace MeuMouse\Joinotify\Admin\History;

defined('ABSPATH') || exit;

/**
 * Bootstrap Joinotify message-history REST endpoints.
 *
 * @since 2.0.0
 */
class Rest_Controller {

	/**
	 * Route classes used by the history screen.
	 *
	 * @var string[]
	 */
	private $route_classes = array(
		'\MeuMouse\Joinotify\Rest\History_Bootstrap',
		'\MeuMouse\Joinotify\Rest\History_List',
		'\MeuMouse\Joinotify\Rest\History_Delete',
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
