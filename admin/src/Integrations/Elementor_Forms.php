<?php

namespace MeuMouse\Joinotify\Integrations;

use MeuMouse\Joinotify\Admin\Admin;
use MeuMouse\Joinotify\Core\Helpers;
use MeuMouse\Joinotify\Core\Logger;
use MeuMouse\Joinotify\Builder\Core;
use MeuMouse\Joinotify\Api\Controller;
use MeuMouse\Joinotify\Validations\Media_Types;

use ElementorPro\Modules\Forms\Classes\Action_Base;
use Elementor\Controls_Manager;

// Exit if accessed directly.
defined('ABSPATH') || exit;

if ( class_exists('\ElementorPro\Modules\Forms\Classes\Action_Base') ) {

	/**
	 * Add integration with Elementor Forms
	 * 
	 * @since 1.1.0
	 * @version 1.4.7
	 * @package MeuMouse\Joinotify\Integrations
	 * @author MeuMouse.com
	 */
	class Elementor_Forms extends Action_Base {

		/**
		 * Get action name
		 *
		 * @since 1.1.0
		 * @access public
		 * @return string
		 */
		public function get_name(): string {
			return 'joinotify';
		}


		/**
		 * Get action label
		 *
		 * @since 1.1.0
		 * @access public
		 * @return string
		 */
		public function get_label(): string {
			return esc_html__( 'Joinotify', 'joinotify' );
		}


		/**
		 * Register action controls
		 *
		 * @since 1.1.0
		 * @version 1.4.7
		 * @access public
		 * @param \Elementor\Widget_Base $widget
		 */
		public function register_settings_section( $widget ): void {
			$widget->start_controls_section(
				'joinotify_section',
				[
					'label' => __( 'Joinotify', 'joinotify' ),
					'condition' => [
						'submit_actions' => $this->get_name(),
					],
				]
			);

			$phone_options = array();

			if ( $phones = get_option('joinotify_get_phones_senders') ) {
				foreach ( $phones as $phone ) {
					$formatted_phone = Helpers::validate_and_format_phone( $phone );
					$phone_options[ esc_attr( $phone ) ] = esc_html( $formatted_phone );
				}
			}

			$senders = get_option( 'joinotify_get_phones_senders', array() );

			$widget->add_control(
				'joinotify_sender',
				[
					'label' => __( 'Sender', 'joinotify' ),
					'type' => Controls_Manager::SELECT,
					'options' => $phone_options,
					'default' => ! empty( $senders[0] ) ? $senders[0] : '',
				]
			);

			$widget->add_control(
				'joinotify_receiver',
				[
					'label' => __( 'Recipient field ID', 'joinotify' ),
					'type' => Controls_Manager::TEXT,
					'description' => __( 'Enter the field ID that collects the recipient\'s phone number.', 'joinotify' ),
					'ai' => [
						'active' => false,
					],
				]
			);

			$widget->add_control(
				'joinotify_form_id',
				[
					'label' => __( 'Form ID', 'joinotify' ),
					'type' => Controls_Manager::TEXT,
					'description' => __( 'Enter this form\'s ID to validate data processing. Available in the form\'s additional information.', 'joinotify' ),
					'ai' => [
						'active' => false,
					],
				]
			);

			$widget->add_control(
				'joinotify_send_text',
				[
					'label' => __( 'Send text message', 'joinotify' ),
					'type' => Controls_Manager::SWITCHER,
					'label_on' => __( 'Yes', 'joinotify' ),
					'label_off' => __( 'No', 'joinotify' ),
					'return_value' => 'yes',
					'default' => 'yes',
				]
			);

			$widget->add_control(
				'joinotify_send_text_message',
				[
					'label' => __( 'Text message', 'joinotify' ),
					'type' => Controls_Manager::TEXTAREA,
					'rows' => 10,
					'description' => __( 'Add your text to be sent to the WhatsApp user. Add text variables to replace information. Use {{ field_id=[FIELD_ID] }} replacing FIELD_ID with the corresponding field ID to retrieve field information.', 'joinotify' ),
					'placeholder' => __( 'Hello {{ field_id=[FIELD_ID] }}!', 'joinotify' ),
					'default' => __( 'Hello {{ field_id=[name] }}! We have received your information, and a representative will contact you shortly.', 'joinotify' ),
					'condition' => [
						'joinotify_send_text' => 'yes',
					],
					'dynamic' => [
						'active' => true,
					],
					'ai' => [
						'active' => false,
					],
				]
			);

			$widget->add_control(
				'joinotify_placeholders_alert',
				[
					'type' => Controls_Manager::ALERT,
					'alert_type' => 'success',
					'heading' => __( 'Tip', 'joinotify' ),
					'content' => __( 'Replace information with ', 'joinotify' ) . ' <a href="https://ajuda.meumouse.com/docs/joinotify/placeholders">' . esc_html__( 'text variables', 'joinotify' ) . '</a>',
				]
			);

			$widget->add_control(
				'joinotify_send_media_message',
				[
					'label' => __( 'Send media', 'joinotify' ),
					'type' => Controls_Manager::SWITCHER,
					'label_on' => __( 'Yes', 'joinotify' ),
					'label_off' => __( 'No', 'joinotify' ),
					'return_value' => 'yes',
					'default' => 'no',
				]
			);

			$media_type = array();

			foreach ( Media_Types::get_media_types() as $type => $value ) {
				$media_type[ esc_attr( $type ) ] = esc_html( $value );
			}

			$widget->add_control(
				'joinotidy_media_type',
				[
					'label' => __( 'Media type', 'joinotify' ),
					'type' => Controls_Manager::SELECT,
					'default' => 'image',
					'options' => $media_type,
					'condition' => [
						'joinotify_send_media_message' => 'yes',
					],
				]
			);

			$widget->add_control(
				'joinotify_media_url',
				[
					'label' => __( 'Add media', 'joinotify' ),
					'type' => Controls_Manager::MEDIA,
					'condition' => [
						'joinotify_send_media_message' => 'yes',
					],
				]
			);

			$widget->add_control(
				'joinotify_media_caption',
				[
					'label' => __( 'Media caption', 'joinotify' ),
					'type' => Controls_Manager::TEXTAREA,
					'rows' => 5,
					'description' => __( 'Add a caption to accompany the sent media. Supports text variables (placeholders).', 'joinotify' ),
					'placeholder' => __( 'Hello {{ field_id=[nome] }}, check out this file!', 'joinotify' ),
					'condition' => [
						'joinotify_send_media_message' => 'yes',
					],
					'dynamic' => [
						'active' => true,
					],
					'ai' => [
						'active' => false,
					],
				]
			);

			$widget->end_controls_section();
		}


		/**
		 * Run action
		 *
		 * @since 1.1.0
		 * @version 1.4.7
		 * @param object $record | Form object class
		 * @param \ElementorPro\Modules\Forms\Classes\Ajax_Handler $ajax_handler
		 * @return void
		 */
		public function run( $record, $ajax_handler ): void {
			$settings = $record->get('form_settings');
		
			// validate the form ID
			if ( empty( $settings['joinotify_form_id'] ) || $settings['joinotify_form_id'] !== $settings['form_id'] ) {
				return;
			}
		
			// validate if the receiver field is filled
			if ( empty( $settings['joinotify_receiver'] ) ) {
				return;
			}
		
			// retrieve the submitted data from form
			$raw_fields = $record->get('fields');
			$fields = array();

			foreach ( $raw_fields as $id => $field ) {
				$fields[ $id ] = $field['value'];
			}
		
			// extract data from form
			$sender = $settings['joinotify_sender'] ?? '';
			$receiver = Controller::prepare_receiver( $fields[ $settings['joinotify_receiver'] ] ?? '' );

			$payload = array(
				'type' => 'elementor',
				'id' => $settings['joinotify_form_id'],
				'fields' => $fields,
			);

			if ( defined('JOINOTIFY_DEBUG_MODE') && JOINOTIFY_DEBUG_MODE ) {
				Logger::register_log( "context on Elementor form: " . print_r( $payload, true ) );
			}

			$text_msg = joinotify_prepare_message( $settings['joinotify_send_text_message'] ?? '', $payload );
			$send_text = $settings['joinotify_send_text'] ?? 'no';
			$send_media = $settings['joinotify_send_media_message'] ?? 'no';
		
			// validate if receiver and sender exists
			if ( empty( $sender ) || empty( $receiver ) ) {
				return;
			}
		
			// check if send message text is enabled
			if ( $send_text === 'yes' && ! empty( $text_msg ) ) {
				if ( defined('JOINOTIFY_DEBUG_MODE') && JOINOTIFY_DEBUG_MODE ) {
					Logger::register_log( "sender on Elementor form: " . $sender );
					Logger::register_log( "receiver on Elementor form: " . $receiver );
					Logger::register_log( "text message on Elementor form: " . $text_msg );
				}

				Controller::send_message_text( $sender, $receiver, $text_msg );
			}
		
			// check if send message media is enabled
			if ( $send_media === 'yes' ) {
				$media_type = $settings['joinotidy_media_type'] ?? 'image';
				$media_url = $settings['joinotify_media_url']['url'] ?? '';
				$caption = joinotify_prepare_message( $settings['joinotify_media_caption'] ?? '', $payload );

				if ( ! empty( $media_url ) ) {
					if ( defined('JOINOTIFY_DEBUG_MODE') && JOINOTIFY_DEBUG_MODE ) {
						Logger::register_log( "media type on Elementor form: " . $media_type );
						Logger::register_log( "media url on Elementor form: " . $media_url );
						Logger::register_log( "media caption on Elementor form: " . $caption );
					}

					Controller::send_message_media( $sender, $receiver, $media_type, $media_url, $caption );
				}
			}
		}


		/**
		 * On export
		 *
		 * @since 1.1.0
		 * @version 1.4.7
		 * @param array $element | Elements from form
		 * @return array
		 */
		public function on_export( $element ): array {
			unset(
				$element['joinotify_form_id'],
				$element['joinotify_sender'],
				$element['joinotify_receiver'],
				$element['joinotify_send_text'],
				$element['joinotify_send_text_message'],
				$element['joinotify_send_media_message'],
				$element['joinotidy_media_type'],
				$element['joinotify_media_url'],
				$element['joinotify_media_caption']
			);

			return $element;
		}
	}
}
