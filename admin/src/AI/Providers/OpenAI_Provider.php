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
     * @since 2.0.0
     * @return array<int,array{value:string,label:string}>
     */
    public function get_models() {
        $models = array(
            array( 'value' => 'gpt-4o-mini', 'label' => 'GPT-4o mini' ),
            array( 'value' => 'gpt-4o', 'label' => 'GPT-4o' ),
            array( 'value' => 'gpt-4.1-mini', 'label' => 'GPT-4.1 mini' ),
            array( 'value' => 'gpt-4.1', 'label' => 'GPT-4.1' ),
            array( 'value' => 'o4-mini', 'label' => 'o4-mini' ),
        );

        /**
         * Filter the OpenAI models offered in settings.
         *
         * @since 2.0.0
         * @param array<int,array{value:string,label:string}> $models
         */
        return apply_filters( 'Joinotify/AI/OpenAI/Models', $models );
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
