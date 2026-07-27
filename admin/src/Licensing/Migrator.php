<?php

namespace MeuMouse\Joinotify\Licensing;

use MeuMouse\Joinotify\Api\License;
use MeuMouse\Joinotify\Core\Logger;
use MeuMouse\Joinotify\Licensing\Drivers\Mds_Driver;

// Exit if accessed directly.
defined('ABSPATH') || exit;

/**
 * Moves an already-activated site from the legacy licensing server to MDS.
 *
 * License keys survive the server migration unchanged and existing domain
 * activations were imported with them, so a site can simply re-check its stored
 * key against the new server and carry on. That check runs in the background:
 * doing it inline would make the first admin page load after the update wait on
 * a network round-trip.
 *
 * The one rule this class will not break is that it never deactivates anything.
 * If the new server disagrees with the local state, that is flagged for a human
 * rather than acted on. A wrong "valid" costs nothing for a few hours; a wrong
 * "invalid" silently stops a paying customer's notifications.
 *
 * @since 2.1.0
 * @package MeuMouse\Joinotify\Licensing
 * @author MeuMouse.com
 */
class Migrator {

    /**
     * Scheduled event that performs the re-check.
     *
     * @since 2.1.0
     * @var string
     */
    const EVENT = 'joinotify_license_migrate_event';

    /**
     * Option holding a copy of the license state from before the migration.
     *
     * @since 2.1.0
     * @var string
     */
    const BACKUP_OPTION = 'joinotify_license_response_object_backup';

    /**
     * Option counting how many times the re-check could not reach a server.
     *
     * @since 2.1.0
     * @var string
     */
    const ATTEMPTS_OPTION = 'joinotify_license_migration_attempts';

    /**
     * Option set when the new server disagrees with the stored license.
     *
     * @since 2.1.0
     * @var string
     */
    const PENDING_OPTION = 'joinotify_license_migration_pending';

    /**
     * Backoff between attempts, in seconds. The last value repeats.
     *
     * @since 2.1.0
     * @var array
     */
    const BACKOFF = array( HOUR_IN_SECONDS, 6 * HOUR_IN_SECONDS, DAY_IN_SECONDS );


    /**
     * Register the scheduled event handler.
     *
     * @since 2.1.0
     * @return void
     */
    public function __construct() {
        add_action( self::EVENT, array( __CLASS__, 'run' ) );
    }


    /**
     * Upgrade routine: take a snapshot and queue the re-check.
     *
     * @since 2.1.0
     * @return void
     */
    public static function schedule_migration() {
        $license_key = get_option('joinotify_license_key');

        // Nothing activated locally means nothing to migrate; the next
        // activation will simply go to whichever server answers.
        if ( empty( $license_key ) ) {
            return;
        }

        if ( Mds_Driver::ID === Driver_State::current() ) {
            return;
        }

        $license = get_option('joinotify_license_response_object');

        if ( is_object( $license ) && ! get_option( self::BACKUP_OPTION ) ) {
            update_option( self::BACKUP_OPTION, $license );
        }

        self::queue( 0 );
    }


    /**
     * Re-check the stored key against the new server.
     *
     * @since 2.1.0
     * @return void
     */
    public static function run() {
        $license_key = get_option('joinotify_license_key');

        if ( empty( $license_key ) ) {
            self::finish();

            return;
        }

        if ( Mds_Driver::ID === Driver_State::current() ) {
            self::finish();

            return;
        }

        $result = ( new Mds_Driver() )->validate( $license_key );

        if ( $result->is_transport_failure() ) {
            self::retry( $result->message() );

            return;
        }

        if ( $result->is_valid() ) {
            self::adopt( $result, $license_key );

            return;
        }

        self::flag_disagreement( $result );
    }


    /**
     * The new server confirmed the license: switch over.
     *
     * @since 2.1.0
     * @param Dto\License_Result $result | Validation result
     * @param string $license_key | License key
     * @return void
     */
    protected static function adopt( $result, $license_key ) {
        $error = '';
        $response = null;

        // Re-runs the normal validation path so the stored object, the caches
        // and the expiry schedule are all written exactly as they would be by
        // an ordinary check, instead of by a parallel code path that has to be
        // kept in sync with it.
        Driver_State::elect( Mds_Driver::ID, 'migrated from the legacy licensing server' );

        License::check_license( $license_key, $error, $response, defined('JOINOTIFY_FILE') ? JOINOTIFY_FILE : '' );
        License::persist_status_from_response( $response );

        Logger::register_log( 'License migrated to the MDS licensing server.', 'INFO' );

        self::finish();
    }


    /**
     * Neither server could be reached: try again later, changing nothing.
     *
     * @since 2.1.0
     * @param string $reason | Why the attempt failed
     * @return void
     */
    protected static function retry( $reason ) {
        $attempts = (int) get_option( self::ATTEMPTS_OPTION, 0 ) + 1;

        update_option( self::ATTEMPTS_OPTION, $attempts );

        Logger::register_log( sprintf( 'License migration attempt %d could not reach the licensing server: %s', $attempts, $reason ), 'INFO' );

        self::queue( $attempts );
    }


    /**
     * The new server answered, and disagrees with what is stored locally.
     *
     * Recorded, never acted on. A mismatch here is far more likely to be a
     * record that did not come across in the server migration than a customer
     * who should lose access, and the cost of guessing wrong is a silent outage
     * for someone who paid.
     *
     * @since 2.1.0
     * @param Dto\License_Result $result | Validation result
     * @return void
     */
    protected static function flag_disagreement( $result ) {
        update_option( self::PENDING_OPTION, array(
            'reason' => (string) $result->get( 'reason', '' ),
            'message' => $result->message(),
            'flagged_at' => time(),
        ));

        Logger::register_log( sprintf(
            'License migration needs attention: the new licensing server reported "%s" for a license active on this site.',
            $result->message()
        ), 'ERROR' );

        self::finish();
    }


    /**
     * Schedule the next attempt.
     *
     * @since 2.1.0
     * @param int $attempts | Attempts made so far
     * @return void
     */
    protected static function queue( $attempts ) {
        if ( wp_next_scheduled( self::EVENT ) ) {
            return;
        }

        $attempts = (int) $attempts;
        $backoff = self::BACKOFF;

        if ( $attempts < 1 ) {
            // The first pass is spread over a few minutes rather than fired at
            // once: every site on a host updates within the same window, and
            // they should not all call the licensing server in the same second.
            $delay = wp_rand( MINUTE_IN_SECONDS, 15 * MINUTE_IN_SECONDS );
        } else {
            // The last interval repeats, so an outage that outlasts the table
            // never leaves a site stranded on a server being switched off.
            $delay = $backoff[ min( $attempts - 1, count( $backoff ) - 1 ) ];
        }

        wp_schedule_single_event( time() + $delay, self::EVENT );
    }


    /**
     * Stop retrying and clear the bookkeeping.
     *
     * @since 2.1.0
     * @return void
     */
    protected static function finish() {
        wp_clear_scheduled_hook( self::EVENT );
        delete_option( self::ATTEMPTS_OPTION );
    }


    /**
     * Whether a human needs to look at this site's license.
     *
     * @since 2.1.0
     * @return array|null
     */
    public static function pending_notice() {
        $pending = get_option( self::PENDING_OPTION );

        return is_array( $pending ) ? $pending : null;
    }


    /**
     * Clear the disagreement flag, once the license is sorted out.
     *
     * @since 2.1.0
     * @return void
     */
    public static function clear_pending() {
        delete_option( self::PENDING_OPTION );
    }
}
