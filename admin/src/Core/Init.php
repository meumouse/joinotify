<?php

namespace MeuMouse\Joinotify\Core;

use Throwable;

defined('ABSPATH') || exit;

/**
 * Initialize plugin classes
 *
 * @since 1.0.0
 * @version 2.0.0
 * @package MeuMouse\Joinotify\Core
 * @author MeuMouse.com
 */
class Init {

	/**
	 * Plugin main file path.
	 *
	 * @since 1.4.5
	 * @var string
	 */
	private $plugin_file;

	/**
	 * Plugin version
	 *
	 * @since 1.4.5
	 * @var string
	 */
	private $plugin_version;

	/**
	 * Plugin directory.
	 * 
	 * @since 1.4.2
	 * @var string
	 */
	public $directory;

	/**
	 * Plugin basename.
	 * 
	 * @since 1.0.0
	 * @version 1.4.7
	 * @var string
	 */
	public $basename;

	/**
	 * Cache for instantiated classes to prevent duplicate instantiation.
	 * 
	 * @since 1.4.3
	 * @var array
	 */
	private $instantiated_classes = array();

	/**
	 * Deferred classes: hook => class list.
	 *
	 * @since 1.4.5
	 * @version 1.4.7
	 * @var array
	 */
	private $deferred_classes = array(
		'woocommerce_loaded' => array(
			'MeuMouse\\Joinotify\\Integrations\\Woo_Subscriptions',
		),
	);


	/**
	 * Construct function.
	 * 
	 * @since 1.0.0
	 * @version 1.4.7
	 * @param string $plugin_file | Plugin main file path.
	 * @param string $plugin_version | Plugin version.
	 * @return void
	 */
	public function __construct( $plugin_file, $plugin_version ) {
		$this->plugin_file = $plugin_file;
		$this->plugin_version = $plugin_version;

		/**
		 * Fire hook before Joinotify initialize.
		 * 
		 * @since 1.1.0
		 */
		do_action('before_joinotify_init');

		// Display notice if PHP version is below 7.4.
		if ( version_compare( phpversion(), '7.4', '<' ) ) {
			add_action( 'admin_notices', array( $this, 'php_version_notice' ) );
			return;
		}

		$this->setup_constants();

		// Now the plugin can safely load internal functions and instance classes.
		require_once JOINOTIFY_SRC . 'Core/Functions.php';

		$this->directory = JOINOTIFY_DIR;
		$this->basename = JOINOTIFY_BASENAME;

		// Register deferred instantiation ASAP.
		$this->register_deferred_classes();

		// Instance only the explicitly allowed classes
		add_action( 'init', array( $this, 'instance_init_classes' ), 10 );
		add_action( 'admin_init', array( $this, 'instance_admin_init_classes' ), 10 );
		add_action( 'wp_loaded', array( $this, 'instance_wp_loaded_classes' ), 10 );

		// REST controllers only register routes during a REST request, so they
		// are deferred to rest_api_init instead of being instantiated on every
		// front-end, admin, AJAX and cron request. Priority 5 runs before the
		// route classes register themselves at the default priority 10.
		add_action( 'rest_api_init', array( $this, 'instance_rest_classes' ), 5 );

		// Add settings link on plugins list.
		add_filter( 'plugin_action_links_' . $this->basename, array( $this, 'add_action_plugin_links' ), 10, 4 );

		// Add docs link on plugins list.
		add_filter( 'plugin_row_meta', array( $this, 'add_row_meta_links' ), 10, 4 );

		// Load plugin text domain.
		add_action( 'init', array( $this, 'load_text_domain' ) );

		// Translate the plugin header metadata shown in the Plugins screen.
		add_filter( 'all_plugins', array( $this, 'translate_plugin_header_data' ) );

		/**
		 * Fire hook after Joinotify initialize.
		 * 
		 * @since 1.1.0
		 * @version 1.4.7
		 */
		do_action('joinotify_init');
	}


	/**
	 * Load text domain after init hook.
	 * 
	 * @since 1.0.0
	 * @version 1.4.7
	 * @return void
	 */
	public function load_text_domain() {
		load_plugin_textdomain( 'joinotify', false, dirname( $this->basename ) . '/languages/' );
	}


