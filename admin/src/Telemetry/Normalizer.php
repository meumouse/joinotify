<?php

namespace MeuMouse\Joinotify\Telemetry;

// Exit if accessed directly.
defined('ABSPATH') || exit;

/**
 * Value normalization for telemetry — the last thing that runs before a value leaves.
 *
 * Two jobs, and both are about a number that never shows up in code review: cardinality.
 *
 * The service turns some of these values into counter names, so a value drawn from an
 * unbounded set — an HTTP status we have never seen, a Meta error code invented last
 * week, a PHP errno — would create a counter row per distinct value and quietly turn a
 * small aggregate table into a large one. Every map below is closed, and anything that
 * falls off the end becomes 'other'. A new code earns its own bucket in a release, never
 * at runtime.
 *
 * The second job is redaction. The service redacts too, but by then the value has already
 * left the site, and that is the part that cannot be undone. Whatever looks like a phone
 * number, an address or a credential is dropped here, before the request is built.
 *
 * Every method is pure: no options, no hooks, no database. That is deliberate — it is
 * what lets the whole guarantee be covered by tests that run without WordPress.
 *
 * @since 2.5.0
 * @package MeuMouse\Joinotify\Telemetry
 * @author MeuMouse.com
 */
class Normalizer {

    /**
     * Maximum length of a slug value, mirroring the service.
     *
     * @since 2.5.0
     * @var int
     */
    const MAX_SLUG = 60;


    /**
     * Maximum length of a version value, mirroring the service.
     *
     * @since 2.5.0
     * @var int
     */
    const MAX_VERSION = 40;


    /**
     * Template errors that earn their own counter.
     *
     * Same list the Cloud client already special-cases; anything outside it collapses
     * into a single bucket, because a template error we have never seen is not worth a
     * permanent row.
     *
     * @since 2.5.0
     * @var array<int,int>
     */
    const TEMPLATE_CODES = array( 132000, 132001, 132005, 132007, 132012, 132015, 132016 );


    /**
     * HTTP statuses this integration actually produces.
     *
     * @since 2.5.0
     * @var array<int,int>
     */
    const HTTP_CODES = array( 400, 401, 403, 404, 405, 408, 409, 413, 422, 425, 429, 500, 502, 503, 504 );


    /**
     * Graph API error types, normalized to snake case.
     *
     * @since 2.5.0
     * @var array<string,string>
     */
    const META_TYPES = array(
        'oauthexception' => 'meta_oauth',
        'graphmethodexception' => 'meta_graph_method',
        'facebookapiexception' => 'meta_api',
        'igapiexception' => 'meta_ig_api',
    );


    /**
     * PHP error levels, by errno.
     *
     * @since 2.5.0
     * @var array<int,string>
     */
    const PHP_LEVELS = array(
        1 => 'php_fatal',
        2 => 'php_warning',
        8 => 'php_notice',
        256 => 'php_user_error',
        512 => 'php_user_warning',
        1024 => 'php_user_notice',
        4096 => 'php_recoverable',
        8192 => 'php_deprecated',
        16384 => 'php_deprecated',
    );


    /**
     * Error codes the plugin raises itself, and that are already a closed set.
     *
     * @since 2.5.0
     * @var array<int,string>
     */
    const INTERNAL_CODES = array(
        'window_closed_requires_template',
        'channel_unavailable',
        'channel_unconfigured',
        'channel_unsupported',
        'invalid_channel_result',
        'invalid_queue_type',
        'missing_phone_number_id',
        'invalid_media',
        'unknown_response',
        'php_fatal',
        'joinotify_cloud_no_token',
        'joinotify_cloud_senders_failed',
        'joinotify_connect_no_license',
        'joinotify_connect_invalid_key',
    );


