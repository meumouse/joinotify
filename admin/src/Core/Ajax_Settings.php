<?php

namespace MeuMouse\Joinotify\Core;

use MeuMouse\Joinotify\Admin\Admin;
use MeuMouse\Joinotify\Api\Controller;
use WP_Query;

defined('ABSPATH') || exit;

/**
 * AJAX callbacks related to general settings, modules, and miscellaneous operations.
 *
 * Handles: save options, reset plugin, dismiss tip, fetch groups,
 *          save action/trigger settings, send test message,
 *          install modules, activate plugin, get WooCommerce products.
 *
 * @since 1.4.7
 * @package MeuMouse\Joinotify\Core
 */
class Ajax_Settings {

    /**
     * Register AJAX actions.
     *
     * @since 1.4.7
     */
    public function __construct() {
        $actions = array(
            'joinotify_save_options'           => 'admin_save_options_callback',
            'joinotify_reset_plugin_action'    => 'reset_plugin_callback',
            'joinotify_dismiss_placeholders_tip' => 'dismiss_placeholders_tip_callback',
            'joinotify_fetch_all_groups'       => 'fetch_all_groups_callback',
            'joinotify_save_action_edition'    => 'save_action_settings_callback',
            'joinotify_save_trigger_settings'  => 'save_trigger_settings_callback',
            'joinotify_send_message_test'      => 'send_message_test_callback',
            'joinotify_install_modules'        => 'install_modules_ajax_callback',
            'joinotify_activate_plugin'        => 'activate_plugin_callback',
            'joinotify_get_woo_products'       => 'get_woo_products_callback',
        );

        foreach ( $actions as $action => $callback ) {
            add_action( "wp_ajax_{$action}", array( $this, $callback ) );
        }
    }


    /**
     * Save options on AJAX callback.
     *
     * @since 1.0.0
     * @return void
     */
    public function admin_save_options_callback() {
        if ( isset( $_POST['action'] ) && $_POST['action'] === 'joinotify_save_options' ) {
            parse_str( $_POST['form_data'], $form_data );

            $switch_options = apply_filters( 'Joinotify/Admin/Ajax/Save_Options', Helpers::get_switch_options() );

            foreach ( $switch_options as $switch ) {
                $options[ $switch ] = isset( $form_data[ $switch ] ) ? 'yes' : 'no';
            }

            $updated_options = wp_parse_args( $form_data, $options );
            $saved_options   = update_option( 'joinotify_settings', $updated_options );

            if ( $saved_options ) {
                $response = array(
                    'status'             => 'success',
                    'toast_header_title' => esc_html__( 'Saved successfully', 'joinotify' ),
                    'toast_body_title'   => esc_html__( 'Settings updated successfully!', 'joinotify' ),
                );

                if ( defined( 'JOINOTIFY_DEBUG_MODE' ) && JOINOTIFY_DEBUG_MODE ) {
                    $response['debug'] = array( 'options' => $updated_options );
                }

                wp_send_json( $response );
            }
        }
    }


    /**
     * Reset plugin options on AJAX callback.
     *
     * @since 1.0.0
     * @return void
     */
    public function reset_plugin_callback() {
        if ( isset( $_POST['action'] ) && $_POST['action'] === 'joinotify_reset_plugin_action' ) {
            $delete_option = delete_option( 'joinotify_settings' );

            if ( $delete_option ) {
                delete_option( 'joinotify_alternative_license_activation' );
                Cache_Helper::clear_license_cache();
                delete_user_meta( get_current_user_id(), 'joinotify_dismiss_placeholders_tip_user_meta' );

                $response = array(
                    'status'             => 'success',
                    'toast_header_title' => esc_html__( 'Options reset', 'joinotify' ),
                    'toast_body_title'   => esc_html__( 'The options were reset successfully!', 'joinotify' ),
                );
            } else {
                $response = array(
                    'status'             => 'error',
                    'toast_header_title' => esc_html__( 'Oops! An error occurred.', 'joinotify' ),
                    'toast_body_title'   => esc_html__( 'An error occurred while resetting the settings.', 'joinotify' ),
                );
            }

            wp_send_json( $response );
        }
    }


    /**
     * Dismiss placeholders tip on AJAX callback.
     *
     * @since 1.1.0
     * @return void
     */
    public function dismiss_placeholders_tip_callback() {
        if ( isset( $_POST['action'] ) && $_POST['action'] === 'joinotify_dismiss_placeholders_tip' ) {
            $user_id             = get_current_user_id();
            $dismiss_placeholders = update_user_meta( $user_id, 'joinotify_dismiss_placeholders_tip_user_meta', 'hidden' );

            if ( $dismiss_placeholders ) {
                $response = array(
                    'status'      => 'success',
                    'get_user_meta' => get_user_meta( $user_id, 'joinotify_dismiss_placeholders_tip_user_meta', true ),
                );
            } else {
                $response = array( 'status' => 'error' );
            }

            wp_send_json( $response );
        }
    }


