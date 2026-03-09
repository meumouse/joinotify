<?php

/**
 * Plugin Name: 			Joinotify
 * Description: 			Aumente a satisfação do seu cliente automatizando o envio de mensagens via WhatsApp com o Joinotify.
 * Plugin URI: 				https://meumouse.com/plugins/joinotify/
 * Author: 					MeuMouse.com
 * Author URI: 				https://meumouse.com/
 * Version: 				1.4.7
 * Requires PHP: 			7.4
 * Tested up to:      		6.9.1
 * Text Domain: 			joinotify
 * Domain Path: 			/languages
 * 
 * @copyright 				2026 MeuMouse.com
 * @license 				Proprietary - See license.md for details
 */

use MeuMouse\Joinotify\Core\Init;

defined('ABSPATH') || exit;

// Load Composer autoloader if available.
$autoload = plugin_dir_path( __FILE__ ) . 'vendor/autoload.php';

if ( file_exists( $autoload ) ) {
	require_once $autoload;
}

$plugin_version = '1.4.7';

// Initialize the plugin
new Init( __FILE__, $plugin_version );