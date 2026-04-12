<?php
/**
 * License source file.
 *
 * @since 1.4.7
 * @version 1.4.7
 */

use MeuMouse\Joinotify\Admin\Settings\Registry;

defined( 'ABSPATH' ) || exit;

$bootstrap = Registry::get_bootstrap_data();
$bootstrap['page'] = 'license';
?>

<div class="wrap joinotify-settings-page">
	<div class="joinotify-settings-shell">
		<div class="joinotify-settings-shell__content">
			<div id="joinotify-license-app" class="joinotify-settings-app" data-bootstrap="<?php echo esc_attr( wp_json_encode( $bootstrap ) ); ?>">
				<div class="joinotify-settings-app__fallback">
					<p><?php esc_html_e( 'Carregando a interface de licença...', 'joinotify' ); ?></p>
				</div>
			</div>
		</div>
	</div>
</div>
