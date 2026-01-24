<?php

/**
 * Plugin Name: 			Joinotify
 * Description: 			Aumente a satisfação do seu cliente automatizando o envio de mensagens via WhatsApp com o Joinotify.
 * Plugin URI: 				https://meumouse.com/plugins/joinotify/
 * Author: 					MeuMouse.com
 * Author URI: 				https://meumouse.com/
 * Version: 				1.4.5
 * Requires PHP: 			7.4
 * Tested up to:      		6.9
 * Text Domain: 			joinotify
 * Domain Path: 			/languages
 * 
 * @copyright 				2025 MeuMouse.com
 * @license 				Proprietary - See license.md for details
 */

defined( 'ABSPATH' ) || exit;

// Load Composer autoloader if available.
$autoload = plugin_dir_path( __FILE__ ) . 'vendor/autoload.php';

if ( file_exists( $autoload ) ) {
	require_once $autoload;
}

$plugin_version = '1.4.5';

// Initialize the plugin
new \MeuMouse\Joinotify\Core\Init( __FILE__, $plugin_version );