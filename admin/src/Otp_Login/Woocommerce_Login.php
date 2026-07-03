<?php

namespace MeuMouse\Joinotify\Otp_Login;

defined('ABSPATH') || exit;

/**
 * WooCommerce login integration points for the passwordless flow.
 *
 * Overrides the WooCommerce login templates, renders the OTP form on checkout,
 * and adds an international phone field to the account details form so customers
 * can keep the number used for code delivery up to date.
 *
 * @since 2.0.0
 * @package MeuMouse\Joinotify\Otp_Login
 * @author MeuMouse.com
 */
class Woocommerce_Login {

    /**
     * User repository used to persist phone metadata.
     *
     * @since 2.0.0
     * @var User_Repository
     */
    private $users;


    /**
     * Register WooCommerce hooks.
     *
     * @since 2.0.0
     * @return void
     */
    public function __construct() {
        if ( ! class_exists( 'WooCommerce' ) ) {
            return;
        }

        $this->users = new User_Repository();

        add_filter( 'woocommerce_locate_template', array( $this, 'locate_template' ), 99, 3 );
        add_action( 'flexify_checkout_before_layout', array( $this, 'disable_flexify_native_login_form' ), 1 );
        add_action( 'woocommerce_login_form_start', array( $this, 'render_checkout_login_form' ) );
        add_action( 'woocommerce_edit_account_form', array( $this, 'render_account_phone_field' ) );
        add_action( 'woocommerce_save_account_details', array( $this, 'save_account_phone_field' ), 20, 1 );
        add_action( 'woocommerce_created_customer', array( $this, 'save_registration_phone' ), 20, 1 );
    }


    /**
     * Persist the phone submitted through the account registration form.
     *
     * @since 2.0.0
     * @param int $customer_id Newly created customer ID.
     * @return void
     */
    public function save_registration_phone( $customer_id ) {
        if ( ! $customer_id || empty( $_POST['phone'] ) ) {
            return;
        }

        $phone = Phone_Utils::normalize( sanitize_text_field( wp_unslash( $_POST['phone'] ) ) );

        if ( ! empty( $phone ) ) {
            $this->users->save_phone( $customer_id, $phone );
        }
    }


    /**
     * Override WooCommerce login templates with the plugin versions when available.
     *
     * @since 2.0.0
     * @param string $template Located template path.
     * @param string $template_name Requested template name.
     * @param string $template_path Template base path provided by WooCommerce.
     * @return string
     */
    public function locate_template( $template, $template_name, $template_path ) {
        if ( ! Settings::is_enabled() ) {
            return $template;
        }

        $supported_templates = array(
            'myaccount/form-login.php',
            'checkout/form-login.php',
        );

        if ( ! in_array( $template_name, $supported_templates, true ) ) {
            return $template;
        }

        $plugin_template = Templates::get_base_path() . $template_name;

        if ( file_exists( $plugin_template ) ) {
            Frontend_Assets::enqueue();

            return $plugin_template;
        }

        return $template;
    }


    /**
     * Render the OTP login form on the WooCommerce checkout login hook.
     *
     * @since 2.0.0
     * @return void
     */
    public function render_checkout_login_form() {
        if ( ! Settings::is_enabled() || ! function_exists( 'is_checkout' ) || ! is_checkout() || is_user_logged_in() ) {
            return;
        }

        if ( ! class_exists( 'WC_Checkout' ) ) {
            return;
        }

        Frontend_Assets::enqueue();

        Templates::render(
            'shared/otp-login-form.php',
            array(
                'context' => 'checkout',
                'redirect_url' => wc_get_checkout_url(),
                'title' => __( 'Log in to continue', 'joinotify' ),
                'description' => __( 'Use your phone number to receive the code or sign in with email and password.', 'joinotify' ),
                'show_header' => true,
                'root_class' => 'woocommerce-form-login',
            )
        );
    }


    /**
     * Remove the native Flexify checkout login template before it renders.
     *
     * @since 2.0.0
     * @return void
     */
    public function disable_flexify_native_login_form() {
        if ( ! Settings::is_enabled() || ! function_exists( 'is_checkout' ) || ! is_checkout() || is_user_logged_in() ) {
            return;
        }

        if ( ! function_exists( 'remove_filters_with_method_name' ) ) {
            return;
        }

        remove_filters_with_method_name( 'flexify_checkout_before_layout', 'load_form_login_template', 10 );
    }


    /**
     * Render the phone field inside the WooCommerce account details form.
     *
     * @since 2.0.0
     * @return void
     */
    public function render_account_phone_field() {
        if ( ! Settings::is_enabled() || ! function_exists( 'is_wc_endpoint_url' ) || ! is_wc_endpoint_url( 'edit-account' ) ) {
            return;
        }

        $user_id = get_current_user_id();
        $current_phone = $user_id ? (string) get_user_meta( $user_id, 'joinotify_user_phone', true ) : '';

        if ( empty( $current_phone ) && $user_id ) {
            $current_phone = (string) get_user_meta( $user_id, 'billing_phone', true );
        }

        Frontend_Assets::enqueue();

        printf(
            '<div id="joinotify-account-phone" data-default-country="%1$s" data-initial-phone="%2$s"></div>',
            esc_attr( $this->get_default_country() ),
            esc_attr( $current_phone )
        );
    }


    /**
     * Save the account phone field when the customer updates their profile.
     *
     * @since 2.0.0
     * @param int $user_id Current user ID.
     * @return void
     */
    public function save_account_phone_field( $user_id ) {
        if ( ! $user_id ) {
            return;
        }

        $phone = isset( $_POST['account_phone'] ) ? sanitize_text_field( wp_unslash( $_POST['account_phone'] ) ) : '';

        if ( empty( $phone ) ) {
            return;
        }

        $normalized = Phone_Utils::normalize( $phone );

        if ( empty( $normalized ) ) {
            return;
        }

        $this->users->save_phone( $user_id, $normalized );
    }


    /**
     * Resolve the WooCommerce base country for the phone field default.
     *
     * @since 2.0.0
     * @return string
     */
    private function get_default_country() {
        if ( function_exists( 'wc_get_base_location' ) ) {
            $base_location = wc_get_base_location();

            if ( ! empty( $base_location['country'] ) ) {
                return strtolower( (string) $base_location['country'] );
            }
        }

        return 'br';
    }
}
