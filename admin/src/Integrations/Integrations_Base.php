<?php

namespace MeuMouse\Joinotify\Integrations;

use MeuMouse\Joinotify\Admin\Admin;
use MeuMouse\Joinotify\Builder\Triggers;

// Exit if accessed directly.
defined('ABSPATH') || exit;

/**
 * Abstract base class for integrations
 * 
 * @since 1.0.0
 * @version 1.4.7
 * @package MeuMouse\Joinotify\Integrations
 * @author MeuMouse.com
 */
abstract class Integrations_Base {

    /**
     * Register the integration's settings-tab card filter.
     *
     * @since 2.0.0
     * @param int $priority Filter priority controlling the tab ordering.
     * @return void
     */
    protected function register_settings_tab( $priority = 10 ) {
        add_filter( 'Joinotify/Settings/Tabs/Integrations', array( $this, 'add_integration_item' ), $priority, 1 );
    }


    /**
     * Register the builder hooks shared by trigger-based integrations: the
     * trigger list, trigger tab + content, placeholders and action conditions.
     *
     * Each callback is wired only when the integration defines it, so partial
     * integrations stay safe and the helper is reusable by third parties.
     *
     * @since 2.0.0
     * @param int $tab_priority     Priority controlling the trigger tab ordering.
     * @param int $placeholder_args Accepted args for the placeholders filter.
     * @return void
     */
    protected function register_builder_hooks( $tab_priority = 10, $placeholder_args = 1 ) {
        if ( method_exists( $this, 'add_triggers' ) ) {
            add_filter( 'Joinotify/Builder/Get_All_Triggers', array( $this, 'add_triggers' ), 10, 1 );
        }

        if ( method_exists( $this, 'add_triggers_tab' ) ) {
            add_action( 'Joinotify/Builder/Triggers', array( $this, 'add_triggers_tab' ), $tab_priority );
        }

        if ( method_exists( $this, 'add_triggers_content' ) ) {
            add_action( 'Joinotify/Builder/Triggers_Content', array( $this, 'add_triggers_content' ) );
        }

        if ( method_exists( $this, 'add_placeholders' ) ) {
            add_filter( 'Joinotify/Builder/Placeholders_List', array( $this, 'add_placeholders' ), 10, $placeholder_args );
        }

        if ( method_exists( $this, 'add_conditions' ) ) {
            add_filter( 'Joinotify/Validations/Get_Action_Conditions', array( $this, 'add_conditions' ), 10, 1 );
        }
    }


    /**
     * Add tab items on integration settings tab
     *
     * @since 1.0.0
     * @version 1.4.7
     * @return array
     */
    public static function integration_tab_items() {
        return self::normalize_integration_items( apply_filters( 'Joinotify/Settings/Tabs/Integrations', array() ) );
    }


    /**
     * Build a normalized integration card payload.
     *
     * This helper gives external plugins a small, consistent contract for
     * adding integrations to the settings screen without re-creating the
     * internal array shape.
     *
     * @since 1.4.6
     * @param string $slug Integration slug.
     * @param string $title Integration title.
     * @param string $description Integration description.
     * @param string $icon SVG markup for the card icon.
     * @param array  $args Extra integration arguments.
     * @return array<string,mixed>
     */
    public static function build_integration_item( $slug, $title, $description, $icon, $args = array() ) {
        $slug = sanitize_key( $slug );
        $settings = isset( $args['settings'] ) && is_array( $args['settings'] )
            ? $args['settings']
            : ( isset( $args['fields'] ) && is_array( $args['fields'] ) ? $args['fields'] : array() );
        $modal = isset( $args['modal'] ) && is_array( $args['modal'] ) ? $args['modal'] : array();
        $modal = self::resolve_modal_size_hints( $modal, $args );

        $defaults = isset( $args['defaults'] ) && is_array( $args['defaults'] ) ? $args['defaults'] : array();

        $item = wp_parse_args( $args, array(
            'slug'          => $slug,
            'title'         => $title,
            'description'   => $description,
            'icon'          => $icon,
            'setting_key'   => '',
            'action_hook'   => self::get_integration_action_hook( $slug ),
            'is_plugin'     => false,
            'plugin_active' => array(),
            'coming_soon'   => ! empty( $args['coming_soon'] ) || ! empty( $args['comming_soon'] ),
            'comming_soon'  => ! empty( $args['coming_soon'] ) || ! empty( $args['comming_soon'] ),
            'settings'      => $settings,
            'fields'        => $settings,
            'defaults'      => $defaults,
            'modal'         => $modal,
        ) );

        return self::normalize_integration_item( $slug, $item );
    }


