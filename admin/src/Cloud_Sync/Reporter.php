<?php

namespace MeuMouse\Joinotify\Cloud_Sync;

use MeuMouse\Joinotify\Api\Cloud_Client;
use MeuMouse\Joinotify\Core\Debug_Log;

// Exit if accessed directly.
defined('ABSPATH') || exit;

/**
 * The site's report to Joinotify Cloud (`PUT /sites/me`): its address, name, plugin version, the
 * integrations it has active and the events it sends, with a synthetic sample of each.
 *
 * It is only ever sent while syncing is on. Versions and active plugins are environment data, and
 * WordPress.org does not allow collecting them before the owner agrees — switching syncing on is
 * that agreement, and `readme.txt` says so.
 *
 * The answer carries where each field of the platform's packs lives in the account
 * (`wc_total_spent` may live at `wc_total_spent_site`); it is stored in `State` and every contact
 * block written afterwards uses those keys.
 *
 * It also guards against the copy: the address the site was paired at is kept, and a site that
 * finds itself at another one — a staging copy restored with the production database — pauses
 * before it sends a single test order to real customers. The platform refuses such a copy too;
 * this just stops it one request earlier.
 *
 * @since 2.5.0
 * @package MeuMouse\Joinotify\Cloud_Sync
 * @author MeuMouse.com
 */
class Reporter {

	/**
	 * Hook of the report, run off the request that asked for it.
	 *
	 * @since 2.5.0
	 * @var string
	 */
	const HOOK = 'joinotify_cloud_sync_report';

	/**
	 * Hook of the daily report that keeps the catalog and the integrations fresh.
	 *
	 * @since 2.5.0
	 * @var string
	 */
	const DAILY_HOOK = 'joinotify_cloud_sync_report_daily';


	/**
	 * Register the report callbacks.
	 *
	 * @since 2.5.0
	 * @return void
	 */
	public static function register() {
		add_action( self::HOOK, array( __CLASS__, 'report' ) );
		add_action( self::DAILY_HOOK, array( __CLASS__, 'report' ) );
	}


	/**
	 * Ask for a report in a few seconds — after a setting changed, a plugin was switched on or
	 * off, or the site was connected. Never inside the request that caused it.
	 *
	 * @since 2.5.0
	 * @return void
	 */
	public static function schedule() {
		if ( ! Cloud_Sync::is_enabled() ) {
			return;
		}

		if ( ! wp_next_scheduled( self::HOOK ) ) {
			wp_schedule_single_event( time() + 10, self::HOOK );
		}

		if ( ! wp_next_scheduled( self::DAILY_HOOK ) ) {
			wp_schedule_event( time() + DAY_IN_SECONDS, 'daily', self::DAILY_HOOK );
		}
	}


	/**
	 * Take the reports off the calendar.
	 *
	 * @since 2.5.0
	 * @return void
	 */
	public static function unschedule() {
		wp_clear_scheduled_hook( self::HOOK );
		wp_clear_scheduled_hook( self::DAILY_HOOK );
	}


	/**
	 * Two addresses are the same site: scheme, host and port, case-insensitive, trailing path
	 * ignored — the same normalization as the platform's.
	 *
	 * @since 2.5.0
	 * @param string $url
	 * @return string The origin, or an empty string.
	 */
	public static function origin( $url ) {
		$parts = wp_parse_url( trim( (string) $url ) );

		if ( empty( $parts['scheme'] ) || empty( $parts['host'] ) ) {
			return '';
		}

		$origin = strtolower( $parts['scheme'] . '://' . $parts['host'] );

		if ( ! empty( $parts['port'] ) ) {
			$origin .= ':' . (int) $parts['port'];
		}

		return $origin;
	}


	/**
	 * Whether this site now answers at another address than the one it was paired at.
	 *
	 * @since 2.5.0
	 * @return bool
	 */
	public static function moved() {
		$registered = (string) State::load()['registered_url'];

		return '' !== $registered && self::origin( $registered ) !== self::origin( home_url() );
	}


	/**
	 * The report's body.
	 *
	 * @since 2.5.0
	 * @return array<string,mixed>
	 */
	public static function body() {
		$integrations = Catalog::integrations();

		return array(
			'siteUrl' => home_url(),
			'name' => wp_specialchars_decode( (string) get_bloginfo( 'name' ), ENT_QUOTES ),
			'platform' => 'wordpress',
			'clientVersion' => defined( 'JOINOTIFY_VERSION' ) ? (string) JOINOTIFY_VERSION : '',
			'integrations' => empty( $integrations ) ? new \stdClass() : $integrations,
			'catalog' => Catalog::entries(),
		);
	}


	/**
	 * Send the report now.
	 *
	 * @since 2.5.0
	 * @return bool Whether the platform accepted it.
	 */
	public static function report() {
		if ( ! Cloud_Sync::is_enabled() ) {
			return false;
		}

		if ( self::moved() ) {
			State::pause( State::PAUSE_URL_MISMATCH, array( 'mismatch_url' => home_url() ) );

			return false;
		}

		$response = Cloud_Client::request( 'PUT', '/sites/me', self::body(), 15 );
		$status = is_wp_error( $response ) ? 0 : (int) wp_remote_retrieve_response_code( $response );
		$body = is_wp_error( $response ) ? array() : json_decode( (string) wp_remote_retrieve_body( $response ), true );
		$body = is_array( $body ) ? $body : array();

		if ( 200 === $status && isset( $body['data'] ) && is_array( $body['data'] ) ) {
			$data = $body['data'];
			$state = State::load();
			$patch = array(
				'site_id' => (string) ( $data['id'] ?? '' ),
				'registered_url' => (string) ( $data['siteUrl'] ?? home_url() ),
				'fields' => isset( $data['fields'] ) && is_array( $data['fields'] ) ? array_filter( $data['fields'], 'is_string' ) : array(),
				'reported_at' => time(),
			);

			// A report that went through proves the key and the site are good again.
			if ( in_array( $state['paused'], array( State::PAUSE_AUTH, State::PAUSE_SITE_MISSING ), true ) ) {
				$patch['paused'] = '';
				$patch['failures'] = 0;
			}

			State::update( $patch );
			Dispatcher::schedule_soon();

			return true;
		}

		$type = (string) ( $body['error']['type'] ?? '' );

		if ( 409 === $status && 'site_url_mismatch' === $type ) {
			State::pause( State::PAUSE_URL_MISMATCH, array(
				'registered_url' => (string) ( $body['error']['registeredUrl'] ?? '' ),
				'mismatch_url' => home_url(),
			) );
		} elseif ( 404 === $status && 'site_not_found' === $type ) {
			State::pause( State::PAUSE_SITE_MISSING );
		} elseif ( 401 === $status || 403 === $status ) {
			State::pause( State::PAUSE_AUTH );
		}

		Debug_Log::warning(
			'Joinotify Cloud did not accept the site report.',
			array(
				'channel' => 'cloud_sync',
				'code' => '' !== $type ? $type : 'report_failed',
				'response_code' => $status,
			)
		);

		State::update( array( 'last_status' => $status, 'last_error' => '' !== $type ? $type : 'report_failed', 'last_attempt_at' => time() ) );

		return false;
	}
}
