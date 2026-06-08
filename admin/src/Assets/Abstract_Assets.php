<?php

namespace MeuMouse\Joinotify\Assets;

defined('ABSPATH') || exit;

/**
 * Shared helpers for Joinotify asset loaders.
 *
 * @since 1.4.7
 * @package MeuMouse\Joinotify\Assets
 * @author MeuMouse.com
 */
abstract class Abstract_Assets {

    /**
     * Base URL for the plugin assets directory.
     *
     * @since 1.4.7
     * @var string
     */
    protected $assets_url = '';

    /**
     * Suffix used to resolve minified files.
     *
     * @since 1.4.7
     * @var string
     */
    protected $min_file = '';

    /**
     * Current plugin version.
     *
     * @since 1.4.7
     * @var string
     */
    protected $version = '';

    /**
     * Whether debug mode is enabled.
     *
     * @since 1.4.7
     * @var bool
     */
    protected $debug_mode = false;

    /**
     * Whether developer mode is enabled.
     *
     * @since 1.4.7
     * @var bool
     */
    protected $dev_mode = false;


    /**
     * Load the shared asset context from plugin constants.
     *
     * @since 1.4.7
     * @return void
     */
    public function __construct() {
        $this->assets_url = defined( 'JOINOTIFY_ASSETS' ) ? JOINOTIFY_ASSETS : '';
        $this->version = defined( 'JOINOTIFY_VERSION' ) ? JOINOTIFY_VERSION : '';
        $this->dev_mode = defined( 'JOINOTIFY_DEV_MODE' ) ? (bool) JOINOTIFY_DEV_MODE : false;
        $this->debug_mode = defined( 'JOINOTIFY_DEBUG_MODE' ) ? (bool) JOINOTIFY_DEBUG_MODE : false;
        $this->min_file = $this->debug_mode ? '' : '.min';
    }


    /**
     * Resolve a public asset URL from a relative path.
     *
     * @since 1.4.7
     * @param string $relative_path Relative path inside the assets directory.
     * @return string
     */
    protected function build_asset_url( $relative_path ) {
        if ( is_string( $relative_path ) && preg_match( '#^https?://#i', $relative_path ) ) {
            return $relative_path;
        }

        return trailingslashit( $this->assets_url ) . ltrim( $relative_path, '/' );
    }


    /**
     * Enqueue a stylesheet using a relative asset path.
     *
     * @since 1.4.7
     * @param string $handle Style handle.
     * @param string $relative_path Relative asset path.
     * @param array  $deps Optional style dependencies.
     * @param string $version Optional asset version.
     * @return void
     */
    protected function enqueue_style_asset( $handle, $relative_path, $deps = array(), $version = null ) {
        wp_enqueue_style(
            $handle,
            $this->build_asset_url( $relative_path ),
            $deps,
            null === $version ? $this->version : $version
        );
    }


    /**
     * Enqueue a script using a relative asset path.
     *
     * @since 1.4.7
     * @param string $handle Script handle.
     * @param string $relative_path Relative asset path.
     * @param array  $deps Optional script dependencies.
     * @param string $version Optional asset version.
     * @param bool   $in_footer Whether to print the script in the footer.
     * @return void
     */
    protected function enqueue_script_asset( $handle, $relative_path, $deps = array(), $version = null, $in_footer = true ) {
        wp_enqueue_script(
            $handle,
            $this->build_asset_url( $relative_path ),
            $deps,
            null === $version ? $this->version : $version,
            $in_footer
        );
    }


    /**
     * Localize a script with a data payload.
     *
     * @since 1.4.7
     * @param string $handle Script handle.
     * @param string $object_name Localized object name.
     * @param array  $data Localization payload.
     * @return void
     */
	protected function localize_script_asset( $handle, $object_name, $data ) {
		wp_localize_script( $handle, $object_name, $data );
	}


