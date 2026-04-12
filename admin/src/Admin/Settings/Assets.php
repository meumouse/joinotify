<?php

namespace MeuMouse\Joinotify\Admin\Settings;

defined('ABSPATH') || exit;

/**
 * Load the Vue settings app when the build artifacts are available.
 */
class Assets {

    public function __construct() {
        add_action( 'admin_enqueue_scripts', array( $this, 'enqueue_assets' ), 100 );
    }


    /**
     * Enqueue the Vue settings bundle or keep the legacy fallback.
     *
     * @return void
     */
    public function enqueue_assets() {
        if ( ! $this->is_settings_page() ) {
            return;
        }

		$script_path = JOINOTIFY_DIR . 'assets/admin/vue-settings/settings-app.js';
		$style_dir = JOINOTIFY_DIR . 'assets/admin/vue-settings';
		$main_style_path = $style_dir . '/main.css';
		$style_files = glob( $style_dir . '/*.css' ) ?: array();

        if ( ! file_exists( $script_path ) ) {
            return;
        }

        wp_dequeue_style( 'joinotify-styles' );
        wp_dequeue_script( 'joinotify-scripts' );
        wp_dequeue_style( 'bootstrap-grid' );
        wp_dequeue_style( 'bootstrap-utilities' );

        wp_deregister_style( 'joinotify-styles' );
        wp_deregister_script( 'joinotify-scripts' );

		if ( file_exists( $main_style_path ) ) {
			wp_enqueue_style(
				'joinotify-settings-app-main',
				JOINOTIFY_ASSETS . 'admin/vue-settings/main.css',
				array(),
				filemtime( $main_style_path )
			);
		}

		foreach ( $style_files as $style_path ) {
			if ( basename( $style_path ) === 'main.css' ) {
				continue;
			}

			wp_enqueue_style(
				'joinotify-settings-app-' . sanitize_title( basename( $style_path, '.css' ) ),
				JOINOTIFY_ASSETS . 'admin/vue-settings/' . basename( $style_path ),
				array(),
				filemtime( $style_path )
			);
		}

        wp_enqueue_script(
            'joinotify-settings-app',
            JOINOTIFY_ASSETS . 'admin/vue-settings/settings-app.js',
            array(),
            filemtime( $script_path ),
            true
        );
    }


    /**
     * Check if we are on the Joinotify settings page.
     *
     * @return bool
     */
    private function is_settings_page() {
        if ( ! is_admin() || ! isset( $_GET['page'] ) ) {
            return false;
        }

        return 'joinotify-settings' === sanitize_text_field( wp_unslash( $_GET['page'] ) );
    }
}
