<?php
/**
 * Standalone test harness for the history resend.
 *
 * A resend is rebuilt from the history row alone, so what matters is which rows
 * hold enough to send the same message again and what each one turns into:
 * text, a media or audio link, a template with the components that went out,
 * or the Meta object of an interactive message. These assertions cover those
 * rebuilds, every row that must not be offered a resend (wrong status, login
 * codes, masked or redacted templates, local attachments, rows already resent),
 * and the dispatch itself — one attempt per row, outside the retry queue, in
 * the original order, tagged with the row it resends, and refused as a whole
 * past the limit.
 *
 * Run (Windows / Local):
 *   & "C:\path\to\Local\php.exe" tests/history-resend-test.php
 *
 * @since 2.7.0
 */

namespace {
	// The class file guards with `defined('ABSPATH') || exit;`.
	define( 'ABSPATH', __DIR__ . '/' );

	$GLOBALS['filters'] = array();

	function apply_filters( $hook, $value ) {
		return isset( $GLOBALS['filters'][ $hook ] ) ? $GLOBALS['filters'][ $hook ]( $value ) : $value;
	}
	function absint( $value ) { return abs( (int) $value ); }
	function sanitize_key( $value ) { return strtolower( preg_replace( '/[^a-z0-9_\-]/i', '', (string) $value ) ); }
	function wp_parse_url( $url, $component = -1 ) { return parse_url( $url, $component ); }
}

namespace MeuMouse\Joinotify\Core {
	class Message_History {
		public static $rows = array();
		public static $contexts = array();
		public static $context = array();

		public static function decode_meta( $meta ) {
			$decoded = is_string( $meta ) && '' !== $meta ? json_decode( $meta, true ) : null;
			return is_array( $decoded ) ? $decoded : array();
		}

		// Newest first, like the real query.
		public static function get_items_by_ids( $ids ) {
			$ids = array_map( 'intval', (array) $ids );
			$rows = array_values( array_filter( self::$rows, function ( $row ) use ( $ids ) {
				return in_array( (int) $row['id'], $ids, true );
			} ) );

			usort( $rows, function ( $a, $b ) { return $b['id'] <=> $a['id']; } );

			return $rows;
		}

		public static function set_context( $context ) {
			self::$context = $context;
			self::$contexts[] = $context;
		}

		public static function clear_context() {
			self::$context = array();
		}
	}
}

namespace MeuMouse\Joinotify\Api {
	class Transport {
		public static $calls = array();
		public static $succeed = true;

		private static function answer( $method, $args ) {
			self::$calls[] = array(
				'method' => $method,
				'args' => $args,
				'context' => \MeuMouse\Joinotify\Core\Message_History::$context,
			);

			return array( 'success' => self::$succeed );
		}

		public static function send_message_text( ...$args ) { return self::answer( 'text', $args ); }
		public static function send_message_media( ...$args ) { return self::answer( 'media', $args ); }
		public static function send_whatsapp_audio( ...$args ) { return self::answer( 'audio', $args ); }
		public static function send_message_template( ...$args ) { return self::answer( 'template', $args ); }
		public static function send_raw_message( ...$args ) { return self::answer( 'raw', $args ); }
	}
}

namespace {
	require __DIR__ . '/../admin/src/Admin/History/Resend.php';

	use MeuMouse\Joinotify\Admin\History\Resend;
	use MeuMouse\Joinotify\Api\Transport;
	use MeuMouse\Joinotify\Core\Message_History;

	$failures = 0;
	$assertions = 0;

	/**
	 * Assert a condition, tracking pass/fail counts.
	 */
	function check( $label, $condition ) {
		global $failures, $assertions;
		$assertions++;

		if ( $condition ) {
			echo "  PASS  {$label}\n";
		} else {
			$failures++;
			echo "  FAIL  {$label}\n";
		}
	}

