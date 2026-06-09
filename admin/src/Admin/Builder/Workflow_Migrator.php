<?php

namespace MeuMouse\Joinotify\Admin\Builder;

use MeuMouse\Joinotify\Core\Helpers;

defined('ABSPATH') || exit;

/**
 * Version-aware converter that upgrades legacy workflow content to the current
 * schema.
 *
 * Legacy workflows (exported or stored by plugin versions prior to 2.0.0) share
 * most of their structure with the current format, but a few shapes drifted over
 * time: condition nodes only carried the nested `condition_content` payload, the
 * delay period used inconsistent aliases, triggers could ship without a
 * `settings` container, and placeholder tokens may be renamed in the future.
 *
 * Rather than scattering ad-hoc backward-compat fallbacks across the importer,
 * the runtime and the Vue parser, every legacy structure is funneled through a
 * single, ordered pipeline of version-gated migration steps here. The pipeline
 * is idempotent: running it on already-current content is a safe no-op, so it can
 * be applied liberally (on import and on plugin upgrade) without double-mutating.
 *
 * The core transforms are intentionally free of WordPress functions so they can
 * be unit tested in isolation; only the bulk database routine touches WP APIs.
 *
 * @since 2.0.0
 */
class Workflow_Migrator {

	/**
	 * Schema version this migrator upgrades content to. Mirrors
	 * Registry::WORKFLOW_SCHEMA_VERSION (kept in sync intentionally so the two
	 * classes do not create a circular bootstrap dependency).
	 *
	 * @since 2.0.0
	 * @var string
	 */
	const CURRENT_SCHEMA_VERSION = '2.0.0';


	/**
	 * Whether content originating from the given version needs migration.
	 *
	 * @since 2.0.0
	 * @param string $from_version Source version (plugin_version or stored schema version).
	 * @return bool
	 */
	public static function needs_migration( $from_version ) {
		return version_compare( self::normalize_version( $from_version ), self::CURRENT_SCHEMA_VERSION, '<' );
	}


	/**
	 * Migrate a full exported workflow file array.
	 *
	 * @since 2.0.0
	 * @param array<string,mixed> $file Exported file ({plugin_version, post, workflow_content}).
	 * @return array<string,mixed>
	 */
	public static function migrate_file( $file ) {
		$file = is_array( $file ) ? $file : array();
		$from = isset( $file['plugin_version'] ) ? (string) $file['plugin_version'] : '';
		$content = isset( $file['workflow_content'] ) && is_array( $file['workflow_content'] ) ? $file['workflow_content'] : array();

		$file['workflow_content'] = self::migrate_content( $content, $from );

		// Stamp the file with the schema version it now conforms to.
		$file['plugin_version'] = self::CURRENT_SCHEMA_VERSION;

		return $file;
	}


	/**
	 * Run the migration pipeline over a workflow content node tree.
	 *
	 * @since 2.0.0
	 * @param array<int,mixed> $content Workflow content nodes.
	 * @param string $from_version Source version the content was produced by.
	 * @return array<int,array<string,mixed>>
	 */
	public static function migrate_content( $content, $from_version ) {
		if ( ! is_array( $content ) ) {
			return array();
		}

		$from = self::normalize_version( $from_version );

		foreach ( self::get_migration_steps() as $step ) {
			$to = isset( $step['to'] ) ? (string) $step['to'] : '';
			$callback = isset( $step['callback'] ) ? $step['callback'] : null;

			if ( '' === $to || ! is_callable( $callback ) ) {
				continue;
			}

			// Apply a step only when the source predates the version it targets.
			if ( version_compare( $from, $to, '<' ) ) {
				$content = (array) call_user_func( $callback, $content );
			}
		}

		return $content;
	}


