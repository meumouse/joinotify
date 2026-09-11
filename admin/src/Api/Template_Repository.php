<?php

namespace MeuMouse\Joinotify\Api;

use MeuMouse\Joinotify\Core\Helpers;

// Exit if accessed directly.
defined('ABSPATH') || exit;

/**
 * Read side of the WhatsApp message templates owned by the customer's account.
 *
 * Templates belong to a business account (WABA), not to a number, and are the
 * only way to write to someone outside the 24-hour window. The API already
 * serves them from a mirror it keeps fresh by webhook, so this layer only adds
 * a local cache (query routes are capped at 60 requests per minute) and the
 * shape the builder needs: the body preview and the list of variables each
 * template expects.
 *
 * @since 2.3.0
 * @package MeuMouse\Joinotify\Api
 * @author MeuMouse.com
 */
class Template_Repository {

    /**
     * Transient prefix holding a normalized listing per business account.
     *
     * @var string
     */
    const CACHE_PREFIX = 'joinotify_cloud_templates_';

    /**
     * How long a successful listing is served locally.
     *
     * Matches the 15 minutes the API itself uses before refreshing its mirror.
     *
     * @var int
     */
    const CACHE_TTL = 900;

    /**
     * Templates asked for per request.
     *
     * The API caps the page at 250; asking for it keeps the picker complete for
     * accounts with many templates, which would otherwise be cut at the default
     * page of 100 with no visible sign.
     *
     * @var int
     */
    const PAGE_SIZE = 250;

    /**
     * Option holding the last known copy of every listed template.
     *
     * The listing cache expires after 15 minutes, but the send path still has
     * to know what a template says to record the message the recipient read.
     * This copy has no expiry: it is refreshed on every listing and only read
     * when the cache is cold.
     *
     * @since 2.4.1
     * @var string
     */
    const SNAPSHOT_OPTION = 'joinotify_template_snapshots';

    /**
     * Transient prefix marking a template the listing did not have.
     *
     * Keeps the send path from asking the API again, message after message,
     * for a template that is not there. It shares the listing cache prefix, so
     * flush_cache() — which the template webhooks call when a template is
     * approved or changed — clears these marks along with the listings.
     *
     * @since 2.4.2
     * @var string
     */
    const MISS_PREFIX = self::CACHE_PREFIX . 'miss_';

    /**
     * How long a missing template waits before the listing is asked again.
     *
     * @since 2.4.2
     * @var int
     */
    const MISS_TTL = 600;

    /**
     * What a masked parameter value is replaced with.
     *
     * @since 2.4.1
     * @var string
     */
    const MASK = '••••••';


    /**
     * List the templates of a business account.
     *
     * @since 2.3.0
     * @param array $args | Optional `status`, `waba_id` and `force`.
     * @return array|\WP_Error {
     *     @type array  $templates  Normalized templates.
     *     @type string $waba_id    Business account the list came from.
     *     @type string $synced_at  When the API mirror was last refreshed.
     *     @type bool   $stale      True when the mirror could not be refreshed.
     *     @type string $sync_error Reason the refresh failed, when it did.
     * }
     */
    public static function get_templates( $args = array() ) {
        $waba_id = isset( $args['waba_id'] ) ? (string) $args['waba_id'] : '';
        $force = ! empty( $args['force'] );
        $cache_key = self::CACHE_PREFIX . md5( $waba_id );

        if ( ! $force ) {
            $cached = get_transient( $cache_key );

            if ( is_array( $cached ) ) {
                return $cached;
            }
        }

        if ( ! Helpers::cloud_api_ready() ) {
            return new \WP_Error( 'joinotify_cloud_no_token', __( 'Connect your Joinotify account to load message templates.', 'joinotify' ) );
        }

        $response = Cloud_Client::list_templates( array(
            'status' => $args['status'] ?? '',
            'waba_id' => $waba_id,
            // The API pages at 100 by default; ask for the cap so a big account
            // does not silently lose templates from the picker.
            'limit' => self::PAGE_SIZE,
            // The mirror maintains itself; only ask the API to hit Meta when the
            // user explicitly asked for fresh data.
            'refresh' => $force,
        ) );

        if ( is_wp_error( $response ) ) {
            return $response;
        }

        if ( ! is_array( $response ) ) {
            return new \WP_Error( 'joinotify_cloud_bad_response', __( 'The templates could not be read.', 'joinotify' ) );
        }

        if ( isset( $response['error'] ) ) {
            $message = $response['error']['message'] ?? __( 'The templates could not be read.', 'joinotify' );

            return new \WP_Error( 'joinotify_cloud_templates_failed', $message );
        }

        $templates = array();

        foreach ( (array) ( $response['data'] ?? array() ) as $template ) {
            if ( is_array( $template ) ) {
                $templates[] = self::normalize( $template );
            }
        }

        $result = array(
            'templates' => $templates,
            // What the account holds, so a listing cut by the page cap is visible.
            'total' => isset( $response['total'] ) ? (int) $response['total'] : count( $templates ),
            'waba_id' => (string) ( $response['wabaId'] ?? $waba_id ),
            'synced_at' => (string) ( $response['syncedAt'] ?? '' ),
            'stale' => ! empty( $response['stale'] ),
            'sync_error' => (string) ( $response['syncError'] ?? '' ),
        );

        // Cache successful, non-empty listings only, so an outage never poisons
        // the picker with an empty catalogue.
        if ( ! empty( $templates ) ) {
            set_transient( $cache_key, $result, self::CACHE_TTL );
            self::store_snapshots( $templates );
        }

        return $result;
    }


