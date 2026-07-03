<?php

namespace MeuMouse\Joinotify\Admin\History;

use MeuMouse\Joinotify\Rest\Abstract_Rest_Controller;

defined('ABSPATH') || exit;

/**
 * Bootstrap Joinotify message-history REST endpoints.
 *
 * @since 2.0.0
 */
class Rest_Controller extends Abstract_Rest_Controller {

	/**
	 * Route classes used by the history screen.
	 *
	 * @var string[]
	 */
	protected $route_classes = array(
		'\MeuMouse\Joinotify\Rest\History_Bootstrap',
		'\MeuMouse\Joinotify\Rest\History_List',
		'\MeuMouse\Joinotify\Rest\History_Delete',
	);
}
