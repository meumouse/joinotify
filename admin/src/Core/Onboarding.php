<?php

namespace MeuMouse\Joinotify\Core;

// Exit if accessed directly.
defined('ABSPATH') || exit;

/**
 * Own the setup wizard's state and decide when to show it.
 *
 * The wizard is the plugin's consent point: the country, the Joinotify API key
 * and the usage-data opt-in are all decided there, and nothing contacts an
 * external server before the user works through it. So it has to actually be
 * seen — on a fresh activation, and on installs that upgraded into a version
 * that has it.
 *
 * Being seen is not the same as taking over the admin. The redirect fires at
 * most once per user: after that the reminder is a dismissible notice on
 * Joinotify's own screens, and the wizard stays reachable by URL forever.
 *
 * @since 2.3.0
 * @package MeuMouse\Joinotify\Core
 * @author MeuMouse.com
 */
class Onboarding {

    /**
     * Option holding the wizard state.
     *
     * @since 2.3.0
     * @var string
     */
    const OPTION = 'joinotify_onboarding';

    /**
     * Transient set on activation to trigger the one-time redirect.
     *
     * @since 2.3.0
     * @var string
     */
    const ACTIVATION_KEY = 'joinotify_activation_redirect';

    /**
     * User meta recording that this user already landed on the wizard.
     *
     * @since 2.3.0
     * @var string
     */
    const SEEN_META = 'joinotify_onboarding_seen';

    /**
     * Admin page slug of the wizard.
     *
     * @since 2.3.0
     * @var string
     */
    const PAGE = 'joinotify-onboarding';


    /**
     * Register the hooks that surface the wizard.
     *
     * @since 2.3.0
     * @return void
     */
    public function __construct() {
        add_action( 'admin_init', array( $this, 'maybe_redirect' ), 1 );
        add_action( 'admin_notices', array( $this, 'render_notice' ) );
        add_action( 'wp_ajax_joinotify_dismiss_onboarding_notice', array( $this, 'ajax_dismiss_notice' ) );
        add_filter( 'admin_body_class', array( $this, 'add_body_class' ) );
    }


    /**
     * Flag the wizard screen on the admin body.
     *
     * The wizard covers the whole viewport, and a fixed overlay does not stop
     * the document underneath from scrolling. Locking it from CSS on the body
     * costs nothing and works before any JavaScript has run.
     *
     * @since 2.3.0
     * @param string $classes Space-separated body classes.
     * @return string
     */
    public function add_body_class( $classes ) {
        if ( self::PAGE !== $this->current_page() ) {
            return $classes;
        }

        return trim( $classes . ' joinotify-onboarding-fullscreen' );
    }


    /**
     * Flag a fresh activation so the next admin request opens the wizard.
     *
     * Called from the plugin's activation hook. Bulk activations are filtered
     * out later, in maybe_redirect(), because that is where the request that
     * would be redirected can actually be inspected.
     *
     * @since 2.3.0
     * @return void
     */
    public static function on_activation() {
        if ( self::is_completed() ) {
            return;
        }

        set_transient( self::ACTIVATION_KEY, 1, 60 );
    }


    /**
     * Read the stored wizard state.
     *
     * @since 2.3.0
     * @return array{completed:bool,completed_at:int,step:string,dismissed:bool,version:int}
     */
    public static function get_state() {
        $state = get_option( self::OPTION, array() );
        $state = is_array( $state ) ? $state : array();

        return array(
            'completed' => ! empty( $state['completed'] ),
            'completed_at' => isset( $state['completed_at'] ) ? (int) $state['completed_at'] : 0,
            'step' => isset( $state['step'] ) ? sanitize_key( $state['step'] ) : '',
            'dismissed' => ! empty( $state['dismissed'] ),
            'version' => isset( $state['version'] ) ? (int) $state['version'] : 1,
        );
    }


    /**
     * Whether the wizard has already been finished on this site.
     *
     * @since 2.3.0
     * @return bool
     */
    public static function is_completed() {
        $state = self::get_state();

        return $state['completed'];
    }


    /**
     * Record which step the user reached, so a reload resumes there.
     *
     * @since 2.3.0
     * @param string $step Step identifier.
     * @return void
     */
    public static function save_progress( $step ) {
        $state = self::get_state();
        $state['step'] = sanitize_key( $step );

        update_option( self::OPTION, $state );
    }


    /**
     * Mark the wizard as finished.
     *
     * @since 2.3.0
     * @return void
     */
    public static function complete() {
        $state = self::get_state();
        $state['completed'] = true;
        $state['completed_at'] = time();
        $state['dismissed'] = true;

        update_option( self::OPTION, $state );

        /**
         * Fires once the setup wizard is finished.
         *
         * @since 2.3.0
         */
        do_action('Joinotify/Onboarding/Completed');
    }