	/**
	 * Return the ordered list of migration steps.
	 *
	 * Each step is { to: target version, callback: fn(array $content): array }.
	 * Third parties (or future core versions) register additional steps through
	 * the `Joinotify/Builder/Migration_Steps` filter; steps run in ascending
	 * target-version order so a chain (1.x -> 2.0 -> 2.1 ...) is applied
	 * deterministically.
	 *
	 * @since 2.0.0
	 * @return array<int,array<string,mixed>>
	 */
	public static function get_migration_steps() {
		$steps = array(
			array(
				'to' => '2.0.0',
				'callback' => array( self::class, 'migrate_to_2_0_0' ),
			),
		);

		if ( function_exists( 'apply_filters' ) ) {
			$steps = apply_filters( 'Joinotify/Builder/Migration_Steps', $steps );
		}

		usort( $steps, function ( $left, $right ) {
			return version_compare(
				isset( $left['to'] ) ? (string) $left['to'] : '0',
				isset( $right['to'] ) ? (string) $right['to'] : '0'
			);
		});

		return $steps;
	}


	/**
	 * Migrate every stored workflow whose schema predates the current one.
	 *
	 * Hooked on `Joinotify/Upgraded`. For each workflow it converts the content
	 * when needed, runs it back through the canonical sanitizer (which rebuilds
	 * `condition_content`/`delay_timestamp`), persists it, and rebuilds the
	 * runtime indexes — subsuming the standalone trigger-hook backfill.
	 *
	 * @since 2.0.0
	 * @param string $old_version Previously stored plugin version ('' on fresh install).
	 * @param string $new_version Current plugin version.
	 * @return void
	 */
	public static function migrate_stored_workflows( $old_version = '', $new_version = '' ) {
		if ( ! function_exists( 'get_posts' ) ) {
			return;
		}

		$workflows = get_posts( array(
			'post_type' => 'joinotify-workflow',
			'post_status' => 'any',
			'numberposts' => -1,
			'fields' => 'ids',
		));

		if ( empty( $workflows ) ) {
			return;
		}

		foreach ( $workflows as $post_id ) {
			$content = Helpers::get_workflow_content_meta( $post_id );

			if ( ! is_array( $content ) ) {
				continue;
			}

			$stored_schema = get_post_meta( $post_id, '_joinotify_workflow_schema_version', true );
			$from = is_string( $stored_schema ) && '' !== $stored_schema
				? $stored_schema
				: self::normalize_version( $old_version );

			if ( self::needs_migration( $from ) ) {
				$content = Registry::sanitize_workflow_content( self::migrate_content( $content, $from ) );
				Helpers::update_workflow_content_meta( $post_id, $content );
			}

			// Always (re)build the trigger-hook index and stamp the schema version,
			// even for workflows that did not need a content migration.
			Registry::update_workflow_indexes( $post_id, $content );
		}
	}


	/**
	 * Migration step: bring any pre-2.0.0 content up to the 2.0.0 schema.
	 *
	 * @since 2.0.0
	 * @param array<int,mixed> $content Workflow content nodes.
	 * @return array<int,array<string,mixed>>
	 */
	public static function migrate_to_2_0_0( $content ) {
		return self::walk_nodes( (array) $content, array( self::class, 'migrate_node_to_2_0_0' ) );
	}


	/**
	 * Apply the 2.0.0 transforms to a single node's data.
	 *
	 * @since 2.0.0
	 * @param array<string,mixed> $node Workflow node.
	 * @return array<string,mixed>
	 */
	public static function migrate_node_to_2_0_0( $node ) {
		if ( ! is_array( $node ) ) {
			return array();
		}

		$type = isset( $node['type'] ) ? (string) $node['type'] : 'action';
		$data = isset( $node['data'] ) && is_array( $node['data'] ) ? $node['data'] : array();

		if ( 'trigger' === $type ) {
			$data = self::migrate_trigger_data( $data );
		} else {
			$action = isset( $data['action'] ) ? (string) $data['action'] : '';

			if ( 'condition' === $action ) {
				$data = self::migrate_condition_data( $data );
			} elseif ( 'time_delay' === $action ) {
				$data = self::migrate_delay_data( $data );
			}
		}

		// Rename placeholder tokens across every textual field (no-op unless a
		// rename map is registered).
		$data = self::migrate_placeholders_deep( $data, self::get_placeholder_map() );

		$node['data'] = $data;

		return $node;
	}


