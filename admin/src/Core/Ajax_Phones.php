<?php

namespace MeuMouse\Joinotify\Core;

use MeuMouse\Joinotify\Admin\Admin;
use MeuMouse\Joinotify\Admin\Components as Admin_Components;
use MeuMouse\Joinotify\Api\Controller;
use MeuMouse\Joinotify\Validations\Otp_Validation;

defined('ABSPATH') || exit;

/**
 * AJAX callbacks related to phone sender management.
 *
 * Handles: get numbers, register, validate OTP, remove, check connection.
 *
 * @since 1.4.7
 * @package MeuMouse\Joinotify\Core
 */
class Ajax_Phones {

    /**
     * Register AJAX actions.
     *
     * @since 1.4.7
     */
    public function __construct() {
        $actions = array(
            'joinotify_get_phone_numbers'         => 'get_phone_numbers_callback',
            'joinotify_register_phone_sender'     => 'register_phone_sender_callback',
            'joinotify_validate_otp'              => 'validate_otp_callback',
            'joinotify_remove_phone_sender'       => 'remove_phone_sender_callback',
            'joinotify_check_instance_connection' => 'check_instance_connection_callback',
        );

        foreach ( $actions as $action => $callback ) {
            add_action( "wp_ajax_{$action}", array( $this, $callback ) );
        }
    }


    /**
     * Get phone sender candidates on AJAX callback.
     *
     * @since 1.0.0
     * @return void
     */
    public function get_phone_numbers_callback() {
        if ( isset( $_POST['action'] ) && $_POST['action'] === 'joinotify_get_phone_numbers' ) {
            $response_data = Controller::get_numbers();
            $phone_numbers = isset( $response_data['slots'] ) && is_array( $response_data['slots'] ) ? $response_data['slots'] : array();
            $registered    = Phone_Manager::get_senders();

            $filtered = array_filter( $phone_numbers, static function ( $value ) use ( $registered ) {
                return is_array( $value ) && isset( $value['phone'] ) && ! in_array( $value['phone'], $registered, true );
            } );

            if ( empty( $filtered ) ) {
                wp_send_json( array(
                    'status'             => 'success',
                    'empty_phone_message' => sprintf(
                        __( 'No available phone number found for registration. Register it using the link: <a class="fancy-link" href="%s" target="_blank">%s</a>', 'joinotify' ),
                        esc_url( JOINOTIFY_REGISTER_PHONE_URL ),
                        __( 'Register a sender', 'joinotify' )
                    ),
                ) );
            } else {
                $html = '<ul class="list-group">';

                foreach ( $filtered as $value ) {
                    if ( ! is_array( $value ) || ! isset( $value['phone'] ) ) {
                        continue;
                    }

                    $html .= '<li class="list-group-item d-flex align-items-center justify-content-between py-3" data-phone="' . esc_attr( $value['phone'] ) . '">';
                    $html .= '<span class="fs-base">' . Helpers::validate_and_format_phone( $value['phone'] ) . '</span>';
                    $html .= '<button class="btn btn-sm btn-outline-primary register-sender" data-phone="' . esc_attr( $value['phone'] ) . '">' . esc_html__( 'Register sender', 'joinotify' ) . '</button>';
                    $html .= '</li>';
                }

                $html .= '</ul>';

                wp_send_json( array(
                    'status'             => 'success',
                    'phone_numbers_html' => $html,
                ) );
            }
        }
    }


    /**
     * Register phone sender and send OTP on AJAX callback.
     *
     * @since 1.0.0
     * @return void
     */
    public function register_phone_sender_callback() {
        if ( isset( $_POST['action'] ) && $_POST['action'] === 'joinotify_register_phone_sender' ) {
            $phone   = isset( $_POST['phone'] ) ? Phone_Manager::sanitize_phone( $_POST['phone'] ) : '';
            $get_otp = Otp_Validation::generate_and_send_otp( $phone );

            if ( $get_otp ) {
                $response = array(
                    'status'              => 'success',
                    'otp_input_component' => Admin_Components::otp_input_code( $phone ),
                );
            } else {
                $response = array(
                    'status'             => 'error',
                    'toast_header_title' => __( 'Oops! An error occurred.', 'joinotify' ),
                    'toast_body_title'   => __( 'Could not send the verification code.', 'joinotify' ),
                );
            }

            wp_send_json( $response );
        }
    }


