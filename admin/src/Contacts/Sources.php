<?php

namespace MeuMouse\Joinotify\Contacts;

use MeuMouse\Joinotify\Admin\Admin;

defined('ABSPATH') || exit;

/**
 * How the integrations fill the contact base: the tags each source adds, the site data written to
 * the account's custom fields, and, per form, which fields are the phone, e-mail, name, consent and
 * custom fields.
 *
 * It rides on the Cloud sync rather than replacing it: the contact blocks the sync builds pass
 * through `Joinotify/Cloud_Sync/Contact_Block`, where this class adds what the owner configured on
 * the Sources tab of Audiences & Contacts. A form without a rule keeps the sync's own reading
 * (the field typed as phone, or labelled so).
 *
 * @since 2.5.0
 * @package MeuMouse\Joinotify\Contacts
 * @author MeuMouse.com
 */
class Sources {

	/**
	 * Sources a contact block can come from.
	 *
	 * @since 2.5.0
	 * @var string[]
	 */
	const SOURCES = array( 'woocommerce', 'wordpress', 'forms', 'carts' );

	/**
	 * Setting holding the rules of each form, keyed `plugin:form id`.
	 *
	 * @since 2.5.0
	 * @var string
	 */
	const FORM_RULES = 'cloud_sync_form_rules';

	/**
	 * Setting: `all` forms are synced, or only the `selected` ones (those with an enabled rule).
	 *
	 * @since 2.5.0
	 * @var string
	 */
	const FORMS_MODE = 'cloud_sync_forms_mode';

	/**
	 * Setting holding the site data written to custom fields: a list of `{ source, target }`.
	 *
	 * @since 2.5.0
	 * @var string
	 */
	const ATTRIBUTE_MAP = 'cloud_sync_attribute_map';

	/**
	 * Most tags a contact row may carry.
	 *
	 * @since 2.5.0
	 * @var int
	 */
	const MAX_TAGS = 20;


	/**
	 * Wire the filters.
	 *
	 * @since 2.5.0
	 * @return void
	 */
	public static function register() {
		add_filter( 'Joinotify/Admin/Set_Default_Options', array( __CLASS__, 'add_defaults' ) );

		// After Consent (10): a form's own consent only fills a block that has none.
		add_filter( 'Joinotify/Cloud_Sync/Contact_Block', array( __CLASS__, 'extend_block' ), 20, 2 );
	}


	/**
	 * The defaults of the settings this class owns.
	 *
	 * @since 2.5.0
	 * @param array<string,mixed> $defaults
	 * @return array<string,mixed>
	 */
	public static function add_defaults( $defaults ) {
		$own = array(
			self::FORMS_MODE => 'all',
			self::FORM_RULES => array(),
			self::ATTRIBUTE_MAP => array(),
		);

		foreach ( self::SOURCES as $source ) {
			$own[ self::tags_setting( $source ) ] = '';
		}

		return array_merge( $own, is_array( $defaults ) ? $defaults : array() );
	}


	/**
	 * The setting holding a source's tags.
	 *
	 * @since 2.5.0
	 * @param string $source One of SOURCES.
	 * @return string
	 */
	public static function tags_setting( $source ) {
		return 'cloud_sync_tags_' . $source;
	}


	/**
	 * A setting of this class, as stored.
	 *
	 * @since 2.5.0
	 * @param string $key Setting key.
	 * @param mixed  $default Value when never saved.
	 * @return mixed
	 */
	private static function setting( $key, $default ) {
		$value = Admin::get_setting( $key );

		return false === $value || null === $value ? $default : $value;
	}


	// ── The block ───────────────────────────────────────────────────────────────────────────