	/**
	 * Translate the plugin name and description shown in the Plugins screen.
	 *
	 * WordPress reads these values from the plugin header, but the header itself
	 * cannot call translation functions. This filter injects translated values
	 * at runtime so the strings can still be included in the manual string map.
	 *
	 * @since 1.4.7
	 * @param array<string,array<string,mixed>> $plugins All plugins indexed by basename.
	 * @return array<string,array<string,mixed>> Filtered plugins list.
	 */
	public function translate_plugin_header_data( $plugins ) {
		if ( ! isset( $plugins[ $this->basename ] ) || ! is_array( $plugins[ $this->basename ] ) ) {
			return $plugins;
		}

		$plugins[ $this->basename ]['Name'] = __( 'Joinotify', 'joinotify' );
		$plugins[ $this->basename ]['Description'] = __( 'Increase customer satisfaction by automating WhatsApp messaging with Joinotify.', 'joinotify' );

		return $plugins;
	}


	/**
	 * Setup plugin constants.
	 *
	 * @since 1.4.5
	 * @return void
	 */
	private function setup_constants() {
		$base_file = $this->plugin_file;
		$base_dir = plugin_dir_path( $base_file );
		$base_url = plugin_dir_url( $base_file );

		$constants = array(
			'JOINOTIFY_BASENAME'           	=> plugin_basename( $base_file ),
			'JOINOTIFY_FILE'               	=> $base_file,
			'JOINOTIFY_DIR'                	=> $base_dir,
			'JOINOTIFY_SRC'                	=> $base_dir . 'admin/src/',
			'JOINOTIFY_INC'                	=> $base_dir . 'admin/src/',
			'JOINOTIFY_URL'                	=> $base_url,
			'JOINOTIFY_ASSETS'             	=> $base_url . 'assets/',
			'JOINOTIFY_ABSPATH'            	=> dirname( $base_file ) . '/',
			'JOINOTIFY_ADMIN_EMAIL'        	=> get_option('admin_email'),
			'JOINOTIFY_DOCS_URL'           	=> 'https://ajuda.meumouse.com/docs/joinotify/overview',
			'JOINOTIFY_REGISTER_PHONE_URL' 	=> 'https://meumouse.com/minha-conta/joinotify-slots/',
			'JOINOTIFY_API_BASE_URL'       	=> 'https://slots-manager.joinotify.com',
			'JOINOTIFY_SLUG'               	=> 'joinotify',
			'JOINOTIFY_VERSION'            	=> $this->plugin_version,
			// Verbose runtime logging follows WP_DEBUG: on in development, off in
			// production. A site can still force it via wp-config (defined first wins).
			'JOINOTIFY_DEV_MODE'          	=> defined('WP_DEBUG') && WP_DEBUG,
		);

		foreach ( $constants as $key => $value ) {
			if ( ! defined( $key ) ) {
				define( $key, $value );
			}
		}
	}


	/**
	 * PHP version notice.
	 * 
	 * @since 1.0.0
	 * @return void
	 */
	public function php_version_notice() {
		$class = 'notice notice-error is-dismissible';
		$message = __( '<strong>Joinotify</strong> requires PHP version 7.4 or higher. Contact your hosting support to upgrade.', 'joinotify' );

		printf( '<div class="%1$s"><p>%2$s</p></div>', esc_attr( $class ), $message );
	}


	/**
	 * Instance only the explicitly allowed classes on init.
	 *
	 * REST controllers are no longer listed here; they are deferred to the
	 * rest_api_init hook (see instance_rest_classes) so they are not built on
	 * non-REST requests. Admin-screen classes are only instantiated for admin
	 * requests, but still on `init` because the admin menu (admin_menu) is
	 * built before admin_init fires.
	 *
	 * @since 1.4.6
	 * @version 2.0.0
	 * @return void
	 */
	public function instance_init_classes() {
		$classes = apply_filters( 'Joinotify/Init/Init_Classes', array(
			'MeuMouse\\Joinotify\\Core\\Workflow_Post_Type',
			'MeuMouse\\Joinotify\\Core\\Compatibility',
			'MeuMouse\\Joinotify\\Builder\\Custom_Variables',
			'MeuMouse\\Joinotify\\Api\\Controller',
			'MeuMouse\\Joinotify\\Core\\Notification_Queue',
			'MeuMouse\\Joinotify\\Core\\Message_History',
			'MeuMouse\\Joinotify\\Cron\\Schedule',
			'MeuMouse\\Joinotify\\Cron\\Routines',
			'MeuMouse\\Joinotify\\Core\\Debug',
		));

		// Admin-screen classes only register hooks that fire inside wp-admin
		// (admin_menu, admin_enqueue_scripts), so skip them entirely on
		// front-end, REST and cron requests.
		if ( is_admin() ) {
			$admin_classes = apply_filters( 'Joinotify/Init/Admin_Screen_Classes', array(
				'MeuMouse\\Joinotify\\Admin\\Menu',
				'MeuMouse\\Joinotify\\Assets\\Settings_Assets',
			));

			if ( is_array( $admin_classes ) ) {
				$classes = array_merge( $classes, $admin_classes );
			}
		}

		if ( ! is_array( $classes ) || empty( $classes ) ) {
			return;
		}

		foreach ( $classes as $class ) {
			$this->safe_instance_class( $class );
		}
	}


