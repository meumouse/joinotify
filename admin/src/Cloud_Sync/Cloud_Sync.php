<?php

namespace MeuMouse\Joinotify\Cloud_Sync;

use MeuMouse\Joinotify\Admin\Admin;
use MeuMouse\Joinotify\Core\Helpers;
use MeuMouse\Joinotify\Integrations\Integrations_Base;

// Exit if accessed directly.
defined('ABSPATH') || exit;

/**
 * Syncing with Joinotify Cloud: this site's customers become contacts of the Joinotify account,
 * and what happens here — an order paid, a sign-up, an abandoned cart — reaches the account's
 * flows as a site event.
 *
 * **Off by default, and nothing leaves the site until the owner switches it on.** It sends
 * customers' names, phone numbers and orders to a service, which WordPress.org only allows after
 * the owner agrees; the switch is that agreement, its card says exactly what is sent, and
 * `readme.txt` declares it. A connected site that never turns it on sends nothing beyond what
 * sending messages already needs.
 *
 * The pieces: `Outbox` (what is waiting to be sent, in its own table), `Dispatcher` (sends it in
 * batches, off the request), `Reporter` (tells the platform what this site is and sends), `State`
 * (why it is paused, where fields live), `Catalog` (the events and their samples) and, per
 * integration, the emitters that write events to the outbox.
 *
 * Registered as an Applications card rather than a settings tab of its own: the card and its modal
 * are the plugin's declarative settings, and need no screen of their own.
 *
 * @since 2.5.0
 * @package MeuMouse\Joinotify\Cloud_Sync
 * @author MeuMouse.com
 */
class Cloud_Sync extends Integrations_Base {

	/**
	 * The master switch.
	 *
	 * @since 2.5.0
	 * @var string
	 */
	const SETTING = 'enable_cloud_sync';

	/**
	 * Daily purge of the outbox.
	 *
	 * @since 2.5.0
	 * @var string
	 */
	const PURGE_HOOK = 'joinotify_cloud_sync_purge';

	/**
	 * What each switch is when the owner never touched it. Everything that is on by default is
	 * still behind the master switch, which is off.
	 *
	 * @since 2.5.0
	 * @var array<string,string>
	 */
	const DEFAULTS = array(
		'enable_cloud_sync' => 'no',
		'cloud_sync_woocommerce' => 'yes',
		'cloud_sync_wordpress' => 'yes',
		// Form fields are whatever the form asks — the owner opts in knowingly.
		'cloud_sync_forms' => 'no',
		'cloud_sync_carts' => 'yes',
		// Data minimization: the address is the one field a flow rarely needs.
		'cloud_sync_send_address' => 'no',
		'cloud_sync_send_items' => 'yes',
		'cloud_sync_source_tag' => '',
	);


	/**
	 * Wire the module. Cheap when syncing is off: the settings card, the table migration and the
	 * lifecycle hooks — no emitter listens to anything.
	 *
	 * @since 2.5.0
	 * @return void
	 */
	public function __construct() {
		$this->register_settings_tab( 90 );
		add_filter( 'Joinotify/Admin/Set_Default_Options', array( __CLASS__, 'add_defaults' ) );

		Dispatcher::register();
		Reporter::register();

		add_action( 'admin_init', array( Outbox::class, 'maybe_create_table' ), 5 );
		add_action( 'Joinotify/Upgraded', array( Outbox::class, 'maybe_create_table' ) );
		add_action( 'Joinotify/Upgraded', array( Reporter::class, 'schedule' ) );
		add_action( self::PURGE_HOOK, array( Outbox::class, 'purge' ) );

		add_action( 'Joinotify/Settings/Saved', array( __CLASS__, 'on_settings_saved' ), 10, 2 );
		add_action( 'Joinotify/Cloud_Api/Connected', array( __CLASS__, 'on_connected' ) );

		// A plugin switched on or off changes what this site can send.
		add_action( 'activated_plugin', array( Reporter::class, 'schedule' ) );
		add_action( 'deactivated_plugin', array( Reporter::class, 'schedule' ) );

		if ( ! self::is_enabled() ) {
			return;
		}

		add_action( 'admin_init', array( __CLASS__, 'ensure_scheduled' ) );

		/**
		 * Syncing is on: where the emitters subscribe to the site's hooks.
		 *
		 * @since 2.5.0
		 */
		do_action( 'Joinotify/Cloud_Sync/Ready' );
	}


	/**
	 * Whether syncing is on: switched on by the owner, and a Joinotify key to send with.
	 *
	 * @since 2.5.0
	 * @return bool
	 */
	public static function is_enabled() {
		return 'yes' === self::setting( self::SETTING ) && Helpers::cloud_api_ready();
	}


