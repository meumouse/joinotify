<?php

namespace MeuMouse\Joinotify\Admin\Settings;

use MeuMouse\Joinotify\Admin\Admin;
use MeuMouse\Joinotify\Admin\Default_Options;
use MeuMouse\Joinotify\Api\License;
use MeuMouse\Joinotify\Core\Helpers;
use MeuMouse\Joinotify\Integrations\Integrations_Base;
use MeuMouse\Joinotify\Validations\Country_Codes;

defined('ABSPATH') || exit;

/**
 * Build the settings schema and bootstrap payload for the Vue admin app.
 * 
 * @since 1.4.7
 * @package MeuMouse\Joinotify\Admin\Settings
 * @author MeuMouse.com
 */
class Registry {

    /**
     * Return the current settings merged with defaults.
     *
     * @since 1.4.7
     * @return array<string,mixed>
     */
    public static function get_settings() {
        return wp_parse_args( get_option( 'joinotify_settings', array() ), Default_Options::set_default_options() );
    }


    /**
     * Build the settings schema consumed by the Vue app.
     *
     * @since 1.4.7
     * @return array<string,mixed>
     */
    public static function get_schema() {
        $schema = array(
            array(
                'id' => 'general',
                'title' => esc_html__( 'General', 'joinotify' ),
                'description' => esc_html__( "Plugin's basic preferences, sending proxy, and WhatsApp notifications.", 'joinotify' ),
                'layout' => 'cards',
                'cards' => array(
                    array(
                        'id' => 'general-localization',
                        'title' => esc_html__( 'Location and phone numbers', 'joinotify' ),
                        'description' => esc_html__( 'Defines the default country used to format numbers and populate support options.', 'joinotify' ),
                        'fields' => array(
                            self::field_select(
                                'joinotify_default_country_code',
                                esc_html__( 'Default country code', 'joinotify' ),
                                esc_html__( 'Choose the fallback country when the phone number does not include a country code.', 'joinotify' ),
                                self::build_country_code_options()
                            ),
                            self::field_toggle(
                                'enable_send_disconnect_notifications',
                                esc_html__( 'Notify when WhatsApp disconnects', 'joinotify' ),
                                esc_html__( 'Sends a notification to the sender when the connection is not established.', 'joinotify' )
                            ),
                            self::field_text(
                                'test_number_phone',
                                esc_html__( 'Test phone', 'joinotify' ),
                                esc_html__( 'Number used as the default destination in test mailings. Please only include numbers with country code + area code.', 'joinotify' ),
                                array(
                                    'placeholder' => '5541987111527',
                                )
                            ),
                        ),
                    ),
                    array(
                        'id' => 'general-proxy',
                        'title' => esc_html__( 'Proxy API', 'joinotify' ),
                        'description' => esc_html__( 'Activate and configure the endpoints used to process external API requests.', 'joinotify' ),
                        'fields' => array(
                            self::field_toggle(
                                'enable_proxy_api',
                                esc_html__( 'Enable Proxy API', 'joinotify' ),
                                esc_html__( 'This site exposes endpoints to process Joinotify requests.', 'joinotify' )
                            ),
                            self::field_text(
                                'send_text_proxy_api_route',
                                esc_html__( 'Text route', 'joinotify' ),
                                esc_html__( 'Route path used to send text messages.', 'joinotify' ),
                                array(
                                    'placeholder' => 'send-message/text',
                                )
                            ),
                            self::field_text(
                                'send_media_proxy_api_route',
                                esc_html__( 'Media route', 'joinotify' ),
                                esc_html__( 'Path of the route used to send messages with media.', 'joinotify' ),
                                array(
                                    'placeholder' => 'send-message/media',
                                )
                            ),
                            self::field_text(
                                'proxy_api_key',
                                esc_html__( 'API key', 'joinotify' ),
                                esc_html__( 'Key used to authenticate Proxy API calls.', 'joinotify' ),
                                array(
                                    'placeholder' => '',
                                )
                            ),
                        ),
                    ),
                ),
            ),
            array(
                'id' => 'phones',
                'title' => esc_html__( 'Phones', 'joinotify' ),
                'description' => esc_html__( 'Manage senders, validate new connections, and send test messages.', 'joinotify' ),
                'layout' => 'custom',
                'cards' => array(
                    array(
                        'id' => 'phones-senders',
                        'title' => esc_html__( 'Registered senders', 'joinotify' ),
                        'description' => esc_html__( 'Phones already validated and available for use in workflows.', 'joinotify' ),
                        'component' => 'phone-sender-list',
                    ),
                    array(
                        'id' => 'phones-actions',
                        'title' => esc_html__( 'Quick Tools', 'joinotify' ),
                        'description' => esc_html__( 'Actions to add a new sender and send a test message.', 'joinotify' ),
                        'component' => 'phone-actions',
                    ),
                ),
            ),
            array(
                'id' => 'integrations',
                'title' => esc_html__( 'Integrations', 'joinotify' ),
                'description' => esc_html__( 'Enable integrations, manage dependencies, and adjust advanced options for each service.', 'joinotify' ),
                'layout' => 'cards',
                'cards' => self::get_integration_cards(),
            ),
            array(
                'id' => 'about',
                'title' => esc_html__( 'About', 'joinotify' ),
                'description' => esc_html__( 'Maintenance, logs, updates, and environment details.', 'joinotify' ),
                'layout' => 'custom',
                'cards' => array(
                    array(
                        'id' => 'about-maintenance',
                        'title' => esc_html__( 'Maintenance and preference', 'joinotify' ),
                        'description' => esc_html__( 'Operational plugin settings and development flags.', 'joinotify' ),
                        'fields' => array(
                            self::field_toggle(
                                'enable_debug_mode',
                                esc_html__( 'Debug mode', 'joinotify' ),
                                esc_html__( 'Enable to log additional error and process details.', 'joinotify' )
                            ),
                            self::field_toggle(
                                'enable_auto_updates',
                                esc_html__( 'Automatic updates', 'joinotify' ),
                                esc_html__( 'Allows the plugin to update automatically whenever possible.', 'joinotify' )
                            ),
                            self::field_toggle(
                                'enable_update_notice',
                                esc_html__( 'Update notices', 'joinotify' ),
                                esc_html__( 'Displays notifications when a new version is available.', 'joinotify' )
                            ),
                            self::field_toggle(
                                'enable_developer_integration',
                                esc_html__( 'Developer integration', 'joinotify' ),
                                esc_html__( 'Maintains support integrations and advanced contracts available.', 'joinotify' )
                            ),
                            self::field_toggle(
                                'enable_message_history',
                                esc_html__( 'Message history', 'joinotify' ),
                                esc_html__( 'Record every dispatched WhatsApp message so it can be audited from the History screen.', 'joinotify' )
                            ),
                            self::field_select(
                                'message_history_retention_days',
                                esc_html__( 'History retention', 'joinotify' ),
                                esc_html__( 'Automatically delete history records older than the selected period.', 'joinotify' ),
                                array(
                                    array( 'value' => '0', 'label' => esc_html__( 'Keep forever', 'joinotify' ) ),
                                    array( 'value' => '30', 'label' => esc_html__( '30 days', 'joinotify' ) ),
                                    array( 'value' => '60', 'label' => esc_html__( '60 days', 'joinotify' ) ),
                                    array( 'value' => '90', 'label' => esc_html__( '90 days', 'joinotify' ) ),
                                    array( 'value' => '180', 'label' => esc_html__( '180 days', 'joinotify' ) ),
                                    array( 'value' => '365', 'label' => esc_html__( '365 days', 'joinotify' ) ),
                                )
                            ),
                        ),
                    ),
                    array(
                        'id' => 'about-system',
                        'title' => esc_html__( 'System status', 'joinotify' ),
                        'description' => esc_html__( 'A quick overview of the WordPress environment, PHP, and critical extensions.', 'joinotify' ),
                        'component' => 'system-status',
                    ),
                    array(
                        'id' => 'about-danger',
                        'title' => esc_html__( 'Danger zone', 'joinotify' ),
                        'description' => esc_html__( 'Irreversible actions and configuration cleanup.', 'joinotify' ),
                        'component' => 'danger-zone',
                    ),
                ),
            ),
        );

        return apply_filters( 'Joinotify/Admin/Settings/Schema', $schema );
    }


