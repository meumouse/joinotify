<?php

namespace MeuMouse\Joinotify\Core;

use MeuMouse\Joinotify\Admin\Admin;

// Exit if accessed directly.
defined('ABSPATH') || exit;

/**
 * Load assets class
 *
 * @since 1.0.0
 * @version 1.3.0
 * @package MeuMouse.com
 */
class Assets {

	public $assets_url = JOINOTIFY_ASSETS;
	public $min_file = JOINOTIFY_DEBUG_MODE ? '' : '.min';
	public $version = JOINOTIFY_VERSION;
	public $debug_mode = JOINOTIFY_DEBUG_MODE;
	public $dev_mode = JOINOTIFY_DEV_MODE;

	/**
	 * Construct function
	 * 
	 * @since 1.0.0
	 * @version 1.1.0
	 * @return void
	 */
	public function __construct() {
		// settings page scripts
		add_action( 'admin_enqueue_scripts', array( $this, 'settings_assets' ) );

		// license page scripts
		add_action( 'admin_enqueue_scripts', array( $this, 'license_assets' ) );

		// workflow builder scripts
		add_action( 'admin_enqueue_scripts', array( $this, 'builder_assets' ) );

		// workflows table scripts
		add_action( 'admin_enqueue_scripts', array( $this, 'workflows_table_assets' ) );
	}


	/**
	 * Enqueue scripts on settings page
	 * 
	 * @since 1.0.0
	 * @version 1.3.0
	 * @return void
	 */
	public function settings_assets() {
		if ( joinotify_check_admin_page('joinotify-settings') ) {
			// check if Flexify Dashboard is active for prevent duplicate Bootstrap files
			if ( ! class_exists('Flexify_Dashboard') ) {
				wp_enqueue_style( 'bootstrap-grid', $this->assets_url . 'vendor/bootstrap/css/bootstrap-grid.min.css', array(), '5.3.3' );
				wp_enqueue_style( 'bootstrap-utilities', $this->assets_url . 'vendor/bootstrap/css/bootstrap-utilities.min.css', array(), '5.3.3' );
			}

			wp_enqueue_style( 'joinotify-styles', $this->assets_url . 'admin/css/settings'. $this->min_file .'.css', array(), $this->version );
			wp_enqueue_script( 'joinotify-scripts', $this->assets_url . 'admin/js/settings'. $this->min_file .'.js', array('jquery'), $this->version );

			// settings params
			wp_localize_script( 'joinotify-scripts', 'joinotify_params', array(
				'debug_mode' => $this->debug_mode,
				'dev_mode' => $this->dev_mode,
				'ajax_url' => admin_url('admin-ajax.php'),
				'i18n' => array(
					'confirm_remove_sender' => __( 'Tem certeza que deseja remover este remetente?', 'joinotify' ),
					'resend_otp_button' => __( 'Reenviar código', 'joinotify' ),
					'confirm_clear_debug_logs' => __( 'Tem certeza que deseja limpar os registros de depuração?', 'joinotify' ),
					'offline_toast_header' => esc_html__( 'Ops! Não há conexão com a internet', 'joinotify' ),
                    'offline_toast_body' => esc_html__( 'As alterações não serão salvas.', 'joinotify' ),
				),
			));
		}
	}


	/**
	 * Enqueue assets on license page
	 * 
	 * @since 1.1.0
	 * @version 1.3.0
	 * @return void
	 */
	public function license_assets() {
		if ( joinotify_check_admin_page('joinotify-license') ) {
			// check if Flexify Dashboard is active for prevent duplicate Bootstrap files
			if ( ! class_exists('Flexify_Dashboard') ) {
				wp_enqueue_style( 'bootstrap-grid', $this->assets_url . 'vendor/bootstrap/css/bootstrap-grid.min.css', array(), '5.3.3' );
				wp_enqueue_style( 'bootstrap-utilities', $this->assets_url . 'vendor/bootstrap/css/bootstrap-utilities.min.css', array(), '5.3.3' );
			}
			
			wp_enqueue_script( 'joinotify-visibility-controller', $this->assets_url . 'modules/visibility-controller/visibility-controller'. $this->min_file .'.js', array('jquery'), $this->version );

			wp_enqueue_style( 'joinotify-styles', $this->assets_url . 'admin/css/settings'. $this->min_file .'.css', array(), $this->version );
			wp_enqueue_script( 'joinotify-license-scripts', $this->assets_url . 'admin/js/license'. $this->min_file .'.js', array('jquery'), $this->version );

			// license page params
			wp_localize_script( 'joinotify-license-scripts', 'joinotify_license_params', array(
				'debug_mode' => $this->debug_mode,
				'dev_mode' => $this->dev_mode,
				'ajax_url' => admin_url('admin-ajax.php'),
				'i18n' => array(
					'close_notice_aria_label' => __( 'Fechar', 'joinotify' ),
					'confirm_deactivate_license' => __( 'Tem certeza que deseja desativar sua licença?', 'joinotify' ),
				),
			));
		}
	}


