<?php

namespace MeuMouse\Joinotify\Otp_Login;

use MeuMouse\Joinotify\Otp_Login\Channels\Whatsapp_Channel;

// Exit if accessed directly.
defined('ABSPATH') || exit;

/**
 * Registry/factory for OTP delivery channels.
 *
 * Third parties register a new channel (e-mail, Telegram, SMS, ...) by hooking
 * `Joinotify/Otp_Login/Channels` and mapping an id to a class name (or instance)
 * implementing Channel_Interface. This mirrors the AI Provider_Registry so the
 * passwordless login can grow new delivery engines without core changes.
 *
 * @since 2.0.0
 * @package MeuMouse\Joinotify\Otp_Login
 * @author MeuMouse.com
 */
class Channel_Registry {

    /**
     * Instantiated channels cache (id => Channel_Interface).
     *
     * @since 2.0.0
     * @var array<string,Channel_Interface>
     */
    protected static $instances = array();

    /**
     * Return the registered channel map (id => class|instance).
     *
     * @since 2.0.0
     * @return array<string,mixed>
     */
    public static function get_channels() {
        $channels = array(
            'whatsapp' => Whatsapp_Channel::class,
        );

        /**
         * Filter the registered OTP delivery channels.
         *
         * Map an id to a class name or instance implementing Channel_Interface.
         * Example (an e-mail channel added by an extension):
         *
         *     add_filter( 'Joinotify/Otp_Login/Channels', function( $channels ) {
         *         $channels['email'] = My_Email_Channel::class;
         *         return $channels;
         *     });
         *
         * @since 2.0.0
         * @param array<string,mixed> $channels
         */
        $channels = apply_filters( 'Joinotify/Otp_Login/Channels', $channels );

        return is_array( $channels ) ? $channels : array();
    }


    /**
     * Resolve a channel instance by id.
     *
     * @since 2.0.0
     * @param string $id | Channel identifier.
     * @return Channel_Interface|null
     */
    public static function get_channel( $id ) {
        $id = is_string( $id ) ? trim( $id ) : '';

        if ( '' === $id ) {
            return null;
        }

        if ( isset( self::$instances[ $id ] ) ) {
            return self::$instances[ $id ];
        }

        $channels = self::get_channels();

        if ( ! isset( $channels[ $id ] ) ) {
            return null;
        }

        $entry = $channels[ $id ];

        if ( $entry instanceof Channel_Interface ) {
            return self::$instances[ $id ] = $entry;
        }

        if ( is_string( $entry ) && class_exists( $entry ) ) {
            $instance = new $entry();

            if ( $instance instanceof Channel_Interface ) {
                return self::$instances[ $id ] = $instance;
            }
        }

        return null;
    }


    /**
     * Build the channel options for the settings dropdown.
     *
     * Every registered channel is selectable; runtime configuration (sender,
     * credentials) is validated at send time, not in the dropdown. Future
     * channels simply stay unregistered until they are ready to ship.
     *
     * @since 2.0.0
     * @return array<int,array{value:string,label:string}>
     */
    public static function get_channel_options() {
        $options = array();

        foreach ( array_keys( self::get_channels() ) as $id ) {
            $channel = self::get_channel( $id );

            if ( ! $channel instanceof Channel_Interface ) {
                continue;
            }

            $options[] = array(
                'value' => $channel->get_id(),
                'label' => $channel->get_label(),
            );
        }

        return $options;
    }
}
