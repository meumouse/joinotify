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
        $context = sanitize_key( (string) $context );
        $triggers = self::build_triggers_reference( $context );
        $actions = self::build_actions_reference();
        $conditions = self::build_conditions_reference();
        $placeholders = self::build_placeholders_reference( $context );

        $lines = array();
        $lines[] = 'You are an automation builder for the Joinotify WhatsApp automation plugin.';
        $lines[] = 'Given a natural-language description, output ONE workflow as a JSON object.';
        $lines[] = '';
        $lines[] = 'Respond with a JSON object with exactly these keys:';
        $lines[] = '- "title": a short, human title for the workflow.';
        $lines[] = '- "category": the trigger context slug (e.g. "woocommerce", "wordpress").';
        $lines[] = '- "workflow_content": an array of nodes.';
        $lines[] = '';
        $lines[] = 'NODE SHAPE: { "type": "trigger"|"action", "data": { ... }, "children": [] }.';
        $lines[] = 'The FIRST node MUST be the trigger: data = { "title", "description", "context", "trigger" }.';
        $lines[] = '  - "context" is the integration slug; "trigger" is the trigger slug (the part after the slash in the triggers list below).';
        $lines[] = '  - If the chosen trigger lists settings, add each setting key inside data with one of the allowed values.';
        $lines[] = 'Action node: data = { "title", "action", ...action-specific keys }. "action" is the action slug from the actions list.';
        $lines[] = '  - Fill the action data keys using the field schema shown for each action (use one of the allowed values when a field lists options).';
        $lines[] = 'Linear steps are nested: each step goes in the previous node\'s "children" array. (A flat top-level array where each item is the next step is also accepted.)';
        $lines[] = 'CONDITION nodes use action "condition" and BRANCH: "children" MUST be an object { "action_true": [ ...nodes ], "action_false": [ ...nodes ] } (each branch is an array of nodes, possibly empty).';
        $lines[] = '  Condition data keys: { "title", "action": "condition", "condition": <condition key>, "condition_type": <operator>, "value_text": <value to compare>, "meta_key": <only when the condition requires meta_key>, "field_id": <only when the condition requires field_id> }.';
        $lines[] = '  Pick "condition" + "condition_type" only from the CONDITIONS list for the chosen trigger below. Put the compared value in "value_text".';
        $lines[] = '';
        $lines[] = 'RULES:';
        $lines[] = '- Use ONLY the triggers, actions, conditions and placeholders listed below. NEVER invent slugs, keys, operators or placeholders.';
        $lines[] = '- All actions must be compatible with the chosen trigger context (an action only appears here because it is available in this ecosystem).';
        $lines[] = '- Do NOT include canvas_position or connection_from — the editor lays out and connects nodes automatically.';
        $lines[] = '- For WhatsApp sender fields, use an empty string "" (the user selects the sender afterwards).';
        $lines[] = '- For recipients (receiver) and message text, prefer the placeholders listed below (e.g. {{ wc_billing_phone }}, {{ first_name }}) so the message is personalized at send time.';
        $lines[] = '- Keep message copy in the same language as the user description.';
        $lines[] = '';
        $lines[] = 'AVAILABLE TRIGGERS (context/trigger — description [settings]):';
        $lines[] = $triggers;
        $lines[] = '';
        $lines[] = 'AVAILABLE ACTIONS (slug [category] — description; fields):';
        $lines[] = $actions;
        $lines[] = '';
        $lines[] = 'AVAILABLE CONDITIONS (per trigger — usable inside "condition" nodes):';
        $lines[] = $conditions;
        $lines[] = '';
        $lines[] = 'AVAILABLE PLACEHOLDERS (token — description):';
        $lines[] = $placeholders;

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

        // List the preferred context first so the model favors it.
        if ( '' !== $preferred_context && isset( $catalog[ $preferred_context ] ) ) {
            $catalog = array( $preferred_context => $catalog[ $preferred_context ] ) + $catalog;
        }

        foreach ( $catalog as $ctx => $triggers ) {
            if ( ! is_array( $triggers ) ) {
                continue;
            }

            foreach ( $triggers as $trigger ) {
                $slug = $trigger['data_trigger'] ?? '';

                if ( '' === $slug ) {
                    continue;
                }

                $label = self::shorten( (string) ( $trigger['title'] ?? $slug ) );
                $description = self::shorten( (string) ( $trigger['description'] ?? '' ) );
                $text = '' !== $description ? $label . ': ' . $description : $label;
                $settings = self::describe_trigger_settings( $trigger );

                $lines[] = sprintf(
                    '- %s/%s — %s%s',
                    (string) $ctx,
                    (string) $slug,
                    $text,
                    '' !== $settings ? ' [' . $settings . ']' : ''
                );
            }
        }

        return implode( "\n", $lines );
    }


    /**
     * Describe a trigger's required settings (field key + allowed option values).
     *
     * @since 2.0.0
     * @param array<string,mixed> $trigger | Trigger catalog entry.
     * @return string
     */
    protected static function describe_trigger_settings( $trigger ) {
        $settings = isset( $trigger['settings'] ) && is_array( $trigger['settings'] ) ? $trigger['settings'] : array();

        if ( empty( $settings ) ) {
            return '';
        }

        $fields = array();

        foreach ( $settings as $field ) {
            if ( ! is_array( $field ) || empty( $field['key'] ) ) {
                continue;
            }

            $fields[] = self::describe_schema_field( $field );
        }

        if ( empty( $fields ) ) {
            return '';
        }

        return 'settings: ' . implode( ', ', $fields );
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

            $label = self::shorten( (string) ( $action['title'] ?? $slug ) );
            $description = self::shorten( (string) ( $action['description'] ?? '' ) );
            $category = isset( $action['category'] ) ? (string) $action['category'] : '';
            $fields = self::describe_action_fields( $action );

            $lines[] = sprintf(
                '- %s%s — %s%s',
                (string) $slug,
                '' !== $category ? ' [' . $category . ']' : '',
                '' !== $description ? $description : $label,
                '' !== $fields ? '; fields: ' . $fields : ''
            );
        }

        return implode( "\n", $lines );
    }


    /**
     * Describe an action's configurable fields, preferring the settings schema
     * (with field types/options) and falling back to the default-data keys.
     *
     * @since 2.0.0
     * @param array<string,mixed> $action | Action catalog entry.
     * @return string
     */
    protected static function describe_action_fields( $action ) {
        $schema = isset( $action['settings_schema'] ) && is_array( $action['settings_schema'] ) ? $action['settings_schema'] : array();
        $fields = array();

        foreach ( $schema as $field ) {
            if ( ! is_array( $field ) || empty( $field['key'] ) ) {
                continue;
            }

            $fields[] = self::describe_schema_field( $field );
        }

        if ( ! empty( $fields ) ) {
            return implode( ', ', $fields );
        }

        // Fallback: raw data keys when the action has no declarative schema.
        $default_data = isset( $action['default_data'] ) && is_array( $action['default_data'] ) ? $action['default_data'] : array();
        $keys = array_values( array_filter( array_keys( $default_data ), static function( $key ) {
            return ! in_array( $key, array( 'title', 'description', 'action' ), true );
        }));

        return implode( ', ', $keys );
    }


    /**
     * Render a single schema field as "key(component, required) options: a|b".
     *
     * @since 2.0.0
     * @param array<string,mixed> $field | Schema field.
     * @return string
     */
    protected static function describe_schema_field( $field ) {
        $key = sanitize_key( (string) ( $field['key'] ?? '' ) );
        $meta = array();

        if ( ! empty( $field['component'] ) ) {
            $meta[] = (string) $field['component'];
        }

        if ( ! empty( $field['required'] ) ) {
            $meta[] = 'required';
        }

        $rendered = $key;

        if ( ! empty( $meta ) ) {
            $rendered .= '(' . implode( ', ', $meta ) . ')';
        }

        $values = self::extract_option_values( $field['options'] ?? array() );

        if ( ! empty( $values ) ) {
            $rendered .= ' values: ' . implode( '|', $values );
        }

        return $rendered;
    }


    /**
     * Extract the allowed values from an options array (limited for prompt size).
     *
     * @since 2.0.0
     * @param mixed $options | Options array (each: {label,value} or value).
     * @return array<int,string>
     */
    protected static function extract_option_values( $options ) {
        if ( ! is_array( $options ) ) {
            return array();
        }

        $values = array();

        foreach ( $options as $option ) {
            if ( is_array( $option ) && isset( $option['value'] ) ) {
                $values[] = (string) $option['value'];
            } elseif ( is_scalar( $option ) ) {
                $values[] = (string) $option;
            }

            if ( count( $values ) >= 12 ) {
                $values[] = '...';
                break;
            }
        }

        return $values;
    }


    /**
     * Build the conditions reference block, grouped per trigger.
     *
     * @since 2.0.0
     * @return string
     */
    protected static function build_conditions_reference() {
        $catalog = Registry::get_conditions_catalog();
        $operators = isset( $catalog['operators'] ) && is_array( $catalog['operators'] ) ? $catalog['operators'] : array();
        $triggers = isset( $catalog['triggers'] ) && is_array( $catalog['triggers'] ) ? $catalog['triggers'] : array();

        if ( empty( $triggers ) ) {
            return '(no conditions available)';
        }

        $lines = array();

        if ( ! empty( $operators ) ) {
            $lines[] = 'Operators (condition_type): ' . implode( ', ', array_keys( $operators ) ) . '.';
        }

        foreach ( $triggers as $trigger_id => $conditions ) {
            if ( ! is_array( $conditions ) || empty( $conditions ) ) {
                continue;
            }

            $lines[] = sprintf( 'Trigger "%s":', (string) $trigger_id );

            foreach ( $conditions as $condition ) {
                if ( ! is_array( $condition ) || empty( $condition['key'] ) ) {
                    continue;
                }

                $ops = isset( $condition['operators'] ) && is_array( $condition['operators'] ) ? $condition['operators'] : array();
                $requires = isset( $condition['requires'] ) && is_array( $condition['requires'] ) ? $condition['requires'] : array();
                $values = self::extract_option_values( $condition['options'] ?? array() );

                $parts = array( sprintf( '  - %s', (string) $condition['key'] ) );

                if ( ! empty( $condition['title'] ) ) {
                    $parts[0] .= ': ' . self::shorten( (string) $condition['title'] );
                }

                if ( ! empty( $ops ) ) {
                    $parts[] = 'operators: ' . implode( '|', $ops );
                }

                if ( ! empty( $values ) ) {
                    $parts[] = 'values: ' . implode( '|', $values );
                }

                if ( ! empty( $requires ) ) {
                    $parts[] = 'requires: ' . implode( ', ', $requires );
                }

                $lines[] = implode( ' — ', $parts );
            }
        }

        return implode( "\n", $lines );
    }


    /**
     * Build the placeholders reference block.
     *
     * Lists the global placeholders plus the ones belonging to the preferred
     * context (or every context when none is given), so generated messages use
     * real tokens instead of invented ones.
     *
     * @since 2.0.0
     * @param string $preferred_context | Optional context slug to scope tokens.
     * @return string
     */
    protected static function build_placeholders_reference( $preferred_context = '' ) {
        $grouped = apply_filters( 'Joinotify/Builder/Placeholders_List', array(), array() );

        if ( ! is_array( $grouped ) || empty( $grouped ) ) {
            return '(no placeholders available)';
        }

        $lines = array();
        $seen = array();

        foreach ( $grouped as $group => $placeholders ) {
            if ( ! is_array( $placeholders ) ) {
                continue;
            }

            foreach ( $placeholders as $token => $details ) {
                if ( ! is_array( $details ) || isset( $seen[ $token ] ) ) {
                    continue;
                }

                $token_triggers = isset( $details['triggers'] ) && is_array( $details['triggers'] ) ? $details['triggers'] : array();
                $is_global = empty( $token_triggers );

                // When a context is preferred, keep globals + that context only.
                if ( '' !== $preferred_context && ! $is_global && (string) $group !== $preferred_context ) {
                    continue;
                }

                $seen[ $token ] = true;
                $description = self::shorten( (string) ( $details['description'] ?? '' ) );

                $lines[] = sprintf(
                    '- %s%s',
                    (string) $token,
                    '' !== $description ? ' — ' . $description : ''
                );
            }
        }

        return empty( $lines ) ? '(no placeholders available)' : implode( "\n", $lines );
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