	/**
	 * Enqueue scripts on builder page
	 * 
	 * @since 1.1.0
	 * @version 1.3.0
	 * @return void
	 */
	public function builder_assets() {
		if ( joinotify_check_admin_page('joinotify-workflows-builder') ) {
			wp_enqueue_media(); // wordpress media library

			// check if Flexify Dashboard is active for prevent duplicate Bootstrap files
			if ( ! class_exists('Flexify_Dashboard') ) {
				wp_enqueue_style( 'bootstrap-styles', $this->assets_url . 'vendor/bootstrap/css/bootstrap.min.css', array(), '5.3.3' );
				wp_enqueue_script( 'bootstrap-bundle', $this->assets_url . 'vendor/bootstrap/js/bootstrap.bundle.min.js', array('jquery'), '5.3.3' );
			}

			// bootstrap datepicker library
			wp_enqueue_style( 'bootstrap-datepicker-styles', $this->assets_url . 'vendor/bootstrap-datepicker/bootstrap-datepicker'. $this->min_file .'.css', array(), $this->version );
			wp_enqueue_script( 'bootstrap-datepicker', 'https://cdnjs.cloudflare.com/ajax/libs/bootstrap-datepicker/1.9.0/js/bootstrap-datepicker.min.js', array('jquery'), '1.9.0' );
			wp_enqueue_script( 'bootstrap-datepicker-translate-pt-br', $this->assets_url . 'vendor/bootstrap-datepicker/bootstrap-datepicker.pt-BR.min.js', array('jquery'), $this->version );

			// EmojioneArea library
			wp_enqueue_style( 'joinotify-emojionearea-styles', 'https://cdn.rawgit.com/mervick/emojionearea/master/dist/emojionearea.min.css', array(), '3.4.1' );
			wp_enqueue_script( 'joinotify-emojionearea-scripts', 'https://cdn.rawgit.com/mervick/emojionearea/master/dist/emojionearea.min.js', array('jquery'), '3.4.1' );

			// codemirror library
			wp_enqueue_style( 'joinotify-codemirror-styles', $this->assets_url . 'vendor/codemirror/lib/codemirror.css', array(), '5.65.18' );
			wp_enqueue_style( 'joinotify-codemirror-dracula-theme', $this->assets_url . 'vendor/codemirror/theme/dracula.css', array(), '5.65.18' );
			wp_enqueue_script( 'joinotify-codemirror-scripts', $this->assets_url . 'vendor/codemirror/lib/codemirror.js', array(),  '5.65.18' );
			wp_enqueue_script( 'joinotify-codemirror-php-mode', $this->assets_url . 'vendor/codemirror/mode/php/php.js', array(),  '5.65.18' );
			wp_enqueue_script( 'joinotify-codemirror-clike-mode', $this->assets_url . 'vendor/codemirror/mode/clike/clike.js', array(),  '5.65.18' );
			wp_enqueue_script( 'joinotify-codemirror-css-mode', $this->assets_url . 'vendor/codemirror/mode/css/css.js', array(),  '5.65.18' );
			wp_enqueue_script( 'joinotify-codemirror-htmlmixed-mode', $this->assets_url . 'vendor/codemirror/mode/htmlmixed/htmlmixed.js', array(),  '5.65.18' );
			wp_enqueue_script( 'joinotify-codemirror-javascript-mode', $this->assets_url . 'vendor/codemirror/mode/javascript/javascript.js', array(),  '5.65.18' );
			wp_enqueue_script( 'joinotify-codemirror-xml-mode', $this->assets_url . 'vendor/codemirror/mode/xml/xml.js', array(),  '5.65.18' );
			wp_enqueue_script( 'joinotify-codemirror-matchbrackets-addon', $this->assets_url . 'vendor/codemirror/addon/edit/matchbrackets.js', array(),  '5.65.18' );
			wp_enqueue_script( 'joinotify-codemirror-activeline-addon', $this->assets_url . 'vendor/codemirror/addon/selection/active-line.js', array(),  '5.65.18' );
			wp_enqueue_script( 'joinotify-codemirror-matchtags-addon', $this->assets_url . 'vendor/codemirror/addon/edit/matchtags.js', array(),  '5.65.18' );
			wp_enqueue_script( 'joinotify-codemirror-closebrackets-addon', $this->assets_url . 'vendor/codemirror/addon/edit/closebrackets.js', array(),  '5.65.18' );
			wp_enqueue_script( 'joinotify-codemirror-autorefresh-addon', $this->assets_url . 'vendor/codemirror/addon/display/autorefresh.js', array(),  '5.65.18' );

			// Selectize library
			wp_enqueue_style( 'joinotify-selectize-styles', $this->assets_url . 'vendor/selectize/css/selectize.bootstrap5.css', array(), '0.15.2' );
			wp_enqueue_script( 'joinotify-selectize-scripts', $this->assets_url . 'vendor/selectize/js/selectize'. $this->min_file .'.js', array('jquery'), '0.15.2' );
		//	wp_enqueue_script( 'joinotify-selectize-remove-button-plugin', $this->assets_url . 'vendor/selectize/plugins/remove_button/plugin.js', array('jquery'), '0.15.2' );

			// builder main
			wp_enqueue_style( 'joinotify-builder-styles', $this->assets_url . 'builder/css/builder'. $this->min_file .'.css', array(), $this->version );
			wp_enqueue_script( 'joinotify-builder-scripts', $this->assets_url . 'builder/js/builder'. $this->min_file .'.js', array('jquery', 'media-upload'), $this->version, true );

			// builder params
			wp_localize_script( 'joinotify-builder-scripts', 'joinotify_builder_params', array(
				'debug_mode' => $this->debug_mode,
				'dev_mode' => $this->dev_mode,
				'ajax_url' => admin_url('admin-ajax.php'),
				'nonces' => array(
					'import_nonce' => wp_create_nonce('joinotify_import_workflow_nonce'),
					'export_nonce' => wp_create_nonce('joinotify_export_workflow_nonce'),
				),
				'i18n' => array(
					'status_active' => esc_html__( 'Ativo', 'joinotify' ),
					'confirm_exclude_action' => esc_html__( 'Tem certeza que deseja excluir esta ação?', 'joinotify' ),
					'set_media_title' => esc_html__( 'Escolher mídia', 'joinotify' ),
					'use_this_media_title' => esc_html__( 'Usar esta mídia', 'joinotify' ),
					'default_workflow_name' => sprintf( __( 'Minha automação #%s', 'joinotify' ), random_int( 0, 999999 ) ),
					'copy_group_id' => esc_html__( 'ID copiado!', 'joinotify' ),
					'remove_product_selectize' => esc_html__( 'Remover', 'joinotify' ),
					'not_templates_found' => esc_html__( 'Nenhum fluxo encontrado.', 'joinotify' ),
					'emoji_picker' => array(
						'placeholder' => esc_html__( 'Pesquisar', 'joinotify' ),
						'button_title' => esc_html__( 'Use a tecla TAB para inserir um emoji rapidamente', 'joinotify' ),
						'filters' => array(
							'tones_title' => esc_html__( 'Diversidade', 'joinotify' ),
							'recent_title' => esc_html__( 'Recentes', 'joinotify' ),
							'smileys_people_title' => esc_html__( 'Sorrisos e Pessoas', 'joinotify' ),
							'animals_nature_title' => esc_html__( 'Animais e Natureza', 'joinotify' ),
							'food_drink_title' => esc_html__( 'Comidas e Bebidas', 'joinotify' ),
							'activity_title' => esc_html__( 'Atividades', 'joinotify' ),
							'travel_places_title' => esc_html__( 'Viajens e Lugares', 'joinotify' ),
							'objects_title' => esc_html__( 'Objetos', 'joinotify' ),
							'symbols_title' => esc_html__( 'Símbolos', 'joinotify' ),
							'flags_title' => esc_html__( 'Bandeiras', 'joinotify' ),
						),
					),
					'offline_toast_header' => esc_html__( 'Ops! Não há conexão com a internet', 'joinotify' ),
                    'offline_toast_body' => esc_html__( 'As alterações não serão salvas.', 'joinotify' ),
				),
			));
		}
	}


	/**
	 * Enqueue scripts on workflows table page
	 * 
	 * @since 1.1.0
	 * @version 1.3.0
	 * @return void
	 */
	public function workflows_table_assets() {
		if ( joinotify_check_admin_page('joinotify-workflows') && ! joinotify_check_admin_page('joinotify-workflows-builder') ) {
			wp_enqueue_style( 'joinotify-workflows-table-styles', $this->assets_url . 'admin/css/workflows-table'. $this->min_file .'.css', array(), $this->version );
			wp_enqueue_script( 'joinotify-workflows-table-scripts', $this->assets_url . 'admin/js/workflows-table'. $this->min_file .'.js', array('jquery'), $this->version );

			// workflows table params
			wp_localize_script( 'joinotify-workflows-table-scripts', 'joinotify_workflows_table_params', array(
				'debug_mode' => $this->debug_mode,
				'dev_mode' => $this->dev_mode,
				'ajax_url' => admin_url('admin-ajax.php'),
			));
		}
	}
}