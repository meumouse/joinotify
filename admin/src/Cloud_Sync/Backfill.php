<?php

namespace MeuMouse\Joinotify\Cloud_Sync;

// Exit if accessed directly.
defined('ABSPATH') || exit;

/**
 * "Sync existing customers": the people the store already has, sent once as contacts.
 *
 * The live events only reach who buys or signs up from now on; a store switching the sync on has
 * years of customers its first campaign should reach. The backfill walks them in steps — users by
 * id, then buyers without an account by their orders — and writes one contact row per person to
 * the outbox, which the dispatcher sends to `POST /contacts/sync` in batches of 500 with
 * `triggers: false`: ten thousand customers arriving must not greet ten thousand people.
 *
 * It never blocks the screen that started it. Each step runs from a scheduled task for at most
 * `BUDGET_SECONDS`, saves where it stopped, and schedules the next; a failure or a restart resumes
 * from there. What the platform did with each row (created, skipped for having no phone, over the
 * plan's contact limit…) is read back from the outbox for the report.
 *
 * People without a phone number are counted and left out here: the platform creates nobody
 * without one (D4), so sending them would only be skipped there.
 *
 * @since 2.5.0
 * @package MeuMouse\Joinotify\Cloud_Sync
 * @author MeuMouse.com
 */
class Backfill {

	/**
	 * Where the run is.
	 *
	 * @since 2.5.0
	 * @var string
	 */
	const OPTION = 'joinotify_cloud_sync_backfill';

	/**
	 * The step's scheduled hook.
	 *
	 * @since 2.5.0
	 * @var string
	 */
	const HOOK = 'joinotify_cloud_sync_backfill_step';

	/**
	 * Users read per query, guest orders per page, and seconds per step.
	 *
	 * @since 2.5.0
	 * @var int
	 */
	const USERS_PER_QUERY = 200;
	const ORDERS_PER_PAGE = 100;
	const BUDGET_SECONDS = 20;


	/**
	 * @since 2.5.0
	 * @return void
	 */
	public static function register() {
		add_action( self::HOOK, array( __CLASS__, 'step' ) );
	}


	/**
	 * The run as saved, over its defaults.
	 *
	 * @since 2.5.0
	 * @return array<string,mixed>
	 */
	public static function load() {
		$saved = get_option( self::OPTION, array() );

		return array_merge( array(
			'status' => 'idle',
			'phase' => 'users',
			'cursor' => 0,
			'queued' => 0,
			'no_phone' => 0,
			'started_at' => '',
			'finished_at' => '',
			'error' => '',
		), is_array( $saved ) ? $saved : array() );
	}


	/**
	 * @since 2.5.0
	 * @param array<string,mixed> $patch
	 * @return array<string,mixed>
	 */
	private static function update( $patch ) {
		$run = array_merge( self::load(), $patch );
		update_option( self::OPTION, $run, false );

		return $run;
	}


	/**
	 * What a backfill would send — shown before the owner confirms.
	 *
	 * @since 2.5.0
	 * @return array<string,int>
	 */
	public static function estimate() {
		global $wpdb;

		$users = count_users();
		$with_phone = (int) $wpdb->get_var(
			"SELECT COUNT(DISTINCT user_id) FROM {$wpdb->usermeta} WHERE meta_key IN ('billing_phone', 'joinotify_user_phone') AND meta_value <> ''"
		);

		$guest_orders = 0;

		if ( function_exists( 'wc_get_orders' ) ) {
			$page = wc_get_orders( array(
				'customer_id' => 0,
				'limit' => 1,
				'paginate' => true,
				'return' => 'ids',
			) );
			$guest_orders = is_object( $page ) ? (int) $page->total : 0;
		}

		return array(
			'users' => (int) ( $users['total_users'] ?? 0 ),
			'users_with_phone' => $with_phone,
			'guest_orders' => $guest_orders,
		);
	}


	/**
	 * Start a run, from the beginning.
	 *
	 * @since 2.5.0
	 * @return array<string,mixed>|\WP_Error
	 */
	public static function start() {
		if ( ! Cloud_Sync::is_enabled() ) {
			return new \WP_Error( 'joinotify_sync_off', __( 'Switch the sync on and save before sending existing customers.', 'joinotify' ) );
		}

		if ( 'running' === self::load()['status'] ) {
			return new \WP_Error( 'joinotify_backfill_running', __( 'Existing customers are already being sent.', 'joinotify' ) );
		}

		Outbox::maybe_create_table();

		$run = self::update( array(
			'status' => 'running',
			'phase' => 'users',
			'cursor' => 0,
			'queued' => 0,
			'no_phone' => 0,
			'started_at' => Outbox::now(),
			'finished_at' => '',
			'error' => '',
		) );

		self::schedule();

		return $run;
	}


	/**
	 * Stop a run where it is. Rows already queued are still sent.
	 *
	 * @since 2.5.0
	 * @return array<string,mixed>
	 */
	public static function cancel() {
		wp_clear_scheduled_hook( self::HOOK );

		if ( function_exists( 'as_unschedule_all_actions' ) ) {
			as_unschedule_all_actions( self::HOOK, array(), Dispatcher::GROUP );
		}

		return self::update( array( 'status' => 'cancelled', 'finished_at' => Outbox::now() ) );
	}


