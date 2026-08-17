<?php

namespace MeuMouse\Joinotify\Api;

use MeuMouse\Joinotify\Core\Logger;

// Exit if accessed directly.
defined('ABSPATH') || exit;

/**
 * Fetch workflow templates from the Joinotify API.
 *
 * The catalog used to live on a dedicated host (`templates.joinotify.com`,
 * namespaced under `/v1/templates`). It is now served by the same API as the
 * rest of the account, under `/workflow-templates`:
 *
 * - `GET /workflow-templates`               paginated catalog (accepts filters)
 * - `GET /workflow-templates/count`         number of published templates
 * - `GET /workflow-templates/categories`    categories with their template count
 * - `GET /workflow-templates/{id}`          `{ meta, template }`, ETag aware
 * - `GET /workflow-templates/{id}/download` raw template JSON, counts a download
 *
 * These routes are public: unlike the rest of the API they carry no bearer
 * token. Template payloads keep the `{ plugin_version, post, workflow_content }`
 * contract the builder exports and imports.
 *
 * @since 1.0.0
 * @version 2.3.0
 * @package MeuMouse\Joinotify\API
 * @author MeuMouse.com
 */
class Workflow_Templates {

    /**
     * Default base URL of the Joinotify API (no trailing slash).
     *
     * Only used when the Cloud API client is unavailable; normally the base URL
     * is taken from Cloud_Client so a single override moves both.
     *
     * @since 2.0.0
     * @version 2.3.0
     * @var string
     */
    const DEFAULT_API_URL = 'https://api.joinotify.com';

    /**
     * Route namespace of the workflow templates service.
     *
     * @since 2.3.0
     * @var string
     */
    const ROUTE_NAMESPACE = '/workflow-templates';

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
     * Transient key for the cached category list.
     *
     * @since 2.3.0
     * @var string
     */
    const CATEGORIES_CACHE_KEY = 'joinotify_templates_categories';

    /**
     * Transient key prefix for cached per-template content.
     *
     * @since 2.0.0
     * @var string
     */
    const TEMPLATE_CACHE_PREFIX = 'joinotify_template_';

    /**
     * How long a stored template copy is kept around.
     *
     * Freshness of a stored copy comes from its checksum/ETag rather than from
     * the clock (see get_template()), so it is held well past the catalog TTL
     * to give the revalidation something to work with.
     *
     * @since 2.3.0
     * @var int
     */
    const TEMPLATE_CACHE_TTL = DAY_IN_SECONDS;


