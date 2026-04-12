<?php

namespace MeuMouse\Joinotify\Admin\Settings;

use MeuMouse\Joinotify\Admin\Admin;
use MeuMouse\Joinotify\Admin\Default_Options;
use MeuMouse\Joinotify\Api\License;
use MeuMouse\Joinotify\Core\Helpers;
use MeuMouse\Joinotify\Integrations\Integrations_Base;
use MeuMouse\Joinotify\Validations\Country_Codes;

defined('ABSPATH') || exit;

/**
 * Build the settings schema and bootstrap payload for the Vue admin app.
 */
class Registry {

    /**
     * Return the current settings merged with defaults.
     *
     * @return array<string,mixed>
     */
    public static function get_settings() {
        return wp_parse_args( get_option( 'joinotify_settings', array() ), Default_Options::set_default_options() );
    }


    /**
     * Build the settings schema consumed by the Vue app.
     *
     * @return array<string,mixed>
     */
    public static function get_schema() {
        $schema = array(
            array(
                'id' => 'general',
                'title' => esc_html__( 'Geral', 'joinotify' ),
                'description' => esc_html__( 'Preferências base do plugin, proxy de envio e avisos do WhatsApp.', 'joinotify' ),
                'layout' => 'cards',
                'cards' => array(
                    array(
                        'id' => 'general-localization',
                        'title' => esc_html__( 'Localização e telefones', 'joinotify' ),
                        'description' => esc_html__( 'Define o país padrão usado para formatar números e preencher as opções de suporte.', 'joinotify' ),
                        'fields' => array(
                            self::field_select(
                                'joinotify_default_country_code',
                                esc_html__( 'Código padrão do país', 'joinotify' ),
                                esc_html__( 'Escolha o país usado como fallback quando o telefone não vier com DDI.', 'joinotify' ),
                                self::build_country_code_options()
                            ),
                            self::field_toggle(
                                'enable_send_disconnect_notifications',
                                esc_html__( 'Avisar quando o WhatsApp desconectar', 'joinotify' ),
                                esc_html__( 'Envia uma notificação ao remetente quando a conexão não é estabelecida.', 'joinotify' )
                            ),
                            self::field_text(
                                'test_number_phone',
                                esc_html__( 'Telefone de teste', 'joinotify' ),
                                esc_html__( 'Número usado como destino padrão em envios de teste. Informe apenas números com DDI + DDD.', 'joinotify' ),
                                array(
                                    'placeholder' => '5541987111527',
                                )
                            ),
                        ),
                    ),
                    array(
                        'id' => 'general-proxy',
                        'title' => esc_html__( 'Proxy API', 'joinotify' ),
                        'description' => esc_html__( 'Ative e configure os endpoints usados para processar requisições externas de API.', 'joinotify' ),
                        'fields' => array(
                            self::field_toggle(
                                'enable_proxy_api',
                                esc_html__( 'Ativar Proxy API', 'joinotify' ),
                                esc_html__( 'Expõe endpoints neste site para processar requisições do Joinotify.', 'joinotify' )
                            ),
                            self::field_text(
                                'send_text_proxy_api_route',
                                esc_html__( 'Rota de texto', 'joinotify' ),
                                esc_html__( 'Caminho da rota usada para enviar mensagens de texto.', 'joinotify' ),
                                array(
                                    'placeholder' => 'send-message/text',
                                )
                            ),
                            self::field_text(
                                'send_media_proxy_api_route',
                                esc_html__( 'Rota de mídia', 'joinotify' ),
                                esc_html__( 'Caminho da rota usada para enviar mensagens com mídia.', 'joinotify' ),
                                array(
                                    'placeholder' => 'send-message/media',
                                )
                            ),
                            self::field_text(
                                'proxy_api_key',
                                esc_html__( 'Chave da API', 'joinotify' ),
                                esc_html__( 'Chave usada para autenticar chamadas ao Proxy API.', 'joinotify' ),
                                array(
                                    'placeholder' => '',
                                )
                            ),
                        ),
                    ),
                ),
            ),
            array(
                'id' => 'phones',
                'title' => esc_html__( 'Telefones', 'joinotify' ),
                'description' => esc_html__( 'Gerencie remetentes, valide novas conexões e envie mensagens de teste.', 'joinotify' ),
                'layout' => 'custom',
                'cards' => array(
                    array(
                        'id' => 'phones-senders',
                        'title' => esc_html__( 'Remetentes cadastrados', 'joinotify' ),
                        'description' => esc_html__( 'Telefones já validados e disponíveis para uso nos fluxos.', 'joinotify' ),
                        'component' => 'phone-sender-list',
                    ),
                    array(
                        'id' => 'phones-actions',
                        'title' => esc_html__( 'Ferramentas rápidas', 'joinotify' ),
                        'description' => esc_html__( 'Ações para adicionar novo remetente e disparar uma mensagem de teste.', 'joinotify' ),
                        'component' => 'phone-actions',
                    ),
                ),
            ),
            array(
                'id' => 'integrations',
                'title' => esc_html__( 'Integrações', 'joinotify' ),
                'description' => esc_html__( 'Ative integrações, controle dependências e ajuste opções avançadas de cada serviço.', 'joinotify' ),
                'layout' => 'cards',
                'cards' => self::get_integration_cards(),
            ),
            array(
                'id' => 'about',
                'title' => esc_html__( 'Sobre', 'joinotify' ),
                'description' => esc_html__( 'Manutenção, logs, atualizações e informações do ambiente.', 'joinotify' ),
                'layout' => 'custom',
                'cards' => array(
                    array(
                        'id' => 'about-maintenance',
                        'title' => esc_html__( 'Manutenção e preferência', 'joinotify' ),
                        'description' => esc_html__( 'Ajustes operacionais do plugin e sinalizadores de desenvolvimento.', 'joinotify' ),
                        'fields' => array(
                            self::field_toggle(
                                'enable_debug_mode',
                                esc_html__( 'Modo de depuração', 'joinotify' ),
                                esc_html__( 'Ative para registrar informações extras de erros e processos.', 'joinotify' )
                            ),
                            self::field_toggle(
                                'enable_auto_updates',
                                esc_html__( 'Atualizações automáticas', 'joinotify' ),
                                esc_html__( 'Permite que o plugin seja atualizado automaticamente sempre que possível.', 'joinotify' )
                            ),
                            self::field_toggle(
                                'enable_update_notice',
                                esc_html__( 'Avisos de atualização', 'joinotify' ),
                                esc_html__( 'Mostra notificações quando houver uma nova versão disponível.', 'joinotify' )
                            ),
                            self::field_toggle(
                                'enable_developer_integration',
                                esc_html__( 'Integração para desenvolvedor', 'joinotify' ),
                                esc_html__( 'Mantém integrações de suporte e contratos avançados disponíveis.', 'joinotify' )
                            ),
                        ),
                    ),
                    array(
                        'id' => 'about-system',
                        'title' => esc_html__( 'Status do sistema', 'joinotify' ),
                        'description' => esc_html__( 'Visão rápida do ambiente WordPress, PHP e extensões críticas.', 'joinotify' ),
                        'component' => 'system-status',
                    ),
                    array(
                        'id' => 'about-danger',
                        'title' => esc_html__( 'Zona de risco', 'joinotify' ),
                        'description' => esc_html__( 'Ações irreversíveis e operação de limpeza da configuração.', 'joinotify' ),
                        'component' => 'danger-zone',
                    ),
                ),
            ),
        );

        return apply_filters( 'Joinotify/Admin/Settings/Schema', $schema );
    }


