<?php

namespace MeuMouse\Joinotify\Integrations;

use MeuMouse\Joinotify\Admin\Admin;
use MeuMouse\Joinotify\Builder\Triggers;
use MeuMouse\Joinotify\Core\Workflow_Processor;

// Exit if accessed directly.
defined('ABSPATH') || exit;

/**
 * Add integration with WordPress hooks
 * 
 * @since 1.0.0
 * @version 1.4.7
 * @package MeuMouse\Joinotify\Integrations
 * @author MeuMouse.com
 */
class Wordpress extends Integrations_Base {

    /**
     * Construct function
     * 
     * @since 1.0.0
     * @version 1.4.7
     * @return void
     */
    public function __construct() {
        // settings tab + standard builder hooks (triggers, tab, content, placeholders, conditions)
        $this->register_settings_tab( 70 );
        $this->register_builder_hooks( 10, 2 );

        // fire hooks if WordPress is active
        if ( Admin::get_setting('enable_wordpress_integration') === 'yes' ) {
            // on user register
            add_action( 'user_register', array( $this, 'process_workflow_user_register' ), 10, 2 );

            // on user login
            add_action( 'wp_login', array( $this, 'process_workflow_user_login' ), 10, 2 );

            // on password reset
            add_action( 'password_reset', array( $this, 'process_workflow_password_reset' ), 10, 2 );

            // on password reset request (when the reset key/link is generated)
            add_action( 'retrieve_password_key', array( $this, 'process_workflow_retrieve_password' ), 10, 2 );

            // on change post status
            add_action( 'transition_post_status', array( $this, 'process_workflow_change_post_status' ), 10, 3 );
        }
    }


    /**
     * Add integration item on settings
     * 
     * @since 1.3.0
     * @param array $integrations | Current integrations
     * @return array
     */
    public function add_integration_item( $integrations ) {
        $integrations['wordpress'] = array(
            'title' => __('WordPress', 'joinotify'),
            'description' => __('Automate message delivery for WordPress event triggers.', 'joinotify'),
            'category' => 'content',
            'icon' => '<svg viewBox="0 0 24 24" version="1.1" xmlns="http://www.w3.org/2000/svg" xmlns:xlink="http://www.w3.org/1999/xlink" fill="#000000"><g stroke-width="0"></g><g stroke-linecap="round" stroke-linejoin="round"></g><g> <title>wordpress</title> <desc>Created with sketchtool.</desc> <g id="brand" stroke="none" stroke-width="1" fill="none" fill-rule="evenodd"> <g id="wordpress" fill="#000000"> <path d="M12,2 C6.4859945,2 2,6.48575931 2,11.9997648 C2,17.5142407 6.4859945,22 12,22 C17.5140055,22 22.0004704,17.5142407 22.0004704,11.9997648 C22.0004704,6.48575931 17.5140055,2 12,2 M12,21.5416167 C6.73882264,21.5416167 2.4586185,17.2609422 2.4586185,11.9997648 C2.4586185,6.73882264 6.73882264,2.45838331 12,2.45838331 C17.2611774,2.45838331 21.5416167,6.73882264 21.5416167,11.9997648 C21.5416167,17.2609422 17.2611774,21.5416167 12,21.5416167 M3.42157624,11.9996943 C3.42157624,15.3953527 5.39457654,18.3300407 8.25659117,19.7202427 L4.16430302,8.50854912 C3.68828053,9.57536631 3.42157624,10.756015 3.42157624,11.9996943 M17.7912933,11.5671112 C17.7912933,10.5066441 17.4102872,9.7726193 17.0838449,9.20111009 C16.6492156,8.49436722 16.2411628,7.89628166 16.2411628,7.18906842 C16.2411628,6.40071497 16.8392483,5.66669019 17.6816952,5.66669019 C17.7197959,5.66669019 17.756015,5.67139397 17.7929396,5.67351067 C16.2667984,4.27531221 14.2333545,3.42134105 12,3.42134105 C9.0029869,3.42134105 6.36628331,4.95900656 4.83238082,7.28784779 C5.03393777,7.29443308 5.22350008,7.2981961 5.38460453,7.2981961 C6.28161528,7.2981961 7.67087655,7.18906842 7.67087655,7.18906842 C8.13325807,7.16225687 8.18782191,7.84148263 7.72591077,7.89628166 C7.72591077,7.89628166 7.26094217,7.95061032 6.7439968,7.97765705 L9.86777676,17.2702086 L11.7452903,11.6397846 L10.4089466,7.97765705 C9.94680025,7.95061032 9.50911357,7.89628166 9.50911357,7.89628166 C9.04673205,7.86876455 9.1010607,7.16225687 9.56367741,7.18906842 C9.56367741,7.18906842 10.9799854,7.2981961 11.8229027,7.2981961 C12.7199135,7.2981961 14.1094099,7.18906842 14.1094099,7.18906842 C14.5720266,7.16225687 14.6263553,7.84148263 14.1639738,7.89628166 C14.1639738,7.89628166 13.69877,7.95061032 13.182295,7.97765705 L16.282556,17.1991815 L17.1386439,14.3404596 C17.5088313,13.1532256 17.7912933,12.3013712 17.7912933,11.5671112 M12.1506621,12.7502763 L9.57651873,20.2292857 C10.3453515,20.4555375 11.1579294,20.5787765 11.9999059,20.5787765 C12.9989887,20.5787765 13.9571486,20.406383 14.8489852,20.0926409 C14.8259366,20.0557163 14.8047696,20.0166749 14.7873656,19.9743409 L12.1506621,12.7502763 Z M19.5278817,7.88423999 C19.5645711,8.15752958 19.585503,8.45057504 19.585503,8.76643383 C19.585503,9.63663303 19.422517,10.6150192 18.9330887,11.8389426 L16.3128484,19.4146146 C18.8632376,17.9277499 20.5784708,15.1647499 20.5784708,11.9998118 C20.5784708,10.5082434 20.1974647,9.10604671 19.5278817,7.88423999" id="Shape"> </path> </g> </g> </g></svg>',
            'setting_key' => 'enable_wordpress_integration',
            'action_hook' => 'Joinotify/Settings/Tabs/Integrations/Wordpress',
        );

        return $integrations;
    }


