<?php

namespace MeuMouse\Joinotify\Builder;

use MeuMouse\Joinotify\Core\Admin;
use MeuMouse\Joinotify\Core\Components;
use MeuMouse\Joinotify\Core\Helpers;

// Exit if accessed directly.
defined('ABSPATH') || exit;

/**
 * This class manages the entire core of the workflow builder, registration of triggers, actions, etc.
 * 
 * @since 1.0.0
 * @package MeuMouse.com
 */
class Core {

    /**
     * Construct function
     * 
     * @since 1.0.0
     * @return void
     */
    public function __construct() {
        // add modal content for edit workflow title
        add_action( 'in_admin_header', array( 'MeuMouse\Joinotify\Core\Components', 'workflow_title_modal_content' ) );

    /*    if ( Admin::get_setting('enable_advanced_trigger') === 'yes' ) {
            // add advanced custom trigger
            add_action( 'Joinotify/Builder/Triggers', array( $this, 'add_advanced_custom_trigger' ), 100 );

            // add advanced custom trigger tab content
            add_action( 'Joinotify/Builder/Triggers_Content', array( $this, 'add_advanced_custom_trigger_tab_content' ) );

            // add advanded trigger on all triggers list
            add_filter( 'Joinotify/Builder/Get_All_Triggers', array( $this, 'add_advanced_triggers_on_all_triggers' ), 10, 1 );
        }*/
    }