	/**
	 * Read a Vite manifest file if it exists.
	 *
	 * @since 1.4.7
	 * @param string $manifest_relative_path Relative path from the plugin root.
	 * @return array<string,mixed>
	 */
	protected function get_vite_manifest( $manifest_relative_path ) {
		$manifest_path = trailingslashit( defined( 'JOINOTIFY_DIR' ) ? JOINOTIFY_DIR : plugin_dir_path( JOINOTIFY_FILE ) ) . ltrim( $manifest_relative_path, '/' );

		if ( ! file_exists( $manifest_path ) ) {
			return array();
		}

		$contents = file_get_contents( $manifest_path );
		$decoded = json_decode( (string) $contents, true );

		return is_array( $decoded ) ? $decoded : array();
	}


	/**
	 * Enqueue a Vite build entry, including its CSS assets.
	 *
	 * @since 1.4.7
	 * @param string $handle Script handle.
	 * @param string $entry_key Manifest entry key, for example `src/entries/builder.js`.
	 * @param string $manifest_relative_path Relative path to the manifest file.
	 * @param string $asset_root_relative_path Public asset root relative to the plugin root.
	 * @return void
	 */
	protected function enqueue_vite_entry_asset( $handle, $entry_key, $manifest_relative_path = 'dist/.vite/manifest.json', $asset_root_relative_path = 'dist' ) {
		$manifest = $this->get_vite_manifest( $manifest_relative_path );

		if ( empty( $manifest[ $entry_key ] ) || ! is_array( $manifest[ $entry_key ] ) ) {
			return;
		}

		$entry = $manifest[ $entry_key ];
		$asset_root = trailingslashit( defined( 'JOINOTIFY_URL' ) ? JOINOTIFY_URL : plugin_dir_url( JOINOTIFY_FILE ) ) . trim( $asset_root_relative_path, '/' ) . '/';
		$asset_dir = trailingslashit( defined( 'JOINOTIFY_DIR' ) ? JOINOTIFY_DIR : plugin_dir_path( JOINOTIFY_FILE ) ) . trim( $asset_root_relative_path, '/' ) . '/';
		$entry_file = $entry['file'] ?? '';

		if ( $entry_file ) {
			wp_enqueue_script(
				$handle,
				$asset_root . ltrim( $entry_file, '/' ),
				array(),
				$this->resolve_built_asset_version( $asset_dir . ltrim( $entry_file, '/' ) ),
				true
			);
			wp_script_add_data( $handle, 'type', 'module' );
		}

		$css_files = array();

		if ( ! empty( $entry['css'] ) && is_array( $entry['css'] ) ) {
			$css_files = array_merge( $css_files, $entry['css'] );
		}

		if ( ! empty( $entry['imports'] ) && is_array( $entry['imports'] ) ) {
			foreach ( $entry['imports'] as $import_key ) {
				if ( empty( $manifest[ $import_key ] ) || ! is_array( $manifest[ $import_key ] ) ) {
					continue;
				}

				if ( ! empty( $manifest[ $import_key ]['css'] ) && is_array( $manifest[ $import_key ]['css'] ) ) {
					$css_files = array_merge( $css_files, $manifest[ $import_key ]['css'] );
				}
			}
		}

		$css_files = array_values( array_unique( array_filter( $css_files ) ) );

		foreach ( $css_files as $index => $css_file ) {
			wp_enqueue_style(
				$handle . '-css-' . $index,
				$asset_root . ltrim( $css_file, '/' ),
				array(),
				$this->resolve_built_asset_version( $asset_dir . ltrim( $css_file, '/' ) )
			);
		}
	}


	/**
	 * Resolve a cache-busting version for a built Vite asset.
	 *
	 * The Vite entry script and its stylesheet keep fixed file names
	 * (`builder/app.js`, `styles/*.css`), so a static plugin version would let
	 * browsers serve a stale bundle after every rebuild. Use the file
	 * modification time so each rebuild produces a fresh URL, falling back to
	 * the plugin version when the file cannot be read.
	 *
	 * @since 2.0.0
	 * @param string $absolute_path Absolute filesystem path to the built asset.
	 * @return string
	 */
	protected function resolve_built_asset_version( $absolute_path ) {
		if ( is_string( $absolute_path ) && file_exists( $absolute_path ) ) {
			$modified_time = filemtime( $absolute_path );

			if ( $modified_time ) {
				return (string) $modified_time;
			}
		}

		return $this->version;
	}
}
