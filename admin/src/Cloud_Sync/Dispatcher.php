<?php

namespace MeuMouse\Joinotify\Cloud_Sync;

use MeuMouse\Joinotify\Api\Cloud_Client;
use MeuMouse\Joinotify\Core\Debug_Log;

// Exit if accessed directly.
defined('ABSPATH') || exit;

/**
 * Sends the outbox to Joinotify Cloud: site events to `POST /site-events` in batches of 100,
 * contacts to `POST /contacts/sync` in batches of 500.
 *
 * It never runs inside the request that wrote the row. A write wakes it at the end of the request
 * (an async Action Scheduler action, or a WP-Cron event a few seconds out), and a recurring run
 * every minute picks up whatever is due — retries, rows written while the network was down, a
 * backfill in progress. Each run works for a bounded time, so a large backfill is spread over
 * many runs instead of holding one PHP process for minutes.
 *
 * What an answer means is decided by `classify()`, a pure function the harness covers: 202/200
 * deliver, a refusal of one event gives up on that event, a dead key or a missing site pauses the
 * whole sync until the owner reconnects, a copy of the site at another address pauses it until the
 * owner connects that copy on its own, and anything temporary (network, 429, 5xx, a site paused in
 * the panel) retries later with a growing delay.
 *
 * @since 2.5.0
 * @package MeuMouse\Joinotify\Cloud_Sync
 * @author MeuMouse.com
 */
class Dispatcher {

	/**
	 * Action Scheduler / WP-Cron hook that runs a dispatch.
	 *
	 * @since 2.5.0
	 * @var string
	 */
	const HOOK = 'joinotify_cloud_sync_dispatch';

	/**
	 * Action Scheduler group, shared with the plugin's other actions.
	 *
	 * @since 2.5.0
	 * @var string
	 */
	const GROUP = 'joinotify';

	/**
	 * Transient that keeps two dispatches from running at once.
	 *
	 * @since 2.5.0
	 * @var string
	 */
	const LOCK = 'joinotify_cloud_sync_lock';

	/**
	 * Batch sizes — the platform's own ceilings.
	 *
	 * @since 2.5.0
	 * @var int
	 */
	const EVENT_BATCH = 100;
	const CONTACT_BATCH = 500;

	/**
	 * Seconds one dispatch may keep sending batches.
	 *
	 * @since 2.5.0
	 * @var int
	 */
	const BUDGET_SECONDS = 20;

	/**
	 * Seconds allowed for one request.
	 *
	 * @since 2.5.0
	 * @var int
	 */
	const TIMEOUT = 15;

	/**
	 * Whether this request already asked for a dispatch.
	 *
	 * @since 2.5.0
	 * @var bool
	 */
	private static $woken = false;


	/**
	 * Register the dispatch callback and the minute schedule it needs on WP-Cron.
	 *
	 * @since 2.5.0
	 * @return void
	 */
	public static function register() {
		add_action( self::HOOK, array( __CLASS__, 'run' ) );
		add_filter( 'cron_schedules', array( __CLASS__, 'add_minute_schedule' ) );
	}


	/**
	 * A one-minute WP-Cron interval, for sites without Action Scheduler.
	 *
	 * @since 2.5.0
	 * @param array<string,array<string,mixed>> $schedules
	 * @return array<string,array<string,mixed>>
	 */
	public static function add_minute_schedule( $schedules ) {
		if ( ! isset( $schedules['joinotify_minutely'] ) ) {
			$schedules['joinotify_minutely'] = array(
				'interval' => MINUTE_IN_SECONDS,
				'display' => __( 'Every minute (Joinotify)', 'joinotify' ),
			);
		}

		return $schedules;
	}


	/**
	 * Whether Action Scheduler (bundled with WooCommerce) is available.
	 *
	 * @since 2.5.0
	 * @return bool
	 */
	private static function has_action_scheduler() {
		return function_exists( 'as_schedule_recurring_action' ) && function_exists( 'as_has_scheduled_action' );
	}


