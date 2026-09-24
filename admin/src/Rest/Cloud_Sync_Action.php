<?php

namespace MeuMouse\Joinotify\Rest;

use MeuMouse\Joinotify\Cloud_Sync\Backfill;
use MeuMouse\Joinotify\Cloud_Sync\Dispatcher;
use MeuMouse\Joinotify\Cloud_Sync\Outbox;
use MeuMouse\Joinotify\Cloud_Sync\Reporter;
use MeuMouse\Joinotify\Cloud_Sync\State;
use WP_REST_Request;

defined('ABSPATH') || exit;

/**
 * What the owner can do from the sync's monitor: send again what was given up on, discard it,
 * try again after a pause, and start or stop sending the existing customers.
 *
 * @since 2.5.0
 */
class Cloud_Sync_Action extends Abstract_Route {

    /**
     * Route path.
     *
     * @var string
     */
    protected $route = '/admin/cloud-sync/action';

    /**
     * Allowed HTTP methods.
     *
     * @var string
     */
    protected $methods = 'POST';


    /**
     * Handle the request.
     *
     * @since 2.5.0
     * @param WP_REST_Request $request REST request instance.
     * @return \WP_REST_Response
     */
    public function handle( WP_REST_Request $request ) {
        switch ( (string) $request->get_param( 'action' ) ) {
            case 'retry_dead':
                $count = Outbox::requeue_dead();
                Dispatcher::schedule_soon();

                /* translators: %d: number of items */
                return $this->success_response( array( 'message' => sprintf( _n( '%d item will be sent again.', '%d items will be sent again.', $count, 'joinotify' ), $count ) ) );

            case 'discard_dead':
                $count = Outbox::discard_dead();

                /* translators: %d: number of items */
                return $this->success_response( array( 'message' => sprintf( _n( '%d item discarded.', '%d items discarded.', $count, 'joinotify' ), $count ) ) );

            case 'resume':
                // The next report decides: a copy of the site at another address pauses again.
                State::resume();
                Reporter::schedule();
                Dispatcher::schedule_soon();

                return $this->success_response( array( 'message' => __( 'The sync will try again in a moment.', 'joinotify' ) ) );

            case 'backfill_start':
                $run = Backfill::start();

                if ( is_wp_error( $run ) ) {
                    return $this->error_response( $run->get_error_message() );
                }

                return $this->success_response( array( 'message' => __( 'Sending existing customers. You can leave this page.', 'joinotify' ) ) );

            case 'backfill_cancel':
                Backfill::cancel();

                return $this->success_response( array( 'message' => __( 'Stopped. Customers already queued will still be sent.', 'joinotify' ) ) );
        }

        return $this->error_response( __( 'Unknown action.', 'joinotify' ) );
    }
}
