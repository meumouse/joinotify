<?php

namespace MeuMouse\Joinotify\Integrations;

use MeuMouse\Joinotify\Otp_Login\Channel_Registry;
use MeuMouse\Joinotify\Otp_Login\Color_Scheme;
use MeuMouse\Joinotify\Otp_Login\Settings;

// Exit if accessed directly.
defined('ABSPATH') || exit;

/**
 * Passwordless OTP login integration card.
 *
 * Registers the OTP Login card in the Joinotify integrations tab and exposes the
 * delivery channel, theme and sender settings consumed by the login widget.
 *
 * @since 2.0.0
 * @package MeuMouse\Joinotify\Integrations
 * @author MeuMouse.com
 */
class Otp_Login extends Integrations_Base {

    /**
     * Construct function.
     *
     * @since 2.0.0
     * @return void
     */
    public function __construct() {
        add_filter( 'Joinotify/Settings/Tabs/Integrations', array( $this, 'add_integration_item' ), 50, 1 );

        // The OTP login feature is now native; deactivate the legacy companion
        // plugin if it is still active to avoid duplicate cards and routes.
        add_action( 'admin_init', array( $this, 'deactivate_legacy_companion' ) );
    }


    /**
     * Deactivate the standalone "Joinotify OTP Login" companion plugin.
     *
     * @since 2.0.0
     * @return void
     */
    public function deactivate_legacy_companion() {
        $companion = 'joinotify-otp-login/joinotify-otp-login.php';

        if ( ! function_exists( 'is_plugin_active' ) ) {
            require_once ABSPATH . 'wp-admin/includes/plugin.php';
        }

        if ( ! is_plugin_active( $companion ) ) {
            return;
        }

        deactivate_plugins( $companion );

        add_action( 'admin_notices', function() {
            printf(
                '<div class="notice notice-info is-dismissible"><p>%s</p></div>',
                esc_html__( 'The standalone "Joinotify OTP Login" plugin was deactivated because passwordless login is now built into Joinotify.', 'joinotify' )
            );
        });
    }


