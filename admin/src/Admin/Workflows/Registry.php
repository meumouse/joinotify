<?php

namespace MeuMouse\Joinotify\Admin\Workflows;

use WP_Query;
use WP_Post;

// Exit if accessed directly.
defined('ABSPATH') || exit;

/**
 * Workflows list registry.
 *
 * Centralizes the read/mutation logic used by both the workflows admin
 * bootstrap and the workflows REST endpoints, so the Vue list screen has a
 * single, persistent source of truth.
 *
 * @since 2.0.0
 * @package MeuMouse\Joinotify\Admin\Workflows
 * @author MeuMouse.com
 */
class Registry {

	/**
	 * Workflow post type slug.
	 *
	 * @var string
	 */
	const POST_TYPE = 'joinotify-workflow';

	/**
	 * Statuses surfaced on the list screen.
	 *
	 * @var string[]
	 */
	const ALLOWED_STATUSES = array( 'publish', 'draft', 'trash' );

	/**
	 * Actions accepted by the mutation endpoints.
	 *
	 * @var string[]
	 */
	const ALLOWED_ACTIONS = array( 'publish', 'draft', 'trash', 'restore', 'delete_permanently' );


	/**
	 * Build the full bootstrap payload for the workflows Vue screen.
	 *
	 * The Vue list filters and paginates client-side, so it is seeded with every
	 * status. Mirrors the previous inline data-bootstrap payload, now served over
	 * a GET request.
	 *
	 * @since 2.0.0
	 * @param string $status Optional active status hint.
	 * @return array<string,mixed>
	 */
	public static function get_bootstrap_data( $status = 'publish' ) {
		$status = in_array( $status, self::ALLOWED_STATUSES, true ) ? $status : 'publish';
		$list = self::get_list_state();

		return array(
			'page'          => 'workflows',
			'title'         => __( 'Manage workflows', 'joinotify' ),
			'create_url'    => admin_url( 'admin.php?page=joinotify-workflows-builder' ),
			'active_status' => $status,
			'date_format'   => get_option( 'date_format' ),
			'time_format'   => get_option( 'time_format' ),
			'loading_delay' => 350,
			'workflows'     => $list['workflows'],
			'counts'        => $list['counts'],
			'pagination'    => $list['pagination'],
			'rest'          => array(
				'root'  => esc_url_raw( rest_url( 'joinotify/v1' ) ),
				'nonce' => wp_create_nonce( 'wp_rest' ),
			),
		);
	}


	/**
	 * Build a normalized list item from a workflow post.
	 *
	 * @since 2.0.0
	 * @param WP_Post $post Workflow post object.
	 * @return array<string,mixed>
	 */
	public static function build_item( $post ) {
		$id = (int) $post->ID;

		return array(
			'id' => $id,
			'name' => get_the_title( $post ),
			'created_at' => get_post_time( 'Y-m-d H:i:s', false, $post ),
			'status' => $post->post_status,
			'edit_url' => admin_url( 'admin.php?page=joinotify-workflows-builder&id=' . $id ),
			'delete_url' => admin_url( 'admin.php?page=joinotify-workflows&action=delete&id=' . $id ),
			'restore_url' => admin_url( 'admin.php?page=joinotify-workflows&action=restore&id=' . $id ),
			'delete_permanently_url' => admin_url( 'admin.php?page=joinotify-workflows&action=delete_permanently&id=' . $id ),
			'previous_status' => (string) get_post_meta( $id, '_wp_trash_meta_status', true ),
		);
	}


	/**
	 * Fetch every workflow across the surfaced statuses.
	 *
	 * The list screen filters and paginates client-side, so the registry
	 * returns the full collection ordered by date.
	 *
	 * @since 2.0.0
	 * @param string $search Optional search term.
	 * @return array<int,array<string,mixed>>
	 */
	public static function get_items( $search = '' ) {
		$args = array(
			'post_type' => self::POST_TYPE,
			'post_status' => self::ALLOWED_STATUSES,
			'posts_per_page' => -1,
			'orderby' => 'date',
			'order' => 'DESC',
			'no_found_rows' => true,
			'ignore_sticky_posts' => true,
		);

		$search = sanitize_text_field( (string) $search );

		if ( '' !== $search ) {
			$args['s'] = $search;
		}

		$query = new WP_Query( $args );
		$items = array();

		foreach ( $query->posts as $post ) {
			$items[] = self::build_item( $post );
		}

		return $items;
	}


	/**
	 * Count workflows per status.
	 *
	 * @since 2.0.0
	 * @return array<string,int>
	 */
	public static function get_counts() {
		$counts = wp_count_posts( self::POST_TYPE );

		return array(
			'publish' => isset( $counts->publish ) ? (int) $counts->publish : 0,
			'draft' => isset( $counts->draft ) ? (int) $counts->draft : 0,
			'trash' => isset( $counts->trash ) ? (int) $counts->trash : 0,
		);
	}


	/**
	 * Build the full list state payload (items + counts + pagination hints).
	 *
	 * @since 2.0.0
	 * @param string $search Optional search term.
	 * @param int    $per_page Per-page hint for the client paginator.
	 * @return array<string,mixed>
	 */
	public static function get_list_state( $search = '', $per_page = 20 ) {
		$items = self::get_items( $search );
		$per_page = max( 1, absint( $per_page ) );
		$total = count( $items );

		return array(
			'workflows' => $items,
			'counts' => self::get_counts(),
			'pagination' => array(
				'current_page' => 1,
				'per_page' => $per_page,
				'total_items' => $total,
				'total_pages' => (int) max( 1, ceil( $total / $per_page ) ),
			),
		);
	}


	/**
	 * Apply a status/lifecycle action to a set of workflow IDs.
	 *
	 * @since 2.0.0
	 * @param string    $action One of self::ALLOWED_ACTIONS.
	 * @param int[]     $ids Workflow post IDs.
	 * @return array<string,mixed>
	 */
	public static function apply_action( $action, $ids ) {
		$action = sanitize_key( (string) $action );

		if ( ! in_array( $action, self::ALLOWED_ACTIONS, true ) ) {
			return array(
				'processed' => 0,
				'error' => __( 'Invalid workflow action.', 'joinotify' ),
			);
		}

		$ids = array_filter( array_map( 'absint', (array) $ids ) );
		$processed = 0;

		foreach ( $ids as $id ) {
			if ( get_post_type( $id ) !== self::POST_TYPE ) {
				continue;
			}

			if ( self::apply_single( $action, $id ) ) {
				$processed++;
			}
		}

		return array( 'processed' => $processed );
	}


	/**
	 * Apply a single action to one workflow.
	 *
	 * @since 2.0.0
	 * @param string $action Action slug.
	 * @param int    $id Workflow post ID.
	 * @return bool
	 */
	private static function apply_single( $action, $id ) {
		switch ( $action ) {
			case 'publish':
			case 'draft':
				$updated = wp_update_post( array(
					'ID' => $id,
					'post_status' => $action,
				), true );

				return ! is_wp_error( $updated );

			case 'trash':
				return (bool) wp_trash_post( $id );

			case 'restore':
				// Restore to draft so a workflow never auto-resumes dispatching on untrash.
				$restored = wp_untrash_post( $id );

				if ( ! $restored ) {
					return false;
				}

				if ( get_post_status( $id ) !== 'draft' ) {
					wp_update_post( array(
						'ID' => $id,
						'post_status' => 'draft',
					) );
				}

				return true;

			case 'delete_permanently':
				return (bool) wp_delete_post( $id, true );
		}

		return false;
	}
}
