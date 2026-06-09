<?php

namespace MeuMouse\Joinotify\Builder;

// Exit if accessed directly.
defined('ABSPATH') || exit;

/**
 * Manage user defined custom text variables (placeholders).
 *
 * Each custom variable maps an entity (post_type) and a meta key to a named
 * token (e.g. {{ product_sku }}) that becomes available in the flow builder.
 * Variables are stored on the joinotify_custom_variables option and registered
 * into the builder placeholders catalog through the
 * Joinotify/Builder/Placeholders_List filter, so they resolve from the trigger
 * payload at runtime using standard get_post_meta().
 *
 * @since 2.0.0
 * @package MeuMouse\Joinotify\Builder
 * @author MeuMouse.com
 */
class Custom_Variables {

    /**
     * Option name where custom variables are stored.
     *
     * @var string
     */
    const OPTION = 'joinotify_custom_variables';

    /**
     * Register the placeholder filter hook.
     *
     * @since 2.0.0
     * @return void
     */
    public function __construct() {
        add_filter( 'Joinotify/Builder/Placeholders_List', array( __CLASS__, 'register_placeholders' ), 20, 2 );
    }


    /**
     * Get all custom variables, normalized.
     *
     * @since 2.0.0
     * @return array<int,array<string,string>>
     */
    public static function get_all() {
        $stored = get_option( self::OPTION, array() );

        if ( ! is_array( $stored ) ) {
            return array();
        }

        $variables = array();

        foreach ( $stored as $variable ) {
            $normalized = self::normalize( $variable );

            if ( '' !== $normalized['token'] ) {
                $variables[] = $normalized;
            }
        }

        return array_values( $variables );
    }


    /**
     * Normalize a single variable row.
     *
     * @since 2.0.0
     * @param mixed $variable Raw variable data.
     * @return array<string,string>
     */
    protected static function normalize( $variable ) {
        $variable = is_array( $variable ) ? $variable : array();

        return array(
            'id'          => isset( $variable['id'] ) ? sanitize_text_field( (string) $variable['id'] ) : '',
            'token'       => isset( $variable['token'] ) ? self::slugify_token( $variable['token'] ) : '',
            'label'       => isset( $variable['label'] ) ? sanitize_text_field( (string) $variable['label'] ) : '',
            'post_type'   => isset( $variable['post_type'] ) ? sanitize_key( (string) $variable['post_type'] ) : '',
            'meta_key'    => isset( $variable['meta_key'] ) ? sanitize_text_field( (string) $variable['meta_key'] ) : '',
            'description' => isset( $variable['description'] ) ? sanitize_text_field( (string) $variable['description'] ) : '',
            'example'     => isset( $variable['example'] ) ? sanitize_text_field( (string) $variable['example'] ) : '',
        );
    }


    /**
     * Create or update a custom variable.
     *
     * @since 2.0.0
     * @param array<string,mixed> $variable Variable data.
     * @return array{success:bool,message:string,variable?:array<string,string>}
     */
    public static function save( $variable ) {
        $variable = self::normalize( $variable );

        if ( '' === $variable['token'] ) {
            return array(
                'success' => false,
                'message' => esc_html__( 'Provide a valid variable name.', 'joinotify' ),
            );
        }

        if ( '' === $variable['post_type'] || '' === $variable['meta_key'] ) {
            return array(
                'success' => false,
                'message' => esc_html__( 'Select an entity and inform the meta key.', 'joinotify' ),
            );
        }

        // collision check against every registered placeholder + other custom vars
        if ( self::token_exists( $variable['token'], $variable['id'] ) ) {
            return array(
                'success' => false,
                'message' => sprintf(
                    /* translators: %s: variable token */
                    esc_html__( 'The variable "%s" already exists. Choose another name.', 'joinotify' ),
                    $variable['token']
                ),
            );
        }

        $variables = self::get_all();
        $found = false;

        if ( '' !== $variable['id'] ) {
            foreach ( $variables as $index => $existing ) {
                if ( $existing['id'] === $variable['id'] ) {
                    $variables[ $index ] = $variable;
                    $found = true;
                    break;
                }
            }
        }

        if ( ! $found ) {
            $variable['id'] = uniqid( 'jcv_', false );
            $variables[] = $variable;
        }

        update_option( self::OPTION, array_values( $variables ) );

        return array(
            'success'  => true,
            'message'  => esc_html__( 'Variable saved successfully.', 'joinotify' ),
            'variable' => $variable,
        );
    }