    /**
     * Flatten all field definitions by key.
     *
     * @return array<string,array<string,mixed>>
     */
    public static function get_field_definitions() {
        $fields = array();

        foreach ( self::get_schema() as $section ) {
            foreach ( $section['cards'] ?? array() as $card ) {
                foreach ( $card['fields'] ?? array() as $field ) {
                    if ( ! empty( $field['key'] ) ) {
                        $fields[ $field['key'] ] = $field;
                    }
                }
            }
        }

        return $fields;
    }


    /**
     * Build the integration cards used by the integrations section.
     *
     * @return array<int,array<string,mixed>>
     */
    public static function get_integration_cards() {
        if ( ! function_exists( 'is_plugin_active' ) ) {
            require_once ABSPATH . 'wp-admin/includes/plugin.php';
        }

        if ( ! function_exists( 'get_plugins' ) ) {
            require_once ABSPATH . 'wp-admin/includes/plugin.php';
        }

        $settings = self::get_settings();
        $cards = array();
        $integrations = Integrations_Base::integration_tab_items();

        foreach ( $integrations as $slug => $item ) {
            $requires_plugin = ! empty( $item['is_plugin'] );
            $plugin_active = self::is_integration_plugin_active( $item['plugin_active'] ?? array(), $requires_plugin );
            $setting_key = $item['setting_key'] ?? '';

            $card = array(
                'slug' => $slug,
                'title' => $item['title'] ?? ucfirst( $slug ),
                'description' => $item['description'] ?? '',
                'icon' => $item['icon'] ?? '',
                'setting_key' => $setting_key,
                'enabled' => $setting_key ? ( ( $settings[ $setting_key ] ?? 'no' ) === 'yes' ) : false,
                'requires_plugin' => $requires_plugin,
                'plugin_active' => $plugin_active,
                'coming_soon' => ! empty( $item['comming_soon'] ),
                'disabled_message' => $requires_plugin && ! $plugin_active
                    ? esc_html__( 'Este plugin precisa estar instalado e ativo para habilitar esta integração.', 'joinotify' )
                    : '',
                'fields' => array(),
            );

            if ( 'woocommerce' === $slug ) {
                $card['fields'] = self::get_woocommerce_advanced_fields();
            }

            $cards[] = $card;
        }

        return $cards;
    }


