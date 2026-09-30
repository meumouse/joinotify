<?php

namespace MeuMouse\Joinotify\Rest;

use MeuMouse\Joinotify\Api\Cloud_Contacts;
use MeuMouse\Joinotify\Cloud_Sync\Consent;
use WP_REST_Request;

defined('ABSPATH') || exit;

/**
 * Read one contact, with the site's users that are the same person.
 *
 * @since 2.5.0
 */
class Contacts_Detail extends Abstract_Cloud_Route {

	/**
	 * Route path.
	 *
	 * @var string
	 */
	protected $route = '/admin/contacts/detail';

	/**
	 * HTTP methods.
	 *
	 * @var string
	 */
	protected $methods = 'GET';


	/**
	 * Handle request.
	 *
	 * @since 2.5.0
	 * @param WP_REST_Request $request Request instance.
	 * @return \WP_REST_Response
	 */
	public function handle( WP_REST_Request $request ) {
		$id = $this->id_param( $request );

		if ( '' === $id ) {
			return $this->invalid( __( 'Contact not found.', 'joinotify' ), 'not_found' );
		}

		$envelope = Cloud_Contacts::get_contact( $id );

		if ( ! $envelope['ok'] ) {
			return $this->envelope_error( $envelope );
		}

		$contact = is_array( $envelope['data'] ) ? $envelope['data'] : array();

		return $this->success_response( array(
			'contact' => $contact,
			'wp_users' => self::site_users( $contact ),
		) );
	}


	/**
	 * The site's users that are this contact, matched by e-mail and phone.
	 *
	 * @since 2.5.0
	 * @param array $contact Platform contact.
	 * @return array<int,array{id:int,name:string,email:string,edit_url:string}>
	 */
	private static function site_users( $contact ) {
		$users = array();
		$ids = Consent::users_of( (string) ( $contact['email'] ?? '' ), (string) ( $contact['phone'] ?? '' ) );

		foreach ( array_slice( $ids, 0, 5 ) as $user_id ) {
			$user = get_userdata( $user_id );

			if ( ! $user ) {
				continue;
			}

			$users[] = array(
				'id' => (int) $user->ID,
				'name' => $user->display_name,
				'email' => $user->user_email,
				'edit_url' => get_edit_user_link( $user->ID ),
			);
		}

		return $users;
	}
}
