<?php

namespace MeuMouse\Joinotify\Background;

use WP_Background_Process;

// Exit if accessed directly.
defined('ABSPATH') || exit;

if ( ! class_exists( 'WP_Background_Process' ) ) {
    require_once ABSPATH . 'wp-admin/includes/class-wp-background-process.php';
}

/**
 * Process workflows in the background asynchronously
 * 
 * @since 1.2.2
 * @package MeuMouse.com
 */
class Workflow_Background_Process extends WP_Background_Process {

    /**
     * Unique action name
     *
     * @since 1.2.2
     * @var string
     */
    protected $action = 'joinotify_workflow_processing';

    /**
     * Process a single queue item
     *
     * @since 1.2.2
     * @param array $payload | Workflow payload data
     * @return false|mixed
     */
    protected function task( $payload ) {
        if ( empty( $payload ) || ! is_array( $payload ) ) {
            return false;
        }

        // Chama o processamento de workflows
        Workflow_Processor::process_workflows( $payload );

        // Return false to indicate completion
        return false;
    }


    /**
     * Complete when all items have been processed
     * 
     * @since 1.2.2
     * @return void
     */
    protected function complete() {
        parent::complete();
    }
}