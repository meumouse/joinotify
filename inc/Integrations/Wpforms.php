<?php

namespace MeuMouse\Joinotify\Integrations;

use MeuMouse\Joinotify\Core\Admin;
use MeuMouse\Joinotify\Builder\Core;

// Exit if accessed directly.
defined('ABSPATH') || exit;

/**
 * Add integration with WPForms plugin
 * 
 * @since 1.0.0
 * @package MeuMouse.com
 */
class Wpforms extends Integrations_Base {

    /**
     * Construct function
     * 
     * @since 1.0.0
     * @return void
     */
    public function __construct() {
        // check if WPForms is active
        if ( function_exists('wpforms') ) {
            add_action( 'Joinotify/Builder/Triggers', array( $this, 'add_wpforms_triggers' ), 40 );
            add_action( 'Joinotify/Builder/Triggers_Content', array( $this, 'add_wpforms_tab_content' ) );
        }
    }


    /**
     * Add WPForms triggers on sidebar
     * 
     * @since 1.0.0
     * @return void
     */
    public function add_wpforms_triggers() {
        if ( Admin::get_setting('enable_wpforms_integration') === 'yes' ) : ?>
            <a href="#wpforms" class="nav-tab">
                <svg class="joinotify-tab-icon" fill="#000000" viewBox="0 0 14 14" role="img" focusable="false" aria-hidden="true" xmlns="http://www.w3.org/2000/svg"><g stroke-width="0"></g><g stroke-linecap="round" stroke-linejoin="round"></g><g><path d="m 13,2.15688 0,9.68624 C 13,12.49386 12.491071,13 11.842857,13 L 2.1571429,13 C 1.5169643,12.99732 1,12.49922 1,11.84044 L 1,2.15688 C 1,1.51149 1.5035714,1 2.1571429,1 L 11.845536,1 C 12.488393,1 13,1.50346 13,2.15688 Z m -0.999107,9.68356 0,-9.68356 c 0,-0.0803 -0.06964,-0.15532 -0.155357,-0.15532 l -0.249107,0 L 8.6419643,3.99933 7,2.66302 5.3607143,3.99933 2.40625,1.99888 l -0.2491071,0 c -0.085714,0 -0.1553572,0.075 -0.1553572,0.15533 l 0,9.68623 c 0,0.0803 0.069643,0.15532 0.1553572,0.15532 l 9.6883931,0 c 0.08571,0.003 0.155357,-0.0723 0.155357,-0.15532 z m -6.9776787,-6.71636 0,0.99085 -1.96875,0 0,-0.99085 1.96875,0 z m 0,1.99241 0,0.99889 -1.96875,0 0,-0.99889 1.96875,0 z m 0.2973214,-3.94465 1.4464286,-1.17028 -3.1741072,0 1.7276786,1.17028 z m 5.6250003,1.95224 0,0.99085 -5.2500003,0 0,-0.99085 5.2500003,0 z m 0,1.99241 0,0.99889 -5.2500003,0 0,-0.99889 5.2500003,0 z M 8.6794643,3.17184 10.407143,2.00156 l -3.1714287,0 1.44375,1.17028 z m 2.2660717,5.94242 0,0.99888 -2.6625003,0 0,-0.99888 2.6625003,0 z"></path></g></svg>
                <?php esc_html_e( 'WPForms', 'joinotify' ) ?>
            </a>
        <?php endif;
    }


    /**
     * Add content tab
     * 
     * @since 1.0.0
     * @return void
     */
    public function add_wpforms_tab_content() {
        if ( Admin::get_setting('enable_wpforms_integration') === 'yes' ) : ?>
            <div id="wpforms" class="nav-content triggers-group">
                <?php foreach ( Core::get_triggers_by_context('wpforms') as $trigger ) : ?>
                    <div class="trigger-item <?php echo ( isset( $trigger['class'] ) ? $trigger['class'] : '' ) ?>" data-context="wpforms" data-trigger="<?php echo esc_attr( $trigger['data_trigger'] ); ?>">
                        <h4 class="title"><?php echo $trigger['title']; ?></h4>
                        <span class="description"><?php echo $trigger['description']; ?></span>

                        <?php if ( isset( $trigger['class'] ) && $trigger['class'] === 'locked' ) : ?>
                            <span class="fs-sm mt-3"><?php esc_html_e( 'Este recurso será liberado em breve', 'joinotify' ) ?></span>
                        <?php endif; ?>
                    </div>
                <?php endforeach; ?>
            </div>
        <?php endif;
    }


    /**
     * Get all WPForms forms
     * 
     * @since 1.0.0
     * @return array
     */
    public static function get_all_wpforms_forms() {
        // Initializes an empty array to store the forms
        $forms_list = array();

        $forms = wpforms()->form->get(
            '',  // Pass an empty string to fetch all forms
            array(
                'number' => -1,  // get all forms
                'orderby' => 'ID',  // Sort forms by ID (optional)
                'order' => 'ASC',  // Ascending order (optional)
            )
        );
    
        // Check if forms exist
        if ( ! empty( $forms ) ) {
            foreach ( $forms as $form ) {
                // Add each form to the forms array
                $forms_list[] = array(
                    'ID' => $form->ID,
                    'title' => $form->post_title,
                    'content' => $form->post_content,
                );
            }
        }
    
        return $forms_list;
    }
}