<?php

namespace MeuMouse\Joinotify\Notifications;

use MeuMouse\Joinotify\Notifications\Channels\Whatsapp_Evolution_Channel;

// Exit if accessed directly.
defined('ABSPATH') || exit;

/**
 * Registry/factory for notification channels.
 *
 * Third parties register a new delivery service by hooking
 * `Joinotify/Notifications/Channels` and mapping an id to a class name (or
 * instance) implementing Channel_Interface. Mirrors AI\Provider_Registry and
 * Otp_Login\Channel_Registry.
 *
 * @since 2.0.0
 * @package MeuMouse\Joinotify\Notifications
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
            'whatsapp' => Whatsapp_Evolution_Channel::class,
        );

        /**
         * Filter the registered notification channels.
         *
         * Map an id to a class name or instance implementing Channel_Interface.
         *
         * @since 2.0.0
         * @param array<string,mixed> $channels
         */
        $channels = apply_filters( 'Joinotify/Notifications/Channels', $channels );

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
     * Build the channel options for a settings dropdown.
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
