<?php

namespace MeuMouse\Joinotify\Integrations;

use MeuMouse\Joinotify\AI\Providers\Anthropic_Provider;

// Exit if accessed directly.
defined('ABSPATH') || exit;

/**
 * Add integration with Anthropic (Claude)
 *
 * @since 2.1.0
 * @package MeuMouse\Joinotify\Integrations
 * @author MeuMouse.com
 */
class Anthropic extends Integrations_Base {

    /**
     * Construct function
     *
     * @since 2.1.0
     * @return void
     */
    public function __construct() {
        // add integration on settings
        add_filter( 'Joinotify/Settings/Tabs/Integrations', array( $this, 'add_integration_item' ), 41, 1 );
    }


    /**
     * Add integration item on settings
     *
     * @since 2.1.0
     * @param array $integrations | Current integrations
     * @return array
     */
    public function add_integration_item( $integrations ) {
        $integrations['anthropic'] = self::build_integration_item(
            'anthropic',
            esc_html__( 'Anthropic', 'joinotify' ),
            esc_html__( 'Create dynamic messages for your automations using Claude, the family of AI models from Anthropic.', 'joinotify' ),
            '<svg role="img" viewBox="0 0 24 24" xmlns="http://www.w3.org/2000/svg" fill="#D97757" style="max-width: 4.5rem;"><title>Anthropic icon</title><path d="M13.827 3.52h3.603L24 20h-3.603l-6.57-16.48zm-7.258 0h3.767L16.906 20h-3.674l-1.343-3.461H5.017l-1.344 3.46H0L6.57 3.52zm4.132 9.959L8.453 7.687 6.205 13.48H10.7z"/></svg>',
            array(
                'category' => 'ai',
                'setting_key' => 'enable_anthropic_integration',
                'action_hook' => 'Joinotify/Settings/Tabs/Integrations/Anthropic',
                'settings' => self::get_integration_settings(),
                'modal' => array(
                    'title' => __( 'Anthropic (Claude) settings', 'joinotify' ),
                    'description' => __( 'Configure the credentials and default model used to generate messages with Claude.', 'joinotify' ),
                    'button_label' => __( 'Configure', 'joinotify' ),
                ),
            )
        );

        return $integrations;
    }


    /**
     * Declarative settings rendered in the integration modal.
     *
     * @since 2.1.0
     * @return array<int,array<string,mixed>>
     */
    public static function get_integration_settings() {
        $anthropic_models = ( new Anthropic_Provider() )->get_models();

        return array(
            self::field_text(
                'anthropic_api_key',
                esc_html__( 'Anthropic API key', 'joinotify' ),
                esc_html__( 'Secret key used to authenticate requests to Anthropic. Find it at console.anthropic.com.', 'joinotify' ),
                array(
                    'placeholder' => 'sk-ant-...',
                    'autocomplete' => 'off',
                )
            ),
            self::field_select(
                'anthropic_default_model',
                esc_html__( 'Default model', 'joinotify' ),
                esc_html__( 'Claude model used when a workflow node does not override it. The list is fetched from Anthropic and can be refreshed.', 'joinotify' ),
                $anthropic_models,
                array(
                    'default' => 'claude-haiku-4-5',
                    'component' => 'anthropic-model-select',
                    'component_props' => array(
                        'endpoint' => 'admin/ai/anthropic-models',
                    ),
                )
            ),
        );
    }
}