    /**
     * Normalize a list of integration items.
     *
     * This keeps legacy integrations working even when they return a raw
     * associative array with only `fields`, `comming_soon`, or callback data.
     *
     * @since 1.4.7
     * @param array<string,array<string,mixed>> $items
     * @return array<string,array<string,mixed>>
     */
    public static function normalize_integration_items( $items ) {
        if ( ! is_array( $items ) ) {
            return array();
        }

        $normalized = array();

        foreach ( $items as $slug => $item ) {
            if ( ! is_array( $item ) ) {
                continue;
            }

            $item_slug = is_string( $slug ) && '' !== $slug ? sanitize_key( $slug ) : '';

            if ( empty( $item_slug ) && ! empty( $item['slug'] ) ) {
                $item_slug = sanitize_key( (string) $item['slug'] );
            }

            if ( empty( $item_slug ) ) {
                continue;
            }

            $normalized[ $item_slug ] = self::normalize_integration_item( $item_slug, $item );
        }

        return $normalized;
    }


    /**
     * Normalize an integration item payload.
     *
     * @since 1.4.7
     * @param string $slug Integration slug.
     * @param array<string,mixed> $item Raw integration item.
     * @return array<string,mixed>
     */
    public static function normalize_integration_item( $slug, $item ) {
        $slug = sanitize_key( $slug );
        $item = is_array( $item ) ? $item : array();

        $settings = isset( $item['settings'] ) && is_array( $item['settings'] )
            ? $item['settings']
            : ( isset( $item['fields'] ) && is_array( $item['fields'] ) ? $item['fields'] : array() );

        $modal = isset( $item['modal'] ) && is_array( $item['modal'] ) ? $item['modal'] : array();
        $modal = self::resolve_modal_size_hints( $modal, $item );

        $defaults = self::normalize_integration_defaults(
            isset( $item['defaults'] ) && is_array( $item['defaults'] ) ? $item['defaults'] : array(),
            $settings
        );
        $coming_soon = ! empty( $item['coming_soon'] ) || ! empty( $item['comming_soon'] );

        $normalized = wp_parse_args( $item, array(
            'slug'          => $slug,
            'title'         => '',
            'description'   => '',
            'icon'          => '',
            'setting_key'   => '',
            'action_hook'   => self::get_integration_action_hook( $slug ),
            'is_plugin'     => false,
            'plugin_active' => array(),
            'coming_soon'   => $coming_soon,
            'comming_soon'  => $coming_soon,
            'settings'      => $settings,
            'fields'        => $settings,
            'defaults'      => $defaults,
            'modal'         => $modal,
        ) );

        $normalized['slug'] = $slug;
        $normalized['settings'] = self::normalize_integration_settings( $normalized['settings'] );
        $normalized['fields'] = $normalized['settings'];
        $normalized['defaults'] = self::normalize_integration_defaults( $normalized['defaults'], $normalized['settings'] );
        $normalized['modal'] = self::normalize_integration_modal( $normalized['modal'] );
        $normalized['coming_soon'] = ! empty( $normalized['coming_soon'] ) || ! empty( $normalized['comming_soon'] );
        $normalized['comming_soon'] = $normalized['coming_soon'];

        return $normalized;
    }


    /**
     * Normalize integration settings definitions.
     *
     * @since 1.4.7
     * @param array<int|string,array<string,mixed>> $settings
     * @return array<int,array<string,mixed>>
     */
    public static function normalize_integration_settings( $settings ) {
        if ( ! is_array( $settings ) ) {
            return array();
        }

        $normalized = array();
        $field_index = 0;

        foreach ( $settings as $key => $setting ) {
            if ( ! is_array( $setting ) ) {
                continue;
            }

            $is_associative = self::is_associative_array( $settings );

            if ( $is_associative && empty( $setting['key'] ) && is_string( $key ) ) {
                $setting['key'] = $key;
            }

            $setting = wp_parse_args( $setting, array(
                'key'              => '',
                'type'             => 'text',
                'label'            => '',
                'description'      => '',
                'component'        => '',
                'component_props'  => array(),
                'default'          => null,
                'placeholder'      => '',
                'options'          => array(),
                'rows'             => 4,
                'show_header'      => true,
                'disabled'         => false,
                'required'         => false,
                'wrapper_class'    => '',
                'group_class'      => '',
                'input_class'      => '',
                'addon_class'      => '',
                'autocomplete'     => 'off',
                'inputmode'        => '',
                'searchable'       => false,
                'search_placeholder' => '',
                'empty_label'      => '',
                'prepend_text'     => '',
                'append_text'      => '',
            ) );

            $setting['key'] = sanitize_key( (string) $setting['key'] );

            if ( empty( $setting['key'] ) ) {
                $setting['key'] = sanitize_key( (string) $key );
            }

            if ( empty( $setting['key'] ) ) {
                $setting['key'] = 'integration_setting_' . $field_index;
            }

            $setting['type'] = self::normalize_integration_setting_type( $setting['type'] );
            $setting['component'] = sanitize_key( (string) $setting['component'] );
            $setting['component_props'] = is_array( $setting['component_props'] ) ? $setting['component_props'] : array();
            $setting['options'] = is_array( $setting['options'] ) ? array_values( $setting['options'] ) : array();

            if ( 'input-group' === $setting['type'] || 'input-group' === $setting['component'] ) {
                if ( empty( $setting['component_props']['items'] ) && ! empty( $setting['items'] ) && is_array( $setting['items'] ) ) {
                    $setting['component_props']['items'] = self::normalize_input_group_items( $setting['items'] );
                }

                if ( empty( $setting['items'] ) && ! empty( $setting['component_props']['items'] ) && is_array( $setting['component_props']['items'] ) ) {
                    $setting['items'] = self::normalize_input_group_items( $setting['component_props']['items'] );
                }
            }

            if ( empty( $setting['label'] ) ) {
                if ( ! empty( $setting['name'] ) ) {
                    $setting['label'] = (string) $setting['name'];
                } elseif ( ! empty( $setting['title'] ) ) {
                    $setting['label'] = (string) $setting['title'];
                }
            }
            $setting['default'] = array_key_exists( 'default', $setting ) ? $setting['default'] : self::infer_integration_default_value( $setting );
            $setting['show_header'] = (bool) $setting['show_header'];
            $setting['disabled'] = (bool) $setting['disabled'];
            $setting['required'] = (bool) $setting['required'];
            $setting['searchable'] = (bool) $setting['searchable'];

            $normalized[] = $setting;
            $field_index++;
        }

        return $normalized;
    }