    /**
     * Build the tabs used by the settings section navigation.
     *
     * @since 1.4.7
     * @return array<int,array<string,mixed>>
     */
    public static function get_section_tabs() {
        $tabs = array(
            array(
                'id' => 'general',
                'name' => esc_html__( 'General', 'joinotify' ),
                'icon' => '<svg class="joinotify-tab-icon"><path d="M7.5 14.5c-1.58 0-2.903 1.06-3.337 2.5H2v2h2.163c.434 1.44 1.757 2.5 3.337 2.5s2.903-1.06 3.337-2.5H22v-2H10.837c-.434-1.44-1.757-2.5-3.337-2.5zm0 5c-.827 0-1.5-.673-1.5-1.5s.673-1.5 1.5-1.5S9 17.173 9 18s-.673 1.5-1.5 1.5zm9-11c-1.58 0-2.903 1.06-3.337 2.5H2v2h11.163c.434 1.44 1.757 2.5 3.337 2.5s2.903-1.06 3.337-2.5H22v-2h-2.163c-.434-1.44-1.757-2.5-3.337-2.5zm0 5c-.827 0-1.5-.673-1.5-1.5s.673-1.5 1.5-1.5 1.5.673 1.5 1.5-.673 1.5-1.5 1.5z"></path><path d="M12.837 5C12.403 3.56 11.08 2.5 9.5 2.5S6.597 3.56 6.163 5H2v2h4.163C6.597 8.44 7.92 9.5 9.5 9.5s2.903-1.06 3.337-2.5h9.288V5h-9.288zM9.5 7.5C8.673 7.5 8 6.827 8 6s.673-1.5 1.5-1.5S11 5.173 11 6s-.673 1.5-1.5 1.5z"></path></svg>',
                'section' => 'general',
            ),
            array(
                'id' => 'phones',
                'name' => esc_html__( 'Phones', 'joinotify' ),
                'icon' => '<svg class="joinotify-tab-icon" xmlns="http://www.w3.org/2000/svg"><path d="M17.707 12.293a.999.999 0 0 0-1.414 0l-1.594 1.594c-.739-.22-2.118-.72-2.992-1.594s-1.374-2.253-1.594-2.992l1.594-1.594a.999.999 0 0 0 0-1.414l-4-4a.999.999 0 0 0-1.414 0L3.581 5.005c-.38.38-.594.902-.586 1.435.023 1.424.4 6.37 4.298 10.268s8.844 4.274 10.269 4.298h.028c.528 0 1.027-.208 1.405-.586l2.712-2.712a.999.999 0 0 0 0-1.414l-4-4.001zm-.127 6.712c-1.248-.021-5.518-.356-8.873-3.712-3.366-3.366-3.692-7.651-3.712-8.874L7 4.414 9.586 7 8.293 8.293a1 1 0 0 0-.272.912c.024.115.611 2.842 2.271 4.502s4.387 2.247 4.502 2.271a.991.991 0 0 0 .912-.271L17 14.414 19.586 17l-2.006 2.005z"></path></svg>',
                'section' => 'phones',
            ),
            array(
                'id' => 'integrations',
                'name' => esc_html__( 'Integrations', 'joinotify' ),
                'icon' => '<svg class="joinotify-tab-icon"><path d="M3 8h2v5c0 2.206 1.794 4 4 4h2v5h2v-5h2c2.206 0 4-1.794 4-4V8h2V6H3v2zm4 0h10v5c0 1.103-.897 2-2 2H9c-1.103 0-2-.897-2-2V8zm0-6h2v3H7zm8 0h2v3h-2z"></path></svg>',
                'section' => 'integrations',
            ),
            array(
                'id' => 'about',
                'name' => esc_html__( 'About', 'joinotify' ),
                'icon' => '<svg class="joinotify-tab-icon"><path d="M12 2C6.486 2 2 6.486 2 12s4.486 10 10 10 10-4.486 10-10S17.514 2 12 2zm0 18c-4.411 0-8-3.589-8-8s3.589-8 8-8 8 3.589 8 8-3.589 8-8 8z"></path><path d="M11 11h2v6h-2zm0-4h2v2h-2z"></path></svg>',
                'section' => 'about',
            ),
        );

        return apply_filters( 'Joinotify/Admin/Settings/Section_Tabs', $tabs );
    }