    /**
     * Find one synced template by name.
     *
     * Reads local data only: callers use it on the send path, where a round
     * trip to the API would delay the message for a detail the cache almost
     * always has. The listing cache is tried first and the long-lived snapshot
     * second, so an expired cache no longer means an unknown template.
     *
     * The same name can be approved in several languages; when `$language` is
     * given the matching one wins, otherwise the first one found is returned.
     *
     * @since 2.3.0
     * @version 2.4.1
     * @param string $name | Template name as approved on Meta.
     * @param string $language | Optional language code to prefer (e.g. pt_BR).
     * @return array|null Normalized template, or null when it is not known locally.
     */
    public static function find( $name, $language = '' ) {
        $name = trim( (string) $name );
        $language = trim( (string) $language );

        if ( '' === $name ) {
            return null;
        }

        $cached = get_transient( self::CACHE_PREFIX . md5( '' ) );
        $match = null;

        if ( is_array( $cached ) && ! empty( $cached['templates'] ) ) {
            $match = self::pick( (array) $cached['templates'], $name, $language );

            // The fresh listing wins unless it only has the name in another
            // language, in which case the snapshot may still know the right one.
            if ( null !== $match && ( '' === $language || ( $match['language'] ?? '' ) === $language ) ) {
                return $match;
            }
        }

        $snapshots = get_option( self::SNAPSHOT_OPTION, array() );
        $snapshot = is_array( $snapshots ) ? self::pick( $snapshots, $name, $language ) : null;

        if ( null !== $snapshot && ( null === $match || ( $snapshot['language'] ?? '' ) === $language ) ) {
            return $snapshot;
        }

        return $match;
    }


    /**
     * Find a template for a send, loading the listing once when nothing local knows it.
     *
     * find() only reads what a listing left behind, and a listing only runs
     * when someone opens the template picker. A site that just sends — the
     * usual case right after an update, when the snapshot is still empty —
     * would otherwise never learn what its templates say. On a miss this asks
     * for the listing, which costs nothing while the 15-minute cache is fresh
     * and one request to the API mirror when it is cold, and fills the
     * snapshot for every send after it.
     *
     * A template the listing still does not have is marked for MISS_TTL, so
     * sending it again does not repeat the request each time.
     *
     * @since 2.4.2
     * @param string $name | Template name as approved on Meta.
     * @param string $language | Optional language code to prefer (e.g. pt_BR).
     * @return array|null Normalized template, or null when the account does not list it.
     */
    public static function find_or_fetch( $name, $language = '' ) {
        $template = self::find( $name, $language );
        $name = trim( (string) $name );

        if ( null !== $template || '' === $name ) {
            return $template;
        }

        $miss_key = self::MISS_PREFIX . md5( $name . '|' . trim( (string) $language ) );

        if ( false !== get_transient( $miss_key ) ) {
            return null;
        }

        // A failed listing (no key, API down) is a miss like any other: the
        // mark is what stops an outage from costing one request per message.
        self::get_templates();

        $template = self::find( $name, $language );

        if ( null === $template ) {
            set_transient( $miss_key, 1, self::MISS_TTL );
        }

        return $template;
    }


