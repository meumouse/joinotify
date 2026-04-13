<?php

namespace MeuMouse\Joinotify\Assets;

defined('ABSPATH') || exit;

/**
 * Load legacy admin assets for Joinotify screens.
 *
 * @since 1.4.7
 * @package MeuMouse\Joinotify\Assets
 * @author MeuMouse.com
 */
class Core_Assets extends Abstract_Assets {

    /**
     * Register legacy admin asset hooks.
     *
     * @since 1.4.7
     * @version 1.4.7
     * @return void
     */
    public function __construct() {
        parent::__construct();

        // Settings page styles and scripts.
        add_action( 'admin_enqueue_scripts', array( $this, 'settings_assets' ) );

        // License page scripts.
        add_action( 'admin_enqueue_scripts', array( $this, 'license_assets' ) );

        // Workflow builder scripts.
        add_action( 'admin_enqueue_scripts', array( $this, 'builder_assets' ) );

        // Workflows table scripts.
        add_action( 'admin_enqueue_scripts', array( $this, 'workflows_table_assets' ) );
    }


    /**
     * Enqueue assets on the settings page.
     *
     * @since 1.4.7
     * @version 1.4.7
     * @return void
     */
    public function settings_assets() {
        if ( ! joinotify_check_admin_page( 'joinotify-settings' ) ) {
            return;
        }

        $this->enqueue_style_asset(
            'joinotify-styles',
            'admin/css/settings' . $this->min_file . '.css'
        );
    }


    /**
     * Enqueue assets on the license page.
     *
     * @since 1.4.7
     * @version 1.4.7
     * @return void
     */
    public function license_assets() {
        if ( ! joinotify_check_admin_page( 'joinotify-license' ) ) {
            return;
        }

        $this->enqueue_script_asset(
            'joinotify-license-scripts',
            'admin/js/license' . $this->min_file . '.js',
            array( 'jquery' )
        );

        // Keep the legacy localized payload available for the license screen.
        $this->localize_script_asset(
            'joinotify-license-scripts',
            'joinotify_license_params',
            array(
                'debug_mode' => $this->debug_mode,
                'dev_mode'   => $this->dev_mode,
                'ajax_url'   => admin_url( 'admin-ajax.php' ),
                'i18n'       => array(
                    'close_notice_aria_label'   => __( 'Fechar', 'joinotify' ),
                    'confirm_deactivate_license' => __( 'Are you sure you want to deactivate your license?', 'joinotify' ),
                ),
            )
        );
    }