	/**
	 * Instance the REST controllers when WordPress builds the REST API.
	 *
	 * Each controller instantiates its route classes, which in turn register
	 * their endpoints on rest_api_init. Because this callback already runs on
	 * rest_api_init at an earlier priority (5), those default-priority (10)
	 * registrations still fire within the same dispatch cycle.
	 *
	 * @since 2.0.0
	 * @return void
	 */
	public function instance_rest_classes() {
		$classes = apply_filters( 'Joinotify/Init/Rest_Classes', array(
			'MeuMouse\\Joinotify\\Admin\\Builder\\Rest_Controller',
			'MeuMouse\\Joinotify\\Admin\\Workflows\\Rest_Controller',
			'MeuMouse\\Joinotify\\Admin\\Settings\\Rest_Controller',
			'MeuMouse\\Joinotify\\Admin\\History\\Rest_Controller',
			'MeuMouse\\Joinotify\\Rest\\Extensions_Controller',
		));

		if ( ! is_array( $classes ) || empty( $classes ) ) {
			return;
		}

		foreach ( $classes as $class ) {
			$this->safe_instance_class( $class );
		}
	}


	/**
	 * Instance only the explicitly allowed classes on init.
	 * 
	 * @since 1.4.6
	 * @return void
	 */
	public function instance_admin_init_classes() {
		$classes = apply_filters( 'Joinotify/Init/Admin_Init_Classes', array(
			'MeuMouse\\Joinotify\\Admin\\Admin',
		));

		if ( ! is_array( $classes ) || empty( $classes ) ) {
			return;
		}

		foreach ( $classes as $class ) {
			$this->safe_instance_class( $class );
		}
	}


	/**
	 * Instance only the explicitly allowed classes on wp_loaded.
	 * 
	 * @since 1.4.6
	 * @version 1.4.7
	 * @return void
	 */
	public function instance_wp_loaded_classes() {
		$classes = apply_filters( 'Joinotify/Init/WP_Loaded_Classes', array(
			'MeuMouse\\Joinotify\\Core\\Cache',
			'MeuMouse\\Joinotify\\Builder\\Workflow_Manager',
			'MeuMouse\\Joinotify\\Integrations\\Whatsapp',
			'MeuMouse\\Joinotify\\Integrations\\OpenAI',
			'MeuMouse\\Joinotify\\Integrations\\AI_Messaging',
			'MeuMouse\\Joinotify\\Integrations\\Flexify_Checkout',
			'MeuMouse\\Joinotify\\Integrations\\Elementor',
			'MeuMouse\\Joinotify\\Integrations\\Woocommerce',
			'MeuMouse\\Joinotify\\Integrations\\Wpforms',
			'MeuMouse\\Joinotify\\Integrations\\Wordpress',
			'MeuMouse\\Joinotify\\Api\\Updater',
			'MeuMouse\\Joinotify\\Core\\Logger',
			'MeuMouse\\Joinotify\\Api\\License',
		));

		if ( ! is_array( $classes ) || empty( $classes ) ) {
			return;
		}

		foreach ( $classes as $class ) {
			$this->safe_instance_class( $class );
		}
	}


