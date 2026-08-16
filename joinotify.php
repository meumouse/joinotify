<?php

/**
 * Plugin Name: 			Joinotify
 * Description: 			Increase customer satisfaction by automating WhatsApp messaging with Joinotify.
 * Plugin URI: 				https://meumouse.com/plugins/joinotify/
 * Author: 					MeuMouse.com
 * Author URI: 				https://meumouse.com/
 * Version: 				2.4.0
 * Requires at least: 		6.0
 * Requires PHP: 			7.4
 * Tested up to: 			6.8
 * Text Domain: 			joinotify
 * Domain Path: 			/languages
 * License: 				GPLv2 or later
 * License URI: 			https://www.gnu.org/licenses/gpl-2.0.html
 *
 * @copyright 				2026 MeuMouse.com
 * @license 				GPL-2.0-or-later - See LICENSE for details
 */

use MeuMouse\Joinotify\Core\Init;
use MeuMouse\Joinotify\Core\Onboarding;

defined('ABSPATH') || exit;

// Load Composer autoloader if available.
$autoload = plugin_dir_path( __FILE__ ) . 'admin/vendor/autoload.php';

if ( file_exists( $autoload ) ) {
	require_once $autoload;
}

$plugin_version = '2.4.0';

// Flag a fresh activation so the first admin request opens the setup wizard.
// The wizard is where the site owner picks a country, connects the Joinotify
// account and decides about usage data, so nothing reaches an external server
// before they have been through it.
register_activation_hook( __FILE__, function() {
	if ( class_exists( Onboarding::class ) ) {
		Onboarding::on_activation();
	}
} );

// Initialize the plugin
new Init( __FILE__, $plugin_version );
