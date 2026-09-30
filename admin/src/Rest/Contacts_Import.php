<?php

namespace MeuMouse\Joinotify\Rest;

use MeuMouse\Joinotify\Admin\Contacts\Registry;
use MeuMouse\Joinotify\Api\Cloud_Contacts;
use WP_REST_Request;

defined('ABSPATH') || exit;

/**
 * Import contacts from a file the screen already read, in steps:
 *
 * - `{ step: 'open', filename, total }` opens the import record;
 * - `{ step: 'batch', import_id, rows: [...], options: {...} }` sends up to 500 rows;
 * - `{ step: 'close', import_id, status }` closes it.
 *
 * The file never reaches the server: the screen parses it and sends the mapped rows, so an upload
 * size limit or a stray column never gets in the way.
 *
 * @since 2.5.0
 */
class Contacts_Import extends Abstract_Cloud_Route {

	/**
	 * Route path.
	 *
	 * @var string
	 */
	protected $route = '/admin/contacts/import';

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
		$body = $this->body( $request );
		$step = isset( $body['step'] ) ? (string) $body['step'] : '';
		$import_id = isset( $body['import_id'] ) && Cloud_Contacts::is_id( $body['import_id'] ) ? $body['import_id'] : '';

		if ( 'open' === $step ) {
			return $this->respond( Cloud_Contacts::open_import( (string) ( $body['filename'] ?? '' ), (int) ( $body['total'] ?? 0 ) ) );
		}

		if ( 'close' === $step ) {
			if ( '' === $import_id ) {
				return $this->invalid( __( 'Import not found.', 'joinotify' ), 'not_found' );
			}

			return $this->respond( Cloud_Contacts::close_import( $import_id, (string) ( $body['status'] ?? 'completed' ) ) );
		}

		if ( 'batch' !== $step ) {
			return $this->invalid( __( 'Unknown import step.', 'joinotify' ) );
		}

		$rows = array();
		$sent = array();
		$dropped = array();

		foreach ( array_slice( is_array( $body['rows'] ?? null ) ? $body['rows'] : array(), 0, Cloud_Contacts::BATCH_SIZE ) as $index => $row ) {
			$clean = Cloud_Contacts::batch_row( $row );

			if ( null === $clean ) {
				$dropped[] = (int) $index;
				continue;
			}

			$rows[] = $clean;
			$sent[] = (int) $index;
		}

		if ( empty( $rows ) ) {
			return $this->success_response( array(
				'data' => array(
					'results' => array(),
					'summary' => array( 'created' => 0, 'updated' => 0, 'skipped' => count( $dropped ), 'failed' => 0 ),
				),
				'dropped' => $dropped,
			) );
		}

		$options = is_array( $body['options'] ?? null ) ? $body['options'] : array();
		$envelope = Cloud_Contacts::batch_contacts( $rows, array(
			'on_duplicate' => (string) ( $options['on_duplicate'] ?? 'update' ),
			'default_country' => Registry::default_country(),
			'import_id' => $import_id,
			'opt_in_evidence' => (string) ( $options['opt_in_evidence'] ?? '' ),
		) );

		if ( ! $envelope['ok'] ) {
			return $this->envelope_error( $envelope );
		}

		// Rows without a phone never left the site; they count as skipped here.
		$data = is_array( $envelope['data'] ) ? $envelope['data'] : array();
		$data['summary']['skipped'] = (int) ( $data['summary']['skipped'] ?? 0 ) + count( $dropped );

		return $this->success_response( array(
			'data' => $data,
			// The results index the rows sent; `sent[i]` is the row of the batch that result i is about.
			'sent' => $sent,
			'dropped' => $dropped,
		) );
	}
}