	/**
	 * Build a stored history row.
	 */
	function row( $fields = array(), $meta = null ) {
		return array_merge( array(
			'id' => 1,
			'status' => 'failed',
			'source' => 'workflow',
			'workflow_id' => 7,
			'sender' => '5541999990000',
			'receiver' => '5541988887777',
			'message_type' => 'text',
			'media_type' => '',
			'content' => 'Your order shipped',
			'media_url' => '',
			'meta' => null === $meta ? null : json_encode( $meta ),
		), $fields );
	}

	$components = array(
		array(
			'type' => 'body',
			'parameters' => array( array( 'type' => 'text', 'text' => 'Ana' ) ),
		),
	);

	echo "== rebuilds ==\n";

	$request = Resend::build_request( row() );
	check( 'a failed text is resent as text', 'text' === ( $request['type'] ?? '' ) && 'Your order shipped' === $request['message'] );
	check( '…from the same sender to the same recipient', '5541999990000' === $request['sender'] && '5541988887777' === $request['receiver'] );

	check( 'a cancelled retry can be resent too', null !== Resend::build_request( row( array( 'status' => 'cancelled' ) ) ) );

	$request = Resend::build_request( row( array(
		'message_type' => 'media',
		'media_type' => 'image',
		'media_url' => 'https://shop.example.com/banner.png',
		'content' => 'Look',
	) ) );
	check( 'a media link is resent with its caption', 'media' === ( $request['type'] ?? '' ) && 'image' === $request['media_type'] && 'https://shop.example.com/banner.png' === $request['media'] && 'Look' === $request['caption'] );

	$request = Resend::build_request( row( array(
		'message_type' => 'audio',
		'media_type' => 'audio',
		'media_url' => 'https://shop.example.com/voice.ogg',
	) ) );
	check( 'an audio link is resent as audio', 'audio' === ( $request['type'] ?? '' ) && 'https://shop.example.com/voice.ogg' === $request['audio'] );

	$request = Resend::build_request( row( array( 'message_type' => 'template' ), array(
		'template' => array(
			'name' => 'order_shipped',
			'language' => 'pt_BR',
			'masked' => false,
			'parameters' => array( array( 'component' => 'body', 'index' => 0, 'key' => '1', 'value' => 'Ana' ) ),
			'components' => $components,
		),
	) ) );
	check( 'a template is resent with the components that went out', 'template' === ( $request['type'] ?? '' ) && 'order_shipped' === $request['template_name'] && 'pt_BR' === $request['language'] && $components === $request['components'] );

	$request = Resend::build_request( row( array( 'message_type' => 'template' ), array(
		'template' => array( 'name' => 'welcome', 'language' => 'en_US', 'masked' => false, 'parameters' => array(), 'components' => array() ),
	) ) );
	check( 'a template without variables is resent without components', 'template' === ( $request['type'] ?? '' ) && array() === $request['components'] );

	$interactive = array(
		'type' => 'button',
		'body' => array( 'text' => 'Confirm?' ),
		'action' => array( 'buttons' => array( array( 'type' => 'reply', 'reply' => array( 'id' => 'yes', 'title' => 'Yes' ) ) ) ),
	);
	$request = Resend::build_request( row( array( 'content' => 'Confirm?' ), array(
		'raw' => array( 'type' => 'interactive', 'content' => json_encode( $interactive ) ),
	) ) );
	check( 'an interactive message is resent as its Meta object, not as its preview', 'raw' === ( $request['type'] ?? '' ) && 'interactive' === $request['raw_type'] && $interactive === $request['raw_content'] && 'Confirm?' === $request['preview'] );

	echo "\n== not offered ==\n";

	foreach ( array( 'sent', 'delivered', 'read', 'queued' ) as $status ) {
		check( "a {$status} message", false === Resend::can_resend( row( array( 'status' => $status ) ) ) );
	}

	check( 'a login code', false === Resend::can_resend( row( array( 'source' => 'otp' ) ) ) );
	check( 'a row already resent', false === Resend::can_resend( row( array(), array( 'resent_as' => 9 ) ) ) );
	check( 'a row without a recipient', false === Resend::can_resend( row( array( 'receiver' => '' ) ) ) );
	check( 'an empty text', false === Resend::can_resend( row( array( 'content' => '   ' ) ) ) );
	check( 'an unknown message type', false === Resend::can_resend( row( array( 'message_type' => 'sticker' ) ) ) );