	/**
	 * Normalize a legacy trigger node's data.
	 *
	 * @since 2.0.0
	 * @param array<string,mixed> $data Trigger node data.
	 * @return array<string,mixed>
	 */
	private static function migrate_trigger_data( $data ) {
		$map = self::get_trigger_slug_map();

		if ( isset( $data['trigger'] ) && is_string( $data['trigger'] ) && isset( $map[ $data['trigger'] ] ) ) {
			$data['trigger'] = $map[ $data['trigger'] ];
		}

		// The 2.0.0 builder expects a settings container on every trigger so
		// required-setting triggers can hydrate their fields.
		if ( ! isset( $data['settings'] ) || ! is_array( $data['settings'] ) ) {
			$data['settings'] = array();
		}

		return $data;
	}


	/**
	 * Lift a legacy condition node's nested payload into the flat keys the 2.0.0
	 * builder and runtime read.
	 *
	 * Legacy condition nodes only carried `condition_content`; the 2.0.0 builder
	 * reads flat keys (condition/condition_type/value/...). This promotes the
	 * nested values to flat keys without dropping `condition_content` (the
	 * sanitizer rebuilds a canonical copy afterwards).
	 *
	 * @since 2.0.0
	 * @param array<string,mixed> $data Condition node data.
	 * @return array<string,mixed>
	 */
	private static function migrate_condition_data( $data ) {
		$legacy = isset( $data['condition_content'] ) && is_array( $data['condition_content'] ) ? $data['condition_content'] : array();

		$data = self::fill_from_legacy( $data, 'condition', $legacy, 'condition' );
		$data = self::fill_from_legacy( $data, 'condition_type', $legacy, 'type' );
		$data = self::fill_from_legacy( $data, 'type_text', $legacy, 'type_text' );
		$data = self::fill_from_legacy( $data, 'value_text', $legacy, 'value_text' );
		$data = self::fill_from_legacy( $data, 'meta_key', $legacy, 'meta_key' );
		$data = self::fill_from_legacy( $data, 'field_id', $legacy, 'field_id' );

		// `value` is the comparison value the engine actually reads; it may
		// legitimately be an empty string, so key existence (not truthiness) drives it.
		if ( ! array_key_exists( 'value', $data ) ) {
			$data['value'] = array_key_exists( 'value', $legacy ) ? $legacy['value'] : '';
		}

		if ( ! isset( $data['products'] ) && isset( $legacy['products'] ) && is_array( $legacy['products'] ) ) {
			$data['products'] = $legacy['products'];
		}

		return $data;
	}


	/**
	 * Normalize a legacy delay node's data.
	 *
	 * @since 2.0.0
	 * @param array<string,mixed> $data Delay node data.
	 * @return array<string,mixed>
	 */
	private static function migrate_delay_data( $data ) {
		$period_map = self::get_delay_period_map();

		if ( isset( $data['delay_period'] ) && is_string( $data['delay_period'] ) && isset( $period_map[ $data['delay_period'] ] ) ) {
			$data['delay_period'] = $period_map[ $data['delay_period'] ];
		}

		// The runtime recomputes the delay at trigger time (Schedule::resolve_delay_seconds),
		// so drop any cached, possibly-stale timestamp baked in by the legacy builder.
		unset( $data['delay_timestamp'] );

		return $data;
	}


	/**
	 * Copy a legacy nested value into a flat key when the flat key is absent/empty.
	 *
	 * @since 2.0.0
	 * @param array<string,mixed> $data Node data.
	 * @param string $flat_key Destination flat key.
	 * @param array<string,mixed> $legacy Legacy nested payload.
	 * @param string $legacy_key Source key in the legacy payload.
	 * @return array<string,mixed>
	 */
	private static function fill_from_legacy( $data, $flat_key, $legacy, $legacy_key ) {
		$missing = ! isset( $data[ $flat_key ] ) || '' === $data[ $flat_key ];

		if ( $missing && isset( $legacy[ $legacy_key ] ) ) {
			$data[ $flat_key ] = $legacy[ $legacy_key ];
		}

		return $data;
	}


	/**
	 * Recursively rename placeholder tokens in every string within a value.
	 *
	 * @since 2.0.0
	 * @param mixed $value Value to walk.
	 * @param array<string,string> $map Map of exact old token => new token.
	 * @return mixed
	 */
	private static function migrate_placeholders_deep( $value, $map ) {
		if ( empty( $map ) ) {
			return $value;
		}

		if ( is_array( $value ) ) {
			foreach ( $value as $key => $item ) {
				$value[ $key ] = self::migrate_placeholders_deep( $item, $map );
			}

			return $value;
		}

		if ( is_string( $value ) ) {
			return strtr( $value, $map );
		}

		return $value;
	}


