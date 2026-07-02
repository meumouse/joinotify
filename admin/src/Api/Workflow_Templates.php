<?php

namespace MeuMouse\Joinotify\Api;

// Exit if accessed directly.
defined('ABSPATH') || exit;

/**
 * Fetch workflow templates from the Joinotify Templates API.
 *
 * Replaces the previous GitHub Contents API integration (which downloaded the
 * JSON files from `meumouse/joinotify/dist/templates`) with the dedicated
 * templates service served at https://templates.joinotify.com. The service
 * exposes a paginated catalog of published templates and per-template JSON
 * content using the same `{ plugin_version, post, workflow_content }` contract
 * the builder exports/imports.
 *
 * @since 1.0.0
 * @version 2.0.0
 * @package MeuMouse\Joinotify\API
 * @author MeuMouse.com
 */
class Workflow_Templates {

    /**
     * Default base URL of the Joinotify Templates API (no trailing slash).
     *
     * Override with the JOINOTIFY_TEMPLATES_API_URL constant to point at a
     * different environment (e.g. http://localhost:3333 in development).
     *
     * @since 2.0.0
     * @var string
     */
    const DEFAULT_API_URL = 'https://templates.joinotify.com';

    /**
     * Items requested per catalog page. The API hard-caps `per_page` at 100.
     *
     * @since 2.0.0
     * @var int
     */
    const PER_PAGE = 100;

    /**
     * Safety cap on the number of catalog pages walked in a single fetch.
     *
     * @since 2.0.0
     * @var int
     */
    const MAX_PAGES = 50;

    /**
     * Transient key for the cached catalog list.
     *
     * @since 2.0.0
     * @var string
     */
    const CATALOG_CACHE_KEY = 'joinotify_templates_catalog';

    /**
     * Transient key prefix for cached per-template content.
     *
     * @since 2.0.0
     * @var string
     */
    const TEMPLATE_CACHE_PREFIX = 'joinotify_template_';


    /**
     * Resolve the base URL of the templates API (no trailing slash).
     *
     * @since 2.0.0
     * @return string
     */
    public static function get_base_url() {
        $url = defined('JOINOTIFY_TEMPLATES_API_URL') && JOINOTIFY_TEMPLATES_API_URL
            ? JOINOTIFY_TEMPLATES_API_URL
            : self::DEFAULT_API_URL;

        /**
         * Filter the Joinotify Templates API base URL.
         *
         * @since 2.0.0
         * @param string $url Base URL without trailing slash.
         */
        $url = apply_filters( 'Joinotify/Api/Templates_Base_Url', $url );

        return untrailingslashit( (string) $url );
    }


    /**
     * Cache lifetime for catalog/content responses, in seconds.
     *
     * @since 2.0.0
     * @return int
     */
    protected static function cache_ttl() {
        /**
         * Filter the cache lifetime (in seconds) for templates API responses.
         *
         * @since 2.0.0
         * @param int $ttl Lifetime in seconds. Default 1 hour.
         */
        return (int) apply_filters( 'Joinotify/Api/Templates_Cache_Ttl', HOUR_IN_SECONDS );
    }


    /**
     * Perform a GET request against the templates API and decode the JSON body.
     *
     * @since 2.0.0
     * @param string $path Path starting with a slash (e.g. `/v1/templates`).
     * @param array<string,mixed> $query Optional query args appended to the URL.
     * @return array<string,mixed>|null Decoded body, or null on transport/HTTP error.
     */
    protected static function request( $path, $query = array() ) {
        $url = self::get_base_url() . '/v1' . $path;

        if ( ! empty( $query ) ) {
            // add_query_arg() url-encodes the values for us.
            $url = add_query_arg( $query, $url );
        }

        $response = wp_remote_get( $url, array(
            'timeout' => 15,
            'headers' => array(
                'Accept' => 'application/json',
                'User-Agent' => 'Joinotify/' . ( defined('JOINOTIFY_VERSION') ? JOINOTIFY_VERSION : '' ),
            ),
        ));

        if ( is_wp_error( $response ) ) {
            return null;
        }

        if ( 200 !== (int) wp_remote_retrieve_response_code( $response ) ) {
            return null;
        }

        $decoded = json_decode( wp_remote_retrieve_body( $response ), true );

        return is_array( $decoded ) ? $decoded : null;
    }


