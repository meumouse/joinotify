<?php
/**
 * Integrations_Base source file.
 *
 * @since 1.4.7
 * @version 1.4.7
 */

namespace MeuMouse\Joinotify\Integrations;

use MeuMouse\Joinotify\Admin\Admin;
use MeuMouse\Joinotify\Builder\Triggers;

// Exit if accessed directly.
defined('ABSPATH') || exit;

/**
 * Abstract base class for integrations
 * 
 * @since 1.0.0
 * @version 1.4.7
 * @package MeuMouse\Joinotify\Integrations
 * @author MeuMouse.com
 */
abstract class Integrations_Base {

    /**
     * Add tab items on integration settings tab
     * 
     * @since 1.0.0
     * @version 1.4.7
     * @return array
     */
    public static function integration_tab_items() {
        return apply_filters( 'Joinotify/Settings/Tabs/Integrations', array() );
    }


    /**
     * Build a normalized integration card payload.
     *
     * This helper gives external plugins a small, consistent contract for
     * adding integrations to the settings screen without re-creating the
     * internal array shape.
     *
     * @since 1.4.6
     * @param string $slug Integration slug.
     * @param string $title Integration title.
     * @param string $description Integration description.
     * @param string $icon SVG markup for the card icon.
     * @param array  $args Extra integration arguments.
     * @return array<string,mixed>
     */
    public static function build_integration_item( $slug, $title, $description, $icon, $args = array() ) {
        $slug = sanitize_key( $slug );

        return wp_parse_args( $args, array(
            'title'         => $title,
            'description'   => $description,
            'icon'          => $icon,
            'setting_key'   => '',
            'action_hook'   => self::get_integration_action_hook( $slug ),
            'is_plugin'     => false,
            'plugin_active' => array(),
            'comming_soon'  => false,
            'fields'        => array(),
        ) );
    }


    /**
     * Return the action hook used by the native integration modal.
     *
     * External plugins can hook into this action and render custom controls
     * inside the Joinotify modal container.
     *
     * @since 1.4.6
     * @param string $slug Integration slug.
     * @return string
     */
    public static function get_integration_action_hook( $slug ) {
        return sprintf( 'Joinotify/Settings/Tabs/Integrations/%s', sanitize_key( $slug ) );
    }


    /**
     * Render the native integration settings modal.
     *
     * The body can be provided either through a callback or by attaching a
     * listener to the integration action hook returned by
     * `get_integration_action_hook()`.
     *
     * @since 1.4.6
     * @param string   $slug Integration slug.
     * @param array    $args Modal arguments.
     * @param callable $content_callback Optional callback that prints the modal body.
     * @return void
     */
    public static function render_integration_settings_modal( $slug, $args = array(), $content_callback = null ) {
        $defaults = array(
            'title'                 => esc_html__( 'Configurações da integração', 'joinotify' ),
            'description'           => '',
            'button_label'          => esc_html__( 'Configurações', 'joinotify' ),
            'setting_key'           => '',
            'action_hook'           => self::get_integration_action_hook( $slug ),
            'modal_size_class'      => 'popup-lg',
            'container_class'       => 'joinotify-popup-container',
            'content_class'         => 'joinotify-popup-body my-3',
            'title_class'           => 'joinotify-popup-title',
            'header_class'          => 'joinotify-popup-header',
            'button_class'          => 'btn btn-outline-primary mb-5',
            'close_button_class'    => 'btn-close fs-lg',
            'show_when_disabled'    => false,
            'render_button'         => true,
        );

        $args = wp_parse_args( $args, $defaults );

        if ( ! empty( $args['setting_key'] ) && ! self::is_setting_enabled( $args['setting_key'] ) && ! $args['show_when_disabled'] ) {
            return;
        }

        $slug = sanitize_key( $slug );
        $trigger_id = $slug . '_settings_trigger';
        $container_id = $slug . '_settings_container';
        $close_id = $slug . '_settings_close';

        if ( $args['render_button'] ) : ?>
            <button id="<?php echo esc_attr( $trigger_id ); ?>" class="<?php echo esc_attr( $args['button_class'] ); ?>">
                <?php echo esc_html( $args['button_label'] ); ?>
            </button>
        <?php endif; ?>

        <div id="<?php echo esc_attr( $container_id ); ?>" class="<?php echo esc_attr( $args['container_class'] ); ?>">
            <div class="joinotify-popup-content <?php echo esc_attr( $args['modal_size_class'] ); ?>">
                <div class="<?php echo esc_attr( $args['header_class'] ); ?>">
                    <h5 class="<?php echo esc_attr( $args['title_class'] ); ?>">
                        <?php echo esc_html( $args['title'] ); ?>
                    </h5>
                    <button id="<?php echo esc_attr( $close_id ); ?>" class="<?php echo esc_attr( $args['close_button_class'] ); ?>" aria-label="<?php esc_attr_e( 'Fechar', 'joinotify' ); ?>"></button>
                </div>

                <?php if ( ! empty( $args['description'] ) ) : ?>
                    <div class="px-4 pt-3">
                        <p class="joinotify-description mb-0"><?php echo esc_html( $args['description'] ); ?></p>
                    </div>
                <?php endif; ?>

                <div class="<?php echo esc_attr( $args['content_class'] ); ?>">
                    <?php
                    if ( is_callable( $content_callback ) ) {
                        call_user_func( $content_callback, $args );
                    } else {
                        do_action( $args['action_hook'] );
                    }
                    ?>
                </div>
            </div>
        </div>
        <?php
    }