    /**
     * Flatten all field definitions by key.
     *
     * @since 1.4.7
     * @return array<string,array<string,mixed>>
     */
    public static function get_field_definitions() {
        $fields = array();

        foreach ( self::get_schema() as $section ) {
            foreach ( $section['cards'] ?? array() as $card ) {
                foreach ( self::collect_card_fields( $card ) as $field ) {
                    if ( is_array( $field ) && ! empty( $field['key'] ) ) {
                        $fields[ (string) $field['key'] ] = $field;
                    }
                }
            }
        }

        return $fields;
    }


    /**
     * Build the integration cards used by the integrations section.
     *
     * @since 1.4.7
     * @return array<int,array<string,mixed>>
     */
    public static function get_integration_cards() {
        if ( ! function_exists( 'is_plugin_active' ) ) {
            require_once ABSPATH . 'wp-admin/includes/plugin.php';
        }

        if ( ! function_exists( 'get_plugins' ) ) {
            require_once ABSPATH . 'wp-admin/includes/plugin.php';
        }

        $settings = self::get_settings();
        $cards = array();
        $integrations = Integrations_Base::integration_tab_items();

        foreach ( $integrations as $slug => $item ) {
            $requires_plugin = ! empty( $item['is_plugin'] );
            $plugin_active = self::is_integration_plugin_active( $item['plugin_active'] ?? array(), $requires_plugin );
            $setting_key = $item['setting_key'] ?? '';
            $settings_fields = self::collect_card_fields( $item );
            $coming_soon = ! empty( $item['coming_soon'] ) || ! empty( $item['comming_soon'] );
            $modal = isset( $item['modal'] ) && is_array( $item['modal'] ) ? $item['modal'] : array();

            $card = array(
                'slug' => $slug,
                'title' => $item['title'] ?? ucfirst( $slug ),
                'description' => $item['description'] ?? '',
                'icon' => $item['icon'] ?? '',
                'setting_key' => $setting_key,
                'enabled' => $setting_key ? ( ( $settings[ $setting_key ] ?? 'no' ) === 'yes' ) : false,
                'requires_plugin' => $requires_plugin,
                'plugin_active' => $plugin_active,
                'coming_soon' => $coming_soon,
                'comming_soon' => $coming_soon,
                'modal' => $modal,
                'disabled_message' => $requires_plugin && ! $plugin_active
                    ? esc_html__( 'This plugin needs to be installed and active to enable this integration.', 'joinotify' )
                    : '',
                'fields' => $settings_fields,
                'settings' => $settings_fields,
                'defaults' => $item['defaults'] ?? array(),
                'has_settings' => ! empty( $settings_fields ),
            );

            $cards[] = $card;
        }

        return $cards;
    }

    
    /**
     * Current sender list and supporting phone metadata.
     *
     * @since 1.4.7
     * @return array<string,mixed>
     */
    public static function get_phone_state() {
        $phones_senders = get_option( 'joinotify_get_phones_senders', array() );
        $phones_senders = is_array( $phones_senders ) ? array_values( array_filter( $phones_senders ) ) : array();

        $senders = array();

        foreach ( $phones_senders as $phone ) {
            $senders[] = array(
                'phone' => $phone,
                'formatted' => Helpers::validate_and_format_phone( $phone ),
                'connection' => get_option( 'joinotify_status_connection_' . $phone, 'disconnected' ),
            );
        }

        return array(
            'senders' => $senders,
            'test_number_phone' => Admin::get_setting( 'test_number_phone' ),
            'default_country_iso2' => self::get_default_country_iso2(),
            'locale' => function_exists('determine_locale') ? determine_locale() : get_locale(),
            'sender_count' => count( $senders ),
        );
    }


