<?php

namespace MeuMouse\Joinotify\Core;

// Exit if accessed directly.
defined('ABSPATH') || exit;

/**
 * Initialize plugin classes
 * 
 * @since 1.0.0
 * @version 1.4.2
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
     * Construct function
     * 
     * @since 1.0.0
     * @version 1.4.2
     * @return void
     */
    public function __construct() {
        // load plugin functions
        require_once JOINOTIFY_INC . 'Core/Functions.php';

        $this->instance_classes();

        // load text domain
        load_plugin_textdomain( 'joinotify', false, dirname( $this->basename ) . '/languages/' );

        // add settings link on plugins list
        add_filter( 'plugin_action_links_' . $this->basename, array( $this, 'add_action_plugin_links' ), 10, 4 );

        // add docs link on plugins list
        add_filter( 'plugin_row_meta', array( $this, 'add_row_meta_links' ), 10, 4 );
    }


    /**
     * Instance classes after load Composer
     * 
     * @since 1.0.0
     * @version 1.4.2
     * @return void
     */
    public function instance_classes() {
        /**
         * Filter to add new classes
         * 
         * @since 1.0.0
         * @param array $classes | Array with classes to instance
         */
        $manual_classes = apply_filters( 'Joinotify/Init/Instance_Classes', array() );

        // iterate through manual classes and instance them
        foreach ( $manual_classes as $class ) {
            if ( class_exists( $class ) ) {
                $instance = new $class();

                if ( method_exists( $instance, 'init' ) ) {
                    $instance->init();
                }
            }
        }

        // get classmap from Composer
        $classmap = include_once $this->directory . 'vendor/composer/autoload_classmap.php';

        // ensure classmap is an array
        if ( ! is_array( $classmap ) ) {
            $classmap = array();
        }

        // iterate through classmap and instance classes
        foreach ( $classmap as $class => $path ) {
            // skip classes not in the plugin namespace
            if ( strpos( $class, 'MeuMouse\\Joinotify\\' ) !== 0 ) {
                continue;
            }

            // skip the Init class to prevent duplicate instances
            if ( strpos( $class, 'MeuMouse\\Joinotify\\Core\\Init' ) !== false ) {
                continue;
            }

            // skip specific utility classes
            if ( $class === 'Composer\\InstalledVersions' ) {
                continue;
            }

            // check if class exists
            if ( ! class_exists( $class ) ) {
                continue;
            }

            // use ReflectionClass to check if class is instantiable
            $reflection = new \ReflectionClass( $class );

            // instance only if class is not abstract, trait or interface
            if ( ! $reflection->isInstantiable() ) {
                continue;
            }

            // check if class has a constructor
            $constructor = $reflection->getConstructor();

            // skip classes that require mandatory arguments in __construct
            if ( $constructor && $constructor->getNumberOfRequiredParameters() > 0 ) {
                continue;
            }

            // safe instance
            $instance = new $class();

            // this is useful for classes that need to run some initialization code
            if ( method_exists( $instance, 'init' ) ) {
                $instance->init();
            }
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
}