    /**
     * Delete a custom variable by id.
     *
     * @since 2.0.0
     * @param string $id Variable id.
     * @return bool
     */
    public static function delete( $id ) {
        $id = sanitize_text_field( (string) $id );

        if ( '' === $id ) {
            return false;
        }

        $variables = self::get_all();
        $filtered = array();
        $removed = false;

        foreach ( $variables as $variable ) {
            if ( $variable['id'] === $id ) {
                $removed = true;
                continue;
            }

            $filtered[] = $variable;
        }

        if ( $removed ) {
            update_option( self::OPTION, array_values( $filtered ) );
        }

        return $removed;
    }


    /**
     * Check if a token already exists among registered placeholders or custom variables.
     *
     * @since 2.0.0
     * @param string $token Token slug (without braces).
     * @param string $exclude_id Custom variable id to skip (when editing).
     * @return bool
     */
    public static function token_exists( $token, $exclude_id = '' ) {
        $token = self::slugify_token( $token );

        if ( '' === $token ) {
            return false;
        }

        // gather tokens already used by other custom variables
        foreach ( self::get_all() as $variable ) {
            if ( $variable['id'] === $exclude_id ) {
                continue;
            }

            if ( $variable['token'] === $token ) {
                return true;
            }
        }

        // gather every registered placeholder token across all groups
        $groups = apply_filters( 'Joinotify/Builder/Placeholders_List', array(), array() );

        if ( is_array( $groups ) ) {
            foreach ( $groups as $group_placeholders ) {
                if ( ! is_array( $group_placeholders ) ) {
                    continue;
                }

                foreach ( array_keys( $group_placeholders ) as $placeholder ) {
                    if ( self::normalize_token( $placeholder ) === $token ) {
                        return true;
                    }
                }
            }
        }

        return false;
    }


    /**
     * Strip braces/whitespace from a placeholder string to get its bare token.
     *
     * @since 2.0.0
     * @param string $placeholder Placeholder string e.g. "{{ token }}".
     * @return string
     */
    protected static function normalize_token( $placeholder ) {
        $placeholder = (string) $placeholder;
        $placeholder = str_replace( array( '{{', '}}' ), '', $placeholder );

        return self::slugify_token( $placeholder );
    }


    /**
     * Convert an arbitrary string into a token slug.
     *
     * Mirrors the frontend slugify so a name typed in the UI and a value sent
     * directly to the API resolve to the same token: lowercase, non
     * alphanumeric runs collapsed to a single underscore, trimmed.
     *
     * @since 2.0.0
     * @param mixed $value Raw value.
     * @return string
     */
    protected static function slugify_token( $value ) {
        $value = strtolower( (string) $value );
        $value = preg_replace( '/[^a-z0-9_]+/', '_', $value );
        $value = preg_replace( '/_+/', '_', $value );

        return trim( $value, '_' );
    }


    /**
     * Inject custom variables into the builder placeholders catalog.
     *
     * Registered as global placeholders (empty triggers) so they are available
     * in every workflow context.
     *
     * @since 2.0.0
     * @param array<string,mixed> $list Current placeholders grouped by integration.
     * @param mixed $context Context payload (unused, kept for filter signature).
     * @return array<string,mixed>
     */
    public static function register_placeholders( $list, $context = array() ) {
        if ( ! is_array( $list ) ) {
            $list = array();
        }

        $variables = self::get_all();

        if ( empty( $variables ) ) {
            return $list;
        }

        if ( ! isset( $list['custom'] ) || ! is_array( $list['custom'] ) ) {
            $list['custom'] = array();
        }

        foreach ( $variables as $variable ) {
            $token = $variable['token'];
            $post_type = $variable['post_type'];
            $meta_key = $variable['meta_key'];
            $placeholder = '{{ ' . $token . ' }}';
            $description = '' !== $variable['description'] ? $variable['description'] : $variable['label'];
            $sandbox = '' !== $variable['example'] ? $variable['example'] : ( '' !== $variable['label'] ? $variable['label'] : $token );

            $list['custom'][ $placeholder ] = array(
                'triggers'    => array(),
                'description' => '' !== $description ? $description : $token,
                'replacement' => array(
                    'sandbox'    => $sandbox,
                    'production' => function( $payload ) use ( $post_type, $meta_key ) {
                        $post_id = self::resolve_post_id( is_array( $payload ) ? $payload : array(), $post_type );

                        if ( ! $post_id ) {
                            return '';
                        }

                        $value = get_post_meta( $post_id, $meta_key, true );

                        if ( is_array( $value ) ) {
                            return implode( ', ', $value );
                        }

                        return is_scalar( $value ) ? (string) $value : '';
                    },
                ),
            );
        }

        return $list;
    }