    /**
     * Runtime system details shown on the About tab.
     *
     * @since 1.4.7
     * @return array<string,mixed>
     */
    public static function get_system_status() {
        $post_max_size = function_exists( 'ini_get' ) ? ini_get( 'post_max_size' ) : '';
        $max_execution_time = function_exists( 'ini_get' ) ? ini_get( 'max_execution_time' ) : '';
        $max_input_vars = function_exists( 'ini_get' ) ? ini_get( 'max_input_vars' ) : '';
        $memory_limit = function_exists( 'ini_get' ) ? ini_get( 'memory_limit' ) : '';
        $upload_max_filesize = function_exists( 'ini_get' ) ? ini_get( 'upload_max_filesize' ) : '';

        return array(
            'wordpress' => array(
                array(
                    'label' => esc_html__( 'WordPress version', 'joinotify' ),
                    'value' => get_bloginfo( 'version' ),
                    'status' => 'info',
                ),
                array(
                    'label' => esc_html__( 'WordPress Multisite', 'joinotify' ),
                    'value' => is_multisite() ? esc_html__( 'Yes', 'joinotify' ) : esc_html__( 'No', 'joinotify' ),
                    'status' => 'info',
                ),
                array(
                    'label' => esc_html__( 'WP_DEBUG', 'joinotify' ),
                    'value' => defined( 'WP_DEBUG' ) && WP_DEBUG ? esc_html__( 'Enabled', 'joinotify' ) : esc_html__( 'Disabled', 'joinotify' ),
                    'status' => defined( 'WP_DEBUG' ) && WP_DEBUG ? 'warning' : 'success',
                ),
            ),
            'plugin' => array(
                array(
                    'label' => esc_html__( 'Joinotify version', 'joinotify' ),
                    'value' => JOINOTIFY_VERSION,
                    'status' => 'info',
                ),
            ),
            'server' => array(
                array(
                    'label' => esc_html__( 'PHP version', 'joinotify' ),
                    'value' => PHP_VERSION,
                    'status' => version_compare( PHP_VERSION, '7.4', '>=' ) ? 'success' : 'danger',
                ),
                array(
                    'label' => esc_html__( 'DOMDocument', 'joinotify' ),
                    'value' => class_exists( 'DOMDocument' ) ? esc_html__( 'Yes', 'joinotify' ) : esc_html__( 'No', 'joinotify' ),
                    'status' => class_exists( 'DOMDocument' ) ? 'success' : 'danger',
                ),
                array(
                    'label' => esc_html__( 'cURL ', 'joinotify' ),
                    'value' => extension_loaded( 'curl' ) ? curl_version()['version'] : esc_html__( 'No', 'joinotify' ),
                    'status' => extension_loaded( 'curl' ) ? 'success' : 'danger',
                ),
                array(
                    'label' => esc_html__( 'OpenSSL extension', 'joinotify' ),
                    'value' => extension_loaded( 'openssl' ) ? OPENSSL_VERSION_TEXT : esc_html__( 'No', 'joinotify' ),
                    'status' => extension_loaded( 'openssl' ) ? 'success' : 'danger',
                ),
                array(
                    'label' => esc_html__( 'post_max_size', 'joinotify' ),
                    'value' => $post_max_size,
                    'status' => function_exists( 'wp_convert_hr_to_bytes' ) && wp_convert_hr_to_bytes( $post_max_size ) < 64000000 ? 'danger' : 'success',
                ),
                array(
                    'label' => esc_html__( 'max_execution_time', 'joinotify' ),
                    'value' => $max_execution_time,
                    'status' => (int) $max_execution_time < 180 ? 'danger' : 'success',
                ),
                array(
                    'label' => esc_html__( 'max_input_vars', 'joinotify' ),
                    'value' => $max_input_vars,
                    'status' => (int) $max_input_vars < 10000 ? 'danger' : 'success',
                ),
                array(
                    'label' => esc_html__( 'memory_limit', 'joinotify' ),
                    'value' => $memory_limit,
                    'status' => function_exists( 'wp_convert_hr_to_bytes' ) && wp_convert_hr_to_bytes( $memory_limit ) < 128000000 ? 'danger' : 'success',
                ),
                array(
                    'label' => esc_html__( 'upload_max_filesize', 'joinotify' ),
                    'value' => $upload_max_filesize,
                    'status' => function_exists( 'wp_convert_hr_to_bytes' ) && wp_convert_hr_to_bytes( $upload_max_filesize ) < 64000000 ? 'danger' : 'success',
                ),
                array(
                    'label' => esc_html__( 'allow_url_fopen', 'joinotify' ),
                    'value' => ! ini_get( 'allow_url_fopen' ) ? esc_html__( 'Off', 'joinotify' ) : esc_html__( 'On', 'joinotify' ),
                    'status' => ! ini_get( 'allow_url_fopen' ) ? 'danger' : 'success',
                ),
            ),
        );
    }


