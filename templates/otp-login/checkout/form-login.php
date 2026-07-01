<?php
/**
 * Checkout login form
 *
 * This template can be overridden by copying it to yourtheme/woocommerce/checkout/form-login.php.
 *
 * @see https://woocommerce.com/document/template-structure/
 * @package WooCommerce\Templates
 * @version 2.0.0
 *
 * @since 2.0.0
 */

defined('ABSPATH') || exit;

$registration_at_checkout   = WC_Checkout::instance()->is_registration_enabled();
$login_reminder_at_checkout = 'yes' === get_option( 'woocommerce_enable_checkout_login_reminder' );
$is_flexify_checkout        = function_exists( 'is_flexify_template' ) && is_flexify_template();

if ( is_user_logged_in() ) {
	return;
}

if ( $is_flexify_checkout ) :
	do_action( 'woocommerce_login_form_start' );
	return;
endif;

if ( $login_reminder_at_checkout ) : ?>
	<div class="woocommerce-form-login-toggle">
		<?php
		wc_print_notice(
			apply_filters( 'woocommerce_checkout_login_message', esc_html__( 'Already have an account?', 'joinotify' ) ) .
			' <a href="#" class="showlogin">' . esc_html__( 'Click here to log in', 'joinotify' ) . '</a>',
			'notice'
		);
		?>
	</div>
	<?php
endif;

if ( $registration_at_checkout || $login_reminder_at_checkout ) :
	do_action( 'woocommerce_login_form_start' );
endif;
