<?php

namespace MeuMouse\Joinotify\Integrations;

use MeuMouse\Joinotify\Admin\Admin;

// Exit if accessed directly.
defined('ABSPATH') || exit;

/**
 * AI-powered messaging actions for the workflow builder.
 *
 * Registers the "WhatsApp: AI message" action, which generates the message
 * text at trigger time (via the active AI provider) and sends it over WhatsApp.
 * The action is gated on the WhatsApp integration being enabled; the runtime
 * handler degrades gracefully when no AI provider is configured.
 *
 * @since 2.0.0
 * @package MeuMouse\Joinotify\Integrations
 * @author MeuMouse.com
 */
class AI_Messaging extends Integrations_Base {

    /**
     * Construct function
     *
     * @since 2.0.0
     * @return void
     */
    public function __construct() {
        if ( Admin::get_setting('enable_whatsapp_integration') === 'yes' ) {
            add_filter( 'Joinotify/Builder/Actions', array( $this, 'add_ai_actions' ), 20, 1 );
            add_filter( 'Joinotify/Builder/Action_Categories', array( $this, 'add_ai_category' ), 10, 1 );
        }
    }


    /**
     * Register the "Artificial Intelligence" category on the builder actions library.
     *
     * @since 2.0.0
     * @param array $categories | Current categories
     * @return array
     */
    public function add_ai_category( $categories ) {
        $categories[] = array(
            'id' => 'ai',
            'label' => esc_html__( 'Artificial Intelligence', 'joinotify' ),
            'icon' => '<svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" fill="currentColor"><path d="M19 9l1.25-2.75L23 5l-2.75-1.25L19 1l-1.25 2.75L15 5l2.75 1.25L19 9zm-7.5.5L9 4 6.5 9.5 1 12l5.5 2.5L9 20l2.5-5.5L17 12l-5.5-2.5zM19 15l-1.25 2.75L15 19l2.75 1.25L19 23l1.25-2.75L23 19l-2.75-1.25L19 15z"></path></svg>',
            'priority' => 15,
        );

        return $categories;
    }


    /**
     * Add AI messaging actions to the builder actions catalog.
     *
     * @since 2.0.0
     * @param array $actions | Current actions
     * @return array
     */
    public function add_ai_actions( $actions ) {
        $actions[] = array(
            'action' => 'send_whatsapp_ai_message',
            'title' => esc_html__( 'WhatsApp: AI message', 'joinotify' ),
            'description' => esc_html__( 'Generate a message with AI at trigger time and send it via WhatsApp.', 'joinotify' ),
            'context' => array(),
            'category' => 'ai',
            'icon' => '<svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" fill="currentColor"><path d="M12.04 2c-5.46 0-9.91 4.45-9.91 9.91 0 1.75.46 3.45 1.32 4.95L2.05 22l5.25-1.38c1.45.79 3.08 1.21 4.74 1.21h.01c5.46 0 9.91-4.45 9.91-9.91 0-2.65-1.03-5.14-2.9-7.01A9.82 9.82 0 0 0 12.04 2zm0 1.67c2.2 0 4.27.86 5.82 2.42a8.2 8.2 0 0 1 2.42 5.83c0 4.54-3.7 8.23-8.24 8.23-1.48 0-2.93-.4-4.19-1.15l-.3-.18-3.12.82.83-3.04-.2-.31a8.18 8.18 0 0 1-1.26-4.38c0-4.54 3.7-8.24 8.24-8.24zm-.34 3.16-.66 1.45-1.45.66 1.45.66.66 1.45.66-1.45 1.45-.66-1.45-.66-.66-1.45zm4.3 4.3-.44.97-.97.44.97.44.44.97.44-.97.97-.44-.97-.44-.44-.97z"></path></svg>',
            'external_icon' => false,
            'has_settings' => true,
            'is_expansible' => false,
            'priority' => 45,
            'default_data' => array(
                'title' => esc_html__( 'WhatsApp: AI message', 'joinotify' ),
                'description' => '',
                'action' => 'send_whatsapp_ai_message',
                'sender' => '',
                'receiver' => '{{ wc_billing_phone }}',
                'ai_prompt' => '',
                'ai_system' => '',
                'ai_tone' => 'friendly',
                'ai_length' => 'medium',
                'ai_model' => '',
                'ai_temperature' => '',
            ),
        );

        $actions[] = array(
            'action' => 'dynamic_placeholder',
            'title' => esc_html__( 'AI: Smart variable', 'joinotify' ),
            'description' => esc_html__( 'Generate a named value with AI and reuse it in later messages with {{ ai:NAME }}.', 'joinotify' ),
            'context' => array(),
            'category' => 'ai',
            'icon' => '<svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" fill="currentColor"><path d="M12 2 9.5 9.5 2 12l7.5 2.5L12 22l2.5-7.5L22 12l-7.5-2.5L12 2zm7 13-1 3-3 1 3 1 1 3 1-3 3-1-3-1-1-3z"></path></svg>',
            'external_icon' => false,
            'has_settings' => true,
            'is_expansible' => false,
            'priority' => 60,
            'default_data' => array(
                'title' => esc_html__( 'AI: Smart variable', 'joinotify' ),
                'description' => '',
                'action' => 'dynamic_placeholder',
                'var_name' => '',
                'ai_prompt' => '',
                'ai_system' => '',
                'ai_model' => '',
                'ai_temperature' => '',
            ),
        );

        return $actions;
    }
}
