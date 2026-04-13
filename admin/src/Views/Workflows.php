<?php

/**
 * Workflows view file.
 *
 * @since 1.4.8
 */

defined('ABSPATH') || exit;

$bootstrap_json = wp_json_encode( $bootstrap ?? array(), JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES ); ?>

<div class="wrap p-0">
    <div id="joinotify-workflows-app" data-bootstrap="<?php echo esc_attr( $bootstrap_json ); ?>">
        <div class="skeleton-content" style="width: 950px; height: 100px;"></div>

        <div class="skeleton-content" style="width: 680px; height: 65px; margin-top: 2rem;"></div>

        <div class="skeleton-content" style="width: 100%; height: 550px; margin-top: 2rem;"></div>
    </div>
</div>