    /**
     * Get start templates for initialize workflow builder
     * 
     * @since 1.0.0
     * @return array
     */
    public static function get_start_templates() {
        return apply_filters( 'Joinotify/Builder/Choose_Template', array(
            'scratch' => array(
                'title' => esc_html__( 'Começar do zero', 'joinotify' ),
                'description' => esc_html__( 'Crie seu fluxo de automação do zero', 'joinotify' ),
                'button_title' => esc_html__( 'Criar do zero', 'joinotify' ),
                'icon' => '<svg class="action-icon" fill="#008aff" xmlns="http://www.w3.org/2000/svg" xmlns:xlink="http://www.w3.org/1999/xlink" viewBox="0 0 503.607 503.607" xml:space="preserve" stroke="#343A40"><g stroke-width="0"></g><g stroke-linecap="round" stroke-linejoin="round"></g><g> <g transform="translate(1 1)"> <g> <g> <path d="M343.131,108.115v-25.18V72.023c1.679-1.679,2.518-3.357,2.518-5.875c0-2.632-0.924-4.798-2.518-6.274V49.361 c0-9.233-7.554-16.787-16.787-16.787h-25.18V24.18c0-14.269-10.911-25.18-25.18-25.18h-50.361 c-14.269,0-25.18,11.751-25.18,25.18v8.393h-25.18c-9.233,0-16.787,7.554-16.787,16.787v8.531 c-4.088,0.668-6.715,3.804-6.715,8.256c0,4.197,2.518,7.554,6.715,8.393v1.679v31.738c-0.159,0.893-0.175,1.802,0,2.675 c1.557,7.784,7.448,13.393,14.989,14.168c0.037,0.004,0.073,0.011,0.11,0.015c0.555,0.053,1.117,0.086,1.688,0.086h4.197h34.413 h16.787h33.574h16.787h33.574h11.751c3.357,0,6.715-0.839,9.233-2.518c1.058-0.635,1.901-1.43,2.532-2.33 C341.203,117.004,343.131,112.775,343.131,108.115z M314.593,108.115H281.02h-16.787h-33.574h-16.787h-34.413h-4.197V76.22 V66.148V49.361h33.574c5.036,0,8.393-3.357,8.393-8.393V24.18c0-5.036,3.357-8.393,8.393-8.393h50.361 c5.036,0,8.393,4.197,8.393,8.393v16.787c0,5.036,3.357,8.393,8.393,8.393h33.574v16.787v16.787v25.18H314.593z"></path> <path d="M91.328,457.282v-16.787c0-5.036-3.357-8.393-8.393-8.393s-8.393,3.357-8.393,8.393v16.787 c0,5.036,3.357,8.393,8.393,8.393S91.328,461.479,91.328,457.282z"></path> <path d="M82.934,415.315c5.036,0,8.393-4.197,8.393-8.393v-16.787c0-5.036-3.357-8.393-8.393-8.393s-8.393,3.357-8.393,8.393 v16.787C74.541,411.957,77.898,415.315,82.934,415.315z"></path> <path d="M82.934,364.954c5.036,0,8.393-4.197,8.393-8.393v-16.787c0-5.036-3.357-8.393-8.393-8.393s-8.393,3.357-8.393,8.393 v16.787C74.541,361.597,77.898,364.954,82.934,364.954z"></path> <path d="M126.58,57.754h-10.072c-3.357,0-5.875,0.839-9.233,0.839c-4.197,0.839-6.715,5.875-5.875,10.072 s4.197,6.715,8.393,6.715c0,0,0.839,0,1.679,0c1.679-0.839,3.357-0.839,5.036-0.839h10.072c5.036,0,8.393-3.357,8.393-8.393 C134.974,61.111,131.616,57.754,126.58,57.754z"></path> <path d="M112.311,485.82c-4.197-0.839-8.393-2.518-10.911-4.197c-3.357-3.357-9.233-2.518-11.751,0.839 c-3.357,3.357-2.518,9.233,0.839,11.751c5.036,4.197,11.751,7.554,18.466,8.393c0.839,0,0.839,0,1.679,0 c4.197,0,7.554-3.357,8.393-6.715C119.866,490.856,116.508,486.659,112.311,485.82z"></path> <path d="M82.934,314.593c5.036,0,8.393-4.197,8.393-8.393v-16.787c0-5.036-3.357-8.393-8.393-8.393s-8.393,3.357-8.393,8.393 V306.2C74.541,311.236,77.898,314.593,82.934,314.593z"></path> <path d="M82.934,113.151c5.036,0,8.393-4.197,8.393-8.393v-5.036c0-2.518,0.839-5.875,1.679-8.393 c1.679-5.036-0.839-9.233-5.036-11.751c-4.197-1.679-9.233,0.839-10.911,5.036c-1.679,5.036-2.518,10.072-2.518,15.108v5.036 C74.541,109.793,77.898,113.151,82.934,113.151z"></path> <path d="M410.279,99.721c0,5.036,3.357,8.393,8.393,8.393s8.393-3.357,8.393-8.393c0-6.715-1.679-13.43-5.036-20.144 c-2.518-4.197-7.554-5.875-11.751-3.357c-4.197,2.518-5.875,7.554-3.357,11.751C409.439,91.328,410.279,95.525,410.279,99.721z"></path> <path d="M82.934,163.511c5.036,0,8.393-4.197,8.393-8.393v-16.787c0-5.036-3.357-8.393-8.393-8.393s-8.393,3.357-8.393,8.393 v16.787C74.541,160.154,77.898,163.511,82.934,163.511z"></path> <path d="M82.934,213.872c5.036,0,8.393-4.197,8.393-8.393v-16.787c0-5.036-3.357-8.393-8.393-8.393s-8.393,3.357-8.393,8.393 v16.787C74.541,210.515,77.898,213.872,82.934,213.872z"></path> <path d="M82.934,264.233c5.036,0,8.393-4.197,8.393-8.393v-16.787c0-5.036-3.357-8.393-8.393-8.393s-8.393,3.357-8.393,8.393 v16.787C74.541,260.875,77.898,264.233,82.934,264.233z"></path> <path d="M160.993,485.82h-16.787c-5.036,0-8.393,3.357-8.393,8.393c0,5.036,3.357,8.393,8.393,8.393h16.787 c4.197,0,8.393-3.357,8.393-8.393C169.387,489.177,166.03,485.82,160.993,485.82z"></path> <path d="M418.672,376.705c-5.036,0-8.393,3.357-8.393,8.393v16.787c0,5.036,3.357,8.393,8.393,8.393s8.393-3.357,8.393-8.393 v-16.787C427.066,380.062,423.708,376.705,418.672,376.705z"></path> <path d="M418.672,275.984c-5.036,0-8.393,3.357-8.393,8.393v16.787c0,5.036,3.357,8.393,8.393,8.393s8.393-3.357,8.393-8.393 v-16.787C427.066,279.341,423.708,275.984,418.672,275.984z"></path> <path d="M418.672,225.623c-5.036,0-8.393,3.357-8.393,8.393v16.787c0,5.036,3.357,8.393,8.393,8.393s8.393-3.357,8.393-8.393 v-16.787C427.066,228.98,423.708,225.623,418.672,225.623z"></path> <path d="M418.672,326.344c-5.036,0-8.393,3.357-8.393,8.393v16.787c0,5.036,3.357,8.393,8.393,8.393s8.393-3.357,8.393-8.393 v-16.787C427.066,329.702,423.708,326.344,418.672,326.344z"></path> <path d="M211.354,485.82h-16.787c-5.036,0-8.393,3.357-8.393,8.393c0,5.036,3.357,8.393,8.393,8.393h16.787 c4.197,0,8.393-3.357,8.393-8.393C219.748,489.177,216.39,485.82,211.354,485.82z"></path> <path d="M418.672,427.066c-5.036,0-8.393,3.357-8.393,8.393v16.787c0,5.036,3.357,8.393,8.393,8.393s8.393-3.357,8.393-8.393 v-16.787C427.066,430.423,423.708,427.066,418.672,427.066z"></path> <path d="M418.672,175.262c-5.036,0-8.393,3.357-8.393,8.393v16.787c0,5.036,3.357,8.393,8.393,8.393s8.393-3.357,8.393-8.393 v-16.787C427.066,178.62,423.708,175.262,418.672,175.262z"></path> <path d="M418.672,124.902c-5.036,0-8.393,3.357-8.393,8.393v16.787c0,5.036,3.357,8.393,8.393,8.393s8.393-3.357,8.393-8.393 v-16.787C427.066,128.259,423.708,124.902,418.672,124.902z"></path> <path d="M403.564,477.426c-2.518,3.357-5.875,5.036-10.072,6.715c-4.197,1.679-6.715,6.715-5.036,10.911 c0.839,3.357,4.197,5.875,7.554,5.875c0.839,0,1.679,0,1.679-0.839c6.715-1.679,12.59-5.875,17.626-10.911 c3.357-3.357,3.357-8.393,0-11.751C411.957,474.069,406.921,474.069,403.564,477.426z"></path> <path d="M312.075,485.82h-16.787c-5.036,0-8.393,3.357-8.393,8.393c0,5.036,3.357,8.393,8.393,8.393h16.787 c4.197,0,8.393-3.357,8.393-8.393C320.469,489.177,317.111,485.82,312.075,485.82z"></path> <path d="M261.715,485.82h-16.787c-5.036,0-8.393,3.357-8.393,8.393c0,5.036,3.357,8.393,8.393,8.393h16.787 c4.197,0,8.393-3.357,8.393-8.393C270.108,489.177,266.751,485.82,261.715,485.82z"></path> <path d="M396.01,66.987c0.839-5.036-2.518-9.233-7.554-9.233c-1.679,0-2.518,0-3.357,0h-14.269c-5.036,0-8.393,3.357-8.393,8.393 c0,5.036,3.357,8.393,8.393,8.393h14.269c0.716,0,1.433,0,2.149,0c0.041,0,0.369,0,0.369,0 C391.813,74.541,396.01,71.184,396.01,66.987z"></path> <path d="M362.436,485.82h-16.787c-5.036,0-8.393,3.357-8.393,8.393c0,5.036,3.357,8.393,8.393,8.393h16.787 c4.197,0,8.393-3.357,8.393-8.393C370.829,489.177,367.472,485.82,362.436,485.82z"></path></g></g></g></g></svg>',
            ),
            'template' => array(
                'title' => esc_html__( 'Começar com um modelo', 'joinotify' ),
                'description' => esc_html__( 'Crie seu fluxo de automação a partir de um modelo de sua escolha', 'joinotify' ),
                'button_title' => esc_html__( 'Criar com um modelo', 'joinotify' ),
                'icon' => '<svg class="action-icon" fill="#008aff" xmlns="http://www.w3.org/2000/svg" xmlns:xlink="http://www.w3.org/1999/xlink" viewBox="0 0 503.607 503.607" xml:space="preserve"><g stroke-width="0"></g><g stroke-linecap="round" stroke-linejoin="round"></g><g> <g transform="translate(1 1)"> <g> <g> <path d="M385.098,57.754h-41.967v-8.393c0-9.233-7.554-16.787-16.787-16.787h-25.18V24.18c0-14.269-10.911-25.18-25.18-25.18 h-50.361c-14.269,0-25.18,11.751-25.18,25.18v8.393h-25.18c-9.233,0-16.787,7.554-16.787,16.787v8.393h-41.967 c-23.502,0-41.967,18.466-41.967,41.967v360.918c0,23.502,18.466,41.967,41.967,41.967h268.59 c23.502,0,41.967-18.466,41.967-41.967V99.721C427.066,76.22,408.6,57.754,385.098,57.754z M175.262,49.361h33.574 c5.036,0,8.393-3.357,8.393-8.393V24.18c0-5.036,3.357-8.393,8.393-8.393h50.361c5.036,0,8.393,4.197,8.393,8.393v16.787 c0,5.036,3.357,8.393,8.393,8.393h33.574v16.787v41.967H175.262V66.148V49.361z M410.279,460.639 c0,14.269-10.911,25.18-25.18,25.18h-268.59c-14.269,0-25.18-10.911-25.18-25.18V99.721c0-14.269,10.911-25.18,25.18-25.18 h41.967v33.574c0,9.233,7.554,16.787,16.787,16.787h151.082c9.233,0,16.787-7.554,16.787-16.787V74.541h41.967 c14.269,0,25.18,10.911,25.18,25.18V460.639z"></path> <path d="M133.295,334.738h100.721c5.036,0,8.393-3.357,8.393-8.393c0-5.036-3.357-8.393-8.393-8.393H133.295 c-5.036,0-8.393,3.357-8.393,8.393C124.902,331.38,128.259,334.738,133.295,334.738z"></path> <path d="M334.738,317.951H267.59c-5.036,0-8.393,3.357-8.393,8.393c0,5.036,3.357,8.393,8.393,8.393h67.148 c5.036,0,8.393-3.357,8.393-8.393C343.131,321.308,339.774,317.951,334.738,317.951z"></path> <path d="M309.557,385.098H208.836c-5.036,0-8.393,3.357-8.393,8.393c0,5.036,3.357,8.393,8.393,8.393h100.721 c5.036,0,8.393-3.357,8.393-8.393C317.951,388.456,314.593,385.098,309.557,385.098z"></path> <path d="M133.295,200.443h50.361c5.036,0,8.393-3.357,8.393-8.393s-3.357-8.393-8.393-8.393h-50.361 c-5.036,0-8.393,3.357-8.393,8.393S128.259,200.443,133.295,200.443z"></path> <path d="M217.229,200.443h41.967c5.036,0,8.393-3.357,8.393-8.393s-3.357-8.393-8.393-8.393h-41.967 c-5.036,0-8.393,3.357-8.393,8.393S212.193,200.443,217.229,200.443z"></path> <path d="M368.311,183.656H292.77c-5.036,0-8.393,3.357-8.393,8.393s3.357,8.393,8.393,8.393h75.541 c5.036,0,8.393-3.357,8.393-8.393S373.348,183.656,368.311,183.656z"></path> <path d="M133.295,267.59h83.934c5.036,0,8.393-3.357,8.393-8.393c0-5.036-3.357-8.393-8.393-8.393h-83.934 c-5.036,0-8.393,3.357-8.393,8.393C124.902,264.233,128.259,267.59,133.295,267.59z"></path> <path d="M175.262,385.098h-41.967c-5.036,0-8.393,3.357-8.393,8.393c0,5.036,3.357,8.393,8.393,8.393h41.967 c5.036,0,8.393-3.357,8.393-8.393C183.656,388.456,180.298,385.098,175.262,385.098z"></path> <path d="M351.525,250.803H250.803c-5.036,0-8.393,3.357-8.393,8.393c0,5.036,3.357,8.393,8.393,8.393h100.721 c5.036,0,8.393-3.357,8.393-8.393C359.918,254.161,356.561,250.803,351.525,250.803z"></path></g></g></g></g></svg>',
            ),
            'import' => array(
                'title' => esc_html__( 'Importar um modelo', 'joinotify' ),
                'description' => esc_html__( 'Crie seu fluxo de automação importando a partir de um arquivo', 'joinotify' ),
                'button_title' => esc_html__( 'Importar arquivo', 'joinotify' ),
                'icon' => '<svg class="action-icon" fill="#008aff" xmlns="http://www.w3.org/2000/svg" xmlns:xlink="http://www.w3.org/1999/xlink" viewBox="0 0 503.607 503.607" xml:space="preserve"><g stroke-width="0"></g><g stroke-linecap="round" stroke-linejoin="round"></g><g> <g transform="translate(1 1)"> <g> <g> <path d="M385.098,57.754h-41.967v-8.393c0-9.233-7.554-16.787-16.787-16.787h-25.18V24.18c0-14.269-10.911-25.18-25.18-25.18 h-50.361c-14.269,0-25.18,11.751-25.18,25.18v8.393h-25.18c-9.233,0-16.787,7.554-16.787,16.787v8.393h-41.967 c-23.502,0-41.967,18.466-41.967,41.967v360.918c0,23.502,18.466,41.967,41.967,41.967h268.59 c23.502,0,41.967-18.466,41.967-41.967V99.721C427.066,76.22,408.6,57.754,385.098,57.754z M175.262,49.361h33.574 c5.036,0,8.393-3.357,8.393-8.393V24.18c0-5.036,3.357-8.393,8.393-8.393h50.361c5.036,0,8.393,4.197,8.393,8.393v16.787 c0,5.036,3.357,8.393,8.393,8.393h33.574v16.787v41.967H175.262V66.148V49.361z M410.279,460.639 c0,14.269-10.911,25.18-25.18,25.18h-268.59c-14.269,0-25.18-10.911-25.18-25.18V99.721c0-14.269,10.911-25.18,25.18-25.18 h41.967v33.574c0,9.233,7.554,16.787,16.787,16.787h151.082c9.233,0,16.787-7.554,16.787-16.787V74.541h41.967 c14.269,0,25.18,10.911,25.18,25.18V460.639z"></path> <path d="M197.925,228.141c-3.357-3.357-8.393-4.197-11.751-0.839l-67.148,58.754c-1.679,1.679-2.518,4.197-2.518,6.715 c0,2.518,0.839,5.036,2.518,6.715l67.148,58.754c1.679,0.839,4.197,1.679,5.875,1.679c2.518,0,5.036-0.839,5.875-3.357 c3.357-3.357,2.518-8.393-0.839-11.751l-59.593-52.039l59.593-52.879C200.443,236.534,201.282,231.498,197.925,228.141z"></path> <path d="M314.593,228.98c-3.357-3.357-8.393-2.518-11.751,0.839c-3.357,3.357-2.518,8.393,0.839,11.751l59.593,52.039 l-59.593,52.039c-3.357,3.357-4.197,8.393-0.839,11.751c1.679,1.679,4.197,2.518,6.715,2.518c1.679,0,4.197-0.839,5.036,0 l67.148-58.754c1.679-1.679,2.518-4.197,2.518-6.715s-0.839-5.036-2.518-6.715L314.593,228.98z"></path> <path d="M279.341,226.462c-4.197-1.679-9.233,0-10.911,4.197l-50.361,117.508c-1.679,4.197,0,9.233,4.197,10.911 c0.839,0.839,2.518,0.839,3.357,0.839c3.357,0,6.715-1.679,7.554-5.036l50.361-117.508 C285.216,233.177,283.538,228.141,279.341,226.462z"></path></g></g></g></g></svg>',
            ),
        ));
    }


