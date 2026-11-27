<?php

namespace MeuMouse\Joinotify\Integrations;

use MeuMouse\Joinotify\Admin\Admin;

// Exit if accessed directly.
defined('ABSPATH') || exit;

/**
 * Deprecated integrations (Telegram and Resend)
 *
 * Joinotify is converging on WhatsApp only, the same channel the Joinotify
 * platform supports. The Telegram and Resend integrations stay working for the
 * sites that already use them until they are removed in REMOVAL_VERSION, but
 * they are no longer offered to anyone else. This class knows which builder
 * actions are deprecated, tells whether a site still uses an integration, and
 * warns the site owner about the published workflows that depend on one.
 *
 * @since 2.8.0
 * @package MeuMouse\Joinotify\Integrations
 * @author MeuMouse.com
 */
class Deprecated_Integrations {

    /**
     * Joinotify version that removes the deprecated integrations.
     *
     * @since 2.8.0
     * @var string
     */
    const REMOVAL_VERSION = '3.0.0';

    /**
     * Transient holding which workflows use each deprecated action.
     *
     * The settings defaults read the integration cards on most requests, and a
     * card asks whether its integration is in use, so the workflow lookup is
     * cached across requests and dropped whenever a workflow changes.
     *
     * @since 2.8.0
     * @var string
     */
    const USAGE_TRANSIENT = 'joinotify_deprecated_actions_usage';

    /**
     * Usage map memoized for the current request.
     *
     * @since 2.8.0
     * @var array<string,array<string,array<int,int>>>|null
     */
    private static $usage = null;

    /**
     * Construct function
     *
     * @since 2.8.0
     * @return void
     */
    public function __construct() {
        add_action( 'admin_notices', array( $this, 'notice_deprecated_workflows' ) );

        // a workflow saved, trashed, restored or deleted may add or drop a deprecated step
        add_action( 'save_post_joinotify-workflow', array( __CLASS__, 'flush_usage' ) );
        add_action( 'transition_post_status', array( $this, 'flush_usage_on_status_change' ), 10, 3 );
        add_action( 'deleted_post', array( __CLASS__, 'flush_usage' ) );
        add_action( 'added_post_meta', array( $this, 'flush_usage_on_content_change' ), 10, 3 );
        add_action( 'updated_post_meta', array( $this, 'flush_usage_on_content_change' ), 10, 3 );
    }


    /**
     * Drop the cached usage map.
     *
     * @since 2.8.0
     * @return void
     */
    public static function flush_usage() {
        self::$usage = null;
        delete_transient( self::USAGE_TRANSIENT );
    }


    /**
     * Drop the cached usage map when a workflow changes status.
     *
     * @since 2.8.0
     * @param string $new_status New post status.
     * @param string $old_status Previous post status.
     * @param \WP_Post $post Post object.
     * @return void
     */
    public function flush_usage_on_status_change( $new_status, $old_status, $post ) {
        if ( $new_status !== $old_status && $post instanceof \WP_Post && 'joinotify-workflow' === $post->post_type ) {
            self::flush_usage();
        }
    }


    /**
     * Drop the cached usage map when a workflow's content meta is written.
     *
     * The builder saves the content straight into post meta, which does not
     * always fire `save_post`.
     *
     * @since 2.8.0
     * @param int $meta_id Meta ID.
     * @param int $object_id Post ID.
     * @param string $meta_key Meta key.
     * @return void
     */
    public function flush_usage_on_content_change( $meta_id, $object_id, $meta_key ) {
        if ( 'joinotify_workflow_content' === $meta_key ) {
            self::flush_usage();
        }
    }


    /**
     * The deprecated integrations, keyed by integration slug.
     *
     * @since 2.8.0
     * @return array<string,array<string,mixed>>
     */
    public static function get_integrations() {
        return array(
            'telegram' => array(
                'label' => __( 'Telegram', 'joinotify' ),
                'setting_key' => 'enable_telegram_integration',
                'credential_keys' => array( 'telegram_bot_token' ),
                'action' => 'send_telegram_message_text',
            ),
            'resend' => array(
                'label' => __( 'Resend', 'joinotify' ),
                'setting_key' => 'enable_resend_integration',
                'credential_keys' => array( 'resend_api_key' ),
                'action' => 'send_resend_email',
            ),
        );
    }


    /**
     * The message shown wherever a deprecated integration appears.
     *
     * @since 2.8.0
     * @param string $label Integration name.
     * @return string
     */
    public static function get_message( $label ) {
        return sprintf(
            /* translators: 1: integration name (e.g. Telegram), 2: Joinotify version that removes it */
            __( '%1$s is deprecated and will be removed in Joinotify %2$s. Workflows that use it keep sending until then; move them to WhatsApp.', 'joinotify' ),
            $label,
            self::REMOVAL_VERSION
        );
    }