    /**
     * Render a trigger tab on builder sidebar
     * 
     * @since 1.1.0
     * @param string $slug | Integration slug (eg: 'wordpress')
     * @param string $name | Integration name (eg: esc_html__( 'WordPress', 'text-domain' ) )
     * @param string $icon_svg | SVG icon code
     * @return void
     */
    protected function render_integration_trigger_tab( $slug, $name, $icon ) {
        if ( Admin::get_setting("enable_{$slug}_integration") === 'yes' ) : ?>
            <a href="#<?php echo esc_attr( $slug ); ?>" class="nav-tab">
                <?php echo $icon; // SVG icon ?>
                <?php echo $name; ?>
            </a>
        <?php endif;
    }


    /**
     * Render the trigger content
     * 
     * @since 1.1.0
     * @version 1.4.7
     * @param string $slug | Slug da integração (eg: 'wordpress')
     * @return void
     */
    protected function render_integration_trigger_content( $slug ) {
        if ( Admin::get_setting("enable_{$slug}_integration") === 'yes' ) : ?>
            <div id="<?php echo esc_attr( $slug ); ?>" class="nav-content triggers-group">
                <?php foreach ( Triggers::get_triggers_by_context( $slug ) as $trigger ) :
                    // disable trigger if plugin is not active or not installed
                    $disable_select_trigger = isset( $trigger['require_plugins'] ) && array_key_exists( $trigger['plugins'][0]['slug'], get_plugins() ) && ! is_plugin_active( $trigger['plugins'][0]['slug'] )
                    || isset( $trigger['require_plugins'] ) && ! array_key_exists( $trigger['plugins'][0]['slug'], get_plugins() ); ?>

                    <div class="trigger-item <?php echo esc_attr( isset( $trigger['class'] ) ? $trigger['class'] : '' ); ?> <?php echo esc_attr( $disable_select_trigger  ? 'require-plugins' : '' ); ?>" data-context="<?php echo esc_attr( $slug ); ?>" data-trigger="<?php echo esc_attr( $trigger['data_trigger'] ); ?>">
                        <h4 class="title"><?php echo esc_html( $trigger['title'] ); ?></h4>
                        <span class="description"><?php echo esc_html( $trigger['description'] ); ?></span>

                        <?php if ( isset( $trigger['class'] ) && $trigger['class'] === 'locked' ) : ?>
                            <span class="fs-sm mt-3"><?php esc_html_e( 'Este recurso será liberado em breve', 'joinotify' ); ?></span>
                        <?php endif; ?>

                        <!-- Install trigger dependencies -->
                        <?php if ( isset( $trigger['require_plugins'] ) && $trigger['require_plugins'] === true ) : ?>
                            <?php foreach ( $trigger['plugins'] as $plugin => $item ) : ?>
                                <?php if ( array_key_exists( $item['slug'], get_plugins() ) && ! is_plugin_active( $item['slug'] ) ) : ?>
                                    <span class="fs-sm my-3"><?php esc_html_e( 'Este acionamento depende de um plugin', 'joinotify' ); ?></span>

                                    <button class="btn btn-sm btn-outline-secondary activate-plugin mb-2" data-plugin-slug="<?php echo esc_attr( $item['slug'] ) ?>" data-bs-toggle="tooltip" data-bs-placement="bottom" data-bs-custom-class="joinotify-tooltip" data-bs-title="<?php echo esc_attr( $item['name'] ) ?>"><?php esc_html_e( 'Ativar plugin', 'joinotify' ) ?></button>
                                <?php elseif ( ! array_key_exists( $item['slug'], get_plugins() ) ) : ?>
                                    <span class="fs-sm my-3"><?php esc_html_e( 'Este acionamento depende de um plugin', 'joinotify' ); ?></span>

                                    <button class="btn btn-sm btn-outline-secondary install-required-plugin mb-2" data-download-url="<?php echo esc_attr( $item['download_url'] ) ?>" data-required-plugin="<?php echo esc_attr( $item['slug'] ) ?>" data-bs-toggle="tooltip" data-bs-placement="bottom" data-bs-custom-class="joinotify-tooltip" data-bs-title="<?php echo esc_attr( $item['name'] ) ?>"><?php esc_html_e( 'Instalar plugin', 'joinotify' ) ?></button>
                                <?php endif; ?>
                            <?php endforeach; ?>
                        <?php endif; ?>
                    </div>
                <?php endforeach; ?>
            </div>
        <?php endif;
    }


    /**
     * Check if an integration setting is enabled.
     *
     * @since 1.4.6
     * @param string $setting_key Setting option key.
     * @return bool
     */
    protected static function is_setting_enabled( $setting_key ) {
        if ( empty( $setting_key ) ) {
            return false;
        }

        return Admin::get_setting( $setting_key ) === 'yes';
    }
}
