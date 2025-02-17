<?php

use MeuMouse\Joinotify\API\Controller;
use MeuMouse\Joinotify\Admin\Admin;
use MeuMouse\Joinotify\Core\Logger;

// Exit if accessed directly.
defined('ABSPATH') || exit;

/**
 * Check admin page from partial URL
 * 
 * @since 1.1.0
 * @param $admin_page | Page string for check from admin.php?page=
 * @return bool
 */
function joinotify_check_admin_page( $admin_page ) {
   $current_url = ( isset( $_SERVER['HTTPS'] ) && $_SERVER['HTTPS'] === 'on' ? 'https' : 'http' ) . '://' . $_SERVER['HTTP_HOST'] . $_SERVER['REQUEST_URI'];

   return strpos( $current_url, "admin.php?page=$admin_page" );
}


/**
 * Send message text on WhatsApp
 * 
 * @since 1.1.0
 * @param string $sender | Instance phone number
 * @param string $receiver | Phone number for receive message
 * @param string $message | Message text for send
 * @param int $delay | Delay in miliseconds before send message
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
 * @param string $sender | Instance phone number
 * @param string $receiver | Phone number for receive message
 * @param string $media_type | Media type (image, audio, video or document)
 * @param string $media | Media URL
 * @param int $delay | Delay in miliseconds before send message
 * @return int
 */
function joinotify_send_whatsapp_message_media( $sender, $receiver, $media_type, $media, $delay ) {
   $response = Controller::send_message_media( $sender, $receiver, $media_type, $media, $delay );

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