    /**
     * Add the OTP Login integration card to the settings tab.
     *
     * @since 2.0.0
     * @param array $integrations | Current integrations.
     * @return array
     */
    public function add_integration_item( $integrations ) {
        $integrations['otp_login'] = self::build_integration_item(
            'otp_login',
            esc_html__( 'OTP Login - Passwordless authentication', 'joinotify' ),
            esc_html__( 'Let your users log in securely with a verification code sent through Joinotify, offering a fast passwordless experience.', 'joinotify' ),
            '<svg fill="#000000" version="1.1" xmlns="http://www.w3.org/2000/svg" xmlns:xlink="http://www.w3.org/1999/xlink" viewBox="0 0 512 512" xml:space="preserve"><g stroke-width="0"></g><g stroke-linecap="round"></g><g stroke-linejoin="round"></g><g><g><g> <path d="M469.779,145.37H42.221C18.941,145.37,0,164.31,0,187.591v85.511c0,23.281,18.941,42.221,42.221,42.221H332.96 c4.427,0,8.017-3.589,8.017-8.017s-3.589-8.017-8.017-8.017H42.221c-14.44,0-26.188-11.748-26.188-26.188v-85.511 c0-14.44,11.748-26.188,26.188-26.188h427.557c14.44,0,26.188,11.748,26.188,26.188v42.756c0,4.427,3.589,8.017,8.017,8.017 c4.427,0,8.017-3.589,8.017-8.017v-42.756C512,164.31,493.059,145.37,469.779,145.37z"></path> </g> </g> <g> <g> <path d="M477.795,249.302v-18.956c0-27.995-22.777-50.772-50.772-50.772c-27.995,0-50.772,22.777-50.772,50.772v18.956 c-9.93,3.354-17.102,12.752-17.102,23.8v25.653c0,37.426,30.448,67.875,67.875,67.875c37.426,0,67.875-30.448,67.875-67.875 v-25.653C494.898,262.054,487.725,252.656,477.795,249.302z M392.284,230.347c0-19.155,15.584-34.739,34.739-34.739 c19.155,0,34.739,15.584,34.739,34.739v17.637h-69.478V230.347z M478.864,298.756c0,28.585-23.256,51.841-51.841,51.841 c-28.585,0-51.841-23.256-51.841-51.841v-25.653c0-5.01,4.076-9.086,9.086-9.086h85.511c5.01,0,9.086,4.076,9.086,9.086V298.756z"></path> </g> </g> <g> <g> <path d="M427.023,282.188c-9.136,0-16.568,7.432-16.568,16.568c0,6.228,3.458,11.659,8.551,14.489v5.553 c0,4.427,3.589,8.017,8.017,8.017c4.427,0,8.017-3.589,8.017-8.017v-5.553c5.093-2.829,8.551-8.26,8.551-14.489 C443.591,289.62,436.159,282.188,427.023,282.188z"></path> </g> </g> <g> <g> <path d="M238.324,240.506l-17.597-10.16l17.597-10.16c3.834-2.214,5.148-7.117,2.934-10.951 c-2.214-3.835-7.117-5.149-10.951-2.934l-17.597,10.16v-20.32c0-4.427-3.589-8.017-8.017-8.017s-8.017,3.589-8.017,8.017v20.32 l-17.597-10.16c-3.835-2.215-8.737-0.9-10.951,2.934s-0.9,8.737,2.934,10.951l17.597,10.16l-17.597,10.16 c-3.834,2.214-5.148,7.117-2.934,10.951c1.485,2.572,4.179,4.009,6.95,4.009c1.36,0,2.738-0.346,4.001-1.075l17.597-10.16v20.32 c0,4.427,3.589,8.017,8.017,8.017s8.017-3.589,8.017-8.017v-20.32l17.597,10.16c1.262,0.729,2.641,1.075,4.001,1.075 c2.771,0,5.465-1.439,6.95-4.009C243.471,247.623,242.158,242.72,238.324,240.506z"></path> </g> </g> <g> <g> <path d="M135.71,240.506l-17.597-10.16l17.597-10.16c3.834-2.214,5.148-7.117,2.934-10.951c-2.214-3.835-7.117-5.149-10.951-2.934 l-17.597,10.16v-20.32c0-4.427-3.589-8.017-8.017-8.017s-8.017,3.589-8.017,8.017v20.32l-17.597-10.16 c-3.835-2.215-8.737-0.9-10.951,2.934s-0.9,8.737,2.934,10.951l17.597,10.16l-17.597,10.16c-3.834,2.214-5.148,7.117-2.934,10.951c1.485,2.572,4.179,4.009,6.95,4.009c1.36,0,2.738-0.346,4.001-1.075l17.597-10.16v20.32c0,4.427,3.589,8.017,8.017,8.017 s8.017-3.589,8.017-8.017v-20.32l17.597,10.16c1.262,0.729,2.641,1.075,4.001,1.075c2.771,0,5.465-1.439,6.95-4.009 C140.858,247.623,139.544,242.72,135.71,240.506z"></path> </g> </g> <g> <g> <path d="M340.938,240.506l-17.597-10.16l17.597-10.16c3.834-2.214,5.148-7.117,2.934-10.951 c-2.214-3.835-7.117-5.149-10.951-2.934l-17.597,10.16v-20.32c0-4.427-3.589-8.017-8.017-8.017s-8.017,3.589-8.017,8.017v20.32 l-17.597-10.16c-3.835-2.215-8.737-0.9-10.951,2.934s-0.9,8.737,2.934,10.951l17.597,10.16l-17.597,10.16 c-3.834,2.214-5.148,7.117-2.934,10.951c1.485,2.572,4.179,4.009,6.95,4.009c1.36,0,2.739-0.346,4.001-1.075l17.597-10.16v20.32 c0,4.427,3.589,8.017,8.017,8.017s8.017-3.589,8.017-8.017v-20.32l17.597,10.16c1.262,0.729,2.641,1.075,4.001,1.075 c2.771,0,5.465-1.439,6.95-4.009C346.085,247.623,344.772,242.72,340.938,240.506z"></path></g></g></g></svg>',
            array(
                'category' => 'security',
                'setting_key' => 'enable_otp_login_integration',
                'action_hook' => 'Joinotify/Settings/Tabs/Integrations/Otp_Login',
                'settings' => self::get_integration_settings(),
                'defaults' => self::get_integration_defaults(),
                'modal' => array(
                    'title' => __( 'Passwordless login settings', 'joinotify' ),
                    'description' => __( 'Configure how verification codes are delivered, the visual theme, and the sender used for the login form.', 'joinotify' ),
                    'button_label' => __( 'Configure', 'joinotify' ),
                ),
            )
        );

        return $integrations;
    }


