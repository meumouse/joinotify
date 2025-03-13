<?php

namespace MeuMouse\Joinotify\Core;

// Exit if accessed directly.
defined('ABSPATH') || exit;

/**
 * Class for handle with install and upgrade plugins
 * 
 * @since 1.2.0
 * @package MeuMouse.com
 */
class Modules {

    /**
     * Check if plugin is installed
     * 
     * @since 1.2.0
     * @param string $plugin_slug | Plugin slug
     * @return bool
     */
    public static function is_plugin_installed( $plugin_slug ) {
        if ( ! function_exists('get_plugins') ) {
            require_once ABSPATH . 'wp-admin/includes/plugin.php';
        }

        $all_plugins = get_plugins();
        
        return ! empty( $all_plugins[$plugin_slug] );
    }
    

    /**
     * Install plugin
     * 
     * @since 1.2.0
     * @param string $plugin_zip | URL of plugin
     * @return object|bool
     */
    public static function install_plugin( $plugin_zip ) {
        include_once ABSPATH . 'wp-admin/includes/class-wp-upgrader.php';
        wp_cache_flush();
        
        $upgrader = new \Plugin_Upgrader( new \WP_Ajax_Upgrader_Skin() );
        $installed = $upgrader->install( $plugin_zip );

        if ( is_wp_error( $installed ) ) {
            Logger::register_log( 'Error on install plugin: ' . print_r( $installed->get_error_message(), true ), 'ERROR' );
        }

        return $installed;
    }


    /**
     * Upgrade plugin
     * 
     * @since 1.2.0
     * @param string $plugin_slug | Plugin slug
     * @return object|bool
     */
    public static function upgrade_plugin( $plugin_slug ) {
        include_once ABSPATH . 'wp-admin/includes/class-wp-upgrader.php';
        wp_cache_flush();
        
        $upgrader = new \Plugin_Upgrader( new \WP_Ajax_Upgrader_Skin() );
        $upgraded = $upgrader->upgrade( $plugin_slug );

        if ( is_wp_error( $upgraded ) ) {
            Logger::register_log( 'Error on upgrade plugin: ' . print_r( $installed->get_error_message(), true ), 'ERROR' );
        }

        return $upgraded;
    }
}