    /**
     * WooCommerce advanced settings rendered inside the integrations card.
     *
     * @return array<int,array<string,mixed>>
     */
    public static function get_woocommerce_advanced_fields() {
        return array(
            self::field_toggle(
                'enable_create_coupon_action',
                esc_html__( 'Ativar ação Cupom de desconto', 'joinotify' ),
                esc_html__( 'Adiciona a ação de cupom aos fluxos do WooCommerce.', 'joinotify' )
            ),
            self::field_text(
                'create_coupon_prefix',
                esc_html__( 'Prefixo do cupom', 'joinotify' ),
                esc_html__( 'Prefixo usado na criação automática de cupons.', 'joinotify' ),
                array(
                    'placeholder' => 'CUPOM_',
                )
            ),
            self::field_textarea(
                'woocommerce_billing_full_address_format',
                esc_html__( 'Formato do endereço completo de faturamento', 'joinotify' ),
                esc_html__( 'Define o texto usado na variável de endereço completo de faturamento.', 'joinotify' ),
                array(
                    'placeholder' => '{{ address_1 }}, {{ number }}, {{ city }} - {{ state }} (CEP: {{ postcode }})',
                    'rows' => 3,
                )
            ),
            self::field_textarea(
                'woocommerce_shipping_full_address_format',
                esc_html__( 'Formato do endereço completo de entrega', 'joinotify' ),
                esc_html__( 'Define o texto usado na variável de endereço completo de entrega.', 'joinotify' ),
                array(
                    'placeholder' => '{{ address_1 }}, {{ number }}, {{ city }} - {{ state }} (CEP: {{ postcode }})',
                    'rows' => 3,
                )
            ),
            self::field_toggle(
                'enable_ignore_processed_actions',
                esc_html__( 'Ignorar ações já processadas', 'joinotify' ),
                esc_html__( 'Evita que a mesma ação seja processada novamente quando o gatilho se repetir.', 'joinotify' )
            ),
        );
    }


    /**
     * Current sender list and supporting phone metadata.
     *
     * @return array<string,mixed>
     */
    public static function get_phone_state() {
        $phones_senders = get_option( 'joinotify_get_phones_senders', array() );
        $phones_senders = is_array( $phones_senders ) ? array_values( array_filter( $phones_senders ) ) : array();

        $senders = array();

        foreach ( $phones_senders as $phone ) {
            $senders[] = array(
                'phone' => $phone,
                'formatted' => Helpers::validate_and_format_phone( $phone ),
                'connection' => get_option( 'joinotify_status_connection_' . $phone, 'disconnected' ),
            );
        }

        return array(
            'senders' => $senders,
            'test_number_phone' => Admin::get_setting( 'test_number_phone' ),
            'sender_count' => count( $senders ),
        );
    }