    /**
     * Get all triggers
     * 
     * This function returns all triggers from different contexts (woocommerce, wpforms, etc.).
     * Triggers are filtered by 'apply_filters' to allow dynamic extensions.
     * 
     * @since 1.0.0
     * @return array
     */
    public static function get_all_triggers() {
        return apply_filters( 'Joinotify/Builder/Get_All_Triggers', array(
            'woocommerce' => array(
                array(
                    'data_trigger' => 'woocommerce_new_order',
                    'title' => esc_html__( 'Novo pedido', 'joinotify' ),
                    'description' => esc_html__( 'Este acionamento é disparado quando um novo pedido é recebido no WooCommerce com qualquer status.', 'joinotify' ),
                ),
                array(
                    'data_trigger' => 'woocommerce_checkout_order_processed',
                    'title' => esc_html__( 'Novo pedido (Processando)', 'joinotify' ),
                    'description' => esc_html__( 'Este acionamento é disparado quando um novo pedido é recebido no WooCommerce com status processando.', 'joinotify' ),
                ),
                array(
                    'data_trigger' => 'woocommerce_order_status_completed',
                    'title' => esc_html__( 'Pedido concluído', 'joinotify' ),
                    'description' => esc_html__( 'Este acionamento é disparado quando um pedido tem o status alterado para concluído.', 'joinotify' ),
                ),
                array(
                    'data_trigger' => 'woocommerce_order_fully_refunded',
                    'title' => esc_html__( 'Pedido totalmente reembolsado', 'joinotify' ),
                    'description' => esc_html__( 'Este acionamento é disparado quando um pedido é totalmente reembolsado.', 'joinotify' ),
                ),
                array(
                    'data_trigger' => 'woocommerce_order_partially_refunded',
                    'title' => esc_html__( 'Pedido parcialmente reembolsado', 'joinotify' ),
                    'description' => esc_html__( 'Este acionamento é disparado quando um pedido é parcialmente reembolsado.', 'joinotify' ),
                ),
                array(
                    'data_trigger' => 'woocommerce_order_status_changed',
                    'title' => esc_html__( 'Status de um pedido alterado', 'joinotify' ),
                    'description' => esc_html__( 'Este acionamento é disparado quando um pedido tem seu status alterado.', 'joinotify' ),
                ),
            ),
            'wpforms' => array(
                array(
                    'data_trigger' => 'wpforms_process_complete',
                    'title' => esc_html__( 'Formulário é enviado', 'joinotify' ),
                    'description' => esc_html__( 'Este acionamento é disparado quando um formulário do WPForms é enviado.', 'joinotify' ),
                    'class' => 'locked',
                ),
                array(
                    'data_trigger' => 'wpforms_paypal_standard_process_complete',
                    'title' => esc_html__( 'Pagamento processado pelo PayPal', 'joinotify' ),
                    'description' => esc_html__( 'Este acionamento é disparado quando um formulário de pagamento do WPForms é processado usando PayPal.', 'joinotify' ),
                    'class' => 'locked',
                ),
            ),
            'elementor' => array(
                array(
                    'data_trigger' => 'elementor_pro/forms/form_submitted',
                    'title' => esc_html__( 'Formulário é enviado', 'joinotify' ),
                    'description' => esc_html__( 'Este acionamento é disparado quando um formulário do Elementor é enviado.', 'joinotify' ),
                    'class' => 'locked',
                ),
            ),
            'flexify_checkout' => array(
                array(
                    'data_trigger' => 'flexify_checkout_cart_abandonment',
                    'title' => esc_html__( 'Abandono do carrinho', 'joinotify' ),
                    'description' => esc_html__( 'Este acionamento é disparado quando o usuário abandona o carrinho.', 'joinotify' ),
                    'class' => 'locked',
                ),
                array(
                    'data_trigger' => 'flexify_checkout_entry_step_1',
                    'title' => esc_html__( 'Ao entrar na etapa 1', 'joinotify' ),
                    'description' => esc_html__( 'Este acionamento é disparado quando o usuário entra na etapa 1 da finalização de compras.', 'joinotify' ),
                    'class' => 'locked',
                ),
                array(
                    'data_trigger' => 'flexify_checkout_entry_step_2',
                    'title' => esc_html__( 'Ao entrar na etapa 2', 'joinotify' ),
                    'description' => esc_html__( 'Este acionamento é disparado quando o usuário entra na etapa 2 da finalização de compras.', 'joinotify' ),
                    'class' => 'locked',
                ),
                array(
                    'data_trigger' => 'flexify_checkout_entry_step_3',
                    'title' => esc_html__( 'Ao entrar na etapa 3', 'joinotify' ),
                    'description' => esc_html__( 'Este acionamento é disparado quando o usuário entra na etapa 3 da finalização de compras.', 'joinotify' ),
                    'class' => 'locked',
                ),
            ),
            'wordpress' => array(
                array(
                    'data_trigger' => 'user_register',
                    'title' => esc_html__( 'Novo registro de usuário', 'joinotify' ),
                    'description' => esc_html__( 'Este acionamento é disparado quando um novo registro de usuário é recebido.', 'joinotify' ),
                    'class' => 'locked',
                ),
                array(
                    'data_trigger' => 'wp_login',
                    'title' => esc_html__( 'Login do usuário', 'joinotify' ),
                    'description' => esc_html__( 'Este acionamento é disparado quando um usuário fizer login no site.', 'joinotify' ),
                    'class' => 'locked',
                ),
                array(
                    'data_trigger' => 'password_reset',
                    'title' => esc_html__( 'Recuperação de senha do usuário', 'joinotify' ),
                    'description' => esc_html__( 'Este acionamento é disparado quando um usuário solicitar recuperação de senha no site.', 'joinotify' ),
                    'class' => 'locked',
                ),
                array(
                    'data_trigger' => 'transition_post_status',
                    'title' => esc_html__( 'Novo post é publicado', 'joinotify' ),
                    'description' => esc_html__( 'Este acionamento é disparado quando um novo post é publicado no site.', 'joinotify' ),
                    'class' => 'locked',
                ),
            ),
        ));
    }