    /**
     * Add WordPress triggers
     * 
     * @since 1.1.0
     * @version 1.4.7
     * @param array $triggers | Current triggers
     * @return array
     */
    public function add_triggers( $triggers ) {
        $triggers['wordpress'] = array(
            array(
                'data_trigger' => 'user_register',
                'title' => __( 'New user registration', 'joinotify' ),
                'description' => __( 'This trigger fires when a new user registration is received.', 'joinotify' ),
                'require_settings' => false,
            ),
            array(
                'data_trigger' => 'wp_login',
                'title' => __( 'User login', 'joinotify' ),
                'description' => __( 'This trigger fires when a user logs in to the site.', 'joinotify' ),
                'require_settings' => false,
            ),
            array(
                'data_trigger' => 'password_reset',
                'title' => __( 'User password recovery', 'joinotify' ),
                'description' => __( 'This trigger fires after a user resets their password on the site.', 'joinotify' ),
                'require_settings' => false,
            ),
            array(
                'data_trigger' => 'retrieve_password',
                'title' => __( 'Password reset request', 'joinotify' ),
                'description' => __( 'This trigger fires when a user requests a password reset link, allowing you to send the reset link.', 'joinotify' ),
                'require_settings' => false,
            ),
            array(
                'data_trigger' => 'transition_post_status',
                'title' => __( 'Post status changed', 'joinotify' ),
                'description' => __( 'This trigger fires when a post status changes on the site.', 'joinotify' ),
                'require_settings' => true,
            ),
        );

        return $triggers;
    }


