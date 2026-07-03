<?php

namespace MeuMouse\Joinotify\Core;

use MeuMouse\Joinotify\Admin\Builder\Workflow_Migrator;

// Exit if accessed directly.
defined('ABSPATH') || exit;

/**
 * Central upgrade manager.
 *
 * Owns the single decision of "did the installed version change, and what must
 * run to bring this site's stored data up to the current schema?". Previously
 * this responsibility was scattered: the version stamp lived in
 * Admin::update_default_options and the actual migrations were loose
 * `Joinotify/Upgraded` listeners registered from unrelated classes (Cron\Schedule,
 * Core\Message_History). That had two flaws this class fixes:
 *
 *  1. A fresh install and an upgrade from a pre-2.0.0 build were
 *     indistinguishable (both report an empty stored version, because versions
 *     prior to 2.0.0 never wrote the `joinotify_version` option). This class
 *     looks for a legacy data footprint to tell them apart and infers a usable
 *     previous version so version-gated routines actually fire on legacy sites.
 *
 *  2. The version stamp was written even if a migration threw mid-way, so a
 *     failed upgrade never retried. Here each routine is tracked individually:
 *     a routine that fails is simply left out of the completed list and runs
 *     again on the next admin load, while routines that succeeded are never
 *     repeated.
 *
 * Core migrations run as tracked routines; the public `Joinotify/Upgraded`
 * action is still fired afterwards so third-party extensions keep working.
 *
 * @since 2.0.0
 * @package MeuMouse\Joinotify\Core
 * @author MeuMouse.com
 */
class Upgrader {

    /**
     * Option storing the last fully-booted plugin version.
     *
     * @since 2.0.0
     * @var string
     */
    const VERSION_OPTION = 'joinotify_version';

    /**
     * Option storing the ids of upgrade routines that have completed.
     *
     * @since 2.0.0
     * @var string
     */
    const COMPLETED_OPTION = 'joinotify_completed_upgrades';

    /**
     * Synthetic "previous version" assumed when the site clearly ran a
     * pre-2.0.0 build (legacy footprint present) but never recorded a version.
     * Any value below the lowest migration target works; it just needs to make
     * the version-gated routines run.
     *
     * @since 2.0.0
     * @var string
     */
    const LEGACY_FALLBACK_VERSION = '1.0.0';

    /**
     * Version reported for a genuinely fresh install (no prior data).
     *
     * @since 2.0.0
     * @var string
     */
    const FRESH_INSTALL_VERSION = '0.0.0';


    /**
     * Construct function.
     *
     * @since 2.0.0
     * @return void
     */
    public function __construct() {
        // Run after Admin::update_default_options (admin_init @99) so newly
        // shipped settings keys are already backfilled before migrations run.
        add_action( 'admin_init', array( $this, 'maybe_upgrade' ), 110 );
    }


    /**
     * Detect an install/upgrade and run any pending migration routines.
     *
     * @since 2.0.0
     * @return void
     */
    public function maybe_upgrade() {
        $current = defined('JOINOTIFY_VERSION') ? (string) JOINOTIFY_VERSION : '';

        // Nothing to compare against if the constant is somehow unavailable.
        if ( '' === $current ) {
            return;
        }

        $stored = (string) get_option( self::VERSION_OPTION, '' );
        $is_fresh = '' === $stored && ! self::has_legacy_footprint();
        $from = self::resolve_previous_version( $stored, $is_fresh );

        // Version-gated, individually-tracked routines (retried on failure).
        self::run_pending_routines( $from, $current );

        if ( $stored !== $current ) {
            /**
             * Fires once after the plugin is installed or upgraded, once the
             * core migration routines have run.
             *
             * Third-party extensions hook here to run their own version-gated
             * migrations. `$from` is the resolved previous version: an empty
             * stored version is reported as either the fresh-install sentinel
             * or the legacy-fallback version depending on the data footprint.
             *
             * @since 2.0.0
             * @param string $from    Resolved previous version.
             * @param string $current Current plugin version.
             */
            do_action( 'Joinotify/Upgraded', $from, $current );

            update_option( self::VERSION_OPTION, $current );
        }
    }


    /**
     * Resolve the version the site is upgrading from.
     *
     * Pure helper (no WordPress calls) so it can be unit tested in isolation.
     *
     * @since 2.0.0
     * @param string $stored   Stored `joinotify_version` value ('' when absent).
     * @param bool   $is_fresh Whether this is a genuinely fresh install.
     * @return string
     */
    public static function resolve_previous_version( $stored, $is_fresh ) {
        $stored = is_scalar( $stored ) ? trim( (string) $stored ) : '';

        if ( '' !== $stored ) {
            return $stored;
        }

        // Empty stored version: a pre-2.0.0 build (which never wrote it) or a
        // brand-new install. The footprint check decides which.
        return $is_fresh ? self::FRESH_INSTALL_VERSION : self::LEGACY_FALLBACK_VERSION;
    }


