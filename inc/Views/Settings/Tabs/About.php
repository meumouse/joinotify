<?php

use MeuMouse\Joinotify\Admin\Admin;
use MeuMouse\Joinotify\API\License;

// Exit if accessed directly.
defined('ABSPATH') || exit; ?>

<div id="about" class="nav-content">
	<table class="form-table">
		<tr>
			<th>
				<?php esc_html_e( 'Ativar modo depuração', 'joinotify' ); ?>
				<span class="joinotify-description"><?php esc_html_e( 'Ative essa opção para ativar o modo depuração para verificar mensagens de erros e informações relevantes.', 'joinotify' ); ?></span>
			</th>
			<td>
				<div class="form-check form-switch">
					<input type="checkbox" class="toggle-switch" id="enable_debug_mode" name="enable_debug_mode" value="yes" <?php checked( Admin::get_setting('enable_debug_mode') === 'yes' ); ?> />
				</div>
			</td>
			
			<td class="require-debug-mode">
				<button id="joinotify_get_debug_details_trigger" class="btn btn-outline-primary"><?php esc_html_e( 'Ver registros de depuração', 'joinotify' ); ?></button>
		
				<div id="joinotify_get_debug_details_container" class="joinotify-popup-container">
					<div class="joinotify-popup-content">
						<div class="joinotify-popup-header">
							<h5 class="joinotify-popup-title"><?php esc_html_e( 'Registros de depuração', 'joinotify' ); ?></h5>
							<button id="joinotify_get_debug_details_close" class="btn-close fs-lg" aria-label="<?php esc_attr_e( 'Fechar', 'joinotify' ); ?>"></button>
						</div>

						<div class="joinotify-popup-body my-3 text-start">
							<div class="placeholder-content" style="width: 100%; height: 10rem;"></div>
						</div>

						<div class="joinotify-popup-footer position-sticky bottom-0">
							<div class="d-flex align-items-center justify-content-end">
								<button id="joinotify_clear_log_file" class="btn btn-outline-secondary me-3" disabled><?php esc_html_e( 'Limpar registros', 'joinotify' ); ?></button>
								<button id="joinotify_download_log_file" class="btn btn-primary" disabled><?php esc_html_e( 'Baixar arquivo', 'joinotify' ); ?></button>
							</div>
						</div>
					</div>
				</div>
			</td>
		</tr>

		<tr>
			<th>
				<?php esc_html_e( 'Ativar atualizações automáticas', 'joinotify' ); ?>
				<span class="joinotify-description"><?php esc_html_e( 'Ative essa opção para que o plugin Joinotify seja atualizado automaticamente sempre que possível.', 'joinotify' ); ?></span>
			</th>
			<td>
				<div class="form-check form-switch">
					<input type="checkbox" class="toggle-switch" id="enable_auto_updates" name="enable_auto_updates" value="yes" <?php checked( Admin::get_setting('enable_auto_updates') === 'yes' ); ?> />
				</div>
			</td>
		</tr>

		<tr class="container-separator"></tr>

		<tr>
			<td>
				<h3 class="h2 mt-0"><?php esc_html_e( 'Status do sistema:', 'joinotify' ); ?></h3>
				<h4 class="mt-4"><?php esc_html_e( 'WordPress', 'joinotify' ); ?></h4>
				<div class="d-flex align-items-center mb-2">
					<span><?php esc_html_e( 'Versão do WordPress:', 'joinotify' ); ?></span>
					<span class="ms-2"><?php echo esc_html( get_bloginfo( 'version' ) ); ?></span>
				</div>
				<div class="d-flex align-items-center mb-2">
					<span><?php esc_html_e( 'WordPress Multisite:', 'joinotify' ); ?></span>
					<span class="ms-2"><?php echo is_multisite() ? esc_html__( 'Sim', 'joinotify' ) : esc_html__( 'Não', 'joinotify' ); ?></span>
				</div>
				<div class="d-flex align-items-center mb-2">
					<span><?php esc_html_e( 'Modo de depuração do WordPress:', 'joinotify' ); ?></span>
					<span class="ms-2"><?php echo defined( 'WP_DEBUG' ) && WP_DEBUG ? esc_html__( 'Ativo', 'joinotify' ) : esc_html__( 'Desativado', 'joinotify' ); ?></span>
				</div>

				<h4 class="mt-4"><?php esc_html_e( 'Plugin', 'joinotify' ); ?></h4>
				<div class="d-flex align-items-center mb-2">
					<span><?php printf( __( 'Versão do Joinotify: %s', 'joinotify' ), JOINOTIFY_VERSION ) ?></span>
				</div>

				<h4 class="mt-4"><?php esc_html_e( 'Servidor', 'joinotify' ); ?></h4>
				<div class="d-flex align-items-center mb-2">
					<span><?php esc_html_e( 'Versão do PHP:', 'joinotify' ); ?></span>
					<span class="ms-2">
						<?php if ( version_compare( PHP_VERSION, '7.2', '<' ) ) : ?>
							<span class="badge bg-translucent-danger">
								<span>
									<?php echo esc_html( PHP_VERSION ); ?>
								</span>
								<span>
									<?php esc_html_e( 'A versão mínima exigida do PHP é 7.2', 'joinotify' ); ?>
								</span>
							</span>
						<?php else : ?>
							<span class="badge bg-translucent-success">
								<?php echo esc_html( PHP_VERSION ); ?>
							</span>
						<?php endif; ?>
					</span>
				</div>
				<div class="d-flex align-items-center mb-2">
					<span><?php esc_html_e( 'DOMDocument:', 'joinotify' ); ?></span>
					<span class="ms-2">
						<span>
							<?php if ( ! class_exists( 'DOMDocument' ) ) : ?>
								<span class="badge bg-translucent-danger">
									<?php esc_html_e( 'Não', 'joinotify' ); ?>
								</span>
							<?php else : ?>
								<span class="badge bg-translucent-success">
									<?php esc_html_e( 'Sim', 'joinotify' ); ?>
								</span>
							<?php endif; ?>
						</span>
					</span>
				</div>
				<div class="d-flex align-items-center mb-2">
					<span><?php esc_html_e( 'Extensão cURL:', 'joinotify' ); ?></span>
					<span class="ms-2">
						<span>
							<?php if ( !extension_loaded('curl') ) : ?>
								<span class="badge bg-translucent-danger">
									<?php esc_html_e( 'Não', 'joinotify' ); ?>
								</span>
							<?php else : ?>
								<span class="badge bg-translucent-success">
									<?php esc_html_e( 'Sim', 'joinotify' ); ?>
								</span>
								<span>
									<?php echo sprintf( __( 'Versão %s', 'joinotify' ), curl_version()['version'] ) ?>
								</span>
							<?php endif; ?>
						</span>
					</span>
				</div>
				<div class="d-flex align-items-center mb-2">
					<span><?php esc_html_e( 'Extensão OpenSSL:', 'joinotify' ); ?></span>
					<span class="ms-2">
						<span>
							<?php if ( !extension_loaded('openssl') ) : ?>
								<span class="badge bg-translucent-danger">
									<?php esc_html_e( 'Não', 'joinotify' ); ?>
								</span>
							<?php else : ?>
								<span class="badge bg-translucent-success">
									<?php esc_html_e( 'Sim', 'joinotify' ); ?>
								</span>
								<span>
									<?php echo OPENSSL_VERSION_TEXT ?>
								</span>
							<?php endif; ?>
						</span>
					</span>
				</div>
				<?php if ( function_exists( 'ini_get' ) ) : ?>
					<div class="d-flex align-items-center mb-2">
						<span>
							<?php $post_max_size = ini_get( 'post_max_size' ); ?>

							<?php esc_html_e( 'Tamanho máximo da postagem do PHP:', 'joinotify' ); ?>
						</span>
						<span class="ms-2">
							<?php if ( wp_convert_hr_to_bytes( $post_max_size ) < 64000000 ) : ?>
								<span>
									<span class="badge bg-translucent-danger">
										<?php echo esc_html( $post_max_size ); ?>
									</span>
									<span>
										<?php esc_html_e( 'Valor mínimo recomendado é 64M', 'joinotify' ); ?>
									</span>
								</span>
							<?php else : ?>
								<span class="badge bg-translucent-success">
									<?php echo esc_html( $post_max_size ); ?>
								</span>
							<?php endif; ?>
						</span>
					</div>
					<div class="d-flex align-items-center mb-2">
						<span>
							<?php $max_execution_time = ini_get( 'max_execution_time' ); ?>
							<?php esc_html_e( 'Limite de tempo do PHP:', 'joinotify' ); ?>
						</span>
						<span class="ms-2">
							<?php if ( $max_execution_time < 180 ) : ?>
								<span>
									<span class="badge bg-translucent-danger">
										<?php echo esc_html( $max_execution_time ); ?>
									</span>
									<span>
										<?php esc_html_e( 'Valor mínimo recomendado é 180', 'joinotify' ); ?>
									</span>
								</span>
							<?php else : ?>
								<span class="badge bg-translucent-success">
									<?php echo esc_html( $max_execution_time ); ?>
								</span>
							<?php endif; ?>
						</span>
					</div>
					<div class="d-flex align-items-center mb-2">
						<span>
							<?php $max_input_vars = ini_get( 'max_input_vars' ); ?>
							<?php esc_html_e( 'Variáveis máximas de entrada do PHP:', 'joinotify' ); ?>
						</span>
						<span class="ms-2">
							<?php if ( $max_input_vars < 10000 ) : ?>
								<span>
									<span class="badge bg-translucent-danger">
										<?php echo esc_html( $max_input_vars ); ?>
									</span>
									<span>
										<?php esc_html_e( 'Valor mínimo recomendado é 10000', 'joinotify' ); ?>
									</span>
								</span>
							<?php else : ?>
								<span class="badge bg-translucent-success">
									<?php echo esc_html( $max_input_vars ); ?>
								</span>
							<?php endif; ?>
						</span>
					</div>
					<div class="d-flex align-items-center mb-2">
						<span>
							<?php $memory_limit = ini_get( 'memory_limit' ); ?>
							<?php esc_html_e( 'Limite de memória do PHP:', 'joinotify' ); ?>
						</span>
						<span class="ms-2">
							<?php if ( wp_convert_hr_to_bytes( $memory_limit ) < 128000000 ) : ?>
								<span>
									<span class="badge bg-translucent-danger">
										<?php echo esc_html( $memory_limit ); ?>
									</span>
									<span>
										<?php esc_html_e( 'Valor mínimo recomendado é 128M', 'joinotify' ); ?>
									</span>
								</span>
							<?php else : ?>
								<span class="badge bg-translucent-success">
									<?php echo esc_html( $memory_limit ); ?>
								</span>
							<?php endif; ?>
						</span>
					</div>
					<div class="d-flex align-items-center mb-2">
						<span>
							<?php $upload_max_filesize = ini_get( 'upload_max_filesize' ); ?>
							<?php esc_html_e( 'Tamanho máximo de envio do PHP:', 'joinotify' ); ?>
						</span>
						<span class="ms-2">
							<?php if ( wp_convert_hr_to_bytes( $upload_max_filesize ) < 64000000 ) : ?>
								<span>
									<span class="badge bg-translucent-danger">
										<?php echo esc_html( $upload_max_filesize ); ?>
									</span>
									<span>
										<?php esc_html_e( 'Valor mínimo recomendado é 64M', 'joinotify' ); ?>
									</span>
								</span>
							<?php else : ?>
								<span class="badge bg-translucent-success">
									<?php echo esc_html( $upload_max_filesize ); ?>
								</span>
							<?php endif; ?>
						</span>
					</div>
					<div class="d-flex align-items-center mb-2">
						<span><?php esc_html_e( 'Função PHP "file_get_content":', 'joinotify' ); ?></span>
						<span class="ms-2">
							<?php if ( ! ini_get( 'allow_url_fopen' ) ) : ?>
								<span class="badge bg-translucent-danger">
									<?php esc_html_e( 'Desligado', 'joinotify' ); ?>
								</span>
							<?php else : ?>
								<span class="badge bg-translucent-success">
									<?php esc_html_e( 'Ligado', 'joinotify' ); ?>
								</span>
							<?php endif; ?>
						</span>
					</div>
				<?php endif; ?>
			</td>
		</tr>

		<tr>
			<td>
				<button id="joinotify_reset_settings_trigger" class="btn btn-sm btn-outline-warning d-flex align-items-center">
					<svg class="icon icon-lg icon-warning me-2" xmlns="http://www.w3.org/2000/svg"><path d="M12 16c1.671 0 3-1.331 3-3s-1.329-3-3-3-3 1.331-3 3 1.329 3 3 3z"></path><path d="M20.817 11.186a8.94 8.94 0 0 0-1.355-3.219 9.053 9.053 0 0 0-2.43-2.43 8.95 8.95 0 0 0-3.219-1.355 9.028 9.028 0 0 0-1.838-.18V2L8 5l3.975 3V6.002c.484-.002.968.044 1.435.14a6.961 6.961 0 0 1 2.502 1.053 7.005 7.005 0 0 1 1.892 1.892A6.967 6.967 0 0 1 19 13a7.032 7.032 0 0 1-.55 2.725 7.11 7.11 0 0 1-.644 1.188 7.2 7.2 0 0 1-.858 1.039 7.028 7.028 0 0 1-3.536 1.907 7.13 7.13 0 0 1-2.822 0 6.961 6.961 0 0 1-2.503-1.054 7.002 7.002 0 0 1-1.89-1.89A6.996 6.996 0 0 1 5 13H3a9.02 9.02 0 0 0 1.539 5.034 9.096 9.096 0 0 0 2.428 2.428A8.95 8.95 0 0 0 12 22a9.09 9.09 0 0 0 1.814-.183 9.014 9.014 0 0 0 3.218-1.355 8.886 8.886 0 0 0 1.331-1.099 9.228 9.228 0 0 0 1.1-1.332A8.952 8.952 0 0 0 21 13a9.09 9.09 0 0 0-.183-1.814z"></path></svg>
					<?php esc_html_e( 'Redefinir configurações', 'joinotify' ); ?>
				</button>

				<div id="joinotify_reset_settings_container" class="joinotify-popup-container">
					<div class="joinotify-popup-content">
						<div class="joinotify-popup-header border-bottom-0 justify-content-end">
							<button id="joinotify_reset_settings_close" class="btn-close" aria-label="<?php esc_html( 'Fechar', 'joinotify' ); ?>"></button>
						</div>
						<div class="joinotify-popup-body">
							<div class="d-flex flex-column align-items-center p-4">
								<div class="btn-icon rounded-circle p-2 mb-3 bg-translucent-danger">
									<svg class="icon icon-lg icon-danger" xmlns="http://www.w3.org/2000/svg" width="24" height="24" viewBox="0 0 24 24"><path d="M11.953 2C6.465 2 2 6.486 2 12s4.486 10 10 10 10-4.486 10-10S17.493 2 11.953 2zM12 20c-4.411 0-8-3.589-8-8s3.567-8 7.953-8C16.391 4 20 7.589 20 12s-3.589 8-8 8z"></path><path d="M11 7h2v7h-2zm0 8h2v2h-2z"></path></svg>
								</div>
								<h5 class="joinotify-popup-title text-center"><?php esc_html_e('Atenção! Você realmente deseja redefinir as configurações?', 'joinotify' ); ?></h5>
								<span class="title-hightlight bg-danger mt-2 mb-3"></span>
								<span class="text-muted fs-lg p-3"><?php esc_html_e( 'Ao redefinir as configurações do plugin, todas opções serão removidas, voltando ao estado original. Sua licença não será removida.', 'joinotify' ) ?></span>
							</div>
							
							<div class="my-4 p-3">
								<button id="confirm_reset_settings" class="btn btn-lg btn-outline-secondary"><?php esc_html_e( 'Sim, desejo redefinir', 'joinotify' ); ?></button>
							</div>
						</div>
					</div>
				</div>
			</td>
		</tr>

		<tr class="container-separator"></tr>

		<tr>
			<td>
				<a class="btn btn-sm btn-outline-danger d-flex align-items-center" target="_blank" href="https://meumouse.com/reportar-problemas/?wpf9053_2=<?php echo urlencode( JOINOTIFY_ADMIN_EMAIL ); ?>&wpf9053_9=<?php echo urlencode( License::is_valid() ? 'Sim' : 'Não' ) ?>&wpf9053_7=<?php echo urlencode( License::get_domain() ) ?>&wpf9053_6=<?php echo urlencode( wp_get_theme()->get('Name') ) ?>"><?php esc_html_e( 'Reportar problemas', 'joinotify' ); ?></a>
			</td>
		</tr>
	</table>
</div>