    /**
     * Add WordPress triggers on sidebar
     * 
     * @since 1.0.0
     * @version 1.4.7
     * @return void
     */
    public function add_triggers_tab() {
        $integration_slug = 'wordpress';
        $integration_name = esc_html__( 'WordPress', 'joinotify' );
        $icon_svg = '<svg class="joinotify-tab-icon" viewBox="0 0 24 24" xmlns="http://www.w3.org/2000/svg" xmlns:xlink="http://www.w3.org/1999/xlink" fill="#000000" stroke="#000000"><g stroke-width="0"></g><g stroke-linecap="round" stroke-linejoin="round"></g><g><g stroke="none" stroke-width="1" fill="none" fill-rule="evenodd"><g fill="#000000"><path d="M12,2 C6.4859945,2 2,6.48575931 2,11.9997648 C2,17.5142407 6.4859945,22 12,22 C17.5140055,22 22.0004704,17.5142407 22.0004704,11.9997648 C22.0004704,6.48575931 17.5140055,2 12,2 M12,21.5416167 C6.73882264,21.5416167 2.4586185,17.2609422 2.4586185,11.9997648 C2.4586185,6.73882264 6.73882264,2.45838331 12,2.45838331 C17.2611774,2.45838331 21.5416167,6.73882264 21.5416167,11.9997648 C21.5416167,17.2609422 17.2611774,21.5416167 12,21.5416167 M3.42157624,11.9996943 C3.42157624,15.3953527 5.39457654,18.3300407 8.25659117,19.7202427 L4.16430302,8.50854912 C3.68828053,9.57536631 3.42157624,10.756015 3.42157624,11.9996943 M17.7912933,11.5671112 C17.7912933,10.5066441 17.4102872,9.7726193 17.0838449,9.20111009 C16.6492156,8.49436722 16.2411628,7.89628166 16.2411628,7.18906842 C16.2411628,6.40071497 16.8392483,5.66669019 17.6816952,5.66669019 C17.7197959,5.66669019 17.756015,5.67139397 17.7929396,5.67351067 C16.2667984,4.27531221 14.2333545,3.42134105 12,3.42134105 C9.0029869,3.42134105 6.36628331,4.95900656 4.83238082,7.28784779 C5.03393777,7.29443308 5.22350008,7.2981961 5.38460453,7.2981961 C6.28161528,7.2981961 7.67087655,7.18906842 7.67087655,7.18906842 C8.13325807,7.16225687 8.18782191,7.84148263 7.72591077,7.89628166 C7.72591077,7.89628166 7.26094217,7.95061032 6.7439968,7.97765705 L9.86777676,17.2702086 L11.7452903,11.6397846 L10.4089466,7.97765705 C9.94680025,7.95061032 9.50911357,7.89628166 9.50911357,7.89628166 C9.04673205,7.86876455 9.1010607,7.16225687 9.56367741,7.18906842 C9.56367741,7.18906842 10.9799854,7.2981961 11.8229027,7.2981961 C12.7199135,7.2981961 14.1094099,7.18906842 14.1094099,7.18906842 C14.5720266,7.16225687 14.6263553,7.84148263 14.1639738,7.89628166 C14.1639738,7.89628166 13.69877,7.95061032 13.182295,7.97765705 L16.282556,17.1991815 L17.1386439,14.3404596 C17.5088313,13.1532256 17.7912933,12.3013712 17.7912933,11.5671112 M12.1506621,12.7502763 L9.57651873,20.2292857 C10.3453515,20.4555375 11.1579294,20.5787765 11.9999059,20.5787765 C12.9989887,20.5787765 13.9571486,20.406383 14.8489852,20.0926409 C14.8259366,20.0557163 14.8047696,20.0166749 14.7873656,19.9743409 L12.1506621,12.7502763 Z M19.5278817,7.88423999 C19.5645711,8.15752958 19.585503,8.45057504 19.585503,8.76643383 C19.585503,9.63663303 19.422517,10.6150192 18.9330887,11.8389426 L16.3128484,19.4146146 C18.8632376,17.9277499 20.5784708,15.1647499 20.5784708,11.9998118 C20.5784708,10.5082434 20.1974647,9.10604671 19.5278817,7.88423999"></path></g></g></g></svg>';

        $this->render_integration_trigger_tab( $integration_slug, $integration_name, $icon_svg );
    }


    /**
     * Add content tab
     * 
     * @since 1.0.0
     * @version 1.4.7
     * @return void
     */
    public function add_triggers_content() {
        $this->render_integration_trigger_content('wordpress');
    }


