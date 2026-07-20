<?php

namespace MeuMouse\Joinotify\Builder;

use MeuMouse\Joinotify\Core\Logger;
use MeuMouse\Joinotify\Integrations\Woocommerce;

// Exit if accessed directly.
defined('ABSPATH') || exit;

/**
 * Resolves the attachments declared on a builder action into deliverable files
 *
 * An action stores a list of attachment items, each declaring where the file comes from.
 * This class turns that declaration into concrete files at runtime, so the notification
 * channels only ever deal with a resolved path or URL.
 *
 * @since 2.1.0
 * @package MeuMouse\Joinotify\Builder
 * @author MeuMouse.com
 */
class Attachments {

    /**
     * File picked from the WordPress media library
     */
    const SOURCE_MEDIA = 'media';

    /**
     * Arbitrary URL, which may contain placeholders
     */
    const SOURCE_URL = 'url';

    /**
     * Every downloadable file granted to the order of the current payload
     */
    const SOURCE_ORDER_DOWNLOADS = 'order_downloads';


    /**
     * Sources accepted on an attachment item
     *
     * @since 2.1.0
     * @return array<int,string>
     */
    public static function get_sources() {
        return array( self::SOURCE_MEDIA, self::SOURCE_URL, self::SOURCE_ORDER_DOWNLOADS );
    }


    /**
     * Resolve the attachment list of an action into deliverable files
     *
     * @since 2.1.0
     * @param array $attachments | Attachment items stored on the action
     * @param array $payload | Runtime trigger payload
     * @return array<int,array<string,mixed>> List of { name, path, url, size, mime, remote }
     */
    public static function resolve( $attachments, $payload = array() ) {
        if ( ! is_array( $attachments ) || empty( $attachments ) ) {
            return array();
        }

        $resolved = array();

        foreach ( $attachments as $item ) {
            if ( ! is_array( $item ) ) {
                continue;
            }

            $source = isset( $item['source'] ) ? sanitize_key( (string) $item['source'] ) : self::SOURCE_URL;

            switch ( $source ) {
                case self::SOURCE_MEDIA:
                    $files = self::resolve_media( $item );
                    break;

                case self::SOURCE_ORDER_DOWNLOADS:
                    $files = self::resolve_order_downloads( $payload );
                    break;

                default:
                    $files = self::resolve_url( $item, $payload );
                    break;
            }

            foreach ( $files as $file ) {
                // the same file can be declared twice (e.g. a product shipping a shared PDF),
                // and attaching it twice would only inflate the message
                if ( ! self::already_resolved( $resolved, $file ) ) {
                    $resolved[] = $file;
                }
            }
        }

        /**
         * Filter the resolved attachment list before it reaches the channels
         *
         * @since 2.1.0
         * @param array $resolved | Resolved files
         * @param array $attachments | Attachment items stored on the action
         * @param array $payload | Runtime trigger payload
         */
        return apply_filters( 'Joinotify/Builder/Resolve_Attachments', $resolved, $attachments, $payload );
    }


    /**
     * Resolve a file picked from the WordPress media library
     *
     * @since 2.1.0
     * @param array $item | Attachment item
     * @return array<int,array<string,mixed>>
     */
    protected static function resolve_media( $item ) {
        $attachment_id = absint( $item['attachment_id'] ?? 0 );

        if ( ! $attachment_id ) {
            // fall back to the stored URL so an item saved before the id was tracked still works
            return self::resolve_url( $item, array() );
        }

        $path = get_attached_file( $attachment_id );

        if ( ! $path || ! file_exists( $path ) ) {
            Logger::register_log( sprintf( 'Joinotify: media attachment %d has no file on disk.', $attachment_id ), 'WARNING' );

            return array();
        }

        return array( self::build_local_file( $path, (string) ( $item['name'] ?? '' ) ) );
    }


