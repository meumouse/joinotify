<?php
/**
 * Functions source file.
 *
 * @since 1.4.7
 * @version 1.4.7
 */

use MeuMouse\Joinotify\Api\Controller;
use MeuMouse\Joinotify\Api\Extensions;
use MeuMouse\Joinotify\Admin\Admin;
use MeuMouse\Joinotify\Builder\Placeholders;
use MeuMouse\Joinotify\Builder\Utils;
use MeuMouse\Joinotify\Core\Helpers;
use MeuMouse\Joinotify\Core\Phone_Manager;
use MeuMouse\Joinotify\Core\Message_History;
use MeuMouse\Joinotify\Core\Logger;
use MeuMouse\Joinotify\Notifications\Channel_Manager;
use MeuMouse\Joinotify\Notifications\Notification_Message;

// Exit if accessed directly.
defined('ABSPATH') || exit;

/**
 * Check admin page from partial URL
 * 
 * @since 1.1.0
 * @version 1.4.7
 * @param $admin_page | Page string for check from admin.php?page=
 * @return bool
 */
function joinotify_check_admin_page( $admin_page ) {
   $current_url = ( isset( $_SERVER['HTTPS'] ) && $_SERVER['HTTPS'] === 'on' ? 'https' : 'http' ) . '://' . $_SERVER['HTTP_HOST'] . $_SERVER['REQUEST_URI'];
   $admin_page = is_scalar( $admin_page ) ? (string) $admin_page : '';

   return strpos( $current_url, "admin.php?page=$admin_page" ) !== false;
}


/**
 * Send message text on WhatsApp
 * 
 * @since 1.1.0
 * @param string $sender | Instance phone number
 * @param string $receiver | Phone number for receive message
 * @param string $message | Message text for send
 * @param int $delay | Delay in seconds before send message
 * @return int
 */
function joinotify_send_whatsapp_message_text( $sender, $receiver, $message, $delay = 0 ) {
   $response = Controller::send_message_text( $sender, $receiver, $message, $delay );

   return $response;
}


/**
 * Send message media on WhatsApp
 * 
 * @since 1.1.0
 * @version 1.4.7
 * @param string $sender | Instance phone number
 * @param string $receiver | Phone number for receive message
 * @param string $media_type | Media type (image, audio, video or document)
 * @param string $media | Media URL
 * @param int $delay | Delay in miliseconds before send message
 * @return int
 */
function joinotify_send_whatsapp_message_media( $sender, $receiver, $media_type, $media, $caption = '', $delay = 0 ) {
   $response = Controller::send_message_media( $sender, $receiver, $media_type, $media, $caption, $delay );

   return $response;
}


/**
 * Get endpoint for Proxy API send text message
 * 
 * @since 1.1.0
 * @return string
 */
function joinotify_proxy_api_text_message_text_endpoint() {
   return get_home_url() . '/wp-json/joinotify/v1/' . Admin::get_setting('send_text_proxy_api_route');
}


/**
 * Get endpoint for Proxy API send media message
 * 
 * @since 1.1.0
 * @return string
 */
function joinotify_proxy_api_media_message_text_endpoint() {
   return get_home_url() . '/wp-json/joinotify/v1/' . Admin::get_setting('send_media_proxy_api_route');
}


/**
 * Get Proxy API key
 * 
 * @since 1.1.0
 * @return string
 */
function joinotify_get_proxy_api_key() {
   return Admin::get_setting('proxy_api_key');
}


/**
 * Prepare the receiver phone number with the correct format
 * 
 * @since 1.0.0
 * @version 1.4.7
 * @param string $receiver |  Receiver phone
 * @param array $payload | Payload for replace placeholders
 * @return string
 */
function joinotify_prepare_receiver( $receiver, $payload = array() ) {
	$receiver = is_scalar( $receiver ) ? (string) $receiver : '';
	// First, we replace all placeholders
	$receiver = Placeholders::replace_placeholders( $receiver, $payload );

	// Keep only digits in the number
	$format_phone  = Helpers::validate_and_format_phone( $receiver );
	$phone = preg_replace( '/\D/', '', $format_phone ); // Remove all non-digit characters

	// Check receiver phone number
	if ( JOINOTIFY_DEV_MODE ) {
		error_log( 'joinotify_prepare_receiver() receiver finished: ' . print_r( $phone, true ) );
	}

	return $phone;
}