    /**
     * Add WordPress placeholders on workflow builder
     * 
     * @since 1.1.0
     * @version 1.4.7
     * @param array $placeholders | Current placeholders
     * @return array
     */
    public function add_placeholders( $placeholders, $payload = array() ) {
        $current_user = wp_get_current_user();
        $post = isset( $payload['post_id'] ) ? get_post( $payload['post_id'] ) : null;
        
        $placeholders['wordpress'] = array(
            '{{ first_name }}' => array(
                'triggers' => array(), // is global
                'description' => __( 'To retrieve the user first name', 'joinotify' ),
                'replacement' => array(
                    'production' => $current_user->exists() ? $current_user->first_name : __( 'Could not retrieve the user first name', 'joinotify' ),
                    'sandbox' => $current_user->exists() ? $current_user->first_name : esc_html__( 'John', 'joinotify' ),
                ),
            ),
            '{{ last_name }}' => array(
                'triggers' => array(), // is global
                'description' => __( 'To retrieve the user last name', 'joinotify' ),
                'replacement' => array(
                    'production' => $current_user->exists() ? $current_user->last_name : __( 'Could not retrieve the user last name', 'joinotify' ),
                    'sandbox' => $current_user->exists() ? $current_user->last_name : esc_html__( 'Doe', 'joinotify' ),
                ),
            ),
            '{{ email }}' => array(
                'triggers' => array(), // is global
                'description' => __( 'To retrieve the user email', 'joinotify' ),
                'replacement' => array(
                    'production' => $current_user->exists() ? $current_user->user_email : esc_html__( 'Could not retrieve the user email', 'joinotify' ),
                    'sandbox' => $current_user->exists() ? $current_user->user_email : esc_html__( 'user@example.com', 'joinotify' ),
                ),
            ),
            '{{ site_url }}' => array(
                'triggers' => array(), // is global
                'description' => __( 'To retrieve the site URL', 'joinotify' ),
                'replacement' => array(
                    'production' => get_site_url(),
                    'sandbox' => get_site_url(),
                ),
            ),
            '{{ site_name }}' => array(
                'triggers' => array(), // is global
                'description' => __( 'To retrieve the site name', 'joinotify' ),
                'replacement' => array(
                    'production' => get_bloginfo('name'),
                    'sandbox' => get_bloginfo('name'),
                ),
            ),
            '{{ current_date }}' => array(
                'triggers' => array(), // is global
                'description' => __( 'To retrieve the current date', 'joinotify' ),
                'replacement' => array(
                    'production' => date( get_option('date_format') ),
                    'sandbox' => date( get_option('date_format') ),
                ),
            ),
            '{{ user_meta[META_KEY] }}' => array(
                'triggers' => array(), // is global
                'description' => __( 'To retrieve a user meta value. Replace META_KEY with the key you want to retrieve. Example: billing_phone', 'joinotify' ),
                'replacement' => array(),
            ),
        );

        // placeholders for posts
        $placeholders['wordpress']['{{ post_title }}'] = array(
            'triggers' => array( 'transition_post_status' ),
            'description' => __( 'To retrieve the post title', 'joinotify' ),
            'replacement' => array(
                'production' => $post ? $post->post_title : '',
                'sandbox' => __( 'Example post title', 'joinotify' ),
            ),
        );

        $placeholders['wordpress']['{{ post_date }}'] = array(
            'triggers' => array( 'transition_post_status' ),
            'description' => __( 'To retrieve the post publish date', 'joinotify' ),
            'replacement' => array(
                'production' => $post ? get_the_date( get_option( 'date_format' ), $post ) : '',
                'sandbox' => date( get_option('date_format') ),
            ),
        );

        $placeholders['wordpress']['{{ post_content }}'] = array(
            'triggers' => array( 'transition_post_status' ),
            'description' => __( 'To retrieve the post content (text only)', 'joinotify' ),
            'replacement' => array(
                'production' => $post ? joinotify_format_plain_text( $post->post_content ) : '',
                'sandbox' => __( 'Example post content', 'joinotify' ),
            ),
        );

        $placeholders['wordpress']['{{ post_link }}'] = array(
            'triggers' => array( 'transition_post_status' ),
            'description' => __( 'To retrieve the post link', 'joinotify' ),
            'replacement' => array(
                'production' => $post ? get_permalink( $post ) : '',
                'sandbox' => trailingslashit( get_site_url() ) . 'exemplo-de-post',
            ),
        );

        $placeholders['wordpress']['{{ post_tags }}'] = array(
            'triggers' => array( 'transition_post_status' ),
            'description' => __( 'To retrieve the post tags, separated by commas', 'joinotify' ),
            'replacement' => array(
                'production' => $post ? implode( ', ', wp_get_post_terms( $post->ID, 'post_tag', array( 'fields' => 'names' ) ) ) : '',
                'sandbox' => __( 'tag1, tag2', 'joinotify' ),
            ),
        );

        $placeholders['wordpress']['{{ post_categories }}'] = array(
            'triggers' => array( 'transition_post_status' ),
            'description' => __( 'To retrieve the post categories, separated by commas', 'joinotify' ),
            'replacement' => array(
                'production' => $post ? implode( ', ', wp_get_post_terms( $post->ID, 'category', array( 'fields' => 'names' ) ) ) : '',
                'sandbox' => __( 'Category 1, Category 2', 'joinotify' ),
            ),
        );

        $placeholders['wordpress']['{{ post_featured_image }}'] = array(
            'triggers' => array( 'transition_post_status' ),
            'description' => __( 'To retrieve the featured image URL of the post', 'joinotify' ),
            'replacement' => array(
                'production' => $post ? get_the_post_thumbnail_url( $post, 'full' ) : '',
                'sandbox' => trailingslashit( get_site_url() ) . 'wp-content/uploads/example.jpg',
            ),
        );

        // password reset link (available only on the password reset request trigger)
        $placeholders['wordpress']['{{ reset_password_link }}'] = array(
            'triggers' => array( 'retrieve_password' ),
            'description' => __( 'To retrieve the password reset link', 'joinotify' ),
            'replacement' => array(
                'production' => isset( $payload['reset_password_link'] ) ? $payload['reset_password_link'] : '',
                'sandbox' => trailingslashit( get_site_url() ) . 'wp-login.php?action=rp&key=EXAMPLEKEY&login=user',
            ),
        );

        return $placeholders;
    }