    /**
     * Fetch all groups information on AJAX callback.
     *
     * @since 1.1.0
     * @return void
     */
    public function fetch_all_groups_callback() {
        if ( isset( $_POST['action'] ) && $_POST['action'] === 'joinotify_fetch_all_groups' ) {
            $sender       = isset( $_POST['sender'] ) ? sanitize_text_field( $_POST['sender'] ) : '';
            $fetch_groups = Controller::fetch_all_groups( $sender );
            $groups_html  = '';

            if ( $fetch_groups && ! isset( $fetch_groups['status'] ) ) {
                $groups_html .= '<div id="joinotify_groups_list_details" class="list-group">';

                foreach ( $fetch_groups as $group ) {
                    $group_id    = isset( $group['id'] )         ? esc_attr( $group['id'] )         : '';
                    $group_name  = isset( $group['subject'] )    ? esc_html( $group['subject'] )    : '';
                    $group_owner = isset( $group['owner'] )      ? esc_html( $group['owner'] )      : '';
                    $group_size  = isset( $group['size'] )       ? esc_html( $group['size'] )       : '';
                    $group_desc  = ! empty( $group['desc'] )     ? esc_html( $group['desc'] )       : esc_html__( 'No description available', 'joinotify' );
                    $group_image = ! empty( $group['pictureUrl'] ) ? esc_url( $group['pictureUrl'] ) : JOINOTIFY_ASSETS . 'builder/img/empty-profile-avatar.svg';

                    $groups_html .= '<a href="#" class="list-group-item list-group-item-action d-flex align-items-center shadow-none get-group-id" data-group-id="' . $group_id . '">
                        <img src="' . $group_image . '" class="rounded-circle me-3" alt="' . $group_name . '" width="50" height="50">
                        <div>
                            <h5 class="mb-1">' . $group_name . '</h5>
                            <p class="mb-1 text-muted">' . sprintf( __( 'Owner: %s | Members: %s', 'joinotify' ), $group_owner, $group_size ) . '</p>
                            <small>' . $group_desc . '</small>
                        </div>
                    </a>';
                }

                $groups_html .= '</div>';
            }

            $response = array(
                'status'             => 'success',
                'groups_details_html' => $groups_html,
            );

            if ( $fetch_groups && isset( $fetch_groups['status'] ) && $fetch_groups['status'] === 404 ) {
                $response['status']             = 'error';
                $response['toast_header_title'] = esc_html__( 'Ops! An error occurred', 'joinotify' );
                $response['toast_body_title']   = esc_html__( 'Could not retrieve group information.', 'joinotify' );
            }

            wp_send_json( $response );
        }
    }


    /**
     * Save action edition settings on AJAX callback.
     *
     * @since 1.1.0
     * @return void
     */
    public function save_action_settings_callback() {
        if ( isset( $_POST['action'] ) && $_POST['action'] === 'joinotify_save_action_edition' ) {
            $post_id         = isset( $_POST['post_id'] )        ? sanitize_text_field( $_POST['post_id'] ) : '';
            $action_id       = isset( $_POST['action_id'] )      ? sanitize_text_field( $_POST['action_id'] ) : '';
            $new_action_data = isset( $_POST['new_action_data'] ) ? json_decode( stripslashes( $_POST['new_action_data'] ), true ) : array();

            if ( $post_id && get_post_type( $post_id ) === 'joinotify-workflow' ) {
                if ( isset( $new_action_data['data']['action'] ) && $new_action_data['data']['action'] === 'time_delay' ) {
                    $new_action_data = $this->apply_delay_timestamp( $new_action_data );
                }

                $workflow_content = Helpers::get_workflow_content_meta( $post_id );

                if ( empty( $workflow_content ) ) {
                    $workflow_content = array();
                }

                $updated = false;

                foreach ( $workflow_content as &$item ) {
                    if ( \MeuMouse\Joinotify\Builder\Actions::update_action_by_id( $item, $action_id, $new_action_data ) ) {
                        $updated = true;
                        break;
                    }
                }

                if ( $updated ) {
                    $updated_workflow = Helpers::update_workflow_content_meta( $post_id, $workflow_content );

                    if ( $updated_workflow ) {
                        $response = array(
                            'status'             => 'success',
                            'toast_header_title' => esc_html__( 'Action updated', 'joinotify' ),
                            'toast_body_title'   => esc_html__( 'The action was updated successfully!', 'joinotify' ),
                            'workflow_content'   => \MeuMouse\Joinotify\Builder\Workflow_Manager::get_workflow_content( $post_id ),
                        );
                    } else {
                        $response = array(
                            'status'             => 'error',
                            'toast_header_title' => esc_html__( 'Ops! An error occurred', 'joinotify' ),
                            'toast_body_title'   => esc_html__( 'Could not update the action.', 'joinotify' ),
                        );
                    }
                } else {
                    $response = array(
                        'status'             => 'error',
                        'toast_header_title' => esc_html__( 'Ops! An error occurred', 'joinotify' ),
                        'toast_body_title'   => esc_html__( 'Could not find the action to update.', 'joinotify' ),
                    );
                }

                wp_send_json( $response );
            }
        }
    }


