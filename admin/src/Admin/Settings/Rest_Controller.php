<?php

namespace MeuMouse\Joinotify\Admin\Settings;

use MeuMouse\Joinotify\Rest\Abstract_Rest_Controller;

defined('ABSPATH') || exit;

/**
 * Bootstrap all Joinotify REST endpoint classes used by the admin interface.
 */
class Rest_Controller extends Abstract_Rest_Controller {

    /**
     * REST endpoint classes that should be loaded for the admin UI.
     *
     * @var string[]
     */
    protected $route_classes = array(
        '\MeuMouse\Joinotify\Rest\Settings_Bootstrap',
        '\MeuMouse\Joinotify\Rest\Settings_Save',
        '\MeuMouse\Joinotify\Rest\Phone_Candidates',
        '\MeuMouse\Joinotify\Rest\Phone_Register',
        '\MeuMouse\Joinotify\Rest\Phone_Validate_Otp',
        '\MeuMouse\Joinotify\Rest\Phone_Remove',
        '\MeuMouse\Joinotify\Rest\Phone_Test_Message',
        '\MeuMouse\Joinotify\Rest\Phone_Check_Connection',
        '\MeuMouse\Joinotify\Rest\Cloud_Numbers',
        '\MeuMouse\Joinotify\Rest\Cloud_Numbers_Sync',
        '\MeuMouse\Joinotify\Rest\Cloud_Connect_Key',
        '\MeuMouse\Joinotify\Rest\Cloud_Connect_Start',
        '\MeuMouse\Joinotify\Rest\Cloud_Connect_License',
        '\MeuMouse\Joinotify\Rest\Cloud_Connect_Finish',
        '\MeuMouse\Joinotify\Rest\Cloud_Webhook',
        '\MeuMouse\Joinotify\Rest\Debug_Logs',
        '\MeuMouse\Joinotify\Rest\Debug_Clear',
        '\MeuMouse\Joinotify\Rest\Debug_Download',
        '\MeuMouse\Joinotify\Rest\Settings_Reset',
        '\MeuMouse\Joinotify\Rest\Settings_Export',
        '\MeuMouse\Joinotify\Rest\Settings_Import',
        '\MeuMouse\Joinotify\Rest\Builder_Variables_Save',
        '\MeuMouse\Joinotify\Rest\Builder_Variables_Delete',
        '\MeuMouse\Joinotify\Rest\Builder_Variables_Meta_Keys',
        '\MeuMouse\Joinotify\Rest\Ai_Openai_Models',
        '\MeuMouse\Joinotify\Rest\Ai_Anthropic_Models',
        '\MeuMouse\Joinotify\Rest\User_Dismiss_Tip',
        '\MeuMouse\Joinotify\Rest\Send_Text_Message',
        '\MeuMouse\Joinotify\Rest\Send_Media_Message',
    );
}