    /**
     * Get triggers by context
     * 
     * @since 1.0.0
     * @param string $context | Nome do contexto (woocommerce, elementor, etc.)
     * @return array | return triggers array from context
     */
    public static function get_triggers_by_context( $context ) {
        $all_triggers = self::get_all_triggers();

        // check if has context on triggers array
        if ( isset( $all_triggers[$context] ) ) {
            return $all_triggers[$context];
        }

        // if context not found, return empty array
        return array();
    }


    /**
     * Get specific trigger based on context and data_trigger
     * 
     * @since 1.0.0
     * @param string $context | Context name (woocommerce, elementor, etc.)
     * @param string $data_trigger | Trigger name (ex: 'order_completed')
     * @return array|null | return the trigger or null if not found
     */
    public static function get_trigger( $context, $data_trigger ) {
        $triggers = self::get_triggers_by_context( $context );

        foreach ( $triggers as $trigger ) {
            if ( $trigger['data_trigger'] === $data_trigger ) {
                return $trigger;
            }
        }

        // If trigger is not found, return null
        return null;
    }


    /**
     * Get trigger from a specific post ID
     * 
     * @since 1.0.0
     * @param int $post_id | Post ID
     * @return array|null | Trigger data if found, or null if not
     */
    public static function get_trigger_from_post( $post_id ) {
        if ( get_post_type( $post_id ) === 'joinotify-workflow' ) {
            $workflow_content = get_post_meta( $post_id, 'joinotify_workflow_content', true );

            if ( is_array( $workflow_content ) ) {
                foreach ( $workflow_content as $item ) {
                    if ( isset( $item['type'] ) && $item['type'] === 'trigger' ) {
                        return $item['data']['trigger']; // Returns the found trigger
                    }
                }
            }
        }

        return null; // Returns null if no trigger is found.
    }


