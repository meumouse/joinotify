<?php

namespace MeuMouse\Joinotify\Otp_Login;

// Exit if accessed directly.
defined('ABSPATH') || exit;

/**
 * Contract implemented by every OTP delivery channel (WhatsApp, e-mail, Telegram, ...).
 *
 * New channels are registered through the `Joinotify/Otp_Login/Channels` filter
 * and only need to implement this interface to become selectable as the active
 * delivery engine for the passwordless login flow.
 *
 * @since 2.0.0
 * @package MeuMouse\Joinotify\Otp_Login
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
     * Whether the channel has everything it needs to deliver a code.
     *
     * @since 2.0.0
     * @return bool
     */
    public function is_configured();


    /**
     * Whether the channel can reach the recipient described by the message.
     *
     * A WhatsApp/Telegram channel needs a phone number, an e-mail channel needs
     * an e-mail address. Returning false makes the manager skip this channel.
     *
     * @since 2.0.0
     * @param Otp_Message $message | OTP message to deliver.
     * @return bool
     */
    public function supports( Otp_Message $message );


    /**
     * Deliver the OTP message through this channel.
     *
     * @since 2.0.0
     * @param Otp_Message $message | OTP message to deliver.
     * @return bool|\WP_Error True on success, false or WP_Error on failure.
     */
    public function send( Otp_Message $message );
}
