<?php

namespace MeuMouse\Joinotify\Rest;

use MeuMouse\Joinotify\Admin\Settings\Repository;
use MeuMouse\Joinotify\Contacts\Sources;
use WP_REST_Request;

defined('ABSPATH') || exit;

/**
 * Save the Sources tab: the Cloud sync switches and texts, and the tags, custom field mapping and
 * form rules of the integrations.
 *
 * Body: `{ sync: { enable_cloud_sync: bool, … }, config: { cloud_sync_form_rules: {…}, … } }`.
 * Both are written into the plugin settings through the settings repository, so the Cloud sync
 * reacts exactly as when its window in Settings is saved.
 *
 * @since 2.5.0
 */
class Contacts_Sources_Save extends Abstract_Route {

	/**
	 * Route path.
	 *
	 * @var string
	 */
	protected $route = '/admin/contacts/sources/save';

	/**
	 * HTTP methods.
	 *
	 * @var string
	 */
	protected $methods = 'POST';


	/**
	 * Handle request.
	 *
	 * @since 2.5.0
	 * @param WP_REST_Request $request Request instance.
	 * @return \WP_REST_Response
	 */
	public function handle( WP_REST_Request $request ) {
		$body = $request->get_json_params();
		$body = is_array( $body ) ? $body : array();
		$changes = Sources::sanitize_config( isset( $body['config'] ) && is_array( $body['config'] ) ? $body['config'] : array() );
		$sync = isset( $body['sync'] ) && is_array( $body['sync'] ) ? $body['sync'] : array();

		foreach ( Contacts_Sources::SYNC_SETTINGS as $key => $kind ) {
			if ( ! array_key_exists( $key, $sync ) ) {
				continue;
			}

			$changes[ $key ] = 'switch' === $kind
				? ( ! empty( $sync[ $key ] ) && 'no' !== $sync[ $key ] ? 'yes' : 'no' )
				: sanitize_text_field( (string) $sync[ $key ] );
		}

		// The whole stored settings go back with the changes on top: the repository turns every
		// switch missing from its input into "no", which would switch other features off.
		Repository::save_settings( array_merge( Repository::get_settings(), $changes ) );

		return $this->success_response( array_merge(
			array( 'message' => __( 'Sources saved.', 'joinotify' ) ),
			Contacts_Sources::state()
		) );
	}
}
