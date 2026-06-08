<?php

namespace MeuMouse\Joinotify\Assets;

defined('ABSPATH') || exit;

/**
 * Load legacy admin assets for Joinotify screens.
 *
 * @since 1.4.7
 * @package MeuMouse\Joinotify\Assets
 * @author MeuMouse.com
 */
class Core_Assets extends Abstract_Assets {

    /**
     * Register legacy admin asset hooks.
     *
     * @since 1.4.7
     * @version 1.4.7
     * @return void
     */
    public function __construct() {
        parent::__construct();

        // Settings page styles and scripts.
        add_action( 'admin_enqueue_scripts', array( $this, 'settings_assets' ) );

        // License page scripts.
        add_action( 'admin_enqueue_scripts', array( $this, 'license_assets' ) );

        // Workflow builder scripts.
        add_action( 'admin_enqueue_scripts', array( $this, 'builder_assets' ) );

        // Workflows table scripts.
        add_action( 'admin_enqueue_scripts', array( $this, 'workflows_table_assets' ) );
    }


    /**
     * Enqueue assets on the settings page.
     *
     * @since 1.4.7
     * @version 1.4.7
     * @return void
     */
    public function settings_assets() {
        if ( ! joinotify_check_admin_page( 'joinotify-settings' ) ) {
            return;
        }

        $this->enqueue_style_asset(
            'joinotify-styles',
            'admin/css/settings' . $this->min_file . '.css'
        );
    }


    /**
     * Enqueue assets on the license page.
     *
     * @since 1.4.7
     * @version 1.4.7
     * @return void
     */
    public function license_assets() {
        if ( ! joinotify_check_admin_page( 'joinotify-license' ) ) {
            return;
        }

        $this->enqueue_script_asset(
            'joinotify-license-scripts',
            'admin/js/license' . $this->min_file . '.js',
            array( 'jquery' )
        );

        // Keep the legacy localized payload available for the license screen.
        $this->localize_script_asset(
            'joinotify-license-scripts',
            'joinotify_license_params',
            array(
                'debug_mode' => $this->debug_mode,
                'dev_mode'   => $this->dev_mode,
                'ajax_url'   => admin_url( 'admin-ajax.php' ),
                'i18n'       => array(
                    'close_notice_aria_label'   => __( 'Close', 'joinotify' ),
                    'confirm_deactivate_license' => __( 'Are you sure you want to deactivate your license?', 'joinotify' ),
                ),
            )
        );
    }


    /**
     * Enqueue scripts on the workflow builder page.
     *
     * @since 1.4.7
     * @version 1.4.7
     * @return void
     */
    public function builder_assets() {
        if ( ! joinotify_check_admin_page( 'joinotify-workflows-builder' ) ) {
            return;
        }

        wp_enqueue_media();
        $this->enqueue_vite_entry_asset(
            'joinotify-builder-app',
            'src/entries/builder.js'
        );
    }


    /**
     * Enqueue scripts on the workflows table page.
     *
     * @since 1.4.7
     * @version 1.4.7
     * @return void
     */
    public function workflows_table_assets() {
        if ( ! joinotify_check_admin_page( 'joinotify-workflows' ) || joinotify_check_admin_page( 'joinotify-workflows-builder' ) ) {
            return;
        }

        $this->enqueue_style_asset(
            'joinotify-workflows-table-styles',
            'admin/css/workflows-table' . $this->min_file . '.css'
        );
        $this->enqueue_script_asset(
            'joinotify-workflows-table-scripts',
            'admin/js/workflows-table' . $this->min_file . '.js',
            array( 'jquery' )
        );

        $this->localize_script_asset(
            'joinotify-workflows-table-scripts',
            'joinotify_workflows_table_params',
            array(
                'debug_mode' => $this->debug_mode,
                'dev_mode'   => $this->dev_mode,
                'ajax_url'   => admin_url( 'admin-ajax.php' ),
            )
        );
    }
}
