<?php

namespace MeuMouse\Joinotify\AI;

use MeuMouse\Joinotify\AI\Providers\OpenAI_Provider;
use MeuMouse\Joinotify\AI\Providers\Anthropic_Provider;

// Exit if accessed directly.
defined('ABSPATH') || exit;

/**
 * Registry/factory for AI providers.
 *
 * Third parties register a new LLM by hooking `Joinotify/AI/Providers` and
 * mapping an id to a class name (or instance) implementing Provider_Interface.
 *
 * @since 2.0.0
 * @package MeuMouse\Joinotify\AI
 * @author MeuMouse.com
 */
class Provider_Registry {

    /**
     * Instantiated providers cache (id => Provider_Interface).
     *
     * @since 2.0.0
     * @var array<string,Provider_Interface>
     */
    protected static $instances = array();

    /**
     * Return the registered provider map (id => class|instance).
     *
     * @since 2.0.0
     * @return array<string,mixed>
     */
    public static function get_providers() {
        $providers = array(
            'openai' => OpenAI_Provider::class,
            'anthropic' => Anthropic_Provider::class,
        );

        /**
         * Filter the registered AI providers.
         *
         * Map an id to a class name or instance implementing Provider_Interface.
         *
         * @since 2.0.0
         * @param array<string,mixed> $providers
         */
        $providers = apply_filters( 'Joinotify/AI/Providers', $providers );

        return is_array( $providers ) ? $providers : array();
    }


    /**
     * Resolve a provider instance by id.
     *
     * @since 2.0.0
     * @param string $id | Provider identifier.
     * @return Provider_Interface|null
     */
    public static function get_provider( $id ) {
        $id = is_string( $id ) ? trim( $id ) : '';

        if ( '' === $id ) {
            return null;
        }

        if ( isset( self::$instances[ $id ] ) ) {
            return self::$instances[ $id ];
        }

        $providers = self::get_providers();

        if ( ! isset( $providers[ $id ] ) ) {
            return null;
        }

        $entry = $providers[ $id ];

        if ( $entry instanceof Provider_Interface ) {
            return self::$instances[ $id ] = $entry;
        }

        if ( is_string( $entry ) && class_exists( $entry ) ) {
            $instance = new $entry();

            if ( $instance instanceof Provider_Interface ) {
                return self::$instances[ $id ] = $instance;
            }
        }

        return null;
    }


    /**
     * Build the provider options for the settings dropdown.
     *
     * @since 2.0.0
     * @return array<int,array{value:string,label:string}>
     */
    public static function get_provider_options() {
        $options = array();

        foreach ( array_keys( self::get_providers() ) as $id ) {
            $provider = self::get_provider( $id );

            if ( ! $provider instanceof Provider_Interface ) {
                continue;
            }

            $options[] = array(
                'value' => $provider->get_id(),
                'label' => $provider->get_label(),
            );
        }

        return $options;
    }


    /**
     * List the providers that are ready to use (credentials configured).
     *
     * Each entry carries the provider id, label and selectable models, so the
     * builder can offer per-node provider routing when more than one engine is
     * available.
     *
     * @since 2.1.0
     * @return array<int,array{value:string,label:string,models:array<int,array{value:string,label:string}>}>
     */
    public static function get_active_providers() {
        $active = array();

        foreach ( array_keys( self::get_providers() ) as $id ) {
            $provider = self::get_provider( $id );

            if ( ! $provider instanceof Provider_Interface || ! $provider->is_configured() ) {
                continue;
            }

            $active[] = array(
                'value' => $provider->get_id(),
                'label' => $provider->get_label(),
                'models' => $provider->get_models(),
            );
        }

        return $active;
    }
}