    /**
     * Build the full bootstrap payload for the frontend application.
     *
     * @since 1.4.7
     * @return array<string,mixed>
     */
    public static function get_bootstrap_data() {
        return apply_filters( 'Joinotify/Admin/Settings/Bootstrap_Data', array(
            'version' => JOINOTIFY_VERSION,
            'page' => 'settings',
            'settings' => self::get_settings(),
            'schema' => self::get_schema(),
            'section_tabs' => self::get_section_tabs(),
            'integrations' => self::get_integration_cards(),
            'phones' => self::get_phone_state(),
            'system' => self::get_system_status(),
            'license' => self::get_license_state(),
            'links' => array(
                'docs_url' => esc_url_raw( 'https://ajuda.meumouse.com/docs/joinotify/overview' ),
                'purchase_url' => esc_url_raw( 'https://meumouse.com/plugins/joinotify/' ),
            ),
            'permissions' => array(
                'manage_options' => current_user_can( 'manage_options' ),
            ),
            'rest' => array(
                'root' => esc_url_raw( rest_url( 'joinotify/v1' ) ),
                'nonce' => wp_create_nonce( 'wp_rest' ),
            ),
            'contracts' => array(
                'bootstrap_filter' => 'Joinotify/Admin/Settings/Bootstrap_Data',
                'schema_filter' => 'Joinotify/Admin/Settings/Schema',
                'section_tabs_filter' => 'Joinotify/Admin/Settings/Section_Tabs',
                'integration_filter' => 'Joinotify/Settings/Tabs/Integrations',
                'integration_field_types' => self::get_supported_integration_field_types(),
                'integration_field_components' => self::get_supported_integration_field_components(),
                'integration_modal_block_types' => array( 'html', 'component' ),
                'actions_filter' => 'Joinotify/Builder/Actions',
                'triggers_filter' => 'Joinotify/Builder/Get_All_Triggers',
            ),
            'i18n' => array(
                'saved' => esc_html__( 'Settings saved.', 'joinotify' ),
                'error' => esc_html__( 'Could not complete the operation.', 'joinotify' ),
            ),
        ) );
    }