    /**
     * Resolve the base URL of the templates API (no trailing slash).
     *
     * @since 2.0.0
     * @version 2.3.0
     * @return string
     */
    public static function get_base_url() {
        if ( defined('JOINOTIFY_TEMPLATES_API_URL') && JOINOTIFY_TEMPLATES_API_URL ) {
            $url = JOINOTIFY_TEMPLATES_API_URL;
        } else {
            // The catalog is served by the account API, so pointing the Cloud
            // API at another environment moves the template library with it.
            $url = class_exists( Cloud_Client::class ) ? Cloud_Client::base_url() : self::DEFAULT_API_URL;
        }

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
     * Perform a GET request against the templates API.
     *
     * @since 2.0.0
     * @version 2.3.0
     * @param string $path Path relative to the route namespace (e.g. `/count`).
     * @param array<string,mixed> $query Optional query args appended to the URL.
     * @param array<string,string> $headers Optional extra request headers.
     * @return array{code:int,body:array<string,mixed>|null,etag:string}|null Null on transport/HTTP error.
     */
    protected static function request( $path, $query = array(), $headers = array() ) {
        $url = self::get_base_url() . self::ROUTE_NAMESPACE . $path;

        if ( ! empty( $query ) ) {
            // add_query_arg() url-encodes the values for us.
            $url = add_query_arg( $query, $url );
        }

        $response = wp_remote_get( $url, array(
            'timeout' => 15,
            'headers' => array_merge( array(
                'Accept' => 'application/json',
                'User-Agent' => 'Joinotify/' . ( defined('JOINOTIFY_VERSION') ? JOINOTIFY_VERSION : '' ),
            ), $headers ),
        ));

        if ( is_wp_error( $response ) ) {
            self::log_failure( $url, $response->get_error_message() );

            return null;
        }

        $code = (int) wp_remote_retrieve_response_code( $response );
        $etag = trim( (string) wp_remote_retrieve_header( $response, 'etag' ), '"' );

        // 304 carries no body: the caller reuses the copy it already holds.
        if ( 304 === $code ) {
            return array(
                'code' => $code,
                'body' => null,
                'etag' => $etag,
            );
        }

        $body = json_decode( wp_remote_retrieve_body( $response ), true );

        if ( $code < 200 || $code >= 300 ) {
            // Failures come back as `{ error: { message, type } }`.
            $message = isset( $body['error']['message'] ) ? (string) $body['error']['message'] : 'HTTP ' . $code;

            self::log_failure( $url, $message );

            return null;
        }

        return array(
            'code' => $code,
            'body' => is_array( $body ) ? $body : null,
            'etag' => $etag,
        );
    }


    /**
     * Perform a GET request and return only the decoded JSON object.
     *
     * @since 2.3.0
     * @param string $path Path relative to the route namespace.
     * @param array<string,mixed> $query Optional query args appended to the URL.
     * @return array<string,mixed>|null Decoded body, or null on error.
     */
    protected static function get_json( $path, $query = array() ) {
        $response = self::request( $path, $query );

        return is_array( $response ) && is_array( $response['body'] ) ? $response['body'] : null;
    }


    /**
     * Record a failed request in the debug log.
     *
     * @since 2.3.0
     * @param string $url Requested URL.
     * @param string $message Transport error or API error message.
     * @return void
     */
    protected static function log_failure( $url, $message ) {
        if ( class_exists( Logger::class ) ) {
            Logger::register_log( sprintf( 'Workflow templates request failed (%s): %s', $url, $message ), 'ERROR' );
        }
    }


    /**
     * Get the full published template catalog (metadata only).
     *
     * Walks every page of `GET /workflow-templates` and returns the flattened
     * list of catalog items. Each item carries `id`, `file` (`{id}.json`),
     * `title`, `description`, `category`, `trigger`, `tags`, `status`,
     * `min_plugin_version`, `downloads`, `version` and `checksum`. The result is
     * cached in a transient.
     *
     * @since 2.0.0
     * @version 2.3.0
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
            $response = self::get_json( '', array(
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
     * @version 2.3.0
     * @return int|null Count, or null when the request fails.
     */
    public static function get_templates_count() {
        $response = self::get_json( '/count' );

        if ( ! is_array( $response ) || ! isset( $response['count'] ) ) {
            return null;
        }

        return (int) $response['count'];
    }


    /**
     * List the categories that have published templates, with their counts.
     *
     * @since 2.3.0
     * @param bool $force Bypass the cache and refetch.
     * @return array<int,array{category:string,count:int}>
     */
    public static function get_categories( $force = false ) {
        if ( ! $force ) {
            $cached = get_transient( self::CATEGORIES_CACHE_KEY );

            if ( is_array( $cached ) ) {
                return $cached;
            }
        }

        $response = self::get_json( '/categories' );
        $categories = array();

        if ( isset( $response['categories'] ) && is_array( $response['categories'] ) ) {
            foreach ( $response['categories'] as $entry ) {
                if ( ! is_array( $entry ) || empty( $entry['category'] ) ) {
                    continue;
                }

                $categories[] = array(
                    'category' => sanitize_key( (string) $entry['category'] ),
                    'count' => isset( $entry['count'] ) ? (int) $entry['count'] : 0,
                );
            }
        }

        if ( ! empty( $categories ) ) {
            set_transient( self::CATEGORIES_CACHE_KEY, $categories, self::cache_ttl() );
        }

        return $categories;
    }


    /**
     * Get the full JSON of a single template by its identifier.
     *
     * Accepts either the raw UUID or the catalog `file` value (`{id}.json`).
     * Returns the builder-compatible template payload
     * (`{ plugin_version, post, workflow_content }`).
     *
     * A stored copy is only reused while it is provably current: either its
     * ETag still matches the checksum the catalog advertises (no request at
     * all), or the API answers the conditional request with a 304. This is why
     * updating a template upstream is picked up as soon as the catalog is, with
     * no cache flush needed.
     *
     * This route does not count a download; use download_template() for the
     * import path.
     *
     * @since 2.0.0
     * @version 2.3.0
     * @param string $id_or_file Template UUID or `{id}.json` filename.
     * @param bool $force Bypass the stored copy and refetch.
     * @return array<string,mixed>|null Decoded template, or null when not found.
     */
    public static function get_template( $id_or_file, $force = false ) {
        $id = self::normalize_id( $id_or_file );

        if ( '' === $id ) {
            return null;
        }

        $cache_key = self::TEMPLATE_CACHE_PREFIX . md5( $id );
        $cached = get_transient( $cache_key );
        $has_copy = is_array( $cached ) && isset( $cached['template'] ) && is_array( $cached['template'] );
        $etag = $has_copy && ! empty( $cached['etag'] ) ? (string) $cached['etag'] : '';

        if ( ! $force && $has_copy && '' !== $etag && $etag === self::catalog_checksum( $id ) ) {
            return $cached['template'];
        }

        $headers = array();

        if ( '' !== $etag ) {
            $headers['If-None-Match'] = '"' . $etag . '"';
        }

        $response = self::request( '/' . rawurlencode( $id ), array(), $headers );

        if ( ! is_array( $response ) ) {
            // Serve the stale copy rather than failing an import on an outage.
            return $has_copy ? $cached['template'] : null;
        }

        if ( 304 === $response['code'] ) {
            return $has_copy ? $cached['template'] : null;
        }

        if ( ! isset( $response['body']['template'] ) || ! is_array( $response['body']['template'] ) ) {
            return null;
        }

        return self::store_template( $cache_key, $response['body']['template'], $response['etag'] );
    }


    /**
     * Download the raw JSON of a template, counting the download.
     *
     * This is the route the import path uses: it returns the same payload as
     * get_template() but is the one the API tallies. The response is never
     * served from a stored copy, otherwise repeat imports would go uncounted.
     *
     * @since 2.3.0
     * @param string $id_or_file Template UUID or `{id}.json` filename.
     * @return array<string,mixed>|null Decoded template, or null on failure.
     */
    public static function download_template( $id_or_file ) {
        $id = self::normalize_id( $id_or_file );

        if ( '' === $id ) {
            return null;
        }

        $response = self::request( '/' . rawurlencode( $id ) . '/download' );

        if ( ! is_array( $response ) || ! is_array( $response['body'] ) ) {
            return null;
        }

        // The download route returns the template itself, not a `{ meta, template }` envelope.
        return self::store_template( self::TEMPLATE_CACHE_PREFIX . md5( $id ), $response['body'], $response['etag'] );
    }


    /**
     * Store a freshly fetched template alongside the ETag it came with.
     *
     * @since 2.3.0
     * @param string $cache_key Transient key.
     * @param array<string,mixed> $template Template payload.
     * @param string $etag ETag advertised by the API (also the catalog checksum).
     * @return array<string,mixed> The stored template.
     */
    protected static function store_template( $cache_key, $template, $etag ) {
        set_transient( $cache_key, array(
            'template' => $template,
            'etag' => (string) $etag,
        ), self::TEMPLATE_CACHE_TTL );

        return $template;
    }


    /**
     * Read the checksum the catalog advertises for a template.
     *
     * The checksum is the sha256 of the file the API serves, and is the same
     * value it sends as the ETag, so it doubles as a freshness marker for a
     * stored copy. Only the cached catalog is consulted: fetching the whole
     * catalog to avoid a single conditional request would be a bad trade.
     *
     * @since 2.3.0
     * @param string $id Normalized template UUID.
     * @return string Checksum, or an empty string when the catalog has no entry.
     */
    protected static function catalog_checksum( $id ) {
        $catalog = get_transient( self::CATALOG_CACHE_KEY );

        if ( ! is_array( $catalog ) ) {
            return '';
        }

        foreach ( $catalog as $item ) {
            if ( ! is_array( $item ) || empty( $item['checksum'] ) ) {
                continue;
            }

            if ( self::normalize_id( $item['id'] ?? ( $item['file'] ?? '' ) ) === $id ) {
                return (string) $item['checksum'];
            }
        }

        return '';
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
     * Flush the cached catalog, categories and template content.
     *
     * @since 2.0.0
     * @version 2.3.0
     * @return void
     */
    public static function flush_cache() {
        global $wpdb;

        delete_transient( self::CATALOG_CACHE_KEY );
        delete_transient( self::CATEGORIES_CACHE_KEY );

        // Also drop the per-template content transients (keyed by md5(id)); without
        // this a flush would leave stale template JSON pinned for the whole TTL.
        $like = $wpdb->esc_like( '_transient_' . self::TEMPLATE_CACHE_PREFIX ) . '%';
        $like_timeout = $wpdb->esc_like( '_transient_timeout_' . self::TEMPLATE_CACHE_PREFIX ) . '%';

        // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching -- Transient names are only discoverable by pattern, so there is no delete_transient() equivalent; this call is itself the cache invalidation.
        $wpdb->query( $wpdb->prepare(
            "DELETE FROM {$wpdb->options} WHERE option_name LIKE %s OR option_name LIKE %s",
            $like,
            $like_timeout
        ) );
    }
}