    /**
     * Bring every template parameter Meta would refuse down to one line.
     *
     * Meta refuses a text parameter holding a line break, a tab or more than
     * four spaces in a row (error 132018). Workflow values are already built
     * that way by Workflow_Processor::build_template_components(), but other
     * callers hand over their own components: extensions, the OTP components
     * filter, a retry stored by a version that did not flatten yet. Only the
     * values Meta would refuse are rewritten; everything else is returned as
     * given.
     *
     * @since 2.4.2
     * @param array $components | Meta components payload.
     * @return array
     */
    public static function flatten_components( $components ) {
        if ( ! is_array( $components ) ) {
            return array();
        }

        foreach ( $components as $c => $component ) {
            if ( ! is_array( $component ) || ! is_array( $component['parameters'] ?? null ) ) {
                continue;
            }

            foreach ( $component['parameters'] as $p => $parameter ) {
                if ( ! is_array( $parameter ) || 'text' !== strtolower( (string) ( $parameter['type'] ?? '' ) ) || ! isset( $parameter['text'] ) ) {
                    continue;
                }

                $text = (string) $parameter['text'];

                if ( self::breaks_one_line_rule( $text ) ) {
                    $components[ $c ]['parameters'][ $p ]['text'] = self::flatten_parameter_text( $text );
                }
            }
        }

        return $components;
    }


    /**
     * Join the lines of a value with a comma and collapse runs of blanks.
     *
     * An address formatted over several lines, or a list with one item per
     * line, still reads as one: "Rua A, 1\nCentro" becomes "Rua A, 1, Centro".
     * Blank lines are dropped, and a line already ending in a comma or a
     * semicolon does not get a second separator.
     *
     * @since 2.4.2
     * @param string $value | Plain-text value.
     * @return string
     */
    public static function flatten_parameter_text( $value ) {
        $value = (string) $value;

        // \R understands every Unicode line break but gives up on malformed
        // UTF-8; the byte-level split still catches the ones Meta refuses.
        $split = preg_split( '/\R/u', $value );

        if ( false === $split ) {
            $split = preg_split( '/\r\n|[\r\n\v\f]/', $value ) ?: array( $value );
        }

        $lines = array();

        foreach ( $split as $line ) {
            $line = rtrim( trim( $line ), ',;' );

            if ( '' !== trim( $line ) ) {
                $lines[] = trim( $line );
            }
        }

        return (string) preg_replace( '/[\t ]+/', ' ', implode( ', ', $lines ) );
    }


    /**
     * Whether a value breaks Meta's one-line rule for template parameters.
     *
     * @since 2.4.2
     * @param string $text | Parameter value.
     * @return bool
     */
    protected static function breaks_one_line_rule( $text ) {
        $found = preg_match( '/\R|\t| {5,}/u', $text );

        if ( false === $found ) {
            $found = preg_match( '/[\r\n\t\v\f]| {5,}/', $text );
        }

        return 1 === $found;
    }


    /**
     * Pick a template by name from a list, preferring the given language.
     *
     * @since 2.4.1
     * @param array  $templates | Normalized templates.
     * @param string $name | Template name.
     * @param string $language | Language to prefer, or empty for any.
     * @return array|null
     */
    protected static function pick( $templates, $name, $language ) {
        $fallback = null;

        foreach ( $templates as $template ) {
            if ( ! is_array( $template ) || ( $template['name'] ?? '' ) !== $name ) {
                continue;
            }

            if ( '' === $language || ( $template['language'] ?? '' ) === $language ) {
                return $template;
            }

            $fallback = $fallback ?? $template;
        }

        return $fallback;
    }


    /**
     * Keep a long-lived copy of the listed templates.
     *
     * Entries are merged by name and language rather than replaced, so a
     * listing filtered by status or scoped to another business account never
     * erases what the others taught.
     *
     * @since 2.4.1
     * @param array $templates | Normalized templates from a listing.
     * @return void
     */
    protected static function store_snapshots( $templates ) {
        $snapshots = get_option( self::SNAPSHOT_OPTION, array() );
        $snapshots = is_array( $snapshots ) ? $snapshots : array();
        $changed = false;

        foreach ( $templates as $template ) {
            if ( ! is_array( $template ) || '' === ( $template['name'] ?? '' ) ) {
                continue;
            }

            $key = $template['name'] . '|' . ( $template['language'] ?? '' );

            if ( ( $snapshots[ $key ] ?? null ) !== $template ) {
                $snapshots[ $key ] = $template;
                $changed = true;
            }
        }

        // Skip the write when nothing moved: listings are frequent and the
        // catalogue rarely changes between them.
        if ( $changed ) {
            update_option( self::SNAPSHOT_OPTION, $snapshots, false );
        }
    }


