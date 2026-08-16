<?php

namespace MeuMouse\Joinotify\Rest;

use MeuMouse\Joinotify\Api\Template_Repository;
use WP_REST_Request;

defined('ABSPATH') || exit;

/**
 * Reconcile the WhatsApp message template mirror with Meta.
 *
 * Only needed when a template was created straight in the Business Manager, or
 * when a status webhook is suspected lost — the mirror keeps itself up to date
 * on its own.
 *
 * @since 2.3.0
 */
class Builder_Whatsapp_Templates_Sync extends Abstract_Route {

    /**
     * Route path.
     *
     * @var string
     */
    protected $route = '/admin/builder/whatsapp-templates/sync';

    /**
     * Allowed HTTP methods.
     *
     * @var string
     */
    protected $methods = 'POST';


    /**
     * Handle the request.
     *
     * @since 2.3.0
     * @param WP_REST_Request $request REST request instance.
     * @return \WP_REST_Response
     */
    public function handle( WP_REST_Request $request ) {
        $result = Template_Repository::sync();

        if ( is_wp_error( $result ) ) {
            return $this->error_response( $result->get_error_message() );
        }

        $fresh = Template_Repository::get_templates( array( 'force' => true ) );

        if ( is_wp_error( $fresh ) ) {
            return $this->error_response( $fresh->get_error_message(), array( 'templates' => array() ) );
        }

        return $this->success_response( array_merge( $fresh, array(
            'message' => __( 'Message templates synced.', 'joinotify' ),
        ) ) );
    }
}
