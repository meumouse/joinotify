<?php

namespace MeuMouse\Joinotify\Core;

use MeuMouse\Joinotify\Admin\Admin;

// Exit if accessed directly.
defined('ABSPATH') || exit;

/**
 * Load assets class
 *
 * @since 1.0.0
 * @version 1.1.0
 * @package MeuMouse.com
 */
class Assets {

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
	 * @version 1.1.0
	 * @return void
	 */
	public function settings_assets() {
		$min_file = JOINOTIFY_DEBUG_MODE ? '' : '.min';

		if ( joinotify_check_admin_page('joinotify-settings') ) {
			// check if Flexify Dashboard is active for prevent duplicate Bootstrap files
			if ( ! class_exists('Flexify_Dashboard') ) {
				wp_enqueue_style( 'bootstrap-grid', JOINOTIFY_ASSETS . 'vendor/bootstrap/css/bootstrap-grid.min.css', array(), '5.3.3' );
				wp_enqueue_style( 'bootstrap-utilities', JOINOTIFY_ASSETS . 'vendor/bootstrap/css/bootstrap-utilities.min.css', array(), '5.3.3' );
			}

			wp_enqueue_script( 'joinotify-visibility-controller', JOINOTIFY_ASSETS . 'modules/visibility-controller/visibility-controller'. $min_file .'.js', array('jquery'), JOINOTIFY_VERSION );
			wp_enqueue_script( 'joinotify-toasts', JOINOTIFY_ASSETS . 'modules/toasts/toasts'. $min_file .'.js', array('jquery'), JOINOTIFY_VERSION );

			wp_enqueue_style( 'joinotify-modal-styles', JOINOTIFY_ASSETS . 'modules/modal/modal'. $min_file .'.css', array(), JOINOTIFY_VERSION );
			wp_enqueue_script( 'joinotify-modal', JOINOTIFY_ASSETS . 'modules/modal/modal'. $min_file .'.js', array('jquery'), JOINOTIFY_VERSION );

			wp_enqueue_style( 'joinotify-styles', JOINOTIFY_ASSETS . 'admin/css/settings'. $min_file .'.css', array(), JOINOTIFY_VERSION );
			wp_enqueue_script( 'joinotify-scripts', JOINOTIFY_ASSETS . 'admin/js/settings'. $min_file .'.js', array('jquery'), JOINOTIFY_VERSION );

			// settings params
			wp_localize_script( 'joinotify-scripts', 'joinotify_params', array(
				'debug_mode' => JOINOTIFY_DEBUG_MODE,
				'ajax_url' => admin_url('admin-ajax.php'),
				'confirm_remove_sender' => __( 'Tem certeza que deseja remover este remetente?', 'joinotify' ),
				'resend_otp_button' => __( 'Reenviar código', 'joinotify' ),
				'confirm_clear_debug_logs' => __( 'Tem certeza que deseja limpar os registros de depuração?', 'joinotify' ),
			));
		}
	}


	/**
	 * Enqueue assets on license page
	 * 
	 * @since 1.1.0
	 * @return void
	 */
	public function license_assets() {
		$min_file = JOINOTIFY_DEBUG_MODE ? '' : '.min';

		if ( joinotify_check_admin_page('joinotify-license') ) {
			// check if Flexify Dashboard is active for prevent duplicate Bootstrap files
			if ( ! class_exists('Flexify_Dashboard') ) {
				wp_enqueue_style( 'bootstrap-grid', JOINOTIFY_ASSETS . 'vendor/bootstrap/css/bootstrap-grid.min.css', array(), '5.3.3' );
				wp_enqueue_style( 'bootstrap-utilities', JOINOTIFY_ASSETS . 'vendor/bootstrap/css/bootstrap-utilities.min.css', array(), '5.3.3' );
			}

			wp_enqueue_style( 'joinotify-styles', JOINOTIFY_ASSETS . 'admin/css/joinotify-styles'. $min_file .'.css', array(), JOINOTIFY_VERSION );
			
			wp_enqueue_script( 'joinotify-visibility-controller', JOINOTIFY_ASSETS . 'modules/visibility-controller/visibility-controller'. $min_file .'.js', array('jquery'), JOINOTIFY_VERSION );
			wp_enqueue_script( 'joinotify-toasts', JOINOTIFY_ASSETS . 'modules/toasts/toasts'. $min_file .'.js', array('jquery'), JOINOTIFY_VERSION );

			wp_enqueue_style( 'joinotify-modal-styles', JOINOTIFY_ASSETS . 'modules/modal/modal'. $min_file .'.css', array(), JOINOTIFY_VERSION );
			wp_enqueue_script( 'joinotify-modal', JOINOTIFY_ASSETS . 'modules/modal/modal'. $min_file .'.js', array('jquery'), JOINOTIFY_VERSION );

			wp_enqueue_script( 'joinotify-license-scripts', JOINOTIFY_ASSETS . 'admin/js/license'. $min_file .'.js', array('jquery'), JOINOTIFY_VERSION );

			// license page params
			wp_localize_script( 'joinotify-license-scripts', 'joinotify_license_params', array(
				'debug_mode' => JOINOTIFY_DEBUG_MODE,
				'ajax_url' => admin_url('admin-ajax.php'),
				'close_notice_aria_label' => __( 'Fechar', 'joinotify' ),
				'confirm_deactivate_license' => __( 'Tem certeza que deseja desativar sua licença?', 'joinotify' ),
			));
		}
	}


