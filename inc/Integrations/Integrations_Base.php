<?php

namespace MeuMouse\Joinotify\Integrations;

use MeuMouse\Joinotify\Admin\Admin;
use MeuMouse\Joinotify\Builder\Triggers;

// Exit if accessed directly.
defined('ABSPATH') || exit;

/**
 * Abstract base class for integrations
 * 
 * @since 1.0.0
 * @version 1.3.0
 * @package MeuMouse.com
 */
abstract class Integrations_Base {

    /**
     * Add tab items on integration settings tab
     * 
     * @since 1.0.0
     * @version 1.3.0
     * @return array
     */
    public static function integration_tab_items() {
        return apply_filters( 'Joinotify/Settings/Tabs/Integrations', array() );
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
     * @version 1.2.0
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
}