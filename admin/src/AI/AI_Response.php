<?php

namespace MeuMouse\Joinotify\AI;

use WP_Error;

// Exit if accessed directly.
defined('ABSPATH') || exit;

/**
 * Provider-agnostic AI generation response.
 *
 * Wraps either the generated text (success) or a WP_Error (failure), plus the
 * raw provider payload and token usage for logging.
 *
 * @since 2.0.0
 * @package MeuMouse\Joinotify\AI
 * @author MeuMouse.com
 */
class AI_Response {

    /**
     * Generated text content.
     *
     * @since 2.0.0
     * @var string
     */
    protected $text = '';

    /**
     * Raw provider response payload.
     *
     * @since 2.0.0
     * @var array<string,mixed>
     */
    protected $raw = array();

    /**
     * Token usage details (provider-specific).
     *
     * @since 2.0.0
     * @var array<string,mixed>
     */
    protected $usage = array();

    /**
     * Error instance when the generation failed.
     *
     * @since 2.0.0
     * @var WP_Error|null
     */
    protected $error = null;

    /**
     * Build a successful response.
     *
     * @since 2.0.0
     * @param string $text | Generated text.
     * @param array<string,mixed> $raw | Raw provider payload.
     * @param array<string,mixed> $usage | Token usage details.
     * @return self
     */
    public static function success( $text, $raw = array(), $usage = array() ) {
        $response = new self();
        $response->text = (string) $text;
        $response->raw = is_array( $raw ) ? $raw : array();
        $response->usage = is_array( $usage ) ? $usage : array();

        return $response;
    }


    /**
     * Build a failed response.
     *
     * @since 2.0.0
     * @param WP_Error $error | Error instance.
     * @return self
     */
    public static function failure( WP_Error $error ) {
        $response = new self();
        $response->error = $error;

        return $response;
    }


    /**
     * Whether the generation succeeded.
     *
     * @since 2.0.0
     * @return bool
     */
    public function is_successful() {
        return ! ( $this->error instanceof WP_Error ) && '' !== $this->text;
    }


    /**
     * Get the generated text.
     *
     * @since 2.0.0
     * @return string
     */
    public function get_text() {
        return $this->text;
    }


    /**
     * Get the error instance (or null on success).
     *
     * @since 2.0.0
     * @return WP_Error|null
     */
    public function get_error() {
        return $this->error;
    }


    /**
     * Get the raw provider payload.
     *
     * @since 2.0.0
     * @return array<string,mixed>
     */
    public function get_raw() {
        return $this->raw;
    }


    /**
     * Get the token usage details.
     *
     * @since 2.0.0
     * @return array<string,mixed>
     */
    public function get_usage() {
        return $this->usage;
    }
}
