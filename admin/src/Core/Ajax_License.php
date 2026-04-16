<?php

namespace MeuMouse\Joinotify\Core;

use MeuMouse\Joinotify\Admin\Settings\Registry;
use MeuMouse\Joinotify\Api\License;
use WP_Error;
use stdClass;

defined('ABSPATH') || exit;

/**
 * AJAX callbacks related to license management.
 *
 * Handles: activate, alternative-activate, deactivate, sync.
 *
 * @since 1.4.7
 * @package MeuMouse\Joinotify\Core
 */
class Ajax_License {

    /**
     * Register AJAX actions.
     *
     * @since 1.4.7
     */
    public function __construct() {
        $actions = array(
            'joinotify_active_license'                  => 'active_license_callback',
            'joinotify_alternative_activation_license'  => 'alternative_active_license_callback',
            'joinotify_deactive_license'                => 'deactive_license_callback',
            'joinotify_sync_license'                    => 'sync_license_callback',
        );

        foreach ( $actions as $action => $callback ) {
            add_action( "wp_ajax_{$action}", array( $this, $callback ) );
        }
    }


    /**
     * Active license process on AJAX callback
     *
     * @since 1.0.0
     * @return void
     */
    public function active_license_callback() {
        if ( isset( $_POST['action'] ) && $_POST['action'] === 'joinotify_active_license' ) {
            $response_obj    = new stdClass();
            $license_message = '';
            $license_key     = isset( $_POST['license_key'] ) ? sanitize_text_field( $_POST['license_key'] ) : '';

            Cache_Helper::clear_license_cache();

            update_option( 'joinotify_license_key', $license_key ) || add_option( 'joinotify_license_key', $license_key );
            update_option( 'joinotify_temp_license_key', $license_key ) || add_option( 'joinotify_temp_license_key', $license_key );

            if ( License::check_license( $license_key, $license_message, $response_obj, JOINOTIFY_FILE ) ) {
                if ( $response_obj && $response_obj->is_valid ) {
                    update_option( 'joinotify_license_status', 'valid' );
                    delete_option( 'joinotify_temp_license_key' );
                    delete_option( 'joinotify_alternative_license_activation' );
                } else {
                    update_option( 'joinotify_license_status', 'invalid' );
                }

                if ( License::is_valid() ) {
                    $response = array(
                        'status'             => 'success',
                        'toast_header_title' => __( 'License activated successfully.', 'joinotify' ),
                        'toast_body_title'   => __( 'All features are now active!', 'joinotify' ),
                        'license_data'       => Registry::get_license_state(),
                    );
                }
            } else {
                if ( ! empty( $license_key ) && ! empty( $license_message ) ) {
                    $response = array(
                        'status'             => 'error',
                        'toast_header_title' => __( 'Oops! An error occurred.', 'joinotify' ),
                        'toast_body_title'   => $license_message,
                    );
                }
            }

            wp_send_json( $response );
        }
    }


