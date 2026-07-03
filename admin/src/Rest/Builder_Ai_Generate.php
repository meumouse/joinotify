<?php

namespace MeuMouse\Joinotify\Rest;

use MeuMouse\Joinotify\AI\Workflow_Generator;
use MeuMouse\Joinotify\AI\Snippet_Generator;
use WP_REST_Request;

defined('ABSPATH') || exit;

/**
 * AI generation endpoint for the builder.
 *
 * Supports multiple intents through a single route. Currently:
 * - intent "flow": generate a full workflow_content from a natural-language brief.
 *
 * @since 2.0.0
 */
class Builder_Ai_Generate extends Abstract_Route {

    /**
     * Route path.
     *
     * @var string
     */
    protected $route = '/admin/ai/generate';

    /**
     * HTTP methods.
     *
     * @var string
     */
    protected $methods = 'POST';


    /**
     * Handle request.
     *
     * @since 2.0.0
     * @param WP_REST_Request $request Request instance.
     * @return \WP_REST_Response
     */
    public function handle( WP_REST_Request $request ) {
        $payload = $request->get_json_params();
        $payload = is_array( $payload ) ? $payload : array();

        $intent = isset( $payload['intent'] ) ? sanitize_key( (string) $payload['intent'] ) : 'flow';
        $instructions = isset( $payload['instructions'] ) ? (string) $payload['instructions'] : '';
        $system = isset( $payload['system'] ) ? (string) $payload['system'] : '';
        $context = isset( $payload['context'] ) ? sanitize_key( (string) $payload['context'] ) : '';

        switch ( $intent ) {
            case 'flow':
                $result = Workflow_Generator::generate( $instructions, $system, $context );
                break;

            case 'snippet':
                $result = Snippet_Generator::generate( $instructions, $system );
                break;

            default:
                $result = array(
                    'status' => 'error',
                    'message' => __( 'Unsupported AI intent.', 'joinotify' ),
                );
                break;
        }

        if ( isset( $result['status'] ) && 'error' === $result['status'] ) {
            return $this->error_response(
                isset( $result['message'] ) ? (string) $result['message'] : esc_html__( 'AI generation failed.', 'joinotify' )
            );
        }

        return $this->success_response( $result );
    }
}