	/**
	 * Add the configured tags, custom fields and form consent to a contact block.
	 *
	 * @since 2.5.0
	 * @param array<string,mixed> $block  Block built by Cloud_Sync\Contact.
	 * @param array<string,mixed> $fields What it was built from; `source` says which integration.
	 * @return array<string,mixed>
	 */
	public static function extend_block( $block, $fields ) {
		$fields = is_array( $fields ) ? $fields : array();
		$source = isset( $fields['source'] ) ? (string) $fields['source'] : '';
		$tags = in_array( $source, self::SOURCES, true ) ? self::parse_tags( self::setting( self::tags_setting( $source ), '' ) ) : array();

		if ( ! empty( $fields['tags'] ) && is_array( $fields['tags'] ) ) {
			$tags = array_merge( $tags, $fields['tags'] );
		}

		$attributes = array_merge(
			self::site_attributes( self::attribute_map(), $fields ),
			isset( $fields['attributes'] ) && is_array( $fields['attributes'] ) ? $fields['attributes'] : array()
		);

		return self::merge_block( $block, $tags, $attributes, isset( $fields['consent'] ) && is_array( $fields['consent'] ) ? $fields['consent'] : null );
	}


	/**
	 * Merge tags, attributes and a consent into a block. Pure, for the harness.
	 *
	 * Tags are kept once each (case-insensitive), 20 at most, the block's own first. Attributes
	 * never replace a value the sync already computed. A consent only fills a block that has none,
	 * so a checkout opt-out or opt-in always wins over a form.
	 *
	 * @since 2.5.0
	 * @param array<string,mixed>      $block      Contact block.
	 * @param string[]                 $tags       Tag names to add.
	 * @param array<string,mixed>      $attributes Custom field values by key.
	 * @param array<string,mixed>|null $consent    Consent to use when the block has none.
	 * @return array<string,mixed>
	 */
	public static function merge_block( $block, $tags, $attributes, $consent = null ) {
		$all = array_merge( isset( $block['tags'] ) ? (array) $block['tags'] : array(), (array) $tags );
		$unique = array();

		foreach ( $all as $tag ) {
			$tag = self::clean( $tag, 60 );

			if ( '' !== $tag && ! isset( $unique[ mb_strtolower( $tag ) ] ) ) {
				$unique[ mb_strtolower( $tag ) ] = $tag;
			}
		}

		if ( ! empty( $unique ) ) {
			$block['tags'] = array_slice( array_values( $unique ), 0, self::MAX_TAGS );
		}

		$attributes = array_filter( (array) $attributes, static function( $value ) {
			return null !== $value && '' !== $value && array() !== $value;
		} );

		if ( ! empty( $attributes ) ) {
			$block['attributes'] = array_merge( $attributes, isset( $block['attributes'] ) ? (array) $block['attributes'] : array() );
		}

		if ( null !== $consent && ! isset( $block['consent'] ) ) {
			$block['consent'] = $consent;
		}

		return $block;
	}


	/**
	 * Tag names typed as a comma-separated list.
	 *
	 * @since 2.5.0
	 * @param mixed $value Stored setting.
	 * @return string[]
	 */
	public static function parse_tags( $value ) {
		$list = is_array( $value ) ? $value : explode( ',', (string) $value );
		$tags = array();

		foreach ( $list as $tag ) {
			$tag = self::clean( $tag, 60 );

			if ( '' !== $tag ) {
				$tags[] = $tag;
			}
		}

		return array_values( array_unique( $tags ) );
	}


	// ── Site data written to custom fields ──────────────────────────────────────────────────


	/**
	 * Where site data can be read from, for the mapping on the Sources tab.
	 *
	 * @since 2.5.0
	 * @return array<int,array{value:string,label:string}>
	 */
	public static function attribute_sources() {
		$sources = array(
			array( 'value' => 'user_meta:', 'label' => __( 'User meta (type the key)', 'joinotify' ) ),
		);

		if ( function_exists( 'wc_get_order' ) ) {
			$sources[] = array( 'value' => 'order_meta:', 'label' => __( 'Order meta or checkout field (type the key)', 'joinotify' ) );
		}

		return $sources;
	}


	/**
	 * The stored mapping of site data to custom fields.
	 *
	 * @since 2.5.0
	 * @return array<int,array{source:string,target:string}>
	 */
	public static function attribute_map() {
		return self::sanitize_attribute_map( self::setting( self::ATTRIBUTE_MAP, array() ) );
	}