	/**
	 * Make sure the recurring dispatch is on the calendar. Self-healing: called on every admin
	 * load and after every write.
	 *
	 * @since 2.5.0
	 * @return void
	 */
	public static function ensure_scheduled() {
		if ( ! Cloud_Sync::is_enabled() ) {
			return;
		}

		if ( self::has_action_scheduler() ) {
			if ( ! as_has_scheduled_action( self::HOOK, array(), self::GROUP ) ) {
				as_schedule_recurring_action( time() + MINUTE_IN_SECONDS, MINUTE_IN_SECONDS, self::HOOK, array(), self::GROUP );
			}

			return;
		}

		if ( ! wp_next_scheduled( self::HOOK ) ) {
			wp_schedule_event( time() + MINUTE_IN_SECONDS, 'joinotify_minutely', self::HOOK );
		}
	}


	/**
	 * Take the dispatch off the calendar — syncing was switched off.
	 *
	 * @since 2.5.0
	 * @return void
	 */
	public static function unschedule() {
		if ( function_exists( 'as_unschedule_all_actions' ) ) {
			as_unschedule_all_actions( self::HOOK, array(), self::GROUP );
		}

		wp_clear_scheduled_hook( self::HOOK );
	}


	/**
	 * Ask for a dispatch soon, once per request — at shutdown, so the request that wrote the row
	 * (a checkout) is answered before anything is sent.
	 *
	 * @since 2.5.0
	 * @return void
	 */
	public static function wake() {
		if ( self::$woken ) {
			return;
		}

		self::$woken = true;
		add_action( 'shutdown', array( __CLASS__, 'schedule_soon' ) );
	}


	/**
	 * Put a one-off dispatch on the queue for right now.
	 *
	 * @since 2.5.0
	 * @return void
	 */
	public static function schedule_soon() {
		if ( ! Cloud_Sync::is_enabled() ) {
			return;
		}

		self::ensure_scheduled();

		if ( function_exists( 'as_enqueue_async_action' ) ) {
			as_enqueue_async_action( self::HOOK, array( 'soon' ), self::GROUP );

			return;
		}

		if ( ! wp_next_scheduled( self::HOOK, array( 'soon' ) ) ) {
			wp_schedule_single_event( time() + 5, self::HOOK, array( 'soon' ) );
		}
	}


	/**
	 * Send what is due, for at most `BUDGET_SECONDS`.
	 *
	 * @since 2.5.0
	 * @return void
	 */
	public static function run() {
		if ( ! Cloud_Sync::is_enabled() || '' !== State::paused() ) {
			return;
		}

		if ( get_transient( self::LOCK ) ) {
			return;
		}

		set_transient( self::LOCK, 1, 2 * MINUTE_IN_SECONDS );

		try {
			Outbox::maybe_create_table();
			$deadline = time() + self::BUDGET_SECONDS;

			while ( time() < $deadline ) {
				$sent = self::send_batch( 'event', self::EVENT_BATCH );

				if ( null === $sent ) {
					break;
				}

				$sent_contacts = self::send_batch( 'contact', self::CONTACT_BATCH );

				if ( null === $sent_contacts || ( 0 === $sent && 0 === $sent_contacts ) ) {
					break;
				}
			}
		} finally {
			delete_transient( self::LOCK );
		}
	}


	/**
	 * Claim one batch of a kind and send it.
	 *
	 * @since 2.5.0
	 * @param string $kind
	 * @param int $limit
	 * @return int|null Rows claimed; null when the sync paused and no more should be sent.
	 */
	private static function send_batch( $kind, $limit ) {
		$rows = Outbox::claim( $kind, $limit );

		if ( empty( $rows ) ) {
			return 0;
		}

		$payloads = array_map( static function( $row ) {
			return $row['payload'];
		}, $rows );

		if ( 'event' === $kind ) {
			$response = Cloud_Client::request( 'POST', '/site-events', array( 'events' => $payloads ), self::TIMEOUT );
		} else {
			// The same batch retried after a lost answer replays the first answer instead of
			// applying twice: the key is the rows it carries.
			$key = 'jn-sync-' . substr( md5( implode( ',', wp_list_pluck( $rows, 'event_id' ) ) ), 0, 24 );
			$response = Cloud_Client::request( 'POST', '/contacts/sync', array(
				'contacts' => $payloads,
				'options' => array( 'triggers' => false ),
			), self::TIMEOUT, array( 'Idempotency-Key' => $key ) );
		}

		$status = is_wp_error( $response ) ? 0 : (int) wp_remote_retrieve_response_code( $response );
		$body = is_wp_error( $response ) ? array() : json_decode( (string) wp_remote_retrieve_body( $response ), true );
		$body = is_array( $body ) ? $body : array();
		$retry_after = is_wp_error( $response ) ? 0 : (int) wp_remote_retrieve_header( $response, 'retry-after' );
		$error = is_wp_error( $response ) ? $response->get_error_message() : '';

		$outcome = self::classify( $kind, $status, $body, count( $rows ) );

		return self::apply( $outcome, $rows, $status, $retry_after, $error );
	}


