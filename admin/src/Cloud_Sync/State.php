<?php

namespace MeuMouse\Joinotify\Cloud_Sync;

// Exit if accessed directly.
defined('ABSPATH') || exit;

/**
 * What the site knows about its own syncing: whether it is paused and why, the address it was
 * paired at, where each field of the platform's packs lives, and how the last attempts went.
 *
 * One option, not autoloaded: it is read by the dispatcher and the settings screen, never on a
 * front-end page load.
 *
 * A pause is a reason, not a flag, because each one has a different way out: a revoked key
 * waits for the site to be connected again, a copy of the site at another address waits for its
 * owner to connect it on its own, and a site paused in the panel waits for the panel.
 *
 * @since 2.5.0
 * @package MeuMouse\Joinotify\Cloud_Sync
 * @author MeuMouse.com
 */
class State {

	/**
	 * Option that holds the state.
	 *
	 * @since 2.5.0
	 * @var string
	 */
	const OPTION = 'joinotify_cloud_sync_state';

	/**
	 * The key was refused (401/403): revoked or replaced.
	 *
	 * @since 2.5.0
	 * @var string
	 */
	const PAUSE_AUTH = 'auth';

	/**
	 * The key is not bound to a connected site any more (404 site_not_found).
	 *
	 * @since 2.5.0
	 * @var string
	 */
	const PAUSE_SITE_MISSING = 'site_missing';

	/**
	 * This site answers at another address than the one it was paired at — a staging copy that
	 * took the production key along, most of the time.
	 *
	 * @since 2.5.0
	 * @var string
	 */
	const PAUSE_URL_MISMATCH = 'url_mismatch';


	/**
	 * Read the state, with every key present.
	 *
	 * @since 2.5.0
	 * @return array<string,mixed>
	 */
	public static function load() {
		$state = get_option( self::OPTION, array() );
		$state = is_array( $state ) ? $state : array();

		return array_merge( array(
			'paused' => '',
			'registered_url' => '',
			'mismatch_url' => '',
			'site_id' => '',
			'fields' => array(),
			'reported_at' => 0,
			'last_status' => 0,
			'last_error' => '',
			'last_attempt_at' => 0,
			'last_success_at' => 0,
			'failures' => 0,
			'next_at' => 0,
		), $state );
	}


	/**
	 * Write the state.
	 *
	 * @since 2.5.0
	 * @param array<string,mixed> $state
	 * @return void
	 */
	public static function save( $state ) {
		update_option( self::OPTION, $state, false );
	}


	/**
	 * Merge a few keys into the stored state.
	 *
	 * @since 2.5.0
	 * @param array<string,mixed> $patch
	 * @return array<string,mixed> The state after the change.
	 */
	public static function update( $patch ) {
		$state = array_merge( self::load(), $patch );
		self::save( $state );

		return $state;
	}


	/**
	 * Pause syncing for a reason.
	 *
	 * @since 2.5.0
	 * @param string $reason One of the PAUSE_* constants.
	 * @param array<string,mixed> $extra Keys to store with it.
	 * @return void
	 */
	public static function pause( $reason, $extra = array() ) {
		self::update( array_merge( $extra, array( 'paused' => $reason ) ) );
	}


	/**
	 * Clear a pause, when the reason for it is gone.
	 *
	 * @since 2.5.0
	 * @return void
	 */
	public static function resume() {
		self::update( array(
			'paused' => '',
			'mismatch_url' => '',
			'failures' => 0,
			'next_at' => 0,
		) );
	}


	/**
	 * The reason syncing is paused, or an empty string.
	 *
	 * @since 2.5.0
	 * @return string
	 */
	public static function paused() {
		return (string) self::load()['paused'];
	}


	/**
	 * Where each field of the platform's packs lives in the account, from the last report:
	 * `wc_total_spent` may be `wc_total_spent_site` when the account already had a field of that
	 * name with another type, or missing when neither key was free.
	 *
	 * @since 2.5.0
	 * @return array<string,string>
	 */
	public static function field_map() {
		$fields = self::load()['fields'];

		return is_array( $fields ) ? array_filter( $fields, 'is_string' ) : array();
	}
}
