<?php

namespace MeuMouse\Joinotify\Admin\Settings;

use MeuMouse\Joinotify\Builder\Custom_Variables;
use MeuMouse\Joinotify\Core\Phone_Manager;

defined('ABSPATH') || exit;

/**
 * Export and import Joinotify settings as a portable JSON payload.
 *
 * The payload bundles the plugin settings (joinotify_settings), the builder
 * custom variables and the registered phone senders. Sensitive credentials are
 * stripped on export so the file can be shared safely; on import the data is
 * merged into the current configuration (never a destructive replace).
 *
 * @since 2.0.0
 * @package MeuMouse\Joinotify\Admin\Settings
 * @author MeuMouse.com
 */
class Transfer {

    /**
     * Payload type marker used to validate imported files.
     *
     * @var string
     */
    const PAYLOAD_TYPE = 'joinotify_settings_export';

    /**
     * Export schema version. Bump when the payload structure changes.
     *
     * @var int
     */
    const SCHEMA_VERSION = 1;


    /**
     * Setting keys holding credentials that must never be exported.
     *
     * @since 2.0.0
     * @return string[]
     */
    public static function get_sensitive_keys() {
        /**
         * Filter the settings keys excluded from the export payload.
         *
         * @since 2.0.0
         * @param string[] $keys Sensitive setting keys.
         */
        return apply_filters( 'Joinotify/Admin/Settings/Transfer/Sensitive_Keys', array(
            'proxy_api_key',
            'openai_api_key',
        ) );
    }


    /**
     * Build the export payload.
     *
     * @since 2.0.0
     * @return array<string,mixed>
     */
    public static function export() {
        $settings = Repository::get_settings();

        foreach ( self::get_sensitive_keys() as $key ) {
            unset( $settings[ $key ] );
        }

        $payload = array(
            'type'           => self::PAYLOAD_TYPE,
            'plugin'         => 'joinotify',
            'plugin_version' => defined( 'JOINOTIFY_VERSION' ) ? JOINOTIFY_VERSION : '',
            'schema_version' => self::SCHEMA_VERSION,
            'exported_at'    => gmdate( 'c' ),
            'site_url'       => home_url(),
            'data'           => array(
                'settings'          => $settings,
                'builder_variables' => Custom_Variables::get_all(),
                'phones_senders'    => Phone_Manager::get_senders(),
            ),
        );

        /**
         * Filter the full settings export payload before it is returned.
         *
         * @since 2.0.0
         * @param array<string,mixed> $payload Export payload.
         */
        return apply_filters( 'Joinotify/Admin/Settings/Transfer/Export_Payload', $payload );
    }


    /**
     * Suggested file name for the exported payload.
     *
     * @since 2.0.0
     * @return string
     */
    public static function get_export_filename() {
        $host = wp_parse_url( home_url(), PHP_URL_HOST );
        $slug = $host ? sanitize_title( $host ) : 'site';

        return sprintf( 'joinotify-settings-%s-%s.json', $slug, gmdate( 'Ymd-His' ) );
    }


    /**
     * Import a previously exported payload, merging it into the current config.
     *
     * @since 2.0.0
     * @param mixed $payload Decoded JSON payload (associative array).
     * @return array{success:bool,message:string,imported?:array<string,int>}
     */
    public static function import( $payload ) {
        if ( ! is_array( $payload ) ) {
            return array(
                'success' => false,
                'message' => __( 'Invalid file. Could not read the configuration.', 'joinotify' ),
            );
        }

        // accept payloads where data sits at the root or under a "data" key
        $data = isset( $payload['data'] ) && is_array( $payload['data'] ) ? $payload['data'] : $payload;

        $type = isset( $payload['type'] ) ? (string) $payload['type'] : '';

        if ( '' !== $type && self::PAYLOAD_TYPE !== $type ) {
            return array(
                'success' => false,
                'message' => __( 'This file is not a valid Joinotify settings export.', 'joinotify' ),
            );
        }

        $has_settings = isset( $data['settings'] ) && is_array( $data['settings'] );
        $has_variables = isset( $data['builder_variables'] ) && is_array( $data['builder_variables'] );
        $has_phones = isset( $data['phones_senders'] ) && is_array( $data['phones_senders'] );

        if ( ! $has_settings && ! $has_variables && ! $has_phones ) {
            return array(
                'success' => false,
                'message' => __( 'The file does not contain any settings to import.', 'joinotify' ),
            );
        }

        $imported = array(
            'settings'          => 0,
            'builder_variables' => 0,
            'phones_senders'    => 0,
        );

        // settings are merged: only keys present in the file are overwritten,
        // and save_settings() preserves everything else (including the local
        // credentials that were stripped from the export).
        if ( $has_settings ) {
            $incoming = self::strip_sensitive( $data['settings'] );

            if ( ! empty( $incoming ) ) {
                // merge over the current settings so a partial file does not
                // reset toggles that are absent from it back to "no".
                $merged = array_merge( Repository::get_settings(), $incoming );
                Repository::save_settings( $merged );
                $imported['settings'] = count( $incoming );
            }
        }

        if ( $has_variables ) {
            $result = Custom_Variables::import( $data['builder_variables'] );
            $imported['builder_variables'] = isset( $result['imported'] ) ? (int) $result['imported'] : 0;
        }

        if ( $has_phones ) {
            $imported['phones_senders'] = self::import_phone_senders( $data['phones_senders'] );
        }

        return array(
            'success'  => true,
            'message'  => __( 'Settings imported successfully.', 'joinotify' ),
            'imported' => $imported,
        );
    }


    /**
     * Remove sensitive credential keys from an incoming settings array.
     *
     * @since 2.0.0
     * @param array<string,mixed> $settings Incoming settings.
     * @return array<string,mixed>
     */
    private static function strip_sensitive( $settings ) {
        foreach ( self::get_sensitive_keys() as $key ) {
            unset( $settings[ $key ] );
        }

        return $settings;
    }


    /**
     * Merge imported phone senders into the registered list.
     *
     * Connection state is not transferred; only the sender numbers are added so
     * they can be re-validated on the target site.
     *
     * @since 2.0.0
     * @param array<int,mixed> $senders Incoming sender numbers.
     * @return int Number of new senders added.
     */
    private static function import_phone_senders( $senders ) {
        $existing = Phone_Manager::get_senders();
        $added = 0;

        foreach ( $senders as $phone ) {
            $phone = Phone_Manager::sanitize_phone( (string) $phone );

            if ( '' === $phone || in_array( $phone, $existing, true ) ) {
                continue;
            }

            Phone_Manager::add_sender( $phone );
            $existing[] = $phone;
            $added++;
        }

        return $added;
    }
}