    /**
     * Build the license state payload used by the Vue license page.
     *
     * @since 1.4.7
     * @return array<string,mixed>
     */
    public static function get_license_state() {
        $license_key = get_option( 'joinotify_license_key', '' );
        $license_key = is_string( $license_key ) ? sanitize_text_field( $license_key ) : '';
        $license_object = get_option( 'joinotify_license_response_object' );
        $is_valid = License::is_valid();
        $purchase_url = apply_filters( 'Joinotify/Admin/Settings/License_Purchase_Url', 'https://meumouse.com/plugins/joinotify/' );
        $docs_url = apply_filters( 'Joinotify/Admin/Settings/License_Help_Url', 'https://ajuda.meumouse.com/docs/joinotify/overview' );

        $subscription_label = $is_valid
            ? ( strpos( $license_key, 'CM-' ) === 0
                ? sprintf( esc_html__( 'Subscription: Club M - %s', 'joinotify' ), License::license_title() )
                : sprintf( esc_html__( 'Subscription: %s', 'joinotify' ), License::license_title() )
            )
            : esc_html__( 'Activate your license to unlock premium features.', 'joinotify' );

        $support_text = esc_html__( 'Not available', 'joinotify' );

        if ( is_object( $license_object ) && ! empty( $license_object->support_end ) ) {
            $support_text = is_string( $license_object->support_end )
                ? sanitize_text_field( $license_object->support_end )
                : esc_html__( 'Not available', 'joinotify' );
        }

        return array(
            'is_valid' => $is_valid,
            'status_label' => $is_valid ? esc_html__( 'Valid', 'joinotify' ) : esc_html__( 'Invalid', 'joinotify' ),
            'status_tone' => $is_valid ? 'success' : 'danger',
            'title' => $is_valid ? esc_html__( 'Active license', 'joinotify' ) : esc_html__( 'Activate your license', 'joinotify' ),
            'subtitle' => $is_valid
                ? esc_html__( 'Your installation is now ready for full use.', 'joinotify' )
                : esc_html__( 'Enter the license code to unlock premium features.', 'joinotify' ),
            'purchase_url' => esc_url_raw( $purchase_url ),
            'docs_url' => esc_url_raw( $docs_url ),
            'activate_action' => 'joinotify_active_license',
            'deactivate_action' => 'joinotify_deactive_license',
            'sync_action' => 'joinotify_sync_license',
            'alternative_action' => 'joinotify_alternative_activation_license',
            'license_key' => $license_key,
            'license_key_masked' => self::mask_license_key( $license_key ),
            'license_title' => $is_valid ? License::license_title() : esc_html__( 'Not available', 'joinotify' ),
            'subscription_label' => $subscription_label,
            'expire_label' => $is_valid
                ? sprintf( esc_html__( 'License expires in: %s', 'joinotify' ), License::license_expire() )
                : esc_html__( 'License expires in: Not available', 'joinotify' ),
            'support_label' => $is_valid
                ? sprintf( esc_html__( 'Support up to: %s', 'joinotify' ), $support_text )
                : esc_html__( 'Support up to: Not available', 'joinotify' ),
            'key_label' => esc_html__( 'Your license key:', 'joinotify' ) . ' ' . self::mask_license_key( $license_key ),
            'renew_link' => is_object( $license_object ) && ! empty( $license_object->renew_link ) ? esc_url_raw( $license_object->renew_link ) : '',
            'expire_renew_link' => is_object( $license_object ) && ! empty( $license_object->expire_renew_link ) ? esc_url_raw( $license_object->expire_renew_link ) : '',
            'support_renew_link' => is_object( $license_object ) && ! empty( $license_object->support_renew_link ) ? esc_url_raw( $license_object->support_renew_link ) : '',
        );
    }


