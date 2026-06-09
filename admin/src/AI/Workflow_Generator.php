<?php

namespace MeuMouse\Joinotify\AI;

use MeuMouse\Joinotify\Admin\Builder\Registry;

// Exit if accessed directly.
defined('ABSPATH') || exit;

/**
 * Generate a full workflow from a natural-language brief using the active AI provider.
 *
 * Builds a system prompt embedding the workflow_content JSON contract plus the
 * available triggers/actions catalogs, asks the provider for a JSON object, then
 * sanitizes the result through the same import sanitizer used by the builder.
 * Canvas positions/connections are intentionally omitted — the builder canvas
 * auto-lays-out and auto-connects nodes that lack them.
 *
 * @since 2.0.0
 * @package MeuMouse\Joinotify\AI
 * @author MeuMouse.com
 */
class Workflow_Generator {

    /**
     * Generate a workflow from instructions.
     *
     * @since 2.0.0
     * @param string $instructions | Natural-language description of the desired automation.
     * @param string $system | Optional extra system instructions/documentation.
     * @param string $context | Optional preferred trigger context (integration slug).
     * @return array<string,mixed> { status, message?, workflow_content?, title?, category? }
     */
    public static function generate( $instructions, $system = '', $context = '' ) {
        $instructions = is_string( $instructions ) ? trim( $instructions ) : '';

        if ( '' === $instructions ) {
            return array(
                'status' => 'error',
                'message' => __( 'Describe the automation you want to generate.', 'joinotify' ),
            );
        }

        if ( ! AI_Manager::is_available() ) {
            return array(
                'status' => 'error',
                'message' => __( 'No AI provider is configured. Set it up in Settings → Integrations.', 'joinotify' ),
            );
        }

        $response = AI_Manager::generate( new AI_Request( array(
            'system' => self::build_system_prompt( $system, $context ),
            'prompt' => $instructions,
            'json_mode' => true,
            'context' => array( 'intent' => 'workflow_generation' ),
        )));

        if ( ! $response->is_successful() ) {
            $error = $response->get_error();

            return array(
                'status' => 'error',
                'message' => $error ? $error->get_error_message() : esc_html__( 'The AI could not generate the workflow.', 'joinotify' ),
            );
        }

        $decoded = json_decode( $response->get_text(), true );

        if ( ! is_array( $decoded ) ) {
            return array(
                'status' => 'error',
                'message' => __( 'The AI returned an invalid workflow. Please try again.', 'joinotify' ),
            );
        }

        $raw_content = $decoded['workflow_content'] ?? ( $decoded['workflow'] ?? ( $decoded['nodes'] ?? array() ) );

        if ( ! is_array( $raw_content ) || empty( $raw_content ) ) {
            return array(
                'status' => 'error',
                'message' => __( 'The AI did not produce any workflow steps. Try refining the description.', 'joinotify' ),
            );
        }

        // run through the same sanitizer the import path uses
        $content = Registry::sanitize_workflow_content( $raw_content );

        if ( empty( $content ) || ! self::has_trigger_node( $content ) ) {
            return array(
                'status' => 'error',
                'message' => __( 'The generated workflow is missing a valid trigger. Try again with more detail.', 'joinotify' ),
            );
        }

        $title = isset( $decoded['title'] ) ? sanitize_text_field( (string) $decoded['title'] ) : '';
        $category = isset( $decoded['category'] ) ? sanitize_key( (string) $decoded['category'] ) : self::resolve_category( $content );

        return array(
            'status' => 'success',
            'title' => $title ?: esc_html__( 'AI workflow', 'joinotify' ),
            'category' => $category,
            'workflow_content' => $content,
        );
    }


