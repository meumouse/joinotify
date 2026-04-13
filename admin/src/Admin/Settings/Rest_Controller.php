<?php

namespace MeuMouse\Joinotify\Admin\Settings;

defined('ABSPATH') || exit;

/**
 * Bootstrap all Joinotify REST endpoint classes used by the admin interface.
 */
class Rest_Controller {

    /**
     * REST endpoint classes that should be loaded for the admin UI.
     *
     * @var string[]
     */
    private $route_classes = array(
        '\MeuMouse\Joinotify\Rest\Settings_Bootstrap',
        '\MeuMouse\Joinotify\Rest\Settings_Save',
        '\MeuMouse\Joinotify\Rest\Phone_Candidates',
        '\MeuMouse\Joinotify\Rest\Phone_Register',
        '\MeuMouse\Joinotify\Rest\Phone_Validate_Otp',
        '\MeuMouse\Joinotify\Rest\Phone_Remove',
        '\MeuMouse\Joinotify\Rest\Phone_Test_Message',
        '\MeuMouse\Joinotify\Rest\Phone_Check_Connection',
        '\MeuMouse\Joinotify\Rest\Debug_Logs',
        '\MeuMouse\Joinotify\Rest\Debug_Clear',
        '\MeuMouse\Joinotify\Rest\Settings_Reset',
        '\MeuMouse\Joinotify\Rest\Send_Text_Message',
        '\MeuMouse\Joinotify\Rest\Send_Media_Message',
    );


    /**
     * Register the route classes on construction.
     */
    public function __construct() {
        foreach ( $this->route_classes as $class ) {
            if ( class_exists( $class ) ) {
                new $class();
            }
        }
    }
}
