<?php

namespace MeuMouse\Joinotify\AI\Providers;

use MeuMouse\Joinotify\AI\Provider_Interface;
use MeuMouse\Joinotify\AI\AI_Request;
use MeuMouse\Joinotify\AI\AI_Response;
use MeuMouse\Joinotify\Admin\Admin;
use WP_Error;

// Exit if accessed directly.
defined('ABSPATH') || exit;

/**
 * Anthropic provider (Claude Messages API).
 *
 * Reads the API key and global default model from plugin settings. The base
 * URL can be overridden via the JOINOTIFY_ANTHROPIC_BASE_URL constant or the
 * ANTHROPIC_BASE_URL env for proxies and Anthropic-compatible gateways.
 *
 * @since 2.1.0
 * @package MeuMouse\Joinotify\AI\Providers
 * @author MeuMouse.com
 */
class Anthropic_Provider implements Provider_Interface {

    /**
     * Fallback model when none is configured per-call or globally.
     *
     * @since 2.1.0
     * @var string
     */
    const DEFAULT_MODEL = 'claude-haiku-4-5';

    /**
     * Default API base URL.
     *
     * @since 2.1.0
     * @var string
     */
    const DEFAULT_BASE_URL = 'https://api.anthropic.com/v1';

    /**
     * API version header required by the Anthropic Messages API.
     *
     * @since 2.1.0
     * @var string
     */
    const API_VERSION = '2023-06-01';

    /**
     * Default max output tokens (required by the Messages API).
     *
     * @since 2.1.0
     * @var int
     */
    const DEFAULT_MAX_TOKENS = 2048;

    /**
     * Transient key caching the models fetched from the API.
     *
     * @since 2.1.0
     * @var string
     */
    const MODELS_TRANSIENT = 'joinotify_anthropic_models';

    /**
     * Transient flag that throttles automatic fetch attempts after a failure.
     *
     * @since 2.1.0
     * @var string
     */
    const MODELS_ATTEMPT_TRANSIENT = 'joinotify_anthropic_models_attempt';


    /**
     * Provider identifier.
     *
     * @since 2.1.0
     * @return string
     */
    public function get_id() {
        return 'anthropic';
    }


    /**
     * Provider label.
     *
     * @since 2.1.0
     * @return string
     */
    public function get_label() {
        return 'Anthropic (Claude)';
    }


    /**
     * Selectable models for the settings dropdown.
     *
     * Returns the list cached from the Anthropic API when available, otherwise a
     * curated fallback. When the cache is empty and the provider is configured,
     * a single throttled fetch is attempted so newly released models appear
     * automatically on the next page load.
     *
     * @since 2.1.0
     * @return array<int,array{value:string,label:string}>
     */
    public function get_models() {
        $cached = get_transient( self::MODELS_TRANSIENT );

        if ( is_array( $cached ) && ! empty( $cached ) ) {
            $models = $cached;
        } else {
            $models = $this->get_fallback_models();

            // Lazily warm the cache, throttled to avoid repeated calls on failure.
            if ( $this->is_configured() && false === get_transient( self::MODELS_ATTEMPT_TRANSIENT ) ) {
                $remote = $this->fetch_remote_models();

                if ( ! is_wp_error( $remote ) && ! empty( $remote ) ) {
                    $models = $remote;
                }
            }
        }

        $models = $this->ensure_selected_model( $models );

        /**
         * Filter the Anthropic models offered in settings.
         *
         * @since 2.1.0
         * @param array<int,array{value:string,label:string}> $models
         */
        return apply_filters( 'Joinotify/AI/Anthropic/Models', $models );
    }


