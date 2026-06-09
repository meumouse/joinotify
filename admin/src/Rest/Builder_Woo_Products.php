<?php

namespace MeuMouse\Joinotify\Rest;

use WP_REST_Request;
use WP_Query;

defined('ABSPATH') || exit;

/**
 * Search WooCommerce products for use in workflow conditions.
 *
 * @since 1.4.7
 */
class Builder_Woo_Products extends Abstract_Route {

    /**
     * Route path.
     *
     * @var string
     */
    protected $route = '/admin/builder/woo-products';

    /**
     * HTTP methods.
     *
     * @var string
     */
    protected $methods = 'GET';


    /**
     * Handle request.
     *
     * @param WP_REST_Request $request Request instance.
     * @return \WP_REST_Response
     */
    public function handle( WP_REST_Request $request ) {
        if ( ! class_exists( 'WooCommerce' ) ) {
            return rest_ensure_response( array(
                'status'   => 'error',
                'message'  => __( 'WooCommerce is not active.', 'joinotify' ),
                'products' => array(),
            ) );
        }

        $search_query = sanitize_text_field( $request->get_param( 'search' ) ?? '' );

        $args = array(
            'post_type'      => array( 'product', 'product_variation' ),
            'post_status'    => 'publish',
            'posts_per_page' => -1,
            's'              => $search_query,
        );

        $products_query = new WP_Query( $args );
        $results        = array();

        if ( $products_query->have_posts() ) {
            while ( $products_query->have_posts() ) {
                $products_query->the_post();
                $results[] = array(
                    'id'            => get_the_ID(),
                    'product_title' => html_entity_decode( get_the_title(), ENT_QUOTES, 'UTF-8' ),
                );
            }
        }

        wp_reset_postdata();

        return rest_ensure_response( array(
            'status'   => 'success',
            'products' => $results,
        ) );
    }
}
