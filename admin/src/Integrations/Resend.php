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
    const ACTION_ICON = '<svg class="icon icon-lg resend" xmlns="http://www.w3.org/2000/svg" width="1800" height="1800" viewBox="0 0 1800 1800" fill="none"><path d="M1000.46 450C1174.77 450 1278.43 553.669 1278.43 691.282C1278.43 828.896 1174.77 932.563 1000.46 932.563H912.382L1350 1350H1040.82L707.794 1033.48C683.944 1011.47 672.936 985.781 672.935 963.765C672.935 932.572 694.959 905.049 737.161 893.122L908.712 847.244C973.85 829.812 1018.81 779.353 1018.81 713.298C1018.8 632.567 952.745 585.78 871.095 585.78H450V450H1000.46Z" fill="black"/></svg>';

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
            'icon' => '<svg class="icon icon-lg resend" xmlns="http://www.w3.org/2000/svg" width="1800" height="1800" viewBox="0 0 1800 1800" fill="none"><path d="M1000.46 450C1174.77 450 1278.43 553.669 1278.43 691.282C1278.43 828.896 1174.77 932.563 1000.46 932.563H912.382L1350 1350H1040.82L707.794 1033.48C683.944 1011.47 672.936 985.781 672.935 963.765C672.935 932.572 694.959 905.049 737.161 893.122L908.712 847.244C973.85 829.812 1018.81 779.353 1018.81 713.298C1018.8 632.567 952.745 585.78 871.095 585.78H450V450H1000.46Z" fill="black"/></svg>',
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
            '<svg class="icon icon-lg resend" xmlns="http://www.w3.org/2000/svg" width="1800" height="1800" viewBox="0 0 1800 1800" fill="none"><path d="M1000.46 450C1174.77 450 1278.43 553.669 1278.43 691.282C1278.43 828.896 1174.77 932.563 1000.46 932.563H912.382L1350 1350H1040.82L707.794 1033.48C683.944 1011.47 672.936 985.781 672.935 963.765C672.935 932.572 694.959 905.049 737.161 893.122L908.712 847.244C973.85 829.812 1018.81 779.353 1018.81 713.298C1018.8 632.567 952.745 585.78 871.095 585.78H450V450H1000.46Z" fill="black"/></svg>',
            array(
                'category' => 'channels',
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