    /**
     * Normalize the default values declared by an integration item.
     *
     * @since 1.4.7
     * @param array<string,mixed> $defaults Explicit defaults.
     * @param array<int,array<string,mixed>> $settings Settings definitions.
     * @return array<string,mixed>
     */
    public static function normalize_integration_defaults( $defaults, $settings ) {
        $normalized = is_array( $defaults ) ? $defaults : array();

        foreach ( self::normalize_integration_settings( $settings ) as $setting ) {
            $key = $setting['key'] ?? '';

            if ( empty( $key ) || array_key_exists( $key, $normalized ) ) {
                continue;
            }

            $normalized[ $key ] = array_key_exists( 'default', $setting ) && null !== $setting['default']
                ? $setting['default']
                : self::infer_integration_default_value( $setting );
        }

        return $normalized;
    }


    /**
     * Normalize modal metadata.
     *
     * @since 1.4.7
     * @param array<string,mixed> $modal
     * @return array<string,mixed>
     */
    public static function normalize_integration_modal( $modal ) {
        $modal = is_array( $modal ) ? $modal : array();

        if ( empty( $modal['size'] ) ) {
            if ( ! empty( $modal['modal_size'] ) ) {
                $modal['size'] = self::normalize_modal_size( $modal['modal_size'] );
            } elseif ( ! empty( $modal['modal_size_class'] ) ) {
                $modal['size'] = self::normalize_modal_size( $modal['modal_size_class'] );
            } elseif ( ! empty( $modal['size_class'] ) ) {
                $modal['size'] = self::normalize_modal_size( $modal['size_class'] );
            }
        }

        if ( empty( $modal['modal_size_class'] ) ) {
            $modal['modal_size_class'] = self::get_modal_size_class( $modal['size'] ?? 'medium' );
        }

        $modal['blocks'] = self::normalize_integration_modal_blocks(
            isset( $modal['blocks'] ) && is_array( $modal['blocks'] ) ? $modal['blocks'] : array()
        );

        if ( empty( $modal['blocks'] ) ) {
            $content = '';

            if ( ! empty( $modal['content'] ) ) {
                $content = (string) $modal['content'];
            } elseif ( ! empty( $modal['html'] ) ) {
                $content = (string) $modal['html'];
            }

            if ( '' !== trim( $content ) ) {
                $modal['blocks'] = array( self::modal_html_block( $content ) );
            }
        }

        return wp_parse_args( $modal, array(
            'title'              => '',
            'description'        => '',
            'button_label'       => __( 'Configure', 'joinotify' ),
            'render_button'      => true,
            'show_when_disabled' => false,
            'size'               => 'medium',
            'modal_size_class'   => self::get_modal_size_class( 'medium' ),
            'blocks'             => array(),
        ) );
    }


    /**
     * Build a select field definition.
     *
     * @since 1.4.7
     * @param string $key
     * @param string $label
     * @param string $description
     * @param array<int,array{value:string,label:string}> $options
     * @param array<string,mixed> $extra
     * @return array<string,mixed>
     */
    public static function field_select( $key, $label, $description, $options, $extra = array() ) {
        return self::build_field_definition( 'select', $key, $label, $description, array_merge( array(
            'options' => $options,
        ), $extra ) );
    }


    /**
     * Build a toggle field definition.
     *
     * @since 1.4.7
     * @param string $key
     * @param string $label
     * @param string $description
     * @param array<string,mixed> $extra
     * @return array<string,mixed>
     */
    public static function field_toggle( $key, $label, $description, $extra = array() ) {
        return self::build_field_definition( 'toggle', $key, $label, $description, $extra );
    }


    /**
     * Build a text field definition.
     *
     * @since 1.4.7
     * @param string $key
     * @param string $label
     * @param string $description
     * @param array<string,mixed> $extra
     * @return array<string,mixed>
     */
    public static function field_text( $key, $label, $description, $extra = array() ) {
        return self::build_field_definition( 'text', $key, $label, $description, $extra );
    }


