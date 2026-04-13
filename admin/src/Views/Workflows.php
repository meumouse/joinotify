<?php
/**
 * Workflows view file.
 *
 * @since 1.4.8
 */

defined('ABSPATH') || exit;

$bootstrap_json = wp_json_encode( $bootstrap ?? array(), JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES );
?>

<div class="wrap p-0">
    <div
        id="joinotify-workflows-app"
        data-bootstrap="<?php echo esc_attr( $bootstrap_json ); ?>"
    ></div>
</div>