    /**
     * Normalize a slug value, or return null to drop it.
     *
     * Lower-cased on the way out even though the service would accept mixed case: the
     * value can end up inside a counter name, and 'Cart_Abandonment' and
     * 'cart_abandonment' arriving from two sites would count as two different features.
     *
     * @since 2.5.0
     * @param mixed $value | Raw value.
     * @return string|null
     */
    public static function slug( $value ) {
        if ( ! is_string( $value ) && ! is_int( $value ) ) {
            return null;
        }

        $value = strtolower( trim( (string) $value ) );

        if ( '' === $value || self::is_sensitive( $value ) ) {
            return null;
        }

        $value = substr( $value, 0, self::MAX_SLUG );

        return preg_match( '/^[a-z0-9][a-z0-9_.:-]*$/', $value ) ? $value : null;
    }


    /**
     * Normalize a version string, or return null to drop it.
     *
     * @since 2.5.0
     * @param mixed $value | Raw value.
     * @return string|null
     */
    public static function version( $value ) {
        if ( ! is_string( $value ) ) {
            return null;
        }

        $value = strtolower( trim( $value ) );

        // Deliberately NOT truncated: a distribution suffix like
        // '8.2.20-1+ubuntu22.04.1+deb.sury.org' is longer than the service accepts, and
        // cutting it would invent a version that does not exist. Dropping the field says
        // "unknown", which is true; a made-up '8.2.20-1+ubuntu22.04.1+deb.su' is not.
        if ( '' === $value || strlen( $value ) > self::MAX_VERSION ) {
            return null;
        }

        return preg_match( '/^[0-9][0-9a-z.+_-]*$/', $value ) ? $value : null;
    }


    /**
     * Keep a value only when it is one of the allowed ones.
     *
     * @since 2.5.0
     * @param mixed $value | Raw value.
     * @param array $allowed | Allowed values.
     * @return string|null
     */
    public static function enum( $value, $allowed ) {
        if ( ! is_string( $value ) ) {
            return null;
        }

        $value = strtolower( trim( $value ) );

        return in_array( $value, (array) $allowed, true ) ? $value : null;
    }


    /**
     * Normalize an integer inside a range, or return null to drop it.
     *
     * @since 2.5.0
     * @param mixed $value | Raw value.
     * @param int $min | Lower bound, inclusive.
     * @param int $max | Upper bound, inclusive.
     * @return int|null
     */
    public static function number( $value, $min, $max ) {
        if ( ! is_int( $value ) && ! ( is_string( $value ) && preg_match( '/^-?\d+$/', $value ) ) ) {
            return null;
        }

        $value = (int) $value;

        return ( $value >= (int) $min && $value <= (int) $max ) ? $value : null;
    }


    /**
     * Normalize a list of slugs into the comma-joined form the service stores.
     *
     * Sorted and de-duplicated so that two sites running the same integrations produce
     * the same value and can be grouped.
     *
     * @since 2.5.0
     * @param mixed $value | Array of slugs, or an already joined list.
     * @param int $max | Maximum number of items kept.
     * @return string|null
     */
    public static function slug_list( $value, $max ) {
        if ( is_string( $value ) ) {
            $value = explode( ',', $value );
        }

        if ( ! is_array( $value ) ) {
            return null;
        }

        $clean = array();

        foreach ( $value as $item ) {
            $slug = self::slug( $item );

            // One odd third-party slug must not cost the whole inventory of that site.
            if ( null !== $slug ) {
                $clean[] = $slug;
            }
        }

        $clean = array_values( array_unique( $clean ) );
        sort( $clean );
        $clean = array_slice( $clean, 0, (int) $max );

        return empty( $clean ) ? null : implode( ',', $clean );
    }


