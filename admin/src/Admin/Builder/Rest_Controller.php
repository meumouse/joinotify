<?php

namespace MeuMouse\Joinotify\Admin\Builder;

use MeuMouse\Joinotify\Rest\Abstract_Rest_Controller;

defined('ABSPATH') || exit;

/**
 * Bootstrap Joinotify builder REST endpoints.
 *
 * @since 1.4.7
 */
class Rest_Controller extends Abstract_Rest_Controller {

	/**
	 * Route classes used by the builder admin screen.
	 *
	 * @var string[]
	 */
	protected $route_classes = array(
		'\MeuMouse\Joinotify\Rest\Builder_Bootstrap',
		'\MeuMouse\Joinotify\Rest\Builder_Actions',
		'\MeuMouse\Joinotify\Rest\Builder_Workflow',
		'\MeuMouse\Joinotify\Rest\Builder_Create',
		'\MeuMouse\Joinotify\Rest\Builder_Import',
		'\MeuMouse\Joinotify\Rest\Builder_Ai_Generate',
		'\MeuMouse\Joinotify\Rest\Builder_Templates',
		'\MeuMouse\Joinotify\Rest\Builder_Export',
		'\MeuMouse\Joinotify\Rest\Builder_Test',
		'\MeuMouse\Joinotify\Rest\Builder_Status',
		'\MeuMouse\Joinotify\Rest\Builder_Groups',
		'\MeuMouse\Joinotify\Rest\Builder_Woo_Products',
	);
}
