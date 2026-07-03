<?php

namespace MeuMouse\Joinotify\Integrations;

use MeuMouse\Joinotify\Admin\Admin;
use MeuMouse\Joinotify\Notifications\Channels\Telegram_Channel;

// Exit if accessed directly.
defined('ABSPATH') || exit;

/**
 * Add integration with Telegram
 *
 * Registers the Telegram delivery channel (Bot API), the Applications settings
 * card and, while enabled, the "Telegram: message" builder action.
 *
 * @since 2.1.0
 * @package MeuMouse\Joinotify\Integrations
 * @author MeuMouse.com
 */
class Telegram extends Integrations_Base {

    /**
     * Builder action icon (paper-plane glyph, keeps its brand blue fill).
     *
     * @since 2.1.0
     * @var string
     */
    const ACTION_ICON = '<svg class="icon icon-lg telegram" role="img" viewBox="0 0 24 24" xmlns="http://www.w3.org/2000/svg" fill="#229ED9"><path d="M11.944 0A12 12 0 0 0 0 12a12 12 0 0 0 12 12 12 12 0 0 0 12-12A12 12 0 0 0 12 0a12 12 0 0 0-.056 0zm4.962 7.224c.1-.002.321.023.465.14a.506.506 0 0 1 .171.325c.016.093.036.306.02.472-.18 1.898-.962 6.502-1.36 8.627-.168.9-.499 1.201-.82 1.23-.696.065-1.225-.46-1.9-.902-1.056-.693-1.653-1.124-2.678-1.8-1.185-.78-.417-1.21.258-1.91.177-.184 3.247-2.977 3.307-3.23.007-.032.014-.15-.056-.212s-.174-.041-.249-.024c-.106.024-1.793 1.14-5.061 3.345-.48.33-.913.49-1.302.48-.428-.008-1.252-.241-1.865-.44-.752-.245-1.349-.374-1.297-.789.027-.216.325-.437.893-.663 3.498-1.524 5.83-2.529 6.998-3.014 3.332-1.386 4.025-1.627 4.476-1.635z"/></svg>';

    /**
     * Construct function
     *
     * @since 2.1.0
     * @return void
     */
    public function __construct() {
        // register the Telegram delivery channel (available to Channel_Manager)
        add_filter( 'Joinotify/Notifications/Channels', array( $this, 'register_channel' ), 10, 1 );

        // add integration on settings
        add_filter( 'Joinotify/Settings/Tabs/Integrations', array( $this, 'add_integration_item' ), 42, 1 );

        // add Telegram message action while the integration is enabled
        if ( Admin::get_setting('enable_telegram_integration') === 'yes' ) {
            add_filter( 'Joinotify/Builder/Actions', array( $this, 'add_telegram_messages' ), 10, 1 );
            add_filter( 'Joinotify/Builder/Action_Categories', array( $this, 'add_messages_category' ), 11, 1 );
        }
    }


    /**
     * Register the Telegram channel on the notifications registry.
     *
     * @since 2.1.0
     * @param array $channels | Current channels (id => class|instance)
     * @return array
     */
    public function register_channel( $channels ) {
        $channels['telegram'] = Telegram_Channel::class;

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
        $integrations['telegram'] = self::build_integration_item(
            'telegram',
            esc_html__( 'Telegram', 'joinotify' ),
            esc_html__( 'Send notifications to Telegram chats, groups and channels through a bot.', 'joinotify' ),
            '<svg role="img" viewBox="0 0 24 24" xmlns="http://www.w3.org/2000/svg" fill="#229ED9" style="max-width: 4.5rem;"><title>Telegram icon</title><path d="M11.944 0A12 12 0 0 0 0 12a12 12 0 0 0 12 12 12 12 0 0 0 12-12A12 12 0 0 0 12 0a12 12 0 0 0-.056 0zm4.962 7.224c.1-.002.321.023.465.14a.506.506 0 0 1 .171.325c.016.093.036.306.02.472-.18 1.898-.962 6.502-1.36 8.627-.168.9-.499 1.201-.82 1.23-.696.065-1.225-.46-1.9-.902-1.056-.693-1.653-1.124-2.678-1.8-1.185-.78-.417-1.21.258-1.91.177-.184 3.247-2.977 3.307-3.23.007-.032.014-.15-.056-.212s-.174-.041-.249-.024c-.106.024-1.793 1.14-5.061 3.345-.48.33-.913.49-1.302.48-.428-.008-1.252-.241-1.865-.44-.752-.245-1.349-.374-1.297-.789.027-.216.325-.437.893-.663 3.498-1.524 5.83-2.529 6.998-3.014 3.332-1.386 4.025-1.627 4.476-1.635z"/></svg>',
            array(
                'setting_key' => 'enable_telegram_integration',
                'action_hook' => 'Joinotify/Settings/Tabs/Integrations/Telegram',
                'settings' => self::get_integration_settings(),
                'modal' => array(
                    'title' => __( 'Telegram settings', 'joinotify' ),
                    'description' => __( 'Create a bot with @BotFather and paste its token below. The destination chat id is set on each workflow action.', 'joinotify' ),
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
                'telegram_bot_token',
                esc_html__( 'Bot token', 'joinotify' ),
                esc_html__( 'Token provided by @BotFather when you create a bot (eg: 123456:ABC-DEF...).', 'joinotify' ),
                array(
                    'placeholder' => '123456789:ABCdef...',
                    'autocomplete' => 'off',
                )
            ),
            self::field_text(
                'telegram_default_chat_id',
                esc_html__( 'Default chat id', 'joinotify' ),
                esc_html__( 'Optional. Used only when a workflow action does not specify a chat id.', 'joinotify' ),
                array(
                    'placeholder' => '-1001234567890',
                    'autocomplete' => 'off',
                )
            ),
        );
    }


    /**
     * Add Telegram message action in the builder actions sidebar.
     *
     * @since 2.1.0
     * @param array $actions | Current actions
     * @return array
     */
    public function add_telegram_messages( $actions ) {
        $actions[] = array(
            'action' => 'send_telegram_message_text',
            'title' => __( 'Telegram: message', 'joinotify' ),
            'description' => __( 'Send a text message to a Telegram chat, group or channel.', 'joinotify' ),
            'context' => array(),
            'category' => 'messages',
            'icon' => self::ACTION_ICON,
            'external_icon' => false,
            'has_settings' => true,
            'priority' => 60,
            'is_expansible' => true,
        );

        return $actions;
    }
}