    /**
     * Save trigger settings on AJAX callback.
     *
     * @since 1.1.0
     * @return void
     */
    public function save_trigger_settings_callback() {
        if ( isset( $_POST['action'] ) && $_POST['action'] === 'joinotify_save_trigger_settings' ) {
            $post_id          = isset( $_POST['post_id'] )   ? intval( $_POST['post_id'] ) : null;
            $trigger_id       = isset( $_POST['trigger_id'] ) ? sanitize_text_field( $_POST['trigger_id'] ) : '';
            $trigger_settings = isset( $_POST['settings'] )  ? json_decode( stripslashes( $_POST['settings'] ), true ) : array();

            if ( $post_id && get_post_type( $post_id ) === 'joinotify-workflow' ) {
                $workflow_content = Helpers::get_workflow_content_meta( $post_id );

                if ( empty( $workflow_content ) ) {
                    $workflow_content = array();
                }

                $updated = false;

                foreach ( $workflow_content as &$item ) {
                    if ( \MeuMouse\Joinotify\Builder\Triggers::update_trigger_settings_by_id( $item, $trigger_id, $trigger_settings ) ) {
                        $updated = true;
                        break;
                    }
                }

                if ( $updated ) {
                    $updated_workflow = Helpers::update_workflow_content_meta( $post_id, $workflow_content );

                    if ( $updated_workflow ) {
                        $response = array(
                            'status'             => 'success',
                            'toast_header_title' => esc_html__( 'Trigger updated', 'joinotify' ),
                            'toast_body_title'   => esc_html__( 'The trigger was updated successfully!', 'joinotify' ),
                        );
                    } else {
                        $response = array(
                            'status'             => 'error',
                            'toast_header_title' => esc_html__( 'Ops! An error occurred', 'joinotify' ),
                            'toast_body_title'   => esc_html__( 'Could not update the trigger.', 'joinotify' ),
                        );
                    }
                } else {
                    $response = array(
                        'status'             => 'error',
                        'toast_header_title' => esc_html__( 'Ops! An error occurred', 'joinotify' ),
                        'toast_body_title'   => esc_html__( 'Could not find the trigger to update.', 'joinotify' ),
                    );
                }

                wp_send_json( $response );
            }
        }
    }


    /**
     * Send test message on AJAX callback.
     *
     * @since 1.0.0
     * @return void
     */
    public function send_message_test_callback() {
        if ( isset( $_POST['action'] ) && $_POST['action'] === 'joinotify_send_message_test' ) {
            $sender   = isset( $_POST['sender'] )   ? sanitize_text_field( $_POST['sender'] )   : '';
            $receiver = isset( $_POST['receiver'] ) ? sanitize_text_field( $_POST['receiver'] ) : '';
            $message  = isset( $_POST['message'] )  ? sanitize_text_field( $_POST['message'] )  : '';

            if ( 201 === Controller::send_message_text( $sender, $receiver, $message ) ) {
                $response = array(
                    'status'             => 'success',
                    'toast_header_title' => __( 'Message sent', 'joinotify' ),
                    'toast_body_title'   => __( 'The test message was sent successfully!', 'joinotify' ),
                );
            } else {
                Controller::get_connection_state( $sender );

                $response = array(
                    'status'             => 'error',
                    'toast_header_title' => __( 'Ops! An error occurred', 'joinotify' ),
                    'toast_body_title'   => __( 'Could not send the test message.', 'joinotify' ),
                );
            }

            wp_send_json( $response );
        }
    }