/**
 * Replace placeholders in message
 * 
 * @since 1.2.0
 * @param string $message | Message text
 * @param array $payload | Payload for replace placeholders
 * @return string
 */
function joinotify_prepare_message( $message, $payload = array() ) {
	$message = is_scalar( $message ) ? (string) $message : '';
	// First, we replace all placeholders
	$message = Placeholders::replace_placeholders( $message, $payload );

	// Replace AI smart variables produced by the dynamic_placeholder action: {{ ai:NAME }}
	if ( false !== strpos( $message, '{{ ai:' ) || false !== strpos( $message, '{{ai:' ) ) {
		$ai_vars = isset( $payload['ai_vars'] ) && is_array( $payload['ai_vars'] ) ? $payload['ai_vars'] : array();

		$message = preg_replace_callback( '/\{\{\s*ai:([a-zA-Z0-9_-]+)\s*\}\}/', function( $matches ) use ( $ai_vars ) {
			$key = sanitize_key( $matches[1] );

			return isset( $ai_vars[ $key ] ) && is_scalar( $ai_vars[ $key ] ) ? (string) $ai_vars[ $key ] : '';
		}, $message );
	}

	return $message;
}


/**
 * Sanitize HTML content to plain text
 *
 * Removes HTML tags and decodes HTML entities, returning a clean text output.
 *
 * @since 1.2.2
 * @version 1.4.7
 * @param string $content | Input text with potential HTML formatting
 * @return string Cleaned plain text
 */
function joinotify_format_plain_text( $content ) {
	$content = (string) ( $content ?? '' );

	return html_entity_decode( strip_tags( $content ), ENT_QUOTES | ENT_HTML5, 'UTF-8' );
}


/**
 * Convert the HTML formatting produced by the builder rich text field into
 * WhatsApp markdown syntax.
 *
 * The rich text editor stores inline formatting as HTML tags (e.g.
 * <strong>word</strong>), but WhatsApp expects its own markers. This maps:
 * bold -> *word*, italic -> _word_, strikethrough -> ~word~. WhatsApp has no
 * underline, so <u> is dropped while keeping its text. Nested tags collapse
 * from the inside out (e.g. <strong><em>x</em></strong> -> *_x_*).
 *
 * Safe on plain messages: when no HTML tag is present the input is returned
 * untouched, which also makes the conversion idempotent.
 *
 * @since 2.0.0
 * @param string $message | Message text, possibly containing HTML formatting
 * @return string Message using WhatsApp markdown
 */
function joinotify_convert_html_to_whatsapp( $message ) {
	$message = is_scalar( $message ) ? (string) $message : '';

	// Nothing to convert when there is no markup.
	if ( false === strpos( $message, '<' ) ) {
		return $message;
	}

	// Normalize <br> variants to line breaks (pasted content may include them).
	$message = preg_replace( '/<br\s*\/?>/i', "\n", $message );

	// Map inline formatting tags to WhatsApp markers. Patterns run in sequence,
	// so nested tags collapse from the inside out.
	$message = preg_replace( array(
		'/<(?:strong|b)(?:\s[^>]*)?>(.*?)<\/(?:strong|b)>/is',         // bold
		'/<(?:em|i)(?:\s[^>]*)?>(.*?)<\/(?:em|i)>/is',                 // italic
		'/<(?:del|s|strike)(?:\s[^>]*)?>(.*?)<\/(?:del|s|strike)>/is', // strikethrough
		'/<u(?:\s[^>]*)?>(.*?)<\/u>/is',                               // underline (unsupported by WhatsApp)
	), array(
		'*$1*',
		'_$1_',
		'~$1~',
		'$1',
	), $message );

	// Drop any leftover style wrappers the editor may emit, keeping their text.
	$message = preg_replace( '/<\/?(?:span|font)(?:\s[^>]*)?>/i', '', $message );

	return $message;
}


