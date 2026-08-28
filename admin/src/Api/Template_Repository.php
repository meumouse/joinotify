<?php

namespace MeuMouse\Joinotify\Api;

use MeuMouse\Joinotify\Core\Helpers;

// Exit if accessed directly.
defined('ABSPATH') || exit;

/**
 * Read side of the WhatsApp message templates owned by the customer's account.
 *
 * Templates belong to a business account (WABA), not to a number, and are the
 * only way to write to someone outside the 24-hour window. The API already
 * serves them from a mirror it keeps fresh by webhook, so this layer only adds
 * a local cache (query routes are capped at 60 requests per minute) and the
 * shape the builder needs: the body preview and the list of variables each
 * template expects.
 *
 * @since 2.3.0
 * @package MeuMouse\Joinotify\Api
 * @author MeuMouse.com
 */
class Template_Repository {

    /**
     * Transient prefix holding a normalized listing per business account.
     *
     * @var string
     */
    const CACHE_PREFIX = 'joinotify_cloud_templates_';

    /**
     * How long a successful listing is served locally.
     *
     * Matches the 15 minutes the API itself uses before refreshing its mirror.
     *
     * @var int
     */
    const CACHE_TTL = 900;

    /**
     * Templates asked for per request.
     *
     * The API caps the page at 250; asking for it keeps the picker complete for
     * accounts with many templates, which would otherwise be cut at the default
     * page of 100 with no visible sign.
     *
     * @var int
     */
    const PAGE_SIZE = 250;


    /**
     * List the templates of a business account.
     *
     * @since 2.3.0
     * @param array $args | Optional `status`, `waba_id` and `force`.
     * @return array|\WP_Error {
     *     @type array  $templates  Normalized templates.
     *     @type string $waba_id    Business account the list came from.
     *     @type string $synced_at  When the API mirror was last refreshed.
     *     @type bool   $stale      True when the mirror could not be refreshed.
     *     @type string $sync_error Reason the refresh failed, when it did.
     * }
     */
    public static function get_templates( $args = array() ) {
        $waba_id = isset( $args['waba_id'] ) ? (string) $args['waba_id'] : '';
        $force = ! empty( $args['force'] );
        $cache_key = self::CACHE_PREFIX . md5( $waba_id );

        if ( ! $force ) {
            $cached = get_transient( $cache_key );

            if ( is_array( $cached ) ) {
                return $cached;
            }
        }

        if ( ! Helpers::cloud_api_ready() ) {
            return new \WP_Error( 'joinotify_cloud_no_token', __( 'Connect your Joinotify account to load message templates.', 'joinotify' ) );
        }

        $response = Cloud_Client::list_templates( array(
            'status' => $args['status'] ?? '',
            'waba_id' => $waba_id,
            // The API pages at 100 by default; ask for the cap so a big account
            // does not silently lose templates from the picker.
            'limit' => self::PAGE_SIZE,
            // The mirror maintains itself; only ask the API to hit Meta when the
            // user explicitly asked for fresh data.
            'refresh' => $force,
        ) );

        if ( is_wp_error( $response ) ) {
            return $response;
        }

        if ( ! is_array( $response ) ) {
            return new \WP_Error( 'joinotify_cloud_bad_response', __( 'The templates could not be read.', 'joinotify' ) );
        }

        if ( isset( $response['error'] ) ) {
            $message = $response['error']['message'] ?? __( 'The templates could not be read.', 'joinotify' );

            return new \WP_Error( 'joinotify_cloud_templates_failed', $message );
        }

        $templates = array();

        foreach ( (array) ( $response['data'] ?? array() ) as $template ) {
            if ( is_array( $template ) ) {
                $templates[] = self::normalize( $template );
            }
        }

        $result = array(
            'templates' => $templates,
            // What the account holds, so a listing cut by the page cap is visible.
            'total' => isset( $response['total'] ) ? (int) $response['total'] : count( $templates ),
            'waba_id' => (string) ( $response['wabaId'] ?? $waba_id ),
            'synced_at' => (string) ( $response['syncedAt'] ?? '' ),
            'stale' => ! empty( $response['stale'] ),
            'sync_error' => (string) ( $response['syncError'] ?? '' ),
        );

        // Cache successful, non-empty listings only, so an outage never poisons
        // the picker with an empty catalogue.
        if ( ! empty( $templates ) ) {
            set_transient( $cache_key, $result, self::CACHE_TTL );
        }

        return $result;
    }


    /**
     * Find one synced template by name.
     *
     * Reads the local cache only: callers use it on the send path, where a
     * round trip to the API would delay the message for a detail the cache
     * almost always has.
     *
     * @since 2.3.0
     * @param string $name | Template name as approved on Meta.
     * @return array|null Normalized template, or null when it is not cached.
     */
    public static function find( $name ) {
        $name = trim( (string) $name );

        if ( '' === $name ) {
            return null;
        }

        $cached = get_transient( self::CACHE_PREFIX . md5( '' ) );

        if ( ! is_array( $cached ) || empty( $cached['templates'] ) ) {
            return null;
        }

        foreach ( (array) $cached['templates'] as $template ) {
            if ( is_array( $template ) && isset( $template['name'] ) && $template['name'] === $name ) {
                return $template;
            }
        }

        return null;
    }


