<?php

namespace MeuMouse\Joinotify\Core;

use MeuMouse\Joinotify\Core\Admin;

// Exit if accessed directly.
defined('ABSPATH') || exit;

/**
 * Load assets class
 *
 * @since 1.0.0
 * @package MeuMouse.com
 */
class Assets {

	/**
	 * Construct function
	 * 
	 * @since 1.0.0
	 * @return void
	 */
	public function __construct() {
		add_action( 'admin_enqueue_scripts', array( $this, 'admin_assets' ) );
	}


	/**
	 * Enqueue admin scripts in specific pages
	 * 
	 * @since 1.0.0
	 * @return void
	 */
	public function admin_assets() {
		$current_url = ( isset( $_SERVER['HTTPS'] ) && $_SERVER['HTTPS'] === 'on' ? 'https' : 'http' ) . '://' . $_SERVER['HTTP_HOST'] . $_SERVER['REQUEST_URI'];
		$min_file = JOINOTIFY_DEBUG_MODE ? '' : '.min';

		// enqueue settings scripts
		if ( strpos( $current_url, 'admin.php?page=joinotify-settings' ) !== false ) {
			wp_enqueue_script( 'joinotify-scripts', JOINOTIFY_ASSETS . 'js/joinotify-scripts'. $min_file .'.js', array('jquery'), JOINOTIFY_VERSION );

			wp_localize_script( 'joinotify-scripts', 'joinotify_params', array(
				'debug_mode' => JOINOTIFY_DEBUG_MODE,
				'ajax_url' => admin_url('admin-ajax.php'),
				'confirm_remove_sender' => __( 'Tem certeza que deseja remover este remetente?', 'joinotify' ),
				'resend_otp_button' => __( 'Reenviar código', 'joinotify' ),
			));
		}

		// enqueue bootstrap grid and utilities
		if ( strpos( $current_url, 'admin.php?page=joinotify-settings' ) !== false || strpos( $current_url, 'admin.php?page=joinotify-license' ) !== false ) {
			if ( ! class_exists('Flexify_Dashboard') ) {
				wp_enqueue_style( 'bootstrap-grid', JOINOTIFY_ASSETS . 'vendor/bootstrap/css/bootstrap-grid.min.css', array(), '5.3.3' );
				wp_enqueue_style( 'bootstrap-utilities', JOINOTIFY_ASSETS . 'vendor/bootstrap/css/bootstrap-utilities.min.css', array(), '5.3.3' );
			}

			wp_enqueue_style( 'joinotify-styles', JOINOTIFY_ASSETS . 'css/joinotify-styles'. $min_file .'.css', array(), JOINOTIFY_VERSION );
			wp_enqueue_script( 'joinotify-visibility-controller', JOINOTIFY_ASSETS . 'modules/visibility-controller/visibility-controller'. $min_file .'.js', array('jquery'), JOINOTIFY_VERSION );
			wp_enqueue_script( 'joinotify-toasts', JOINOTIFY_ASSETS . 'modules/toasts/toasts.js', array('jquery'), JOINOTIFY_VERSION );

			wp_enqueue_style( 'joinotify-modal-styles', JOINOTIFY_ASSETS . 'modules/modal/modal'. $min_file .'.css', array(), JOINOTIFY_VERSION );
			wp_enqueue_script( 'joinotify-modal', JOINOTIFY_ASSETS . 'modules/modal/modal'. $min_file .'.js', array('jquery'), JOINOTIFY_VERSION );
		}

		// license page scripts
		if ( strpos( $current_url, 'admin.php?page=joinotify-license' ) !== false ) {
			wp_enqueue_script( 'joinotify-license-scripts', JOINOTIFY_ASSETS . 'js/joinotify-license.js', array('jquery'), JOINOTIFY_VERSION );

			wp_localize_script( 'joinotify-license-scripts', 'joinotify_license_params', array(
				'debug_mode' => JOINOTIFY_DEBUG_MODE,
				'ajax_url' => admin_url('admin-ajax.php'),
				'close_notice_aria_label' => __( 'Fechar', 'joinotify' ),
				'confirm_deactivate_license' => __( 'Tem certeza que deseja desativar sua licença?', 'joinotify' ),
			));
		}

		// enqueue assets on page builder
		if ( strpos( $current_url, 'admin.php?page=joinotify-workflows-builder' ) !== false ) {
			if ( ! class_exists('Flexify_Dashboard') ) {
                wp_enqueue_style( 'bootstrap-styles', JOINOTIFY_ASSETS . 'vendor/bootstrap/css/bootstrap.min.css', array(), '5.3.3' );
                wp_enqueue_script( 'bootstrap-bundle', JOINOTIFY_ASSETS . 'vendor/bootstrap/js/bootstrap.bundle.min.js', array('jquery'), '5.3.3' );
            }

			wp_enqueue_script( 'joinotify-visibility-controller', JOINOTIFY_ASSETS . 'modules/visibility-controller/visibility-controller'. $min_file .'.js', array('jquery'), JOINOTIFY_VERSION );
			wp_enqueue_script( 'joinotify-toasts', JOINOTIFY_ASSETS . 'modules/toasts/toasts.js', array('jquery'), JOINOTIFY_VERSION );

			// bootstrap datepicker
			wp_enqueue_style( 'bootstrap-datepicker-styles', JOINOTIFY_ASSETS . 'vendor/bootstrap-datepicker/bootstrap-datepicker'. $min_file .'.css', array(), JOINOTIFY_VERSION );
			wp_enqueue_script( 'bootstrap-datepicker', 'https://cdnjs.cloudflare.com/ajax/libs/bootstrap-datepicker/1.9.0/js/bootstrap-datepicker.min.js', array('jquery'), '1.9.0' );
			wp_enqueue_script( 'bootstrap-datepicker-translate-pt-br', JOINOTIFY_ASSETS . 'vendor/bootstrap-datepicker/bootstrap-datepicker.pt-BR.min.js', array('jquery'), JOINOTIFY_VERSION );

			// add picmo emojis
			wp_enqueue_script_module( 'picmo-popup', JOINOTIFY_ASSETS . 'vendor/picmo/popup.js', array(), JOINOTIFY_VERSION );

			wp_enqueue_media();

			// builder main
			wp_enqueue_style( 'joinotify-builder-styles', JOINOTIFY_ASSETS . 'builder/css/builder'. $min_file .'.css', array(), JOINOTIFY_VERSION );
			wp_enqueue_script( 'joinotify-builder-scripts', JOINOTIFY_ASSETS . 'builder/js/builder'. $min_file .'.js', array('jquery', 'media-upload'), JOINOTIFY_VERSION );

			wp_localize_script( 'joinotify-builder-scripts', 'joinotify_builder_params', array(
				'debug_mode' => JOINOTIFY_DEBUG_MODE,
				'ajax_url' => admin_url('admin-ajax.php'),
				'status_inactive' => __( 'Inativo', 'joinotify' ),
				'status_active' => __( 'Ativo', 'joinotify' ),
				'arial_label_toasts' => __( 'Fechar', 'joinotify' ),
				'confirm_exclude_action' => __( 'Tem certeza que deseja excluir esta ação?', 'joinotify' ),
				'confirm_exclude_trigger' => __( 'Tem certeza que deseja excluir este acionamento?', 'joinotify' ),
				'export_nonce' => wp_create_nonce('joinotify_export_workflow_nonce'),
				'set_media_title' => esc_html__( 'Escolher mídia', 'joinotify' ),
				'use_this_media_title' => esc_html__( 'Usar esta mídia', 'joinotify' ),
			));
		}

		// enqueue assets on page builder
		if ( strpos( $current_url, 'admin.php?page=joinotify-workflows' ) !== false && strpos( $current_url, 'admin.php?page=joinotify-workflows-builder' ) === false ) {
			wp_enqueue_script( 'joinotify-toasts', JOINOTIFY_ASSETS . 'modules/toasts/toasts.js', array('jquery'), JOINOTIFY_VERSION );

			wp_enqueue_style( 'joinotify-workflows-table-styles', JOINOTIFY_ASSETS . 'css/workflows-table.css', array(), JOINOTIFY_VERSION );
			wp_enqueue_script( 'joinotify-workflows-table-scripts', JOINOTIFY_ASSETS . 'js/workflows-table.js', array('jquery'), JOINOTIFY_VERSION );

			wp_localize_script( 'joinotify-workflows-table-scripts', 'joinotify_workflows_table_params', array(
				'debug_mode' => JOINOTIFY_DEBUG_MODE,
				'ajax_url' => admin_url('admin-ajax.php'),
			));
		}
	}
}