    /**
     * Enqueue scripts on the workflow builder page.
     *
     * @since 1.4.7
     * @version 1.4.7
     * @return void
     */
    public function builder_assets() {
        if ( ! joinotify_check_admin_page( 'joinotify-workflows-builder' ) ) {
            return;
        }

        wp_enqueue_media();

        // Avoid duplicate Bootstrap assets when Flexify Dashboard is active.
        if ( ! class_exists( 'Flexify_Dashboard' ) ) {
            $this->enqueue_style_asset(
                'bootstrap-styles',
                'vendor/bootstrap/css/bootstrap.min.css',
                array(),
                '5.3.3'
            );

            $this->enqueue_script_asset(
                'bootstrap-bundle',
                'vendor/bootstrap/js/bootstrap.bundle.min.js',
                array( 'jquery' ),
                '5.3.3'
            );
        }

        // Datepicker, emoji picker, CodeMirror and Selectize are shared builder dependencies.
        $this->enqueue_style_asset(
            'bootstrap-datepicker-styles',
            'vendor/bootstrap-datepicker/bootstrap-datepicker' . $this->min_file . '.css'
        );
        $this->enqueue_script_asset(
            'bootstrap-datepicker',
            'https://cdnjs.cloudflare.com/ajax/libs/bootstrap-datepicker/1.9.0/js/bootstrap-datepicker.min.js',
            array( 'jquery' ),
            '1.9.0'
        );
        $this->enqueue_style_asset(
            'joinotify-emojionearea-styles',
            'vendor/emojionearea/emojionearea' . $this->min_file . '.css',
            array(),
            '3.4.1'
        );
        $this->enqueue_script_asset(
            'joinotify-emojionearea-scripts',
            'vendor/emojionearea/emojionearea' . $this->min_file . '.js',
            array( 'jquery' ),
            '3.4.1'
        );

        $this->enqueue_style_asset(
            'joinotify-codemirror-styles',
            'vendor/codemirror/lib/codemirror.css',
            array(),
            '5.65.18'
        );
        $this->enqueue_style_asset(
            'joinotify-codemirror-dracula-theme',
            'vendor/codemirror/theme/dracula.css',
            array(),
            '5.65.18'
        );
        $this->enqueue_script_asset(
            'joinotify-codemirror-scripts',
            'vendor/codemirror/lib/codemirror.js',
            array(),
            '5.65.18'
        );
        $this->enqueue_script_asset(
            'joinotify-codemirror-php-mode',
            'vendor/codemirror/mode/php/php.js',
            array(),
            '5.65.18'
        );
        $this->enqueue_script_asset(
            'joinotify-codemirror-clike-mode',
            'vendor/codemirror/mode/clike/clike.js',
            array(),
            '5.65.18'
        );
        $this->enqueue_script_asset(
            'joinotify-codemirror-css-mode',
            'vendor/codemirror/mode/css/css.js',
            array(),
            '5.65.18'
        );
        $this->enqueue_script_asset(
            'joinotify-codemirror-htmlmixed-mode',
            'vendor/codemirror/mode/htmlmixed/htmlmixed.js',
            array(),
            '5.65.18'
        );
        $this->enqueue_script_asset(
            'joinotify-codemirror-javascript-mode',
            'vendor/codemirror/mode/javascript/javascript.js',
            array(),
            '5.65.18'
        );
        $this->enqueue_script_asset(
            'joinotify-codemirror-xml-mode',
            'vendor/codemirror/mode/xml/xml.js',
            array(),
            '5.65.18'
        );
        $this->enqueue_script_asset(
            'joinotify-codemirror-matchbrackets-addon',
            'vendor/codemirror/addon/edit/matchbrackets.js',
            array(),
            '5.65.18'
        );
        $this->enqueue_script_asset(
            'joinotify-codemirror-activeline-addon',
            'vendor/codemirror/addon/selection/active-line.js',
            array(),
            '5.65.18'
        );
        $this->enqueue_script_asset(
            'joinotify-codemirror-matchtags-addon',
            'vendor/codemirror/addon/edit/matchtags.js',
            array(),
            '5.65.18'
        );
        $this->enqueue_script_asset(
            'joinotify-codemirror-closebrackets-addon',
            'vendor/codemirror/addon/edit/closebrackets.js',
            array(),
            '5.65.18'
        );
        $this->enqueue_script_asset(
            'joinotify-codemirror-autorefresh-addon',
            'vendor/codemirror/addon/display/autorefresh.js',
            array(),
            '5.65.18'
        );

        $this->enqueue_style_asset(
            'joinotify-selectize-styles',
            'vendor/selectize/css/selectize.bootstrap5.css',
            array(),
            '0.15.2'
        );
        $this->enqueue_script_asset(
            'joinotify-selectize-scripts',
            'vendor/selectize/js/selectize' . $this->min_file . '.js',
            array( 'jquery' ),
            '0.15.2'
        );

        $this->enqueue_style_asset(
            'joinotify-builder-styles',
            'builder/css/builder' . $this->min_file . '.css'
        );
        $this->enqueue_script_asset(
            'joinotify-builder-scripts',
            'builder/js/builder' . $this->min_file . '.js',
            array( 'jquery', 'media-upload' ),
            $this->version
        );

        $this->localize_script_asset(
            'joinotify-builder-scripts',
            'joinotify_builder_params',
            array(
                'debug_mode' => $this->debug_mode,
                'dev_mode'   => $this->dev_mode,
                'ajax_url'   => admin_url( 'admin-ajax.php' ),
                'nonces'     => array(
                    'import_nonce' => wp_create_nonce( 'joinotify_import_workflow_nonce' ),
                    'export_nonce' => wp_create_nonce( 'joinotify_export_workflow_nonce' ),
                ),
                'i18n'       => array(
                    'status_active'           => esc_html__( 'Ativo', 'joinotify' ),
                    'confirm_exclude_action'   => esc_html__( 'Are you sure you want to delete this action?', 'joinotify' ),
                    'set_media_title'         => esc_html__( 'Escolher midia', 'joinotify' ),
                    'use_this_media_title'    => esc_html__( 'Usar esta midia', 'joinotify' ),
                    'default_workflow_name'   => sprintf( __( 'Minha automacao #%s', 'joinotify' ), random_int( 0, 999999 ) ),
                    'copy_group_id'           => esc_html__( 'ID copiado!', 'joinotify' ),
                    'remove_product_selectize' => esc_html__( 'Remover', 'joinotify' ),
                    'not_templates_found'     => esc_html__( 'Nenhum fluxo encontrado.', 'joinotify' ),
                    'emoji_picker'            => array(
                        'placeholder'      => esc_html__( 'Pesquisar', 'joinotify' ),
                        'button_title'     => esc_html__( 'Press TAB to insert an emoji quickly', 'joinotify' ),
                        'filters'          => array(
                            'tones_title'            => esc_html__( 'Diversidade', 'joinotify' ),
                            'recent_title'           => esc_html__( 'Recentes', 'joinotify' ),
                            'smileys_people_title'   => esc_html__( 'Sorrisos e Pessoas', 'joinotify' ),
                            'animals_nature_title'   => esc_html__( 'Animais e Natureza', 'joinotify' ),
                            'food_drink_title'       => esc_html__( 'Comidas e Bebidas', 'joinotify' ),
                            'activity_title'         => esc_html__( 'Atividades', 'joinotify' ),
                            'travel_places_title'    => esc_html__( 'Viagens e Lugares', 'joinotify' ),
                            'objects_title'          => esc_html__( 'Objetos', 'joinotify' ),
                            'symbols_title'          => esc_html__( 'Simbolos', 'joinotify' ),
                            'flags_title'            => esc_html__( 'Bandeiras', 'joinotify' ),
                        ),
                    ),
                    'offline_toast_header'    => esc_html__( 'Oops! There is no internet connection', 'joinotify' ),
                    'offline_toast_body'      => esc_html__( 'As alteracoes nao serao salvas.', 'joinotify' ),
                ),
            )
        );
    }


    /**
     * Enqueue scripts on the workflows table page.
     *
     * @since 1.4.7
     * @version 1.4.7
     * @return void
     */
    public function workflows_table_assets() {
        if ( ! joinotify_check_admin_page( 'joinotify-workflows' ) || joinotify_check_admin_page( 'joinotify-workflows-builder' ) ) {
            return;
        }

        $this->enqueue_style_asset(
            'joinotify-workflows-table-styles',
            'admin/css/workflows-table' . $this->min_file . '.css'
        );
        $this->enqueue_script_asset(
            'joinotify-workflows-table-scripts',
            'admin/js/workflows-table' . $this->min_file . '.js',
            array( 'jquery' )
        );

        $this->localize_script_asset(
            'joinotify-workflows-table-scripts',
            'joinotify_workflows_table_params',
            array(
                'debug_mode' => $this->debug_mode,
                'dev_mode'   => $this->dev_mode,
                'ajax_url'   => admin_url( 'admin-ajax.php' ),
            )
        );
    }
}