    /**
     * Build a textarea field definition.
     *
     * @since 1.4.7
     * @param string $key
     * @param string $label
     * @param string $description
     * @param array<string,mixed> $extra
     * @return array<string,mixed>
     */
    public static function field_textarea( $key, $label, $description, $extra = array() ) {
        return self::build_field_definition( 'textarea', $key, $label, $description, $extra );
    }


    /**
     * Build an input group field definition.
     *
     * The group stores a structured value and can render multiple controls in
     * the same row, such as input + select + action button.
     *
     * @since 1.4.8
     * @param string $key
     * @param string $label
     * @param string $description
     * @param array<int,array<string,mixed>> $items
     * @param array<string,mixed> $extra
     * @return array<string,mixed>
     */
    public static function field_input_group( $key, $label, $description, $items = array(), $extra = array() ) {
        $items = self::normalize_input_group_items( $items );
        $component_props = isset( $extra['component_props'] ) && is_array( $extra['component_props'] ) ? $extra['component_props'] : array();
        $default = array_key_exists( 'default', $extra ) ? $extra['default'] : self::infer_input_group_default_value( $items );

        unset( $extra['component_props'] );

        return self::build_field_definition( 'input-group', $key, $label, $description, array_merge( array(
            'component' => 'input-group',
            'component_props' => array_merge( array(
                'items' => $items,
            ), $component_props ),
            'default' => $default,
        ), $extra ) );
    }


    /**
     * Build a single input-group item.
     *
     * @since 1.4.8
     * @param string $type
     * @param string $label
     * @param array<string,mixed> $extra
     * @return array<string,mixed>
     */
    public static function input_group_item( $type, $label = '', $extra = array() ) {
        return array_merge( array(
            'type' => self::normalize_input_group_item_type( $type ),
            'label' => $label,
        ), is_array( $extra ) ? $extra : array() );
    }


    /**
     * Build an input-group text item.
     *
     * @since 1.4.8
     * @param string $label
     * @param array<string,mixed> $extra
     * @return array<string,mixed>
     */
    public static function input_group_text_item( $label = '', $extra = array() ) {
        return self::input_group_item( 'text', $label, $extra );
    }


    /**
     * Build an input-group select item.
     *
     * @since 1.4.8
     * @param string $label
     * @param array<int,array<string,mixed>> $options
     * @param array<string,mixed> $extra
     * @return array<string,mixed>
     */
    public static function input_group_select_item( $label = '', $options = array(), $extra = array() ) {
        return self::input_group_item( 'select', $label, array_merge( array(
            'options' => is_array( $options ) ? $options : array(),
        ), is_array( $extra ) ? $extra : array() ) );
    }


    /**
     * Build an input-group button item.
     *
     * @since 1.4.8
     * @param string $label
     * @param array<string,mixed> $extra
     * @return array<string,mixed>
     */
    public static function input_group_button_item( $label, $extra = array() ) {
        return self::input_group_item( 'button', $label, $extra );
    }


    /**
     * Build an input-group addon item.
     *
     * @since 1.4.8
     * @param string $label
     * @param array<string,mixed> $extra
     * @return array<string,mixed>
     */
    public static function input_group_addon_item( $label, $extra = array() ) {
        return self::input_group_item( 'addon', $label, $extra );
    }


    /**
     * Build a modal block that renders trusted HTML.
     *
     * @since 1.4.8
     * @param string $html
     * @param array<string,mixed> $extra
     * @return array<string,mixed>
     */
    public static function modal_html_block( $html, $extra = array() ) {
        return array_merge( array(
            'type' => 'html',
            'html' => wp_kses_post( (string) $html ),
        ), is_array( $extra ) ? $extra : array() );
    }


    /**
     * Build a modal block that renders a registered Vue component.
     *
     * @since 1.4.8
     * @param string $component
     * @param array<string,mixed> $props
     * @param array<string,mixed> $extra
     * @return array<string,mixed>
     */
    public static function modal_component_block( $component, $props = array(), $extra = array() ) {
        return array_merge( array(
            'type' => 'component',
            'component' => sanitize_key( (string) $component ),
            'props' => is_array( $props ) ? $props : array(),
        ), is_array( $extra ) ? $extra : array() );
    }


    /**
     * Build a field definition that uses a custom frontend component.
     *
     * The `type` should still describe how the value is stored and sanitized.
     *
     * @since 1.4.7
     * @param string $key
     * @param string $type Storage/type contract used by the backend.
     * @param string $component Vue component name or registry key.
     * @param string $label
     * @param string $description
     * @param array<string,mixed> $extra
     * @return array<string,mixed>
     */
    public static function field_component( $key, $type, $component, $label, $description = '', $extra = array() ) {
        return self::build_field_definition( $type, $key, $label, $description, array_merge( array(
            'component' => $component,
        ), $extra ) );
    }