    /**
     * Get the full published template catalog (metadata only).
     *
     * Walks every page of `GET /v1/templates` and returns the flattened list of
     * catalog items. Each item carries `id`, `file` (`{id}.json`), `title`,
     * `description`, `category`, `trigger`, `tags`, `status`, `min_plugin_version`,
     * `downloads`, `version` and `checksum`. The result is cached in a transient.
     *
     * @since 2.0.0
     * @param bool $force Bypass the cache and refetch.
     * @return array<int,array<string,mixed>>
     */
    public static function get_catalog( $force = false ) {
        if ( ! $force ) {
            $cached = get_transient( self::CATALOG_CACHE_KEY );

            if ( is_array( $cached ) ) {
                return $cached;
            }
        }

        $items = array();
        $page = 1;

        do {
            $response = self::request( '/templates', array(
                'page' => (string) $page,
                'per_page' => (string) self::PER_PAGE,
            ));

            if ( ! is_array( $response ) || ! isset( $response['items'] ) || ! is_array( $response['items'] ) ) {
                // On the first page a failure means we have nothing to cache; on
                // later pages we keep whatever we already collected.
                break;
            }

            foreach ( $response['items'] as $item ) {
                if ( is_array( $item ) ) {
                    $items[] = $item;
                }
            }

            $total_pages = isset( $response['pagination']['total_pages'] ) ? (int) $response['pagination']['total_pages'] : 1;
            $page++;
        } while ( $page <= $total_pages && $page <= self::MAX_PAGES );

        // Only cache successful, non-empty fetches so a transient outage doesn't
        // pin an empty catalog for the whole TTL.
        if ( ! empty( $items ) ) {
            set_transient( self::CATALOG_CACHE_KEY, $items, self::cache_ttl() );
        }

        return $items;
    }


    /**
     * Get the number of published templates.
     *
     * @since 1.0.1
     * @version 2.0.0
     * @return int|null Count, or null when the request fails.
     */
    public static function get_templates_count() {
        $response = self::request( '/templates/count' );

        if ( ! is_array( $response ) || ! isset( $response['count'] ) ) {
            return null;
        }

        return (int) $response['count'];
    }


    /**
     * Get the full JSON of a single template by its identifier.
     *
     * Accepts either the raw UUID or the catalog `file` value (`{id}.json`).
     * Returns the builder-compatible template payload
     * (`{ plugin_version, post, workflow_content }`), cached in a transient.
     *
     * @since 2.0.0
     * @param string $id_or_file Template UUID or `{id}.json` filename.
     * @param bool $force Bypass the cache and refetch.
     * @return array<string,mixed>|null Decoded template, or null when not found.
     */
    public static function get_template( $id_or_file, $force = false ) {
        $id = self::normalize_id( $id_or_file );

        if ( '' === $id ) {
            return null;
        }

        $cache_key = self::TEMPLATE_CACHE_PREFIX . md5( $id );

        if ( ! $force ) {
            $cached = get_transient( $cache_key );

            if ( is_array( $cached ) ) {
                return $cached;
            }
        }

        $response = self::request( '/templates/' . rawurlencode( $id ) );

        if ( ! is_array( $response ) || ! isset( $response['template'] ) || ! is_array( $response['template'] ) ) {
            return null;
        }

        set_transient( $cache_key, $response['template'], self::cache_ttl() );

        return $response['template'];
    }


    /**
     * Normalize a catalog `file` value (`{id}.json`) down to the template UUID.
     *
     * @since 2.0.0
     * @param string $id_or_file Template UUID or `{id}.json` filename.
     * @return string Sanitized identifier, or an empty string when invalid.
     */
    protected static function normalize_id( $id_or_file ) {
        $id = trim( (string) $id_or_file );
        $id = preg_replace( '/\.json$/i', '', $id );

        // The public identifier is a UUID; reject anything else so it can't be
        // used to build arbitrary request paths.
        if ( ! preg_match( '/^[0-9a-f]{8}-[0-9a-f]{4}-[0-9a-f]{4}-[0-9a-f]{4}-[0-9a-f]{12}$/i', (string) $id ) ) {
            return '';
        }

        return strtolower( $id );
    }


    /**
     * Flush the cached catalog and template content.
     *
     * @since 2.0.0
     * @return void
     */
    public static function flush_cache() {
        delete_transient( self::CATALOG_CACHE_KEY );
    }
}