	/**
	 * A sync setting, falling back to its default when never saved.
	 *
	 * @since 2.5.0
	 * @param string $key
	 * @return string
	 */
	public static function setting( $key ) {
		$value = Admin::get_setting( $key );

		if ( false === $value || null === $value ) {
			return self::DEFAULTS[ $key ] ?? '';
		}

		return is_string( $value ) ? $value : (string) $value;
	}


	/**
	 * Whether a source's data is synced: `woocommerce`, `wordpress`, `forms` or `carts`.
	 *
	 * @since 2.5.0
	 * @param string $source
	 * @return bool
	 */
	public static function syncs( $source ) {
		return self::is_enabled() && 'yes' === self::setting( 'cloud_sync_' . $source );
	}


	/**
	 * The tag every contact from this site carries: the owner's choice, or the site's name.
	 *
	 * @since 2.5.0
	 * @return string
	 */
	public static function source_tag() {
		$tag = trim( self::setting( 'cloud_sync_source_tag' ) );

		if ( '' === $tag ) {
			$tag = wp_specialchars_decode( (string) get_bloginfo( 'name' ), ENT_QUOTES );
		}

		return mb_substr( $tag, 0, 60 );
	}


	/**
	 * The defaults, for a fresh install and for the settings screen.
	 *
	 * @since 2.5.0
	 * @param array<string,mixed> $defaults
	 * @return array<string,mixed>
	 */
	public static function add_defaults( $defaults ) {
		return array_merge( self::DEFAULTS, is_array( $defaults ) ? $defaults : array() );
	}


	/**
	 * Keep the recurring work on the calendar — self-healing on every admin load.
	 *
	 * @since 2.5.0
	 * @return void
	 */
	public static function ensure_scheduled() {
		Dispatcher::ensure_scheduled();

		if ( ! wp_next_scheduled( self::PURGE_HOOK ) ) {
			wp_schedule_event( time() + HOUR_IN_SECONDS, 'daily', self::PURGE_HOOK );
		}

		if ( ! wp_next_scheduled( Reporter::DAILY_HOOK ) ) {
			Reporter::schedule();
		}
	}


	/**
	 * Start or stop with the switch, and tell the platform when what is sent changed.
	 *
	 * @since 2.5.0
	 * @param array<string,mixed> $new Settings after the save.
	 * @param array<string,mixed> $old Settings before it.
	 * @return void
	 */
	public static function on_settings_saved( $new, $old ) {
		$was = 'yes' === ( $old[ self::SETTING ] ?? 'no' );
		$is = 'yes' === ( $new[ self::SETTING ] ?? 'no' );

		if ( $was && ! $is ) {
			Dispatcher::unschedule();
			Reporter::unschedule();
			wp_clear_scheduled_hook( self::PURGE_HOOK );

			return;
		}

		if ( ! $is ) {
			return;
		}

		$changed = ! $was;

		foreach ( array_keys( self::DEFAULTS ) as $key ) {
			if ( ( $new[ $key ] ?? null ) !== ( $old[ $key ] ?? null ) ) {
				$changed = true;
			}
		}

		if ( $changed && Helpers::cloud_api_ready() ) {
			Outbox::maybe_create_table();
			Reporter::schedule();
			self::ensure_scheduled();
		}
	}


	/**
	 * The site was (re)connected: a new key, maybe a new address. Whatever paused the sync for
	 * the old one no longer holds, and the next report registers where this site lives now — a
	 * staging copy connected on its own becomes a site of its own here.
	 *
	 * @since 2.5.0
	 * @return void
	 */
	public static function on_connected() {
		State::update( array(
			'paused' => '',
			'registered_url' => '',
			'mismatch_url' => '',
			'failures' => 0,
			'next_at' => 0,
		) );

		Reporter::schedule();
	}


