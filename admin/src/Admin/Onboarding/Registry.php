<?php

namespace MeuMouse\Joinotify\Admin\Onboarding;

use MeuMouse\Joinotify\Admin\Settings\Registry as Settings_Registry;
use MeuMouse\Joinotify\Admin\Settings\Repository;
use MeuMouse\Joinotify\AI\AI_Manager;
use MeuMouse\Joinotify\Core\Helpers;
use MeuMouse\Joinotify\Core\Locale_Detector;
use MeuMouse\Joinotify\Core\Onboarding;
use MeuMouse\Joinotify\Core\Telemetry;

defined('ABSPATH') || exit;

/**
 * Build the payload the setup wizard hydrates itself with.
 *
 * The wizard writes to the same `joinotify_settings` option the settings screen
 * does, so everything here is read through the existing settings layer rather
 * than duplicating field definitions. What the wizard adds is the ordering, the
 * country suggestion and the telemetry preview.
 *
 * @since 2.3.0
 * @package MeuMouse\Joinotify\Admin\Onboarding
 * @author MeuMouse.com
 */
class Registry {

    /**
     * Settings keys the wizard is allowed to read back and write.
     *
     * Everything else on the settings screen is out of the wizard's reach, so a
     * malformed step payload can never reach an unrelated option.
     *
     * @since 2.3.0
     * @var array<int,string>
     */
    const ALLOWED_KEYS = array(
        'joinotify_default_country_code',
        'whatsapp_cloud_api_token',
        'whatsapp_phone_number_id',
        'whatsapp_waba_id',
        'ai_provider',
        'enable_ai_integration',
        'enable_usage_tracking',
    );


    /**
     * Build the wizard bootstrap payload.
     *
     * @since 2.3.0
     * @return array<string,mixed>
     */
    public static function get_bootstrap_data() {
        $settings = Repository::get_settings();

        return apply_filters( 'Joinotify/Admin/Onboarding/Bootstrap_Data', array(
            'version' => JOINOTIFY_VERSION,
            'page' => 'onboarding',
            'state' => Onboarding::get_state(),
            'steps' => self::get_steps(),
            'settings' => self::visible_settings( $settings ),
            'country' => array(
                'suggestion' => Locale_Detector::suggest_country_code(),
                'options' => Settings_Registry::build_country_code_options(),
            ),
            'ai' => self::get_ai_status(),
            'connection' => array(
                'connected' => '' !== Helpers::cloud_api_token(),
                'panel_url' => esc_url_raw( JOINOTIFY_PANEL_URL ),
                'api_host' => wp_parse_url( JOINOTIFY_CLOUD_API_BASE_URL, PHP_URL_HOST ),
            ),
            'telemetry' => Telemetry::preview(),
            'links' => array(
                'docs_url' => esc_url_raw( JOINOTIFY_DOCS_URL ),
                'panel_url' => esc_url_raw( JOINOTIFY_PANEL_URL ),
                'builder_url' => esc_url_raw( admin_url('admin.php?page=joinotify-workflows-builder') ),
                'settings_url' => esc_url_raw( admin_url('admin.php?page=joinotify-settings') ),
                'workflows_url' => esc_url_raw( admin_url('admin.php?page=joinotify-workflows') ),
            ),
            'rest' => array(
                'root' => esc_url_raw( rest_url('joinotify/v1') ),
                'nonce' => wp_create_nonce('wp_rest'),
            ),
        ) );
    }


