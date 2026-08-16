<?php

namespace MeuMouse\Joinotify\Otp_Login\Channels;

use MeuMouse\Joinotify\Otp_Login\Channel_Interface;
use MeuMouse\Joinotify\Otp_Login\Otp_Message;
use MeuMouse\Joinotify\Otp_Login\Settings;
use MeuMouse\Joinotify\Admin\Admin;
use MeuMouse\Joinotify\Api\Controller;
use MeuMouse\Joinotify\Api\Transport;

// Exit if accessed directly.
defined('ABSPATH') || exit;

/**
 * Deliver OTP codes over WhatsApp through Joinotify's messaging pipeline.
 *
 * This is the only channel shipped by core. E-mail and Telegram channels can be
 * added later by implementing Channel_Interface and registering them on the
 * `Joinotify/Otp_Login/Channels` filter, with no change to the login flow.
 *
 * @since 2.0.0
 * @package MeuMouse\Joinotify\Otp_Login\Channels
 * @author MeuMouse.com
 */
class Whatsapp_Channel implements Channel_Interface {

    /**
     * Channel identifier.
     *
     * @since 2.0.0
     * @return string
     */
    public function get_id() {
        return 'whatsapp';
    }


    /**
     * Channel label.
     *
     * @since 2.0.0
     * @return string
     */
    public function get_label() {
        return __( 'WhatsApp', 'joinotify' );
    }


    /**
     * Whether a Joinotify sender is available to deliver the code.
     *
     * @since 2.0.0
     * @return bool
     */
    public function is_configured() {
        if ( ! function_exists( 'joinotify_send_whatsapp_message_text' ) ) {
            return false;
        }

        return '' !== (string) $this->resolve_sender();
    }


    /**
     * WhatsApp delivery needs a recipient phone number.
     *
     * @since 2.0.0
     * @param Otp_Message $message | OTP message to deliver.
     * @return bool
     */
    public function supports( Otp_Message $message ) {
        return '' !== trim( (string) $message->phone );
    }


    /**
     * Send the OTP message over WhatsApp.
     *
     * A login code is always started by the business, so on the Cloud API it
     * lands outside the 24-hour session window and free-form text is refused
     * (error 131047). There it must go out as an approved AUTHENTICATION
     * template, whose single variable is the code itself.
     *
     * @since 2.0.0
     * @version 2.3.0
     * @param Otp_Message $message | OTP message to deliver.
     * @return bool|\WP_Error
     */
    public function send( Otp_Message $message ) {
        if ( ! class_exists( Controller::class ) ) {
            return new \WP_Error( 'joinotify_otp_helpers_missing', __( 'Joinotify messaging helpers are unavailable.', 'joinotify' ) );
        }

        $sender = $this->resolve_sender();

        if ( empty( $sender ) ) {
            return new \WP_Error( 'joinotify_otp_no_sender', __( 'No Joinotify sender is configured to deliver the code.', 'joinotify' ) );
        }

        $receiver = function_exists( 'joinotify_prepare_receiver' )
            ? joinotify_prepare_receiver( preg_replace( '/\s+/', '', (string) $message->phone ) )
            : preg_replace( '/\D+/', '', (string) $message->phone );

        // OTP codes expire within minutes, so a deferred retry would deliver an
        // already-invalid code. Send a single attempt and never enqueue it for
        // the notification retry queue ($queue_on_failure = false).
        $result = Transport::is_cloud()
            ? $this->send_template( $sender, $receiver, $message )
            : Controller::send_message_text( $sender, $receiver, $message->body, 0, false );

        if ( is_wp_error( $result ) ) {
            return $result;
        }

        return ( true === $result || 201 === $result || '201' === $result );
    }


    /**
     * Deliver the code through the configured AUTHENTICATION template.
     *
     * @since 2.3.0
     * @param string      $sender | Origin phone number.
     * @param string      $receiver | Recipient phone number.
     * @param Otp_Message $message | OTP message to deliver.
     * @return int|\WP_Error
     */
    protected function send_template( $sender, $receiver, Otp_Message $message ) {
        $template = trim( (string) Admin::get_setting('otp_login_template_name') );

        if ( '' === $template ) {
            return new \WP_Error( 'joinotify_otp_no_template', __( 'Set an approved AUTHENTICATION template to deliver login codes through the WhatsApp Cloud API.', 'joinotify' ) );
        }

        $language = trim( (string) Admin::get_setting('otp_login_template_language') );
        $language = '' !== $language ? $language : 'pt_BR';

        // Meta's AUTHENTICATION templates take the code as the single body
        // variable, and repeat it on the copy button when the template has one.
        $components = array(
            array(
                'type' => 'body',
                'parameters' => array(
                    array( 'type' => 'text', 'text' => (string) $message->code ),
                ),
            ),
            array(
                'type' => 'button',
                'sub_type' => 'url',
                'index' => '0',
                'parameters' => array(
                    array( 'type' => 'text', 'text' => (string) $message->code ),
                ),
            ),
        );

        /**
         * Filter the components sent with the OTP login template.
         *
         * Lets a site drop the copy-code button entry when its template has no
         * button, or add a header parameter.
         *
         * @since 2.3.0
         * @param array       $components | Meta components payload.
         * @param Otp_Message $message | OTP message being delivered.
         * @return array
         */
        $components = apply_filters( 'Joinotify/Otp_Login/Template_Components', $components, $message );

        return Transport::send_message_template( $sender, $receiver, $template, $language, $components, 0, false );
    }


    /**
     * Resolve the sender phone, preferring the OTP Login setting.
     *
     * @since 2.0.0
     * @return string
     */
    protected function resolve_sender() {
        $selected = Settings::get_selected_sender();

        if ( ! empty( $selected ) ) {
            $sender = $selected;
        } else {
            $sender = function_exists( 'joinotify_get_first_sender' ) ? joinotify_get_first_sender() : '';
        }

        /**
         * Filter the sender used to deliver OTP login codes over WhatsApp.
         *
         * @since 2.0.0
         * @param string $sender Sender phone number.
         */
        return (string) apply_filters( 'Joinotify/Otp_Login/Sender', $sender );
    }
}
