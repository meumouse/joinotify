<?php

namespace MeuMouse\Joinotify\Admin;

// Exit if accessed directly.
defined('ABSPATH') || exit;

/**
 * Shared envelope and file name for the JSON exports of the admin tables.
 *
 * The workflows, message history and processing queue screens each export
 * their rows as a JSON file. The rows differ per screen, but the envelope
 * around them — what the file is, which plugin and site produced it and
 * when — is the same, so a reader can tell the files apart by `type` alone.
 *
 * @since 2.4.2
 * @package MeuMouse\Joinotify\Admin
 * @author MeuMouse.com
 */
class Export {

	/**
	 * Export schema version. Bump when the envelope or a row shape changes.
	 *
	 * @since 2.4.2
	 * @var int
	 */
	const SCHEMA_VERSION = 1;


	/**
	 * Wrap exported rows in the shared envelope.
	 *
	 * @since 2.4.2
	 * @param string              $type Payload type (e.g. `joinotify_message_history_export`).
	 * @param array<string,mixed> $data Screen-specific keys appended after the envelope.
	 * @return array<string,mixed>
	 */
	public static function build_payload( $type, array $data ) {
		$payload = array_merge( array(
			'type' => (string) $type,
			'plugin' => 'joinotify',
			'plugin_version' => defined('JOINOTIFY_VERSION') ? JOINOTIFY_VERSION : '',
			'schema_version' => self::SCHEMA_VERSION,
			'exported_at' => gmdate('c'),
			'site_url' => home_url(),
		), $data );

		/**
		 * Filter a table export payload before it is returned for download.
		 *
		 * @since 2.4.2
		 * @param array<string,mixed> $payload Export payload.
		 * @param string              $type Payload type.
		 */
		return apply_filters( 'Joinotify/Admin/Export/Payload', $payload, (string) $type );
	}


	/**
	 * Suggested file name for an export, stamped with the site and the time.
	 *
	 * @since 2.4.2
	 * @param string $name What the file holds (e.g. `message-history`).
	 * @return string
	 */
	public static function build_filename( $name ) {
		$host = wp_parse_url( home_url(), PHP_URL_HOST );
		$site = $host ? sanitize_title( $host ) : 'site';

		return sprintf( 'joinotify-%s-%s-%s.json', sanitize_title( $name ), $site, gmdate('Ymd-His') );
	}
}