    /**
     * Describe the wizard steps, in order.
     *
     * Titles and descriptions live here so they go through the same translation
     * pipeline as the rest of the backend copy.
     *
     * @since 2.3.0
     * @return array<int,array<string,mixed>>
     */
    public static function get_steps() {
        $steps = array(
            array(
                'id' => 'country',
                'title' => __( 'Default country', 'joinotify' ),
                'summary' => __( 'Used to complete phone numbers that were typed without a country code.', 'joinotify' ),
                'optional' => false,
            ),
            array(
                'id' => 'connect',
                'title' => __( 'Connect to Joinotify', 'joinotify' ),
                'summary' => __( 'Paste the API key issued for this site to start sending WhatsApp messages.', 'joinotify' ),
                'optional' => false,
            ),
            array(
                'id' => 'ai',
                'title' => __( 'Artificial intelligence', 'joinotify' ),
                'summary' => __( 'Optional. Adds AI-generated content and workflow suggestions, using the provider set up in WordPress.', 'joinotify' ),
                'optional' => true,
            ),
            array(
                'id' => 'docs',
                'title' => __( 'Documentation', 'joinotify' ),
                'summary' => __( 'Where to look when you need a feature explained.', 'joinotify' ),
                'optional' => false,
            ),
            array(
                'id' => 'privacy',
                'title' => __( 'Usage data', 'joinotify' ),
                'summary' => __( 'Decide whether to share anonymous data that helps improve the plugin.', 'joinotify' ),
                'optional' => false,
            ),
            array(
                'id' => 'finish',
                'title' => __( 'All set', 'joinotify' ),
                'summary' => __( 'Build your first automation or review the full settings.', 'joinotify' ),
                'optional' => false,
            ),
        );

        /**
         * Filter the setup wizard steps.
         *
         * @since 2.3.0
         * @param array<int,array<string,mixed>> $steps
         */
        return apply_filters( 'Joinotify/Admin/Onboarding/Steps', $steps );
    }


    /**
     * Report whether the site can already generate AI content.
     *
     * Credentials belong to WordPress since 7.0: the site owner picks a provider
     * once in Settings > Connectors and every plugin inherits it, so the wizard
     * only reports the state and links to that screen.
     *
     * @since 2.3.0
     * @return array<string,mixed>
     */
    public static function get_ai_status() {
        return apply_filters( 'Joinotify/Admin/Onboarding/Ai_Status', array(
            'supported' => function_exists('wp_supports_ai') ? (bool) wp_supports_ai() : false,
            'configured' => AI_Manager::is_available(),
            'connectors_url' => esc_url_raw( admin_url('options-connectors.php') ),
        ) );
    }


    /**
     * Narrow the stored settings down to the keys the wizard touches.
     *
     * Credentials are reported as a boolean rather than echoed back: the wizard
     * needs to know whether a key is already stored, never what it is.
     *
     * @since 2.3.0
     * @param array<string,mixed> $settings Full settings array.
     * @return array<string,mixed>
     */
    public static function visible_settings( $settings ) {
        $secret_keys = array( 'whatsapp_cloud_api_token' );
        $visible = array();

        foreach ( self::ALLOWED_KEYS as $key ) {
            $value = isset( $settings[ $key ] ) ? $settings[ $key ] : '';

            if ( in_array( $key, $secret_keys, true ) ) {
                $visible[ $key ] = '';
                $visible[ $key . '_is_set' ] = '' !== trim( (string) $value );

                continue;
            }

            $visible[ $key ] = $value;
        }

        return $visible;
    }


    /**
     * Persist a partial settings payload coming from a wizard step.
     *
     * Repository::save_settings() treats any switch key missing from its input
     * as "off", so the partial payload is merged over the current settings
     * before it is handed over. Skipping that would silently disable every
     * toggle on the site the moment a wizard step is saved.
     *
     * @since 2.3.0
     * @param array<string,mixed> $values Raw values from one step.
     * @return array<string,mixed> The saved settings.
     */
    public static function save_step_values( $values ) {
        $values = is_array( $values ) ? $values : array();
        $allowed = array();

        foreach ( $values as $key => $value ) {
            if ( in_array( (string) $key, self::ALLOWED_KEYS, true ) ) {
                $allowed[ (string) $key ] = $value;
            }
        }

        if ( empty( $allowed ) ) {
            return Repository::get_settings();
        }

        return Repository::save_settings( array_merge( Repository::get_settings(), $allowed ) );
    }
}