    /**
     * Build a field definition array.
     *
     * @since 1.4.7
     * @param string $type
     * @param string $key
     * @param string $label
     * @param string $description
     * @param array<string,mixed> $extra
     * @return array<string,mixed>
     */
    protected static function build_field_definition( $type, $key, $label, $description, $extra = array() ) {
        $definition = array_merge( array(
            'type'        => self::normalize_integration_setting_type( $type ),
            'key'         => sanitize_key( $key ),
            'label'       => $label,
            'description' => $description,
        ), is_array( $extra ) ? $extra : array() );

        if ( ! isset( $definition['default'] ) ) {
            $definition['default'] = self::infer_integration_default_value( $definition );
        }

        return $definition;
    }


    /**
     * Fill a modal config's `size` and `modal_size_class` from a source array,
     * honoring the `modal_size`, `modal_size_class` and `size_class` hints.
     *
     * @since 2.0.0
     * @param array $modal  Modal config being built.
     * @param array $source Array to read the size hints from.
     * @return array Modal config with size/modal_size_class filled in.
     */
    protected static function resolve_modal_size_hints( array $modal, array $source ) {
        if ( empty( $modal['size'] ) ) {
            if ( ! empty( $source['modal_size'] ) ) {
                $modal['size'] = self::normalize_modal_size( $source['modal_size'] );
            } elseif ( ! empty( $source['modal_size_class'] ) ) {
                $modal['size'] = self::normalize_modal_size( $source['modal_size_class'] );
            } elseif ( ! empty( $source['size_class'] ) ) {
                $modal['size'] = self::normalize_modal_size( $source['size_class'] );
            }
        }

        if ( empty( $modal['modal_size_class'] ) ) {
            if ( ! empty( $source['modal_size_class'] ) ) {
                $modal['modal_size_class'] = $source['modal_size_class'];
            } elseif ( ! empty( $source['size_class'] ) ) {
                $modal['modal_size_class'] = $source['size_class'];
            }
        }

        return $modal;
    }


    /**
     * Normalize a modal size contract to one of the supported size tokens.
     *
     * @since 1.4.7
     * @param string $size Raw size value.
     * @return string
     */
    protected static function normalize_modal_size( $size ) {
        $size = sanitize_key( str_replace( '_', '-', (string) $size ) );

        $map = array(
            'sm'          => 'small',
            'small'       => 'small',
            'md'          => 'medium',
            'medium'      => 'medium',
            'lg'          => 'large',
            'large'       => 'large',
            'xl'          => 'extra-large',
            'extra-large' => 'extra-large',
            'extra_large' => 'extra-large',
            'popup-lg'    => 'medium',
        );

        if ( isset( $map[ $size ] ) ) {
            return $map[ $size ];
        }

        return in_array( $size, array( 'small', 'medium', 'large', 'extra-large' ), true ) ? $size : 'medium';
    }


    /**
     * Return the CSS width class for a modal size token.
     *
     * @since 1.4.7
     * @param string $size Modal size token.
     * @return string
     */
    protected static function get_modal_size_class( $size ) {
        switch ( self::normalize_modal_size( $size ) ) {
            case 'small':
                return 'max-w-[640px]';
            case 'large':
                return 'max-w-[1200px]';
            case 'extra-large':
                return 'max-w-[1400px]';
            case 'medium':
            default:
                return 'max-w-[900px]';
        }
    }


    /**
     * Infer the default value for a field definition.
     *
     * @since 1.4.7
     * @param array<string,mixed> $field
     * @return mixed
     */
    public static function infer_integration_default_value( $field ) {
        $type = self::normalize_integration_setting_type( $field['type'] ?? 'text' );

        if ( 'toggle' === $type ) {
            return 'no';
        }

        if ( 'color' === $type ) {
            return '#4f46e5';
        }

        if ( 'color-scale' === $type ) {
            return array(
                'baseColor' => '#4f46e5',
                'palette'   => array(),
            );
        }

        if ( 'input-group' === $type ) {
            return array();
        }

        return '';
    }


    /**
     * Return the action hook used by the native integration modal.
     *
     * External plugins can hook into this action and render custom controls
     * inside the Joinotify modal container.
     *
     * @since 1.4.6
     * @param string $slug Integration slug.
     * @return string
     */
    public static function get_integration_action_hook( $slug ) {
        return sprintf( 'Joinotify/Settings/Tabs/Integrations/%s', sanitize_key( $slug ) );
    }


