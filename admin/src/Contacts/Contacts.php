<?php

namespace MeuMouse\Joinotify\Contacts;

use MeuMouse\Joinotify\Api\Cloud_Contacts;

defined('ABSPATH') || exit;

/**
 * Wires the contact base module: the hooks that must run on every request, whatever the screen.
 *
 * @since 2.5.0
 * @package MeuMouse\Joinotify\Contacts
 * @author MeuMouse.com
 */
class Contacts {

	/**
	 * Register the module's hooks.
	 *
	 * @since 2.5.0
	 * @return void
	 */
	public function __construct() {
		// A new key may belong to another account, or have other rights: nothing cached for the
		// old one can be trusted.
		add_action( 'Joinotify/Cloud_Api/Connected', array( __CLASS__, 'flush_cache' ) );
		add_action( 'Joinotify/Cloud_Api/Disconnected', array( __CLASS__, 'flush_cache' ) );

		// What the integrations add to the contacts the Cloud sync sends.
		Sources::register();
	}


	/**
	 * Forget the cached contact definitions and capability.
	 *
	 * @since 2.5.0
	 * @return void
	 */
	public static function flush_cache() {
		Cloud_Contacts::flush_cache();
	}
}
