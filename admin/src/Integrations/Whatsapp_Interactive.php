<?php

namespace MeuMouse\Joinotify\Integrations;

use MeuMouse\Joinotify\Admin\Admin;
use MeuMouse\Joinotify\Api\Transport;
use MeuMouse\Joinotify\Builder\Placeholders;
use MeuMouse\Joinotify\Core\Logger;
use MeuMouse\Joinotify\Core\Message_History;

// Exit if accessed directly.
defined('ABSPATH') || exit;

/**
 * The message types the official WhatsApp Cloud API allows beyond text, media
 * and templates: reply buttons, option lists, link buttons, locations, contact
 * cards, reactions and stickers.
 *
 * None of them exist on the legacy Evolution relay, so every handler here stops
 * with a log when that transport is active. They are also session messages:
 * outside the 24-hour window Meta refuses them, and a template is the only way
 * through.
 *
 * Each action is registered declaratively — the settings drawer renders the
 * `settings_schema` on its own, so none of this needs a Vue component.
 *
 * @since 2.3.0
 * @package MeuMouse\Joinotify\Integrations
 * @author MeuMouse.com
 */
class Whatsapp_Interactive {

    /**
     * Construct function.
     *
     * @since 2.3.0
     * @return void
     */
    public function __construct() {
        if ( Admin::get_setting('enable_whatsapp_integration') !== 'yes' ) {
            return;
        }

        add_filter( 'Joinotify/Builder/Actions', array( $this, 'add_actions' ), 10, 1 );
        add_filter( 'Joinotify/Workflow_Processor/Action_Required_Config', array( $this, 'add_required_config' ), 10, 1 );
        add_filter( 'Joinotify/Workflow_Processor/Handle_Actions', array( $this, 'add_handlers' ), 10, 4 );
        add_filter( 'Joinotify/Download_Template/Fill_Sender_Actions', array( $this, 'add_fill_sender_actions' ), 10, 1 );
    }


    /**
     * Fields every action here shares.
     *
     * @since 2.3.0
     * @return array
     */
    protected static function base_schema() {
        return array(
            array(
                'key' => 'sender',
                'label' => __( 'Sender', 'joinotify' ),
                'component' => 'input',
                'required' => true,
                'description' => __( 'WhatsApp number that will send the message.', 'joinotify' ),
            ),
            array(
                'key' => 'receiver',
                'label' => __( 'Recipient', 'joinotify' ),
                'component' => 'input',
                'required' => true,
                'placeholder' => '{{ wc_billing_phone }}',
            ),
        );
    }


    /**
     * Values every action here starts with.
     *
     * @since 2.3.0
     * @param string $action | Action slug.
     * @param string $title | Action title.
     * @return array
     */
    protected static function base_defaults( $action, $title ) {
        return array(
            'title' => $title,
            'description' => '',
            'action' => $action,
            'sender' => '',
            'receiver' => '{{ wc_billing_phone }}',
        );
    }


    /**
     * Register the actions in the builder catalog.
     *
     * @since 2.3.0
     * @param array $actions | Current actions.
     * @return array
     */
    public function add_actions( $actions ) {
        foreach ( self::definitions() as $definition ) {
            $actions[] = $definition;
        }

        return $actions;
    }


