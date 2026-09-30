<?php

namespace MeuMouse\Joinotify\Rest;

use MeuMouse\Joinotify\Cloud_Sync\Backfill;
use MeuMouse\Joinotify\Cloud_Sync\Cloud_Sync;
use MeuMouse\Joinotify\Cloud_Sync\Outbox;
use MeuMouse\Joinotify\Cloud_Sync\Reporter;
use MeuMouse\Joinotify\Cloud_Sync\State;
use WP_REST_Request;

defined('ABSPATH') || exit;

/**
 * The sync's monitor: whether it runs, why it is paused, what waits in the outbox, what was given
 * up on, and how the backfill goes. `?estimate=1` adds how many customers a backfill would send.
 *
 * @since 2.5.0
 */
class Cloud_Sync_Status extends Abstract_Route {

    /**
     * Route path.
     *
     * @var string
     */
    protected $route = '/admin/cloud-sync/status';

    /**
     * Allowed HTTP methods.
     *
     * @var string
     */
    protected $methods = 'GET';


    /**
     * Handle the request.
     *
     * @since 2.5.0
     * @param WP_REST_Request $request REST request instance.
     * @return \WP_REST_Response
     */
    public function handle( WP_REST_Request $request ) {
        $state = State::load();

        $data = array(
            'enabled' => Cloud_Sync::is_enabled(),
            'site_url' => Reporter::origin( home_url() ),
            'paused' => (string) $state['paused'],
            'registered_url' => (string) $state['registered_url'],
            'mismatch_url' => (string) $state['mismatch_url'],
            'reported' => '' !== (string) $state['site_id'],
            'last_error' => (string) $state['last_error'],
            'last_success_at' => $state['last_success_at'] ? gmdate( 'Y-m-d\TH:i:s\Z', (int) $state['last_success_at'] ) : null,
            'outbox' => Outbox::summary(),
            'dead' => Outbox::recent_dead( 10 ),
            'backfill' => Backfill::report(),
        );

        if ( $request->get_param( 'estimate' ) ) {
            $data['estimate'] = Backfill::estimate();
        }

        return $this->success_response( $data );
    }
}
