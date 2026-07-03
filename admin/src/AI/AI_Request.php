<?php

namespace MeuMouse\Joinotify\AI;

// Exit if accessed directly.
defined('ABSPATH') || exit;

/**
 * Provider-agnostic AI generation request.
 *
 * Value object passed to any LLM provider. Per-call overrides (model,
 * temperature, max_tokens) are optional; null means "use the global default".
 *
 * @since 2.0.0
 * @package MeuMouse\Joinotify\AI
 * @author MeuMouse.com
 */
class AI_Request {

    /**
     * System message / instructions (persona, rules, tone).
     *
     * @since 2.0.0
     * @var string
     */
    public $system = '';

    /**
     * User prompt with the resolved content/context.
     *
     * @since 2.0.0
     * @var string
     */
    public $prompt = '';

    /**
     * Model override (null = global default).
     *
     * @since 2.0.0
     * @var string|null
     */
    public $model = null;

    /**
     * Temperature override (null = global default).
     *
     * @since 2.0.0
     * @var float|null
     */
    public $temperature = null;

    /**
     * Max tokens override (null = provider default).
     *
     * @since 2.0.0
     * @var int|null
     */
    public $max_tokens = null;

    /**
     * Whether the response must be a JSON object.
     *
     * @since 2.0.0
     * @var bool
     */
    public $json_mode = false;

    /**
     * Free-form metadata for logging/telemetry (e.g. intent, workflow id).
     *
     * @since 2.0.0
     * @var array<string,mixed>
     */
    public $context = array();

    /**
     * Construct function.
     *
     * @since 2.0.0
     * @param array<string,mixed> $args | Request properties.
     * @return void
     */
    public function __construct( $args = array() ) {
        if ( ! is_array( $args ) ) {
            return;
        }

        if ( isset( $args['system'] ) ) {
            $this->system = (string) $args['system'];
        }

        if ( isset( $args['prompt'] ) ) {
            $this->prompt = (string) $args['prompt'];
        }

        if ( isset( $args['model'] ) && '' !== trim( (string) $args['model'] ) ) {
            $this->model = (string) $args['model'];
        }

        if ( isset( $args['temperature'] ) && is_numeric( $args['temperature'] ) ) {
            $this->temperature = (float) $args['temperature'];
        }

        if ( isset( $args['max_tokens'] ) && is_numeric( $args['max_tokens'] ) ) {
            $this->max_tokens = (int) $args['max_tokens'];
        }

        if ( isset( $args['json_mode'] ) ) {
            $this->json_mode = (bool) $args['json_mode'];
        }

        if ( isset( $args['context'] ) && is_array( $args['context'] ) ) {
            $this->context = $args['context'];
        }
    }


    /**
     * Build a request from an associative array.
     *
     * @since 2.0.0
     * @param array<string,mixed> $args | Request properties.
     * @return self
     */
    public static function from_array( $args ) {
        return new self( $args );
    }
}