    /**
     * The catalog entry of every action registered by this class.
     *
     * @since 2.3.0
     * @return array
     */
    protected static function definitions() {
        $icon = '<svg class="icon icon-lg whatsapp" viewBox="0 0 24 24" fill="none" xmlns="http://www.w3.org/2000/svg"><path d="M4.5 6.75A2.25 2.25 0 0 1 6.75 4.5h10.5a2.25 2.25 0 0 1 2.25 2.25v7.5a2.25 2.25 0 0 1-2.25 2.25H9l-4.5 3.75V6.75Z" stroke="currentColor" stroke-width="1.5" stroke-linejoin="round"/><path d="M8.25 9h7.5M8.25 12h4.5" stroke="currentColor" stroke-width="1.5" stroke-linecap="round"/></svg>';

        $shared = array(
            'context' => array(),
            'category' => 'messages',
            'icon' => $icon,
            'external_icon' => false,
            'has_settings' => true,
            'is_expansible' => true,
        );

        return array(
            array_merge( $shared, array(
                'action' => 'send_whatsapp_buttons',
                'title' => __( 'WhatsApp: Reply buttons', 'joinotify' ),
                'description' => __( 'Send a message with up to three reply buttons. Cloud API only, and only inside the 24-hour window.', 'joinotify' ),
                'priority' => 60,
                'default_data' => array_merge( self::base_defaults( 'send_whatsapp_buttons', __( 'WhatsApp: Reply buttons', 'joinotify' ) ), array(
                    'header' => '',
                    'message' => '',
                    'footer' => '',
                    'buttons' => array(),
                ) ),
                'settings_schema' => array_merge( self::base_schema(), array(
                    array( 'key' => 'header', 'label' => __( 'Header', 'joinotify' ), 'component' => 'input' ),
                    array( 'key' => 'message', 'label' => __( 'Message', 'joinotify' ), 'component' => 'textarea', 'required' => true, 'rows' => 4 ),
                    array( 'key' => 'footer', 'label' => __( 'Footer', 'joinotify' ), 'component' => 'input' ),
                    array(
                        'key' => 'buttons',
                        'label' => __( 'Buttons', 'joinotify' ),
                        'component' => 'repeater',
                        'description' => __( 'Up to three buttons. Titles are limited to 20 characters by WhatsApp.', 'joinotify' ),
                        'fields' => array(
                            array( 'key' => 'title', 'label' => __( 'Label', 'joinotify' ), 'component' => 'input' ),
                            array( 'key' => 'id', 'label' => __( 'Identifier', 'joinotify' ), 'component' => 'input', 'description' => __( 'Returned on the webhook when the contact taps it.', 'joinotify' ) ),
                        ),
                    ),
                ) ),
            ) ),
            array_merge( $shared, array(
                'action' => 'send_whatsapp_list',
                'title' => __( 'WhatsApp: Option list', 'joinotify' ),
                'description' => __( 'Send a message with a selectable list of options. Cloud API only, and only inside the 24-hour window.', 'joinotify' ),
                'priority' => 62,
                'default_data' => array_merge( self::base_defaults( 'send_whatsapp_list', __( 'WhatsApp: Option list', 'joinotify' ) ), array(
                    'header' => '',
                    'message' => '',
                    'footer' => '',
                    'button_label' => __( 'See options', 'joinotify' ),
                    'section_title' => '',
                    'rows' => array(),
                ) ),
                'settings_schema' => array_merge( self::base_schema(), array(
                    array( 'key' => 'header', 'label' => __( 'Header', 'joinotify' ), 'component' => 'input' ),
                    array( 'key' => 'message', 'label' => __( 'Message', 'joinotify' ), 'component' => 'textarea', 'required' => true, 'rows' => 4 ),
                    array( 'key' => 'footer', 'label' => __( 'Footer', 'joinotify' ), 'component' => 'input' ),
                    array( 'key' => 'button_label', 'label' => __( 'Button label', 'joinotify' ), 'component' => 'input', 'required' => true ),
                    array( 'key' => 'section_title', 'label' => __( 'Section title', 'joinotify' ), 'component' => 'input' ),
                    array(
                        'key' => 'rows',
                        'label' => __( 'Options', 'joinotify' ),
                        'component' => 'repeater',
                        'fields' => array(
                            array( 'key' => 'title', 'label' => __( 'Title', 'joinotify' ), 'component' => 'input' ),
                            array( 'key' => 'description', 'label' => __( 'Description', 'joinotify' ), 'component' => 'input' ),
                            array( 'key' => 'id', 'label' => __( 'Identifier', 'joinotify' ), 'component' => 'input' ),
                        ),
                    ),
                ) ),
            ) ),
            array_merge( $shared, array(
                'action' => 'send_whatsapp_cta',
                'title' => __( 'WhatsApp: Link button', 'joinotify' ),
                'description' => __( 'Send a message with a button that opens a link. Cloud API only, and only inside the 24-hour window.', 'joinotify' ),
                'priority' => 64,
                'default_data' => array_merge( self::base_defaults( 'send_whatsapp_cta', __( 'WhatsApp: Link button', 'joinotify' ) ), array(
                    'header' => '',
                    'message' => '',
                    'footer' => '',
                    'display_text' => '',
                    'url' => '',
                ) ),
                'settings_schema' => array_merge( self::base_schema(), array(
                    array( 'key' => 'header', 'label' => __( 'Header', 'joinotify' ), 'component' => 'input' ),
                    array( 'key' => 'message', 'label' => __( 'Message', 'joinotify' ), 'component' => 'textarea', 'required' => true, 'rows' => 4 ),
                    array( 'key' => 'footer', 'label' => __( 'Footer', 'joinotify' ), 'component' => 'input' ),
                    array( 'key' => 'display_text', 'label' => __( 'Button label', 'joinotify' ), 'component' => 'input', 'required' => true ),
                    array( 'key' => 'url', 'label' => __( 'URL', 'joinotify' ), 'component' => 'input', 'required' => true ),
                ) ),
            ) ),
            array_merge( $shared, array(
                'action' => 'send_whatsapp_location',
                'title' => __( 'WhatsApp: Location', 'joinotify' ),
                'description' => __( 'Send a point on the map. Cloud API only, and only inside the 24-hour window.', 'joinotify' ),
                'priority' => 66,
                'default_data' => array_merge( self::base_defaults( 'send_whatsapp_location', __( 'WhatsApp: Location', 'joinotify' ) ), array(
                    'latitude' => '',
                    'longitude' => '',
                    'place_name' => '',
                    'address' => '',
                ) ),
                'settings_schema' => array_merge( self::base_schema(), array(
                    array( 'key' => 'latitude', 'label' => __( 'Latitude', 'joinotify' ), 'component' => 'input', 'required' => true, 'placeholder' => '-25.4284' ),
                    array( 'key' => 'longitude', 'label' => __( 'Longitude', 'joinotify' ), 'component' => 'input', 'required' => true, 'placeholder' => '-49.2733' ),
                    array( 'key' => 'place_name', 'label' => __( 'Place name', 'joinotify' ), 'component' => 'input' ),
                    array( 'key' => 'address', 'label' => __( 'Address', 'joinotify' ), 'component' => 'input' ),
                ) ),
            ) ),
            array_merge( $shared, array(
                'action' => 'send_whatsapp_contact',
                'title' => __( 'WhatsApp: Contact card', 'joinotify' ),
                'description' => __( 'Send a contact card. Cloud API only, and only inside the 24-hour window.', 'joinotify' ),
                'priority' => 68,
                'default_data' => array_merge( self::base_defaults( 'send_whatsapp_contact', __( 'WhatsApp: Contact card', 'joinotify' ) ), array(
                    'contact_name' => '',
                    'contact_phone' => '',
                ) ),
                'settings_schema' => array_merge( self::base_schema(), array(
                    array( 'key' => 'contact_name', 'label' => __( 'Contact name', 'joinotify' ), 'component' => 'input', 'required' => true ),
                    array( 'key' => 'contact_phone', 'label' => __( 'Contact phone', 'joinotify' ), 'component' => 'input', 'required' => true ),
                ) ),
            ) ),
            array_merge( $shared, array(
                'action' => 'send_whatsapp_sticker',
                'title' => __( 'WhatsApp: Sticker', 'joinotify' ),
                'description' => __( 'Send a WebP sticker from a public URL. Cloud API only, and only inside the 24-hour window.', 'joinotify' ),
                'priority' => 70,
                'default_data' => array_merge( self::base_defaults( 'send_whatsapp_sticker', __( 'WhatsApp: Sticker', 'joinotify' ) ), array(
                    'sticker_url' => '',
                ) ),
                'settings_schema' => array_merge( self::base_schema(), array(
                    array( 'key' => 'sticker_url', 'label' => __( 'Sticker URL', 'joinotify' ), 'component' => 'input', 'required' => true, 'description' => __( 'Public WebP file, up to 100 KB static or 500 KB animated.', 'joinotify' ) ),
                ) ),
            ) ),
            array_merge( $shared, array(
                'action' => 'send_whatsapp_reaction',
                'title' => __( 'WhatsApp: Reaction', 'joinotify' ),
                'description' => __( 'React with an emoji to a message the contact sent. Cloud API only.', 'joinotify' ),
                'priority' => 72,
                'default_data' => array_merge( self::base_defaults( 'send_whatsapp_reaction', __( 'WhatsApp: Reaction', 'joinotify' ) ), array(
                    'message_id' => '',
                    'emoji' => '',
                ) ),
                'settings_schema' => array_merge( self::base_schema(), array(
                    array( 'key' => 'message_id', 'label' => __( 'Message ID', 'joinotify' ), 'component' => 'input', 'required' => true, 'description' => __( 'WAMID of the received message, usually taken from an inbound webhook.', 'joinotify' ) ),
                    array( 'key' => 'emoji', 'label' => __( 'Emoji', 'joinotify' ), 'component' => 'input', 'required' => true, 'description' => __( 'Leave blank to remove a reaction already sent.', 'joinotify' ) ),
                ) ),
            ) ),
        );
    }