    /**
     * Whether a value looks like personal data or a credential.
     *
     * Mirrors the patterns the service redacts. Running them here as well is not
     * belt-and-braces for its own sake: the service redacts a value that already left
     * the site, and leaving the site is the part that cannot be taken back.
     *
     * @since 2.5.0
     * @param string $value | Value to inspect.
     * @return bool
     */
    public static function is_sensitive( $value ) {
        $value = (string) $value;

        // A run of digits long enough to be a phone number, a document or a wamid. The
        // slug alphabet accepts digits, so the allow-list alone never catches this.
        if ( preg_match( '/\d{8,}/', $value ) ) {
            return true;
        }

        if ( preg_match( '/[^\s@]+@[^\s@]+\.[^\s@]+/', $value ) ) {
            return true;
        }

        if ( preg_match( '/(sk_(live|test)_|bearer\s|^eaa)/i', $value ) ) {
            return true;
        }

        // JWT.
        return (bool) preg_match( '/^[A-Za-z0-9_-]{8,}\.[A-Za-z0-9_-]{8,}\.[A-Za-z0-9_-]{8,}$/', $value );
    }


    /**
     * Collapse an error code into one of a closed set of buckets.
     *
     * The Cloud client builds codes by concatenation — 'template_error_<meta_code>',
     * 'http_<code>' — and the debug logger adds 'php_<errno>'. Sent as they are, each
     * distinct value becomes a permanent counter row on the service. Everything here
     * either matches a known bucket or becomes 'other'; the detail that is lost this way
     * is carried by the fingerprint instead.
     *
     * @since 2.5.0
     * @param mixed $raw | Raw error code or message.
     * @return string
     */
    public static function error_code( $raw ) {
        if ( ! is_string( $raw ) && ! is_int( $raw ) ) {
            return 'other';
        }

        $raw = strtolower( trim( (string) $raw ) );

        if ( '' === $raw ) {
            return 'other';
        }

        if ( in_array( $raw, self::INTERNAL_CODES, true ) ) {
            return $raw;
        }

        if ( 0 === strpos( $raw, 'template_error_' ) ) {
            $code = (int) substr( $raw, strlen('template_error_') );

            return in_array( $code, self::TEMPLATE_CODES, true ) ? 'template_error_' . $code : 'template_error_other';
        }

        if ( 0 === strpos( $raw, 'http_' ) ) {
            $code = (int) substr( $raw, strlen('http_') );

            return in_array( $code, self::HTTP_CODES, true ) ? 'http_' . $code : 'http_other';
        }

        if ( 0 === strpos( $raw, 'php_' ) ) {
            $errno = substr( $raw, strlen('php_') );

            if ( preg_match( '/^\d+$/', $errno ) ) {
                return isset( self::PHP_LEVELS[ (int) $errno ] ) ? self::PHP_LEVELS[ (int) $errno ] : 'php_other';
            }

            return in_array( $raw, self::PHP_LEVELS, true ) ? $raw : 'php_other';
        }

        if ( isset( self::META_TYPES[ $raw ] ) ) {
            return self::META_TYPES[ $raw ];
        }

        // Anything ending in 'exception' came from the Graph API and is a Meta problem,
        // even when we have never seen that particular type.
        if ( 'exception' === substr( $raw, -9 ) ) {
            return 'meta_other';
        }

        return 'other';
    }


    /**
     * Stable identifier for the place in the code an error came from.
     *
     * Hashing the message would be the obvious thing and is exactly wrong: messages carry
     * order numbers, wamids and timestamps, so the set of distinct values is unbounded —
     * and the service counts this field. Hashing the call site instead bounds it to the
     * number of lines in the plugin that can fail, which is a few dozen. It also happens
     * to be the useful grouping: "this error comes from this line".
     *
     * @since 2.5.0
     * @param string $code | Already normalized error code.
     * @param string $channel | Logical area (api, cron, rest...).
     * @param string $file | Absolute path of the file that raised it.
     * @param int $line | Line number.
     * @return string
     */
    public static function fingerprint( $code, $channel, $file, $line ) {
        $parts = array(
            (string) $code,
            (string) $channel,
            '' === (string) $file ? '' : basename( (string) $file ),
            (string) (int) $line,
        );

        return substr( md5( implode( '|', $parts ) ), 0, 10 );
    }
}
