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
 * OpenAI provider (Chat Completions API).
 *
 * Reads the API key and global default model from plugin settings. The base
 * URL can be overridden via the OPENAI_BASE_URL constant/env for proxies or
 * Azure/OpenAI-compatible gateways.
 *
 * @since 2.0.0
 * @package MeuMouse\Joinotify\AI\Providers
 * @author MeuMouse.com
 */
class OpenAI_Provider implements Provider_Interface {

    /**
     * Fallback model when none is configured per-call or globally.
     *
     * @since 2.0.0
     * @var string
     */
    const DEFAULT_MODEL = 'gpt-4o-mini';

    /**
     * Default API base URL.
     *
     * @since 2.0.0
     * @var string
     */
    const DEFAULT_BASE_URL = 'https://api.openai.com/v1';

    /**
     * Transient key caching the models fetched from the API.
     *
     * @since 2.0.0
     * @var string
     */
    const MODELS_TRANSIENT = 'joinotify_openai_models';

    /**
     * Transient flag that throttles automatic fetch attempts after a failure.
     *
     * @since 2.0.0
     * @var string
     */
    const MODELS_ATTEMPT_TRANSIENT = 'joinotify_openai_models_attempt';


    /**
     * Provider identifier.
     *
     * @since 2.0.0
     * @return string
     */
    public function get_id() {
        return 'openai';
    }


    /**
     * Provider label.
     *
     * @since 2.0.0
     * @return string
     */
    public function get_label() {
        return 'OpenAI';
    }


