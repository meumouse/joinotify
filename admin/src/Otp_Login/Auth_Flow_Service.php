<?php

namespace MeuMouse\Joinotify\Otp_Login;

use WP_Error;

defined('ABSPATH') || exit;

/**
 * Coordinates the OTP login, password login and account registration flows.
 *
 * @since 2.0.0
 * @package MeuMouse\Joinotify\Otp_Login
 * @author MeuMouse.com
 */
class Auth_Flow_Service {

    /**
     * User repository instance.
     *
     * @since 2.0.0
     * @var User_Repository
     */
    private $users;

    /**
     * OTP code service instance.
     *
     * @since 2.0.0
     * @var Otp_Code
     */
    private $otp_code;


    /**
     * Build the service with optional dependency injection.
     *
     * @since 2.0.0
     * @param User_Repository|null $users Optional user repository instance.
     * @param Otp_Code|null        $otp_code Optional OTP service instance.
     * @return void
     */
    public function __construct( ?User_Repository $users = null, ?Otp_Code $otp_code = null ) {
        $this->users = $users ?: new User_Repository();
        $this->otp_code = $otp_code ?: new Otp_Code();
    }


    /**
     * Request an OTP for the account associated with the submitted phone number.
     *
     * @since 2.0.0
     * @param string $phone Raw or normalized phone number.
     * @return array|WP_Error Response payload or a WordPress error object.
     */
    public function request_otp_login( $phone ) {
        if ( ! Settings::is_enabled() ) {
            return new WP_Error( 'otp_login_disabled', __( 'The OTP login form is currently disabled.', 'joinotify' ) );
        }

        $normalized_phone = Phone_Utils::normalize( $phone );

        if ( empty( $normalized_phone ) ) {
            return new WP_Error( 'invalid_phone', __( 'Enter a valid phone number with country code.', 'joinotify' ) );
        }

        // Throttle requests per phone + client IP to prevent code-send flooding.
        $identity = $normalized_phone . '|' . Rate_Limiter::get_client_ip();
        $max_requests = (int) apply_filters( 'Joinotify/Otp_Login/Max_Requests', 5 );
        $window = (int) apply_filters( 'Joinotify/Otp_Login/Requests_Window', 600 );

        if ( ! Rate_Limiter::consume( 'request', $identity, $max_requests, $window ) ) {
            return new WP_Error( 'too_many_requests', __( 'Too many code requests. Please wait a few minutes and try again.', 'joinotify' ) );
        }

        // Enforce a server-side resend cooldown (the UI countdown is cosmetic).
        $cooldown = (int) apply_filters( 'Joinotify/Otp_Login/Resend_Cooldown', 60 );

        if ( $cooldown > 0 && Rate_Limiter::is_cooling_down( 'cooldown', $normalized_phone ) ) {
            return new WP_Error( 'resend_cooldown', __( 'Please wait before requesting another code.', 'joinotify' ) );
        }

        $user = $this->users->find_by_phone( $normalized_phone );

        if ( ! $user ) {
            return array(
                'status' => 'not_found',
                'message' => __( 'We could not find an account with this phone number. Use email and password to log in.', 'joinotify' ),
                'nextStep' => 'password',
            );
        }

        $sent = $this->otp_code->generate_and_send_otp( $normalized_phone, $user );

        if ( is_wp_error( $sent ) ) {
            return $sent;
        }

        if ( $cooldown > 0 ) {
            Rate_Limiter::start_cooldown( 'cooldown', $normalized_phone, $cooldown );
        }

        return array(
            'status' => 'otp_sent',
            'message' => sprintf(
                /* translators: %s: masked phone number. */
                __( 'We sent a code to %s.', 'joinotify' ),
                Phone_Utils::mask( $normalized_phone )
            ),
            'nextStep' => 'otp',
            'phone' => $normalized_phone,
        );
    }


    /**
     * Validate the submitted OTP and authenticate the matching user.
     *
     * @since 2.0.0
     * @param string $phone Raw or normalized phone number.
     * @param string $otp User submitted OTP code.
     * @param bool   $remember Whether the auth cookie should be persistent.
     * @return array|WP_Error Response payload or a WordPress error object.
     */
    public function verify_otp_login( $phone, $otp, $remember = false ) {
        if ( ! Settings::is_enabled() ) {
            return new WP_Error( 'otp_login_disabled', __( 'The OTP login form is currently disabled.', 'joinotify' ) );
        }

        $normalized_phone = Phone_Utils::normalize( $phone );
        $otp = preg_replace( '/\D+/', '', (string) $otp );

        if ( empty( $normalized_phone ) || empty( $otp ) ) {
            return new WP_Error( 'invalid_otp_payload', __( 'Fill in the phone number and the code you received.', 'joinotify' ) );
        }

        $user = $this->users->find_by_phone( $normalized_phone );

        if ( ! $user ) {
            return new WP_Error( 'user_not_found', __( 'We could not find an account for this phone number.', 'joinotify' ) );
        }

        if ( ! $this->otp_code->validate_otp( $normalized_phone, $otp ) ) {
            return new WP_Error( 'invalid_otp', __( 'The code you entered is invalid or has expired.', 'joinotify' ) );
        }

        $this->users->save_phone( $user->ID, $normalized_phone );
        Rate_Limiter::reset( 'cooldown', $normalized_phone );
        $this->login_user( $user->ID, (bool) $remember );

        return array(
            'status' => 'authenticated',
            'message' => __( 'Login successful.', 'joinotify' ),
        );
    }