    /**
     * Best-effort resolution of the post id to read meta from, based on the payload.
     *
     * @since 2.0.0
     * @param array<string,mixed> $payload Workflow runtime payload.
     * @param string $post_type Target post type for the variable.
     * @return int
     */
    public static function resolve_post_id( $payload, $post_type ) {
        $post_id = 0;

        foreach ( array( 'post_id', 'object_id', 'order_id', 'id' ) as $key ) {
            if ( ! empty( $payload[ $key ] ) && is_numeric( $payload[ $key ] ) ) {
                $post_id = absint( $payload[ $key ] );
                break;
            }
        }

        /**
         * Allow integrations to provide the post id used to resolve a custom variable.
         *
         * @since 2.0.0
         * @param int $post_id Resolved post id (0 when none found).
         * @param array<string,mixed> $payload Workflow runtime payload.
         * @param string $post_type Target post type for the variable.
         */
        $post_id = (int) apply_filters( 'Joinotify/Builder/Custom_Variable_Post_Id', $post_id, $payload, $post_type );

        return $post_id > 0 ? $post_id : 0;
    }


    /**
     * Build the list of public post types for the settings UI.
     *
     * @since 2.0.0
     * @return array<int,array{value:string,label:string}>
     */
    public static function get_public_post_types() {
        $post_types = get_post_types( array( 'public' => true ), 'objects' );
        $options = array();

        foreach ( $post_types as $post_type ) {
            $label = isset( $post_type->labels->singular_name ) && '' !== $post_type->labels->singular_name
                ? $post_type->labels->singular_name
                : $post_type->label;

            $options[] = array(
                'value' => $post_type->name,
                'label' => sprintf( '%s (%s)', $label, $post_type->name ),
            );
        }

        return $options;
    }


    /**
     * List example meta keys for a post type, sampled from a real post.
     *
     * @since 2.0.0
     * @param string $post_type Post type slug.
     * @param int $post_id Optional specific post id to sample.
     * @return array{post_id:int,keys:array<int,array{key:string,sample:string}>}
     */
    public static function get_example_meta_keys( $post_type, $post_id = 0 ) {
        $post_type = sanitize_key( (string) $post_type );
        $post_id = absint( $post_id );

        if ( $post_id > 0 ) {
            $post = get_post( $post_id );

            if ( ! $post || ( '' !== $post_type && $post->post_type !== $post_type ) ) {
                $post_id = 0;
            }
        }

        // fall back to the latest published post of the requested type
        if ( $post_id < 1 && '' !== $post_type ) {
            $latest = get_posts( array(
                'post_type'      => $post_type,
                'post_status'    => 'any',
                'posts_per_page' => 1,
                'orderby'        => 'date',
                'order'          => 'DESC',
                'fields'         => 'ids',
            ) );

            if ( ! empty( $latest ) ) {
                $post_id = absint( $latest[0] );
            }
        }

        if ( $post_id < 1 ) {
            return array(
                'post_id' => 0,
                'keys'    => array(),
            );
        }

        $meta = get_post_meta( $post_id );
        $keys = array();

        if ( is_array( $meta ) ) {
            foreach ( $meta as $key => $values ) {
                $value = is_array( $values ) && isset( $values[0] ) ? $values[0] : '';
                $value = maybe_unserialize( $value );

                if ( is_array( $value ) ) {
                    $value = implode( ', ', array_map( 'strval', $value ) );
                }

                $value = is_scalar( $value ) ? (string) $value : '';
                $sample = mb_substr( trim( $value ), 0, 80 );

                $keys[] = array(
                    'key'    => (string) $key,
                    'sample' => $sample,
                );
            }
        }

        return array(
            'post_id' => $post_id,
            'keys'    => $keys,
        );
    }
}
