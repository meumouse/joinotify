<?php

namespace MeuMouse\Joinotify\Rest;

defined('ABSPATH') || exit;

/**
 * Base controller that instantiates a declared list of REST endpoint classes.
 *
 * Subclasses only need to declare the `$route_classes` they own; this base
 * handles the (previously duplicated) "instantiate each existing class" loop.
 *
 * @since 2.0.0
 */
abstract class Abstract_Rest_Controller {

    /**
     * Fully-qualified REST endpoint class names owned by this controller.
     *
     * @var string[]
     */
    protected $route_classes = array();


    /**
     * Register every available route class on construction.
     */
    public function __construct() {
        foreach ( $this->route_classes as $class ) {
            if ( class_exists( $class ) ) {
                new $class();
            }
        }
    }
}