    /**
     * Resolve an arbitrary URL, replacing any placeholder it contains
     *
     * @since 2.1.0
     * @param array $item | Attachment item
     * @param array $payload | Runtime trigger payload
     * @return array<int,array<string,mixed>>
     */
    protected static function resolve_url( $item, $payload ) {
        $url = trim( (string) ( $item['url'] ?? '' ) );

        if ( '' === $url ) {
            return array();
        }

        $url = Placeholders::replace_placeholders( $url, $payload );

        if ( '' === trim( $url ) ) {
            return array();
        }

        // A WooCommerce permission link consumes one of the customer downloads when it is
        // fetched, so sending it to a remote API would burn their own quota. Resolve it
        // through the order instead, which yields the same files without spending anything.
        if ( self::is_download_permission_url( $url ) ) {
            Logger::register_log( 'Joinotify: a download permission link was used as an attachment URL. Resolving it through the order downloads to avoid consuming the customer download limit.', 'WARNING' );

            return self::resolve_order_downloads( $payload );
        }

        return array( self::build_file_from_path( $url, (string) ( $item['name'] ?? '' ) ) );
    }


    /**
     * Resolve every downloadable file granted to the order of the payload
     *
     * @since 2.1.0
     * @param array $payload | Runtime trigger payload
     * @return array<int,array<string,mixed>>
     */
    protected static function resolve_order_downloads( $payload ) {
        if ( ! isset( $payload['order_id'] ) || ! function_exists('wc_get_order') ) {
            return array();
        }

        $order = wc_get_order( $payload['order_id'] );

        // refunds hold no download permission of their own
        if ( $order instanceof \WC_Order_Refund ) {
            $order = wc_get_order( $order->get_parent_id() );
        }

        if ( ! $order ) {
            return array();
        }

        $files = array();

        foreach ( Woocommerce::get_downloadable_items( $order ) as $item ) {
            $stored_file = $item['file']['file'] ?? '';

            if ( ! is_string( $stored_file ) || '' === $stored_file ) {
                continue;
            }

            $file = self::build_file_from_path( $stored_file, (string) ( $item['file']['name'] ?? '' ) );

            // keep the permission link so a channel that cannot carry the file itself
            // (size limits, unsupported type) can still point the customer at it
            $file['link'] = (string) ( $item['download_url'] ?? '' );

            $files[] = $file;
        }

        return array_values( array_filter( $files ) );
    }


    /**
     * Build a resolved file entry from a stored WooCommerce file reference or a URL
     *
     * Delegates to WC_Download_Handler::parse_file_path() so the resolution matches exactly
     * what the customer would get when downloading the file themselves.
     *
     * @since 2.1.0
     * @param string $file | Stored file reference, absolute path or URL
     * @param string $name | Preferred file name
     * @return array<string,mixed>
     */
    protected static function build_file_from_path( $file, $name = '' ) {
        if ( class_exists('\WC_Download_Handler') ) {
            $parsed = \WC_Download_Handler::parse_file_path( $file );

            if ( empty( $parsed['remote_file'] ) && ! empty( $parsed['file_path'] ) && file_exists( $parsed['file_path'] ) ) {
                return self::build_local_file( $parsed['file_path'], $name );
            }

            $file = ! empty( $parsed['file_path'] ) ? $parsed['file_path'] : $file;
        }

        // still a local path when WooCommerce is unavailable but the file exists as given
        if ( ! preg_match( '#^https?://#i', $file ) && file_exists( $file ) ) {
            return self::build_local_file( $file, $name );
        }

        return self::build_remote_file( $file, $name );
    }


    /**
     * Build a resolved entry for a file available on disk
     *
     * @since 2.1.0
     * @param string $path | Absolute path of the file
     * @param string $name | Preferred file name
     * @return array<string,mixed>
     */
    protected static function build_local_file( $path, $name = '' ) {
        $name = '' !== $name ? $name : basename( $path );
        $size = @filesize( $path );

        return array(
            'name' => self::normalize_file_name( $name, $path ),
            'path' => $path,
            'url' => '',
            'size' => is_int( $size ) ? $size : 0,
            'mime' => self::detect_mime( $name, $path ),
            'remote' => false,
        );
    }