    /**
     * Build the system prompt with the workflow contract and catalogs.
     *
     * @since 2.0.0
     * @param string $extra_system | Optional extra instructions/documentation.
     * @param string $context | Optional preferred trigger context.
     * @return string
     */
    protected static function build_system_prompt( $extra_system = '', $context = '' ) {
        $triggers = self::build_triggers_reference( $context );
        $actions = self::build_actions_reference();

        $lines = array();
        $lines[] = 'You are an automation builder for the Joinotify WhatsApp automation plugin.';
        $lines[] = 'Given a natural-language description, output ONE workflow as a JSON object.';
        $lines[] = '';
        $lines[] = 'Respond with a JSON object with exactly these keys:';
        $lines[] = '- "title": a short title for the workflow.';
        $lines[] = '- "category": the trigger context slug (e.g. "woocommerce", "wordpress").';
        $lines[] = '- "workflow_content": an array of nodes.';
        $lines[] = '';
        $lines[] = 'Node shape: { "type": "trigger"|"action", "data": { ... }, "children": [] }.';
        $lines[] = 'The FIRST node MUST be the trigger: data = { "title", "description", "context", "trigger" }.';
        $lines[] = 'Action node: data = { "title", "action", ...action-specific keys }.';
        $lines[] = 'Linear steps go in the parent "children" array (nested). A simple flow can be a flat array where each next step is the next array item.';
        $lines[] = 'Condition nodes (action "condition") store branches in "children" as { "action_true": [ ...nodes ], "action_false": [ ...nodes ] }.';
        $lines[] = 'Do NOT include canvas_position or connection_from — the editor lays out and connects nodes automatically.';
        $lines[] = 'For WhatsApp sender fields, use an empty string "" (the user selects the sender). For recipients and message variables, use placeholders like {{ wc_billing_phone }}, {{ wc_billing_first_name }}, {{ order_id }} when relevant.';
        $lines[] = 'Use ONLY the triggers and actions listed below. Never invent slugs.';
        $lines[] = '';
        $lines[] = 'AVAILABLE TRIGGERS (context/trigger — description):';
        $lines[] = $triggers;
        $lines[] = '';
        $lines[] = 'AVAILABLE ACTIONS (slug — data keys):';
        $lines[] = $actions;

        $extra_system = is_string( $extra_system ) ? trim( $extra_system ) : '';

        if ( '' !== $extra_system ) {
            $lines[] = '';
            $lines[] = 'ADDITIONAL INSTRUCTIONS:';
            $lines[] = $extra_system;
        }

        return implode( "\n", $lines );
    }


    /**
     * Build the triggers reference block.
     *
     * @since 2.0.0
     * @param string $preferred_context | Optional preferred context to list first.
     * @return string
     */
    protected static function build_triggers_reference( $preferred_context = '' ) {
        $catalog = Registry::get_triggers_catalog();
        $lines = array();

        foreach ( $catalog as $ctx => $triggers ) {
            if ( ! is_array( $triggers ) ) {
                continue;
            }

            foreach ( $triggers as $trigger ) {
                $slug = $trigger['data_trigger'] ?? '';

                if ( '' === $slug ) {
                    continue;
                }

                $lines[] = sprintf(
                    '- %s/%s — %s',
                    (string) $ctx,
                    (string) $slug,
                    self::shorten( (string) ( $trigger['title'] ?? $slug ) )
                );
            }
        }

        return implode( "\n", $lines );
    }


    /**
     * Build the actions reference block.
     *
     * @since 2.0.0
     * @return string
     */
    protected static function build_actions_reference() {
        $catalog = Registry::get_actions_catalog();
        $lines = array();

        foreach ( $catalog as $action ) {
            $slug = $action['action'] ?? '';

            if ( '' === $slug ) {
                continue;
            }

            $default_data = isset( $action['default_data'] ) && is_array( $action['default_data'] ) ? $action['default_data'] : array();
            $keys = array_values( array_filter( array_keys( $default_data ), static function( $key ) {
                return ! in_array( $key, array( 'title', 'description', 'action' ), true );
            }));

            $lines[] = sprintf(
                '- %s (%s)%s',
                (string) $slug,
                self::shorten( (string) ( $action['title'] ?? $slug ) ),
                empty( $keys ) ? '' : ' — keys: ' . implode( ', ', $keys )
            );
        }

        return implode( "\n", $lines );
    }


    /**
     * Whether the content has a trigger node.
     *
     * @since 2.0.0
     * @param array<int,array<string,mixed>> $content
     * @return bool
     */
    protected static function has_trigger_node( $content ) {
        foreach ( $content as $node ) {
            if ( isset( $node['type'] ) && 'trigger' === $node['type'] ) {
                return true;
            }
        }

        return false;
    }


    /**
     * Resolve the trigger context from the content.
     *
     * @since 2.0.0
     * @param array<int,array<string,mixed>> $content
     * @return string
     */
    protected static function resolve_category( $content ) {
        foreach ( $content as $node ) {
            if ( isset( $node['type'], $node['data']['context'] ) && 'trigger' === $node['type'] ) {
                return sanitize_key( (string) $node['data']['context'] );
            }
        }

        return '';
    }


    /**
     * Shorten a label for the prompt.
     *
     * @since 2.0.0
     * @param string $text
     * @return string
     */
    protected static function shorten( $text ) {
        $text = trim( wp_strip_all_tags( $text ) );

        if ( function_exists( 'mb_substr' ) && mb_strlen( $text ) > 120 ) {
            return mb_substr( $text, 0, 117 ) . '...';
        }

        return strlen( $text ) > 120 ? substr( $text, 0, 117 ) . '...' : $text;
    }
}