    /**
     * Install external plugin module on AJAX callback.
     *
     * @since 1.2.0
     * @return void
     */
    public function install_modules_ajax_callback() {
        if ( isset( $_POST['action'] ) && $_POST['action'] === 'joinotify_install_modules' ) {
            $plugin_slug = sanitize_text_field( $_POST['plugin_slug'] );
            $plugin_zip  = esc_url_raw( $_POST['plugin_url'] );

            ob_start();

            if ( Modules::is_plugin_installed( $plugin_slug ) ) {
                $installed = Modules::upgrade_plugin( $plugin_slug );
            } else {
                $installed = Modules::install_plugin( $plugin_zip );
            }

            ob_end_clean();

            if ( ! is_wp_error( $installed ) && $installed ) {
                $plugin_file = WP_PLUGIN_DIR . '/' . $plugin_slug;
                $activate    = activate_plugin( $plugin_file );

                if ( ! is_wp_error( $activate ) ) {
                    $response = array(
                        'status'             => 'success',
                        'toast_header_title' => esc_html__( 'Plugin installed and activated.', 'joinotify' ),
                        'toast_body_title'   => esc_html__( 'Plugin installed and activated successfully.', 'joinotify' ),
                    );
                } else {
                    $response = array(
                        'status'             => 'error',
                        'toast_header_title' => esc_html__( 'Failed to activate the plugin.', 'joinotify' ),
                        'toast_body_title'   => esc_html__( 'The plugin was installed but could not be activated.', 'joinotify' ),
                    );
                }
            } else {
                $response = array(
                    'status'             => 'error',
                    'toast_header_title' => esc_html__( 'Failed to install/update the plugin.', 'joinotify' ),
                    'toast_body_title'   => esc_html__( 'An error occurred while trying to install or update the plugin.', 'joinotify' ),
                );
            }

            wp_send_json( $response );
        }
    }


    /**
     * Activate an installed plugin on AJAX callback.
     *
     * @since 1.2.0
     * @return void
     */
    public function activate_plugin_callback() {
        if ( isset( $_POST['action'] ) && $_POST['action'] === 'joinotify_activate_plugin' ) {
            $plugin_slug = sanitize_text_field( $_POST['plugin_slug'] );
            $activate    = activate_plugin( $plugin_slug );

            if ( is_wp_error( $activate ) ) {
                $response = array(
                    'status'             => 'error',
                    'toast_header_title' => esc_html__( 'Oops! An error occurred.', 'joinotify' ),
                    'toast_body_title'   => $activate->get_error_message(),
                );
            } else {
                $response = array(
                    'status'             => 'success',
                    'toast_header_title' => esc_html__( 'Plugin activated successfully.', 'joinotify' ),
                    'toast_body_title'   => esc_html__( 'New feature added!', 'joinotify' ),
                );
            }

            wp_send_json( $response );
        }
    }


    /**
     * Get WooCommerce products on AJAX callback.
     *
     * @since 1.1.0
     * @return void
     */
    public function get_woo_products_callback() {
        if ( isset( $_POST['action'] ) && $_POST['action'] === 'joinotify_get_woo_products' ) {
            $search_query = sanitize_text_field( $_POST['search_query'] );

            $args = array(
                'post_type'      => array( 'product', 'product_variation' ),
                'status'         => 'publish',
                'posts_per_page' => -1,
                's'              => $search_query,
            );

            $products = new WP_Query( $args );
            $results  = array();

            if ( $products->have_posts() ) {
                while ( $products->have_posts() ) {
                    $products->the_post();

                    $results[] = array(
                        'id'            => get_the_ID(),
                        'product_title' => html_entity_decode( get_the_title(), ENT_QUOTES, 'UTF-8' ),
                    );
                }
            }

            wp_reset_postdata();
            wp_send_json( $results );
        }
    }


    /**
     * Apply delay_timestamp to a time_delay action data array.
     *
     * @since 1.4.7
     * @param array $action_data
     * @return array
     */
    private function apply_delay_timestamp( array $action_data ) {
        $delay_type = $action_data['data']['delay_type'] ?? 'period';

        if ( $delay_type === 'period' ) {
            $delay_value  = (int) ( $action_data['data']['delay_value'] ?? 0 );
            $delay_period = $action_data['data']['delay_period'] ?? 'seconds';

            $action_data['data']['delay_timestamp'] = \MeuMouse\Joinotify\Cron\Schedule::get_delay_timestamp( $delay_value, $delay_period );
        } elseif ( $delay_type === 'scheduled' ) {
            $delay_value  = (int) ( $action_data['data']['delay_value'] ?? 0 );
            $delay_period = $action_data['data']['delay_period'] ?? 'day';
            $time_value   = $action_data['data']['time_value'] ?? '00:00';

            $action_data['data']['delay_timestamp'] = \MeuMouse\Joinotify\Cron\Schedule::get_scheduled_delay_timestamp( $delay_value, $delay_period, $time_value );
        } elseif ( $delay_type === 'date' ) {
            $date_value = $action_data['data']['date_value'] ?? '';
            $time_value = $action_data['data']['time_value'] ?? '00:00';

            if ( ! empty( $date_value ) ) {
                $timestamp = strtotime( $date_value . ' ' . $time_value );

                if ( $timestamp ) {
                    $action_data['data']['delay_timestamp'] = $timestamp;
                }
            }
        }

        return $action_data;
    }
}