    /**
     * Authenticate a user using username or email plus password credentials.
     *
     * @since 2.0.0
     * @param string $identifier Submitted username or email address.
     * @param string $password Submitted password.
     * @param bool   $remember Whether the auth cookie should be persistent.
     * @return array|WP_Error Response payload or a WordPress error object.
     */
    public function login_with_password( $identifier, $password, $remember = false ) {
        if ( ! Settings::is_enabled() ) {
            return new WP_Error( 'otp_login_disabled', __( 'The OTP login form is currently disabled.', 'joinotify' ) );
        }

        $identifier = sanitize_user( $identifier, true );

        if ( empty( $identifier ) ) {
            return new WP_Error( 'invalid_identifier', __( 'Enter a valid username or email address.', 'joinotify' ) );
        }

        if ( empty( $password ) ) {
            return new WP_Error( 'empty_password', __( 'Enter your password.', 'joinotify' ) );
        }

        $user = wp_signon(
            array(
                'user_login' => $identifier,
                'user_password' => $password,
                'remember' => (bool) $remember,
            ),
            is_ssl()
        );

        if ( is_wp_error( $user ) ) {
            return new WP_Error( 'auth_failed', __( 'We could not authenticate with that username or email and password.', 'joinotify' ) );
        }

        return array(
            'status' => 'authenticated',
            'message' => __( 'Login successful.', 'joinotify' ),
        );
    }


    /**
     * Register a new user, store the phone metadata and sign the user in.
     *
     * @since 2.0.0
     * @param array<string,mixed> $payload Registration payload.
     * @return array|WP_Error Response payload or a WordPress error object.
     */
    public function register_user( array $payload ) {
        if ( ! Settings::is_enabled() ) {
            return new WP_Error( 'otp_login_disabled', __( 'The OTP login form is currently disabled.', 'joinotify' ) );
        }

        // Honor the site-wide registration switch.
        if ( ! get_option( 'users_can_register' ) && ! function_exists( 'wc_create_new_customer' ) ) {
            return new WP_Error( 'registration_disabled', __( 'Account registration is currently disabled.', 'joinotify' ) );
        }

        $email = sanitize_email( $payload['email'] ?? '' );
        $password = (string) ( $payload['password'] ?? '' );
        $username = sanitize_user( $payload['username'] ?? '', true );
        $phone = Phone_Utils::normalize( $payload['phone'] ?? '' );

        if ( ! is_email( $email ) ) {
            return new WP_Error( 'invalid_email', __( 'Enter a valid email address.', 'joinotify' ) );
        }

        if ( empty( $password ) ) {
            return new WP_Error( 'empty_password', __( 'Enter a password to create the account.', 'joinotify' ) );
        }

        if ( empty( $phone ) ) {
            return new WP_Error( 'invalid_phone', __( 'Enter a valid phone number with country code.', 'joinotify' ) );
        }

        if ( email_exists( $email ) ) {
            return new WP_Error( 'email_exists', __( 'An account is already registered with this email address.', 'joinotify' ) );
        }

        if ( $this->users->phone_exists( $phone ) ) {
            return new WP_Error( 'phone_exists', __( 'An account is already registered with this phone number.', 'joinotify' ) );
        }

        if ( empty( $username ) ) {
            $username = $this->generate_username_from_email( $email );
        }

        if ( username_exists( $username ) ) {
            $username = $this->generate_username_from_email( $email );
        }

        if ( function_exists( 'wc_create_new_customer' ) ) {
            $user_id = wc_create_new_customer( $email, $username, $password );
        } else {
            $user_id = wp_create_user( $username, $password, $email );
        }

        if ( is_wp_error( $user_id ) ) {
            return new WP_Error( 'registration_failed', $user_id->get_error_message() );
        }

        $this->users->save_phone( $user_id, $phone );
        $this->login_user( $user_id, true );

        return array(
            'status' => 'registered',
            'message' => __( 'Account created successfully.', 'joinotify' ),
        );
    }


    /**
     * Generate a unique username from the email local-part.
     *
     * @since 2.0.0
     * @param string $email Email address.
     * @return string Generated username.
     */
    private function generate_username_from_email( $email ) {
        $base = sanitize_user( current( explode( '@', $email ) ), true );
        $base = empty( $base ) ? 'user' : $base;
        $candidate = $base;
        $suffix = 1;

        while ( username_exists( $candidate ) ) {
            $candidate = $base . $suffix;
            $suffix++;
        }

        return $candidate;
    }


    /**
     * Sign a user into WordPress and trigger the standard login action.
     *
     * @since 2.0.0
     * @param int  $user_id Target user ID.
     * @param bool $remember Whether the auth cookie should be persistent.
     * @return void
     */
    private function login_user( $user_id, $remember ) {
        wp_set_current_user( $user_id );
        wp_set_auth_cookie( $user_id, $remember, is_ssl() );

        $user = get_user_by( 'id', $user_id );

        if ( $user ) {
            do_action( 'wp_login', $user->user_login, $user );
        }
    }
}