    /**
     * Alternative activation license process on AJAX callback
     *
     * @since 1.0.0
     * @return void
     */
    public function alternative_active_license_callback() {
        if ( ! isset( $_POST['action'] ) || $_POST['action'] !== 'joinotify_alternative_activation_license' ) {
            wp_send_json( array(
                'status'             => 'error',
                'toast_header_title' => __( 'Oops! An error occurred.', 'joinotify' ),
                'toast_body_title'   => __( 'Error loading the file. The action was not triggered correctly.', 'joinotify' ),
            ) );
        }

        if ( empty( $_FILES['file'] ) ) {
            wp_send_json( array(
                'status'             => 'error',
                'toast_header_title' => __( 'Oops! An error occurred.', 'joinotify' ),
                'toast_body_title'   => __( 'Error loading the file. The file was not uploaded.', 'joinotify' ),
            ) );
        }

        $file = $_FILES['file'];

        if ( pathinfo( $file['name'], PATHINFO_EXTENSION ) !== 'key' ) {
            wp_send_json( array(
                'status'             => 'invalid_file',
                'toast_header_title' => __( 'Oops! An error occurred.', 'joinotify' ),
                'toast_body_title'   => __( 'Invalid file. The file must have a .key extension.', 'joinotify' ),
            ) );
        }

        $file_content = file_get_contents( $file['tmp_name'] );

        $decrypt_keys = array(
            'E63390D3F50B70F0',
            'B729F2659393EE27',
        );

        $decrypted_data = License::decrypt_alternative_license( $file_content, $decrypt_keys );

        if ( $decrypted_data !== null ) {
            $license_data_array = json_decode( stripslashes( $decrypted_data ) );
            $this_domain        = License::get_domain();

            if ( $license_data_array === null ) {
                return;
            }

            if ( $this_domain !== $license_data_array->site_domain ) {
                wp_send_json( array(
                    'status'             => 'error',
                    'toast_header_title' => __( 'Oops! An error occurred.', 'joinotify' ),
                    'toast_body_title'   => __( 'The activation domain is not allowed.', 'joinotify' ),
                ) );
            }

            if ( ! in_array( $license_data_array->selected_product, array( '7', '8' ) ) ) {
                wp_send_json( array(
                    'status'             => 'error',
                    'toast_header_title' => __( 'Oops! An error occurred.', 'joinotify' ),
                    'toast_body_title'   => __( 'The provided license is not allowed for this product.', 'joinotify' ),
                ) );
            }

            $license_object = $license_data_array->license_object;

            if ( $this_domain === $license_data_array->site_domain ) {
                Cache_Helper::clear_license_cache();

                $obj                = new stdClass();
                $obj->license_key   = $license_data_array->license_code;
                $obj->email         = $license_data_array->user_email;
                $obj->domain        = $this_domain;
                $obj->app_version   = JOINOTIFY_VERSION;
                $obj->product_id    = $license_data_array->selected_product;
                $obj->product_base  = $license_data_array->product_base;
                $obj->is_valid      = $license_object->is_valid;
                $obj->license_title = $license_object->license_title;
                $obj->expire_date   = $license_object->expire_date;

                update_option( 'joinotify_alternative_license', 'active' );
                update_option( 'joinotify_license_response_object', $obj );
                update_option( 'joinotify_license_key', $obj->license_key );
                update_option( 'joinotify_license_status', 'valid' );

                wp_send_json( array(
                    'status'             => 'success',
                    'dropfile_message'   => __( 'File uploaded successfully.', 'joinotify' ),
                    'toast_header_title' => __( 'License activated successfully.', 'joinotify' ),
                    'toast_body_title'   => __( 'All features are now active!', 'joinotify' ),
                    'license_data'       => Registry::get_license_state(),
                ) );
            }
        } else {
            wp_send_json( array(
                'status'             => 'error',
                'toast_header_title' => __( 'Oops! An error occurred.', 'joinotify' ),
                'toast_body_title'   => __( 'Could not decrypt the license file.', 'joinotify' ),
            ) );
        }
    }


    /**
     * Deactivate license process on AJAX callback
     *
     * @since 1.0.0
     * @return void
     */
    public function deactive_license_callback() {
        if ( isset( $_POST['action'] ) && $_POST['action'] === 'joinotify_deactive_license' ) {
            $message = '';

            if ( License::deactive_license( JOINOTIFY_FILE, $message ) ) {
                $response = array(
                    'status'             => 'success',
                    'toast_header_title' => __( 'License deactivated.', 'joinotify' ),
                    'toast_body_title'   => __( 'The license was deactivated successfully!', 'joinotify' ),
                );
            } else {
                $response = array(
                    'status'             => 'error',
                    'toast_header_title' => __( 'Oops! An error occurred.', 'joinotify' ),
                    'toast_body_title'   => __( 'Could not deactivate the license.', 'joinotify' ),
                );
            }

            wp_send_json( $response );
        }
    }


    /**
     * Sync license information on AJAX callback
     *
     * @since 1.4.5
     * @return void
     */
    public function sync_license_callback() {
        if ( isset( $_POST['action'] ) && $_POST['action'] === 'joinotify_sync_license' ) {
            $response_obj    = new stdClass();
            $license_message = '';
            $license_key     = get_option( 'joinotify_license_key', '' );
            $license_key     = is_string( $license_key ) ? sanitize_text_field( $license_key ) : '';

            if ( empty( $license_key ) ) {
                wp_send_json( array(
                    'status'             => 'error',
                    'toast_header_title' => __( 'License not found', 'joinotify' ),
                    'toast_body_title'   => __( 'No license was found to sync.', 'joinotify' ),
                ) );
            }

            Cache_Helper::clear_license_cache();

            if ( License::check_license( $license_key, $license_message, $response_obj, JOINOTIFY_FILE ) ) {
                if ( $response_obj && ! empty( $response_obj->is_valid ) ) {
                    update_option( 'joinotify_license_status', 'valid' );
                    delete_option( 'joinotify_alternative_license_activation' );
                } else {
                    update_option( 'joinotify_license_status', 'invalid' );
                }

                wp_send_json( array(
                    'status'             => 'success',
                    'toast_header_title' => __( 'License synced', 'joinotify' ),
                    'toast_body_title'   => __( 'Your license information was updated successfully.', 'joinotify' ),
                    'license_data'       => Registry::get_license_state(),
                ) );
            }

            wp_send_json( array(
                'status'             => 'error',
                'toast_header_title' => __( 'Oops! An error occurred.', 'joinotify' ),
                'toast_body_title'   => ! empty( $license_message ) ? $license_message : __( 'Could not sync the license information.', 'joinotify' ),
            ) );
        }
    }
}
