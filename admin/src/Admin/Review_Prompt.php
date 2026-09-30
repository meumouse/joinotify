<?php

namespace MeuMouse\Joinotify\Admin;

use MeuMouse\Joinotify\Core\Onboarding;

// Exit if accessed directly.
defined('ABSPATH') || exit;

/**
 * Decides when the settings screen and the workflow builder ask for a WordPress.org review.
 *
 * The request only appears once the site has had Joinotify for a while — ten minutes
 * by default, counted from the first admin request that loaded the plugin — so
 * nobody is asked to rate something they have not tried yet. The answer is kept
 * per user: "Leave a review" and "I already did" end the prompt for good, while
 * "Maybe later" (or closing the dialog) hides it for a week.
 *
 * Nothing here talks to an external server: the review link simply opens the
 * plugin's WordPress.org review page in a new tab.
 *
 * @since 2.5.0
 * @package MeuMouse\Joinotify\Admin
 * @author MeuMouse.com
 */
class Review_Prompt {

    /**
     * Option holding the timestamp of the first admin request with the plugin active.
     *
     * @since 2.5.0
     * @var string
     */
    const FIRST_USE_OPTION = 'joinotify_first_use_at';

    /**
     * User meta holding the user's answer to the prompt.
     *
     * @since 2.5.0
     * @var string
     */
    const USER_META = 'joinotify_review_prompt';

    /**
     * Answers the frontend may send.
     *
     * @since 2.5.0
     * @var string[]
     */
    const STATUSES = array( 'rated', 'dismissed', 'later' );


    /**
     * Record the first use on the admin request that instantiated this class.
     *
     * Runs from `Init::instance_admin_init_classes()`, which is already on
     * `admin_init`, so the work happens right away instead of on a new hook.
     *
     * @since 2.5.0
     * @return void
     */
    public function init() {
        self::get_first_use();
    }


    /**
     * Timestamp of the plugin's first use on this site, recording it when missing.
     *
     * Sites that ran Joinotify before this option existed are backdated to the
     * earliest trace they left — the setup wizard completion or the oldest
     * workflow — so a long-time user is not made to wait again.
     *
     * @since 2.5.0
     * @return int
     */
    public static function get_first_use() {
        $first_use = (int) get_option( self::FIRST_USE_OPTION, 0 );

        if ( $first_use > 0 ) {
            return $first_use;
        }

        $first_use = time();
        $onboarding = Onboarding::get_state();

        if ( $onboarding['completed_at'] > 0 ) {
            $first_use = min( $first_use, $onboarding['completed_at'] );
        }

        $oldest_workflow = get_posts( array(
            'post_type' => 'joinotify-workflow',
            'post_status' => 'any',
            'numberposts' => 1,
            'orderby' => 'date',
            'order' => 'ASC',
            'fields' => 'ids',
            'no_found_rows' => true,
            'suppress_filters' => true,
        ) );

        if ( ! empty( $oldest_workflow ) ) {
            $created = (int) get_post_time( 'U', true, $oldest_workflow[0] );

            if ( $created > 0 ) {
                $first_use = min( $first_use, $created );
            }
        }

        add_option( self::FIRST_USE_OPTION, $first_use, '', 'no' );

        return $first_use;
    }


    /**
     * Read the user's stored answer.
     *
     * @since 2.5.0
     * @param int $user_id User ID.
     * @return array{status:string,until:int}
     */
    public static function get_user_state( $user_id ) {
        $state = get_user_meta( $user_id, self::USER_META, true );
        $state = is_array( $state ) ? $state : array();
        $status = isset( $state['status'] ) ? sanitize_key( $state['status'] ) : '';

        return array(
            'status' => in_array( $status, self::STATUSES, true ) ? $status : '',
            'until' => isset( $state['until'] ) ? (int) $state['until'] : 0,
        );
    }


    /**
     * Store the user's answer to the prompt.
     *
     * @since 2.5.0
     * @param int    $user_id User ID.
     * @param string $status One of `rated`, `dismissed` or `later`.
     * @return bool False when the status is not recognized.
     */
    public static function update_user_state( $user_id, $status ) {
        if ( ! in_array( $status, self::STATUSES, true ) ) {
            return false;
        }

        /**
         * Filter how long "Maybe later" hides the review prompt, in seconds.
         *
         * @since 2.5.0
         * @param int $snooze Snooze length. Default one week.
         */
        $snooze = (int) apply_filters( 'Joinotify/Admin/Review_Prompt/Snooze', WEEK_IN_SECONDS );

        update_user_meta( $user_id, self::USER_META, array(
            'status' => $status,
            'until' => 'later' === $status ? time() + max( 0, $snooze ) : 0,
        ) );

        return true;
    }


    /**
     * Whether the current user should see the prompt now.
     *
     * @since 2.5.0
     * @return bool
     */
    public static function should_show() {
        if ( ! current_user_can( 'manage_options' ) ) {
            return false;
        }

        /**
         * Filter the minimum time the plugin must be in use before the prompt appears, in seconds.
         *
         * @since 2.5.0
         * @param int $delay Delay. Default ten minutes.
         */
        $delay = (int) apply_filters( 'Joinotify/Admin/Review_Prompt/Delay', 10 * MINUTE_IN_SECONDS );

        if ( time() - self::get_first_use() < max( 0, $delay ) ) {
            return false;
        }

        $state = self::get_user_state( get_current_user_id() );

        if ( 'rated' === $state['status'] || 'dismissed' === $state['status'] ) {
            return false;
        }

        if ( 'later' === $state['status'] && time() < $state['until'] ) {
            return false;
        }

        /**
         * Filter whether the settings screen and the workflow builder show the review prompt.
         *
         * @since 2.5.0
         * @param bool $show Whether to show the prompt.
         */
        return (bool) apply_filters( 'Joinotify/Admin/Review_Prompt/Show', true );
    }


    /**
     * Payload consumed by the settings and builder applications.
     *
     * @since 2.5.0
     * @return array{show:bool,review_url:string}
     */
    public static function get_client_payload() {
        return array(
            'show' => self::should_show(),
            'review_url' => esc_url_raw( apply_filters( 'Joinotify/Admin/Review_Prompt/Url', 'https://wordpress.org/support/plugin/joinotify/reviews/#new-post' ) ),
        );
    }
}
