<?php

namespace MeuMouse\Joinotify\AI\Providers;

use MeuMouse\Joinotify\AI\Provider_Interface;
use MeuMouse\Joinotify\AI\AI_Request;
use MeuMouse\Joinotify\AI\AI_Response;
use WP_Error;

// Exit if accessed directly.
defined('ABSPATH') || exit;

/**
 * Generation through the WordPress AI Client (WordPress 7.0+).
 *
 * The site owner picks the provider and stores its credentials once, in
 * Settings > Connectors, and every plugin that uses the client inherits that
 * choice. Joinotify therefore holds no API key of its own and talks to no
 * model endpoint directly.
 *
 * @since 2.3.0
 * @package MeuMouse\Joinotify\AI\Providers
 * @author MeuMouse.com
 */
class Wp_Ai_Client_Provider implements Provider_Interface {

    /**
     * Provider identifier stored in settings and workflow nodes.
     *
     * @since 2.3.0
     * @var string
     */
    const ID = 'wp_ai_client';


    /**
     * Unique provider identifier.
     *
     * @since 2.3.0
     * @return string
     */
    public function get_id() {
        return self::ID;
    }


    /**
     * Human-readable provider label.
     *
     * @since 2.3.0
     * @return string
     */
    public function get_label() {
        return __( 'WordPress AI', 'joinotify' );
    }


    /**
     * Selectable models for the settings dropdown.
     *
     * The client resolves the model from the connector the site configured, so
     * there is nothing for Joinotify to choose here.
     *
     * @since 2.3.0
     * @return array<int,array{value:string,label:string}>
     */
    public function get_models() {
        return array();
    }


    /**
     * Whether the AI Client is available and able to generate text.
     *
     * @since 2.3.0
     * @return bool
     */
    public function is_configured() {
        if ( ! function_exists('wp_ai_client_prompt') || ! function_exists('wp_supports_ai') ) {
            return false;
        }

        if ( ! wp_supports_ai() ) {
            return false;
        }

        // True only once the site has a connector that can generate text.
        return (bool) wp_ai_client_prompt('ping')->is_supported_for_text_generation();
    }


    /**
     * Generate a completion for the given request.
     *
     * @since 2.3.0
     * @param AI_Request $request | Generation request.
     * @return AI_Response
     */
    public function generate( AI_Request $request ) {
        if ( ! function_exists('wp_ai_client_prompt') ) {
            return AI_Response::failure( new WP_Error(
                'joinotify_ai_client_missing',
                __( 'The WordPress AI Client is not available. WordPress 7.0 or later is required.', 'joinotify' )
            ));
        }

        $prompt = is_string( $request->prompt ) ? trim( $request->prompt ) : '';

        if ( '' === $prompt ) {
            return AI_Response::failure( new WP_Error(
                'joinotify_ai_empty_prompt',
                __( 'The AI prompt is empty.', 'joinotify' )
            ));
        }

        $builder = wp_ai_client_prompt( $prompt );

        $system = is_string( $request->system ) ? trim( $request->system ) : '';

        if ( '' !== $system ) {
            $builder = $builder->using_system_instruction( $system );
        }

        if ( null !== $request->temperature ) {
            $builder = $builder->using_temperature( (float) $request->temperature );
        }

        if ( null !== $request->max_tokens ) {
            $builder = $builder->using_max_tokens( (int) $request->max_tokens );
        }

        if ( $request->json_mode ) {
            $builder = $builder->as_json_response();
        }

        /**
         * Filter the AI Client prompt builder before the request runs.
         *
         * Lets a site pin a provider or model for Joinotify specifically, e.g.
         * `$builder->using_provider( 'google' )`.
         *
         * @since 2.3.0
         * @param \WP_AI_Client_Prompt_Builder $builder Prompt builder instance.
         * @param AI_Request $request Generation request.
         */
        $builder = apply_filters( 'Joinotify/AI/Prompt_Builder', $builder, $request );

        // The builder collects failures and only surfaces them here, as WP_Error.
        $text = $builder->generate_text();

        if ( is_wp_error( $text ) ) {
            return AI_Response::failure( $text );
        }

        return AI_Response::success( (string) $text );
    }
}
