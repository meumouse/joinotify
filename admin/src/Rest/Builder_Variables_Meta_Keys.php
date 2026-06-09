<?php

namespace MeuMouse\Joinotify\Rest;

use MeuMouse\Joinotify\Builder\Custom_Variables;
use WP_REST_Request;

defined('ABSPATH') || exit;

/**
 * List example meta keys for a post type, sampled from a real post.
 *
 * @since 2.0.0
 */
class Builder_Variables_Meta_Keys extends Abstract_Route {

    /**
     * Route path.
     *
     * @var string
     */
    protected $route = '/admin/builder-variables/meta-keys';

    /**
     * Allowed HTTP methods.
     *
     * @var string
     */
    protected $methods = 'GET';


    /**
     * Handle the request.
     *
     * @param WP_REST_Request $request REST request instance.
     * @return \WP_REST_Response
     */
    public function handle( WP_REST_Request $request ) {
        $post_type = (string) $request->get_param( 'post_type' );
        $post_id = absint( $request->get_param( 'post_id' ) );

        $result = Custom_Variables::get_example_meta_keys( $post_type, $post_id );

        if ( empty( $result['keys'] ) ) {
            return $this->success_response( array(
                'post_id' => $result['post_id'],
                'keys'    => array(),
                'empty_message' => __( 'No example post or meta keys were found for this entity.', 'joinotify' ),
            ) );
        }

        return $this->success_response( array(
            'post_id' => $result['post_id'],
            'keys'    => $result['keys'],
        ) );
    }
}
