<?php

/**
 * Builder view file.
 *
 * @since 1.4.7
 * @version 2.0.0
 */

defined('ABSPATH') || exit;

$debug_mode = defined( 'JOINOTIFY_DEBUG_MODE' ) ? (bool) JOINOTIFY_DEBUG_MODE : false; ?>

<div class="wrap p-0 joinotify-builder-page">
	<style>
		#adminmenumain,
		#wpfooter {
			display: none !important;
		}

		<?php if ( ! $debug_mode ) : ?>
			#wpadminbar {
				display: none !important;
			}
		<?php endif; ?>
	</style>

	<div id="joinotify-builder-app" class="joinotify-builder-app">
		<style>
			#adminmenumain,
			#wpfooter {
				display: none !important;
			}

			<?php if ( ! $debug_mode ) : ?>
				#wpadminbar {
					display: none !important;
				}
			<?php endif; ?>
		</style>
	</div>
</div>