    /**
     * Declare which fields must be filled before each action runs.
     *
     * @since 2.3.0
     * @param array $required_config | Current map.
     * @return array
     */
    public function add_required_config( $required_config ) {
        $required_config['send_whatsapp_buttons'] = array( 'sender', 'receiver', 'message', 'buttons' );
        $required_config['send_whatsapp_list'] = array( 'sender', 'receiver', 'message', 'button_label', 'rows' );
        $required_config['send_whatsapp_cta'] = array( 'sender', 'receiver', 'message', 'display_text', 'url' );
        $required_config['send_whatsapp_location'] = array( 'sender', 'receiver', 'latitude', 'longitude' );
        $required_config['send_whatsapp_contact'] = array( 'sender', 'receiver', 'contact_name', 'contact_phone' );
        $required_config['send_whatsapp_sticker'] = array( 'sender', 'receiver', 'sticker_url' );
        $required_config['send_whatsapp_reaction'] = array( 'sender', 'receiver', 'message_id' );

        return $required_config;
    }


    /**
     * Auto-fill the sender when a workflow template carrying these actions is
     * imported.
     *
     * @since 2.3.0
     * @param array $actions | Action slugs that need a sender.
     * @return array
     */
    public function add_fill_sender_actions( $actions ) {
        return array_merge( (array) $actions, array(
            'send_whatsapp_buttons',
            'send_whatsapp_list',
            'send_whatsapp_cta',
            'send_whatsapp_location',
            'send_whatsapp_contact',
            'send_whatsapp_sticker',
            'send_whatsapp_reaction',
        ) );
    }


