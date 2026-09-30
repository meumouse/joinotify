<?php

namespace MeuMouse\Joinotify\Admin\Contacts;

use MeuMouse\Joinotify\Rest\Abstract_Rest_Controller;

defined('ABSPATH') || exit;

/**
 * Bootstrap the REST endpoints of the "Audiences & Contacts" screen.
 *
 * @since 2.5.0
 */
class Rest_Controller extends Abstract_Rest_Controller {

	/**
	 * Route classes used by the "Audiences & Contacts" screen.
	 *
	 * @var string[]
	 */
	protected $route_classes = array(
		'\MeuMouse\Joinotify\Rest\Contacts_Bootstrap',
	);
}
