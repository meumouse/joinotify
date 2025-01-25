<?php

namespace MeuMouse\Joinotify\Admin;

use MeuMouse\Joinotify\Core\Helpers;

// Exit if accessed directly.
defined('ABSPATH') || exit;

/**
 * Class for handle with settings components
 * 
 * @since 1.1.0
 * @package MeuMouse.com
 */
class Components {

    /**
     * Display current phones senders
     * 
     * @since 1.0.0
     * @version 1.1.0
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
                            <?php echo esc_html( Helpers::format_phone_number( $phone ) ); ?>
                        </div>

                        <?php echo self::display_state_connection( $phone ); ?>

                        <button class="btn btn-sm btn-outline-danger rounded-2 remove-phone-sender" data-phone="<?php echo esc_attr( $phone ); ?>"><?php esc_html_e( 'Remover', 'joinotify' ); ?></button>
                    </li>
                <?php endforeach; ?>
            </ul>
        <?php else : ?>
            <div class="alert alert-info d-flex align-items-center w-fit">
                <svg class="icon icon-lg me-2 icon-info" xmlns="http://www.w3.org/2000/svg" width="24" height="24" viewBox="0 0 24 24"><path d="M12 2C6.486 2 2 6.486 2 12s4.486 10 10 10 10-4.486 10-10S17.514 2 12 2zm0 18c-4.411 0-8-3.589-8-8s3.589-8 8-8 8 3.589 8 8-3.589 8-8 8z"></path><path d="M11 11h2v6h-2zm0-4h2v2h-2z"></path></svg>
                <?php esc_html_e( 'Nenhum remetente disponível para uso', 'joinotify' ); ?>
            </div>
        <?php endif;

        return ob_get_clean();
    }


    /**
     * Display badge for state connection
     * 
     * @since 1.0.0
     * @version 1.1.0
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