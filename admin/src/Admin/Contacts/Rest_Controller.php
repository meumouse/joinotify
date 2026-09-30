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
		'\MeuMouse\Joinotify\Rest\Contacts_Definitions',
		'\MeuMouse\Joinotify\Rest\Contacts_List',
		'\MeuMouse\Joinotify\Rest\Contacts_Detail',
		'\MeuMouse\Joinotify\Rest\Contacts_Activity',
		'\MeuMouse\Joinotify\Rest\Contacts_Save',
		'\MeuMouse\Joinotify\Rest\Contacts_Delete',
		'\MeuMouse\Joinotify\Rest\Contacts_Consent',
		'\MeuMouse\Joinotify\Rest\Contacts_Export',
		'\MeuMouse\Joinotify\Rest\Contacts_Field_Save',
		'\MeuMouse\Joinotify\Rest\Contacts_Field_Delete',
		'\MeuMouse\Joinotify\Rest\Contacts_Tag_Save',
		'\MeuMouse\Joinotify\Rest\Contacts_Tag_Delete',
		'\MeuMouse\Joinotify\Rest\Contacts_Tag_Apply',
	);
}
