<?php

/**
 * Settings source file.
 *
 * @since 1.4.7
 * @version 1.4.7
 */

use MeuMouse\Joinotify\Admin\Settings\Registry;

defined('ABSPATH') || exit;

$bootstrap = Registry::get_bootstrap_data(); ?>

<div class="wrap joinotify-settings-page">
	<div class="joinotify-settings-shell">
		<div class="joinotify-settings-shell__content">
			<div id="joinotify-settings-app" class="joinotify-settings-app" data-bootstrap="<?php echo esc_attr( wp_json_encode( $bootstrap ) ); ?>">
				<div class="skeleton-content" style="width: 950px; height: 100px;"></div>

				<div class="skeleton-content" style="width: 680px; height: 65px; margin-top: 2rem;"></div>

				<div class="skeleton-content" style="width: 100%; height: 550px; margin-top: 2rem;"></div>
			</div>
		</div>
	</div>
</div>