	/**
	 * Keep the valid `{ source, target }` pairs: a `user_meta:` or `order_meta:` key and a
	 * custom field key. Pure, for the harness.
	 *
	 * @since 2.5.0
	 * @param mixed $map Raw mapping.
	 * @return array<int,array{source:string,target:string}>
	 */
	public static function sanitize_attribute_map( $map ) {
		$clean = array();

		foreach ( is_array( $map ) ? $map : array() as $row ) {
			$source = isset( $row['source'] ) ? trim( (string) $row['source'] ) : '';
			$target = isset( $row['target'] ) ? trim( (string) $row['target'] ) : '';

			if ( 1 !== preg_match( '/^(user_meta|order_meta):[A-Za-z0-9_\-]{1,191}$/', $source ) || 1 !== preg_match( '/^[a-z][a-z0-9_]{0,39}$/', $target ) ) {
				continue;
			}

			$clean[] = array( 'source' => $source, 'target' => $target );
		}

		return array_slice( $clean, 0, 50 );
	}


	/**
	 * Read the mapped site data for the person of a block.
	 *
	 * @since 2.5.0
	 * @param array<int,array{source:string,target:string}> $map    Mapping.
	 * @param array<string,mixed>                           $fields What the block was built from.
	 * @return array<string,mixed> Custom field key → value.
	 */
	private static function site_attributes( $map, $fields ) {
		if ( empty( $map ) ) {
			return array();
		}

		$order = null;
		$user_id = (int) ( $fields['user_id'] ?? 0 );

		if ( ! empty( $fields['order_id'] ) && function_exists( 'wc_get_order' ) ) {
			$order = wc_get_order( (int) $fields['order_id'] );
			$user_id = $user_id ?: ( $order ? (int) $order->get_customer_id() : 0 );
		}

		$values = array();

		foreach ( $map as $row ) {
			list( $kind, $key ) = explode( ':', $row['source'], 2 );
			$value = null;

			if ( 'user_meta' === $kind && $user_id > 0 ) {
				$value = get_user_meta( $user_id, $key, true );
			} elseif ( 'order_meta' === $kind && $order ) {
				$value = $order->get_meta( $key );

				// Core checkout fields are properties, not meta: `billing_company`, `_billing_company`…
				$getter = 'get_' . ltrim( $key, '_' );

				if ( ( '' === $value || null === $value ) && is_callable( array( $order, $getter ) ) ) {
					$value = $order->{$getter}();
				}
			}

			if ( is_scalar( $value ) && '' !== trim( (string) $value ) ) {
				$values[ $row['target'] ] = is_string( $value ) ? self::clean( $value, 1000 ) : $value;
			}
		}

		return $values;
	}


	// ── Forms ───────────────────────────────────────────────────────────────────────────────


	/**
	 * Whether the submissions of a form are synced.
	 *
	 * @since 2.5.0
	 * @param string $plugin `wpforms` or `elementor`.
	 * @param string $id     Form id.
	 * @return bool
	 */
	public static function form_allowed( $plugin, $id ) {
		$rule = self::form_rule( $plugin, $id );

		if ( null !== $rule ) {
			return ! empty( $rule['enabled'] );
		}

		return 'selected' !== self::setting( self::FORMS_MODE, 'all' );
	}


	/**
	 * The stored rule of a form, if the owner configured one.
	 *
	 * @since 2.5.0
	 * @param string $plugin `wpforms` or `elementor`.
	 * @param string $id     Form id.
	 * @return array<string,mixed>|null
	 */
	public static function form_rule( $plugin, $id ) {
		$rules = self::sanitize_form_rules( self::setting( self::FORM_RULES, array() ) );
		$key = $plugin . ':' . $id;

		return $rules[ $key ] ?? null;
	}