    /**
     * Curated fallback models used when the API list is unavailable.
     *
     * @since 2.1.0
     * @return array<int,array{value:string,label:string}>
     */
    public function get_fallback_models() {
        return array(
            array( 'value' => 'claude-haiku-4-5', 'label' => 'Claude Haiku 4.5' ),
            array( 'value' => 'claude-sonnet-4-6', 'label' => 'Claude Sonnet 4.6' ),
            array( 'value' => 'claude-sonnet-5', 'label' => 'Claude Sonnet 5' ),
            array( 'value' => 'claude-opus-4-8', 'label' => 'Claude Opus 4.8' ),
        );
    }


    /**
     * Fetch the model list from the Anthropic API and cache it.
     *
     * @since 2.1.0
     * @param bool $force | Bypass the cache and request a fresh list.
     * @return array<int,array{value:string,label:string}>|\WP_Error
     */
    public function fetch_remote_models( $force = false ) {
        if ( ! $this->is_configured() ) {
            return new WP_Error(
                'joinotify_anthropic_not_configured',
                __( 'Anthropic API key is not configured.', 'joinotify' )
            );
        }

        if ( ! $force ) {
            $cached = get_transient( self::MODELS_TRANSIENT );

            if ( is_array( $cached ) && ! empty( $cached ) ) {
                return $cached;
            }
        }

        // Throttle automatic retries even when the request below fails.
        set_transient( self::MODELS_ATTEMPT_TRANSIENT, 1, HOUR_IN_SECONDS );

        $response = wp_remote_get( $this->get_base_url() . '/models?limit=100', array(
            'headers' => array(
                'x-api-key' => $this->get_api_key(),
                'anthropic-version' => self::API_VERSION,
            ),
            'timeout' => 20,
        ));

        if ( is_wp_error( $response ) ) {
            return $response;
        }

        $status = (int) wp_remote_retrieve_response_code( $response );
        $data = json_decode( wp_remote_retrieve_body( $response ), true );

        if ( $status < 200 || $status >= 300 ) {
            $error_message = '';

            if ( is_array( $data ) && isset( $data['error']['message'] ) ) {
                $error_message = (string) $data['error']['message'];
            }

            if ( '' === $error_message ) {
                /* translators: %d: HTTP status code. */
                $error_message = sprintf( __( 'Anthropic request failed with status %d.', 'joinotify' ), $status );
            }

            return new WP_Error( 'joinotify_anthropic_http_error', $error_message, array( 'status' => $status ) );
        }

        $list = ( is_array( $data ) && isset( $data['data'] ) && is_array( $data['data'] ) ) ? $data['data'] : array();
        $models = $this->normalize_models( $list );

        if ( empty( $models ) ) {
            return new WP_Error(
                'joinotify_anthropic_empty_models',
                __( 'Anthropic did not return any compatible models.', 'joinotify' )
            );
        }

        set_transient( self::MODELS_TRANSIENT, $models, 12 * HOUR_IN_SECONDS );

        return $models;
    }


    /**
     * Filter and format the raw model list returned by the API.
     *
     * Keeps only Claude text models, using the friendly `display_name` returned
     * by the API when present.
     *
     * @since 2.1.0
     * @param array<int,array<string,mixed>> $list | Raw `data` entries from the API.
     * @return array<int,array{value:string,label:string}>
     */
    protected function normalize_models( $list ) {
        $models = array();

        foreach ( $list as $entry ) {
            $id = '';
            $label = '';

            if ( is_array( $entry ) ) {
                $id = isset( $entry['id'] ) ? (string) $entry['id'] : '';
                $label = isset( $entry['display_name'] ) ? (string) $entry['display_name'] : '';
            } elseif ( is_string( $entry ) ) {
                $id = $entry;
            }

            $id = trim( $id );

            if ( '' === $id || 0 !== strpos( $id, 'claude' ) ) {
                continue;
            }

            $models[ $id ] = array(
                'value' => $id,
                'label' => '' !== trim( $label ) ? $label : $this->format_model_label( $id ),
            );
        }

        krsort( $models );

        return array_values( $models );
    }