    /**
     * Register the runtime handler of each action.
     *
     * @since 2.3.0
     * @param array $handlers | Current handler map.
     * @param array $action | Full action item.
     * @param int   $post_id | Workflow post ID.
     * @param array $event_data | Runtime trigger payload.
     * @return array
     */
    public function add_handlers( $handlers, $action, $post_id, $event_data ) {
        $data = isset( $action['data'] ) && is_array( $action['data'] ) ? $action['data'] : array();

        $handlers['send_whatsapp_buttons'] = fn() => self::run( 'buttons', $data, $event_data, $post_id );
        $handlers['send_whatsapp_list'] = fn() => self::run( 'list', $data, $event_data, $post_id );
        $handlers['send_whatsapp_cta'] = fn() => self::run( 'cta', $data, $event_data, $post_id );
        $handlers['send_whatsapp_location'] = fn() => self::run( 'location', $data, $event_data, $post_id );
        $handlers['send_whatsapp_contact'] = fn() => self::run( 'contact', $data, $event_data, $post_id );
        $handlers['send_whatsapp_sticker'] = fn() => self::run( 'sticker', $data, $event_data, $post_id );
        $handlers['send_whatsapp_reaction'] = fn() => self::run( 'reaction', $data, $event_data, $post_id );

        return $handlers;
    }