/**
 * Get first sender phone number
 *
 * @since 1.3.0
 * @version 1.4.7
 * @return string|null
 */
function joinotify_get_first_sender() {
	$current_senders = get_option( 'joinotify_get_phones_senders', array() );

	// remove empty entries and reindex
	$current_senders = array_values( array_filter( $current_senders ) );

	if ( empty( $current_senders ) ) {
		return null;
	}

	return $current_senders[0];
}


/**
 * ---------------------------------------------------------------------------
 * Developer runtime helpers.
 *
 * Public, discoverable wrappers over Joinotify's internal classes (Placeholders, Utils,
 * Phone_Manager, Helpers, Admin, Message_History) so third parties can read and resolve data
 * at runtime without coupling to namespaced classes. Full reference: DEVELOPERS.md.
 * ---------------------------------------------------------------------------
 */

/**
 * Replace Joinotify placeholders ({{ ... }}) in a string.
 *
 * Direct public wrapper over Placeholders::replace_placeholders(). Resolves static placeholders,
 * field tokens ({{ field_id=[ID] }}), WooCommerce checkout fields ({{ wc_checkout_field=[ID] }})
 * and user meta tokens ({{ user_meta[KEY] }}). For messages that may also contain AI smart
 * variables ({{ ai:NAME }}), use joinotify_prepare_message() instead.
 *
 * @since 2.0.0
 * @param string $message | Text containing placeholders.
 * @param array  $payload | Context payload (integration, trigger, fields, order_id, user_id, ...).
 * @param string $mode    | 'production' (send time) or 'sandbox' (builder preview). Default 'production'.
 * @return string Text with placeholders replaced.
 */
function joinotify_replace_placeholders( $message, $payload = array(), $mode = 'production' ) {
	return Placeholders::replace_placeholders( $message, $payload, $mode );
}


/**
 * Get the list of available placeholders for an integration/trigger.
 *
 * @since 2.0.0
 * @param string $integration | Integration/context slug. Empty returns only global placeholders.
 * @param string $trigger     | Trigger slug to filter by. Empty returns all of the integration.
 * @param array  $context     | Optional context passed to the Placeholders_List filter.
 * @return array Map of '{{ token }}' => {triggers, description, replacement}.
 */
function joinotify_get_placeholders( $integration = '', $trigger = '', $context = array() ) {
	return Placeholders::get_placeholders_list( $integration, $trigger, $context );
}


/**
 * Query Joinotify workflows.
 *
 * Thin wrapper over get_posts() locked to the 'joinotify-workflow' post type. Pass any WP_Query
 * arg (post_status, posts_per_page, meta_query, fields, ...); the post_type is always enforced.
 *
 * @since 2.0.0
 * @param array $args | Extra WP_Query args merged over the defaults.
 * @return array List of WP_Post objects (or IDs when 'fields' => 'ids').
 */
function joinotify_get_workflows( $args = array() ) {
	$args = is_array( $args ) ? $args : array();

	$query_args = array_merge( array(
		'post_status'    => 'publish',
		'posts_per_page' => -1,
	), $args );

	// Always enforce the workflow post type.
	$query_args['post_type'] = 'joinotify-workflow';

	return get_posts( $query_args );
}


/**
 * Get the decoded workflow content (tree of triggers/actions) of a workflow.
 *
 * @since 2.0.0
 * @param int $post_id | Workflow post ID.
 * @return array Workflow content tree, or empty array when none.
 */
function joinotify_get_workflow_content( $post_id ) {
	$content = Helpers::get_workflow_content_meta( $post_id );

	return is_array( $content ) ? $content : array();
}


/**
 * Get the trigger context (integration slug) of a workflow.
 *
 * @since 2.0.0
 * @param int $post_id | Workflow post ID.
 * @return string|null Context slug (eg: 'woocommerce') or null when not found.
 */
function joinotify_get_workflow_context( $post_id ) {
	return Utils::get_context_from_post( $post_id );
}


/**
 * Find a single trigger/action item inside a workflow content tree by its ID.
 *
 * Searches recursively, including condition branches (action_true / action_false).
 *
 * @since 2.0.0
 * @param array  $workflow_content | Workflow content tree (see joinotify_get_workflow_content()).
 * @param string $item_id          | Trigger or action ID to locate.
 * @return array|null The matching item, or null when not found.
 */
