<?php

namespace MeuMouse\Joinotify\Core;

defined('ABSPATH') || exit;

/**
 * Resolve Vite manifest assets for Joinotify admin pages.
 *
 * @since 1.4.7
 * @package MeuMouse\Joinotify\Core
 */
class Scripts {

    /**
     * Relative path to the Vite manifest.
     *
     * @var string
     */
    const MANIFEST_PATH = 'dist/.vite/manifest.json';

    /**
     * Relative URL path to the Vite build output.
     *
     * @var string
     */
    const DIST_URL_PATH = 'dist/';


    /**
     * Get all assets for a Vite entry source.
     *
     * @param string $entry_source Relative entry source, e.g. src/entries/settings.js.
     * @return array<string,mixed>
     */
    public static function get_entry_assets( $entry_source ) {
        $manifest = self::get_manifest();
        $entry = self::get_manifest_entry( $entry_source, $manifest );

        if ( empty( $entry ) ) {
            return array();
        }

        $styles = self::collect_css_assets( $entry, $manifest );

        return array(
            'script' => ! empty( $entry['file'] ) ? self::get_asset_url( $entry['file'] ) : '',
            'styles' => $styles,
            'imports' => ! empty( $entry['imports'] ) && is_array( $entry['imports'] ) ? $entry['imports'] : array(),
        );
    }


    /**
     * Get the manifest entry for a source file.
     *
     * @param string $entry_source Relative entry source.
     * @return array<string,mixed>
     */
    private static function get_manifest_entry( $entry_source, $manifest = null ) {
        if ( empty( $entry_source ) || ! is_string( $entry_source ) ) {
            return array();
        }

        if ( null === $manifest ) {
            $manifest = self::get_manifest();
        }

        if ( empty( $manifest ) ) {
            return array();
        }

        $normalized_source = ltrim( str_replace( '\\', '/', $entry_source ), '/' );

        if ( isset( $manifest[ $normalized_source ] ) && is_array( $manifest[ $normalized_source ] ) ) {
            return $manifest[ $normalized_source ];
        }

        foreach ( $manifest as $entry ) {
            if ( ! is_array( $entry ) ) {
                continue;
            }

            if ( isset( $entry['src'] ) && $normalized_source === ltrim( str_replace( '\\', '/', $entry['src'] ), '/' ) ) {
                return $entry;
            }
        }

        return array();
    }


    /**
     * Collect CSS assets from an entry and its imported chunks.
     *
     * @param array<string,mixed> $entry Manifest entry.
     * @param array<string,mixed> $manifest Manifest data.
     * @param array<string,bool> $visited Internal recursion guard.
     * @return array<int,string>
     */
    private static function collect_css_assets( $entry, $manifest, &$visited = array() ) {
        $styles = array();

        if ( empty( $entry ) || ! is_array( $entry ) ) {
            return $styles;
        }

        if ( ! empty( $entry['file'] ) ) {
            $visited_key = $entry['file'];

            if ( isset( $visited[ $visited_key ] ) ) {
                return $styles;
            }

            $visited[ $visited_key ] = true;
        }

        if ( ! empty( $entry['css'] ) && is_array( $entry['css'] ) ) {
            foreach ( $entry['css'] as $css_file ) {
                $styles[] = self::get_asset_url( $css_file );
            }
        }

        if ( empty( $entry['imports'] ) || ! is_array( $entry['imports'] ) ) {
            return array_values( array_unique( $styles ) );
        }

        foreach ( $entry['imports'] as $import_key ) {
            if ( empty( $manifest[ $import_key ] ) || ! is_array( $manifest[ $import_key ] ) ) {
                continue;
            }

            $styles = array_merge( $styles, self::collect_css_assets( $manifest[ $import_key ], $manifest, $visited ) );
        }

        return array_values( array_unique( $styles ) );
    }


    /**
     * Decode the manifest once and cache it.
     *
     * @return array<string,mixed>
     */
    private static function get_manifest() {
        static $manifest = null;

        if ( null !== $manifest ) {
            return $manifest;
        }

        $manifest = array();
        $manifest_path = self::get_manifest_path();

        if ( ! file_exists( $manifest_path ) || ! is_readable( $manifest_path ) ) {
            return $manifest;
        }

        $manifest_content = file_get_contents( $manifest_path );

        if ( false === $manifest_content || '' === trim( $manifest_content ) ) {
            return $manifest;
        }

        $decoded_manifest = json_decode( $manifest_content, true );

        if ( is_array( $decoded_manifest ) ) {
            $manifest = $decoded_manifest;
        }

        return $manifest;
    }


    /**
     * Get the absolute manifest file path.
     *
     * @return string
     */
    private static function get_manifest_path() {
        return trailingslashit( JOINOTIFY_DIR ) . self::MANIFEST_PATH;
    }


    /**
     * Build a public URL for a manifest asset.
     *
     * @param string $relative_path Manifest asset path.
     * @return string
     */
    private static function get_asset_url( $relative_path ) {
        return trailingslashit( JOINOTIFY_URL ) . self::DIST_URL_PATH . ltrim( $relative_path, '/' );
    }
}
