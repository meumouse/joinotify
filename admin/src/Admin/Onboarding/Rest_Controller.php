<?php

namespace MeuMouse\Joinotify\Admin\Onboarding;

use MeuMouse\Joinotify\Rest\Abstract_Rest_Controller;

defined('ABSPATH') || exit;

/**
 * Bootstrap the REST endpoints backing the setup wizard.
 *
 * @since 2.3.0
 * @package MeuMouse\Joinotify\Admin\Onboarding
 * @author MeuMouse.com
 */
class Rest_Controller extends Abstract_Rest_Controller {

    /**
     * REST endpoint classes owned by the wizard.
     *
     * @var string[]
     */
    protected $route_classes = array(
        '\MeuMouse\Joinotify\Rest\Onboarding_Bootstrap',
        '\MeuMouse\Joinotify\Rest\Onboarding_Step',
        '\MeuMouse\Joinotify\Rest\Onboarding_Connect',
        '\MeuMouse\Joinotify\Rest\Onboarding_Complete',
        '\MeuMouse\Joinotify\Rest\Onboarding_Skip',
    );
}