	check( 'a masked template', false === Resend::can_resend( row( array( 'message_type' => 'template' ), array(
		'template' => array( 'name' => 'login_code', 'masked' => true, 'parameters' => array(), 'components' => array() ),
	) ) ) );
	check( 'a template whose values were redacted', false === Resend::can_resend( row( array( 'message_type' => 'template' ), array(
		'template' => array( 'name' => 'order_shipped', 'masked' => false, 'parameters' => array( array( 'key' => '1', 'value' => '' ) ) ),
	) ) ) );
	check( 'a template recorded without details', false === Resend::can_resend( row( array( 'message_type' => 'template' ) ) ) );

	check( 'a local attachment (no link kept)', false === Resend::can_resend( row( array( 'message_type' => 'media', 'media_type' => 'document', 'media_url' => '' ) ) ) );
	check( 'a pre-2.7.0 local attachment, stored as http://<base64>', false === Resend::can_resend( row( array(
		'message_type' => 'media',
		'media_type' => 'image',
		'media_url' => 'http://iVBORw0KGgoAAAANSUhEUgAAAAEAAAABCAYAAAAfFcSJAAAADUlEQVR42mNk',
	) ) ) );
	check( 'an audio row with media type audio under media', false === Resend::can_resend( row( array( 'message_type' => 'media', 'media_type' => 'audio', 'media_url' => 'https://shop.example.com/a.ogg' ) ) ) );
	check( 'a truncated interactive object', false === Resend::can_resend( row( array(), array(
		'raw' => array( 'type' => 'interactive', 'content' => substr( json_encode( $interactive ), 0, 20 ) ),
	) ) ) );

	echo "\n== dispatch ==\n";

	Message_History::$rows = array(
		row( array( 'id' => 10, 'content' => 'first' ) ),
		row( array( 'id' => 11, 'status' => 'sent', 'content' => 'already out' ) ),
		row( array( 'id' => 12, 'content' => 'second', 'source' => 'api', 'workflow_id' => 0 ) ),
	);

	$result = Resend::resend( array( 12, 11, 10 ) );

	check( 'two messages went out, one was skipped', 2 === $result['resent'] && 0 === $result['failed'] && 1 === $result['skipped'] && false === $result['over_limit'] );
	check( 'oldest first', 'first' === ( Transport::$calls[0]['args'][2] ?? '' ) && 'second' === ( Transport::$calls[1]['args'][2] ?? '' ) );
	check( 'a single attempt, outside the retry queue', false === Transport::$calls[0]['args'][4] && true === Transport::$calls[0]['args'][5] );
	check( 'the record links back to the row it resends', 10 === ( Transport::$calls[0]['context']['resent_from'] ?? 0 ) && 12 === ( Transport::$calls[1]['context']['resent_from'] ?? 0 ) );
	check( '…and keeps its source and workflow', 'workflow' === Transport::$calls[0]['context']['source'] && 7 === Transport::$calls[0]['context']['workflow_id'] && 'api' === Transport::$calls[1]['context']['source'] );
	check( 'the context is cleared afterwards', array() === Message_History::$context );

	Transport::$calls = array();
	Transport::$succeed = false;

	$result = Resend::resend( array( 10 ) );
	check( 'a refused resend is counted as failed', 0 === $result['resent'] && 1 === $result['failed'] );

	Transport::$calls = array();
	Transport::$succeed = true;
	$GLOBALS['filters']['Joinotify/Admin/History/Resend_Limit'] = function () { return 1; };

	$result = Resend::resend( array( 10, 12 ) );
	check( 'past the limit nothing is sent', true === $result['over_limit'] && 0 === $result['resent'] && array() === Transport::$calls );

	unset( $GLOBALS['filters']['Joinotify/Admin/History/Resend_Limit'] );
	check( 'the default limit is 50', 50 === Resend::get_limit() );

	echo "\n{$assertions} assertions, {$failures} failures\n";

	exit( $failures > 0 ? 1 : 0 );
}