    /**
     * Build a friendly label from a model id.
     *
     * @since 2.1.0
     * @param string $id | Model identifier (e.g. claude-haiku-4-5).
     * @return string
     */
    protected function format_model_label( $id ) {
        $label = str_replace( '-', ' ', $id );

        return ucwords( $label );
    }


    /**
     * Ensure the currently saved default model is present in the option list.
     *
     * @since 2.1.0
     * @param array<int,array{value:string,label:string}> $models
     * @return array<int,array{value:string,label:string}>
     */
    protected function ensure_selected_model( $models ) {
        $selected = Admin::get_setting('anthropic_default_model');
        $selected = is_string( $selected ) ? trim( $selected ) : '';

        if ( '' === $selected ) {
            return $models;
        }

        foreach ( $models as $model ) {
            if ( isset( $model['value'] ) && (string) $model['value'] === $selected ) {
                return $models;
            }
        }

        array_unshift( $models, array(
            'value' => $selected,
            'label' => $this->format_model_label( $selected ),
        ));

        return $models;
    }


    /**
     * Whether the API key is set.
     *
     * @since 2.1.0
     * @return bool
     */
    public function is_configured() {
        return '' !== trim( (string) $this->get_api_key() );
    }


    /**
     * Read the configured API key.
     *
     * @since 2.1.0
     * @return string
     */
    public function get_api_key() {
        $key = Admin::get_setting('anthropic_api_key');

        return is_string( $key ) ? trim( $key ) : '';
    }


    /**
     * Resolve the API base URL.
     *
     * @since 2.1.0
     * @return string
     */
    public function get_base_url() {
        $base = '';

        if ( defined('JOINOTIFY_ANTHROPIC_BASE_URL') && JOINOTIFY_ANTHROPIC_BASE_URL ) {
            $base = (string) JOINOTIFY_ANTHROPIC_BASE_URL;
        } elseif ( getenv('ANTHROPIC_BASE_URL') ) {
            $base = (string) getenv('ANTHROPIC_BASE_URL');
        }

        if ( '' === trim( $base ) ) {
            $base = self::DEFAULT_BASE_URL;
        }

        /**
         * Filter the Anthropic API base URL.
         *
         * @since 2.1.0
         * @param string $base
         */
        return untrailingslashit( apply_filters( 'Joinotify/AI/Anthropic/Base_URL', $base ) );
    }


    /**
     * Whether the given model accepts sampling parameters (e.g. temperature).
     *
     * Newer Claude families (Opus 4.7/4.8, Sonnet 5, Fable/Mythos 5) reject
     * `temperature` with a 400, so it must be omitted for those models.
     *
     * @since 2.1.0
     * @param string $model | Model identifier.
     * @return bool
     */
    protected function model_supports_temperature( $model ) {
        $model = strtolower( (string) $model );

        $unsupported = array(
            'claude-opus-4-7',
            'claude-opus-4-8',
            'claude-sonnet-5',
            'claude-fable-5',
            'claude-mythos',
        );

        $supported = true;

        foreach ( $unsupported as $needle ) {
            if ( false !== strpos( $model, $needle ) ) {
                $supported = false;
                break;
            }
        }

        /**
         * Filter whether a model accepts the temperature sampling parameter.
         *
         * @since 2.1.0
         * @param bool $supported
         * @param string $model
         */
        return (bool) apply_filters( 'Joinotify/AI/Anthropic/Model_Supports_Temperature', $supported, $model );
    }