function joinotify_find_workflow_item( $workflow_content, $item_id ) {
	$workflow_content = is_array( $workflow_content ) ? $workflow_content : array();

	return Utils::find_workflow_item_by_id( $workflow_content, $item_id );
}


/**
 * Check whether a workflow contains at least one item of the given type.
 *
 * @since 2.0.0
 * @param int    $post_id | Workflow post ID.
 * @param string $type    | Item type to look for ('trigger' or 'action').
 * @return bool
 */
function joinotify_workflow_has_content( $post_id, $type = '' ) {
	return Utils::check_workflow_content( $post_id, $type );
}


/**
 * Get the list of connected WhatsApp sender phone numbers.
 *
 * @since 2.0.0
 * @return array List of sender phone numbers.
 */
function joinotify_get_senders() {
	return Phone_Manager::get_senders();
}


/**
 * Check whether a phone number is a connected/allowed Joinotify sender.
 *
 * @since 2.0.0
 * @param string $sender | Sender phone number.
 * @return bool
 */
function joinotify_is_valid_sender( $sender ) {
	return Helpers::allowed_sender( $sender );
}


/**
 * Validate and format a phone number to the international format used by Joinotify.
 *
 * Falls back to the plugin's default country code (Settings) when the number has no country code.
 *
 * @since 2.0.0
 * @param string $phone | Raw phone number.
 * @return string Formatted phone number.
 */
function joinotify_format_phone( $phone ) {
	return Helpers::validate_and_format_phone( $phone );
}


/**
 * Get a Joinotify plugin setting value.
 *
 * @since 2.0.0
 * @param string $key | Setting key.
 * @return mixed Setting value, or false when the key is not set.
 */
function joinotify_get_setting( $key ) {
	return Admin::get_setting( $key );
}


/**
 * Get message history records.
 *
 * @since 2.0.0
 * @param array $args | Filter args (status, sender, receiver, per_page, page, ...).
 * @return array List of history rows (associative arrays).
 */
function joinotify_get_message_history( $args = array() ) {
	return Message_History::get_items( is_array( $args ) ? $args : array() );
}


/**
 * ---------------------------------------------------------------------------
 * Developer extension API — global helpers.
 *
 * Thin wrappers over MeuMouse\Joinotify\Api\Extensions so third parties can extend every
 * subsystem with plain PHP filters. Full reference and examples: DEVELOPERS.md.
 * ---------------------------------------------------------------------------
 */

/**
 * Register an action category (a tab in the builder actions library modal).
 *
 * @since 1.4.7
 * @param array $category {id, label, icon?, priority?}.
 * @return void
 */
function joinotify_register_action_category( $category ) {
	Extensions::register_action_category( $category );
}


/**
 * Register a builder action (optionally with handler/description/fill_sender convenience keys).
 *
 * @since 1.4.7
 * @param array $definition Action definition.
 * @return void
 */
function joinotify_register_action( $definition ) {
	Extensions::register_action( $definition );
}


/**
 * Register the runtime handler for a custom action.
 *
 * @since 1.4.7
 * @param string   $slug     Action slug.
 * @param callable $callback Handler ($action_data, $action, $post_id, $event_data) => bool.
 * @return void
 */
function joinotify_register_action_handler( $slug, $callback ) {
	Extensions::register_action_handler( $slug, $callback );
}


/**
 * Register the canvas description builder for a custom action.
 *
 * @since 1.4.7
 * @param string   $slug     Action slug.
 * @param callable $callback ($action_data, $workflow_action) => string HTML.
 * @return void
 */
function joinotify_register_action_description( $slug, $callback ) {
	Extensions::register_action_description( $slug, $callback );
}


/**
 * Register an integration card (also defines a trigger context).
 *
 * @since 1.4.7
 * @param array $integration {slug, title, description, icon, setting_key?, ...}.
 * @return void
 */
function joinotify_register_integration( $integration ) {
	Extensions::register_integration( $integration );
}


