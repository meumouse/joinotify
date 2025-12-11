<?php

namespace MeuMouse\Joinotify\Core;

// Exit if accessed directly.
defined('ABSPATH') || exit;

/**
 * Initialize plugin classes
 * 
 * @since 1.0.0
 * @version 1.4.4
 * @package MeuMouse.com
 */
class Init {

    /**
     * Plugin directory
     * 
     * @since 1.4.2
     * @return string
     */
    public $directory = JOINOTIFY_DIR;

    /**
     * Plugin basename
     * 
     * @since 1.0.0
     * @version 1.4.2
     */
    public $basename = JOINOTIFY_BASENAME;

    /**
     * Cache for instantiated classes to prevent duplicate instantiation
     * 
     * @since 1.4.3
     * @var array
     */
    private $instantiated_classes = array();

    /**
     * Construct function
     * 
     * @since 1.0.0
     * @version 1.4.2
     * @return void
     */
    public function __construct() {
        // Load plugin functions
        require_once JOINOTIFY_INC . 'Core/Functions.php';

        $this->instance_classes();

        // Load text domain
        load_plugin_textdomain( 'joinotify', false, dirname( $this->basename ) . '/languages/' );

        // Add settings link on plugins list
        add_filter( 'plugin_action_links_' . $this->basename, array( $this, 'add_action_plugin_links' ), 10, 4 );

        // Add docs link on plugins list
        add_filter( 'plugin_row_meta', array( $this, 'add_row_meta_links' ), 10, 4 );

        /**
         * Fire hook after Joinotify initialize
         * 
         * @since 1.1.0
         * @version 1.4.4
         */
        do_action('joinotify_init');
    }


    /**
     * Instance classes after loading Composer
     * 
     * @since 1.0.0
     * @version 1.4.3
     * @return void
     */
    public function instance_classes() {
        // Process manual classes from filter
        $this->instance_manual_classes();
        
        // Process Composer autoloaded classes
        $this->instance_composer_classes();
    }


    /**
     * Process manual classes registered via filter
     * 
     * @since 1.4.3
     * @return void
     */
    private function instance_manual_classes() {
        /**
         * Filter to add new classes
         * 
         * @since 1.0.0
         * @param array $classes | Array with classes to instance
         */
        $manual_classes = apply_filters( 'Joinotify/Init/Instance_Classes', array() );

        // Validate that we have an array
        if ( ! is_array( $manual_classes ) || empty( $manual_classes ) ) {
            return;
        }

        // Iterate through manual classes and instance them safely
        foreach ( $manual_classes as $class ) {
            $this->safe_instance_class( $class );
        }
    }


    /**
     * Process Composer autoloaded classes
     * 
     * @since 1.4.3
     * @return void
     */
    private function instance_composer_classes() {
        // Get classmap from Composer
        $classmap_path = $this->directory . 'vendor/composer/autoload_classmap.php';
        
        // Check if classmap file exists
        if ( ! file_exists( $classmap_path ) || ! is_readable( $classmap_path ) ) {
            return;
        }

        $classmap = include_once $classmap_path;

        // Ensure classmap is an array
        if ( ! is_array( $classmap ) || empty( $classmap ) ) {
            return;
        }

        // Iterate through classmap and instance classes safely
        foreach ( $classmap as $class => $path ) {
            // Skip classes not in the plugin namespace
            if ( strpos( $class, 'MeuMouse\\Joinotify\\' ) !== 0 ) {
                continue;
            }

            // Skip the Init class to prevent duplicate instances
            if ( strpos( $class, 'MeuMouse\\Joinotify\\Core\\Init' ) !== false ) {
                continue;
            }

            // Skip specific utility classes
            if ( $class === 'Composer\\InstalledVersions' ) {
                continue;
            }

            if ( $class === 'MeuMouse\\Joinotify\\Core\\Workflows_Table' ) {
                // check context
                if ( wp_doing_ajax() || ! is_admin() ) {
                    continue;
                }
                
                $current_page = isset( $_GET['page'] ) ? sanitize_text_field( $_GET['page'] ) : '';
                $plugin_pages = array( 'joinotify-workflows' );
                
                if ( ! in_array( $current_page, $plugin_pages ) && ! defined('DOING_AJAX') ) {
                    continue;
                }
            }

            // Instance the class safely
            $this->safe_instance_class( $class );
        }
    }


    /**
     * Safely instance a single class with validation
     * 
     * @since 1.4.3
     * @param string $class Full class name with namespace
     * @return mixed|null Returns the class instance or null on failure
     */
    private function safe_instance_class( $class ) {
        // Validate class name
        if ( ! is_string( $class ) || empty( trim( $class ) ) ) {
            return null;
        }

        // Check if class has already been instantiated
        if ( isset( $this->instantiated_classes[ $class ] ) ) {
            return $this->instantiated_classes[ $class ];
        }

        // Check if class exists
        if ( ! class_exists( $class ) ) {
            // Optionally log missing class for debugging
            // error_log( 'Joinotify: Class does not exist: ' . $class );
            return null;
        }

        try {
            // Use ReflectionClass for comprehensive validation
            $reflection = new \ReflectionClass( $class );

            // Skip if class is not instantiable (abstract, trait, or interface)
            if ( ! $reflection->isInstantiable() ) {
                return null;
            }

            // Get constructor and check for required parameters
            $constructor = $reflection->getConstructor();
            
            // Skip classes that require mandatory arguments in __construct
            if ( $constructor && $constructor->getNumberOfRequiredParameters() > 0 ) {
                // Optionally log classes with required parameters for debugging
                // error_log( 'Joinotify: Class requires constructor parameters: ' . $class );
                return null;
            }

            // Create new instance with error handling
            $instance = $reflection->newInstance();

            // Store instance in cache
            $this->instantiated_classes[ $class ] = $instance;

            // Call init method if it exists
            if ( method_exists( $instance, 'init' ) ) {
                // Validate that init is a public method
                $init_method = $reflection->getMethod('init');

                if ( $init_method->isPublic() && ! $init_method->isStatic() ) {
                    $instance->init();
                }
            }

            return $instance;

        } catch ( \ReflectionException $e ) {
            // Log reflection errors for debugging
            // error_log( 'Joinotify: Reflection error for class ' . $class . ': ' . $e->getMessage() );
            return null;
        } catch ( \Exception $e ) {
            // Catch any other exceptions during instantiation
            // error_log( 'Joinotify: Error instantiating class ' . $class . ': ' . $e->getMessage() );
            return null;
        }
    }


    /**
     * Plugin action links
     * 
     * @since 1.0.0
     * @version 1.3.0
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
     * @version 1.3.0
     * @param string $plugin_meta | An array of the plugin's metadata, including the version, author, author URI, and plugin URI
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
}