    /**
     * Get all actions
     * 
     * This function returns all actions for uses on connect triggers
     * 
     * @since 1.0.0
     * @return array
     */
    public static function get_all_actions() {
        $actions = array(
            array(
                'action' => 'time_delay',
                'icon' => '<svg class="icon icon-lg icon-dark" xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24"><path d="M12 2C6.486 2 2 6.486 2 12s4.486 10 10 10 10-4.486 10-10S17.514 2 12 2zm0 18c-4.411 0-8-3.589-8-8s3.589-8 8-8 8 3.589 8 8-3.589 8-8 8z"></path><path d="M13 7h-2v5.414l3.293 3.293 1.414-1.414L13 11.586z"></path></svg>',
                'external_icon' => false,
                'title' => __( 'Tempo de espera', 'joinotify' ),
                'description' => __( 'Permite definir um tempo de espera antes da ação ser executada.', 'joinotify' ),
                'has_settings' => true,
                'class' => '',
            ),
            array(
                'action' => 'condition',
                'icon' => '<svg class="icon icon-lg icon-dark condition" xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24"><path d="M17.5 4C15.57 4 14 5.57 14 7.5c0 1.554 1.025 2.859 2.43 3.315-.146.932-.547 1.7-1.23 2.323-1.946 1.773-5.527 1.935-7.2 1.907V8.837c1.44-.434 2.5-1.757 2.5-3.337C10.5 3.57 8.93 2 7 2S3.5 3.57 3.5 5.5c0 1.58 1.06 2.903 2.5 3.337v6.326c-1.44.434-2.5 1.757-2.5 3.337C3.5 20.43 5.07 22 7 22s3.5-1.57 3.5-3.5c0-.551-.14-1.065-.367-1.529 2.06-.186 4.657-.757 6.409-2.35 1.097-.997 1.731-2.264 1.904-3.768C19.915 10.438 21 9.1 21 7.5 21 5.57 19.43 4 17.5 4zm-12 1.5C5.5 4.673 6.173 4 7 4s1.5.673 1.5 1.5S7.827 7 7 7s-1.5-.673-1.5-1.5zM7 20c-.827 0-1.5-.673-1.5-1.5a1.5 1.5 0 0 1 1.482-1.498l.13.01A1.495 1.495 0 0 1 7 20zM17.5 9c-.827 0-1.5-.673-1.5-1.5S16.673 6 17.5 6s1.5.673 1.5 1.5S18.327 9 17.5 9z"></path></svg>',
                'external_icon' => false,
                'title' => __( 'Condição', 'joinotify' ),
                'description' => __( 'Permite definir uma condição para uma ação ser executada.', 'joinotify' ),
                'has_settings' => true,
                'class' => '',
            ),
            array(
                'action' => 'stop_funnel',
                'icon' => '<svg class="icon icon-lg icon-dark" xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24"><path d="M12 2C6.486 2 2 6.486 2 12s4.486 10 10 10 10-4.486 10-10S17.514 2 12 2zm0 18c-4.411 0-8-3.589-8-8s3.589-8 8-8 8 3.589 8 8-3.589 8-8 8z"></path><path d="M9 9h6v6H9z"></path></svg>',
                'external_icon' => false,
                'title' => __( 'Parar automação aqui', 'joinotify' ),
                'description' => __( 'Nenhuma ação será executada ao chegar nesse ponto.', 'joinotify' ),
                'has_settings' => false,
                'class' => '',
            ),
            array(
                'action' => 'send_whatsapp_message_text',
                'icon' => '<svg class="icon icon-lg whatsapp" viewBox="-1.5 0 259 259" xmlns="http://www.w3.org/2000/svg" xmlns:xlink="http://www.w3.org/1999/xlink" preserveAspectRatio="xMidYMid" fill="#000000"><g stroke-width="0"></g><g stroke-linecap="round" stroke-linejoin="round"></g><g> <g> <path d="M67.6631045,221.823373 L71.8484512,223.916047 C89.2873956,234.379413 108.819013,239.262318 128.350631,239.262318 L128.350631,239.262318 C189.735716,239.262318 239.959876,189.038158 239.959876,127.653073 C239.959876,98.3556467 228.101393,69.7557778 207.17466,48.8290445 C186.247927,27.9023111 158.345616,16.0438289 128.350631,16.0438289 C66.9655467,16.0438289 16.7413867,66.2679889 17.4389445,128.350631 C17.4389445,149.277365 23.7169645,169.50654 34.1803311,186.945485 L36.9705622,191.130831 L25.8096378,232.28674 L67.6631045,221.823373 Z" fill="#00E676"> </path> <path d="M219.033142,37.66812 C195.316178,13.2535978 162.530962,0 129.048189,0 C57.8972956,0 0.697557778,57.8972956 1.39511556,128.350631 C1.39511556,150.67248 7.67313556,172.296771 18.1365022,191.828389 L0,258.096378 L67.6631045,240.657433 C86.4971645,251.1208 107.423898,256.003705 128.350631,256.003705 L128.350631,256.003705 C198.803967,256.003705 256.003705,198.106409 256.003705,127.653073 C256.003705,93.4727423 242.750107,61.3850845 219.033142,37.66812 Z M129.048189,234.379413 L129.048189,234.379413 C110.214129,234.379413 91.380069,229.496509 75.3362401,219.7307 L71.1508934,217.638027 L30.6925422,228.101393 L41.1559089,188.3406 L38.3656778,184.155253 C7.67313556,134.628651 22.3218489,69.05822 72.5460089,38.3656778 C122.770169,7.67313556 187.643042,22.3218489 218.335585,72.5460089 C249.028127,122.770169 234.379413,187.643042 184.155253,218.335585 C168.111425,228.798951 148.579807,234.379413 129.048189,234.379413 Z M190.433273,156.9505 L182.760138,153.462711 C182.760138,153.462711 171.599213,148.579807 164.623636,145.092018 C163.926078,145.092018 163.22852,144.39446 162.530962,144.39446 C160.438289,144.39446 159.043173,145.092018 157.648058,145.789576 L157.648058,145.789576 C157.648058,145.789576 156.9505,146.487133 147.184691,157.648058 C146.487133,159.043173 145.092018,159.740731 143.696902,159.740731 L142.999345,159.740731 C142.301787,159.740731 140.906671,159.043173 140.209113,158.345616 L136.721325,156.9505 L136.721325,156.9505 C129.048189,153.462711 122.072611,149.277365 116.492149,143.696902 C115.097033,142.301787 113.00436,140.906671 111.609245,139.511556 C106.72634,134.628651 101.843436,129.048189 98.3556467,122.770169 L97.658089,121.375053 C96.9605312,120.677496 96.9605312,119.979938 96.2629734,118.584822 C96.2629734,117.189707 96.2629734,115.794591 96.9605312,115.097033 C96.9605312,115.097033 99.7507623,111.609245 101.843436,109.516571 C103.238551,108.121456 103.936109,106.028782 105.331225,104.633667 C106.72634,102.540993 107.423898,99.7507623 106.72634,97.658089 C106.028782,94.1703001 97.658089,75.3362401 95.5654156,71.1508934 C94.1703001,69.05822 92.7751845,68.3606623 90.6825112,67.6631045 L88.5898378,67.6631045 C87.1947223,67.6631045 85.1020489,67.6631045 83.0093756,67.6631045 C81.6142601,67.6631045 80.2191445,68.3606623 78.8240289,68.3606623 L78.1264712,69.05822 C76.7313556,69.7557778 75.3362401,71.1508934 73.9411245,71.8484512 C72.5460089,73.2435667 71.8484512,74.6386823 70.4533356,76.0337978 C65.5704312,82.3118178 62.7802,89.9849534 62.7802,97.658089 L62.7802,97.658089 C62.7802,103.238551 64.1753156,108.819013 66.2679889,113.701918 L66.9655467,115.794591 C73.2435667,129.048189 81.6142601,140.906671 92.7751845,151.370038 L95.5654156,154.160269 C97.658089,156.252942 99.7507623,157.648058 101.145878,159.740731 C115.794591,172.296771 132.535978,181.365022 151.370038,186.247927 C153.462711,186.945485 156.252942,186.945485 158.345616,187.643042 L158.345616,187.643042 C160.438289,187.643042 163.22852,187.643042 165.321193,187.643042 C168.808982,187.643042 172.994329,186.247927 175.78456,184.852811 C177.877233,183.457696 179.272349,183.457696 180.667465,182.06258 L182.06258,180.667465 C183.457696,179.272349 184.852811,178.574791 186.247927,177.179676 C187.643042,175.78456 189.038158,174.389445 189.735716,172.994329 C191.130831,170.204098 191.828389,166.716309 192.525947,163.22852 C192.525947,161.833405 192.525947,159.740731 192.525947,158.345616 C192.525947,158.345616 191.828389,157.648058 190.433273,156.9505 Z" fill="#FFFFFF"></path></g></g></svg>',
                'external_icon' => false,
                'title' => __( 'WhatsApp: Mensagem de texto', 'joinotify' ),
                'description' => __( 'Envie uma mensagem de texto com o WhatsApp.', 'joinotify' ),
                'has_settings' => true,
                'class' => '',
            ),
            array(
                'action' => 'send_whatsapp_message_media',
                'icon' => '<svg class="icon icon-lg whatsapp" viewBox="-1.5 0 259 259" xmlns="http://www.w3.org/2000/svg" xmlns:xlink="http://www.w3.org/1999/xlink" preserveAspectRatio="xMidYMid" fill="#000000"><g stroke-width="0"></g><g stroke-linecap="round" stroke-linejoin="round"></g><g> <g> <path d="M67.6631045,221.823373 L71.8484512,223.916047 C89.2873956,234.379413 108.819013,239.262318 128.350631,239.262318 L128.350631,239.262318 C189.735716,239.262318 239.959876,189.038158 239.959876,127.653073 C239.959876,98.3556467 228.101393,69.7557778 207.17466,48.8290445 C186.247927,27.9023111 158.345616,16.0438289 128.350631,16.0438289 C66.9655467,16.0438289 16.7413867,66.2679889 17.4389445,128.350631 C17.4389445,149.277365 23.7169645,169.50654 34.1803311,186.945485 L36.9705622,191.130831 L25.8096378,232.28674 L67.6631045,221.823373 Z" fill="#00E676"> </path> <path d="M219.033142,37.66812 C195.316178,13.2535978 162.530962,0 129.048189,0 C57.8972956,0 0.697557778,57.8972956 1.39511556,128.350631 C1.39511556,150.67248 7.67313556,172.296771 18.1365022,191.828389 L0,258.096378 L67.6631045,240.657433 C86.4971645,251.1208 107.423898,256.003705 128.350631,256.003705 L128.350631,256.003705 C198.803967,256.003705 256.003705,198.106409 256.003705,127.653073 C256.003705,93.4727423 242.750107,61.3850845 219.033142,37.66812 Z M129.048189,234.379413 L129.048189,234.379413 C110.214129,234.379413 91.380069,229.496509 75.3362401,219.7307 L71.1508934,217.638027 L30.6925422,228.101393 L41.1559089,188.3406 L38.3656778,184.155253 C7.67313556,134.628651 22.3218489,69.05822 72.5460089,38.3656778 C122.770169,7.67313556 187.643042,22.3218489 218.335585,72.5460089 C249.028127,122.770169 234.379413,187.643042 184.155253,218.335585 C168.111425,228.798951 148.579807,234.379413 129.048189,234.379413 Z M190.433273,156.9505 L182.760138,153.462711 C182.760138,153.462711 171.599213,148.579807 164.623636,145.092018 C163.926078,145.092018 163.22852,144.39446 162.530962,144.39446 C160.438289,144.39446 159.043173,145.092018 157.648058,145.789576 L157.648058,145.789576 C157.648058,145.789576 156.9505,146.487133 147.184691,157.648058 C146.487133,159.043173 145.092018,159.740731 143.696902,159.740731 L142.999345,159.740731 C142.301787,159.740731 140.906671,159.043173 140.209113,158.345616 L136.721325,156.9505 L136.721325,156.9505 C129.048189,153.462711 122.072611,149.277365 116.492149,143.696902 C115.097033,142.301787 113.00436,140.906671 111.609245,139.511556 C106.72634,134.628651 101.843436,129.048189 98.3556467,122.770169 L97.658089,121.375053 C96.9605312,120.677496 96.9605312,119.979938 96.2629734,118.584822 C96.2629734,117.189707 96.2629734,115.794591 96.9605312,115.097033 C96.9605312,115.097033 99.7507623,111.609245 101.843436,109.516571 C103.238551,108.121456 103.936109,106.028782 105.331225,104.633667 C106.72634,102.540993 107.423898,99.7507623 106.72634,97.658089 C106.028782,94.1703001 97.658089,75.3362401 95.5654156,71.1508934 C94.1703001,69.05822 92.7751845,68.3606623 90.6825112,67.6631045 L88.5898378,67.6631045 C87.1947223,67.6631045 85.1020489,67.6631045 83.0093756,67.6631045 C81.6142601,67.6631045 80.2191445,68.3606623 78.8240289,68.3606623 L78.1264712,69.05822 C76.7313556,69.7557778 75.3362401,71.1508934 73.9411245,71.8484512 C72.5460089,73.2435667 71.8484512,74.6386823 70.4533356,76.0337978 C65.5704312,82.3118178 62.7802,89.9849534 62.7802,97.658089 L62.7802,97.658089 C62.7802,103.238551 64.1753156,108.819013 66.2679889,113.701918 L66.9655467,115.794591 C73.2435667,129.048189 81.6142601,140.906671 92.7751845,151.370038 L95.5654156,154.160269 C97.658089,156.252942 99.7507623,157.648058 101.145878,159.740731 C115.794591,172.296771 132.535978,181.365022 151.370038,186.247927 C153.462711,186.945485 156.252942,186.945485 158.345616,187.643042 L158.345616,187.643042 C160.438289,187.643042 163.22852,187.643042 165.321193,187.643042 C168.808982,187.643042 172.994329,186.247927 175.78456,184.852811 C177.877233,183.457696 179.272349,183.457696 180.667465,182.06258 L182.06258,180.667465 C183.457696,179.272349 184.852811,178.574791 186.247927,177.179676 C187.643042,175.78456 189.038158,174.389445 189.735716,172.994329 C191.130831,170.204098 191.828389,166.716309 192.525947,163.22852 C192.525947,161.833405 192.525947,159.740731 192.525947,158.345616 C192.525947,158.345616 191.828389,157.648058 190.433273,156.9505 Z" fill="#FFFFFF"></path></g></g></svg>',
                'external_icon' => false,
                'title' => __( 'WhatsApp: Mensagem de mídia', 'joinotify' ),
                'description' => __( 'Envie uma mensagem de mídia (imagem, vídeo, documento e áudio) com o WhatsApp.', 'joinotify' ),
                'has_settings' => true,
                'class' => '',
            ),
            array(
                'action' => 'create_coupon',
                'icon' => '<svg class="icon icon-lg icon-dark coupon" viewBox="0 0 24 24" fill="none" xmlns="http://www.w3.org/2000/svg"><g stroke-width="0"></g><g stroke-linecap="round" stroke-linejoin="round"></g><g><path fill-rule="evenodd" clip-rule="evenodd" d="M3.75 6.75L4.5 6H20.25L21 6.75V10.7812H20.25C19.5769 10.7812 19.0312 11.3269 19.0312 12C19.0312 12.6731 19.5769 13.2188 20.25 13.2188H21V17.25L20.25 18L4.5 18L3.75 17.25V13.2188H4.5C5.1731 13.2188 5.71875 12.6731 5.71875 12C5.71875 11.3269 5.1731 10.7812 4.5 10.7812H3.75V6.75ZM5.25 7.5V9.38602C6.38677 9.71157 7.21875 10.7586 7.21875 12C7.21875 13.2414 6.38677 14.2884 5.25 14.614V16.5L9 16.5L9 7.5H5.25ZM10.5 7.5V16.5L19.5 16.5V14.614C18.3632 14.2884 17.5312 13.2414 17.5312 12C17.5312 10.7586 18.3632 9.71157 19.5 9.38602V7.5H10.5Z"></path></g></svg>',
                'external_icon' => false,
                'title' => __( 'Cupom de desconto', 'joinotify' ),
                'description' => __( 'Envie um cupom de desconto para seu usuário através de mensagem de texto do WhatsApp.', 'joinotify' ),
                'has_settings' => false,
                'class' => 'locked-resource',
            ),
        );

        return apply_filters( 'Joinotify/Builder/Actions', $actions );
    }


