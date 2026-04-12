<?php
/**
 * Cache source file.
 *
 * @since 1.4.7
 * @version 1.4.7
 */

namespace MeuMouse\Joinotify\Core;

// Exit if accessed directly.
defined('ABSPATH') || exit;

/**
 * Cache control class
 * 
 * @since 1.4.2
 * @package MeuMouse\Joinotify\Core
 * @author MeuMouse.com
 */
class Cache {

    /**
     * Constructor
     * 
     * @since 1.4.2
     * @return void
     */
    public function __construct() {
        // clear cache after validate OTP code
        add_action( 'Joinotify/Validate_Phone/Success', array( $this, 'clear_server_details_cache' ), 10, 1 );

        // clear cache after removed phone
        add_action( 'Joinotify/Remove_Phone/Success', array( $this, 'clear_server_details_cache' ), 10, 1 );
    }


    /**
     * Clear server details cache
     * 
     * @since 1.4.2
     * @param string $phone | Validated phone number
     * @return bool
     */
    public function clear_server_details_cache( $phone ) {
        $cache_key = 'joinotify_server_details_' . md5( $phone );
        $deleted = delete_transient( $cache_key );

        if ( $deleted ) {
            return true;
        }

        return false;
    }
}