<?php

namespace MeuMouse\Joinotify\Api;

// Exit if accessed directly.
defined('ABSPATH') || exit;

/**
 * Human-readable descriptions for outbound message failures.
 *
 * Api\Cloud_Client boils every failed send down to a short machine code
 * ('window_closed_requires_template', 'template_error_132001', 'http_401'...)
 * that travels in the response details, the message history and the debug log.
 * Those codes are stable and meant for storage and telemetry — they are not
 * something to put in front of an administrator.
 *
 * This class is the single place that turns one into a sentence explaining what
 * went wrong and what to do about it, so the test-send toasts and the history
 * screen say the same thing.
 *
 * The 24-hour session window is the case worth naming out loud: Meta only
 * accepts free-form content within 24 hours of the contact's last reply, and
 * outside it the send is refused with error 131047. Without this the failure
 * reached the admin as a generic "could not send", and the actual cause only
 * showed up in the log.
 *
 * @since 2.4.0
 * @package MeuMouse\Joinotify\Api
 * @author MeuMouse.com
 */
class Send_Error {

    /**
     * Code reported when the contact's 24-hour session window is closed.
     *
     * @var string
     */
    const WINDOW_CLOSED = 'window_closed_requires_template';


    /**
     * Whether a failure code means the 24-hour session window is closed.
     *
     * @since 2.4.0
     * @param string $code | Failure code from the send details.
     * @return bool
     */
    public static function is_window_closed( $code ) {
        return self::WINDOW_CLOSED === (string) $code;
    }


    /**
     * Describe a failure code in a sentence an administrator can act on.
     *
     * Unknown codes fall back to the generic message, so a transport error that
     * has no entry here never surfaces as a raw slug.
     *
     * @since 2.4.0
     * @param string $code | Failure code from the send details.
     * @param string $fallback | Message used when the code has no description.
     * @return string
     */
    public static function describe( $code, $fallback = '' ) {
        $code = (string) $code;

        if ( '' === $fallback ) {
            $fallback = __( 'Could not send the message.', 'joinotify' );
        }

        $messages = self::messages();

        if ( isset( $messages[ $code ] ) ) {
            $message = $messages[ $code ];
        } elseif ( str_starts_with( $code, 'template_error_' ) ) {
            $message = __( 'WhatsApp refused the template. Check that it is approved, that its name and language match, and that every variable was filled.', 'joinotify' );
        } else {
            $message = $fallback;
        }

        /**
         * Filter the human-readable description of a send failure.
         *
         * @since 2.4.0
         * @param string $message Description shown to the administrator.
         * @param string $code Failure code from the send details.
         */
        return (string) apply_filters( 'Joinotify/Api/Send_Error/Description', $message, $code );
    }


    /**
     * Description of every failure code the transport reports by name.
     *
     * Built on each call because the strings are translated, and the locale is
     * only settled once the request knows who is looking at it.
     *
     * @since 2.4.0
     * @return array<string,string>
     */
    protected static function messages() {
        return array(
            self::WINDOW_CLOSED => __( 'This contact has not replied in the last 24 hours. WhatsApp only delivers free-form messages inside that window — use an approved template message to reach them.', 'joinotify' ),
            'retry_cancelled' => __( 'The resend of this message was cancelled from the history screen.', 'joinotify' ),
            'invalid_sender' => __( 'The sender number is not connected to this site. Check it under Joinotify → Settings → Phone numbers.', 'joinotify' ),
            'cloud_no_token' => __( 'The Joinotify account is not connected, so there is no way to reach the WhatsApp API. Connect it under Joinotify → Settings.', 'joinotify' ),
            'http_401' => __( 'The WhatsApp API rejected the credentials. Reconnect the Joinotify account under Joinotify → Settings.', 'joinotify' ),
            'http_403' => __( 'The WhatsApp API refused this request. The number may not be authorized to send on this account.', 'joinotify' ),
            'http_404' => __( 'The WhatsApp API could not find the sender number. Sync your numbers under Joinotify → Settings → Phone numbers.', 'joinotify' ),
            'http_429' => __( 'The WhatsApp API rate limit was reached. The message will be retried shortly.', 'joinotify' ),
        );
    }
}