    /**
     * Describe a template send the way it reached the recipient.
     *
     * Fills the template's header, body and footer with the parameter values
     * being sent, so the history can show the message that was read rather
     * than a bare template name. Values are matched to the `{{...}}` tokens in
     * the order the template declares them, which is the same order the
     * builder packs them in. The template is looked up with find_or_fetch(),
     * so the first send on a site whose snapshot is still empty loads the
     * listing instead of giving up. Only when the account does not list the
     * template does the text fall back to the name followed by one line per
     * parameter.
     *
     * Masking replaces every value with {@see self::MASK} — in the text, the
     * parameter list and the returned components alike. It is forced for
     * AUTHENTICATION templates, whose only variable is a login code.
     *
     * @since 2.4.1
     * @version 2.4.2
     * @param string $name | Template name.
     * @param string $language | Template language code.
     * @param array  $components | Meta components payload being sent.
     * @param bool   $mask | Whether to hide the parameter values.
     * @return array {
     *     @type string $text          Rendered message.
     *     @type array  $parameters    One entry per value: component, index, key, value.
     *     @type array  $components    Components payload, masked when masking applies.
     *     @type string $rendered_from 'snapshot' when the template text was known, 'fallback' otherwise.
     *     @type bool   $masked        Whether the values were masked.
     * }
     */
    public static function render( $name, $language = '', $components = array(), $mask = false ) {
        $name = trim( (string) $name );
        $template = self::find_or_fetch( $name, $language );
        $components = is_array( $components ) ? array_values( $components ) : array();

        if ( is_array( $template ) && 'AUTHENTICATION' === ( $template['category'] ?? '' ) ) {
            $mask = true;
        }

        if ( $mask ) {
            $components = self::mask_components( $components );
        }

        $parameters = self::flatten_parameters( $components, $template );

        if ( is_array( $template ) ) {
            $text = self::fill_template( $template, $parameters );
            $rendered_from = 'snapshot';
        } else {
            $text = self::describe_parameters( $name, $language, $parameters );
            $rendered_from = 'fallback';
        }

        return array(
            'text' => $text,
            'parameters' => $parameters,
            'components' => $components,
            'rendered_from' => $rendered_from,
            'masked' => (bool) $mask,
        );
    }


    /**
     * Replace every parameter value in a components payload with the mask.
     *
     * @since 2.4.1
     * @param array $components | Meta components payload.
     * @return array
     */
    protected static function mask_components( $components ) {
        foreach ( $components as $c => $component ) {
            if ( ! is_array( $component ) || ! is_array( $component['parameters'] ?? null ) ) {
                continue;
            }

            foreach ( $component['parameters'] as $p => $parameter ) {
                $components[ $c ]['parameters'][ $p ] = array(
                    'type' => 'text',
                    'text' => self::MASK,
                );
            }
        }

        return $components;
    }


    /**
     * Flatten a components payload into one entry per parameter value.
     *
     * The variable name comes from the template when it is known; without it
     * the position inside the component (1, 2, ...) stands in, which is what a
     * positional template calls them anyway.
     *
     * @since 2.4.1
     * @param array      $components | Meta components payload.
     * @param array|null $template | Normalized template, when known.
     * @return array<int,array{component:string,index:int,key:string,value:string}>
     */
    protected static function flatten_parameters( $components, $template ) {
        $variables = is_array( $template ) ? (array) ( $template['variables'] ?? array() ) : array();
        $flat = array();

        foreach ( $components as $component ) {
            if ( ! is_array( $component ) ) {
                continue;
            }

            $type = strtolower( (string) ( $component['type'] ?? 'body' ) );
            $index = (int) ( $component['index'] ?? 0 );

            // The template's own variable names for this component, in order.
            $keys = array();

            foreach ( $variables as $variable ) {
                if ( ! is_array( $variable ) || ( $variable['component'] ?? '' ) !== $type ) {
                    continue;
                }

                if ( 'button' === $type && (int) ( $variable['index'] ?? 0 ) !== $index ) {
                    continue;
                }

                $keys[] = (string) ( $variable['key'] ?? '' );
            }

            foreach ( array_values( (array) ( $component['parameters'] ?? array() ) ) as $position => $parameter ) {
                $key = (string) ( $parameter['parameter_name'] ?? '' );

                if ( '' === $key ) {
                    $key = '' !== ( $keys[ $position ] ?? '' ) ? $keys[ $position ] : (string) ( $position + 1 );
                }

                $flat[] = array(
                    'component' => $type,
                    'index' => $index,
                    'key' => $key,
                    'value' => self::parameter_value( $parameter ),
                );
            }
        }

        return $flat;
    }