    /**
     * Add conditions for WordPress triggers
     * 
     * @since 1.2.0
     * @param array $conditions | Current conditions
     * @return array
     */
    public function add_conditions( $conditions ) {
        $wordpress_conditions = array(
            'user_register' => array(
                'user_role' => array(
                    'title' => __( 'User role', 'joinotify' ),
                    'description' => __( 'Allows checking the user role that fired the trigger.', 'joinotify' ),
                ),
                'user_meta' => array(
                    'title' => __( 'User metadata', 'joinotify' ),
                    'description' => __( 'Allows checking specific metadata for the user that fired the trigger.', 'joinotify' ),
                ),
            ),
            'wp_login' => array(
                'user_role' => array(
                    'title' => __( 'User role', 'joinotify' ),
                    'description' => __( 'Allows checking the role of the user who logged in.', 'joinotify' ),
                ),
                'user_meta' => array(
                    'title' => __( 'User metadata', 'joinotify' ),
                    'description' => __( 'Allows checking specific metadata for the user who requested the password reset.', 'joinotify' ),
                ),
            ),
            'password_reset' => array(
                'user_meta' => array(
                    'title' => __( 'User metadata', 'joinotify' ),
                    'description' => __( 'Allows checking specific metadata for the user who requested the password reset.', 'joinotify' ),
                ),
            ),
            'retrieve_password' => array(
                'user_role' => array(
                    'title' => __( 'User role', 'joinotify' ),
                    'description' => __( 'Allows checking the role of the user who requested the password reset.', 'joinotify' ),
                ),
                'user_meta' => array(
                    'title' => __( 'User metadata', 'joinotify' ),
                    'description' => __( 'Allows checking specific metadata for the user who requested the password reset.', 'joinotify' ),
                ),
            ),
            'transition_post_status' => array(
                'post_type' => array(
                    'title' => __( 'Post type', 'joinotify' ),
                    'description' => __( 'Allows checking the post type that was published.', 'joinotify' ),
                ),
            ),
        );

        return array_merge( $conditions, $wordpress_conditions );
    }