    /**
     * Render the native integration settings modal.
     *
     * The body can be provided either through a callback or by attaching a
     * listener to the integration action hook returned by
     * `get_integration_action_hook()`.
     *
     * @since 1.4.6
     * @param string   $slug Integration slug.
     * @param array    $args Modal arguments.
     * @param callable $content_callback Optional callback that prints the modal body.
     * @return void
     */
    public static function render_integration_settings_modal( $slug, $args = array(), $content_callback = null ) {
        $defaults = array(
            'title'                 => __( 'Integration settings', 'joinotify' ),
            'description'           => '',
            'button_label'          => __( 'Configure', 'joinotify' ),
            'setting_key'           => '',
            'action_hook'           => self::get_integration_action_hook( $slug ),
            'modal_size_class'      => 'popup-lg',
            'container_class'       => 'joinotify-popup-container',
            'content_class'         => 'joinotify-popup-body my-3',
            'title_class'           => 'joinotify-popup-title',
            'header_class'          => 'joinotify-popup-header',
            'button_class'          => 'btn btn-outline-primary mb-5',
            'close_button_class'    => 'btn-close fs-lg',
            'show_when_disabled'    => false,
            'render_button'         => true,
        );

        $args = wp_parse_args( $args, $defaults );

        if ( ! empty( $args['setting_key'] ) && ! self::is_setting_enabled( $args['setting_key'] ) && ! $args['show_when_disabled'] ) {
            return;
        }

        $slug = sanitize_key( $slug );
        $trigger_id = $slug . '_settings_trigger';
        $container_id = $slug . '_settings_container';
        $close_id = $slug . '_settings_close';

        if ( $args['render_button'] ) : ?>
            <button id="<?php echo esc_attr( $trigger_id ); ?>" class="<?php echo esc_attr( $args['button_class'] ); ?>">
                <?php echo esc_html( $args['button_label'] ); ?>
            </button>
        <?php endif; ?>

        <div id="<?php echo esc_attr( $container_id ); ?>" class="<?php echo esc_attr( $args['container_class'] ); ?>">
            <div class="joinotify-popup-content <?php echo esc_attr( $args['modal_size_class'] ); ?>">
                <div class="<?php echo esc_attr( $args['header_class'] ); ?>">
                    <h5 class="<?php echo esc_attr( $args['title_class'] ); ?>">
                        <?php echo esc_html( $args['title'] ); ?>
                    </h5>
                    <button id="<?php echo esc_attr( $close_id ); ?>" class="<?php echo esc_attr( $args['close_button_class'] ); ?>" aria-label="<?php esc_attr_e( 'Close', 'joinotify' ); ?>"></button>
                </div>

                <?php if ( ! empty( $args['description'] ) ) : ?>
                    <div class="px-4 pt-3">
                        <p class="joinotify-description mb-0"><?php echo esc_html( $args['description'] ); ?></p>
                    </div>
                <?php endif; ?>

                <div class="<?php echo esc_attr( $args['content_class'] ); ?>">
                    <?php
                    if ( is_callable( $content_callback ) ) {
                        call_user_func( $content_callback, $args );
                    } else {
                        do_action( $args['action_hook'] );
                    }
                    ?>
                </div>
            </div>
        </div>
        <?php
    }


    /**
     * Render a trigger tab on builder sidebar
     * 
     * @since 1.1.0
     * @param string $slug | Integration slug (eg: 'wordpress')
     * @param string $name | Integration name (eg: esc_html__( 'WordPress', 'joinotify' ) )
     * @param string $icon_svg | SVG icon code
     * @return void
     */
    protected function render_integration_trigger_tab( $slug, $name, $icon ) {
        if ( Admin::get_setting("enable_{$slug}_integration") === 'yes' ) : ?>
            <a href="#<?php echo esc_attr( $slug ); ?>" class="nav-tab">
                <?php echo $icon; // SVG icon ?>
                <?php echo $name; ?>
            </a>
        <?php endif;
    }


    /**
     * Render the trigger content
     * 
     * @since 1.1.0
     * @version 1.4.7
     * @param string $slug | Slug da integração (eg: 'wordpress')
     * @return void
     */
    protected function render_integration_trigger_content( $slug ) {
        if ( Admin::get_setting("enable_{$slug}_integration") === 'yes' ) : ?>
            <div id="<?php echo esc_attr( $slug ); ?>" class="nav-content triggers-group">
                <?php foreach ( Triggers::get_triggers_by_context( $slug ) as $trigger ) :
                    // disable trigger if plugin is not active or not installed
                    $disable_select_trigger = isset( $trigger['require_plugins'] ) && array_key_exists( $trigger['plugins'][0]['slug'], get_plugins() ) && ! is_plugin_active( $trigger['plugins'][0]['slug'] )
                    || isset( $trigger['require_plugins'] ) && ! array_key_exists( $trigger['plugins'][0]['slug'], get_plugins() ); ?>

                    <div class="trigger-item <?php echo esc_attr( isset( $trigger['class'] ) ? $trigger['class'] : '' ); ?> <?php echo esc_attr( $disable_select_trigger  ? 'require-plugins' : '' ); ?>" data-context="<?php echo esc_attr( $slug ); ?>" data-trigger="<?php echo esc_attr( $trigger['data_trigger'] ); ?>">
                        <h4 class="title"><?php echo esc_html( $trigger['title'] ); ?></h4>
                        <span class="description"><?php echo esc_html( $trigger['description'] ); ?></span>

                        <?php if ( isset( $trigger['class'] ) && $trigger['class'] === 'locked' ) : ?>
                            <span class="fs-sm mt-3"><?php esc_html_e( 'This feature will be released soon.', 'joinotify' ); ?></span>
                        <?php endif; ?>

                        <!-- Install trigger dependencies -->
                        <?php if ( isset( $trigger['require_plugins'] ) && $trigger['require_plugins'] === true ) : ?>
                            <?php foreach ( $trigger['plugins'] as $plugin => $item ) : ?>
                                <?php if ( array_key_exists( $item['slug'], get_plugins() ) && ! is_plugin_active( $item['slug'] ) ) : ?>
                                    <span class="fs-sm my-3"><?php esc_html_e( 'This trigger depends on a plugin', 'joinotify' ); ?></span>

                                    <button class="btn btn-sm btn-outline-secondary activate-plugin mb-2" data-plugin-slug="<?php echo esc_attr( $item['slug'] ) ?>" data-bs-toggle="tooltip" data-bs-placement="bottom" data-bs-custom-class="joinotify-tooltip" data-bs-title="<?php echo esc_attr( $item['name'] ) ?>"><?php esc_html_e( 'Activate plugin', 'joinotify' ) ?></button>
                                <?php elseif ( ! array_key_exists( $item['slug'], get_plugins() ) ) : ?>
                                    <span class="fs-sm my-3"><?php esc_html_e( 'This trigger depends on a plugin', 'joinotify' ); ?></span>

                                    <button class="btn btn-sm btn-outline-secondary install-required-plugin mb-2" data-download-url="<?php echo esc_attr( $item['download_url'] ) ?>" data-required-plugin="<?php echo esc_attr( $item['slug'] ) ?>" data-bs-toggle="tooltip" data-bs-placement="bottom" data-bs-custom-class="joinotify-tooltip" data-bs-title="<?php echo esc_attr( $item['name'] ) ?>"><?php esc_html_e( 'Install plugin', 'joinotify' ) ?></button>
                                <?php endif; ?>
                            <?php endforeach; ?>
                        <?php endif; ?>
                    </div>
                <?php endforeach; ?>
            </div>
        <?php endif;
    }


