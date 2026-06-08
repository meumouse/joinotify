<?php

namespace MeuMouse\Joinotify\AI;

// Exit if accessed directly.
defined('ABSPATH') || exit;

/**
 * Generate a PHP snippet for the snippet_php workflow action from a brief.
 *
 * Builder-time authoring assist: returns raw PHP suitable for the Joinotify
 * runtime, avoiding the same functions the runtime blocks at execution time.
 *
 * @since 2.0.0
 * @package MeuMouse\Joinotify\AI
 * @author MeuMouse.com
 */
class Snippet_Generator {

    /**
     * Functions blocked by the snippet_php runtime (kept in sync with Workflow_Processor).
     *
     * @since 2.0.0
     * @var array<int,string>
     */
    const BLOCKED_FUNCTIONS = array( 'exec', 'shell_exec', 'system', 'passthru', 'proc_open', 'eval', 'popen', 'dl', 'file_get_contents' );


    /**
     * Generate a PHP snippet from instructions.
     *
     * @since 2.0.0
     * @param string $instructions | What the snippet should do.
     * @param string $system | Optional extra instructions/context.
     * @return array<string,mixed> { status, code?, message? }
     */
    public static function generate( $instructions, $system = '' ) {
        $instructions = is_string( $instructions ) ? trim( $instructions ) : '';

        if ( '' === $instructions ) {
            return array(
                'status' => 'error',
                'message' => esc_html__( 'Describe what the snippet should do.', 'joinotify' ),
            );
        }

        if ( ! AI_Manager::is_available() ) {
            return array(
                'status' => 'error',
                'message' => esc_html__( 'No AI provider is configured. Set it up in Settings → Integrations.', 'joinotify' ),
            );
        }

        $response = AI_Manager::generate( new AI_Request( array(
            'system' => self::build_system_prompt( $system ),
            'prompt' => $instructions,
            'context' => array( 'intent' => 'snippet_generation' ),
        )));

        if ( ! $response->is_successful() ) {
            $error = $response->get_error();

            return array(
                'status' => 'error',
                'message' => $error ? $error->get_error_message() : esc_html__( 'The AI could not generate the snippet.', 'joinotify' ),
            );
        }

        $code = self::clean_code( $response->get_text() );

        if ( '' === $code ) {
            return array(
                'status' => 'error',
                'message' => esc_html__( 'The AI returned an empty snippet. Please try again.', 'joinotify' ),
            );
        }

        $blocked = self::find_blocked_function( $code );

        if ( null !== $blocked ) {
            return array(
                'status' => 'error',
                /* translators: %s: PHP function name. */
                'message' => sprintf( esc_html__( 'The generated snippet uses a blocked function (%s). Please refine the request.', 'joinotify' ), $blocked ),
            );
        }

        return array(
            'status' => 'success',
            'code' => $code,
        );
    }


    /**
     * Build the snippet system prompt.
     *
     * @since 2.0.0
     * @param string $extra_system | Optional extra instructions.
     * @return string
     */
    protected static function build_system_prompt( $extra_system = '' ) {
        $lines = array();
        $lines[] = 'You write a single PHP snippet that runs inside the Joinotify WordPress plugin workflow runtime.';
        $lines[] = 'A variable $payload (associative array) holds the trigger context (e.g. order_id, customer/user data, form fields).';
        $lines[] = 'WordPress and the active plugins are loaded, so WordPress functions are available.';
        $lines[] = 'NEVER use any of these blocked functions: ' . implode( ', ', self::BLOCKED_FUNCTIONS ) . '.';
        $lines[] = 'Keep the code self-contained, deterministic, and safe.';
        $lines[] = 'Return ONLY the raw PHP code. Do not include the <?php tag, explanations, or markdown code fences.';

        $extra_system = is_string( $extra_system ) ? trim( $extra_system ) : '';

        if ( '' !== $extra_system ) {
            $lines[] = '';
            $lines[] = 'ADDITIONAL INSTRUCTIONS:';
            $lines[] = $extra_system;
        }

        return implode( "\n", $lines );
    }


    /**
     * Strip markdown fences and the opening PHP tag from the AI output.
     *
     * @since 2.0.0
     * @param string $text
     * @return string
     */
    protected static function clean_code( $text ) {
        $text = is_string( $text ) ? trim( $text ) : '';

        // remove ```php ... ``` or ``` ... ``` fences
        $text = preg_replace( '/^```[a-zA-Z]*\s*/', '', $text );
        $text = preg_replace( '/```\s*$/', '', $text );

        // remove a leading <?php tag (the runtime strips it too)
        $text = preg_replace( '/^\s*<\?php\s*/', '', (string) $text );

        return trim( (string) $text );
    }


    /**
     * Return the first blocked function used in the code, or null.
     *
     * @since 2.0.0
     * @param string $code
     * @return string|null
     */
    protected static function find_blocked_function( $code ) {
        foreach ( self::BLOCKED_FUNCTIONS as $function ) {
            if ( stripos( $code, $function . '(' ) !== false ) {
                return $function;
            }
        }

        return null;
    }
}