    /**
     * Declarative settings rendered in the integration modal.
     *
     * @since 2.0.0
     * @return array<int,array<string,mixed>>
     */
    public static function get_integration_settings() {
        return array(
            self::field_select(
                'otp_login_channel',
                esc_html__( 'Delivery channel', 'joinotify' ),
                esc_html__( 'How the verification code is sent to the user. New channels (e-mail, Telegram) can be added by extensions.', 'joinotify' ),
                Channel_Registry::get_channel_options(),
                array(
                    'default' => 'whatsapp',
                )
            ),
            self::field_component(
                'otp_login_primary_color',
                'color',
                'color-picker',
                esc_html__( 'Primary color', 'joinotify' ),
                esc_html__( 'Base color used to theme the login form. The full shade palette is derived automatically.', 'joinotify' ),
                array(
                    'default' => '#4f46e5',
                )
            ),
            self::field_text(
                'otp_login_border_radius_value',
                esc_html__( 'Border radius', 'joinotify' ),
                esc_html__( 'Rounding applied to cards, buttons, and form fields.', 'joinotify' ),
                array(
                    'placeholder' => '0.375',
                    'inputmode' => 'decimal',
                    'default' => '0.375',
                )
            ),
            self::field_select(
                'otp_login_border_radius_unit',
                esc_html__( 'Border radius unit', 'joinotify' ),
                esc_html__( 'Measurement unit applied to the border radius value.', 'joinotify' ),
                array(
                    array( 'value' => 'px', 'label' => esc_html__( 'Pixel', 'joinotify' ) ),
                    array( 'value' => 'em', 'label' => esc_html__( 'EM', 'joinotify' ) ),
                    array( 'value' => 'rem', 'label' => esc_html__( 'REM', 'joinotify' ) ),
                    array( 'value' => '%', 'label' => esc_html__( 'Percentage', 'joinotify' ) ),
                ),
                array(
                    'default' => 'rem',
                )
            ),
            self::field_select(
                'otp_login_sender_phone',
                esc_html__( 'Sender phone', 'joinotify' ),
                esc_html__( 'Registered Joinotify number used to deliver OTP codes. Leave on the first available sender to use the default.', 'joinotify' ),
                self::get_sender_options(),
                array(
                    'default' => '',
                )
            ),
        );
    }


    /**
     * Build the sender select options from the available Joinotify senders.
     *
     * @since 2.0.0
     * @return array<int,array{value:string,label:string}>
     */
    private static function get_sender_options() {
        $options = array(
            array(
                'value' => '',
                'label' => esc_html__( 'Use the first available sender', 'joinotify' ),
            ),
        );

        foreach ( Settings::get_available_senders() as $sender ) {
            $options[] = array(
                'value' => $sender,
                'label' => $sender,
            );
        }

        return $options;
    }


    /**
     * Default option values declared by this integration card.
     *
     * @since 2.0.0
     * @return array<string,mixed>
     */
    public static function get_integration_defaults() {
        $palette = array();

        foreach ( Color_Scheme::generate_palette( '#4f46e5' ) as $row ) {
            if ( empty( $row['step'] ) || empty( $row['color'] ) ) {
                continue;
            }

            $palette[ (string) $row['step'] ] = (string) $row['color'];
        }

        return array(
            'enable_otp_login_integration' => 'no',
            'otp_login_channel' => 'whatsapp',
            'otp_login_primary_color' => '#4f46e5',
            'otp_login_palette' => $palette,
            'otp_login_border_radius_value' => '0.375',
            'otp_login_border_radius_unit' => 'rem',
            'otp_login_sender_phone' => '',
        );
    }
}
