<?php

/**
 * Setup wizard source file.
 *
 * The mount point carries the full-screen shell class itself, so the skeleton
 * below already covers the admin before Vue boots. Wrapping it in a plain
 * container instead would paint the wizard inside the admin chrome for a frame
 * and then jump once the app mounted.
 *
 * There is deliberately no `.wrap` around it, unlike every other screen here. A
 * fixed element only escapes to the viewport while no ancestor creates a
 * stacking or containing block, so the shell is kept as close to the top of the
 * admin markup as WordPress allows. `.wrap` exists to add admin gutters, which
 * a full-screen overlay has no use for anyway.
 *
 * @since 2.3.0
 * @version 2.5.0
 */

defined('ABSPATH') || exit; ?>

<div id="joinotify-onboarding-app" class="joinotify-onboarding-shell">
	<div class="joinotify-onboarding-shell__loading">
		<div class="skeleton-content" style="width: 320px; height: 28px;"></div>

		<div class="skeleton-content" style="width: 560px; max-width: 100%; height: 18px; margin-top: 1rem;"></div>

		<div class="skeleton-content" style="width: 100%; height: 520px; margin-top: 2.5rem;"></div>
	</div>
</div>