	/**
	 * Enqueue scripts on builder page
	 * 
	 * @since 1.1.0
	 * @return void
	 */
	public function builder_assets() {
		$min_file = JOINOTIFY_DEBUG_MODE ? '' : '.min';
		
		if ( joinotify_check_admin_page('joinotify-workflows-builder') ) {
			wp_enqueue_media(); // wordpress media library

			// check if Flexify Dashboard is active for prevent duplicate Bootstrap files
			if ( ! class_exists('Flexify_Dashboard') ) {
				wp_enqueue_style( 'bootstrap-styles', JOINOTIFY_ASSETS . 'vendor/bootstrap/css/bootstrap.min.css', array(), '5.3.3' );
				wp_enqueue_script( 'bootstrap-bundle', JOINOTIFY_ASSETS . 'vendor/bootstrap/js/bootstrap.bundle.min.js', array('jquery'), '5.3.3' );
			}

			wp_enqueue_script( 'joinotify-visibility-controller', JOINOTIFY_ASSETS . 'modules/visibility-controller/visibility-controller'. $min_file .'.js', array('jquery'), JOINOTIFY_VERSION );

			// bootstrap datepicker library
			wp_enqueue_style( 'bootstrap-datepicker-styles', JOINOTIFY_ASSETS . 'vendor/bootstrap-datepicker/bootstrap-datepicker'. $min_file .'.css', array(), JOINOTIFY_VERSION );
			wp_enqueue_script( 'bootstrap-datepicker', 'https://cdnjs.cloudflare.com/ajax/libs/bootstrap-datepicker/1.9.0/js/bootstrap-datepicker.min.js', array('jquery'), '1.9.0' );
			wp_enqueue_script( 'bootstrap-datepicker-translate-pt-br', JOINOTIFY_ASSETS . 'vendor/bootstrap-datepicker/bootstrap-datepicker.pt-BR.min.js', array('jquery'), JOINOTIFY_VERSION );

			// EmojioneArea library
			wp_enqueue_style( 'joinotify-emojionearea-styles', 'https://cdn.rawgit.com/mervick/emojionearea/master/dist/emojionearea.min.css', array(), '3.4.1' );
			wp_enqueue_script( 'joinotify-emojionearea-scripts', 'https://cdn.rawgit.com/mervick/emojionearea/master/dist/emojionearea.min.js', array('jquery'), '3.4.1' );

			// codemirror library
			wp_enqueue_style( 'joinotify-codemirror-styles', JOINOTIFY_ASSETS . 'vendor/codemirror/lib/codemirror.css', array(), '5.65.18' );
			wp_enqueue_style( 'joinotify-codemirror-dracula-theme', JOINOTIFY_ASSETS . 'vendor/codemirror/theme/dracula.css', array(), '5.65.18' );
			wp_enqueue_script( 'joinotify-codemirror-scripts', JOINOTIFY_ASSETS . 'vendor/codemirror/lib/codemirror.js', array(),  '5.65.18' );
			wp_enqueue_script( 'joinotify-codemirror-php-mode', JOINOTIFY_ASSETS . 'vendor/codemirror/mode/php/php.js', array(),  '5.65.18' );
			wp_enqueue_script( 'joinotify-codemirror-clike-mode', JOINOTIFY_ASSETS . 'vendor/codemirror/mode/clike/clike.js', array(),  '5.65.18' );
			wp_enqueue_script( 'joinotify-codemirror-css-mode', JOINOTIFY_ASSETS . 'vendor/codemirror/mode/css/css.js', array(),  '5.65.18' );
			wp_enqueue_script( 'joinotify-codemirror-htmlmixed-mode', JOINOTIFY_ASSETS . 'vendor/codemirror/mode/htmlmixed/htmlmixed.js', array(),  '5.65.18' );
			wp_enqueue_script( 'joinotify-codemirror-javascript-mode', JOINOTIFY_ASSETS . 'vendor/codemirror/mode/javascript/javascript.js', array(),  '5.65.18' );
			wp_enqueue_script( 'joinotify-codemirror-xml-mode', JOINOTIFY_ASSETS . 'vendor/codemirror/mode/xml/xml.js', array(),  '5.65.18' );
			wp_enqueue_script( 'joinotify-codemirror-matchbrackets-addon', JOINOTIFY_ASSETS . 'vendor/codemirror/addon/edit/matchbrackets.js', array(),  '5.65.18' );
			wp_enqueue_script( 'joinotify-codemirror-activeline-addon', JOINOTIFY_ASSETS . 'vendor/codemirror/addon/selection/active-line.js', array(),  '5.65.18' );
			wp_enqueue_script( 'joinotify-codemirror-matchtags-addon', JOINOTIFY_ASSETS . 'vendor/codemirror/addon/edit/matchtags.js', array(),  '5.65.18' );
			wp_enqueue_script( 'joinotify-codemirror-closebrackets-addon', JOINOTIFY_ASSETS . 'vendor/codemirror/addon/edit/closebrackets.js', array(),  '5.65.18' );

			// builder main
			wp_enqueue_style( 'joinotify-builder-styles', JOINOTIFY_ASSETS . 'builder/css/builder'. $min_file .'.css', array(), JOINOTIFY_VERSION );
			wp_enqueue_script( 'joinotify-builder-scripts', JOINOTIFY_ASSETS . 'builder/js/builder'. $min_file .'.js', array('jquery', 'media-upload'), JOINOTIFY_VERSION, true );

			// builder params
			wp_localize_script( 'joinotify-builder-scripts', 'joinotify_builder_params', array(
				'debug_mode' => JOINOTIFY_DEBUG_MODE,
				'ajax_url' => admin_url('admin-ajax.php'),
				'status_active' => esc_html__( 'Ativo', 'joinotify' ),
				'arial_label_toasts' => esc_html__( 'Fechar', 'joinotify' ),
				'confirm_exclude_action' => esc_html__( 'Tem certeza que deseja excluir esta ação?', 'joinotify' ),
				'confirm_exclude_trigger' => esc_html__( 'Tem certeza que deseja excluir este acionamento?', 'joinotify' ),
				'export_nonce' => wp_create_nonce('joinotify_export_workflow_nonce'),
				'set_media_title' => esc_html__( 'Escolher mídia', 'joinotify' ),
				'use_this_media_title' => esc_html__( 'Usar esta mídia', 'joinotify' ),
				'default_workflow_name' => sprintf( __( 'Minha automação #%s', 'joinotify' ), random_int( 0, 999999 ) ),
				'copy_group_id' => esc_html__( 'ID copiado!', 'joinotify' ),
				'import_nonce' => wp_create_nonce('joinotify_import_workflow_nonce'),
				'emoji_picker_i18n' => array(
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
			));
		}
	}


	/**
	 * Enqueue scripts on workflows table page
	 * 
	 * @since 1.1.0
	 * @return void
	 */
	public function workflows_table_assets() {
		$min_file = JOINOTIFY_DEBUG_MODE ? '' : '.min';

		if ( joinotify_check_admin_page('joinotify-workflows') && ! joinotify_check_admin_page('joinotify-workflows-builder') ) {
			wp_enqueue_script( 'joinotify-toasts', JOINOTIFY_ASSETS . 'modules/toasts/toasts'. $min_file .'.js', array('jquery'), JOINOTIFY_VERSION );

			wp_enqueue_style( 'joinotify-workflows-table-styles', JOINOTIFY_ASSETS . 'admin/css/workflows-table'. $min_file .'.css', array(), JOINOTIFY_VERSION );
			wp_enqueue_script( 'joinotify-workflows-table-scripts', JOINOTIFY_ASSETS . 'admin/js/workflows-table'. $min_file .'.js', array('jquery'), JOINOTIFY_VERSION );

			// workflows table params
			wp_localize_script( 'joinotify-workflows-table-scripts', 'joinotify_workflows_table_params', array(
				'debug_mode' => JOINOTIFY_DEBUG_MODE,
				'ajax_url' => admin_url('admin-ajax.php'),
			));
		}
	}
}