	/**
	 * Walk a node tree applying a transformer to each node, preserving both
	 * linear children arrays and condition branch containers.
	 *
	 * @since 2.0.0
	 * @param array<int,mixed> $nodes Nodes to walk.
	 * @param callable $transform fn(array $node): array
	 * @return array<int,array<string,mixed>>
	 */
	private static function walk_nodes( $nodes, $transform ) {
		$out = array();

		foreach ( $nodes as $node ) {
			if ( ! is_array( $node ) ) {
				continue;
			}

			$node = (array) call_user_func( $transform, $node );

			if ( isset( $node['children'] ) && is_array( $node['children'] ) ) {
				if ( self::is_branch_container( $node['children'] ) ) {
					$branches = array(
						'action_true' => array(),
						'action_false' => array(),
					);

					foreach ( array( 'action_true', 'action_false' ) as $branch_key ) {
						if ( isset( $node['children'][ $branch_key ] ) && is_array( $node['children'][ $branch_key ] ) ) {
							$branches[ $branch_key ] = self::walk_nodes( $node['children'][ $branch_key ], $transform );
						}
					}

					$node['children'] = $branches;
				} else {
					$node['children'] = self::walk_nodes( $node['children'], $transform );
				}
			}

			$out[] = $node;
		}

		return $out;
	}


	/**
	 * Whether a children payload is a condition branch container.
	 *
	 * @since 2.0.0
	 * @param array<string,mixed> $children Children payload.
	 * @return bool
	 */
	private static function is_branch_container( $children ) {
		return is_array( $children ) && ( array_key_exists( 'action_true', $children ) || array_key_exists( 'action_false', $children ) );
	}


	/**
	 * Map of renamed trigger slugs (old slug => current slug).
	 *
	 * Empty by default — every shipped 1.x trigger slug is still valid in 2.0.0.
	 * The filter lets future versions consolidate or rename triggers without
	 * touching this class.
	 *
	 * @since 2.0.0
	 * @return array<string,string>
	 */
	private static function get_trigger_slug_map() {
		return self::filter( 'Joinotify/Builder/Migration_Trigger_Slug_Map', array() );
	}


	/**
	 * Map of legacy delay-period aliases to the canonical 2.0.0 period keys.
	 *
	 * @since 2.0.0
	 * @return array<string,string>
	 */
	private static function get_delay_period_map() {
		return self::filter( 'Joinotify/Builder/Migration_Delay_Period_Map', array(
			'second' => 'seconds',
			'minutes' => 'minute',
			'min' => 'minute',
			'mins' => 'minute',
			'hour' => 'hours',
			'days' => 'day',
			'weeks' => 'week',
			'months' => 'month',
			'years' => 'year',
		) );
	}


	/**
	 * Map of renamed placeholder tokens (exact old token => new token).
	 *
	 * Empty by default; the filter is the extension point for future token
	 * renames so legacy messages keep resolving after an upgrade.
	 *
	 * @since 2.0.0
	 * @return array<string,string>
	 */
	private static function get_placeholder_map() {
		return self::filter( 'Joinotify/Builder/Migration_Placeholder_Map', array() );
	}


	/**
	 * Normalize a version string for comparison.
	 *
	 * @since 2.0.0
	 * @param mixed $version Version value.
	 * @return string
	 */
	private static function normalize_version( $version ) {
		$version = is_scalar( $version ) ? trim( (string) $version ) : '';

		return '' === $version ? '0' : $version;
	}


	/**
	 * Apply a WordPress filter when available, returning the value unchanged
	 * otherwise (so the pure transforms run under the standalone test harness).
	 *
	 * @since 2.0.0
	 * @param string $hook Filter hook name.
	 * @param mixed $value Value to filter.
	 * @return mixed
	 */
	private static function filter( $hook, $value ) {
		return function_exists( 'apply_filters' ) ? apply_filters( $hook, $value ) : $value;
	}
}
