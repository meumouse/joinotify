<?php

namespace MeuMouse\Joinotify\API;

// Exit if accessed directly.
defined('ABSPATH') || exit;

/**
 * Get workflow templates for import on builder
 * 
 * @since 1.0.0
 * @version 1.1.0
 * @package MeuMouse.com
 */
class Workflow_Templates {

    /**
     * Get JSON files on Joinotify repository
     *
     * @since 1.0.0
     * @param string $owner | The repository owner
     * @param string $repository | The repository name
     * @param string $path | Path for repository
     * @param string $ref | The branch name or commit hash
     * @param string $token | (Optional) Access token for auth requests
     *
     * @return array An associative array where the keys are the names of the files and values are the JSON content
     */
    public static function get_templates( $owner, $repository, $path, $ref = 'main', $token = null ) {
        $api_url = "https://api.github.com/repos/$owner/$repository/contents/$path?ref=$ref";

        $ch = curl_init( $api_url );
        curl_setopt( $ch, CURLOPT_RETURNTRANSFER, true );
        // The Github API requires a user agent
        curl_setopt( $ch, CURLOPT_USERAGENT, 'Joinotify' );

        if ( $token ) {
            curl_setopt( $ch, CURLOPT_HTTPHEADER, array( 'Authorization: token ' . $token ) );
        }

        $response = curl_exec( $ch );
        $http_code = curl_getinfo( $ch, CURLINFO_HTTP_CODE );

        if ( $http_code != 200 ) {
            // Handle error
            curl_close( $ch );
            return array();
        }

        $directory_contents = json_decode( $response, true );
        curl_close( $ch );

        $json_files = array();

        foreach ( $directory_contents as $item ) {
            if ( $item['type'] == 'file' && pathinfo( $item['name'], PATHINFO_EXTENSION ) === 'json' ) {
                // Download file content
                $file_content = file_get_contents( $item['download_url'] );

                if ( $file_content !== false ) {
                    $json_files[ $item['name'] ] = $file_content;
                }
            }
        }

        return $json_files;
    }


    /**
     * Get list of JSON template file names on Joinotify repository without downloading the content
     *
     * @since 1.0.1
     * @param string $owner | The repository owner
     * @param string $repository | The repository name
     * @param string $path | Path for repository
     * @param string $ref | The branch name or commit hash
     * @param string $token | (Optional) Access token for auth requests
     *
     * @return array An array of file names of the JSON templates
     */
    public static function get_templates_list( $owner, $repository, $path, $ref = 'main', $token = null ) {
        // set uniq cache key based on params
        $transient_key = 'joinotify_templates_list_' . md5( $owner . $repository . $path . $ref );

        // try get cache
        $cached_list = get_transient( $transient_key );

        if ( false !== $cached_list ) {
            return $cached_list;
        }
        
        $api_url = "https://api.github.com/repos/$owner/$repository/contents/$path?ref=$ref";

        $ch = curl_init( $api_url );
        curl_setopt( $ch, CURLOPT_RETURNTRANSFER, true );
        // The Github API requires a user agent
        curl_setopt( $ch, CURLOPT_USERAGENT, 'Joinotify' );

        if ( $token ) {
            curl_setopt( $ch, CURLOPT_HTTPHEADER, array( 'Authorization: token ' . $token ) );
        }

        $response = curl_exec( $ch );
        $http_code = curl_getinfo( $ch, CURLINFO_HTTP_CODE );
        curl_close( $ch );

        if ( $http_code != 200 ) {
            // Handle error
            return null;
        }

        $directory_contents = json_decode( $response, true );

        if ( ! is_array( $directory_contents ) ) {
            // Lidar com erro
            return null;
        }
    
        $template_files = array();

        foreach ( $directory_contents as $item ) {
            if ( $item['type'] == 'file' && pathinfo( $item['name'], PATHINFO_EXTENSION ) === 'json' ) {
                $template_files[] = $item['name'];
            }
        }

        // storage templates list in cache for 1 day
        set_transient( $transient_key, $template_files, DAY_IN_SECONDS );

        return $template_files;
    }


    /**
     * Get the count of JSON templates available in the repository
     *
     * @since 1.0.1
     * @param string $owner | The repository owner
     * @param string $repository | The repository name
     * @param string $path | Path for repository
     * @param string $ref | The branch name or commit hash
     * @param string $token | (Optional) Access token for auth requests
     *
     * @return int The number of JSON templates available
     */
    public static function get_templates_count( $owner, $repository, $path, $ref = 'main', $token = null ) {
        $template_files = self::get_templates_list( $owner, $repository, $path, $ref, $token );

        return count( $template_files );
    }
}