    /**
     * Create workflow structure for saving options
     * 
     * @since 1.0.0
     * @param string $type | The type of element (e.g., 'trigger', 'action', etc.)
     * @param array $data | Data specific to the element being created
     * @param array $children | Array of child elements, if any
     * @return array
     */
    public static function create_workflow_structure( $type, $data = array(), $children = array() ) {
        $base_structure = array(
            'id' => isset( $data['id'] ) ? sanitize_text_field( $data['id'] ) : uniqid( 'joinotify_' . $type . '_' ),
            'type' => sanitize_text_field( $type ),
            'data' => array(
                'title' => isset( $data['title'] ) ? sanitize_text_field( $data['title'] ) : '',
                'description' => isset( $data['description'] ) ? sanitize_text_field( $data['description'] ) : '',
            ),
            'children' => is_array( $children ) ? $children : array(),
        );

        switch ( $type ) {
            case 'trigger' :
                $base_structure['data']['trigger'] = isset( $data['trigger'] ) ? sanitize_text_field( $data['trigger'] ) : '';
                $base_structure['data']['context'] = isset( $data['context'] ) ? sanitize_text_field( $data['context'] ) : '';
                
                break;
            case 'action' :
                $base_structure['data']['action'] = isset( $data['action'] ) ? sanitize_text_field( $data['action'] ) : '';
                
                break;
            case 'connector' :
                $base_structure['data']['connector_type'] = isset( $data['connector_type'] ) ? sanitize_text_field( $data['connector_type'] ) : '';
                
                break;
            default:
                break;
        }

        return $base_structure;
    }


