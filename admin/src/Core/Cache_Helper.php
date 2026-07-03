<?php

namespace MeuMouse\Joinotify\Core;

defined('ABSPATH') || exit;

/**
 * Helpers for clearing plugin transient caches.
 *
 * @since 1.4.7
 * @package MeuMouse\Joinotify\Core
 */
class Cache_Helper {

    /**
     * Delete all three license-related transients so the next request
     * fetches a fresh response from the remote server.
     *
     * @since 1.4.7
     * @return void
     */
    public static function clear_license_cache() {
        delete_transient( 'joinotify_api_request_cache' );
        delete_transient( 'joinotify_api_response_cache' );
        delete_transient( 'joinotify_license_status_cached' );
    }
}
