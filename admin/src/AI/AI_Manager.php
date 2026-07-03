<?php

namespace MeuMouse\Joinotify\AI;

use MeuMouse\Joinotify\Admin\Admin;
use MeuMouse\Joinotify\Core\Logger;
use WP_Error;

// Exit if accessed directly.
defined('ABSPATH') || exit;

/**
 * Facade for AI text generation.
 *
 * Picks the active provider from settings, merges per-call overrides with the
 * global defaults (model, temperature, persona/system prompt), delegates to the
 * provider, and logs failures. All capabilities (dynamic messages, smart
 * variables, snippet/flow generation) go through this single entry point.
 *
 * @since 2.0.0
 * @package MeuMouse\Joinotify\AI
 * @author MeuMouse.com
 */
class AI_Manager {

    /**
     * Get the active provider id from settings.
     *
     * @since 2.0.0
     * @return string
     */
    public static function get_active_provider_id() {
        $id = Admin::get_setting('ai_provider');

        return ( is_string( $id ) && '' !== trim( $id ) ) ? trim( $id ) : 'openai';
    }


    /**
     * Resolve the active provider instance.
     *
     * @since 2.0.0
     * @return Provider_Interface|null
     */
    public static function get_active_provider() {
        return Provider_Registry::get_provider( self::get_active_provider_id() );
    }


    /**
     * Provider routing config consumed by the workflow builder.
     *
     * Exposes the default provider plus the list of ready-to-use providers (and
     * their models) so a node can route generation to a specific engine when
     * more than one is configured.
     *
     * @since 2.1.0
     * @return array<string,mixed>
     */
    public static function get_routing_config() {
        return array(
            'default_provider' => self::get_active_provider_id(),
            'providers' => Provider_Registry::get_active_providers(),
        );
    }


    /**
     * Resolve the provider for a request, honoring a valid per-call override.
     *
     * Falls back to the globally active provider when the override is empty or
     * points to a provider that is not configured.
     *
     * @since 2.1.0
     * @param AI_Request $request | Generation request.
     * @return Provider_Interface|null
     */
    protected static function resolve_provider( AI_Request $request ) {
        if ( is_string( $request->provider ) && '' !== trim( $request->provider ) ) {
            $candidate = Provider_Registry::get_provider( trim( $request->provider ) );

            if ( $candidate instanceof Provider_Interface && $candidate->is_configured() ) {
                return $candidate;
            }
        }

        return self::get_active_provider();
    }


    /**
     * Whether AI generation is available (active provider configured).
     *
     * @since 2.0.0
     * @return bool
     */
    public static function is_available() {
        $provider = self::get_active_provider();

        return $provider instanceof Provider_Interface && $provider->is_configured();
    }


    /**
     * Generate text using the active provider.
     *
     * @since 2.0.0
     * @param AI_Request|array<string,mixed> $request | Generation request or args.
     * @return AI_Response
     */
    public static function generate( $request ) {
        if ( is_array( $request ) ) {
            $request = AI_Request::from_array( $request );
        }

        if ( ! $request instanceof AI_Request ) {
            return AI_Response::failure( new WP_Error(
                'joinotify_ai_invalid_request',
                __( 'Invalid AI request.', 'joinotify' )
            ));
        }

        $provider = self::resolve_provider( $request );

        if ( ! $provider instanceof Provider_Interface ) {
            $error = new WP_Error(
                'joinotify_ai_no_provider',
                __( 'No AI provider is available.', 'joinotify' )
            );

            Logger::register_log( 'Joinotify AI: ' . $error->get_error_message(), 'ERROR' );

            return AI_Response::failure( $error );
        }

        // Prepend the global persona/system prompt so every generation inherits it.
        $request->system = self::compose_system_message( $request->system );

        // Apply the global default temperature when the call did not override it.
        if ( null === $request->temperature ) {
            $default_temperature = Admin::get_setting('openai_default_temperature');

            if ( is_numeric( $default_temperature ) ) {
                $request->temperature = (float) $default_temperature;
            }
        }

        /**
         * Filter the AI request right before generation.
         *
         * @since 2.0.0
         * @param AI_Request $request
         * @param Provider_Interface $provider
         */
        $request = apply_filters( 'Joinotify/AI/Request', $request, $provider );

        $response = $provider->generate( $request );

        if ( ! $response->is_successful() ) {
            $error = $response->get_error();
            $message = $error instanceof WP_Error ? $error->get_error_message() : __( 'Unknown AI error.', 'joinotify' );

            Logger::register_log( 'Joinotify AI generation failed: ' . $message, 'ERROR' );
        }

        return $response;
    }


    /**
     * Compose the final system message: global persona + per-call instructions.
     *
     * @since 2.0.0
     * @param string $system | Per-call system message.
     * @return string
     */
    protected static function compose_system_message( $system ) {
        $global = Admin::get_setting('ai_global_system_prompt');
        $global = is_string( $global ) ? trim( $global ) : '';
        $system = is_string( $system ) ? trim( $system ) : '';

        $parts = array_filter( array( $global, $system ), static function( $part ) {
            return '' !== $part;
        });

        return implode( "\n\n", $parts );
    }
}