	/**
	 * The Applications card and its modal.
	 *
	 * @since 2.5.0
	 * @param array<string,array<string,mixed>> $integrations
	 * @return array<string,array<string,mixed>>
	 */
	public function add_integration_item( $integrations ) {
		$integrations['cloud_sync'] = array(
			'title' => __( 'Joinotify Cloud sync', 'joinotify' ),
			'description' => __( 'Send this site\'s customers and events to your Joinotify account, so its flows, audiences and campaigns work with your orders, sign-ups and carts.', 'joinotify' ),
			'category' => 'others',
			'settings' => self::get_integration_settings(),
			'defaults' => self::DEFAULTS,
			'modal' => array(
				'title' => __( 'Joinotify Cloud sync', 'joinotify' ),
				'description' => self::consent_text(),
				'button_label' => __( 'Configure', 'joinotify' ),
				'size' => 'medium',
			),
			'icon' => '<svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" fill="currentColor"><path d="M18.944 11.112C18.507 7.67 15.56 5 12 5 9.244 5 6.85 6.611 5.757 9.15 3.609 9.792 2 11.82 2 14c0 2.757 2.243 5 5 5h11c2.206 0 4-1.794 4-4a4.01 4.01 0 0 0-3.056-3.888zM18 17H7c-1.654 0-3-1.346-3-3 0-1.404 1.199-2.756 2.673-3.015l.581-.102.192-.558C8.149 8.274 9.895 7 12 7c2.757 0 5 2.243 5 5v1h1c1.103 0 2 .897 2 2s-.897 2-2 2z"></path><path d="m10 12.586-1.293-1.293-1.414 1.414L10 15.414l5.707-5.707-1.414-1.414z"></path></svg>',
			'setting_key' => self::SETTING,
		);

		return $integrations;
	}


	/**
	 * What switching the sync on sends — shown where the switch is, in full, before anything is.
	 *
	 * @since 2.5.0
	 * @return string
	 */
	public static function consent_text() {
		return __( 'When switched on, this site sends to Joinotify (api.joinotify.com): the name, e-mail and phone number of customers and of the users who register; each order\'s number, status, totals, payment and shipping method and, if you allow it below, its items and addresses; the forms and carts you choose below; and, for the site itself, its address, name, the Joinotify version and which WooCommerce, form and checkout plugins are active. Nothing is sent before you switch it on, and switching it off stops it at once.', 'joinotify' );
	}


	/**
	 * The modal's fields.
	 *
	 * @since 2.5.0
	 * @return array<int,array<string,mixed>>
	 */
	public static function get_integration_settings() {
		$fields = array(
			self::field_toggle(
				'cloud_sync_woocommerce',
				esc_html__( 'WooCommerce orders and customers', 'joinotify' ),
				esc_html__( 'Order events (created, paid, completed, refunded…), subscriptions, and each customer with their order count, total spent and last purchase.', 'joinotify' ),
				array( 'default' => self::DEFAULTS['cloud_sync_woocommerce'] )
			),
			self::field_toggle(
				'cloud_sync_wordpress',
				esc_html__( 'WordPress users', 'joinotify' ),
				esc_html__( 'Sign-ups and profile updates of users who have a phone number.', 'joinotify' ),
				array( 'default' => self::DEFAULTS['cloud_sync_wordpress'] )
			),
			self::field_toggle(
				'cloud_sync_forms',
				esc_html__( 'Form submissions', 'joinotify' ),
				esc_html__( 'WPForms and Elementor Pro submissions that carry a phone number. Every field of the form is sent.', 'joinotify' ),
				array( 'default' => self::DEFAULTS['cloud_sync_forms'] )
			),
			self::field_toggle(
				'cloud_sync_carts',
				esc_html__( 'Abandoned carts', 'joinotify' ),
				esc_html__( 'Leads and abandoned, recovered or lost carts from Flexify Checkout.', 'joinotify' ),
				array( 'default' => self::DEFAULTS['cloud_sync_carts'] )
			),
			self::field_toggle(
				'cloud_sync_send_items',
				esc_html__( 'Send the items of each order', 'joinotify' ),
				esc_html__( 'Products, quantities and categories — what "bought coffee" audiences and cross-sell flows need.', 'joinotify' ),
				array( 'default' => self::DEFAULTS['cloud_sync_send_items'] )
			),
			self::field_toggle(
				'cloud_sync_send_address',
				esc_html__( 'Send billing and shipping addresses', 'joinotify' ),
				esc_html__( 'Off by default: city and state are always sent, the full address only when a flow needs it.', 'joinotify' ),
				array( 'default' => self::DEFAULTS['cloud_sync_send_address'] )
			),
			self::field_text(
				'cloud_sync_source_tag',
				esc_html__( 'Tag for contacts from this site', 'joinotify' ),
				esc_html__( 'Every contact this site sends gets this tag in Joinotify. Leave it empty to use the site\'s name.', 'joinotify' ),
				array(
					'placeholder' => wp_specialchars_decode( (string) get_bloginfo( 'name' ), ENT_QUOTES ),
					'default' => '',
				)
			),
		);

		/**
		 * Filter the fields of the sync settings modal.
		 *
		 * @since 2.5.0
		 * @param array<int,array<string,mixed>> $fields
		 */
		return (array) apply_filters( 'Joinotify/Cloud_Sync/Settings_Fields', $fields );
	}
}
