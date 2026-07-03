<?php

namespace MeuMouse\Joinotify\Admin\Queue;

use MeuMouse\Joinotify\Rest\Abstract_Rest_Controller;

defined('ABSPATH') || exit;

/**
 * Bootstrap Joinotify processing-queue REST endpoints.
 *
 * @since 2.0.0
 */
class Rest_Controller extends Abstract_Rest_Controller {

	/**
	 * Route classes used by the processing-queue screen.
	 *
	 * @var string[]
	 */
	protected $route_classes = array(
		'\MeuMouse\Joinotify\Rest\Queue_Bootstrap',
		'\MeuMouse\Joinotify\Rest\Queue_List',
		'\MeuMouse\Joinotify\Rest\Queue_Run',
		'\MeuMouse\Joinotify\Rest\Queue_Cancel',
	);
}