    /**
     * Read the value a recipient sees for one Meta parameter object.
     *
     * @since 2.4.1
     * @param mixed $parameter | Meta parameter object.
     * @return string
     */
    protected static function parameter_value( $parameter ) {
        if ( ! is_array( $parameter ) ) {
            return is_scalar( $parameter ) ? (string) $parameter : '';
        }

        $type = strtolower( (string) ( $parameter['type'] ?? 'text' ) );

        switch ( $type ) {
            case 'text':
                return (string) ( $parameter['text'] ?? '' );

            case 'currency':
            case 'date_time':
                return (string) ( $parameter[ $type ]['fallback_value'] ?? '' );

            case 'image':
            case 'video':
            case 'document':
                return (string) ( $parameter[ $type ]['link'] ?? ( $parameter[ $type ]['id'] ?? '' ) );

            case 'payload':
                return (string) ( $parameter['payload'] ?? '' );
        }

        $json = wp_json_encode( $parameter );

        return false !== $json ? $json : '';
    }


    /**
     * Write the template's text with the parameter values in place.
     *
     * Button values have no place in the text — they live in the button URL —
     * so they are only kept in the parameter list. A token with no value is
     * left as-is, which is exactly what makes a missing variable visible.
     *
     * @since 2.4.1
     * @param array $template | Normalized template.
     * @param array $parameters | Flattened parameters.
     * @return string
     */
    protected static function fill_template( $template, $parameters ) {
        $parts = array();

        foreach ( array( 'header', 'body', 'footer' ) as $type ) {
            $text = (string) ( $template[ $type ] ?? '' );

            if ( '' === trim( $text ) ) {
                continue;
            }

            foreach ( $parameters as $parameter ) {
                if ( $parameter['component'] !== $type ) {
                    continue;
                }

                $text = preg_replace(
                    '/\{\{\s*' . preg_quote( $parameter['key'], '/' ) . '\s*\}\}/',
                    // Escape backreference syntax so a value like "$1" stays literal.
                    addcslashes( $parameter['value'], '\\$' ),
                    $text
                );
            }

            $parts[] = $text;
        }

        return implode( "\n\n", $parts );
    }


    /**
     * Describe a template whose text is not known locally.
     *
     * @since 2.4.1
     * @param string $name | Template name.
     * @param string $language | Template language code.
     * @param array  $parameters | Flattened parameters.
     * @return string
     */
    protected static function describe_parameters( $name, $language, $parameters ) {
        if ( '' === $name ) {
            return '';
        }

        $lines = array( '' !== trim( (string) $language ) ? sprintf( '%s (%s)', $name, $language ) : $name );

        foreach ( $parameters as $parameter ) {
            if ( 'body' === $parameter['component'] ) {
                $prefix = '';
            } elseif ( 'button' === $parameter['component'] ) {
                // Meta counts buttons from zero; people count them from one.
                $prefix = 'button #' . ( $parameter['index'] + 1 ) . ' ';
            } else {
                $prefix = $parameter['component'] . ' ';
            }

            $lines[] = sprintf( '%s{{%s}} = %s', $prefix, $parameter['key'], $parameter['value'] );
        }

        return implode( "\n", $lines );
    }


    /**
     * Ask the API to reconcile its mirror with Meta and drop the local cache.
     *
     * @since 2.3.0
     * @return array|\WP_Error
     */
    public static function sync() {
        $response = Cloud_Client::sync_templates();

        if ( is_wp_error( $response ) ) {
            return $response;
        }

        self::flush_cache();

        return is_array( $response ) ? $response : array();
    }


