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
     * Delete the cached API request/response pair so the next call goes out to
     * the remote server instead of being answered from cache.
     *
     * @since 1.4.7
     * @version 2.4.0
     * @return void
     */
    public static function clear_license_cache() {
        delete_transient( 'joinotify_api_request_cache' );
        delete_transient( 'joinotify_api_response_cache' );
    }
}
