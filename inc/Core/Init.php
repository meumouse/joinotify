<?php

namespace MeuMouse\Joinotify\Core;

use ReflectionException;
use ReflectionClass;
use Exception;

defined('ABSPATH') || exit;

/**
 * Initialize plugin classes
 * 
 * @since 1.0.0
 * @version 1.4.5
 * @package MeuMouse.com
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
	 * @version 1.4.5
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
     * @var array
     */
    private $deferred_classes = array(
        'elementor/init' => array(
            'MeuMouse\\Joinotify\\Integrations\\Elementor',
            'MeuMouse\\Joinotify\\Integrations\\Elementor_Forms',
        ),
    );

	/**
	 * Construct function.
	 * 
	 * @since 1.0.0
	 * @version 1.4.5
	 * @param string $plugin_file | Plugin main file path.
     * @param string $plugin_version | Plugin version
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
		require_once JOINOTIFY_INC . 'Core/Functions.php';

		$this->directory = JOINOTIFY_DIR;
		$this->basename = JOINOTIFY_BASENAME;

        $this->register_deferred_classes();
		$this->instance_classes();

		// Add settings link on plugins list.
		add_filter( 'plugin_action_links_' . $this->basename, array( $this, 'add_action_plugin_links' ), 10, 4 );

		// Add docs link on plugins list.
		add_filter( 'plugin_row_meta', array( $this, 'add_row_meta_links' ), 10, 4 );

        // load plugin text domain
        add_action( 'init', array( $this, 'load_text_domain' ) );

		/**
		 * Fire hook after Joinotify initialize.
		 * 
		 * @since 1.1.0
		 * @version 1.4.4
		 */
		do_action('joinotify_init');
	}


    /**
     * Load text domain after init hook
     * 
     * @since 1.0.0
     * @version 1.4.5
     * @return void
     */
    public function load_text_domain() {
        load_plugin_textdomain( 'joinotify', false, dirname( $this->basename ) . '/languages/' );
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
			'JOINOTIFY_BASENAME'           => plugin_basename( $base_file ),
			'JOINOTIFY_FILE'               => $base_file,
			'JOINOTIFY_DIR'                => $base_dir,
			'JOINOTIFY_INC'                => $base_dir . 'inc/',
			'JOINOTIFY_URL'                => $base_url,
			'JOINOTIFY_ASSETS'             => $base_url . 'assets/',
			'JOINOTIFY_ABSPATH'            => dirname( $base_file ) . '/',
			'JOINOTIFY_ADMIN_EMAIL'        => get_option('admin_email'),
			'JOINOTIFY_DOCS_URL'           => 'https://ajuda.meumouse.com/docs/joinotify/overview',
			'JOINOTIFY_REGISTER_PHONE_URL' => 'https://meumouse.com/minha-conta/joinotify-slots/',
			'JOINOTIFY_API_BASE_URL'       => 'https://slots-manager.joinotify.com',
			'JOINOTIFY_SLUG'               => 'joinotify',
			'JOINOTIFY_VERSION'            => $this->plugin_version,
			'JOINOTIFY_DEV_MODE'           => false,
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
		$message = __( '<strong>Joinotify</strong> requer a versão do PHP 7.4 ou maior. Contate o suporte da sua hospedagem para realizar a atualização.', 'joinotify' );

		printf( '<div class="%1$s"><p>%2$s</p></div>', esc_attr( $class ), $message );
	}


	/**
	 * Instance classes after loading Composer.
	 * 
	 * @since 1.0.0
	 * @version 1.4.3
	 * @return void
	 */
	public function instance_classes() {
		$this->instance_manual_classes();
		$this->instance_composer_classes();
	}


	/**
	 * Process manual classes registered via filter.
	 * 
	 * @since 1.4.3
	 * @return void
	 */
	private function instance_manual_classes() {
		$manual_classes = apply_filters( 'Joinotify/Init/Instance_Classes', array() );

		if ( ! is_array( $manual_classes ) || empty( $manual_classes ) ) {
			return;
		}

		foreach ( $manual_classes as $class ) {
			$this->safe_instance_class( $class );
		}
	}


	/**
	 * Process Composer autoloaded classes.
	 * 
	 * @since 1.4.3
     * @version 1.4.5
	 * @return void
	 */
	private function instance_composer_classes() {
		$classmap_path = JOINOTIFY_DIR . 'vendor/composer/autoload_classmap.php';
        $deferred = $this->get_deferred_class_list();

		if ( ! file_exists( $classmap_path ) || ! is_readable( $classmap_path ) ) {
			return;
		}

		$classmap = include $classmap_path;

		if ( ! is_array( $classmap ) || empty( $classmap ) ) {
			return;
		}

		foreach ( $classmap as $class => $path ) {
			if ( strpos( $class, 'MeuMouse\\Joinotify\\' ) !== 0 ) {
				continue;
			}

			if ( $class === __CLASS__ ) {
				continue;
			}

            if ( in_array( $class, $deferred, true ) ) {
                continue;
            }

			if ( $class === 'Composer\\InstalledVersions' ) {
				continue;
			}

			if ( $class === 'MeuMouse\\Joinotify\\Core\\Workflows_Table' ) {
				if ( wp_doing_ajax() || ! is_admin() ) {
					continue;
				}

				$current_page = isset( $_GET['page'] ) ? sanitize_text_field( $_GET['page'] ) : '';
				$plugin_pages = array( 'joinotify-workflows' );

				if ( ! in_array( $current_page, $plugin_pages, true ) && ! defined( 'DOING_AJAX' ) ) {
					continue;
				}
			}

			$this->safe_instance_class( $class );
		}
	}


	/**
	 * Safely instance a single class with validation.
	 * 
	 * @since 1.4.3
	 * @version 1.4.5
	 * @param string $class Full class name with namespace.
	 * @return mixed|null Returns the class instance or null on failure.
	 */
	private function safe_instance_class( $class ) {
		if ( ! is_string( $class ) || empty( trim( $class ) ) ) {
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
			$reflection = new ReflectionClass( $class );

			if ( ! $reflection->isInstantiable() ) {
				return null;
			}

			$constructor = $reflection->getConstructor();

			if ( $constructor && $constructor->getNumberOfRequiredParameters() > 0 ) {
				error_log( 'Joinotify: Class requires constructor parameters: ' . $class );
				return null;
			}

			$instance = $reflection->newInstance();

			$this->instantiated_classes[ $class ] = $instance;

			if ( method_exists( $instance, 'init' ) ) {
				$init_method = $reflection->getMethod( 'init' );

				if ( $init_method->isPublic() && ! $init_method->isStatic() ) {
					$instance->init();
				}
			}

			return $instance;

		} catch ( ReflectionException $e ) {
			error_log( 'Joinotify: Reflection error for class ' . $class . ': ' . $e->getMessage() );
			return null;
		} catch ( Exception $e ) {
			error_log( 'Joinotify: Error instantiating class ' . $class . ': ' . $e->getMessage() );
			return null;
		}
	}


    /**
     * Register deferred class instantiation by hook.
     *
     * @since 1.4.5
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
     * Get a flat list of deferred classes.
     *
     * @since 1.4.5
     * @return array
     */
    private function get_deferred_class_list() {
        $map = apply_filters( 'Joinotify/Init/Deferred_Classes', $this->deferred_classes );

        if ( ! is_array( $map ) || empty( $map ) ) {
            return array();
        }

        $all = array();

        foreach ( $map as $classes ) {
            if ( ! is_array( $classes ) ) {
                continue;
            }

            foreach ( $classes as $class ) {
                if ( is_string( $class ) && ! empty( trim( $class ) ) ) {
                    $all[] = $class;
                }
            }
        }

        return array_values( array_unique( $all ) );
    }


	/**
	 * Plugin action links.
	 * 
	 * @since 1.0.0
	 * @version 1.3.0
	 * @param array $action_links Default plugin action links.
	 * @return array
	 */
	public function add_action_plugin_links( $action_links ) {
		if ( get_option( 'joinotify_license_status' ) !== 'valid' ) {
			$plugins_links = array(
				'<a href="' . admin_url( 'admin.php?page=joinotify-license' ) . '">' . __( 'Configurar', 'joinotify' ) . '</a>',
			);
		} else {
			$plugins_links = array(
				'<a href="' . admin_url( 'admin.php?page=joinotify-settings' ) . '">' . __( 'Configurar', 'joinotify' ) . '</a>',
			);
		}

		return array_merge( $plugins_links, $action_links );
	}


	/**
	 * Add meta links on plugin.
	 * 
	 * @since 1.0.0
	 * @version 1.3.0
	 * @param array  $plugin_meta An array of the plugin's metadata.
	 * @param string $plugin_file Path to the plugin file relative to the plugins directory.
	 * @param array  $plugin_data An array of plugin data.
	 * @param string $status      Status filter currently applied to the plugin list.
	 * @return array
	 */
	public function add_row_meta_links( $plugin_meta, $plugin_file, $plugin_data, $status ) {
		if ( strpos( $plugin_file, JOINOTIFY_BASENAME ) !== false ) {
			$new_links = array(
				'docs' => '<a href="' . esc_attr( JOINOTIFY_DOCS_URL ) . '" target="_blank">' . __( 'Documentação', 'joinotify' ) . '</a>',
			);

			$plugin_meta = array_merge( $plugin_meta, $new_links );
		}

		return $plugin_meta;
	}
}