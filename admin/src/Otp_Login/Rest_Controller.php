<?php

namespace MeuMouse\Joinotify\Otp_Login;

use MeuMouse\Joinotify\Rest\Abstract_Rest_Controller;

defined('ABSPATH') || exit;

/**
 * Bootstrap the public REST endpoints used by the passwordless login widget.
 *
 * @since 2.0.0
 * @package MeuMouse\Joinotify\Otp_Login
 * @author MeuMouse.com
 */
class Rest_Controller extends Abstract_Rest_Controller {

    /**
     * REST endpoint classes owned by the OTP login flow.
     *
     * @since 2.0.0
     * @var string[]
     */
    protected $route_classes = array(
        '\MeuMouse\Joinotify\Rest\Otp_Request_Code',
        '\MeuMouse\Joinotify\Rest\Otp_Verify_Code',
        '\MeuMouse\Joinotify\Rest\Otp_Password_Login',
        '\MeuMouse\Joinotify\Rest\Otp_Register',
    );
}
