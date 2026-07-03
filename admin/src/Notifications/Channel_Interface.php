<?php

namespace MeuMouse\Joinotify\Notifications;

// Exit if accessed directly.
defined('ABSPATH') || exit;

/**
 * Contract implemented by every notification delivery channel
 * (WhatsApp, Telegram, e-mail, SMS, ...).
 *
 * New channels are registered through the `Joinotify/Notifications/Channels`
 * filter and only need to implement this interface to become a selectable
 * delivery service. Mirrors Otp_Login\Channel_Interface and AI\Provider_Interface.
 *
 * @since 2.0.0
 * @package MeuMouse\Joinotify\Notifications
 * @author MeuMouse.com
 */
interface Channel_Interface {

    /**
     * Unique channel identifier (e.g. 'whatsapp').
     *
     * @since 2.0.0
     * @return string
     */
    public function get_id();


    /**
     * Human-readable channel label (e.g. 'WhatsApp').
     *
     * @since 2.0.0
     * @return string
     */
    public function get_label();


    /**
     * Whether the channel has everything it needs to deliver a message.
     *
     * @since 2.0.0
     * @return bool
     */
    public function is_configured();


    /**
     * Message types this channel can deliver (e.g. ['text','media','audio']).
     *
     * @since 2.0.0
     * @return array<int,string>
     */
    public function get_capabilities();


    /**
     * Whether the channel can deliver the given message (recipient present and
     * message type supported). Returning false makes the manager skip dispatch.
     *
     * @since 2.0.0
     * @param Notification_Message $message | Message to deliver.
     * @return bool
     */
    public function supports( Notification_Message $message );


    /**
     * Deliver the message through this channel.
     *
     * @since 2.0.0
     * @param Notification_Message $message | Message to deliver.
     * @return Channel_Result
     */
    public function send( Notification_Message $message );
}