    /**
     * Ask the API to reconcile its mirror with Meta and drop the local cache.
     *
     * @since 2.3.0
     * @return array|\WP_Error
     */
    public static function sync() {
        $response = Cloud_Client::sync_templates();

        if ( is_wp_error( $response ) ) {
            return $response;
        }

        self::flush_cache();

        return is_array( $response ) ? $response : array();
    }


    /**
     * Drop every cached listing.
     *
     * @since 2.3.0
     * @return void
     */
    public static function flush_cache() {
        global $wpdb;

        $like = $wpdb->esc_like( '_transient_' . self::CACHE_PREFIX ) . '%';
        $timeout_like = $wpdb->esc_like( '_transient_timeout_' . self::CACHE_PREFIX ) . '%';

        // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching -- Transient names are only discoverable by pattern, so there is no delete_transient() equivalent; this call is itself the cache invalidation.
        $wpdb->query( $wpdb->prepare( "DELETE FROM {$wpdb->options} WHERE option_name LIKE %s OR option_name LIKE %s", $like, $timeout_like ) );
    }


    /**
     * Reduce an API template to what the builder needs.
     *
     * @since 2.3.0
     * @param array $template | Raw template from the API.
     * @return array
     */
    protected static function normalize( $template ) {
        $components = isset( $template['components'] ) && is_array( $template['components'] ) ? $template['components'] : array();

        return array(
            'id' => (string) ( $template['id'] ?? '' ),
            'name' => (string) ( $template['name'] ?? '' ),
            'language' => (string) ( $template['language'] ?? '' ),
            'status' => strtoupper( (string) ( $template['status'] ?? '' ) ),
            'category' => strtoupper( (string) ( $template['category'] ?? '' ) ),
            'quality' => strtoupper( (string) ( $template['quality_score']['score'] ?? '' ) ),
            'rejected_reason' => (string) ( $template['rejected_reason'] ?? '' ),
            'header' => self::component_text( $components, 'HEADER' ),
            'body' => self::component_text( $components, 'BODY' ),
            'footer' => self::component_text( $components, 'FOOTER' ),
            'variables' => self::extract_variables( $components ),
        );
    }


    /**
     * Read the text of a component by type.
     *
     * @since 2.3.0
     * @param array  $components | Template components.
     * @param string $type | Component type (HEADER, BODY, FOOTER).
     * @return string
     */
    protected static function component_text( $components, $type ) {
        foreach ( $components as $component ) {
            if ( ! is_array( $component ) ) {
                continue;
            }

            if ( strtoupper( (string) ( $component['type'] ?? '' ) ) === $type ) {
                return (string) ( $component['text'] ?? '' );
            }
        }

        return '';
    }


    /**
     * List every variable the template expects, in the order Meta wants them.
     *
     * Both dialects Meta accepts are read: positional (`{{1}}`) and named
     * (`{{nome}}`). The `key` is what goes back in the parameter object, and the
     * `component` plus `index` say where the value belongs when the send payload
     * is assembled.
     *
     * @since 2.3.0
     * @param array $components | Template components.
     * @return array
     */
    protected static function extract_variables( $components ) {
        $variables = array();

        foreach ( $components as $component ) {
            if ( ! is_array( $component ) ) {
                continue;
            }

            $type = strtolower( (string) ( $component['type'] ?? '' ) );

            if ( 'buttons' === $type ) {
                foreach ( (array) ( $component['buttons'] ?? array() ) as $index => $button ) {
                    if ( ! is_array( $button ) || 'URL' !== strtoupper( (string) ( $button['type'] ?? '' ) ) ) {
                        continue;
                    }

                    foreach ( self::tokens_in( (string) ( $button['url'] ?? '' ) ) as $token ) {
                        $variables[] = array(
                            'component' => 'button',
                            'sub_type' => 'url',
                            'index' => (int) $index,
                            'key' => $token,
                            'label' => (string) ( $button['text'] ?? '' ),
                        );
                    }
                }

                continue;
            }

            // Footers cannot carry variables, and headers are limited to one.
            if ( ! in_array( $type, array( 'header', 'body' ), true ) ) {
                continue;
            }

            foreach ( self::tokens_in( (string) ( $component['text'] ?? '' ) ) as $token ) {
                $variables[] = array(
                    'component' => $type,
                    'sub_type' => '',
                    'index' => 0,
                    'key' => $token,
                    'label' => '',
                );
            }
        }

        return $variables;
    }


    /**
     * Pull the `{{...}}` tokens out of a string, preserving order and dropping
     * repeats.
     *
     * @since 2.3.0
     * @param string $text | Component text.
     * @return string[]
     */
    protected static function tokens_in( $text ) {
        if ( ! preg_match_all( '/\{\{\s*([A-Za-z0-9_]+)\s*\}\}/', $text, $matches ) ) {
            return array();
        }

        return array_values( array_unique( $matches[1] ) );
    }
}
