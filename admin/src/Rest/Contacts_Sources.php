<?php

namespace MeuMouse\Joinotify\Rest;

use MeuMouse\Joinotify\Cloud_Sync\Catalog;
use MeuMouse\Joinotify\Cloud_Sync\Cloud_Sync;
use MeuMouse\Joinotify\Cloud_Sync\Consent;
use MeuMouse\Joinotify\Contacts\Sources;
use MeuMouse\Joinotify\Core\Helpers;
use WP_REST_Request;

defined('ABSPATH') || exit;

/**
 * What the Sources tab of Audiences & Contacts shows: the Cloud sync switches, the tags and custom
 * fields each integration adds, and the site's forms with their rules.
 *
 * @since 2.5.0
 */
class Contacts_Sources extends Abstract_Route {

	/**
	 * Cloud sync settings the Sources tab edits, with their kind.
	 *
	 * @since 2.5.0
	 * @var array<string,string>
	 */
	const SYNC_SETTINGS = array(
		'enable_cloud_sync' => 'switch',
		'cloud_sync_woocommerce' => 'switch',
		'cloud_sync_wordpress' => 'switch',
		'cloud_sync_forms' => 'switch',
		'cloud_sync_carts' => 'switch',
		'cloud_sync_send_address' => 'switch',
		'cloud_sync_send_items' => 'switch',
		'cloud_sync_consent_checkbox' => 'switch',
		'cloud_sync_source_tag' => 'text',
		'cloud_sync_consent_text' => 'text',
	);

	/**
	 * Route path.
	 *
	 * @var string
	 */
	protected $route = '/admin/contacts/sources';

	/**
	 * HTTP methods.
	 *
	 * @var string
	 */
	protected $methods = 'GET';


	/**
	 * Handle request.
	 *
	 * @since 2.5.0
	 * @param WP_REST_Request $request Request instance.
	 * @return \WP_REST_Response
	 */
	public function handle( WP_REST_Request $request ) {
		return $this->success_response( self::state() );
	}


	/**
	 * The whole state of the Sources tab.
	 *
	 * @since 2.5.0
	 * @return array<string,mixed>
	 */
	public static function state() {
		$sync = array();

		foreach ( self::SYNC_SETTINGS as $key => $kind ) {
			$value = Cloud_Sync::setting( $key );
			$sync[ $key ] = 'switch' === $kind ? 'yes' === $value : $value;
		}

		return array(
			'connected' => Helpers::cloud_api_ready(),
			'enabled' => Cloud_Sync::is_enabled(),
			'sync' => $sync,
			'defaults' => array(
				'source_tag' => Cloud_Sync::source_tag(),
				'consent_text' => Consent::label(),
			),
			'config' => Sources::config(),
			'integrations' => array_keys( Catalog::integrations() ),
			'forms' => Sources::available_forms(),
			'attribute_sources' => Sources::attribute_sources(),
		);
	}
}