	/**
	 * Keep the valid form rules. Pure, for the harness.
	 *
	 * @since 2.5.0
	 * @param mixed $rules Raw rules keyed `plugin:form id`.
	 * @return array<string,array<string,mixed>>
	 */
	public static function sanitize_form_rules( $rules ) {
		$clean = array();

		foreach ( is_array( $rules ) ? $rules : array() as $key => $rule ) {
			if ( ! is_string( $key ) || 1 !== preg_match( '/^(wpforms|elementor):[A-Za-z0-9_\-]{1,64}$/', $key ) || ! is_array( $rule ) ) {
				continue;
			}

			$field = static function( $name ) use ( $rule ) {
				$value = isset( $rule[ $name ] ) ? trim( (string) $rule[ $name ] ) : '';

				return 1 === preg_match( '/^[A-Za-z0-9_\-]{1,64}$/', $value ) ? $value : '';
			};

			$attributes = array();

			foreach ( isset( $rule['attributes'] ) && is_array( $rule['attributes'] ) ? $rule['attributes'] : array() as $field_id => $target ) {
				if ( 1 === preg_match( '/^[A-Za-z0-9_\-]{1,64}$/', (string) $field_id ) && 1 === preg_match( '/^[a-z][a-z0-9_]{0,39}$/', (string) $target ) ) {
					$attributes[ (string) $field_id ] = (string) $target;
				}
			}

			$clean[ $key ] = array(
				'enabled' => ! empty( $rule['enabled'] ) && 'no' !== $rule['enabled'],
				'phone' => $field( 'phone' ),
				'email' => $field( 'email' ),
				'first_name' => $field( 'first_name' ),
				'last_name' => $field( 'last_name' ),
				'consent' => $field( 'consent' ),
				'consent_text' => self::clean( $rule['consent_text'] ?? '', 300 ),
				'tags' => array_slice( self::parse_tags( $rule['tags'] ?? array() ), 0, self::MAX_TAGS ),
				'attributes' => $attributes,
			);
		}

		return $clean;
	}


	/**
	 * Read a submission through the form's rule. Pure, for the harness.
	 *
	 * A field the rule names wins; one it leaves empty falls back to the sync's own reading. The
	 * consent counts only when the rule names a consent field and the person filled it in.
	 *
	 * @since 2.5.0
	 * @param array<string,mixed>              $rule   Sanitized rule.
	 * @param array<int,array<string,string>>  $fields `{ id, label, type, value }` of the submission.
	 * @param array<string,string>             $parsed The sync's reading (`phone`, `email`, `name`).
	 * @param array<string,string>             $form   `title`, and `host` of the site.
	 * @param string                           $now    ISO 8601 moment of the submission.
	 * @return array{phone:string,email:string,first_name:string,last_name:string,attributes:array,tags:array,consent:array|null}
	 */
	public static function map_form( $rule, $fields, $parsed, $form = array(), $now = '' ) {
		$values = array();
		$labels = array();

		foreach ( (array) $fields as $field ) {
			$id = (string) ( $field['id'] ?? '' );
			$values[ $id ] = trim( (string) ( $field['value'] ?? '' ) );
			$labels[ $id ] = trim( (string) ( $field['label'] ?? '' ) );
		}

		$pick = static function( $name ) use ( $rule, $values ) {
			$id = $rule[ $name ] ?? '';

			return '' !== $id && isset( $values[ $id ] ) ? $values[ $id ] : '';
		};

		$name = preg_split( '/\s+/', trim( (string) ( $parsed['name'] ?? '' ) ), 2 );
		$first = $pick( 'first_name' );
		$last = $pick( 'last_name' );

		// A full name picked as "first name" is split, like the sync's own reading.
		if ( '' !== $first && '' === $last && '' === ( $rule['last_name'] ?? '' ) && false !== strpos( $first, ' ' ) ) {
			list( $first, $last ) = preg_split( '/\s+/', $first, 2 );
		}

		$attributes = array();

		foreach ( (array) ( $rule['attributes'] ?? array() ) as $field_id => $target ) {
			if ( isset( $values[ $field_id ] ) && '' !== $values[ $field_id ] ) {
				$attributes[ $target ] = $values[ $field_id ];
			}
		}

		$consent = null;
		$consent_id = $rule['consent'] ?? '';

		if ( '' !== $consent_id && self::ticked( $values[ $consent_id ] ?? '' ) ) {
			$what = '' !== ( $rule['consent_text'] ?? '' ) ? $rule['consent_text'] : ( $labels[ $consent_id ] ?? '' );
			$where = trim( implode( ' — ', array_filter( array(
				'' !== ( $form['title'] ?? '' ) ? sprintf( 'form "%s"', $form['title'] ) : 'form',
				$form['host'] ?? '',
			) ) ) );

			$consent = array(
				'status' => 'opted_in',
				'evidence' => self::clean( trim( $what . ' — ' . $where, ' —' ), 500 ),
			);

			if ( '' !== $now ) {
				$consent['at'] = $now;
			}

			// The platform wants at least 3 characters of evidence.
			if ( strlen( $consent['evidence'] ) < 3 ) {
				$consent = null;
			}
		}

		return array(
			'phone' => '' !== $pick( 'phone' ) ? $pick( 'phone' ) : (string) ( $parsed['phone'] ?? '' ),
			'email' => '' !== $pick( 'email' ) ? $pick( 'email' ) : (string) ( $parsed['email'] ?? '' ),
			'first_name' => '' !== $first ? $first : ( $name[0] ?? '' ),
			'last_name' => '' !== $first ? $last : ( $name[1] ?? '' ),
			'attributes' => $attributes,
			'tags' => (array) ( $rule['tags'] ?? array() ),
			'consent' => $consent,
		);
	}