    /**
     * Runtime system details shown on the About tab.
     *
     * @return array<string,mixed>
     */
    public static function get_system_status() {
        $post_max_size = function_exists( 'ini_get' ) ? ini_get( 'post_max_size' ) : '';
        $max_execution_time = function_exists( 'ini_get' ) ? ini_get( 'max_execution_time' ) : '';
        $max_input_vars = function_exists( 'ini_get' ) ? ini_get( 'max_input_vars' ) : '';
        $memory_limit = function_exists( 'ini_get' ) ? ini_get( 'memory_limit' ) : '';
        $upload_max_filesize = function_exists( 'ini_get' ) ? ini_get( 'upload_max_filesize' ) : '';

        return array(
            'wordpress' => array(
                array(
                    'label' => esc_html__( 'Versão do WordPress', 'joinotify' ),
                    'value' => get_bloginfo( 'version' ),
                    'status' => 'info',
                ),
                array(
                    'label' => esc_html__( 'WordPress Multisite', 'joinotify' ),
                    'value' => is_multisite() ? esc_html__( 'Sim', 'joinotify' ) : esc_html__( 'Não', 'joinotify' ),
                    'status' => 'info',
                ),
                array(
                    'label' => esc_html__( 'WP_DEBUG', 'joinotify' ),
                    'value' => defined( 'WP_DEBUG' ) && WP_DEBUG ? esc_html__( 'Ativo', 'joinotify' ) : esc_html__( 'Desativado', 'joinotify' ),
                    'status' => defined( 'WP_DEBUG' ) && WP_DEBUG ? 'warning' : 'success',
                ),
            ),
            'plugin' => array(
                array(
                    'label' => esc_html__( 'Versão do Joinotify', 'joinotify' ),
                    'value' => JOINOTIFY_VERSION,
                    'status' => 'info',
                ),
            ),
            'server' => array(
                array(
                    'label' => esc_html__( 'Versão do PHP', 'joinotify' ),
                    'value' => PHP_VERSION,
                    'status' => version_compare( PHP_VERSION, '7.4', '>=' ) ? 'success' : 'danger',
                ),
                array(
                    'label' => esc_html__( 'DOMDocument', 'joinotify' ),
                    'value' => class_exists( 'DOMDocument' ) ? esc_html__( 'Sim', 'joinotify' ) : esc_html__( 'Não', 'joinotify' ),
                    'status' => class_exists( 'DOMDocument' ) ? 'success' : 'danger',
                ),
                array(
                    'label' => esc_html__( 'Extensão cURL', 'joinotify' ),
                    'value' => extension_loaded( 'curl' ) ? curl_version()['version'] : esc_html__( 'Não', 'joinotify' ),
                    'status' => extension_loaded( 'curl' ) ? 'success' : 'danger',
                ),
                array(
                    'label' => esc_html__( 'Extensão OpenSSL', 'joinotify' ),
                    'value' => extension_loaded( 'openssl' ) ? OPENSSL_VERSION_TEXT : esc_html__( 'Não', 'joinotify' ),
                    'status' => extension_loaded( 'openssl' ) ? 'success' : 'danger',
                ),
                array(
                    'label' => esc_html__( 'post_max_size', 'joinotify' ),
                    'value' => $post_max_size,
                    'status' => function_exists( 'wp_convert_hr_to_bytes' ) && wp_convert_hr_to_bytes( $post_max_size ) < 64000000 ? 'danger' : 'success',
                ),
                array(
                    'label' => esc_html__( 'max_execution_time', 'joinotify' ),
                    'value' => $max_execution_time,
                    'status' => (int) $max_execution_time < 180 ? 'danger' : 'success',
                ),
                array(
                    'label' => esc_html__( 'max_input_vars', 'joinotify' ),
                    'value' => $max_input_vars,
                    'status' => (int) $max_input_vars < 10000 ? 'danger' : 'success',
                ),
                array(
                    'label' => esc_html__( 'memory_limit', 'joinotify' ),
                    'value' => $memory_limit,
                    'status' => function_exists( 'wp_convert_hr_to_bytes' ) && wp_convert_hr_to_bytes( $memory_limit ) < 128000000 ? 'danger' : 'success',
                ),
                array(
                    'label' => esc_html__( 'upload_max_filesize', 'joinotify' ),
                    'value' => $upload_max_filesize,
                    'status' => function_exists( 'wp_convert_hr_to_bytes' ) && wp_convert_hr_to_bytes( $upload_max_filesize ) < 64000000 ? 'danger' : 'success',
                ),
                array(
                    'label' => esc_html__( 'allow_url_fopen', 'joinotify' ),
                    'value' => ! ini_get( 'allow_url_fopen' ) ? esc_html__( 'Desligado', 'joinotify' ) : esc_html__( 'Ligado', 'joinotify' ),
                    'status' => ! ini_get( 'allow_url_fopen' ) ? 'danger' : 'success',
                ),
            ),
        );
    }


