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
            'label' => __( 'Artificial Intelligence', 'joinotify' ),
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
            'title' => __( 'WhatsApp: AI message', 'joinotify' ),
            'description' => __( 'Generate a message with AI at trigger time and send it via WhatsApp.', 'joinotify' ),
            'context' => array(),
            'category' => 'ai',
            'icon' => Whatsapp::get_action_icon(),
            'external_icon' => false,
            'has_settings' => true,
            'is_expansible' => false,
            'priority' => 45,
            'default_data' => array(
                'title' => __( 'WhatsApp: AI message', 'joinotify' ),
                'description' => '',
                'action' => 'send_whatsapp_ai_message',
                'sender' => '',
                'receiver' => '{{ wc_billing_phone }}',
                'ai_prompt' => '',
                'ai_system' => '',
                'ai_tone' => 'friendly',
                'ai_length' => 'medium',
                'ai_provider' => '',
                'ai_model' => '',
                'ai_temperature' => '',
            ),
        );

        $actions[] = array(
            'action' => 'dynamic_placeholder',
            'title' => __( 'AI: Smart variable', 'joinotify' ),
            'description' => __( 'Generate a named value with AI and reuse it in later messages with {{ ai:NAME }}.', 'joinotify' ),
            'context' => array(),
            'category' => 'ai',
            'icon' => '<svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" fill="currentColor"><path d="M12 2 9.5 9.5 2 12l7.5 2.5L12 22l2.5-7.5L22 12l-7.5-2.5L12 2zm7 13-1 3-3 1 3 1 1 3 1-3 3-1-3-1-1-3z"></path></svg>',
            'external_icon' => false,
            'has_settings' => true,
            'is_expansible' => false,
            'priority' => 60,
            'default_data' => array(
                'title' => __( 'AI: Smart variable', 'joinotify' ),
                'description' => '',
                'action' => 'dynamic_placeholder',
                'var_name' => '',
                'ai_prompt' => '',
                'ai_system' => '',
                'ai_provider' => '',
                'ai_model' => '',
                'ai_temperature' => '',
            ),
        );

        return $actions;
    }
}