    /**
     * Build a resolved entry for a file hosted elsewhere
     *
     * @since 2.1.0
     * @param string $url | URL of the file
     * @param string $name | Preferred file name
     * @return array<string,mixed>
     */
    protected static function build_remote_file( $url, $name = '' ) {
        $path = (string) wp_parse_url( $url, PHP_URL_PATH );
        $name = '' !== $name ? $name : basename( $path );

        return array(
            'name' => self::normalize_file_name( $name, $path ),
            'path' => '',
            // escaped here rather than on save, because a URL coming from a placeholder is
            // only a real URL once the token has been resolved
            'url' => esc_url_raw( $url ),
            'size' => 0,
            'mime' => self::detect_mime( $name, $path ),
            'remote' => true,
        );
    }


    /**
     * Read the contents of a resolved file, downloading it first when it is remote
     *
     * @since 2.1.0
     * @param array $file | Resolved file entry
     * @return string|false Raw file contents, or false when unavailable
     */
    public static function get_contents( $file ) {
        if ( ! empty( $file['path'] ) && file_exists( $file['path'] ) ) {
            return @file_get_contents( $file['path'] );
        }

        if ( empty( $file['url'] ) ) {
            return false;
        }

        $response = wp_remote_get( $file['url'], array( 'timeout' => 30 ) );

        if ( is_wp_error( $response ) || 200 !== (int) wp_remote_retrieve_response_code( $response ) ) {
            Logger::register_log( sprintf( 'Joinotify: could not download the attachment %s.', $file['url'] ), 'WARNING' );

            return false;
        }

        return wp_remote_retrieve_body( $response );
    }


    /**
     * Sum the size of the resolved files that are known upfront
     *
     * Remote files report zero because their size is only known after downloading them.
     *
     * @since 2.1.0
     * @param array $files | Resolved file entries
     * @return int Total size in bytes
     */
    public static function total_size( $files ) {
        $total = 0;

        foreach ( $files as $file ) {
            $total += absint( $file['size'] ?? 0 );
        }

        return $total;
    }


    /**
     * Whether a resolved file is already present in the list
     *
     * @since 2.1.0
     * @param array $resolved | Files resolved so far
     * @param array $file | Candidate file
     * @return bool
     */
    protected static function already_resolved( $resolved, $file ) {
        foreach ( $resolved as $existing ) {
            if ( ! empty( $file['path'] ) && ( $existing['path'] ?? '' ) === $file['path'] ) {
                return true;
            }

            if ( ! empty( $file['url'] ) && ( $existing['url'] ?? '' ) === $file['url'] ) {
                return true;
            }
        }

        return false;
    }


    /**
     * Whether the URL is a WooCommerce download permission link
     *
     * @since 2.1.0
     * @param string $url | URL to inspect
     * @return bool
     */
    protected static function is_download_permission_url( $url ) {
        $query = (string) wp_parse_url( $url, PHP_URL_QUERY );

        if ( '' === $query ) {
            return false;
        }

        parse_str( $query, $args );

        return isset( $args['download_file'] ) && isset( $args['key'] );
    }


    /**
     * Ensure the file name is safe and carries an extension
     *
     * @since 2.1.0
     * @param string $name | Candidate file name
     * @param string $path | Path or URL the file came from
     * @return string
     */
    protected static function normalize_file_name( $name, $path ) {
        $name = sanitize_file_name( $name );

        if ( '' === $name ) {
            $name = sanitize_file_name( basename( $path ) );
        }

        // the download name stored by WooCommerce is a label, so it often has no extension
        if ( '' !== $name && ! pathinfo( $name, PATHINFO_EXTENSION ) ) {
            $extension = pathinfo( $path, PATHINFO_EXTENSION );

            if ( $extension ) {
                $name .= '.' . $extension;
            }
        }

        return '' !== $name ? $name : 'attachment';
    }


    /**
     * Detect the mime type of a file
     *
     * @since 2.1.0
     * @param string $name | File name
     * @param string $path | Path or URL the file came from
     * @return string
     */
    protected static function detect_mime( $name, $path ) {
        $checked = wp_check_filetype( '' !== $name ? $name : basename( $path ) );

        return ! empty( $checked['type'] ) ? (string) $checked['type'] : 'application/octet-stream';
    }
}
