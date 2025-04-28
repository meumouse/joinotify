<?php

namespace MeuMouse\Joinotify\Core;

// Exit if accessed directly.
defined('ABSPATH') || exit;

/**
 * Initialize plugin classes
 * 
 * @since 1.0.0
 * @version 1.3.0
 * @package MeuMouse.com
 */
class Init {

    /**
     * Construct function
     * 
     * @since 1.0.0
     * @return void
     */
    public function __construct() {
        load_plugin_textdomain( 'joinotify', false, dirname( JOINOTIFY_BASENAME ) . '/languages/' );
        add_filter( 'plugin_action_links_' . JOINOTIFY_BASENAME, array( $this, 'add_action_plugin_links' ), 10, 4 );
        add_filter( 'plugin_row_meta', array( $this, 'add_row_meta_links' ), 10, 4 );

        // load plugin functions
        require_once JOINOTIFY_INC . 'Core/Functions.php';

        self::instance_classes();
    }


    /**
     * Instance classes after load Composer
     * 
     * @since 1.0.0
     * @version 1.3.0
     * @return void
     */
    public static function instance_classes() {
        $classes = apply_filters( 'Joinotify/Init/Instance_Classes', array(
            '\MeuMouse\Joinotify\Core\Logger',
            '\MeuMouse\Joinotify\API\License',
            '\MeuMouse\Joinotify\Admin\Admin',
            '\MeuMouse\Joinotify\Core\Compatibility',
            '\MeuMouse\Joinotify\Core\Debug',
            '\MeuMouse\Joinotify\Core\Assets',
            '\MeuMouse\Joinotify\Core\Ajax',
            '\MeuMouse\Joinotify\Cron\Schedule',
            '\MeuMouse\Joinotify\Cron\Routines',
            '\MeuMouse\Joinotify\Builder\Workflow_Manager',
            '\MeuMouse\Joinotify\Integrations\Whatsapp',
            '\MeuMouse\Joinotify\Integrations\Wordpress',
            '\MeuMouse\Joinotify\Integrations\Woocommerce',
            '\MeuMouse\Joinotify\Integrations\Woo_Subscriptions',
            '\MeuMouse\Joinotify\Integrations\Flexify_Checkout',
            '\MeuMouse\Joinotify\Integrations\Elementor',
            '\MeuMouse\Joinotify\Integrations\Elementor_Forms',
            '\MeuMouse\Joinotify\Integrations\Wpforms',
            '\MeuMouse\Joinotify\Integrations\OpenAI',
            '\MeuMouse\Joinotify\Validations\Media_Types',
            '\MeuMouse\Joinotify\API\Controller',
        	'\MeuMouse\Joinotify\Core\Updater',
        ));

        foreach ( $classes as $class ) {
            if ( class_exists( $class ) ) {
                new $class();
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