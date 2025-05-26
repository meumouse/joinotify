<?php

/**
 * Plugin Name: 			Joinotify
 * Description: 			Aumente a satisfação do seu cliente automatizando o envio de mensagens via WhatsApp com o Joinotify.
 * Plugin URI: 				https://meumouse.com/plugins/joinotify/
 * Author: 					MeuMouse.com
 * Author URI: 				https://meumouse.com/
 * Version: 				1.3.1
 * Requires PHP: 			7.4
 * Tested up to:      		6.8.1
 * Text Domain: 			joinotify
 * Domain Path: 			/languages
 * 
 * @copyright 				2025 MeuMouse.com
 * @license Proprietary - See license.md for details
 */

namespace MeuMouse\Joinotify;

// Exit if accessed directly.
defined('ABSPATH') || exit;

if ( ! class_exists('Joinotify') ) {
  
	/**
	 * Main class for load plugin
	 *
	 * @since 1.0.0
	 * @package MeuMouse.com
	 */
	class Joinotify {

		/**
		 * The single instance of Joinotify class
		 *
		 * @since 1.0.0
		 * @var object
		 */
		private static $instance = null;

		/**
		 * The token
		 *
		 * @var string
		 * @since 1.0.0
		 */
		public static $slug = 'joinotify';

		/**
		 * The version number
		 *
		 * @var string
		 * @since 1.0.0
		 */
		public static $version = '1.3.1';

		/**
		 * Constructor function
		 *
		 * @since 1.0.0
		 * @version 1.1.0
		 * @return void
		 */
		public function __construct() {
			/**
			 * Fire hook before Joinotify initialize
			 * 
			 * @since 1.1.0
			 */
			do_action('before_joinotify_init');

			add_action( 'plugins_loaded', array( $this, 'init' ), 99 );

			/**
			 * Fire hook after Joinotify initialize
			 * 
			 * @since 1.1.0
			 */
			do_action('joinotify_init');
		}
		

		/**
		 * Check requeriments and load plugin
		 * 
		 * @since 1.0.0
		 * @version 1.3.0
		 * @return void
		 */
		public function init() {
			// Display notice if PHP version is bottom 7.4
			if ( version_compare( phpversion(), '7.4', '<' ) ) {
				add_action( 'admin_notices', array( $this, 'php_version_notice' ) );
				return;
			}

			// define constants
			self::setup_constants();
	
			// load Composer
			require_once JOINOTIFY_DIR . 'vendor/autoload.php';

			// load instancer class
			new \MeuMouse\Joinotify\Core\Init;
		}


		/**
		 * Ensures only one instance of Joinotify class is loaded or can be loaded
		 *
		 * @since 1.0.0
		 * @return Main Joinotify instance
		 */
		public static function run() {
			if ( is_null( self::$instance ) ) {
				self::$instance = new self();
			}
			
			return self::$instance;
		}


		/**
		 * Setup plugin constants
		 *
		 * @since 1.0.0
		 * @version 1.3.0
		 * @return void
		 */
		public static function setup_constants() {
			$base_file = __FILE__;
			$base_dir = plugin_dir_path( $base_file );
			$base_url = plugin_dir_url( $base_file );
		
			$constants = array(
				'JOINOTIFY_BASENAME' => plugin_basename( $base_file ),
				'JOINOTIFY_FILE' => $base_file,
				'JOINOTIFY_DIR' => $base_dir,
				'JOINOTIFY_INC' => $base_dir . 'inc/',
				'JOINOTIFY_URL' => $base_url,
				'JOINOTIFY_ASSETS' => $base_url . 'assets/',
				'JOINOTIFY_ABSPATH' => dirname( $base_file ) . '/',
				'JOINOTIFY_ADMIN_EMAIL' => get_option( 'admin_email' ),
				'JOINOTIFY_DOCS_URL' => 'https://ajuda.meumouse.com/docs/joinotify/overview',
				'JOINOTIFY_REGISTER_PHONE_URL' => 'https://meumouse.com/minha-conta/joinotify-slots/',
				'JOINOTIFY_API_BASE_URL' => 'https://slots-manager.joinotify.com',
				'JOINOTIFY_SLUG' => self::$slug,
				'JOINOTIFY_VERSION' => self::$version,
				'JOINOTIFY_DEV_MODE' => false,
			);
		
			// Iterate and define each constant if not already defined
			foreach ( $constants as $key => $value ) {
				if ( ! defined( $key ) ) {
					define( $key, $value );
				}
			}
		}


		/**
		 * PHP version notice
		 * 
		 * @since 1.0.0
		 * @return void
		 */
		public function php_version_notice() {
			$class = 'notice notice-error is-dismissible';
			$message = __( '<strong>Joinotify</strong> requer a versão do PHP 7.4 ou maior. Contate o suporte da sua hospedagem para realizar a atualização.', 'joinotify' );

			printf( '<div class="%1$s"><p>%2$s</p></div>', esc_attr( $class ), $message );
		}


		/**
		 * Cloning is forbidden
		 *
		 * @since 1.0.0
		 * @return void
		 */
		public function __clone() {
			_doing_it_wrong( __FUNCTION__, esc_html__( 'Trapaceando?', 'joinotify' ), '1.0.0' );
		}


		/**
		 * Unserializing instances of this class is forbidden
		 *
		 * @since 1.0.0
		 * @return void
		 */
		public function __wakeup() {
			_doing_it_wrong( __FUNCTION__, esc_html__( 'Trapaceando?', 'joinotify' ), '1.0.0' );
		}
	}
}

/**
 * Initialise the plugin
 * 
 * @since 1.0.0
 * @return object | Instance Joinotify
 */
Joinotify::run();