    /**
     * Check if an integration setting is enabled.
     *
     * @since 1.4.6
     * @param string $setting_key Setting option key.
     * @return bool
     */
    protected static function is_setting_enabled( $setting_key ) {
        if ( empty( $setting_key ) ) {
            return false;
        }

        return Admin::get_setting( $setting_key ) === 'yes';
    }


    /**
     * Normalize a field type to the supported contract.
     *
     * @since 1.4.7
     * @param string $type Raw field type.
     * @return string
     */
    protected static function normalize_integration_setting_type( $type ) {
        $type = sanitize_key( (string) $type );

        if ( 'switch' === $type ) {
            return 'toggle';
        }

        if ( 'custom' === $type ) {
            return 'text';
        }

        return in_array( $type, array( 'toggle', 'text', 'textarea', 'select', 'phone', 'color', 'color-scale', 'input-group' ), true ) ? $type : 'text';
    }


    /**
     * Check if the array uses non-sequential keys.
     *
     * @since 1.4.7
     * @param array<mixed> $value
     * @return bool
     */
    protected static function is_associative_array( $value ) {
        if ( ! is_array( $value ) || empty( $value ) ) {
            return false;
        }

        return array_keys( $value ) !== range( 0, count( $value ) - 1 );
    }


    /**
     * Normalize modal blocks for the Vue settings modal.
     *
     * @since 1.4.8
     * @param array<int,mixed> $blocks
     * @return array<int,array<string,mixed>>
     */
    protected static function normalize_integration_modal_blocks( $blocks ) {
        if ( ! is_array( $blocks ) ) {
            return array();
        }

        $normalized = array();

        foreach ( $blocks as $index => $block ) {
            if ( is_string( $block ) ) {
                $normalized[] = self::modal_html_block( $block );
                continue;
            }

            if ( ! is_array( $block ) ) {
                continue;
            }

            $type = sanitize_key( (string) ( $block['type'] ?? $block['kind'] ?? 'html' ) );

            if ( in_array( $type, array( 'html', 'markup', 'content' ), true ) ) {
                $normalized[] = self::modal_html_block(
                    isset( $block['html'] ) ? $block['html'] : ( $block['content'] ?? '' ),
                    array_diff_key( $block, array(
                        'type' => true,
                        'kind' => true,
                        'html' => true,
                        'content' => true,
                    ) )
                );
                continue;
            }

            if ( in_array( $type, array( 'component', 'vue-component' ), true ) ) {
                $component = sanitize_key( (string) ( $block['component'] ?? $block['name'] ?? '' ) );

                if ( empty( $component ) ) {
                    continue;
                }

                $normalized[] = array_merge( array(
                    'type' => 'component',
                    'component' => $component,
                    'props' => array(),
                ), $block );
                continue;
            }

            $normalized[] = array_merge( array(
                'type' => $type ?: 'html',
                'key' => 'modal-block-' . $index,
            ), $block );
        }

        return array_values( $normalized );
    }