    /**
     * Validate OTP on AJAX callback.
     *
     * @since 1.0.0
     * @return void
     */
    public function validate_otp_callback() {
        if ( isset( $_POST['action'] ) && $_POST['action'] === 'joinotify_validate_otp' ) {
            $phone = isset( $_POST['phone'] ) ? sanitize_text_field( $_POST['phone'] ) : '';
            $otp   = isset( $_POST['otp'] ) ? sanitize_text_field( $_POST['otp'] ) : '';

            if ( empty( $phone ) || empty( $otp ) ) {
                wp_send_json( array(
                    'status'             => 'error',
                    'toast_header_title' => __( 'OTP validation error', 'joinotify' ),
                    'toast_body_title'   => __( 'Please fill in the phone number and the OTP code.', 'joinotify' ),
                ) );

                return;
            }

            if ( Otp_Validation::validate_otp( $phone, $otp ) ) {
                Phone_Manager::add_sender( $phone );

                do_action( 'Joinotify/Validate_Phone/Success', $phone );

                Controller::get_connection_state( $phone );

                wp_send_json( array(
                    'status'                => 'success',
                    'toast_header_title'    => __( 'Verification successful', 'joinotify' ),
                    'toast_body_title'      => __( 'Your WhatsApp was verified successfully!', 'joinotify' ),
                    'current_phone_senders' => Admin_Components::current_phones_senders(),
                ) );
            } else {
                wp_send_json( array(
                    'status'             => 'error',
                    'toast_header_title' => __( 'Verification error', 'joinotify' ),
                    'toast_body_title'   => __( 'The verification code is incorrect or has expired. Please try again.', 'joinotify' ),
                ) );
            }
        }
    }


    /**
     * Remove a phone sender on AJAX callback.
     *
     * @since 1.0.0
     * @return void
     */
    public function remove_phone_sender_callback() {
        if ( isset( $_POST['action'] ) && $_POST['action'] === 'joinotify_remove_phone_sender' ) {
            $phone = isset( $_POST['phone'] ) ? sanitize_text_field( $_POST['phone'] ) : '';

            if ( empty( $phone ) ) {
                wp_send_json( array(
                    'status'             => 'error',
                    'toast_header_title' => __( 'Error removing phone', 'joinotify' ),
                    'toast_body_title'   => __( 'Invalid phone number.', 'joinotify' ),
                ) );

                return;
            }

            if ( Phone_Manager::remove_sender( $phone ) ) {
                do_action( 'Joinotify/Remove_Phone/Success', $phone );

                wp_send_json( array(
                    'status'             => 'success',
                    'toast_header_title' => __( 'Remetente removido', 'joinotify' ),
                    'toast_body_title'   => __( 'The sender phone was removed successfully!', 'joinotify' ),
                    'updated_list_html'  => Admin_Components::current_phones_senders(),
                ) );
            }

            wp_send_json( array(
                'status'             => 'error',
                'toast_header_title' => __( 'Error removing sender', 'joinotify' ),
                'toast_body_title'   => __( 'Could not find the specified phone number.', 'joinotify' ),
            ) );
        }
    }


    /**
     * Check instance connection state on AJAX callback.
     *
     * @since 1.3.0
     * @return void
     */
    public function check_instance_connection_callback() {
        if ( isset( $_POST['action'] ) && $_POST['action'] === 'joinotify_check_instance_connection' ) {
            $phone     = isset( $_POST['phone'] ) ? Phone_Manager::sanitize_phone( $_POST['phone'] ) : '';
            $cache_key = 'joinotify_server_details_' . md5( $phone );

            delete_transient( $cache_key );

            $get_state = Controller::get_connection_state( $phone );

            if ( isset( $get_state['connection'] ) && $get_state['connection'] === 'connected' ) {
                $response = array(
                    'status'                  => 'success',
                    'toast_header_title'      => esc_html__( 'Connection established.', 'joinotify' ),
                    'toast_body_title'        => esc_html__( 'The phone is connected!', 'joinotify' ),
                    'display_state_component' => Admin_Components::display_state_connection( $phone ),
                );
            } else {
                $response = array(
                    'status'                  => 'error',
                    'toast_header_title'      => esc_html__( 'Oops! An error occurred.', 'joinotify' ),
                    'toast_body_title'        => esc_html__( 'The phone is disconnected.', 'joinotify' ),
                    'display_state_component' => Admin_Components::display_state_connection( $phone ),
                );
            }

            wp_send_json( $response );
        }
    }
}