	/**
	 * Schedule the next step.
	 *
	 * @since 2.5.0
	 * @return void
	 */
	private static function schedule() {
		if ( function_exists( 'as_enqueue_async_action' ) ) {
			as_enqueue_async_action( self::HOOK, array(), Dispatcher::GROUP );

			return;
		}

		wp_schedule_single_event( time() + 5, self::HOOK );
	}


	/**
	 * Queue people for at most `BUDGET_SECONDS`, then save and schedule the next step.
	 *
	 * @since 2.5.0
	 * @return void
	 */
	public static function step() {
		$run = self::load();

		if ( 'running' !== $run['status'] ) {
			return;
		}

		if ( ! Cloud_Sync::is_enabled() ) {
			self::update( array( 'status' => 'failed', 'error' => 'sync_off', 'finished_at' => Outbox::now() ) );

			return;
		}

		$deadline = microtime( true ) + self::BUDGET_SECONDS;

		try {
			while ( microtime( true ) < $deadline ) {
				$run = 'users' === $run['phase'] ? self::users_page( $run ) : self::guests_page( $run );
				self::update( $run );

				if ( 'done' === $run['phase'] ) {
					self::update( array( 'status' => 'done', 'finished_at' => Outbox::now() ) );

					return;
				}
			}
		} catch ( \Throwable $e ) {
			// Saved where it stopped: the next step resumes from there.
			self::update( array( 'error' => substr( $e->getMessage(), 0, 191 ) ) );
		}

		self::schedule();
	}


	/**
	 * Queue one page of users.
	 *
	 * @since 2.5.0
	 * @param array<string,mixed> $run
	 * @return array<string,mixed>
	 */
	private static function users_page( $run ) {
		global $wpdb;

		$ids = $wpdb->get_col( $wpdb->prepare(
			"SELECT ID FROM {$wpdb->users} WHERE ID > %d ORDER BY ID ASC LIMIT %d",
			(int) $run['cursor'],
			self::USERS_PER_QUERY
		) );

		foreach ( $ids as $id ) {
			$user = get_userdata( (int) $id );
			$run['cursor'] = (int) $id;

			if ( ! $user ) {
				continue;
			}

			self::queue( Contact::from_customer( $user ), $run );
		}

		if ( count( $ids ) < self::USERS_PER_QUERY ) {
			$run['phase'] = function_exists( 'wc_get_orders' ) && Cloud_Sync::syncs( 'woocommerce' ) ? 'guests' : 'done';
			$run['cursor'] = 0;
		}

		return $run;
	}


	/**
	 * Queue one page of orders placed without an account — one row per buyer, by their e-mail.
	 *
	 * @since 2.5.0
	 * @param array<string,mixed> $run
	 * @return array<string,mixed>
	 */
	private static function guests_page( $run ) {
		$page = (int) $run['cursor'] + 1;
		$orders = wc_get_orders( array(
			'customer_id' => 0,
			'limit' => self::ORDERS_PER_PAGE,
			'page' => $page,
			'orderby' => 'ID',
			'order' => 'ASC',
			'return' => 'objects',
		) );
		$seen = array();

		foreach ( $orders as $order ) {
			if ( ! $order instanceof \WC_Order || $order instanceof \WC_Order_Refund ) {
				continue;
			}

			$email = strtolower( trim( (string) $order->get_billing_email() ) );

			// One row per buyer in the page; a buyer who has an account is sent as that account.
			if ( '' === $email || isset( $seen[ $email ] ) || email_exists( $email ) ) {
				continue;
			}

			$seen[ $email ] = true;
			self::queue( Contact::from_guest( $order ), $run );
		}

		$run['cursor'] = $page;

		if ( count( $orders ) < self::ORDERS_PER_PAGE ) {
			$run['phase'] = 'done';
		}

		return $run;
	}


	/**
	 * Queue one person, or count them out for having no phone.
	 *
	 * @since 2.5.0
	 * @param array<string,mixed>|null $block
	 * @param array<string,mixed> $run Counters, updated.
	 * @return void
	 */
	private static function queue( $block, &$run ) {
		if ( ! is_array( $block ) || '' === trim( (string) ( $block['phone'] ?? '' ) ) ) {
			$run['no_phone']++;

			return;
		}

		if ( Outbox::push_contact( Contact::to_sync_row( $block, gmdate( 'Y-m-d\TH:i:s\Z' ) ) ) ) {
			$run['queued']++;
		}
	}


	/**
	 * The run with what the platform did with its rows — the monitor's report.
	 *
	 * @since 2.5.0
	 * @return array<string,mixed>
	 */
	public static function report() {
		$run = self::load();
		$run['results'] = '' !== $run['started_at'] ? Outbox::contact_results( $run['started_at'] ) : array();

		return $run;
	}
}
