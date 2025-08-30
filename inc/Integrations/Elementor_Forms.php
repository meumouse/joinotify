<?php

namespace MeuMouse\Joinotify\Integrations;

use MeuMouse\Joinotify\Admin\Admin;
use MeuMouse\Joinotify\Core\Helpers;
use MeuMouse\Joinotify\Core\Logger;
use MeuMouse\Joinotify\Builder\Core;
use MeuMouse\Joinotify\API\Controller;
use MeuMouse\Joinotify\Validations\Media_Types;

use ElementorPro\Modules\Forms\Classes\Action_Base;
use Elementor\Controls_Manager;

// Exit if accessed directly.
defined('ABSPATH') || exit;

// check if Elementor Pro is installed
if ( ! class_exists('ElementorPro\Modules\Forms\Classes\Action_Base') ) {
	return;
}

/**
 * Add integration with Elementor Forms
 * 
 * @since 1.1.0
 * @version 1.4.0
 * @package MeuMouse.com
 */
class Elementor_Forms extends Action_Base {

    /**
     * Construct function
     * 
     * @since 1.1.0
     * @return void
     */
    public function __construct() {
        if ( defined('ELEMENTOR_PATH') && Admin::get_setting('enable_elementor_integration') === 'yes' ) {
            add_action( 'elementor_pro/forms/actions/register', array( $this, 'register_joinotify_form_actions' ) );
        }
    }


    /**
     * Register the Joinotify on Actions for Forms
     *
     * @since 1.1.0
     * @param object $form_actions | Class Elementor_Forms
     * @return void
     */
    public function register_joinotify_form_actions( $form_actions ) {
        $form_actions->register( new Elementor_Forms() );
    }


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
	 * @version 1.4.0
	 * @access public
	 * @param \Elementor\Widget_Base $widget
	 */
	public function register_settings_section( $widget ): void {
		$widget->start_controls_section(
			'joinotify_section',
			[
				'label' => esc_html__( 'Joinotify', 'joinotify' ),
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

        $widget->add_control(
            'joinotify_sender',
            [
                'label' => esc_html__( 'Remetente', 'joinotify' ),
                'type' => Controls_Manager::SELECT,
                'options' => $phone_options,
                'default' => get_option('joinotify_get_phones_senders')[0],
            ]
        );

		$widget->add_control(
			'joinotify_receiver',
			[
				'label' => esc_html__( 'ID do campo de destinatário', 'joinotify' ),
				'type' => Controls_Manager::TEXT,
				'description' => esc_html__( 'Informe o ID do campo que coleta o telefone de destinatário do formulário.', 'joinotify' ),
				'ai' => [
					'active' => false,
				],
			]
		);

		$widget->add_control(
			'joinotify_form_id',
			[
				'label' => esc_html__( 'ID do formulário', 'joinotify' ),
				'type' => Controls_Manager::TEXT,
				'description' => esc_html__( 'Informe o ID deste formulário para validar o processamento das informações. Disponível nas informações adicionais do formulário.', 'joinotify' ),
				'ai' => [
					'active' => false,
				],
			]
		);

        $widget->add_control(
			'joinotify_send_text',
			[
				'label' => esc_html__( 'Enviar mensagem de texto', 'joinotify' ),
				'type' => Controls_Manager::SWITCHER,
				'label_on' => esc_html__( 'Sim', 'joinotify' ),
				'label_off' => esc_html__( 'Não', 'joinotify' ),
				'return_value' => 'yes',
				'default' => 'yes',
			]
		);

        $widget->add_control(
			'joinotify_send_text_message',
			[
				'label' => esc_html__( 'Mensagem de texto', 'joinotify' ),
				'type' => Controls_Manager::TEXTAREA,
				'rows' => 10,
				'description' => esc_html__( 'Adicione seu texto à ser enviado ao usuário do WhatsApp. Adicione variáveis de texto para substituir informações. Use {{ field_id=[FIELD_ID] }} substituindo FIELD_ID pelo ID do campo correspondente para recuperar a informação de um campo.', 'joinotify' ),
				'placeholder' => esc_html__( 'Olá {{ field_id=[FIELD_ID] }}!', 'joinotify' ),
                'default' => esc_html__( 'Olá {{ field_id=[nome] }}! Recebemos suas informações, em breve um atendente retornará o contato.', 'joinotify' ),
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
				'heading' => esc_html__( 'Dica', 'joinotify' ),
				'content' => esc_html__( 'Substitua informações com ', 'joinotify' ) . ' <a href="https://ajuda.meumouse.com/docs/joinotify/placeholders">' . esc_html__( 'variáveis de texto', 'joinotify' ) . '</a>',
			]
		);

        $widget->add_control(
			'joinotify_send_media_message',
			[
				'label' => esc_html__( 'Enviar mídia', 'joinotify' ),
				'type' => Controls_Manager::SWITCHER,
				'label_on' => esc_html__( 'Sim', 'joinotify' ),
				'label_off' => esc_html__( 'Não', 'joinotify' ),
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
				'label' => esc_html__( 'Tipo de mídia', 'joinotify' ),
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
				'label' => esc_html__( 'Adicionar mídia', 'joinotify' ),
				'type' => Controls_Manager::MEDIA,
                'condition' => [
                    'joinotify_send_media_message' => 'yes',
                ],
			]
		);

		$widget->add_control(
			'joinotify_media_caption',
			[
				'label' => esc_html__( 'Legenda da mídia', 'joinotify' ),
				'type' => Controls_Manager::TEXTAREA,
				'rows' => 5,
				'description' => esc_html__( 'Adicione uma legenda para acompanhar a mídia enviada. Suporta variáveis de texto (placeholders).', 'joinotify' ),
				'placeholder' => esc_html__( 'Olá {{ field_id=[nome] }}, veja esse arquivo!', 'joinotify' ),
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
	 * @version 1.4.0
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

		if ( JOINOTIFY_DEBUG_MODE ) {
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
			if ( JOINOTIFY_DEBUG_MODE ) {
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
				if ( JOINOTIFY_DEBUG_MODE ) {
					Logger::register_log( "media type on Elementor form: " . $media_type );
					Logger::register_log( "media url on Elementor form: " . $media_url );
					Logger::register_log( "media caption on Elementor form: " . $caption );
				}

				// Aqui já suporta legenda
				Controller::send_message_media( $sender, $receiver, $media_type, $media_url, $caption );
}
		}
	}


	/**
	 * On export
	 *
	 * @since 1.1.0
	 * @version 1.4.0
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
			$element['joinotify_media_caption'],
		);

		return $element;
	}
}