    /**
     * Collect field definitions from a card payload.
     *
     * @since 1.4.7
     * @param array<string,mixed> $card
     * @return array<int,array<string,mixed>>
     */
    private static function collect_card_fields( $card ) {
        $fields = array();
        $seen = array();

        foreach ( array( 'settings', 'fields' ) as $property ) {
            if ( empty( $card[ $property ] ) || ! is_array( $card[ $property ] ) ) {
                continue;
            }

            foreach ( $card[ $property ] as $field ) {
                if ( ! is_array( $field ) ) {
                    continue;
                }

                $signature = '';

                if ( ! empty( $field['key'] ) ) {
                    $signature = 'key:' . sanitize_key( (string) $field['key'] );
                } else {
                    $signature = md5( wp_json_encode( $field ) );
                }

                if ( isset( $seen[ $signature ] ) ) {
                    continue;
                }

                $seen[ $signature ] = true;
                $fields[] = $field;
            }
        }

        return $fields;
    }


    /**
     * Return the integration field types supported by the frontend.
     *
     * @since 1.4.7
     * @return array<int,string>
     */
    private static function get_supported_integration_field_types() {
        return array( 'toggle', 'text', 'textarea', 'select', 'phone', 'color', 'color-scale', 'input-group' );
    }


    /**
     * Return the built-in integration field components supported by the frontend.
     *
     * @since 1.4.7
     * @return array<int,string>
     */
    private static function get_supported_integration_field_components() {
        return array(
            'toggle',
            'text',
            'textarea',
            'select',
            'phone',
            'input-group',
            'input-button',
            'otp',
            'color-picker',
            'color-picker-field',
            'color-scale',
            'color-scale-field',
        );
    }


