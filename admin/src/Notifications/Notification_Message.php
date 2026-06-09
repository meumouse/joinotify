<?php

namespace MeuMouse\Joinotify\Notifications;

// Exit if accessed directly.
defined('ABSPATH') || exit;

/**
 * Channel-agnostic notification message.
 *
 * Value object passed to any delivery channel. It carries every piece of data a
 * channel might need to deliver a notification (sender, recipient, body, media)
 * plus free-form metadata for service-specific fields (e-mail subject, Telegram
 * chat id, template params, ...), so each channel only has to decide how to
 * transport it. Mirrors Otp_Login\Otp_Message.
 *
 * @since 2.1.0
 * @package MeuMouse\Joinotify\Notifications
 * @author MeuMouse.com
 */
class Notification_Message {

    /**
     * Target channel id (e.g. 'whatsapp'). Empty falls back to the default channel.
     *
     * @since 2.1.0
     * @var string
     */
    public $channel = '';

    /**
     * Message type: 'text', 'media' or 'audio'.
     *
     * @since 2.1.0
     * @var string
     */
    public $type = 'text';

    /**
     * Sender identifier (e.g. instance phone number / from address).
     *
     * @since 2.1.0
     * @var string
     */
    public $sender = '';

    /**
     * Recipient identifier (phone number, e-mail, chat id, ...).
     *
     * @since 2.1.0
     * @var string
     */
    public $receiver = '';

    /**
     * Text body (for text messages).
     *
     * @since 2.1.0
     * @var string
     */
    public $content = '';

    /**
     * Media type for media messages (image, audio, video, document).
     *
     * @since 2.1.0
     * @var string
     */
    public $media_type = '';

    /**
     * Media URL for media messages.
     *
     * @since 2.1.0
     * @var string
     */
    public $media_url = '';

    /**
     * Optional caption for media messages.
     *
     * @since 2.1.0
     * @var string
     */
    public $caption = '';

    /**
     * Delay in milliseconds before delivery (when the channel supports it).
     *
     * @since 2.1.0
     * @var int
     */
    public $delay = 0;

    /**
     * Dispatch context for history/telemetry (e.g. source, workflow_id).
     *
     * @since 2.1.0
     * @var array<string,mixed>
     */
    public $context = array();

    /**
     * Free-form, channel-specific extras (subject, chat_id, template params, ...).
     *
     * @since 2.1.0
     * @var array<string,mixed>
     */
    public $meta = array();

    /**
     * Construct function.
     *
     * @since 2.1.0
     * @param array<string,mixed> $args | Message properties.
     * @return void
     */
    public function __construct( $args = array() ) {
        if ( ! is_array( $args ) ) {
            return;
        }

        if ( isset( $args['channel'] ) ) {
            $this->channel = (string) $args['channel'];
        }

        if ( isset( $args['type'] ) ) {
            $this->type = (string) $args['type'];
        }

        if ( isset( $args['sender'] ) ) {
            $this->sender = (string) $args['sender'];
        }

        if ( isset( $args['receiver'] ) ) {
            $this->receiver = (string) $args['receiver'];
        }

        if ( isset( $args['content'] ) ) {
            $this->content = (string) $args['content'];
        }

        if ( isset( $args['media_type'] ) ) {
            $this->media_type = (string) $args['media_type'];
        }

        if ( isset( $args['media_url'] ) ) {
            $this->media_url = (string) $args['media_url'];
        }

        if ( isset( $args['caption'] ) ) {
            $this->caption = (string) $args['caption'];
        }

        if ( isset( $args['delay'] ) && is_numeric( $args['delay'] ) ) {
            $this->delay = (int) $args['delay'];
        }

        if ( isset( $args['context'] ) && is_array( $args['context'] ) ) {
            $this->context = $args['context'];
        }

        if ( isset( $args['meta'] ) && is_array( $args['meta'] ) ) {
            $this->meta = $args['meta'];
        }
    }


    /**
     * Build a message from an associative array.
     *
     * @since 2.1.0
     * @param array<string,mixed> $args | Message properties.
     * @return self
     */
    public static function from_array( $args ) {
        return new self( $args );
    }


    /**
     * Read a single metadata value.
     *
     * @since 2.1.0
     * @param string $key | Metadata key.
     * @param mixed  $default | Fallback when the key is absent.
     * @return mixed
     */
    public function get_meta( $key, $default = null ) {
        return array_key_exists( $key, $this->meta ) ? $this->meta[ $key ] : $default;
    }
}
