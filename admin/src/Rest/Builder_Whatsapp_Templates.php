<?php

namespace MeuMouse\Joinotify\Rest;

use MeuMouse\Joinotify\Api\Template_Repository;
use WP_REST_Request;

defined('ABSPATH') || exit;

/**
 * List the WhatsApp message templates approved on the customer's account.
 *
 * Distinct from Builder_Templates, which serves the Joinotify workflow template
 * catalog: these are the Meta-approved message templates a Cloud API account
 * owns, and the only way to write outside the 24-hour window.
 *
 * @since 2.3.0
 */
class Builder_Whatsapp_Templates extends Abstract_Route {

    /**
     * Route path.
     *
     * @var string
     */
    protected $route = '/admin/builder/whatsapp-templates';

    /**
     * Allowed HTTP methods.
     *
     * @var string
     */
    protected $methods = 'GET';


    /**
     * Handle the request.
     *
     * @since 2.3.0
     * @param WP_REST_Request $request REST request instance.
     * @return \WP_REST_Response
     */
    public function handle( WP_REST_Request $request ) {
        $result = Template_Repository::get_templates( array(
            'status' => sanitize_text_field( (string) $request->get_param('status') ),
            'waba_id' => sanitize_text_field( (string) $request->get_param('waba_id') ),
            'force' => 'true' === (string) $request->get_param('refresh'),
        ) );

        if ( is_wp_error( $result ) ) {
            return $this->error_response( $result->get_error_message(), array( 'templates' => array() ) );
        }

        return $this->success_response( $result );
    }
}