    /**
     * Selectable models for the settings dropdown.
     *
     * Returns the list cached from the OpenAI API when available, otherwise a
     * curated fallback. When the cache is empty and the provider is configured,
     * a single throttled fetch is attempted so newly released models appear
     * automatically on the next page load.
     *
     * @since 2.0.0
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
         * Filter the OpenAI models offered in settings.
         *
         * @since 2.0.0
         * @param array<int,array{value:string,label:string}> $models
         */
        return apply_filters( 'Joinotify/AI/OpenAI/Models', $models );
    }


    /**
     * Curated fallback models used when the API list is unavailable.
     *
     * @since 2.0.0
     * @return array<int,array{value:string,label:string}>
     */
    public function get_fallback_models() {
        return array(
            array( 'value' => 'gpt-4o-mini', 'label' => 'GPT-4o mini' ),
            array( 'value' => 'gpt-4o', 'label' => 'GPT-4o' ),
            array( 'value' => 'gpt-4.1-mini', 'label' => 'GPT-4.1 mini' ),
            array( 'value' => 'gpt-4.1', 'label' => 'GPT-4.1' ),
            array( 'value' => 'o4-mini', 'label' => 'o4-mini' ),
        );
    }


    /**
     * Fetch the model list from the OpenAI API and cache it.
     *
     * @since 2.0.0
     * @param bool $force | Bypass the cache and request a fresh list.
     * @return array<int,array{value:string,label:string}>|\WP_Error
     */
    public function fetch_remote_models( $force = false ) {
        if ( ! $this->is_configured() ) {
            return new WP_Error(
                'joinotify_openai_not_configured',
                __( 'OpenAI API key is not configured.', 'joinotify' )
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

        $response = wp_remote_get( $this->get_base_url() . '/models', array(
            'headers' => array(
                'Authorization' => 'Bearer ' . $this->get_api_key(),
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
                $error_message = sprintf( __( 'OpenAI request failed with status %d.', 'joinotify' ), $status );
            }

            return new WP_Error( 'joinotify_openai_http_error', $error_message, array( 'status' => $status ) );
        }

        $list = ( is_array( $data ) && isset( $data['data'] ) && is_array( $data['data'] ) ) ? $data['data'] : array();
        $models = $this->normalize_models( $list );

        if ( empty( $models ) ) {
            return new WP_Error(
                'joinotify_openai_empty_models',
                __( 'OpenAI did not return any compatible models.', 'joinotify' )
            );
        }

        set_transient( self::MODELS_TRANSIENT, $models, 12 * HOUR_IN_SECONDS );

        return $models;
    }


    /**
     * Filter and format the raw model list returned by the API.
     *
     * Keeps only chat-capable text models (GPT and o-series), dropping audio,
     * image, embedding, moderation and instruct variants.
     *
     * @since 2.0.0
     * @param array<int,array<string,mixed>> $list | Raw `data` entries from the API.
     * @return array<int,array{value:string,label:string}>
     */
    protected function normalize_models( $list ) {
        $exclude = array( 'instruct', 'audio', 'realtime', 'transcribe', 'tts', 'moderation', 'embedding', 'whisper', 'dall-e', 'image', 'search' );
        $models = array();

        foreach ( $list as $entry ) {
            $id = '';

            if ( is_array( $entry ) && isset( $entry['id'] ) ) {
                $id = (string) $entry['id'];
            } elseif ( is_string( $entry ) ) {
                $id = $entry;
            }

            $id = trim( $id );

            if ( '' === $id ) {
                continue;
            }

            // Keep GPT models and o-series reasoning models only.
            if ( 0 !== strpos( $id, 'gpt' ) && ! preg_match( '/^o\d/', $id ) ) {
                continue;
            }

            $skip = false;

            foreach ( $exclude as $needle ) {
                if ( false !== strpos( $id, $needle ) ) {
                    $skip = true;
                    break;
                }
            }

            if ( $skip ) {
                continue;
            }

            $models[ $id ] = array(
                'value' => $id,
                'label' => $this->format_model_label( $id ),
            );
        }

        ksort( $models );

        return array_values( $models );
    }


    /**
     * Build a friendly label from a model id.
     *
     * @since 2.0.0
     * @param string $id | Model identifier (e.g. gpt-4o-mini).
     * @return string
     */
    protected function format_model_label( $id ) {
        if ( 0 === strpos( $id, 'gpt' ) ) {
            return 'GPT' . substr( $id, 3 );
        }

        return $id;
    }


    /**
     * Ensure the currently saved default model is present in the option list.
     *
     * @since 2.0.0
     * @param array<int,array{value:string,label:string}> $models
     * @return array<int,array{value:string,label:string}>
     */
    protected function ensure_selected_model( $models ) {
        $selected = Admin::get_setting('openai_default_model');
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
     * @since 2.0.0
     * @return bool
     */
    public function is_configured() {
        return '' !== trim( (string) $this->get_api_key() );
    }


    /**
     * Read the configured API key.
     *
     * @since 2.0.0
     * @return string
     */
    public function get_api_key() {
        $key = Admin::get_setting('openai_api_key');

        return is_string( $key ) ? trim( $key ) : '';
    }


    /**
     * Resolve the API base URL.
     *
     * @since 2.0.0
     * @return string
     */
    public function get_base_url() {
        $base = '';

        if ( defined('JOINOTIFY_OPENAI_BASE_URL') && JOINOTIFY_OPENAI_BASE_URL ) {
            $base = (string) JOINOTIFY_OPENAI_BASE_URL;
        } elseif ( getenv('OPENAI_BASE_URL') ) {
            $base = (string) getenv('OPENAI_BASE_URL');
        }

        if ( '' === trim( $base ) ) {
            $base = self::DEFAULT_BASE_URL;
        }

        /**
         * Filter the OpenAI API base URL.
         *
         * @since 2.0.0
         * @param string $base
         */
        return untrailingslashit( apply_filters( 'Joinotify/AI/OpenAI/Base_URL', $base ) );
    }


    /**
     * Generate a completion.
     *
     * @since 2.0.0
     * @param AI_Request $request | Generation request.
     * @return AI_Response
     */
    public function generate( AI_Request $request ) {
        if ( ! $this->is_configured() ) {
            return AI_Response::failure( new WP_Error(
                'joinotify_openai_not_configured',
                __( 'OpenAI API key is not configured.', 'joinotify' )
            ));
        }

        $model = $request->model;

        if ( empty( $model ) ) {
            $default = Admin::get_setting('openai_default_model');
            $model = ( is_string( $default ) && '' !== trim( $default ) ) ? $default : self::DEFAULT_MODEL;
        }

        $messages = array();

        if ( '' !== trim( $request->system ) ) {
            $messages[] = array( 'role' => 'system', 'content' => $request->system );
        }

        $messages[] = array( 'role' => 'user', 'content' => $request->prompt );

        $body = array(
            'model' => $model,
            'messages' => $messages,
        );

        if ( null !== $request->temperature ) {
            $body['temperature'] = $request->temperature;
        }

        if ( null !== $request->max_tokens ) {
            $body['max_tokens'] = $request->max_tokens;
        }

        if ( $request->json_mode ) {
            $body['response_format'] = array( 'type' => 'json_object' );
        }

        /**
         * Filter the OpenAI request body before sending.
         *
         * @since 2.0.0
         * @param array<string,mixed> $body
         * @param AI_Request $request
         */
        $body = apply_filters( 'Joinotify/AI/OpenAI/Request_Body', $body, $request );

        $response = wp_remote_post( $this->get_base_url() . '/chat/completions', array(
            'headers' => array(
                'Content-Type' => 'application/json',
                'Authorization' => 'Bearer ' . $this->get_api_key(),
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
                $error_message = sprintf( __( 'OpenAI request failed with status %d.', 'joinotify' ), $status );
            }

            return AI_Response::failure( new WP_Error( 'joinotify_openai_http_error', $error_message, array( 'status' => $status ) ) );
        }

        $text = '';

        if ( is_array( $data ) && isset( $data['choices'][0]['message']['content'] ) ) {
            $text = (string) $data['choices'][0]['message']['content'];
        }

        if ( '' === trim( $text ) ) {
            return AI_Response::failure( new WP_Error(
                'joinotify_openai_empty_response',
                __( 'OpenAI returned an empty response.', 'joinotify' )
            ));
        }

        $usage = ( is_array( $data ) && isset( $data['usage'] ) && is_array( $data['usage'] ) ) ? $data['usage'] : array();

        return AI_Response::success( $text, is_array( $data ) ? $data : array(), $usage );
    }
}
