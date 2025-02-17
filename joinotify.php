<?php

/**
 * Plugin Name: 			Joinotify
 * Description: 			Aumente a satisfação do seu cliente automatizando o envio de mensagens via WhatsApp com o Joinotify.
 * Plugin URI: 			https://meumouse.com/plugins/joinotify/
 * Author: 					MeuMouse.com
 * Author URI: 			https://meumouse.com/
 * Version: 				1.1.0
 * Requires PHP: 			7.4
 * Tested up to:      	6.7.2
 * Text Domain: 			joinotify
 * Domain Path: 			/languages
 * License: 				GPL2
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
		public static $version = '1.1.0';

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
		 * @return void
		 */
		public function init() {
			// Display notice if PHP version is bottom 7.4
			if ( version_compare( phpversion(), '7.4', '<' ) ) {
				add_action( 'admin_notices', array( $this, 'php_version_notice' ) );
				return;
			}

			$this->setup_constants();
	
			load_plugin_textdomain( 'joinotify', false, dirname( JOINOTIFY_BASENAME ) . '/languages/' );
			add_filter( 'plugin_action_links_' . JOINOTIFY_BASENAME, array( $this, 'add_action_plugin_links' ), 10, 4 );
			add_filter( 'plugin_row_meta', array( $this, 'add_row_meta_links' ), 10, 4 );

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
		 * @version 1.1.0
		 * @return void
		 */
		public function setup_constants() {
			$this->define( 'JOINOTIFY_BASENAME', plugin_basename( __FILE__ ) );
			$this->define( 'JOINOTIFY_DIR', plugin_dir_path( __FILE__ ) );
			$this->define( 'JOINOTIFY_INC', JOINOTIFY_DIR . 'inc/' );
			$this->define( 'JOINOTIFY_URL', plugin_dir_url( __FILE__ ) );
			$this->define( 'JOINOTIFY_ASSETS', JOINOTIFY_URL . 'assets/' );
			$this->define( 'JOINOTIFY_FILE', __FILE__ );
			$this->define( 'JOINOTIFY_ABSPATH', dirname( JOINOTIFY_FILE ) . '/' );
			$this->define( 'JOINOTIFY_ADMIN_EMAIL', get_option('admin_email') );
			$this->define( 'JOINOTIFY_DOCS_URL', 'https://ajuda.meumouse.com/docs/joinotify/overview' );
			$this->define( 'JOINOTIFY_REGISTER_PHONE_URL', 'https://meumouse.com/minha-conta/joinotify-slots/' );
			$this->define( 'JOINOTIFY_API_BASE_URL', 'https://whatsapp-api.meumouse.com' );
			$this->define( 'JOINOTIFY_SLUG', self::$slug );
			$this->define( 'JOINOTIFY_VERSION', self::$version );
			$this->define( 'JOINOTIFY_DEV_MODE', true );
		}


		/**
		 * Define constant if not already set
		 *
		 * @since 1.0.0
		 * @param string $name | Constant name
		 * @param string|bool $value Constant value
		 * @return void
		 */
		private function define( $name, $value ) {
			if ( ! defined( $name ) ) {
				define( $name, $value );
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
		 * Plugin action links
		 * 
		 * @since 1.0.0
		 * @version 1.0.5
		 * @param array $action_links | Default plugin action links
		 * @return array
		 */
		public function add_action_plugin_links( $action_links ) {
        	if ( get_option('joinotify_license_status') !== 'valid' ) {
				$plugins_links = array(
					'<a href="' . admin_url('admin.php?page=joinotify-license') . '">'. __( 'Configurar', 'joinotify' ) .'</a>',
				);
			} else {
				$plugins_links = array(
					'<a href="' . admin_url('admin.php?page=joinotify-settings') . '">'. __( 'Configurar', 'joinotify' ) .'</a>',
				);
			}

			return array_merge( $plugins_links, $action_links );
		}


		/**
		 * Add meta links on plugin
		 * 
		 * @since 1.0.0
		 * @param string $plugin_meta | An array of the plugin’s metadata, including the version, author, author URI, and plugin URI
		 * @param string $plugin_file | Path to the plugin file relative to the plugins directory
		 * @param array $plugin_data | An array of plugin data
		 * @param string $status | Status filter currently applied to the plugin list
		 * @return string
		 */
		public function add_row_meta_links( $plugin_meta, $plugin_file, $plugin_data, $status ) {
			if ( strpos( $plugin_file, JOINOTIFY_BASENAME ) !== false ) {
				$new_links = array(
					'docs' => '<a href="'. esc_attr( JOINOTIFY_DOCS_URL ) .'" target="_blank">'. __( 'Documentação', 'joinotify' ) .'</a>',
				);
				
				$plugin_meta = array_merge( $plugin_meta, $new_links );
			}
		
			return $plugin_meta;
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