    /**
     * Recursive function to delete an action by ID
     * 
     * @param array $workflow_content | Workflow content array
     * @param string $action_id | ID of the action or trigger to be deleted
     * @return array | Updated workflow
     */
    public static function delete_item_recursive( $workflow_content, $action_id ) {
        foreach ( $workflow_content as $key => &$item ) {
            // If it is the item to be deleted, remove
            if ( isset( $item['id'] ) && $item['id'] === $action_id ) {
                unset( $workflow_content[$key] );

                continue;
            }
        }

        // Reindex the array to avoid index failures
        return array_values( $workflow_content );
    }


    /**
     * Build workflow content array with connectors
     * 
     * @since 1.0.0
     * @param array $workflow_data | Workflow content data
     * @return array
     */
    /**
     * Build workflow content array with connectors
     * 
     * @since 1.0.0
     * @param array $workflow_data | Workflow content data
     * @return array
     */
    public static function build_workflow_content( $workflow_data ) {
        $new_workflow_data = array();
        $total_items = count( $workflow_data );
        
        // iterate from workflow content
        foreach ( $workflow_data as $key => $data ) {
            // add current item on workflow array
            $new_workflow_data[] = $data;
    
            // Add connectors appropriately
            if ( isset( $data['type'] ) && $data['type'] === 'trigger' ) {
                $new_workflow_data[] = array(
                    'type' => ($key + 1 < $total_items) ? 'connector' : 'connector_add',
                    'parent_id' => $data['id'],
                );
            } elseif ( isset( $data['type'] ) && $data['type'] === 'action' ) {
                if ( $data['data']['action'] === 'condition' ) {
                    $new_workflow_data[] = array(
                        'type' => 'connector_condition',
                        'parent_id' => $data['id'],
                    );
                }

                if ( isset( $workflow_data[$key + 1] ) && $workflow_data[$key + 1]['type'] === 'action' ) {
                    $new_workflow_data[] = array(
                        'type' => 'connector',
                        'parent_id' => $data['id'],
                    );
                } elseif ( $data['data']['action'] !== 'stop_funnel' ) {
                    $new_workflow_data[] = array(
                        'type' => 'connector_add',
                        'parent_id' => $data['id'],
                    );
                }
            }
        }

        return $new_workflow_data;
    }


    /**
     * Get workflow content HTML
     * 
     * @since 1.0.0
     * @param int $post_id | Post ID
     * @return mixed|array|false
     */
    public static function get_workflow_content( $post_id ) {
        if ( get_post_type( $post_id ) === 'joinotify-workflow' ) {
            $workflow_content = get_post_meta( $post_id, 'joinotify_workflow_content', true );
            
            if ( empty( $workflow_content ) ) {
                return false;
            }
    
            // Build the workflow content array with connectors
            $workflow_data = self::build_workflow_content( $workflow_content );
            $workflow_html = array();
    
            // Iterate for each workflow content and generate HTML
            foreach ( $workflow_data as $workflow ) {
                if ( isset( $workflow['type'] ) && $workflow['type'] === 'trigger' ) {
                    $trigger_id = isset( $workflow['id'] ) ? $workflow['id'] : '';
                    $workflow_html[] = Components::get_trigger_html( $workflow['data']['context'], $workflow['data']['trigger'], $trigger_id );
                } elseif ( isset( $workflow['type'] ) && strpos( $workflow['type'], 'connector' ) !== false ) {
                    $workflow_html[] = Components::get_workflow_connector( $post_id, $workflow['type'], $workflow );
                } elseif ( isset( $workflow['type'] ) && $workflow['type'] === 'action' ) {
                    $action_name = $workflow['data']['action'];
                    $description = isset( $workflow['data']['description'] ) ? $workflow['data']['description'] : '';
                    $action_id = isset( $workflow['id'] ) ? $workflow['id'] : '';
                    $workflow_html[] = Components::get_action_html( $post_id, $action_name, $description, $action_id );
                }
            }
    
            return implode( '', $workflow_html );
        }
    
        return false;
    }


    /**
     * Find condition data by ID
     * 
     * @since 1.0.0
     * @param array $workflow_data | Full workflow data array
     * @param string $condition_id | Condition ID to find
     * @return array|null
     */
    public static function find_condition_by_id( $post_id, $condition_id ) {
        $workflow_data = get_post_meta( $post_id, 'joinotify_workflow_content', true );

        foreach ( $workflow_data as $data ) {
            if ( isset( $data['id'] ) && $data['id'] === $condition_id ) {
                return $data;
            }
        }

        return null;
    }


    /**
     * Check if the workflow content contains a specified type (trigger or action)
     * 
     * @since 1.0.0
     * @param int $post_id | Post ID
     * @param string $type | Type to check for in the workflow content ('trigger' or 'action')
     * @return bool
     */
    public static function check_workflow_content( $post_id, $type = '' ) {
        // Check if $type is provided
        if ( empty( $type ) || ! is_string( $type ) ) {
            return false;
        }

        // Check post type
        if ( get_post_type( $post_id ) !== 'joinotify-workflow' ) {
            return false;
        }

        // Retrieve the workflow content from the post meta
        $workflow_data = get_post_meta( $post_id, 'joinotify_workflow_content', true );

        // If the workflow data is empty or not an array, return false
        if ( empty( $workflow_data ) || ! is_array( $workflow_data ) ) {
            return false;
        }

        // Iterate over the workflow data to check for the specified type
        foreach ( $workflow_data as $item ) {
            if ( isset( $item['type'] ) && $item['type'] === $type ) {
                return true; // Type found in workflow content
            }
        }

        // No specified type found in the workflow content
        return false;
    }


    /**
     * Get condition item from action ID
     * 
     * @since 1.0.0
     * @param int $post_id | Post ID
     * @param string $action_id | Action ID
     * @return string|null
     */
    public static function get_condition_item( $post_id, $action_id ) {
        if ( get_post_type( $post_id ) === 'joinotify-workflow' ) {
            $workflow_data = get_post_meta( $post_id, 'joinotify_workflow_content', true );
            
            // Checks if the workflow_data array was provided correctly
            if ( ! is_array( $workflow_data ) ) {
                return null;
            }

            foreach ( $workflow_data as $item ) {
                // Checks if the item type is 'action' and the id matches the provided action_id
                if ( isset( $item['type'] ) && $item['type'] === 'action' && isset( $item['id'] ) && $item['id'] === $action_id ) {
                    // Checks if the item has a condition
                    if ( isset( $item['data']['action'] ) && $item['data']['action'] === 'condition' && isset( $item['data']['condition'] ) ) {
                        return $item['data']['condition'];
                    }
                }
            }
        }

        return null;
    }