    /**
     * Processs workflow content on user register
     * 
     * @since 1.1.0
     * @version 1.4.7
     * @param int $user_id | User ID
     * @param array $userdata | User data
     * @return void
     */
    public function process_workflow_user_register( $user_id, $userdata ) {
        /**
         * Filter the payload before processing workflows
         * 
         * @since 1.3.0
         * @param array $payload | Payload to be processed
         */
        $payload = apply_filters( 'Joinotify/Process_Workflows/Wordpress/User_Register', array(
            'type' => 'trigger',
            'hook' => 'user_register',
            'integration' => 'wordpress',
            'user_id' => $user_id,
            'user_data' => $userdata,
        ));

        Workflow_Processor::process_workflows( $payload );
    }


    /**
     * Processs workflow content on user login
     * 
     * @since 1.1.0
     * @version 1.4.7
     * @param string $user_login | User login
     * @param object $user | User object
     * @return void
     */
    public function process_workflow_user_login( $user_login, $user ) {
        /**
         * Filter the payload before processing workflows
         * 
         * @since 1.3.0
         * @param array $payload | Payload to be processed
         */
        $payload = apply_filters( 'Joinotify/Process_Workflows/Wordpress/User_Login', array(
            'type' => 'trigger',
            // Must match the builder trigger id ('wp_login'); the previous
            // 'user_login' value never matched any workflow's stored trigger.
            'hook' => 'wp_login',
            'integration' => 'wordpress',
            'user_id' => $user->ID,
        ));

        Workflow_Processor::process_workflows( $payload );
    }


    /**
     * Processs workflow content on password reset
     * 
     * @since 1.1.0
     * @version 1.4.7
     * @param object $user | User object
     * @param string $new_pass | New password
     * @return void
     */
    public function process_workflow_password_reset( $user, $new_pass ) {
        /**
         * Filter the payload before processing workflows
         * 
         * @since 1.3.0
         * @param array $payload | Payload to be processed
         */
        $payload = apply_filters( 'Joinotify/Process_Workflows/Wordpress/Password_Reset', array(
            'type' => 'trigger',
            'hook' => 'password_reset',
            'integration' => 'wordpress',
            'user_id' => $user->ID,
        ));

        Workflow_Processor::process_workflows( $payload );
    }


    /**
     * Process workflow content on password reset request
     *
     * Fires when a user requests a password reset and the reset key has just been
     * generated, exposing the reset link through the {{ reset_password_link }} placeholder.
     *
     * @since 2.0.0
     * @param string $user_login | User login that requested the reset
     * @param string $key | Generated password reset key
     * @return void
     */
    public function process_workflow_retrieve_password( $user_login, $key ) {
        $user = get_user_by( 'login', $user_login );

        // bail if the user could not be resolved
        if ( ! $user ) {
            return;
        }

        // build the reset link the same way WordPress core does in retrieve_password()
        $reset_link = network_site_url( 'wp-login.php?action=rp&key=' . $key . '&login=' . rawurlencode( $user_login ), 'login' );

        /**
         * Filter the payload before processing workflows
         *
         * @since 2.0.0
         * @param array $payload | Payload to be processed
         */
        $payload = apply_filters( 'Joinotify/Process_Workflows/Wordpress/Retrieve_Password', array(
            'type' => 'trigger',
            'hook' => 'retrieve_password',
            'integration' => 'wordpress',
            'user_id' => $user->ID,
            'reset_password_link' => $reset_link,
        ));

        Workflow_Processor::process_workflows( $payload );
    }


    /**
     * Process workflow content on post status changed
     * 
     * @since 1.1.0
     * @version 1.4.7
     * @param string $new_status | New post status
     * @param string $old_status | Old post status
     * @param object $post | Post object
     * @return void
     */
    public function process_workflow_change_post_status( $new_status, $old_status, $post ) {
        /**
         * Filter the payload before processing workflows
         * 
         * @since 1.3.0
         * @param array $payload | Payload to be processed
         */
        $payload = apply_filters( 'Joinotify/Process_Workflows/Wordpress/Change_Post_Status', array(
            'type' => 'trigger',
            'hook' => 'transition_post_status',
            'integration' => 'wordpress',
            'post_id' => $post->ID,
            'post_type' => $post->post_type,
            'post_status' => $new_status,
            'old_post_status' => $old_status,
        ));

        Workflow_Processor::process_workflows( $payload );
    }
}