    /**
     * Resolve the placeholders of an action and dispatch it.
     *
     * @since 2.3.0
     * @param string $kind | Which message to build.
     * @param array  $data | Action data.
     * @param array  $payload | Runtime trigger payload.
     * @param int    $post_id | Workflow post ID.
     * @return bool
     */
    protected static function run( $kind, $data, $payload, $post_id ) {
        if ( ! Transport::is_cloud() ) {
            Logger::register_log( sprintf( 'Skipping "%s" action: this message type requires the WhatsApp Cloud API transport.', $kind ), 'WARNING' );

            return false;
        }

        $sender = (string) ( $data['sender'] ?? '' );
        $receiver = joinotify_prepare_receiver( $data['receiver'] ?? '', $payload );
        $text = fn( $key ) => Placeholders::replace_placeholders( (string) ( $data[ $key ] ?? '' ), $payload );

        Message_History::set_context( array(
            'source' => 'workflow',
            'workflow_id' => $post_id,
        ));

        switch ( $kind ) {
            case 'buttons':
                $result = Transport::send_rich_message( 'buttons', $sender, $receiver, array(
                    'body' => $text( 'message' ),
                    'buttons' => self::resolve_repeater( $data['buttons'] ?? array(), array( 'title', 'id' ), $payload ),
                    'header' => $text( 'header' ),
                    'footer' => $text( 'footer' ),
                ) );
                break;

            case 'list':
                $result = Transport::send_rich_message( 'list', $sender, $receiver, array(
                    'body' => $text( 'message' ),
                    'button_label' => $text( 'button_label' ),
                    'sections' => array( array(
                        'title' => $text( 'section_title' ),
                        'rows' => self::resolve_repeater( $data['rows'] ?? array(), array( 'title', 'description', 'id' ), $payload ),
                    ) ),
                    'header' => $text( 'header' ),
                    'footer' => $text( 'footer' ),
                ) );
                break;

            case 'cta':
                $result = Transport::send_rich_message( 'cta', $sender, $receiver, array(
                    'body' => $text( 'message' ),
                    'display_text' => $text( 'display_text' ),
                    'url' => $text( 'url' ),
                    'header' => $text( 'header' ),
                    'footer' => $text( 'footer' ),
                ) );
                break;

            case 'location':
                $result = Transport::send_rich_message( 'location', $sender, $receiver, array(
                    'latitude' => $text( 'latitude' ),
                    'longitude' => $text( 'longitude' ),
                    'name' => $text( 'place_name' ),
                    'address' => $text( 'address' ),
                ) );
                break;

            case 'contact':
                $result = Transport::send_rich_message( 'contact', $sender, $receiver, array(
                    'name' => $text( 'contact_name' ),
                    'phone' => $text( 'contact_phone' ),
                ) );
                break;

            case 'sticker':
                $result = Transport::send_rich_message( 'sticker', $sender, $receiver, array(
                    'url' => $text( 'sticker_url' ),
                ) );
                break;

            case 'reaction':
                $result = Transport::send_rich_message( 'reaction', $sender, $receiver, array(
                    'message_id' => $text( 'message_id' ),
                    'emoji' => $text( 'emoji' ),
                ) );
                break;

            default:
                $result = 0;
        }

        Message_History::clear_context();

        return 201 === (int) $result;
    }


    /**
     * Resolve the placeholders inside a repeater list.
     *
     * @since 2.3.0
     * @param array $items | Repeater rows.
     * @param array $keys | Keys to resolve on each row.
     * @param array $payload | Runtime trigger payload.
     * @return array
     */
    protected static function resolve_repeater( $items, $keys, $payload ) {
        $resolved = array();

        foreach ( (array) $items as $item ) {
            if ( ! is_array( $item ) ) {
                continue;
            }

            $row = array();

            foreach ( $keys as $key ) {
                $row[ $key ] = Placeholders::replace_placeholders( (string) ( $item[ $key ] ?? '' ), $payload );
            }

            $resolved[] = $row;
        }

        return $resolved;
    }
}