    /**
     * Normalize a declarative input group item.
     *
     * @since 1.4.8
     * @param array<string,mixed> $item
     * @param int $index
     * @return array<string,mixed>
     */
    protected static function normalize_input_group_item( $item, $index ) {
        $item = is_array( $item ) ? $item : array();
        $type = self::normalize_input_group_item_type( $item['type'] ?? $item['kind'] ?? 'text' );

        $item = wp_parse_args( $item, array(
            'type' => $type,
            'key' => '',
            'name' => '',
            'label' => '',
            'text' => '',
            'value' => '',
            'default' => null,
            'placeholder' => '',
            'disabled' => false,
            'options' => array(),
            'class' => '',
            'itemClass' => '',
            'buttonClass' => '',
            'inputClass' => '',
            'width' => '',
            'autocomplete' => 'off',
            'inputmode' => '',
            'action' => '',
            'target' => '',
            'source' => '',
        ) );

        $item['type'] = $type;
        $item['key'] = sanitize_key( (string) ( $item['key'] ?: $item['name'] ) );
        $item['name'] = sanitize_key( (string) $item['name'] );
        $item['disabled'] = (bool) $item['disabled'];
        $item['options'] = is_array( $item['options'] ) ? array_values( $item['options'] ) : array();
        $item['class'] = is_string( $item['class'] ) ? $item['class'] : '';
        $item['itemClass'] = is_string( $item['itemClass'] ) ? $item['itemClass'] : '';
        $item['buttonClass'] = is_string( $item['buttonClass'] ) ? $item['buttonClass'] : '';
        $item['inputClass'] = is_string( $item['inputClass'] ) ? $item['inputClass'] : '';
        $item['width'] = is_string( $item['width'] ) ? $item['width'] : '';
        $item['autocomplete'] = is_string( $item['autocomplete'] ) ? $item['autocomplete'] : 'off';
        $item['inputmode'] = is_string( $item['inputmode'] ) ? $item['inputmode'] : '';
        $item['action'] = is_string( $item['action'] ) ? sanitize_key( $item['action'] ) : '';
        $item['target'] = is_string( $item['target'] ) ? sanitize_key( $item['target'] ) : '';
        $item['source'] = is_string( $item['source'] ) ? sanitize_key( $item['source'] ) : '';

        if ( empty( $item['key'] ) && ! in_array( $type, array( 'button', 'addon' ), true ) ) {
            $item['key'] = 'input_group_item_' . $index;
        }

        if ( empty( $item['label'] ) && ! empty( $item['text'] ) ) {
            $item['label'] = (string) $item['text'];
        }

        if ( 'select' === $type && null === $item['default'] && ! empty( $item['options'] ) && is_array( $item['options'] ) ) {
            $first_option = $item['options'][0];
            if ( is_array( $first_option ) && array_key_exists( 'value', $first_option ) ) {
                $item['default'] = $first_option['value'];
            }
        }

        if ( 'button' === $type ) {
            $item['default'] = null;
        } elseif ( null === $item['default'] && array_key_exists( 'value', $item ) && '' !== $item['value'] ) {
            $item['default'] = $item['value'];
        }

        return $item;
    }


    /**
     * Normalize the input-group item collection.
     *
     * @since 1.4.8
     * @param array<int,array<string,mixed>|string> $items
     * @return array<int,array<string,mixed>>
     */
    protected static function normalize_input_group_items( $items ) {
        if ( ! is_array( $items ) ) {
            return array();
        }

        $normalized = array();

        foreach ( $items as $index => $item ) {
            if ( is_string( $item ) ) {
                $normalized[] = self::input_group_addon_item( $item );
                continue;
            }

            if ( ! is_array( $item ) ) {
                continue;
            }

            $normalized[] = self::normalize_input_group_item( $item, (int) $index );
        }

        return array_values( $normalized );
    }


    /**
     * Infer the default value for an input-group field.
     *
     * @since 1.4.8
     * @param array<int,array<string,mixed>> $items
     * @return array<string,mixed>
     */
    protected static function infer_input_group_default_value( $items ) {
        $default = array();

        foreach ( self::normalize_input_group_items( $items ) as $item ) {
            $type = $item['type'] ?? 'text';
            $key = $item['key'] ?? '';

            if ( empty( $key ) || in_array( $type, array( 'button', 'addon' ), true ) ) {
                continue;
            }

            if ( array_key_exists( 'default', $item ) && null !== $item['default'] ) {
                $default[ $key ] = $item['default'];
                continue;
            }

            if ( array_key_exists( 'value', $item ) && '' !== $item['value'] ) {
                $default[ $key ] = $item['value'];
                continue;
            }

            if ( 'select' === $type && ! empty( $item['options'] ) && is_array( $item['options'] ) ) {
                $first_option = $item['options'][0];

                if ( is_array( $first_option ) && array_key_exists( 'value', $first_option ) ) {
                    $default[ $key ] = $first_option['value'];
                }
            }
        }

        return $default;
    }


    /**
     * Normalize an input-group item type.
     *
     * @since 1.4.8
     * @param string $type
     * @return string
     */
    protected static function normalize_input_group_item_type( $type ) {
        $type = sanitize_key( (string) $type );

        if ( in_array( $type, array( 'text', 'input', 'select', 'button', 'addon' ), true ) ) {
            return 'input' === $type ? 'text' : $type;
        }

        return 'text';
    }
}