    /**
     * Add advanced trigger on sidebar
     * 
     * @since 1.0.0
     * @return void
     */
    public function add_advanced_custom_trigger() {
        if ( Admin::get_setting('enable_advanced_trigger') === 'yes' ) : ?>
            <a href="#advanced" class="nav-tab">
                <svg class="joinotify-tab-icon" xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24"><path d="M4 11h6a1 1 0 0 0 1-1V4a1 1 0 0 0-1-1H4a1 1 0 0 0-1 1v6a1 1 0 0 0 1 1zm1-6h4v4H5V5zm15-2h-6a1 1 0 0 0-1 1v6a1 1 0 0 0 1 1h6a1 1 0 0 0 1-1V4a1 1 0 0 0-1-1zm-1 6h-4V5h4v4zm-9 12a1 1 0 0 0 1-1v-6a1 1 0 0 0-1-1H4a1 1 0 0 0-1 1v6a1 1 0 0 0 1 1h6zm-5-6h4v4H5v-4zm13-1h-2v2h-2v2h2v2h2v-2h2v-2h-2z"></path></svg>
                <?php esc_html_e( 'Personalizado (avançado)', 'joinotify' ) ?>
            </a>
        <?php endif;
    }


    /**
     * Add advanced trigger content tab
     * 
     * @since 1.0.0
     * @return void
     */
    public function add_advanced_custom_trigger_tab_content() {
        if ( Admin::get_setting('enable_advanced_trigger') === 'yes' ) : ?>
            <div id="advanced" class="nav-content triggers-group">
                <?php foreach ( self::get_triggers_by_context('advanced') as $trigger ) : ?>
                    <div class="trigger-item <?php echo ( isset( $trigger['class'] ) ? $trigger['class'] : '' ) ?>" data-context="advanced" data-trigger="<?php echo esc_attr( $trigger['data_trigger'] ); ?>">
                        <h4 class="title"><?php echo $trigger['title']; ?></h4>
                        <span class="description"><?php echo $trigger['description']; ?></span>
                    </div>
                <?php endforeach; ?>
            </div>
        <?php endif;
    }


    /**
     * Add advanced triggers on main filter
     * 
     * @since 1.0.0
     * @param array $triggers | Current triggers
     * @return array
     */
    public function add_advanced_triggers_on_all_triggers( $triggers ) {
        $triggers['advanced'] = array(
            array(
                'data_trigger' => 'custom_advanced_trigger',
                'title' => esc_html__( 'Acionamento personalizado', 'joinotify' ),
                'description' => esc_html__( 'Este acionamento é disparado conforme registrado no gancho do_action.', 'joinotify' )
            ),
        );

        return $triggers;
    }


    /**
     * Get template categories for choose template on builder
     * 
     * @since 1.0.0
     * @return array
     */
    public static function get_template_categories() {
        return apply_filters( 'Joinotify/Builder/Get_Template_Categories', array(
            'wordpress' => esc_html__( 'WordPress', 'joinotify' ),
            'woocommerce' => esc_html__( 'WooCommerce', 'joinotify' ),
            'flexify_checkout' => esc_html__( 'Flexify Checkout', 'joinotify' ),
            'elementor' => esc_html__( 'Elementor', 'joinotify' ),
            'wpforms' => esc_html__( 'WPForms', 'joinotify' ),
        ));
    }


    /**
     * Build the funnel action message based on workflow action type
     * 
     * @since 1.0.0
     * @param array $workflow_action | Workflow action data
     * @return string
     */
    public static function build_funnel_action_message( $workflow_action ) {
        $message = '';

        switch ( $workflow_action['data']['action'] ) {
            case 'time_delay':
                $message = self::build_time_delay_message( $workflow_action['data'] );

                break;
            case 'condition':
                $message = self::build_condition_message( $workflow_action['data'] );

                break;
            case 'send_whatsapp_message_text':
                $message = self::build_whatsapp_text_message( $workflow_action['data'] );

                break;
            case 'send_whatsapp_message_media':
                $message = self::build_whatsapp_media_message( $workflow_action['data'] );

                break;
        }

        return $message;
    }
 

    /**
     * Build a message for time delay actions
     * 
     * @since 1.0.0
     * @param array $data | Message data
     * @return string
     */
    public static function build_time_delay_message( $data ) {
        $message = '';

        if ( isset( $data['delay_type'] ) ) {
            if ( $data['delay_type'] === 'period' ) {
                $time_value = $data['delay_value'];
                $time_unit = $data['delay_period'];

                // Format time unit: singular/plural
                $formatted_time_unit = ( $time_value > 1 ) ? Helpers::format_time_unit( $time_unit, true ) : Helpers::format_time_unit( $time_unit, false );
                $message = sprintf( __( 'Esperar por %s %s', 'joinotify' ), $time_value, $formatted_time_unit );

            } elseif ( $data['delay_type'] === 'date' ) {
                $date_value = $data['date_value'];
                $time_value = isset( $data['time_value'] ) ? $data['time_value'] : '';

                if ( ! empty( $time_value ) ) {
                    $message = sprintf( __( 'Esperar até %s - %s', 'joinotify' ), $date_value, $time_value );
                } else {
                    $message = sprintf( __( 'Esperar até %s', 'joinotify' ), $date_value );
                }
            }
        }

        return $message;
    }

    
    /**
     * Build a message for condition actions
     * 
     * @since 1.0.0
     * @param array $data | Message data
     * @return string
     */
    public static function build_condition_message( $data ) {
        $title = isset( $data['title'] ) ? $data['title'] : '';
        $condition_text = isset( $data['condition_content']['type_text'] ) ? $data['condition_content']['type_text'] : '';
        $condition_value = isset( $data['condition_content']['value_text'] ) ? $data['condition_content']['value_text'] : '';

        return sprintf( __( '%s %s %s' ), $title, mb_strtolower( $condition_text, 'UTF-8' ), mb_strtolower( $condition_value, 'UTF-8' ) );
    }


    /**
     * Build a message for WhatsApp text actions
     * 
     * @since 1.0.0
     * @param array $data | Message data
     * @return string
     */
    public static function build_whatsapp_text_message( $data ) {
        $message = isset( $data['message'] ) ? $data['message'] : '';

        // Replace {{ br }} to break line HTML component
        $message = str_replace( '{{ br }}', '<br>', $message );

        // Regular expression to match variables like {{ variable_name }}
        $pattern = '/\{\{\s*(.*?)\s*\}\}/';

        // Callback function to wrap variables in the desired HTML
        $replacement = function( $matches ) {
            return '<span class="builder-placeholder">{{ ' . $matches[1] . ' }}</span>';
        };

        // Assign the processed message
        return preg_replace_callback( $pattern, $replacement, $message );
    }


    /**
     * Build a message for WhatsApp media actions
     * 
     * @since 1.0.0
     * @param array $data | Message data
     * @return string
     */
    public static function build_whatsapp_media_message( $data ) {
        $media = isset( $data['media_url'] ) ? $data['media_url'] : '';

        return '<img class="funnel-media" src="' . esc_url( $media ) . '">';
    }
}