<?php

namespace MeuMouse\Joinotify\Builder;

use MeuMouse\Joinotify\Admin\Admin;
use MeuMouse\Joinotify\Builder\Components as Builder_Components;
use MeuMouse\Joinotify\Core\Helpers;

// Exit if accessed directly.
defined('ABSPATH') || exit;

/**
 * This class manages the workflow functions
 * 
 * @since 1.1.0
 * @package MeuMouse.com
 */
class Workflow_Manager {

    /**
     * Construct function
     * 
     * @since 1.0.0
     * @version 1.1.0
     * @return void
     */
    public function __construct() {
        // add modal content for edit workflow title
        add_action( 'in_admin_header', array( 'MeuMouse\Joinotify\Builder\Components', 'workflow_title_modal_content' ) );

        // add modal content for fetch all groups
        add_action( 'in_admin_header', array( 'MeuMouse\Joinotify\Builder\Components', 'fetch_all_groups_modal_content' ) );
    }


    /**
     * Get start templates for initialize workflow builder
     * 
     * @since 1.0.0
     * @version 1.1.0
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
     * Build workflow content array with connectors
     * 
     * @since 1.0.0
     * @version 1.1.0
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
     * @version 1.1.0
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
                    $trigger_details = array();
                    $trigger_details['trigger_id'] = isset( $workflow['id'] ) ? $workflow['id'] : '';
                    $trigger_details['context'] = isset( $workflow['data']['context'] ) ? $workflow['data']['context'] : '';
                    $trigger_details['data_trigger'] = isset( $workflow['data']['trigger'] ) ? $workflow['data']['trigger'] : '';

                    $workflow_html[] = Builder_Components::get_trigger_html( $post_id, $trigger_details );
                } elseif ( isset( $workflow['type'] ) && strpos( $workflow['type'], 'connector' ) !== false ) {
                    $workflow_html[] = Builder_Components::get_workflow_connector( $post_id, $workflow['type'], $workflow );
                } elseif ( isset( $workflow['type'] ) && $workflow['type'] === 'action' ) {
                    $action_details = array();
                    $action_details['id'] = isset( $workflow['id'] ) ? $workflow['id'] : '';
                    $action_details['action_name'] = isset( $workflow['data']['action'] ) ? $workflow['data']['action'] : '';
                    $action_details['description'] = isset( $workflow['data']['description'] ) ? $workflow['data']['description'] : '';
                    $action_details['sender'] = isset( $workflow['data']['sender'] ) ? $workflow['data']['sender'] : '';
                    $action_details['receiver'] = isset( $workflow['data']['receiver'] ) ? $workflow['data']['receiver'] : '';
                    
                    $workflow_html[] = Builder_Components::get_action_html( $post_id, $action_details );
                }
            }
    
            return implode( '', $workflow_html );
        }
    
        return false;
    }


    /**
     * Create workflow structure for saving options
     * 
     * @since 1.0.0
     * @version 1.1.0
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
}