<?php

namespace MeuMouse\Joinotify\Integrations;

use MeuMouse\Joinotify\Admin\Admin;
use MeuMouse\Joinotify\Notifications\Channels\Resend_Channel;

// Exit if accessed directly.
defined('ABSPATH') || exit;

/**
 * Add integration with Resend
 *
 * Registers the Resend e-mail delivery channel, the Applications settings card
 * and, while enabled, the "Send e-mail (Resend)" builder action.
 *
 * @since 2.1.0
 * @package MeuMouse\Joinotify\Integrations
 * @author MeuMouse.com
 */
class Resend extends Integrations_Base {

    /**
     * Builder action icon (envelope glyph).
     *
     * @since 2.1.0
     * @var string
     */
    const ACTION_ICON = '<svg class="icon icon-lg resend" xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" fill="currentColor"><path d="M20 4H4c-1.103 0-2 .897-2 2v12c0 1.103.897 2 2 2h16c1.103 0 2-.897 2-2V6c0-1.103-.897-2-2-2zm0 2v.511l-8 6.223-8-6.222V6h16zM4 18V9.044l7.386 5.745a.994.994 0 0 0 1.228 0L20 9.044 20.002 18H4z"></path></svg>';

    /**
     * Construct function
     *
     * @since 2.1.0
     * @return void
     */
    public function __construct() {
        // register the Resend delivery channel (available to Channel_Manager)
        add_filter( 'Joinotify/Notifications/Channels', array( $this, 'register_channel' ), 10, 1 );

        // add integration on settings
        add_filter( 'Joinotify/Settings/Tabs/Integrations', array( $this, 'add_integration_item' ), 43, 1 );

        // add Resend e-mail action while the integration is enabled
        if ( Admin::get_setting('enable_resend_integration') === 'yes' ) {
            add_filter( 'Joinotify/Builder/Actions', array( $this, 'add_resend_messages' ), 10, 1 );
            add_filter( 'Joinotify/Builder/Action_Categories', array( $this, 'add_messages_category' ), 12, 1 );
        }
    }


    /**
     * Register the Resend channel on the notifications registry.
     *
     * @since 2.1.0
     * @param array $channels | Current channels (id => class|instance)
     * @return array
     */
    public function register_channel( $channels ) {
        $channels['resend'] = Resend_Channel::class;

        return $channels;
    }


    /**
     * Register the "Messages" category on the builder actions library.
     *
     * Only appends it when no other messaging integration already declared it,
     * so enabling more than one messaging channel never duplicates the tab.
     *
     * @since 2.1.0
     * @param array $categories | Current categories
     * @return array
     */
    public function add_messages_category( $categories ) {
        foreach ( (array) $categories as $category ) {
            if ( isset( $category['id'] ) && $category['id'] === 'messages' ) {
                return $categories;
            }
        }

        $categories[] = array(
            'id' => 'messages',
            'label' => __( 'Messages', 'joinotify' ),
            'icon' => '<svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" fill="currentColor"><path d="M20 2H4c-1.103 0-2 .897-2 2v18l5.333-4H20c1.103 0 2-.897 2-2V4c0-1.103-.897-2-2-2zm0 14H6.667L4 18V4h16v12z"></path><path d="M7 7h10v2H7zm0 4h7v2H7z"></path></svg>',
            'priority' => 20,
        );

        return $categories;
    }


    /**
     * Add integration item on settings
     *
     * @since 2.1.0
     * @param array $integrations | Current integrations
     * @return array
     */
    public function add_integration_item( $integrations ) {
        $integrations['resend'] = self::build_integration_item(
            'resend',
            esc_html__( 'Resend', 'joinotify' ),
            esc_html__( 'Send e-mail notifications for your automations through the Resend API.', 'joinotify' ),
            '<svg viewBox="0 0 24 24" xmlns="http://www.w3.org/2000/svg" fill="#000000" style="max-width: 4.5rem;"><title>Resend icon</title><path d="M2 21V3h8.5c1.7 0 3.1.5 4.1 1.6 1 1 1.6 2.3 1.6 3.9 0 1.2-.3 2.2-.9 3.1-.6.9-1.4 1.5-2.5 1.9L22 21h-5.2l-4.6-6.9H6.9V21H2zm4.9-10.6h3c.8 0 1.4-.2 1.8-.6.4-.4.6-.9.6-1.6 0-.7-.2-1.2-.6-1.6-.4-.4-1-.6-1.8-.6h-3v4.4z"/></svg>',
            array(
                'setting_key' => 'enable_resend_integration',
                'action_hook' => 'Joinotify/Settings/Tabs/Integrations/Resend',
                'settings' => self::get_integration_settings(),
                'modal' => array(
                    'title' => __( 'Resend settings', 'joinotify' ),
                    'description' => __( 'Paste your Resend API key and set the verified sender address used to deliver e-mails.', 'joinotify' ),
                    'button_label' => __( 'Configure', 'joinotify' ),
                ),
            )
        );

        return $integrations;
    }


    /**
     * Declarative settings rendered in the integration modal.
     *
     * @since 2.1.0
     * @return array<int,array<string,mixed>>
     */
    public static function get_integration_settings() {
        return array(
            self::field_text(
                'resend_api_key',
                esc_html__( 'API key', 'joinotify' ),
                esc_html__( 'Secret key used to authenticate requests to Resend. Find it at resend.com/api-keys.', 'joinotify' ),
                array(
                    'placeholder' => 're_...',
                    'autocomplete' => 'off',
                )
            ),
            self::field_text(
                'resend_from_email',
                esc_html__( 'Sender e-mail', 'joinotify' ),
                esc_html__( 'From address used to deliver the e-mails. The domain must be verified in Resend.', 'joinotify' ),
                array(
                    'placeholder' => 'noreply@example.com',
                    'autocomplete' => 'off',
                )
            ),
            self::field_text(
                'resend_from_name',
                esc_html__( 'Sender name', 'joinotify' ),
                esc_html__( 'Optional. Friendly name shown as the sender of the e-mails.', 'joinotify' ),
                array(
                    'placeholder' => 'My Store',
                    'autocomplete' => 'off',
                )
            ),
        );
    }


    /**
     * Add Resend e-mail action in the builder actions sidebar.
     *
     * @since 2.1.0
     * @param array $actions | Current actions
     * @return array
     */
    public function add_resend_messages( $actions ) {
        $actions[] = array(
            'action' => 'send_resend_email',
            'title' => __( 'Send e-mail (Resend)', 'joinotify' ),
            'description' => __( 'Send an e-mail notification through Resend.', 'joinotify' ),
            'context' => array(),
            'category' => 'messages',
            'icon' => self::ACTION_ICON,
            'external_icon' => false,
            'has_settings' => true,
            'priority' => 70,
            'is_expansible' => true,
        );

        return $actions;
    }
}