	/**
	 * What an answer means, without touching anything. Pure.
	 *
	 * @since 2.5.0
	 * @param string $kind `event` or `contact`.
	 * @param int $status HTTP status (0 for a transport failure).
	 * @param array<string,mixed> $body Decoded JSON body.
	 * @param int $count Rows in the batch.
	 * @return array<string,mixed> `action` (deliver|retry|pause|reject|backoff) plus what it needs:
	 *     `dead` (batch index → error) and `notes` (batch index → note) for deliver, `pause` and
	 *     `registered_url` for pause, `error` for the others.
	 */
	public static function classify( $kind, $status, $body, $count ) {
		$error = self::error_of( $body );
		$type = (string) ( $error['type'] ?? '' );

		if ( 202 === $status && 'event' === $kind ) {
			$dead = array();

			foreach ( (array) ( $body['data']['rejected'] ?? array() ) as $rejection ) {
				if ( isset( $rejection['index'] ) ) {
					$dead[ (int) $rejection['index'] ] = (string) ( $rejection['error'] ?? 'rejected' );
				}
			}

			return array( 'action' => 'deliver', 'dead' => $dead, 'notes' => array() );
		}

		if ( 200 === $status && 'contact' === $kind ) {
			$dead = array();
			$notes = array();

			foreach ( (array) ( $body['data']['results'] ?? array() ) as $result ) {
				if ( ! isset( $result['index'] ) ) {
					continue;
				}

				$index = (int) $result['index'];
				$reason = (string) ( $result['reason'] ?? '' );

				// A contact refused for what it is fails the same next time; a race on the
				// number (`conflict_retry`) does not, and neither does anything that went in.
				if ( 'failed' === ( $result['status'] ?? '' ) && 'conflict_retry' !== $reason ) {
					$dead[ $index ] = '' !== $reason ? $reason : 'failed';
				} elseif ( '' !== $reason ) {
					$notes[ $index ] = $reason;
				}
			}

			return array( 'action' => 'deliver', 'dead' => $dead, 'notes' => $notes );
		}

		if ( 401 === $status || ( 403 === $status && 'site_key_required' !== $type ) ) {
			return array( 'action' => 'pause', 'pause' => State::PAUSE_AUTH, 'error' => $type ?: 'unauthorized' );
		}

		if ( 404 === $status && 'site_not_found' === $type ) {
			return array( 'action' => 'pause', 'pause' => State::PAUSE_SITE_MISSING, 'error' => $type );
		}

		if ( 409 === $status && 'site_url_mismatch' === $type ) {
			return array(
				'action' => 'pause',
				'pause' => State::PAUSE_URL_MISMATCH,
				'registered_url' => (string) ( $error['registeredUrl'] ?? '' ),
				'error' => $type,
			);
		}

		// Paused in the panel, or the account's billing needs attention: nothing to fix on the
		// site, so it waits longer and asks again.
		if ( ( 409 === $status && 'site_paused' === $type ) || 402 === $status ) {
			return array( 'action' => 'backoff', 'delay' => 15 * MINUTE_IN_SECONDS, 'error' => $type ?: 'payment_required' );
		}

		// The whole body was refused: sending it again fails the same.
		if ( 422 === $status || 413 === $status ) {
			return array( 'action' => 'reject', 'error' => $type ?: 'invalid_request' );
		}

		return array( 'action' => 'retry', 'error' => $type ?: ( $status > 0 ? 'http_' . $status : 'network' ) );
	}


	/**
	 * The error object of a refusal, or an empty array.
	 *
	 * @since 2.5.0
	 * @param array<string,mixed> $body
	 * @return array<string,mixed>
	 */
	private static function error_of( $body ) {
		return isset( $body['error'] ) && is_array( $body['error'] ) ? $body['error'] : array();
	}


