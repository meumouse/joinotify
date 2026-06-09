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
                'message' => __( 'Provide a valid variable name.', 'joinotify' ),
            );
        }

        if ( '' === $variable['post_type'] || '' === $variable['meta_key'] ) {
            return array(
                'success' => false,
                'message' => __( 'Select an entity and inform the meta key.', 'joinotify' ),
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
            'message'  => __( 'Variable saved successfully.', 'joinotify' ),
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
            foreach ( $groups as $group_key => $group_placeholders ) {
                // the custom group is the variables stored here; it is already
                // checked above with $exclude_id, so skip it to avoid a
                // variable colliding with itself while being edited.
                if ( 'custom' === $group_key ) {
                    continue;
                }

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

                        return self::read_meta( $post_id, $meta_key, $post_type );
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
     * Check whether a post type is a WooCommerce order type.
     *
     * Order types (shop_order, shop_subscription, ...) are not standard posts:
     * under HPOS their meta lives in custom tables, so they must be read through
     * the WooCommerce CRUD API instead of get_post_meta().
     *
     * @since 2.0.0
     * @param string $post_type Post type slug.
     * @return bool
     */
    protected static function is_order_type( $post_type ) {
        return function_exists( 'wc_get_order_types' ) && in_array( $post_type, (array) wc_get_order_types(), true );
    }


    /**
     * Read a single meta value for a given object, supporting WooCommerce orders.
     *
     * @since 2.0.0
     * @param int $post_id Object id (post or order).
     * @param string $meta_key Meta key.
     * @param string $post_type Target post type.
     * @return string
     */
    protected static function read_meta( $post_id, $meta_key, $post_type ) {
        $value = '';

        if ( self::is_order_type( $post_type ) && function_exists( 'wc_get_order' ) ) {
            $order = wc_get_order( $post_id );

            if ( $order ) {
                // standard fields are exposed via getters (works on HPOS too)
                $getter = 'get_' . ltrim( (string) $meta_key, '_' );

                if ( is_callable( array( $order, $getter ) ) ) {
                    $value = $order->{$getter}();
                } else {
                    $value = $order->get_meta( $meta_key );
                }
            }
        } else {
            $value = get_post_meta( $post_id, $meta_key, true );
        }

        /**
         * Filter the resolved value of a custom variable before output.
         *
         * @since 2.0.0
         * @param mixed $value Resolved meta value.
         * @param int $post_id Object id.
         * @param string $meta_key Meta key.
         * @param string $post_type Target post type.
         */
        $value = apply_filters( 'Joinotify/Builder/Custom_Variable_Meta_Value', $value, $post_id, $meta_key, $post_type );

        if ( is_array( $value ) ) {
            return implode( ', ', array_map( 'strval', $value ) );
        }

        return is_scalar( $value ) ? (string) $value : '';
    }


    /**
     * Build the list of selectable post types (entities) for the settings UI.
     *
     * Includes public and admin-visible post types, plus WooCommerce order
     * types which are registered as non-public but are valid meta entities.
     *
     * @since 2.0.0
     * @return array<int,array{value:string,label:string}>
     */
    public static function get_public_post_types() {
        $objects = get_post_types( array(), 'objects' );
        $exclude = array(
            'attachment', 'revision', 'nav_menu_item', 'custom_css', 'customize_changeset',
            'oembed_cache', 'user_request', 'wp_block', 'wp_template', 'wp_template_part',
            'wp_global_styles', 'wp_navigation', 'wp_font_family', 'wp_font_face',
        );
        $allowed = array();

        foreach ( $objects as $post_type ) {
            if ( in_array( $post_type->name, $exclude, true ) ) {
                continue;
            }

            // public content types or anything manageable from the admin UI
            if ( $post_type->public || $post_type->show_ui ) {
                $allowed[ $post_type->name ] = $post_type;
            }
        }

        // ensure WooCommerce order types are always available, even when non-public
        if ( function_exists( 'wc_get_order_types' ) ) {
            foreach ( (array) wc_get_order_types() as $order_type ) {
                if ( isset( $objects[ $order_type ] ) && ! isset( $allowed[ $order_type ] ) && ! in_array( $order_type, $exclude, true ) ) {
                    $allowed[ $order_type ] = $objects[ $order_type ];
                }
            }
        }

        $options = array();

        foreach ( $allowed as $post_type ) {
            $label = isset( $post_type->labels->singular_name ) && '' !== $post_type->labels->singular_name
                ? $post_type->labels->singular_name
                : $post_type->label;

            $options[] = array(
                'value' => $post_type->name,
                'label' => sprintf( '%s (%s)', $label, $post_type->name ),
            );
        }

        /**
         * Filter the selectable entities for custom builder variables.
         *
         * @since 2.0.0
         * @param array<int,array{value:string,label:string}> $options Entity options.
         */
        return apply_filters( 'Joinotify/Builder/Custom_Variable_Entities', $options );
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
        $is_order = self::is_order_type( $post_type );

        // WooCommerce order types: read through the CRUD API (HPOS safe)
        if ( $is_order ) {
            return self::get_example_order_meta_keys( $post_type, $post_id );
        }

        if ( $post_id > 0 ) {
            $post = get_post( $post_id );

            if ( ! $post || ( '' !== $post_type && $post->post_type !== $post_type ) ) {
                $post_id = 0;
            }
        }

        // fall back to the latest post of the requested type
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

                $keys[] = array(
                    'key'    => (string) $key,
                    'sample' => self::format_sample( $value ),
                );
            }
        }

        return array(
            'post_id' => $post_id,
            'keys'    => $keys,
        );
    }


    /**
     * List example meta keys for a WooCommerce order type using the CRUD API.
     *
     * @since 2.0.0
     * @param string $post_type Order post type.
     * @param int $post_id Optional specific order id to sample.
     * @return array{post_id:int,keys:array<int,array{key:string,sample:string}>}
     */
    protected static function get_example_order_meta_keys( $post_type, $post_id = 0 ) {
        if ( ! function_exists( 'wc_get_order' ) ) {
            return array( 'post_id' => 0, 'keys' => array() );
        }

        // pick a sample order id when none is provided
        if ( $post_id < 1 && function_exists( 'wc_get_orders' ) ) {
            $orders = wc_get_orders( array(
                'type'    => $post_type,
                'limit'   => 1,
                'orderby' => 'date',
                'order'   => 'DESC',
                'return'  => 'ids',
            ) );

            if ( ! empty( $orders ) ) {
                $post_id = absint( is_array( $orders ) ? reset( $orders ) : $orders );
            }
        }

        $order = $post_id > 0 ? wc_get_order( $post_id ) : false;

        if ( ! $order ) {
            return array( 'post_id' => 0, 'keys' => array() );
        }

        $keys = array();

        // common standard fields exposed via getters (resolve at runtime too)
        $standard = array(
            'billing_first_name', 'billing_last_name', 'billing_company', 'billing_email',
            'billing_phone', 'billing_address_1', 'billing_city', 'billing_state',
            'billing_postcode', 'billing_country', 'shipping_first_name', 'shipping_last_name',
            'order_number', 'status', 'total', 'currency', 'payment_method_title',
        );

        foreach ( $standard as $field ) {
            $getter = 'get_' . $field;

            if ( is_callable( array( $order, $getter ) ) ) {
                $keys[] = array(
                    'key'    => $field,
                    'sample' => self::format_sample( $order->{$getter}() ),
                );
            }
        }

        // custom meta stored on the order
        if ( is_callable( array( $order, 'get_meta_data' ) ) ) {
            foreach ( $order->get_meta_data() as $meta ) {
                $data = is_callable( array( $meta, 'get_data' ) ) ? $meta->get_data() : array();
                $key = isset( $data['key'] ) ? (string) $data['key'] : '';

                if ( '' === $key ) {
                    continue;
                }

                $keys[] = array(
                    'key'    => $key,
                    'sample' => self::format_sample( isset( $data['value'] ) ? $data['value'] : '' ),
                );
            }
        }

        return array(
            'post_id' => $post_id,
            'keys'    => $keys,
        );
    }


    /**
     * Format a meta value into a short human readable sample string.
     *
     * @since 2.0.0
     * @param mixed $value Raw value.
     * @return string
     */
    protected static function format_sample( $value ) {
        if ( is_array( $value ) ) {
            $value = implode( ', ', array_map( 'strval', $value ) );
        }

        $value = is_scalar( $value ) ? (string) $value : '';

        return mb_substr( trim( $value ), 0, 80 );
    }
}