    /**
     * Stop nudging the user without marking the wizard as done.
     *
     * @since 2.3.0
     * @return void
     */
    public static function dismiss() {
        $state = self::get_state();
        $state['dismissed'] = true;

        update_option( self::OPTION, $state );
    }


    /**
     * Admin URL of the wizard.
     *
     * @since 2.3.0
     * @return string
     */
    public static function wizard_url() {
        return admin_url( 'admin.php?page=' . self::PAGE );
    }


    /**
     * Send the user to the wizard once, when it makes sense to.
     *
     * @since 2.3.0
     * @return void
     */
    public function maybe_redirect() {
        if ( ! is_admin() || wp_doing_ajax() || ( defined('DOING_CRON') && DOING_CRON ) ) {
            return;
        }

        if ( ! current_user_can('manage_options') || self::is_completed() ) {
            return;
        }

        $just_activated = (bool) get_transient( self::ACTIVATION_KEY );

        if ( $just_activated ) {
            delete_transient( self::ACTIVATION_KEY );
        }

        // Activating several plugins at once must not hijack the response.
        // phpcs:ignore WordPress.Security.NonceVerification.Recommended
        if ( isset( $_GET['activate-multi'] ) ) {
            return;
        }

        $user_id = get_current_user_id();
        $already_seen = (bool) get_user_meta( $user_id, self::SEEN_META, true );
        $current_page = $this->current_page();

        if ( self::PAGE === $current_page ) {
            update_user_meta( $user_id, self::SEEN_META, 1 );

            return;
        }

        // Fresh activation goes straight in. Otherwise the wizard only opens by
        // itself when the user visits a Joinotify screen for the first time,
        // which keeps upgrades out of the way of unrelated admin work.
        $is_joinotify_screen = 0 === strpos( (string) $current_page, 'joinotify' );

        if ( ! $just_activated && ( $already_seen || ! $is_joinotify_screen ) ) {
            return;
        }

        update_user_meta( $user_id, self::SEEN_META, 1 );

        wp_safe_redirect( self::wizard_url() );
        exit;
    }


    /**
     * Show a dismissible reminder on Joinotify screens until the wizard is done.
     *
     * @since 2.3.0
     * @return void
     */
    public function render_notice() {
        if ( ! current_user_can('manage_options') || self::is_completed() ) {
            return;
        }

        $state = self::get_state();
        $current_page = $this->current_page();

        if ( $state['dismissed'] || self::PAGE === $current_page || 0 !== strpos( (string) $current_page, 'joinotify' ) ) {
            return;
        }

        printf(
            '<div class="notice notice-info is-dismissible joinotify-onboarding-notice"><p>%1$s</p><p><a class="button button-primary" href="%2$s">%3$s</a> <button type="button" class="button-link joinotify-onboarding-dismiss">%4$s</button></p></div>',
            esc_html__( 'Joinotify is not set up yet. The setup wizard connects your account and gets the first automation running in a couple of minutes.', 'joinotify' ),
            esc_url( self::wizard_url() ),
            esc_html__( 'Run the setup wizard', 'joinotify' ),
            esc_html__( 'Do not remind me again', 'joinotify' )
        );

        printf(
            '<script>document.addEventListener("click",function(e){if(!e.target.classList.contains("joinotify-onboarding-dismiss")){return;}e.preventDefault();var n=e.target.closest(".joinotify-onboarding-notice");if(n){n.style.display="none";}var b=new FormData();b.append("action","joinotify_dismiss_onboarding_notice");b.append("nonce","%1$s");fetch("%2$s",{method:"POST",body:b,credentials:"same-origin"});});</script>',
            esc_js( wp_create_nonce('joinotify_dismiss_onboarding') ),
            esc_url_raw( admin_url('admin-ajax.php') )
        );
    }


    /**
     * Persist the "do not remind me again" choice.
     *
     * @since 2.3.0
     * @return void
     */
    public function ajax_dismiss_notice() {
        if ( ! current_user_can('manage_options') ) {
            wp_send_json_error( array( 'message' => __( 'You do not have permission to do this.', 'joinotify' ) ), 403 );
        }

        $nonce = isset( $_POST['nonce'] ) ? sanitize_text_field( wp_unslash( $_POST['nonce'] ) ) : '';

        if ( ! wp_verify_nonce( $nonce, 'joinotify_dismiss_onboarding' ) ) {
            wp_send_json_error( array( 'message' => __( 'Security check failed.', 'joinotify' ) ), 403 );
        }

        self::dismiss();

        wp_send_json_success();
    }


    /**
     * Read the admin page slug being rendered.
     *
     * @since 2.3.0
     * @return string
     */
    private function current_page() {
        // Reading which screen is being rendered, not acting on a request.
        // phpcs:ignore WordPress.Security.NonceVerification.Recommended
        return isset( $_GET['page'] ) ? sanitize_text_field( wp_unslash( $_GET['page'] ) ) : '';
    }
}