	/**
	 * Safely instance a single class with validation.
	 *
	 * Only first-party Joinotify classes (parameterless constructors) reach
	 * this method, so they are instantiated directly with `new`. This avoids
	 * building a ReflectionClass for every bootstrapped class on every request;
	 * the try/catch still shields the bootstrap from abstract classes,
	 * constructor-argument mismatches and runtime errors.
	 *
	 * @since 1.4.3
	 * @version 2.0.0
	 * @param string $class Full class name with namespace.
	 * @return mixed|null Returns the class instance or null on failure.
	 */
	private function safe_instance_class( $class ) {
		if ( ! is_string( $class ) || empty( trim( $class ) ) ) {
			return null;
		}

		// Only allow Joinotify namespace classes.
		if ( strpos( $class, 'MeuMouse\\Joinotify\\' ) !== 0 ) {
			return null;
		}

		if ( isset( $this->instantiated_classes[ $class ] ) ) {
			return $this->instantiated_classes[ $class ];
		}

		if ( ! class_exists( $class ) ) {
			error_log( 'Joinotify: Class does not exist: ' . $class );
			return null;
		}

		try {
			$instance = new $class();

			$this->instantiated_classes[ $class ] = $instance;

			// Run the optional init() lifecycle method. is_callable() returns
			// false for private/protected methods, mirroring the previous
			// public, non-static reflection guard.
			if ( is_callable( array( $instance, 'init' ) ) ) {
				$instance->init();
			}

			return $instance;

		} catch ( Throwable $e ) {
			error_log( 'Joinotify: Error instantiating class ' . $class . ': ' . $e->getMessage() );

			return null;
		}
	}


	/**
	 * Register deferred class instantiation by hook.
	 *
	 * @since 1.4.5
	 * @version 1.4.7
	 * @return void
	 */
	private function register_deferred_classes() {
		/**
		 * Allow third-parties to add deferred classes.
		 *
		 * Format:
		 * array(
		 *   'hook/name' => array( 'Full\\ClassName', ... ),
		 * )
		 *
		 * @since 1.4.5
		 */
		$map = apply_filters( 'Joinotify/Init/Deferred_Classes', $this->deferred_classes );

		if ( ! is_array( $map ) || empty( $map ) ) {
			return;
		}

		foreach ( $map as $hook => $classes ) {
			if ( ! is_string( $hook ) || empty( trim( $hook ) ) ) {
				continue;
			}

			if ( ! is_array( $classes ) || empty( $classes ) ) {
				continue;
			}

			$callback = function() use ( $classes ) {
				foreach ( $classes as $class ) {
					$this->safe_instance_class( $class );
				}
			};

			// If the hook already fired, instantiate immediately.
			if ( did_action( $hook ) ) {
				$callback();

				continue;
			}

			add_action( $hook, $callback, 10, 0 );
		}
	}


	/**
	 * Plugin action links.
	 * 
	 * @since 1.0.0
	 * @version 1.4.7
	 * @param array $action_links Default plugin action links.
	 * @return array
	 */
	public function add_action_plugin_links( $action_links ) {
		if ( get_option('joinotify_license_status') !== 'valid' ) {
			$plugins_links = array(
				'<a href="' . admin_url( 'admin.php?page=joinotify-license' ) . '">' . __( 'Configure', 'joinotify' ) . '</a>',
			);
		} else {
			$plugins_links = array(
				'<a href="' . admin_url( 'admin.php?page=joinotify-settings' ) . '">' . __( 'Configure', 'joinotify' ) . '</a>',
			);
		}

		return array_merge( $plugins_links, $action_links );
	}


	/**
	 * Add meta links on plugin.
	 * 
	 * @since 1.0.0
	 * @version 1.4.7
	 * @param array  $plugin_meta An array of the plugin's metadata.
	 * @param string $plugin_file Path to the plugin file relative to the plugins directory.
	 * @param array  $plugin_data An array of plugin data.
	 * @param string $status      Status filter currently applied to the plugin list.
	 * @return array
	 */
	public function add_row_meta_links( $plugin_meta, $plugin_file, $plugin_data, $status ) {
		if ( strpos( $plugin_file, $this->basename ) !== false ) {
			$new_links = array(
				'docs' => '<a href="' . esc_attr( JOINOTIFY_DOCS_URL ) . '" target="_blank">' . __( 'Documentation', 'joinotify' ) . '</a>',
			);

			$plugin_meta = array_merge( $plugin_meta, $new_links );
		}

		return $plugin_meta;
	}
}