    /**
     * Generate a completion.
     *
     * @since 2.1.0
     * @param AI_Request $request | Generation request.
     * @return AI_Response
     */
    public function generate( AI_Request $request ) {
        if ( ! $this->is_configured() ) {
            return AI_Response::failure( new WP_Error(
                'joinotify_anthropic_not_configured',
                __( 'Anthropic API key is not configured.', 'joinotify' )
            ));
        }

        $model = $request->model;

        if ( empty( $model ) ) {
            $default = Admin::get_setting('anthropic_default_model');
            $model = ( is_string( $default ) && '' !== trim( $default ) ) ? $default : self::DEFAULT_MODEL;
        }

        // The Messages API requires max_tokens; fall back to a sane default.
        $max_tokens = ( null !== $request->max_tokens ) ? (int) $request->max_tokens : self::DEFAULT_MAX_TOKENS;

        // Anthropic takes the persona as a top-level `system` string, not a message.
        $system = trim( $request->system );

        // Anthropic has no native JSON mode; steer it through the system prompt.
        if ( $request->json_mode ) {
            $json_instruction = __( 'Respond with a single valid JSON object and nothing else. Do not wrap it in markdown code fences.', 'joinotify' );
            $system = '' !== $system ? $system . "\n\n" . $json_instruction : $json_instruction;
        }

        $body = array(
            'model' => $model,
            'max_tokens' => $max_tokens,
            'messages' => array(
                array( 'role' => 'user', 'content' => $request->prompt ),
            ),
        );

        if ( '' !== $system ) {
            $body['system'] = $system;
        }

        // Only send temperature for models that accept it, clamped to [0, 1].
        if ( null !== $request->temperature && $this->model_supports_temperature( $model ) ) {
            $body['temperature'] = max( 0.0, min( 1.0, (float) $request->temperature ) );
        }

        /**
         * Filter the Anthropic request body before sending.
         *
         * @since 2.1.0
         * @param array<string,mixed> $body
         * @param AI_Request $request
         */
        $body = apply_filters( 'Joinotify/AI/Anthropic/Request_Body', $body, $request );

        $response = wp_remote_post( $this->get_base_url() . '/messages', array(
            'headers' => array(
                'Content-Type' => 'application/json',
                'x-api-key' => $this->get_api_key(),
                'anthropic-version' => self::API_VERSION,
            ),
            'body' => wp_json_encode( $body ),
            'timeout' => 60,
        ));

        if ( is_wp_error( $response ) ) {
            return AI_Response::failure( $response );
        }

        $status = (int) wp_remote_retrieve_response_code( $response );
        $data = json_decode( wp_remote_retrieve_body( $response ), true );

        if ( $status < 200 || $status >= 300 ) {
            $error_message = '';

            if ( is_array( $data ) && isset( $data['error']['message'] ) ) {
                $error_message = (string) $data['error']['message'];
            }

            if ( '' === $error_message ) {
                /* translators: %d: HTTP status code. */
                $error_message = sprintf( __( 'Anthropic request failed with status %d.', 'joinotify' ), $status );
            }

            return AI_Response::failure( new WP_Error( 'joinotify_anthropic_http_error', $error_message, array( 'status' => $status ) ) );
        }

        $text = $this->extract_text( $data );

        if ( '' === trim( $text ) ) {
            return AI_Response::failure( new WP_Error(
                'joinotify_anthropic_empty_response',
                __( 'Anthropic returned an empty response.', 'joinotify' )
            ));
        }

        $usage = ( is_array( $data ) && isset( $data['usage'] ) && is_array( $data['usage'] ) ) ? $data['usage'] : array();

        return AI_Response::success( $text, is_array( $data ) ? $data : array(), $usage );
    }


    /**
     * Concatenate the text blocks from a Messages API response.
     *
     * @since 2.1.0
     * @param mixed $data | Decoded response payload.
     * @return string
     */
    protected function extract_text( $data ) {
        if ( ! is_array( $data ) || ! isset( $data['content'] ) || ! is_array( $data['content'] ) ) {
            return '';
        }

        $parts = array();

        foreach ( $data['content'] as $block ) {
            if ( is_array( $block ) && isset( $block['type'], $block['text'] ) && 'text' === $block['type'] ) {
                $parts[] = (string) $block['text'];
            }
        }

        return implode( '', $parts );
    }
}