    /**
     * Whether a site still uses a deprecated integration.
     *
     * It counts as used when its toggle is on, when a credential is stored, or
     * when any workflow (published or not) contains its action. A site that
     * matches none of these never sees the integration again.
     *
     * @since 2.8.0
     * @param string $slug Integration slug (telegram|resend).
     * @return bool
     */
    public static function is_in_use( $slug ) {
        $integrations = self::get_integrations();

        if ( ! isset( $integrations[ $slug ] ) ) {
            return false;
        }

        $integration = $integrations[ $slug ];

        if ( 'yes' === Admin::get_setting( $integration['setting_key'] ) ) {
            return true;
        }

        foreach ( $integration['credential_keys'] as $key ) {
            if ( '' !== trim( (string) Admin::get_setting( $key ) ) ) {
                return true;
            }
        }

        return ! empty( self::get_workflows_using_action( $integration['action'], false ) );
    }


    /**
     * Find the workflows whose content contains an action.
     *
     * @since 2.8.0
     * @param string $action Action slug.
     * @param bool $published_only Only published (active) workflows when true; any non-trashed workflow otherwise.
     * @return array<int,int> Workflow post IDs.
     */
    public static function get_workflows_using_action( $action, $published_only = true ) {
        $usage = self::get_usage();
        $key = $published_only ? 'published' : 'any';

        return $usage[ $action ][ $key ] ?? array();
    }


    /**
     * Which workflows use each deprecated action, cached across requests.
     *
     * Matches the action slug anywhere in the stored workflow content, the same
     * LIKE lookup the processor falls back to for unindexed workflows.
     *
     * @since 2.8.0
     * @return array<string,array<string,array<int,int>>> Map of action slug => { any: ids, published: ids }.
     */
    private static function get_usage() {
        if ( null !== self::$usage ) {
            return self::$usage;
        }

        $cached = get_transient( self::USAGE_TRANSIENT );

        if ( is_array( $cached ) ) {
            self::$usage = $cached;

            return self::$usage;
        }

        $usage = array();

        foreach ( self::get_integrations() as $integration ) {
            $posts = get_posts( array(
                'post_type' => 'joinotify-workflow',
                'post_status' => array( 'publish', 'draft', 'pending', 'private', 'future' ),
                'numberposts' => -1,
                'no_found_rows' => true,
                'suppress_filters' => true,
                // phpcs:ignore WordPress.DB.SlowDBQuery.slow_db_query_meta_query -- Cached in a transient that is dropped whenever a workflow changes.
                'meta_query' => array(
                    array(
                        'key' => 'joinotify_workflow_content',
                        'value' => $integration['action'],
                        'compare' => 'LIKE',
                    ),
                ),
            ) );

            $any = array();
            $published = array();

            foreach ( $posts as $post ) {
                $any[] = (int) $post->ID;

                if ( 'publish' === $post->post_status ) {
                    $published[] = (int) $post->ID;
                }
            }

            $usage[ $integration['action'] ] = array(
                'any' => $any,
                'published' => $published,
            );
        }

        set_transient( self::USAGE_TRANSIENT, $usage, DAY_IN_SECONDS );
        self::$usage = $usage;

        return self::$usage;
    }


    /**
     * Warn the site owner about published workflows that use a deprecated integration.
     *
     * Shown only on the Plugins screen and on the Joinotify screens that keep
     * admin notices. The builder strips every notice, so it warns from inside
     * the app instead.
     *
     * @since 2.8.0
     * @return void
     */
    public function notice_deprecated_workflows() {
        if ( ! current_user_can('manage_options') ) {
            return;
        }

        $screen = function_exists('get_current_screen') ? get_current_screen() : null;
        $screen_id = $screen ? (string) $screen->id : '';

        // phpcs:ignore WordPress.Security.NonceVerification.Recommended -- Only reads which admin screen WordPress is already rendering; nothing is acted on.
        $current_page = isset( $_GET['page'] ) ? sanitize_key( wp_unslash( $_GET['page'] ) ) : '';

        if ( 'plugins' !== $screen_id && 0 !== strpos( $current_page, 'joinotify' ) ) {
            return;
        }

        $in_use = array();
        $workflow_ids = array();

        foreach ( self::get_integrations() as $integration ) {
            $ids = self::get_workflows_using_action( $integration['action'] );

            if ( empty( $ids ) ) {
                continue;
            }

            $in_use[] = $integration['label'];
            $workflow_ids = array_merge( $workflow_ids, $ids );
        }

        if ( empty( $in_use ) ) {
            return;
        }

        $count = count( array_unique( $workflow_ids ) );

        printf(
            '<div class="notice notice-warning"><p>%1$s <a href="%2$s">%3$s</a></p></div>',
            esc_html( sprintf(
                /* translators: 1: number of workflows, 2: integration names (e.g. Telegram, Resend), 3: Joinotify version that removes them */
                _n(
                    '%1$d active workflow uses a deprecated integration (%2$s) that will be removed in Joinotify %3$s. Replace those steps with WhatsApp messages before updating.',
                    '%1$d active workflows use a deprecated integration (%2$s) that will be removed in Joinotify %3$s. Replace those steps with WhatsApp messages before updating.',
                    $count,
                    'joinotify'
                ),
                $count,
                implode( ', ', $in_use ),
                self::REMOVAL_VERSION
            ) ),
            esc_url( admin_url('admin.php?page=joinotify-workflows') ),
            esc_html__( 'View workflows', 'joinotify' )
        );
    }
}
