<?php

namespace MeuMouse\Joinotify\Admin\Workflows;

use MeuMouse\Joinotify\Rest\Abstract_Rest_Controller;

defined('ABSPATH') || exit;

/**
 * Bootstrap Joinotify workflows-list REST endpoints.
 *
 * @since 2.0.0
 */
class Rest_Controller extends Abstract_Rest_Controller {

	/**
	 * Route classes used by the workflows list screen.
	 *
	 * @var string[]
	 */
	protected $route_classes = array(
		'\MeuMouse\Joinotify\Rest\Workflows_Bootstrap',
		'\MeuMouse\Joinotify\Rest\Workflows_List',
		'\MeuMouse\Joinotify\Rest\Workflows_Status',
		'\MeuMouse\Joinotify\Rest\Workflows_Bulk',
	);
}