    /**
     * Build the country-code select options.
     *
     * @since 1.4.7
     * @return array<int,array<string,string>>
     */
    private static function build_country_code_options() {
        $options = array(
            array(
                'value' => '0',
                'label' => esc_html__( 'None', 'joinotify' ),
            ),
        );

        foreach ( Country_Codes::build_country_code_select() as $country ) {
            $options[] = array(
                'value' => (string) $country['code'],
                'label' => sprintf( '%s (+%s)', $country['country'], $country['code'] ),
            );
        }

        return $options;
    }


    /**
     * Convert the configured default dial code to an ISO2 country code.
     *
     * @since 1.4.7
     * @return string
     */
    private static function get_default_country_iso2() {
        $default_country_code = (string) Admin::get_setting( 'joinotify_default_country_code', '55' );
        $countries = Country_Codes::get_country_codes_with_names();

        if ( ! isset( $countries[ $default_country_code ] ) ) {
            return 'us';
        }

        $country_data = $countries[ $default_country_code ];
        $iso2 = array_key_first( $country_data );

        return is_string( $iso2 ) && $iso2 ? strtolower( $iso2 ) : 'us';
    }


    /**
     * Mask a license key preserving the beginning and end.
     *
     * @since 1.4.7
     * @param string $license_key
     * @return string
     */
    private static function mask_license_key( $license_key ) {
        if ( empty( $license_key ) ) {
            return esc_html__( 'Not available', 'joinotify' );
        }

        $license_key = sanitize_text_field( $license_key );

        return substr( $license_key, 0, 9 ) . 'XXXXXXXX-XXXXXXXX' . substr( $license_key, -9 );
    }


    /**
     * Check whether all required plugins for an integration are active.
     *
     * @since 1.4.7
     * @param array<int,string> $plugin_slugs
     * @param bool $requires_plugin
     * @return bool
     */
    private static function is_integration_plugin_active( $plugin_slugs, $requires_plugin ) {
        if ( ! $requires_plugin ) {
            return true;
        }

        if ( ! is_array( $plugin_slugs ) || empty( $plugin_slugs ) ) {
            return false;
        }

        foreach ( $plugin_slugs as $plugin ) {
            if ( function_exists( 'is_plugin_active' ) && is_plugin_active( $plugin ) ) {
                return true;
            }
        }

        return false;
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
    private static function field_select( $key, $label, $description, $options, $extra = array() ) {
        return array_merge( array(
            'type' => 'select',
            'key' => $key,
            'label' => $label,
            'description' => $description,
            'options' => $options,
        ), $extra );
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
    private static function field_toggle( $key, $label, $description, $extra = array() ) {
        return array_merge( array(
            'type' => 'toggle',
            'key' => $key,
            'label' => $label,
            'description' => $description,
        ), $extra );
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
    private static function field_text( $key, $label, $description, $extra = array() ) {
        return array_merge( array(
            'type' => 'text',
            'key' => $key,
            'label' => $label,
            'description' => $description,
        ), $extra );
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
    private static function field_textarea( $key, $label, $description, $extra = array() ) {
        return array_merge( array(
            'type' => 'textarea',
            'key' => $key,
            'label' => $label,
            'description' => $description,
        ), $extra );
    }


    /**
     * Build a color picker field definition.
     *
     * @since 1.4.7
     * @param string $key
     * @param string $label
     * @param string $description
     * @param array<string,mixed> $extra
     * @return array<string,mixed>
     */
    private static function field_color( $key, $label, $description, $extra = array() ) {
        return array_merge( array(
            'type' => 'color',
            'key' => $key,
            'label' => $label,
            'description' => $description,
            'default' => '#4f46e5',
        ), $extra );
    }


    /**
     * Build a color scale field definition.
     *
     * @since 1.4.7
     * @param string $key
     * @param string $label
     * @param string $description
     * @param array<string,mixed> $extra
     * @return array<string,mixed>
     */
    private static function field_color_scale( $key, $label, $description, $extra = array() ) {
        return array_merge( array(
            'type' => 'color-scale',
            'key' => $key,
            'label' => $label,
            'description' => $description,
            'default' => array(
                'baseColor' => '#4f46e5',
                'palette'   => array(),
            ),
            'component' => 'color-scale',
            'component_props' => array(
                'baseColorName' => $key . '_base_color',
                'paletteName'   => $key . '_palette',
            ),
        ), $extra );
    }
}
