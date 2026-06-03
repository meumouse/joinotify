<?php

namespace MeuMouse\Joinotify\Admin;

use MeuMouse\Joinotify\Admin\Admin;
use MeuMouse\Joinotify\Core\Helpers;

// Exit if accessed directly.
defined('ABSPATH') || exit;

/**
 * Class for handle with settings components
 * 
 * @since 1.1.0
 * @version 1.4.7
 * @package MeuMouse\Joinotify\Admin
 * @author MeuMouse.com
 */
class Components {

    /**
     * Display current phones senders
     * 
     * @since 1.0.0
     * @version 1.4.7
     * @return string
     */
    public static function current_phones_senders() {
        $phones_senders = get_option('joinotify_get_phones_senders');

        ob_start();

        if ( ! empty( $phones_senders ) && is_array( $phones_senders ) ) : ?>
            <ul class="list-group joinotify-phone-list">
                <?php foreach ( $phones_senders as $phone ) : ?>
                    <li class="list-group-item d-flex align-items-center py-3 fs-base" data-phone="<?php echo esc_attr( $phone ); ?>">
                        <div class="me-auto">
                            <?php echo esc_html( Helpers::validate_and_format_phone( $phone ) ); ?>
                        </div>

                        <button class="btn btn-icon bg-opacity-0 check-instance-connection joinotify-tooltip" data-phone="<?php echo esc_attr( $phone ); ?>" data-text="<?php esc_attr_e( 'Verificar conexão', 'joinotify' ) ?>"><svg class="icon icon-lg icon-dark" xmlns="http://www.w3.org/2000/svg"viewBox="0 0 24 24"><path d="M10 11H7.101l.001-.009a4.956 4.956 0 0 1 .752-1.787 5.054 5.054 0 0 1 2.2-1.811c.302-.128.617-.226.938-.291a5.078 5.078 0 0 1 2.018 0 4.978 4.978 0 0 1 2.525 1.361l1.416-1.412a7.036 7.036 0 0 0-2.224-1.501 6.921 6.921 0 0 0-1.315-.408 7.079 7.079 0 0 0-2.819 0 6.94 6.94 0 0 0-1.316.409 7.04 7.04 0 0 0-3.08 2.534 6.978 6.978 0 0 0-1.054 2.505c-.028.135-.043.273-.063.41H2l4 4 4-4zm4 2h2.899l-.001.008a4.976 4.976 0 0 1-2.103 3.138 4.943 4.943 0 0 1-1.787.752 5.073 5.073 0 0 1-2.017 0 4.956 4.956 0 0 1-1.787-.752 5.072 5.072 0 0 1-.74-.61L7.05 16.95a7.032 7.032 0 0 0 2.225 1.5c.424.18.867.317 1.315.408a7.07 7.07 0 0 0 2.818 0 7.031 7.031 0 0 0 4.395-2.945 6.974 6.974 0 0 0 1.053-2.503c.027-.135.043-.273.063-.41H22l-4-4-4 4z"></path></svg></button>

                        <?php echo self::display_state_connection( $phone ); ?>

                        <button class="btn btn-sm btn-outline-danger rounded-2 remove-phone-sender" data-phone="<?php echo esc_attr( $phone ); ?>"><?php esc_html_e( 'Remover', 'joinotify' ); ?></button>
                    </li>
                <?php endforeach; ?>
            </ul>
        <?php else : ?>
            <div class="alert alert-info d-flex align-items-center w-fit">
                <svg class="icon icon-lg me-2 icon-info" xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24"><path d="M12 2C6.486 2 2 6.486 2 12s4.486 10 10 10 10-4.486 10-10S17.514 2 12 2zm0 18c-4.411 0-8-3.589-8-8s3.589-8 8-8 8 3.589 8 8-3.589 8-8 8z"></path><path d="M11 11h2v6h-2zm0-4h2v2h-2z"></path></svg>
                <?php esc_html_e( 'Nenhum remetente disponível para uso', 'joinotify' ); ?>
            </div>
        <?php endif;

        return ob_get_clean();
    }


    /**
     * Display OTP input for validation
     * 
     * @since 1.0.0
     * @version 1.4.7
     * @param string $phone | Phone number
     * @return string
     */
    public static function otp_input_code( $phone ) {
        ob_start();

        if ( isset( $phone ) ) : ?>
            <div class="d-grid align-items-center justify-content-center p-4 validate-otp-code" data-phone="<?php echo esc_attr( $phone ) ?>">
                <div class="d-grid align-items-center justify-content-center justify-items-center mb-4">
                    <h3 class="fs-5 mb-3"><?php esc_html_e( 'Verifique seu WhatsApp', 'joinotify' ) ?></h3>
                    <span class="fs-base text-muted mb-2"><?php esc_html_e( 'Informe o código de 4 dígitos que foi enviado para', 'joinotify' ) ?></span>
                    <span class="fw-semibold fs-base"><?php echo esc_html( Helpers::validate_and_format_phone( $phone ) ) ?></span>
                </div>

                <div class="d-flex align-items-center justify-content-center mb-4 otp-input-group">
                    <input type="text" maxlenght="1" class="otp-input-item me-3"/>
                    <input type="text" maxlenght="1" class="otp-input-item me-3"/>
                    <input type="text" maxlenght="1" class="otp-input-item me-3"/>
                    <input type="text" maxlenght="1" class="otp-input-item"/>
                </div>

                <div class="d-flex align-items-center justify-content-center resend-otp">
                    <span class="fs-base text-muted me-1"><?php esc_html_e( 'Reenvie o código em', 'joinotify' ) ?></span>
                    <span class="fw-semibold fs-base me-1 countdown-otp-resend"></span>
                    <span class="fs-base fw-semibold"><?php esc_html_e( 'segundos', 'joinotify' ) ?></span>
                </div>
            </div>
        <?php endif;

        return ob_get_clean();
    }


    /**
     * Display badge for state connection
     * 
     * @since 1.0.0
     * @version 1.4.7
     * @param string $phone | Phone number
     * @return string
     */
    public static function display_state_connection( $phone ) {
        $phone_status = get_option('joinotify_status_connection_'. $phone);

        if ( $phone_status === 'connected' ) {
            $connection_status = '<div class="phone-status badge bg-translucent-success text-success py-2 px-3 rounded-2 mx-3">'. esc_html__( 'Conectado', 'joinotify' ) .'</div>';
        } else {
            $connection_status = '<div class="phone-status badge bg-translucent-danger text-danger py-2 px-3 rounded-2 mx-3">'. esc_html__( 'Desconectado', 'joinotify' ) .'</div>';
        }

        return $connection_status;
    }
}