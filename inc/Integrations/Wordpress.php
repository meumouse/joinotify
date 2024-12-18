<?php

namespace MeuMouse\Joinotify\Integrations;

use MeuMouse\Joinotify\Core\Admin;
use MeuMouse\Joinotify\Builder\Core;

// Exit if accessed directly.
defined('ABSPATH') || exit;

/**
 * Add integration with WordPress hooks
 * 
 * @since 1.0.0
 * @package MeuMouse.com
 */
class Wordpress extends Integrations_Base {

    /**
     * Construct function
     * 
     * @since 1.0.0
     * @return void
     */
    public function __construct() {
        add_action( 'Joinotify/Builder/Triggers', array( $this, 'add_wordpress_triggers' ), 40 );
        add_action( 'Joinotify/Builder/Triggers_Content', array( $this, 'add_wordpress_tab_content' ) );
    }


    /**
     * Add WordPress triggers on sidebar
     * 
     * @since 1.0.0
     * @return void
     */
    public function add_wordpress_triggers() {
        if ( Admin::get_setting('enable_wordpress_integration') === 'yes' ) : ?>
            <a href="#wordpress" class="nav-tab">
                <svg class="joinotify-tab-icon" viewBox="0 0 24 24" xmlns="http://www.w3.org/2000/svg" xmlns:xlink="http://www.w3.org/1999/xlink" fill="#000000" stroke="#000000"><g stroke-width="0"></g><g stroke-linecap="round" stroke-linejoin="round"></g><g><g stroke="none" stroke-width="1" fill="none" fill-rule="evenodd"><g fill="#000000"><path d="M12,2 C6.4859945,2 2,6.48575931 2,11.9997648 C2,17.5142407 6.4859945,22 12,22 C17.5140055,22 22.0004704,17.5142407 22.0004704,11.9997648 C22.0004704,6.48575931 17.5140055,2 12,2 M12,21.5416167 C6.73882264,21.5416167 2.4586185,17.2609422 2.4586185,11.9997648 C2.4586185,6.73882264 6.73882264,2.45838331 12,2.45838331 C17.2611774,2.45838331 21.5416167,6.73882264 21.5416167,11.9997648 C21.5416167,17.2609422 17.2611774,21.5416167 12,21.5416167 M3.42157624,11.9996943 C3.42157624,15.3953527 5.39457654,18.3300407 8.25659117,19.7202427 L4.16430302,8.50854912 C3.68828053,9.57536631 3.42157624,10.756015 3.42157624,11.9996943 M17.7912933,11.5671112 C17.7912933,10.5066441 17.4102872,9.7726193 17.0838449,9.20111009 C16.6492156,8.49436722 16.2411628,7.89628166 16.2411628,7.18906842 C16.2411628,6.40071497 16.8392483,5.66669019 17.6816952,5.66669019 C17.7197959,5.66669019 17.756015,5.67139397 17.7929396,5.67351067 C16.2667984,4.27531221 14.2333545,3.42134105 12,3.42134105 C9.0029869,3.42134105 6.36628331,4.95900656 4.83238082,7.28784779 C5.03393777,7.29443308 5.22350008,7.2981961 5.38460453,7.2981961 C6.28161528,7.2981961 7.67087655,7.18906842 7.67087655,7.18906842 C8.13325807,7.16225687 8.18782191,7.84148263 7.72591077,7.89628166 C7.72591077,7.89628166 7.26094217,7.95061032 6.7439968,7.97765705 L9.86777676,17.2702086 L11.7452903,11.6397846 L10.4089466,7.97765705 C9.94680025,7.95061032 9.50911357,7.89628166 9.50911357,7.89628166 C9.04673205,7.86876455 9.1010607,7.16225687 9.56367741,7.18906842 C9.56367741,7.18906842 10.9799854,7.2981961 11.8229027,7.2981961 C12.7199135,7.2981961 14.1094099,7.18906842 14.1094099,7.18906842 C14.5720266,7.16225687 14.6263553,7.84148263 14.1639738,7.89628166 C14.1639738,7.89628166 13.69877,7.95061032 13.182295,7.97765705 L16.282556,17.1991815 L17.1386439,14.3404596 C17.5088313,13.1532256 17.7912933,12.3013712 17.7912933,11.5671112 M12.1506621,12.7502763 L9.57651873,20.2292857 C10.3453515,20.4555375 11.1579294,20.5787765 11.9999059,20.5787765 C12.9989887,20.5787765 13.9571486,20.406383 14.8489852,20.0926409 C14.8259366,20.0557163 14.8047696,20.0166749 14.7873656,19.9743409 L12.1506621,12.7502763 Z M19.5278817,7.88423999 C19.5645711,8.15752958 19.585503,8.45057504 19.585503,8.76643383 C19.585503,9.63663303 19.422517,10.6150192 18.9330887,11.8389426 L16.3128484,19.4146146 C18.8632376,17.9277499 20.5784708,15.1647499 20.5784708,11.9998118 C20.5784708,10.5082434 20.1974647,9.10604671 19.5278817,7.88423999"></path></g></g></g></svg>
                <?php esc_html_e( 'WordPress', 'joinotify' ) ?>
            </a>
        <?php endif;
    }


    /**
     * Add content tab
     * 
     * @since 1.0.0
     * @return void
     */
    public function add_wordpress_tab_content() {
        if ( Admin::get_setting('enable_wordpress_integration') === 'yes' ) : ?>
            <div id="wordpress" class="nav-content triggers-group">
                <?php foreach ( Core::get_triggers_by_context('wordpress') as $trigger ) : ?>
                    <div class="trigger-item <?php echo ( isset( $trigger['class'] ) ? $trigger['class'] : '' ) ?>" data-context="wordpress" data-trigger="<?php echo esc_attr( $trigger['data_trigger'] ); ?>">
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
}