    /**
     * Whether the site carries data from a previous Joinotify install.
     *
     * Used to tell a legacy upgrade apart from a fresh install when no version
     * was ever stored. Any one of: stored settings, a saved license key, or at
     * least one workflow post is enough.
     *
     * @since 2.0.0
     * @return bool
     */
    public static function has_legacy_footprint() {
        if ( function_exists('get_option') ) {
            $settings = get_option( 'joinotify_settings', array() );

            if ( is_array( $settings ) && ! empty( $settings ) ) {
                return true;
            }

            if ( '' !== (string) get_option( 'joinotify_license_key', '' ) ) {
                return true;
            }
        }

        if ( function_exists('get_posts') ) {
            $workflows = get_posts( array(
                'post_type' => 'joinotify-workflow',
                'post_status' => 'any',
                'numberposts' => 1,
                'fields' => 'ids',
            ));

            if ( ! empty( $workflows ) ) {
                return true;
            }
        }

        return false;
    }


    /**
     * Ordered list of upgrade routines.
     *
     * Each routine is { id, version, callback }. `version` is the schema version
     * the routine brings the site up to; the routine runs only when the resolved
     * previous version predates it. `id` keys completion tracking so a routine
     * runs at most once per site. Third parties register additional routines via
     * the `Joinotify/Upgrade/Routines` filter; routines run in ascending target
     * version order.
     *
     * Routine callbacks receive ($from_version, $current_version) and must be
     * idempotent — a routine may re-run if a previous attempt failed.
     *
     * @since 2.0.0
     * @return array<int,array<string,mixed>>
     */
    public static function get_routines() {
        $routines = array(
            array(
                'id' => 'workflows_schema_2_0_0',
                'version' => '2.0.0',
                'callback' => array( Workflow_Migrator::class, 'migrate_stored_workflows' ),
            ),
        );

        if ( function_exists('apply_filters') ) {
            $routines = apply_filters( 'Joinotify/Upgrade/Routines', $routines );
        }

        usort( $routines, function ( $left, $right ) {
            return version_compare(
                isset( $left['version'] ) ? (string) $left['version'] : '0',
                isset( $right['version'] ) ? (string) $right['version'] : '0'
            );
        });

        return $routines;
    }


    /**
     * Filter the routines down to those that still need to run.
     *
     * Pure helper (no WordPress calls beyond `get_routines`) so the gating logic
     * can be unit tested.
     *
     * @since 2.0.0
     * @param string           $from      Resolved previous version.
     * @param array<int,string> $completed Ids of already-completed routines.
     * @return array<int,array<string,mixed>>
     */
    public static function get_pending_routines( $from, $completed ) {
        $completed = is_array( $completed ) ? $completed : array();
        $pending = array();

        foreach ( self::get_routines() as $routine ) {
            $id = isset( $routine['id'] ) ? (string) $routine['id'] : '';
            $version = isset( $routine['version'] ) ? (string) $routine['version'] : '';

            if ( '' === $id || '' === $version ) {
                continue;
            }

            if ( in_array( $id, $completed, true ) ) {
                continue;
            }

            // Run only when the prior install predates the routine's target.
            if ( version_compare( $from, $version, '<' ) ) {
                $pending[] = $routine;
            }
        }

        return $pending;
    }


    /**
     * Run every pending routine, marking each as completed only on success.
     *
     * A routine that throws is logged and left out of the completed list, so it
     * is retried on the next admin load instead of being silently skipped.
     *
     * @since 2.0.0
     * @param string $from    Resolved previous version.
     * @param string $current Current plugin version.
     * @return void
     */
    public static function run_pending_routines( $from, $current ) {
        $completed = get_option( self::COMPLETED_OPTION, array() );
        $completed = is_array( $completed ) ? $completed : array();
        $pending = self::get_pending_routines( $from, $completed );

        if ( empty( $pending ) ) {
            return;
        }

        foreach ( $pending as $routine ) {
            $id = (string) $routine['id'];
            $callback = isset( $routine['callback'] ) ? $routine['callback'] : null;

            if ( ! is_callable( $callback ) ) {
                continue;
            }

            try {
                call_user_func( $callback, $from, $current );

                $completed[] = $id;
                update_option( self::COMPLETED_OPTION, array_values( array_unique( $completed ) ) );

                Logger::register_log( sprintf( 'Joinotify upgrade routine "%s" completed (from %s to %s).', $id, $from, $current ), 'INFO' );
            } catch ( \Throwable $e ) {
                Logger::register_log( sprintf( 'Joinotify upgrade routine "%s" failed: %s. Will retry on next load.', $id, $e->getMessage() ), 'ERROR' );
            }
        }
    }
}