    /**
     * Build the full bootstrap payload for the frontend application.
     *
     * @return array<string,mixed>
     */
    public static function get_bootstrap_data() {
        return apply_filters( 'Joinotify/Admin/Settings/Bootstrap_Data', array(
            'version' => JOINOTIFY_VERSION,
            'page' => 'settings',
            'settings' => self::get_settings(),
            'schema' => self::get_schema(),
            'integrations' => self::get_integration_cards(),
            'phones' => self::get_phone_state(),
            'system' => self::get_system_status(),
            'license' => self::get_license_state(),
            'links' => array(
                'docs_url' => esc_url_raw( 'https://ajuda.meumouse.com/docs/joinotify/overview' ),
                'purchase_url' => esc_url_raw( 'https://meumouse.com/plugins/joinotify/' ),
            ),
            'permissions' => array(
                'manage_options' => current_user_can( 'manage_options' ),
            ),
            'rest' => array(
                'root' => esc_url_raw( rest_url( 'joinotify/v1' ) ),
                'nonce' => wp_create_nonce( 'wp_rest' ),
            ),
            'contracts' => array(
                'bootstrap_filter' => 'Joinotify/Admin/Settings/Bootstrap_Data',
                'schema_filter' => 'Joinotify/Admin/Settings/Schema',
                'integration_filter' => 'Joinotify/Settings/Tabs/Integrations',
                'actions_filter' => 'Joinotify/Builder/Actions',
                'triggers_filter' => 'Joinotify/Builder/Get_All_Triggers',
            ),
            'i18n' => array(
                'saved' => esc_html__( 'As configurações foram salvas.', 'joinotify' ),
                'error' => esc_html__( 'Não foi possível concluir a operação.', 'joinotify' ),
            ),
        ) );
    }


    /**
     * Build the license state payload used by the Vue license page.
     *
     * @return array<string,mixed>
     */
    public static function get_license_state() {
        $license_key = get_option( 'joinotify_license_key', '' );
        $license_key = is_string( $license_key ) ? sanitize_text_field( $license_key ) : '';
        $license_object = get_option( 'joinotify_license_response_object' );
        $is_valid = License::is_valid();
        $purchase_url = apply_filters( 'Joinotify/Admin/Settings/License_Purchase_Url', 'https://meumouse.com/plugins/joinotify/' );
        $docs_url = apply_filters( 'Joinotify/Admin/Settings/License_Help_Url', 'https://ajuda.meumouse.com/docs/joinotify/overview' );

        $subscription_label = $is_valid
            ? ( strpos( $license_key, 'CM-' ) === 0
                ? sprintf( esc_html__( 'Assinatura: Clube M - %s', 'joinotify' ), License::license_title() )
                : sprintf( esc_html__( 'Assinatura: %s', 'joinotify' ), License::license_title() )
            )
            : esc_html__( 'Ative sua licença para liberar os recursos premium.', 'joinotify' );

        $support_text = esc_html__( 'Não disponível', 'joinotify' );

        if ( is_object( $license_object ) && ! empty( $license_object->support_end ) ) {
            $support_text = is_string( $license_object->support_end )
                ? sanitize_text_field( $license_object->support_end )
                : esc_html__( 'Não disponível', 'joinotify' );
        }

        return array(
            'is_valid' => $is_valid,
            'status_label' => $is_valid ? esc_html__( 'Válida', 'joinotify' ) : esc_html__( 'Inválida', 'joinotify' ),
            'status_tone' => $is_valid ? 'success' : 'danger',
            'title' => $is_valid ? esc_html__( 'Licença ativa', 'joinotify' ) : esc_html__( 'Ative sua licença', 'joinotify' ),
            'subtitle' => $is_valid
                ? esc_html__( 'Sua instalação está liberada para uso completo.', 'joinotify' )
                : esc_html__( 'Digite o código da licença para desbloquear os recursos premium.', 'joinotify' ),
            'purchase_url' => esc_url_raw( $purchase_url ),
            'docs_url' => esc_url_raw( $docs_url ),
            'activate_action' => 'joinotify_active_license',
            'deactivate_action' => 'joinotify_deactive_license',
            'sync_action' => 'joinotify_sync_license',
            'alternative_action' => 'joinotify_alternative_activation_license',
            'license_key' => $license_key,
            'license_key_masked' => self::mask_license_key( $license_key ),
            'license_title' => $is_valid ? License::license_title() : esc_html__( 'Não disponível', 'joinotify' ),
            'subscription_label' => $subscription_label,
            'expire_label' => $is_valid
                ? sprintf( esc_html__( 'Licença expira em: %s', 'joinotify' ), License::license_expire() )
                : esc_html__( 'Licença expira em: Não disponível', 'joinotify' ),
            'support_label' => $is_valid
                ? sprintf( esc_html__( 'Suporte até: %s', 'joinotify' ), $support_text )
                : esc_html__( 'Suporte até: Não disponível', 'joinotify' ),
            'key_label' => esc_html__( 'Sua chave de licença:', 'joinotify' ) . ' ' . self::mask_license_key( $license_key ),
            'renew_link' => is_object( $license_object ) && ! empty( $license_object->renew_link ) ? esc_url_raw( $license_object->renew_link ) : '',
            'expire_renew_link' => is_object( $license_object ) && ! empty( $license_object->expire_renew_link ) ? esc_url_raw( $license_object->expire_renew_link ) : '',
            'support_renew_link' => is_object( $license_object ) && ! empty( $license_object->support_renew_link ) ? esc_url_raw( $license_object->support_renew_link ) : '',
        );
    }


