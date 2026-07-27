<?php

/**
 * Plugin Name: 			Joinotify
 * Description: 			Increase customer satisfaction by automating WhatsApp messaging with Joinotify.
 * Plugin URI: 				https://meumouse.com/plugins/joinotify/
 * Author: 					MeuMouse.com
 * Author URI: 				https://meumouse.com/
 * Version: 				2.1.0
 * Requires PHP: 			7.4
 * Tested up to:      		7.0
 * Text Domain: 			joinotify
 * Domain Path: 			/languages
 * 
 * @copyright 				2026 MeuMouse.com
 * @license 				Proprietary - See license.md for details
 */

use MeuMouse\Joinotify\Core\Init;

defined('ABSPATH') || exit;

// Load Composer autoloader if available.
$autoload = plugin_dir_path( __FILE__ ) . 'admin/vendor/autoload.php';

if ( file_exists( $autoload ) ) {
	require_once $autoload;
}

$plugin_version = '2.1.0';

// Initialize the plugin
new Init( __FILE__, $plugin_version );