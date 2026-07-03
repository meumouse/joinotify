<?php

namespace MeuMouse\Joinotify\AI;

// Exit if accessed directly.
defined('ABSPATH') || exit;

/**
 * Contract implemented by every LLM provider (OpenAI, Anthropic, Gemini, ...).
 *
 * New providers are registered through the `Joinotify/AI/Providers` filter and
 * only need to implement this interface to be selectable as the active engine.
 *
 * @since 2.0.0
 * @package MeuMouse\Joinotify\AI
 * @author MeuMouse.com
 */
interface Provider_Interface {

    /**
     * Unique provider identifier (e.g. 'openai').
     *
     * @since 2.0.0
     * @return string
     */
    public function get_id();


    /**
     * Human-readable provider label (e.g. 'OpenAI').
     *
     * @since 2.0.0
     * @return string
     */
    public function get_label();


    /**
     * Selectable models for the settings dropdown.
     *
     * @since 2.0.0
     * @return array<int,array{value:string,label:string}>
     */
    public function get_models();


    /**
     * Whether the provider has the credentials it needs to run.
     *
     * @since 2.0.0
     * @return bool
     */
    public function is_configured();


    /**
     * Generate a completion for the given request.
     *
     * @since 2.0.0
     * @param AI_Request $request | Generation request.
     * @return AI_Response
     */
    public function generate( AI_Request $request );
}