	/**
	 * Whether a consent field was filled in: a checked box sends its label, "on", "1" or "yes".
	 *
	 * @since 2.5.0
	 * @param string $value Submitted value.
	 * @return bool
	 */
	public static function ticked( $value ) {
		$value = strtolower( trim( (string) $value ) );

		return '' !== $value && ! in_array( $value, array( '0', 'no', 'não', 'nao', 'false', 'off' ), true );
	}


	/**
	 * The forms the site has, with their fields, for the rules on the Sources tab.
	 *
	 * @since 2.5.0
	 * @return array<int,array{plugin:string,id:string,title:string,fields:array}>
	 */
	public static function available_forms() {
		$forms = array_merge( self::wpforms_forms(), self::elementor_forms() );

		/**
		 * Filter the forms listed on the Sources tab of Audiences & Contacts.
		 *
		 * @since 2.5.0
		 * @param array $forms `{ plugin, id, title, fields: [{ id, label, type }] }`.
		 */
		return (array) apply_filters( 'Joinotify/Contacts/Sources/Forms', $forms );
	}


	/**
	 * WPForms forms and their fields.
	 *
	 * @since 2.5.0
	 * @return array
	 */
	private static function wpforms_forms() {
		if ( ! function_exists( 'wpforms' ) || ! isset( wpforms()->form ) ) {
			return array();
		}

		$forms = array();
		$posts = wpforms()->form->get( '', array( 'number' => 100, 'orderby' => 'ID', 'order' => 'ASC' ) );

		foreach ( is_array( $posts ) ? $posts : array() as $post ) {
			$data = json_decode( (string) $post->post_content, true );
			$fields = array();

			foreach ( (array) ( $data['fields'] ?? array() ) as $field ) {
				$fields[] = array(
					'id' => (string) ( $field['id'] ?? '' ),
					'label' => (string) ( $field['label'] ?? '' ),
					'type' => (string) ( $field['type'] ?? '' ),
				);
			}

			$forms[] = array(
				'plugin' => 'wpforms',
				'id' => (string) $post->ID,
				'title' => (string) $post->post_title,
				'fields' => $fields,
			);
		}

		return $forms;
	}


	/**
	 * Elementor Pro forms, found in the Elementor data of the site's posts.
	 *
	 * @since 2.5.0
	 * @return array
	 */
	private static function elementor_forms() {
		if ( ! defined( 'ELEMENTOR_PRO_VERSION' ) ) {
			return array();
		}

		global $wpdb;

		// phpcs:ignore WordPress.DB.DirectDatabaseQuery -- A one-off read for an admin screen; nothing to cache between loads.
		$rows = $wpdb->get_results( $wpdb->prepare(
			"SELECT pm.post_id, pm.meta_value FROM {$wpdb->postmeta} pm INNER JOIN {$wpdb->posts} p ON p.ID = pm.post_id
			WHERE pm.meta_key = %s AND pm.meta_value LIKE %s AND p.post_status IN ('publish','private','draft') LIMIT 100",
			'_elementor_data',
			'%' . $wpdb->esc_like( '"widgetType":"form"' ) . '%'
		) );

		$forms = array();

		foreach ( (array) $rows as $row ) {
			$tree = json_decode( (string) $row->meta_value, true );

			foreach ( self::elementor_widgets( is_array( $tree ) ? $tree : array() ) as $widget ) {
				$fields = array();

				foreach ( (array) ( $widget['settings']['form_fields'] ?? array() ) as $field ) {
					$fields[] = array(
						'id' => (string) ( $field['custom_id'] ?? $field['_id'] ?? '' ),
						'label' => (string) ( $field['field_label'] ?? $field['placeholder'] ?? '' ),
						'type' => (string) ( $field['field_type'] ?? 'text' ),
					);
				}

				$forms[] = array(
					'plugin' => 'elementor',
					'id' => (string) $widget['id'],
					'title' => sprintf( '%s — %s', (string) ( $widget['settings']['form_name'] ?? __( 'Form', 'joinotify' ) ), get_the_title( (int) $row->post_id ) ),
					'fields' => $fields,
				);
			}
		}

		return $forms;
	}