    /**
     * Drop every cached listing.
     *
     * @since 2.3.0
     * @return void
     */
    public static function flush_cache() {
        global $wpdb;

        $like = $wpdb->esc_like( '_transient_' . self::CACHE_PREFIX ) . '%';
        $timeout_like = $wpdb->esc_like( '_transient_timeout_' . self::CACHE_PREFIX ) . '%';

        // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching -- Transient names are only discoverable by pattern, so there is no delete_transient() equivalent; this call is itself the cache invalidation.
        $wpdb->query( $wpdb->prepare( "DELETE FROM {$wpdb->options} WHERE option_name LIKE %s OR option_name LIKE %s", $like, $timeout_like ) );
    }


    /**
     * Reduce an API template to what the builder needs.
     *
     * @since 2.3.0
     * @param array $template | Raw template from the API.
     * @return array
     */
    protected static function normalize( $template ) {
        $components = isset( $template['components'] ) && is_array( $template['components'] ) ? $template['components'] : array();

        return array(
            'id' => (string) ( $template['id'] ?? '' ),
            'name' => (string) ( $template['name'] ?? '' ),
            'language' => (string) ( $template['language'] ?? '' ),
            'status' => strtoupper( (string) ( $template['status'] ?? '' ) ),
            'category' => strtoupper( (string) ( $template['category'] ?? '' ) ),
            'quality' => strtoupper( (string) ( $template['quality_score']['score'] ?? '' ) ),
            'rejected_reason' => (string) ( $template['rejected_reason'] ?? '' ),
            'header' => self::component_text( $components, 'HEADER' ),
            'body' => self::component_text( $components, 'BODY' ),
            'footer' => self::component_text( $components, 'FOOTER' ),
            'variables' => self::extract_variables( $components ),
        );
    }


    /**
     * Read the text of a component by type.
     *
     * @since 2.3.0
     * @param array  $components | Template components.
     * @param string $type | Component type (HEADER, BODY, FOOTER).
     * @return string
     */
    protected static function component_text( $components, $type ) {
        foreach ( $components as $component ) {
            if ( ! is_array( $component ) ) {
                continue;
            }

            if ( strtoupper( (string) ( $component['type'] ?? '' ) ) === $type ) {
                return (string) ( $component['text'] ?? '' );
            }
        }

        return '';
    }


    /**
     * List every variable the template expects, in the order Meta wants them.
     *
     * Both dialects Meta accepts are read: positional (`{{1}}`) and named
     * (`{{nome}}`). The `key` is what goes back in the parameter object, and the
     * `component` plus `index` say where the value belongs when the send payload
     * is assembled.
     *
     * @since 2.3.0
     * @param array $components | Template components.
     * @return array
     */
    protected static function extract_variables( $components ) {
        $variables = array();

        foreach ( $components as $component ) {
            if ( ! is_array( $component ) ) {
                continue;
            }

            $type = strtolower( (string) ( $component['type'] ?? '' ) );

            if ( 'buttons' === $type ) {
                foreach ( (array) ( $component['buttons'] ?? array() ) as $index => $button ) {
                    if ( ! is_array( $button ) || 'URL' !== strtoupper( (string) ( $button['type'] ?? '' ) ) ) {
                        continue;
                    }

                    foreach ( self::tokens_in( (string) ( $button['url'] ?? '' ) ) as $token ) {
                        $variables[] = array(
                            'component' => 'button',
                            'sub_type' => 'url',
                            'index' => (int) $index,
                            'key' => $token,
                            'label' => (string) ( $button['text'] ?? '' ),
                        );
                    }
                }

                continue;
            }

            // Footers cannot carry variables, and headers are limited to one.
            if ( ! in_array( $type, array( 'header', 'body' ), true ) ) {
                continue;
            }

            foreach ( self::tokens_in( (string) ( $component['text'] ?? '' ) ) as $token ) {
                $variables[] = array(
                    'component' => $type,
                    'sub_type' => '',
                    'index' => 0,
                    'key' => $token,
                    'label' => '',
                );
            }
        }

        return $variables;
    }


    /**
     * Pull the `{{...}}` tokens out of a string, preserving order and dropping
     * repeats.
     *
     * @since 2.3.0
     * @param string $text | Component text.
     * @return string[]
     */
    protected static function tokens_in( $text ) {
        if ( ! preg_match_all( '/\{\{\s*([A-Za-z0-9_]+)\s*\}\}/', $text, $matches ) ) {
            return array();
        }

        return array_values( array_unique( $matches[1] ) );
    }
}