    /**
     * Build the country-code select options.
     *
     * @return array<int,array<string,string>>
     */
    private static function build_country_code_options() {
        $options = array(
            array(
                'value' => '0',
                'label' => esc_html__( 'Nenhum', 'joinotify' ),
            ),
        );

        foreach ( Country_Codes::build_country_code_select() as $country ) {
            $options[] = array(
                'value' => (string) $country['code'],
                'label' => sprintf( '%s (+%s)', $country['country'], $country['code'] ),
            );
        }

        return $options;
    }


    /**
     * Mask a license key preserving the beginning and end.
     *
     * @param string $license_key
     * @return string
     */
    private static function mask_license_key( $license_key ) {
        if ( empty( $license_key ) ) {
            return esc_html__( 'Não disponível', 'joinotify' );
        }

        $license_key = sanitize_text_field( $license_key );

        return substr( $license_key, 0, 9 ) . 'XXXXXXXX-XXXXXXXX' . substr( $license_key, -9 );
    }


    /**
     * Check whether all required plugins for an integration are active.
     *
     * @param array<int,string> $plugin_slugs
     * @param bool $requires_plugin
     * @return bool
     */
    private static function is_integration_plugin_active( $plugin_slugs, $requires_plugin ) {
        if ( ! $requires_plugin ) {
            return true;
        }

        if ( ! is_array( $plugin_slugs ) || empty( $plugin_slugs ) ) {
            return false;
        }

        foreach ( $plugin_slugs as $plugin ) {
            if ( function_exists( 'is_plugin_active' ) && is_plugin_active( $plugin ) ) {
                return true;
            }
        }

        return false;
    }


    /**
     * Build a select field definition.
     *
     * @param string $key
     * @param string $label
     * @param string $description
     * @param array<int,array{value:string,label:string}> $options
     * @param array<string,mixed> $extra
     * @return array<string,mixed>
     */
    private static function field_select( $key, $label, $description, $options, $extra = array() ) {
        return array_merge( array(
            'type' => 'select',
            'key' => $key,
            'label' => $label,
            'description' => $description,
            'options' => $options,
        ), $extra );
    }


    /**
     * Build a toggle field definition.
     *
     * @param string $key
     * @param string $label
     * @param string $description
     * @param array<string,mixed> $extra
     * @return array<string,mixed>
     */
    private static function field_toggle( $key, $label, $description, $extra = array() ) {
        return array_merge( array(
            'type' => 'toggle',
            'key' => $key,
            'label' => $label,
            'description' => $description,
        ), $extra );
    }


    /**
     * Build a text field definition.
     *
     * @param string $key
     * @param string $label
     * @param string $description
     * @param array<string,mixed> $extra
     * @return array<string,mixed>
     */
    private static function field_text( $key, $label, $description, $extra = array() ) {
        return array_merge( array(
            'type' => 'text',
            'key' => $key,
            'label' => $label,
            'description' => $description,
        ), $extra );
    }


    /**
     * Build a textarea field definition.
     *
     * @param string $key
     * @param string $label
     * @param string $description
     * @param array<string,mixed> $extra
     * @return array<string,mixed>
     */
    private static function field_textarea( $key, $label, $description, $extra = array() ) {
        return array_merge( array(
            'type' => 'textarea',
            'key' => $key,
            'label' => $label,
            'description' => $description,
        ), $extra );
    }
}
