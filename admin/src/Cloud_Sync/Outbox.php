<?php

namespace MeuMouse\Joinotify\Cloud_Sync;

// Exit if accessed directly.
defined('ABSPATH') || exit;

/**
 * The outbox: what the site has to tell Joinotify Cloud, written down before anything is sent.
 *
 * Writing here is the only thing a WooCommerce or WordPress hook does. A checkout must never wait
 * on the network, and a request that times out must never lose an order: the row is the truth,
 * the dispatcher (`Dispatcher`) sends it later, in batches, and a row that fails stays here until
 * it goes through or is given up on.
 *
 * Two kinds of row share the table: `event` (one site event for `POST /site-events`) and `contact`
 * (one contact for `POST /contacts/sync`, what the backfill writes). Each has an id generated
 * here — the event's id is the platform's key against resends, so sending the same row twice is
 * harmless.
 *
 * Its own table rather than an option: the telemetry buffer lives in an option because it holds
 * a few hundred anonymous counters; this holds customers' orders, can reach thousands of rows in
 * a backfill, and is read and updated row by row.
 *
 * @since 2.5.0
 * @package MeuMouse\Joinotify\Cloud_Sync
 * @author MeuMouse.com
 */
class Outbox {

	/**
	 * Schema version. Bump to trigger a dbDelta migration.
	 *
	 * @since 2.5.0
	 * @var string
	 */
	const DB_VERSION = '1.0.0';

	/**
	 * Option holding the schema version installed.
	 *
	 * @since 2.5.0
	 * @var string
	 */
	const DB_VERSION_OPTION = 'joinotify_cloud_outbox_db_version';

	/**
	 * A row claimed by a dispatcher that never came back (a fatal error, a killed request) goes
	 * back to the queue after this long.
	 *
	 * @since 2.5.0
	 * @var int
	 */
	const LEASE_SECONDS = 600;

	/**
	 * Attempts before a row is given up on and kept as `dead` for someone to look at.
	 *
	 * @since 2.5.0
	 * @var int
	 */
	const MAX_ATTEMPTS = 12;

	/**
	 * Days a sent row is kept (the monitor shows recent traffic), and a dead one (to be read).
	 *
	 * @since 2.5.0
	 * @var int
	 */
	const KEEP_SENT_DAYS = 7;
	const KEEP_DEAD_DAYS = 30;


	/**
	 * Table name with the site's prefix.
	 *
	 * @since 2.5.0
	 * @return string
	 */
	public static function table() {
		global $wpdb;

		return $wpdb->prefix . 'joinotify_cloud_outbox';
	}


	/**
	 * Create or upgrade the table — a cheap version check on every call after the first.
	 *
	 * @since 2.5.0
	 * @return void
	 */
	public static function maybe_create_table() {
		if ( get_option( self::DB_VERSION_OPTION ) === self::DB_VERSION ) {
			return;
		}

		global $wpdb;

		$table = self::table();
		$charset_collate = $wpdb->get_charset_collate();

		$sql = "CREATE TABLE {$table} (
			id BIGINT(20) UNSIGNED NOT NULL AUTO_INCREMENT,
			event_id VARCHAR(64) NOT NULL DEFAULT '',
			kind VARCHAR(10) NOT NULL DEFAULT 'event',
			name VARCHAR(80) NOT NULL DEFAULT '',
			payload LONGTEXT NULL,
			status VARCHAR(10) NOT NULL DEFAULT 'pending',
			attempts SMALLINT(6) NOT NULL DEFAULT 0,
			next_attempt_at DATETIME NOT NULL DEFAULT '0000-00-00 00:00:00',
			claimed_at DATETIME NULL,
			claim_token VARCHAR(40) NOT NULL DEFAULT '',
			last_error VARCHAR(191) NOT NULL DEFAULT '',
			created_at DATETIME NOT NULL DEFAULT '0000-00-00 00:00:00',
			sent_at DATETIME NULL,
			PRIMARY KEY  (id),
			UNIQUE KEY event_id (event_id),
			KEY status_next (status, next_attempt_at),
			KEY kind_status (kind, status),
			KEY created_at (created_at)
		) {$charset_collate};";

		require_once ABSPATH . 'wp-admin/includes/upgrade.php';
		dbDelta( $sql );