/**
 * Register a trigger under a context (integration slug).
 *
 * @since 1.4.7
 * @param string $context Context/integration slug.
 * @param array  $trigger {data_trigger, title, description, require_settings?, settings?, ...}.
 * @return void
 */
function joinotify_register_trigger( $context, $trigger ) {
	Extensions::register_trigger( $context, $trigger );
}


/**
 * Dispatch workflows for a trigger at runtime.
 *
 * @since 1.4.7
 * @param string $hook        Trigger slug.
 * @param string $integration Context/integration slug.
 * @param array  $payload     Extra payload keys.
 * @return void
 */
function joinotify_dispatch_trigger( $hook, $integration, $payload = array() ) {
	Extensions::dispatch_trigger( $hook, $integration, $payload );
}


/**
 * Register conditions available for a trigger.
 *
 * @since 1.4.7
 * @param string $trigger    Trigger slug.
 * @param array  $conditions Map of condition_key => {title, description}.
 * @return void
 */
function joinotify_register_conditions( $trigger, $conditions ) {
	Extensions::register_conditions( $trigger, $conditions );
}


/**
 * Register the allowed operators for a condition type.
 *
 * @since 1.4.7
 * @param string $condition_type Condition key.
 * @param array  $operators      Operator slugs.
 * @return void
 */
function joinotify_register_condition_operators( $condition_type, $operators ) {
	Extensions::register_condition_operators( $condition_type, $operators );
}


/**
 * Register the value resolver for a condition type.
 *
 * @since 1.4.7
 * @param string   $condition_type Condition key.
 * @param callable $callback       ($value_map, $type, $payload) => mixed.
 * @return void
 */
function joinotify_register_condition_value( $condition_type, $callback ) {
	Extensions::register_condition_value( $condition_type, $callback );
}


/**
 * Register dynamic placeholders for an integration/context.
 *
 * @since 1.4.7
 * @param string $integration  Integration/context slug.
 * @param array  $placeholders Map of '{{ name }}' => {triggers, description, replacement}.
 * @return void
 */
function joinotify_register_placeholders( $integration, $placeholders ) {
	Extensions::register_placeholders( $integration, $placeholders );
}


/**
 * Register a settings navigation tab.
 *
 * @since 1.4.7
 * @param array $tab {id, name, icon, section}.
 * @return void
 */
function joinotify_register_settings_tab( $tab ) {
	Extensions::register_settings_tab( $tab );
}


/**
 * Register a settings schema section.
 *
 * @since 1.4.7
 * @param array $section {id, title, layout?, cards}.
 * @return void
 */
function joinotify_register_settings_section( $section ) {
	Extensions::register_settings_section( $section );
}


/**
 * Register a custom REST route under joinotify/v1.
 *
 * @since 1.4.7
 * @param array $route {route, methods?, callback, permission?, args?}.
 * @return void
 */
function joinotify_register_rest_route( $route ) {
	Extensions::register_rest_route( $route );
}


/**
 * Register a notification delivery channel (WhatsApp, Telegram, e-mail, SMS, ...).
 *
 * The class (or instance) must implement
 * MeuMouse\Joinotify\Notifications\Channel_Interface.
 *
 * @since 2.0.0
 * @param string        $id    Unique channel id (eg: 'telegram').
 * @param string|object $class Class name or instance implementing Channel_Interface.
 * @return void
 */
function joinotify_register_notification_channel( $id, $class ) {
	Extensions::register_notification_channel( $id, $class );
}


/**
 * Dispatch a notification through the channel layer.
 *
 * Builds a Notification_Message from $args and routes it through Channel_Manager,
 * which resolves the target channel (from $args['channel'] or the default),
 * validates it, and delivers. Always returns a normalized Channel_Result.
 *
 * @since 2.0.0
 * @param array<string,mixed> $args Message properties (channel, type, sender,
 *                                  receiver, content, media_type, media_url,
 *                                  caption, delay, context, meta).
 * @return \MeuMouse\Joinotify\Notifications\Channel_Result
 */
function joinotify_dispatch_notification( $args ) {
	return Channel_Manager::dispatch( Notification_Message::from_array( is_array( $args ) ? $args : array() ) );
}