	/**
	 * Seconds until the next attempt of a batch that failed for a temporary reason: a minute,
	 * doubling with each attempt, up to an hour — or what the platform asked for.
	 *
	 * @since 2.5.0
	 * @param int $attempts Attempts so far.
	 * @param int $retry_after Seconds from a `Retry-After` header, or 0.
	 * @return int
	 */
	public static function backoff( $attempts, $retry_after = 0 ) {
		if ( $retry_after > 0 ) {
			return min( HOUR_IN_SECONDS, $retry_after );
		}

		return (int) min( HOUR_IN_SECONDS, MINUTE_IN_SECONDS * pow( 2, max( 0, (int) $attempts ) ) );
	}


	/**
	 * Do what `classify()` decided.
	 *
	 * @since 2.5.0
	 * @param array<string,mixed> $outcome
	 * @param array<int,array<string,mixed>> $rows Claimed rows, in batch order.
	 * @param int $status
	 * @param int $retry_after
	 * @param string $transport_error
	 * @return int|null Rows handled, or null to stop the run.
	 */
	private static function apply( $outcome, $rows, $status, $retry_after, $transport_error ) {
		$now = time();

		switch ( $outcome['action'] ) {
			case 'deliver':
				$sent = array();
				$dead = array();
				$notes = array();

				foreach ( $rows as $index => $row ) {
					if ( isset( $outcome['dead'][ $index ] ) ) {
						$dead[ $row['id'] ] = $outcome['dead'][ $index ];
						continue;
					}

					$sent[] = $row['id'];

					if ( isset( $outcome['notes'][ $index ] ) ) {
						$notes[ $row['id'] ] = $outcome['notes'][ $index ];
					}
				}

				Outbox::mark_sent( $sent, $notes );

				if ( ! empty( $dead ) ) {
					Outbox::mark_dead( array_keys( $dead ), $dead );
					Debug_Log::warning(
						sprintf( 'Joinotify Cloud refused %d synced item(s).', count( $dead ) ),
						array( 'channel' => 'cloud_sync', 'code' => 'item_rejected', 'context' => array_values( $dead ) )
					);
				}

				State::update( array(
					'last_status' => $status,
					'last_error' => '',
					'last_attempt_at' => $now,
					'last_success_at' => $now,
					'failures' => 0,
				) );

				return count( $rows );

			case 'pause':
				Outbox::release( $rows );
				State::pause( $outcome['pause'], array(
					'last_status' => $status,
					'last_error' => (string) $outcome['error'],
					'last_attempt_at' => $now,
					'mismatch_url' => State::PAUSE_URL_MISMATCH === $outcome['pause'] ? home_url() : '',
				) );

				Debug_Log::warning(
					sprintf( 'Joinotify Cloud sync paused: %s.', $outcome['pause'] ),
					array( 'channel' => 'cloud_sync', 'code' => 'sync_paused', 'response_code' => $status )
				);

				return null;

			case 'reject':
				Outbox::mark_dead( wp_list_pluck( $rows, 'id' ), (string) $outcome['error'] );
				Debug_Log::error(
					'Joinotify Cloud refused a whole sync batch.',
					array( 'channel' => 'cloud_sync', 'code' => 'batch_rejected', 'response_code' => $status )
				);
				State::update( array( 'last_status' => $status, 'last_error' => (string) $outcome['error'], 'last_attempt_at' => $now ) );

				return count( $rows );

			case 'backoff':
				Outbox::retry( array_map( static function( $row ) {
					// Waiting for someone in the panel is not a failure of the row.
					$row['attempts'] = max( 0, (int) $row['attempts'] - 1 );
					return $row;
				}, $rows ), (string) $outcome['error'], (int) $outcome['delay'] );
				State::update( array( 'last_status' => $status, 'last_error' => (string) $outcome['error'], 'last_attempt_at' => $now ) );

				return null;

			default:
				$attempts = (int) max( wp_list_pluck( $rows, 'attempts' ) );
				$error = '' !== $transport_error ? $transport_error : (string) $outcome['error'];

				Outbox::retry( $rows, $error, self::backoff( $attempts, $retry_after ) );

				$state = State::load();
				State::update( array(
					'last_status' => $status,
					'last_error' => $error,
					'last_attempt_at' => $now,
					'failures' => (int) $state['failures'] + 1,
				) );

				return null;
		}
	}
}