	/**
	 * The form widgets of an Elementor element tree.
	 *
	 * @since 2.5.0
	 * @param array $elements Elementor elements.
	 * @return array
	 */
	private static function elementor_widgets( $elements ) {
		$found = array();

		foreach ( $elements as $element ) {
			if ( ! is_array( $element ) ) {
				continue;
			}

			if ( 'form' === ( $element['widgetType'] ?? '' ) && ! empty( $element['id'] ) ) {
				$found[] = $element;
			}

			if ( ! empty( $element['elements'] ) && is_array( $element['elements'] ) ) {
				$found = array_merge( $found, self::elementor_widgets( $element['elements'] ) );
			}
		}

		return $found;
	}


	// ── Settings of the Sources tab ─────────────────────────────────────────────────────────


	/**
	 * The settings the Sources tab edits.
	 *
	 * @since 2.5.0
	 * @return array<string,mixed>
	 */
	public static function config() {
		$config = array(
			self::FORMS_MODE => 'selected' === self::setting( self::FORMS_MODE, 'all' ) ? 'selected' : 'all',
			self::FORM_RULES => (object) self::sanitize_form_rules( self::setting( self::FORM_RULES, array() ) ),
			self::ATTRIBUTE_MAP => self::attribute_map(),
		);

		foreach ( self::SOURCES as $source ) {
			$config[ self::tags_setting( $source ) ] = implode( ', ', self::parse_tags( self::setting( self::tags_setting( $source ), '' ) ) );
		}

		return $config;
	}


	/**
	 * Sanitize the settings the Sources tab sends. Keys it does not own are left out.
	 *
	 * @since 2.5.0
	 * @param array<string,mixed> $input Raw values.
	 * @return array<string,mixed>
	 */
	public static function sanitize_config( $input ) {
		$input = is_array( $input ) ? $input : array();
		$clean = array();

		if ( isset( $input[ self::FORMS_MODE ] ) ) {
			$clean[ self::FORMS_MODE ] = 'selected' === $input[ self::FORMS_MODE ] ? 'selected' : 'all';
		}

		if ( isset( $input[ self::FORM_RULES ] ) ) {
			// Stored with 'yes'/'no' so the settings sanitizer keeps them as they are.
			$clean[ self::FORM_RULES ] = array_map( static function( $rule ) {
				$rule['enabled'] = $rule['enabled'] ? 'yes' : 'no';

				return $rule;
			}, self::sanitize_form_rules( $input[ self::FORM_RULES ] ) );
		}

		if ( isset( $input[ self::ATTRIBUTE_MAP ] ) ) {
			$clean[ self::ATTRIBUTE_MAP ] = self::sanitize_attribute_map( $input[ self::ATTRIBUTE_MAP ] );
		}

		foreach ( self::SOURCES as $source ) {
			$key = self::tags_setting( $source );

			if ( isset( $input[ $key ] ) ) {
				$clean[ $key ] = implode( ', ', array_slice( self::parse_tags( $input[ $key ] ), 0, self::MAX_TAGS ) );
			}
		}

		return $clean;
	}


	/**
	 * Plain text, trimmed and capped.
	 *
	 * @since 2.5.0
	 * @param mixed $value Raw value.
	 * @param int   $limit Maximum length in characters.
	 * @return string
	 */
	private static function clean( $value, $limit ) {
		if ( ! is_scalar( $value ) || is_bool( $value ) ) {
			return '';
		}

		$text = trim( strip_tags( (string) preg_replace( '/[\x00-\x1F\x7F]+/u', ' ', (string) $value ) ) );

		return mb_substr( $text, 0, $limit );
	}
}
