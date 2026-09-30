<?php

namespace MeuMouse\Joinotify\Admin\Contacts;

use MeuMouse\Joinotify\Admin\Admin;
use MeuMouse\Joinotify\Api\Cloud_Contacts;
use MeuMouse\Joinotify\Core\Helpers;

defined('ABSPATH') || exit;

/**
 * Data the "Audiences & Contacts" screen starts from.
 *
 * The screen holds no contact of its own: it lists and edits the account's contact base on the
 * Joinotify platform. The bootstrap only says whether the site is connected, what its key may do
 * and the defaults a new contact is typed with.
 *
 * @since 2.5.0
 * @package MeuMouse\Joinotify\Admin\Contacts
 * @author MeuMouse.com
 */
class Registry {

	/**
	 * Admin page slug.
	 *
	 * @since 2.5.0
	 * @var string
	 */
	const PAGE = 'joinotify-contacts';


	/**
	 * Build the bootstrap payload for the Vue screen.
	 *
	 * @since 2.5.0
	 * @param bool $refresh Ask the platform again what the key may do.
	 * @return array<string,mixed>
	 */
	public static function get_bootstrap_data( $refresh = false ) {
		$connected = Helpers::cloud_api_ready();

		$data = array(
			'page' => 'contacts',
			'title' => __( 'Audiences & Contacts', 'joinotify' ),
			'date_format' => get_option( 'date_format' ),
			'time_format' => get_option( 'time_format' ),
			'connection' => array(
				'connected' => $connected,
				'settings_url' => admin_url( 'admin.php?page=joinotify-settings' ),
				'onboarding_url' => admin_url( 'admin.php?page=joinotify-onboarding' ),
				'panel_url' => defined( 'JOINOTIFY_PANEL_URL' ) ? esc_url_raw( JOINOTIFY_PANEL_URL ) : '',
			),
			'capability' => Cloud_Contacts::capability( $refresh ),
			'default_country' => self::default_country(),
			'locale' => str_replace( '_', '-', determine_locale() ),
			'rest' => array(
				'root' => esc_url_raw( rest_url( 'joinotify/v1' ) ),
				'nonce' => wp_create_nonce( 'wp_rest' ),
			),
		);

		/**
		 * Filter the bootstrap payload of the "Audiences & Contacts" screen.
		 *
		 * @since 2.5.0
		 * @param array $data Bootstrap payload.
		 */
		return apply_filters( 'Joinotify/Admin/Contacts/Bootstrap_Data', $data );
	}


	/**
	 * Region (ISO 3166-1 alpha-2) a phone typed without country code is read in.
	 *
	 * @since 2.5.0
	 * @return string e.g. "BR", or '' when the settings name no default country.
	 */
	public static function default_country() {
		$region = Helpers::dial_code_to_region( Admin::get_setting( 'joinotify_default_country_code' ) );

		return is_string( $region ) ? $region : '';
	}
}