		update_option( self::DB_VERSION_OPTION, self::DB_VERSION );
	}


	/**
	 * Now, in UTC, as the table stores it.
	 *
	 * @since 2.5.0
	 * @param int $offset Seconds from now.
	 * @return string
	 */
	public static function now( $offset = 0 ) {
		return gmdate( 'Y-m-d H:i:s', time() + (int) $offset );
	}


	/**
	 * The envelope of one site event, as `POST /site-events` takes it.
	 *
	 * Pure, so the harness can check the shape the platform validates.
	 *
	 * @since 2.5.0
	 * @param string $id Unique id of the event (a UUID).
	 * @param string $name `wc.order.paid`, `wp.user.registered`…
	 * @param array<string,mixed> $data The event's data.
	 * @param array<string,mixed>|null $contact The contact block, in snake_case, or null.
	 * @param string $site_url The site's `home_url()`.
	 * @param int $occurred_at Unix time the event happened.
	 * @return array<string,mixed>
	 */
	public static function envelope( $id, $name, $data, $contact, $site_url, $occurred_at ) {
		$envelope = array(
			'id' => $id,
			'name' => $name,
			'occurred_at' => gmdate( 'Y-m-d\TH:i:s\Z', (int) $occurred_at ),
			'schema_version' => 1,
			'site_url' => $site_url,
			'data' => empty( $data ) ? new \stdClass() : $data,
		);

		if ( is_array( $contact ) && ! empty( $contact ) ) {
			$envelope['contact'] = $contact;
		}

		return $envelope;
	}


	/**
	 * Write one site event. The only thing a hook does — no network, one insert.
	 *
	 * @since 2.5.0
	 * @param string $name Event name.
	 * @param array<string,mixed> $data The event's data.
	 * @param array<string,mixed>|null $contact The contact block, or null.
	 * @param int|null $occurred_at Unix time the event happened; now when null.
	 * @return string|false The event id, or false when the row could not be written.
	 */
	public static function push_event( $name, $data, $contact = null, $occurred_at = null ) {
		/**
		 * Filter an event's data before it is queued — where a store adds what its flows need to
		 * read, for one event or for all. Returning `false` drops the event.
		 *
		 * @since 2.5.0
		 * @param array<string,mixed>|false $data
		 * @param string $name
		 * @param array<string,mixed>|null $contact
		 */
		$data = apply_filters( 'Joinotify/Cloud_Sync/Event_Data', $data, $name, $contact );

		if ( false === $data ) {
			return false;
		}

		$id = wp_generate_uuid4();
		$envelope = self::envelope( $id, $name, (array) $data, $contact, home_url(), $occurred_at ?? time() );

		return self::insert( $id, 'event', $name, $envelope ) ? $id : false;
	}


	/**
	 * Write one contact for `POST /contacts/sync` — the backfill's row.
	 *
	 * @since 2.5.0
	 * @param array<string,mixed> $row A sync row (`ref`, `phone`, `firstName`…, `occurredAt`).
	 * @return string|false The row id, or false.
	 */
	public static function push_contact( $row ) {
		$id = wp_generate_uuid4();

		return self::insert( $id, 'contact', 'contact', $row ) ? $id : false;
	}


	/**
	 * Insert a row, and wake the dispatcher at the end of the request.
	 *
	 * @since 2.5.0
	 * @param string $id
	 * @param string $kind
	 * @param string $name
	 * @param array<string,mixed> $payload
	 * @return bool
	 */
	private static function insert( $id, $kind, $name, $payload ) {
		global $wpdb;

		self::maybe_create_table();

		$json = wp_json_encode( $payload );

		if ( false === $json ) {
			return false;
		}

		$now = self::now();
		$written = $wpdb->insert( self::table(), array(
			'event_id' => $id,
			'kind' => $kind,
			'name' => substr( $name, 0, 80 ),
			'payload' => $json,
			'status' => 'pending',
			'next_attempt_at' => $now,
			'created_at' => $now,
		) );

		if ( $written ) {
			Dispatcher::wake();
		}

		return (bool) $written;
	}


	/**
	 * Take up to `$limit` rows of one kind that are due, and mark them as being sent.
	 *
	 * The claim is an UPDATE conditioned on the status, so two dispatchers that read the same rows
	 * (a cron run and an async one overlapping) do not both send them: only the rows this call
	 * moved to `sending` come back.
	 *
	 * @since 2.5.0
	 * @param string $kind `event` or `contact`.
	 * @param int $limit
	 * @return array<int,array<string,mixed>> Rows with `id`, `event_id`, `attempts` and the decoded `payload`.
	 */
	public static function claim( $kind, $limit ) {
		global $wpdb;

		$table = self::table();
		$now = self::now();

		// Rows a dispatcher claimed and never finished go back first.
		$wpdb->query( $wpdb->prepare(
			"UPDATE {$table} SET status = 'pending', claimed_at = NULL, claim_token = '' WHERE status = 'sending' AND claimed_at < %s",
			self::now( -self::LEASE_SECONDS )
		) );

		$ids = $wpdb->get_col( $wpdb->prepare(
			"SELECT id FROM {$table} WHERE kind = %s AND status = 'pending' AND next_attempt_at <= %s ORDER BY id ASC LIMIT %d",
			$kind,
			$now,
			max( 1, (int) $limit )
		) );

		if ( empty( $ids ) ) {
			return array();
		}

		$ids = array_map( 'intval', $ids );
		$placeholders = implode( ',', array_fill( 0, count( $ids ), '%d' ) );
		// A token of this claim, not the time: two dispatchers in the same second would otherwise
		// read back each other's rows.
		$token = wp_generate_uuid4();

		$wpdb->query( $wpdb->prepare(
			"UPDATE {$table} SET status = 'sending', claimed_at = %s, claim_token = %s WHERE status = 'pending' AND id IN ({$placeholders})",
			array_merge( array( $now, $token ), $ids )
		) );

		$rows = $wpdb->get_results( $wpdb->prepare(
			"SELECT id, event_id, attempts, payload FROM {$table} WHERE status = 'sending' AND claim_token = %s ORDER BY id ASC",
			$token
		), ARRAY_A );

		$claimed = array();

		foreach ( (array) $rows as $row ) {
			$payload = json_decode( (string) $row['payload'], true );
			$claimed[] = array(
				'id' => (int) $row['id'],
				'event_id' => (string) $row['event_id'],
				'attempts' => (int) $row['attempts'],
				'payload' => is_array( $payload ) ? $payload : array(),
			);
		}

		return $claimed;
	}


	/**
	 * Mark rows as delivered. A note (a skipped contact's reason) is kept for the monitor.
	 *
	 * @since 2.5.0
	 * @param array<int,int> $ids
	 * @param array<int,string> $notes Row id → note.
	 * @return void
	 */
	public static function mark_sent( $ids, $notes = array() ) {
		global $wpdb;

		if ( empty( $ids ) ) {
			return;
		}

		$table = self::table();
		$now = self::now();

		foreach ( $ids as $id ) {
			$wpdb->update( $table, array(
				'status' => 'sent',
				'sent_at' => $now,
				'claimed_at' => null,
				'last_error' => substr( (string) ( $notes[ $id ] ?? '' ), 0, 191 ),
			), array( 'id' => (int) $id ) );
		}
	}


	/**
	 * Give rows back to the queue for a later attempt — or give up on the ones that have had
	 * enough of them.
	 *
	 * @since 2.5.0
	 * @param array<int,array<string,mixed>> $rows Claimed rows.
	 * @param string $error Why.
	 * @param int $delay Seconds until the next attempt.
	 * @return void
	 */
	public static function retry( $rows, $error, $delay ) {
		global $wpdb;

		$table = self::table();

		foreach ( $rows as $row ) {
			$attempts = (int) $row['attempts'] + 1;
			$dead = $attempts >= self::MAX_ATTEMPTS;

			$wpdb->update( $table, array(
				'status' => $dead ? 'dead' : 'pending',
				'attempts' => $attempts,
				'next_attempt_at' => self::now( $delay ),
				'claimed_at' => null,
				'last_error' => substr( $error, 0, 191 ),
			), array( 'id' => (int) $row['id'] ) );
		}
	}


	/**
	 * Put rows back as they were, without counting an attempt — the dispatcher stopped for a
	 * reason that is not theirs (the sync was paused mid-run).
	 *
	 * @since 2.5.0
	 * @param array<int,array<string,mixed>> $rows
	 * @return void
	 */
	public static function release( $rows ) {
		global $wpdb;

		$table = self::table();

		foreach ( $rows as $row ) {
			$wpdb->update( $table, array(
				'status' => 'pending',
				'claimed_at' => null,
			), array( 'id' => (int) $row['id'] ) );
		}
	}


	/**
	 * Give up on rows the platform refused for what they are — sending them again fails the same.
	 *
	 * @since 2.5.0
	 * @param array<int,int> $ids
	 * @param array<int,string>|string $errors Row id → error, or one error for all.
	 * @return void
	 */
	public static function mark_dead( $ids, $errors ) {
		global $wpdb;

		$table = self::table();

		foreach ( $ids as $id ) {
			$error = is_array( $errors ) ? (string) ( $errors[ $id ] ?? '' ) : (string) $errors;

			$wpdb->update( $table, array(
				'status' => 'dead',
				'claimed_at' => null,
				'last_error' => substr( $error, 0, 191 ),
			), array( 'id' => (int) $id ) );
		}
	}


	/**
	 * Send dead rows again: the owner fixed what was wrong (a field, the plan's limit).
	 *
	 * @since 2.5.0
	 * @return int Rows requeued.
	 */
	public static function requeue_dead() {
		global $wpdb;

		$count = (int) $wpdb->query( $wpdb->prepare(
			'UPDATE ' . self::table() . " SET status = 'pending', attempts = 0, next_attempt_at = %s WHERE status = 'dead'",
			self::now()
		) );

		if ( $count > 0 ) {
			Dispatcher::wake();
		}

		return $count;
	}


	/**
	 * Delete dead rows the owner chose to discard.
	 *
	 * @since 2.5.0
	 * @return int
	 */
	public static function discard_dead() {
		global $wpdb;

		return (int) $wpdb->query( 'DELETE FROM ' . self::table() . " WHERE status = 'dead'" );
	}


	/**
	 * Delete what is past keeping: sent rows after a week, dead ones after a month.
	 *
	 * @since 2.5.0
	 * @return void
	 */
	public static function purge() {
		global $wpdb;

		if ( get_option( self::DB_VERSION_OPTION ) !== self::DB_VERSION ) {
			return;
		}

		$table = self::table();

		$wpdb->query( $wpdb->prepare(
			"DELETE FROM {$table} WHERE status = 'sent' AND created_at < %s",
			self::now( -self::KEEP_SENT_DAYS * DAY_IN_SECONDS )
		) );

		$wpdb->query( $wpdb->prepare(
			"DELETE FROM {$table} WHERE status = 'dead' AND created_at < %s",
			self::now( -self::KEEP_DEAD_DAYS * DAY_IN_SECONDS )
		) );
	}


	/**
	 * Counts per status, and the oldest row still waiting — what the settings screen shows.
	 *
	 * @since 2.5.0
	 * @return array<string,mixed>
	 */
	public static function summary() {
		global $wpdb;

		$summary = array(
			'pending' => 0,
			'sending' => 0,
			'sent' => 0,
			'dead' => 0,
			'oldest_pending_at' => null,
		);

		if ( get_option( self::DB_VERSION_OPTION ) !== self::DB_VERSION ) {
			return $summary;
		}

		$table = self::table();
		$rows = $wpdb->get_results( "SELECT status, COUNT(*) AS total FROM {$table} GROUP BY status", ARRAY_A );

		foreach ( (array) $rows as $row ) {
			if ( isset( $summary[ $row['status'] ] ) ) {
				$summary[ $row['status'] ] = (int) $row['total'];
			}
		}

		$oldest = $wpdb->get_var( "SELECT MIN(created_at) FROM {$table} WHERE status IN ('pending', 'sending')" );
		$summary['oldest_pending_at'] = $oldest ? $oldest . 'Z' : null;

		return $summary;
	}


	/**
	 * The latest rows given up on, with why — what the owner needs to fix them.
	 *
	 * @since 2.5.0
	 * @param int $limit
	 * @return array<int,array<string,mixed>>
	 */
	public static function recent_dead( $limit = 20 ) {
		global $wpdb;

		if ( get_option( self::DB_VERSION_OPTION ) !== self::DB_VERSION ) {
			return array();
		}

		$rows = $wpdb->get_results( $wpdb->prepare(
			'SELECT kind, name, attempts, last_error, created_at FROM ' . self::table() . " WHERE status = 'dead' ORDER BY id DESC LIMIT %d",
			max( 1, (int) $limit )
		), ARRAY_A );

		return array_map( static function( $row ) {
			return array(
				'kind' => (string) $row['kind'],
				'name' => (string) $row['name'],
				'attempts' => (int) $row['attempts'],
				'error' => (string) $row['last_error'],
				'created_at' => $row['created_at'] . 'Z',
			);
		}, (array) $rows );
	}


	/**
	 * What became of the contact rows written since a moment — the backfill's report: how many are
	 * waiting, sent, given up on, and why the platform skipped or kept some of the sent ones.
	 *
	 * @since 2.5.0
	 * @param string $since `Y-m-d H:i:s`, UTC.
	 * @return array{pending: int, sent: int, dead: int, notes: array<string,int>}
	 */
	public static function contact_results( $since ) {
		global $wpdb;

		$results = array( 'pending' => 0, 'sent' => 0, 'dead' => 0, 'notes' => array() );

		if ( get_option( self::DB_VERSION_OPTION ) !== self::DB_VERSION ) {
			return $results;
		}

		$rows = $wpdb->get_results( $wpdb->prepare(
			'SELECT status, last_error, COUNT(*) AS total FROM ' . self::table() . " WHERE kind = 'contact' AND created_at >= %s GROUP BY status, last_error",
			(string) $since
		), ARRAY_A );

		foreach ( (array) $rows as $row ) {
			$total = (int) $row['total'];
			$status = 'sending' === $row['status'] ? 'pending' : (string) $row['status'];

			if ( isset( $results[ $status ] ) && 'notes' !== $status ) {
				$results[ $status ] += $total;
			}

			if ( 'sent' === $status && '' !== (string) $row['last_error'] ) {
				$note = (string) $row['last_error'];
				$results['notes'][ $note ] = ( $results['notes'][ $note ] ?? 0 ) + $total;
			}
		}

		return $results;
	}


	/**
	 * The rows that carry an e-mail address — what the WordPress privacy exporter lists and the
	 * eraser removes. A text match on the JSON: the address is in the contact block and in the
	 * order's billing, never in a column of its own.
	 *
	 * @since 2.5.0
	 * @param string $email
	 * @param int $limit
	 * @return array<int,array<string,mixed>>
	 */
	public static function rows_with_email( $email, $limit = 100 ) {
		global $wpdb;

		$email = trim( (string) $email );

		if ( '' === $email || get_option( self::DB_VERSION_OPTION ) !== self::DB_VERSION ) {
			return array();
		}

		return (array) $wpdb->get_results( $wpdb->prepare(
			'SELECT id, event_id, kind, name, status, created_at, sent_at FROM ' . self::table() . ' WHERE payload LIKE %s ORDER BY id DESC LIMIT %d',
			'%"' . $wpdb->esc_like( $email ) . '"%',
			max( 1, (int) $limit )
		), ARRAY_A );
	}


	/**
	 * Remove every row that carries an e-mail address, sent or not.
	 *
	 * @since 2.5.0
	 * @param string $email
	 * @return int Rows removed.
	 */
	public static function erase_email( $email ) {
		global $wpdb;

		$email = trim( (string) $email );

		if ( '' === $email || get_option( self::DB_VERSION_OPTION ) !== self::DB_VERSION ) {
			return 0;
		}

		return (int) $wpdb->query( $wpdb->prepare(
			'DELETE FROM ' . self::table() . ' WHERE payload LIKE %s',
			'%"' . $wpdb->esc_like( $email ) . '"%'
		) );
	}


	/**
	 * Drop the table and its version — on uninstall.
	 *
	 * @since 2.5.0
	 * @return void
	 */
	public static function drop() {
		global $wpdb;

		$wpdb->query( 'DROP TABLE IF EXISTS ' . self::table() );
		delete_option( self::DB_VERSION_OPTION );
	}
}
