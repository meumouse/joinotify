<?php

namespace MeuMouse\Joinotify\Api;

use MeuMouse\Joinotify\Core\Logger;
use MeuMouse\Joinotify\Licensing\Contracts\Driver;
use MeuMouse\Joinotify\Licensing\Drivers\Legacy_Driver;
use MeuMouse\Joinotify\Licensing\Dto\License_Result;
use MeuMouse\Joinotify\Licensing\Support\Crypto;
use MeuMouse\Joinotify\Licensing\Support\Site;

// Exit if accessed directly.
defined('ABSPATH') || exit;

/**
 * License state for this installation.
 *
 * Owns everything that outlives a single request: the stored license object, the
 * caches in front of it, the grace window that keeps a site working through a
 * server outage, and the scheduled expiry check. Talking to a licensing server
 * is delegated to a driver, so those rules stay the same regardless of which
 * server answered.
 *
 * @since 1.0.0
 * @version 2.1.0
 * @package MeuMouse\Joinotify\API
 * @author MeuMouse.com
 */
class License {

    /**
     * Base URL of the legacy licensing server.
     *
     * Retained for backwards compatibility with third-party code; the driver
     * owns the URL it actually calls.
     *
     * @since 1.0.0
     * @var string
     */
    public static $server_host = 'https://api.meumouse.com/wp-json/license/';

    /**
     * Legacy product key.
     *
     * Retained for backwards compatibility; the driver resolves the product from
     * the license key.
     *
     * @since 1.0.0
     * @var string
     */
    public $joinotify_product_key = 'E63390D3F50B70F0';

    /**
     * Plugin main file path.
     *
     * @since 1.0.0
     * @var string
     */
    protected $plugin_file;

    /**
     * Callbacks invoked when the local license is dropped.
     *
     * @since 1.0.0
     * @var array
     */
    protected static $_onDeleteLicense = array();

    /**
     * Shared instance.
     *
     * @since 1.0.0
     * @var self|null
     */
    protected static $self_obj;


    /**
     * Construct function
     *
     * @since 1.0.0
     * @version 2.1.0
     * @param string $plugin_base_file | Plugin main file path
     * @return void
     */
    public function __construct( $plugin_base_file = '' ) {
        $this->plugin_file = $plugin_base_file;

        // deactive license on expire time
        add_action( 'joinotify_check_license_expires_event', array( __CLASS__, 'check_license_expires_time' ) );

        // register schedule event first time
        if ( ! get_option('joinotify_schedule_expiration_check_runned') ) {
            add_action( 'admin_init', array( __CLASS__, 'schedule_license_expiration_check' ) );
        }
    }


    /**
     * Get plugin instance
     *
     * @since 1.0.0
     * @param self $plugin_base_file | Plugin file
     * @return self|null
     */
    static function &get_instance( $plugin_base_file = null ) {
        if ( empty( self::$self_obj ) ) {
            if ( ! empty( $plugin_base_file ) ) {
                self::$self_obj = new self( $plugin_base_file );
            }
        }

        return self::$self_obj;
    }


    /**
     * Driver used to talk to the licensing server.
     *
     * @since 2.1.0
     * @param string $license_key | License key
     * @return Driver
     */
    protected function driver( $license_key ) {
        /**
         * Filters the driver used for licensing calls.
         *
         * @since 2.1.0
         * @param Driver $driver | Driver instance
         * @param string $license_key | License key
         */
        return apply_filters( 'Joinotify/Licensing/Driver', new Legacy_Driver( $license_key ), $license_key );
    }


    /**
     * Get renew license link
     *
     * @since 1.0.0
     * @param object $response_object | Response object
     * @param string $type | Renew type
     * @return string
     */
    private static function get_renew_link( $response_object, $type = 's' ) {
        if ( empty( $response_object->renew_link ) ) {
            return '';
        }

        $show_button = false;

        if ( $type == 's' ) {
            $support_end = isset( $response_object->support_end ) && is_scalar( $response_object->support_end ) ? (string) $response_object->support_end : '';
            $support_str = strtolower( trim( $support_end ) );

            if ( strtolower( trim( $support_end ) ) == 'no support' ) {
                $show_button = true;
            } elseif ( ! in_array( $support_str, ["unlimited"] ) ) {
                if ( strtotime( 'ADD 30 DAYS', strtotime( $support_end ) ) < time() ) {
                    $show_button = true;
                }
            }

            if ( $show_button ) {
                $renew_link = is_scalar( $response_object->renew_link ) ? (string) $response_object->renew_link : '';
                $license_key = isset( $response_object->license_key ) && is_scalar( $response_object->license_key ) ? (string) $response_object->license_key : '';

                return $renew_link . ( strpos( $renew_link, '?' ) === FALSE ? '?type=s&lic=' . rawurlencode( $license_key ) : '&type=s&lic='. rawurlencode( $license_key ) );
            }

            return '';
        } else {
            $show_button = false;
            $expire_date = isset( $response_object->expire_date ) && is_scalar( $response_object->expire_date ) ? (string) $response_object->expire_date : '';
            $expire_str = strtolower( trim( $expire_date ) );

            if ( ! in_array( $expire_str, array( 'unlimited', 'no expiry' ) ) ) {
                if ( strtotime( 'ADD 30 DAYS', strtotime( $expire_date ) ) < time() ) {
                    $show_button = true;
                }
            }

            if ( $show_button ) {
                $renew_link = is_scalar( $response_object->renew_link ) ? (string) $response_object->renew_link : '';
                $license_key = isset( $response_object->license_key ) && is_scalar( $response_object->license_key ) ? (string) $response_object->license_key : '';

                return $renew_link . ( strpos( $renew_link, '?' ) === FALSE ? '?type=l&lic=' . rawurlencode( $license_key ) : '&type=l&lic=' . rawurlencode( $license_key ) );
            }

            return '';
        }
    }


    /**
     * Get site domain
     *
     * @since 1.0.0
     * @return string
     */
    public static function get_domain() {
        return Site::url();
    }


    /**
     * Generate hash key
     *
     * @since 1.0.0
     * @version 2.1.0
     * @return string
     */
    private function get_key_name() {
        $product = Legacy_Driver::resolve_product( get_option('joinotify_license_key') );

        return hash( 'crc32b', self::get_domain() . $this->plugin_file . $product['id'] . $product['base'] . $product['key'] . 'LIC' );
    }


    /**
     * Set response base option
     *
     * @since 1.0.0
     * @param object $response | Response object
     * @return void
     */
    private function set_response_base( $response ) {
        $key = $this->get_key_name();
        $data = Crypto::encrypt( maybe_serialize( $response ), self::get_domain() );

        update_option( $key, $data ) || add_option( $key, $data );
    }


    /**
     * Get response base option
     *
     * @since 1.0.0
     * @return mixed
     */
    public function get_response_base() {
        $key = $this->get_key_name();
        $response = get_option( $key, NULL );

        if ( empty( $response ) ) {
            return NULL;
        }

        return maybe_unserialize( Crypto::decrypt( $response, self::get_domain() ) );
    }


    /**
     * Remove response base option
     *
     * @since 1.0.0
     * @version 1.4.7
     * @return bool
     */
    public function remove_response_base() {
        $key = $this->get_key_name();
        $is_deleted = delete_option( $key );

        self::clear_local_state();

        foreach ( self::$_onDeleteLicense as $func ) {
            if ( is_callable( $func ) ) {
                call_user_func( $func );
            }
        }

        return $is_deleted;
    }


    /**
     * Drop every option and transient that describes the current license.
     *
     * @since 2.1.0
     * @return void
     */
    protected static function clear_local_state() {
        update_option( 'joinotify_license_status', 'invalid' );

        delete_option('joinotify_license_key');
        delete_option('joinotify_license_response_object');
        delete_option('joinotify_alternative_license');
        delete_option('joinotify_temp_license_key');
        delete_option('joinotify_alternative_license_activation');
        delete_option('joinotify_license_expiration_failed_attempts');
        delete_transient('joinotify_api_request_cache');
        delete_transient('joinotify_api_response_cache');
        delete_transient('joinotify_license_status_cached');
    }


    /**
     * Deactive license action
     *
     * @since 1.0.0
     * @param string $plugin_base_file | Plugin base file
     * @param string $message | Error message
     * @return bool
     */
    public static function deactive_license( $plugin_base_file, &$message = "" ) {
        $obj = self::get_instance( $plugin_base_file );

        return $obj->deactive_license_process( $message );
    }


    /**
     * Check purchase key
     *
     * @since 1.0.0
     * @param string $purchase_key | License key
     * @param string $error | Error message
     * @param object $response | Response object
     * @param string $plugin_base_file | Plugin base file
     * @return object
     */
    public static function check_license( $purchase_key, &$error = '', &$response = null, $plugin_base_file = '' ) {
        $obj = self::get_instance( $plugin_base_file );

        return $obj->check_license_object( $purchase_key, $error, $response );
    }


    /**
     * Deactive license process
     *
     * @since 1.0.0
     * @version 2.1.0
     * @param string $message | Error message
     * @return bool
     */
    final function deactive_license_process( &$message = '' ) {
        $old_response = $this->get_response_base();

        if ( empty( $old_response->is_valid ) ) {
            $this->remove_response_base();

            return true;
        }

        if ( empty( $old_response->license_key ) ) {
            return false;
        }

        $result = $this->driver( $old_response->license_key )->deactivate( $old_response->license_key );

        if ( defined('JOINOTIFY_DEBUG_MODE') && JOINOTIFY_DEBUG_MODE ) {
            Logger::register_log( 'License API - Deactive result : ' . print_r( $result->message(), true ) );
        }

        $message = $result->message();

        if ( ! $result->succeeded() ) {
            return false;
        }

        $this->remove_response_base();

        return true;
    }


    /**
     * Check if license is active and valid
     *
     * @since 1.0.0
     * @version 2.1.0
     * @param string $purchase_key | License key
     * @param string $error | Error message
     * @param object $response_object | Response object
     * @return mixed object or bool
     */
    final function check_license_object( $purchase_key, &$error = '', &$response_object = null ) {
        if ( get_option('joinotify_alternative_license') === 'active' ) {
            return;
        }

        if ( empty( $purchase_key ) ) {
            $this->remove_response_base();
            $error = '';

            return false;
        }

        $transient_name = 'joinotify_api_response_cache';
        $cached_response = get_transient( $transient_name );

        if ( false !== $cached_response ) {
            $response_object = maybe_unserialize( $cached_response );
            unset( $response_object->next_request );

            return true;
        }

        $old_response = $this->get_response_base();
        $is_force = false;

        if ( ! empty( $old_response ) ) {
            if ( ! empty( $old_response->expire_date ) && strtolower( $old_response->expire_date ) != 'no expiry' && strtotime( $old_response->expire_date ) < time() ) {
                $is_force = true;
            }

            if ( ! $is_force && ! empty( $old_response->is_valid ) && $old_response->next_request > time() && ( ! empty( $old_response->license_key ) && $purchase_key == $old_response->license_key ) ) {
                $response_object = clone $old_response;
                unset( $response_object->next_request );

                return true;
            }
        }

        $result = $this->driver( $purchase_key )->validate( $purchase_key );

        if ( defined('JOINOTIFY_DEBUG_MODE') && JOINOTIFY_DEBUG_MODE ) {
            Logger::register_log( 'License API - Validate result : ' . print_r( $result->data(), true ) );
        }

        if ( $result->is_valid() ) {
            $response_object = $this->store_valid_result( $result, $purchase_key );

            return true;
        }

        // An unreachable server must not cost a paying customer their license,
        // so the last known good answer stands for a couple more attempts.
        if ( $this->check_old_response( $old_response, $response_object ) ) {
            return true;
        }

        $this->remove_response_base();
        $error = $result->message();

        return false;
    }


    /**
     * Persist a valid result and build the response object callers expect.
     *
     * @since 2.1.0
     * @param License_Result $result | Driver result
     * @param string $purchase_key | License key
     * @return object
     */
    protected function store_valid_result( License_Result $result, $purchase_key ) {
        $request_duration = (int) $result->get( 'request_duration', 0 );

        $response_object = new \stdClass();
        $response_object->is_valid = true;
        $response_object->next_request = $request_duration > 0 ? strtotime( "+ {$request_duration} hour" ) : time();
        $response_object->expire_date = $result->get( 'expire_date', '' );
        $response_object->support_end = $result->get( 'support_end', '' );
        $response_object->license_title = $result->get( 'license_title', '' );
        $response_object->license_key = $purchase_key;
        $response_object->msg = $result->message();
        $response_object->renew_link = $result->get( 'renew_link', '' );
        $response_object->expire_renew_link = self::get_renew_link( $response_object, 'l' );
        $response_object->support_renew_link = self::get_renew_link( $response_object, 's' );

        $this->set_response_base( $response_object );

        // Cache the response for 1 day
        set_transient( 'joinotify_api_response_cache', maybe_serialize( $response_object ), DAY_IN_SECONDS );

        $public_object = clone $response_object;
        unset( $public_object->next_request );

        // The readable copy of the license, consumed by the settings screen and
        // by is_valid(). Written before scheduling, which reads it back.
        update_option( 'joinotify_license_response_object', $public_object );

        // schedule event for check expiration license time
        self::schedule_license_expiration_check();

        return $public_object;
    }


    /**
     * Check if old response is active
     *
     * @since 1.0.0
     * @param object $old_response |
     * @param object $response_object |
     * @return bool
     */
    private function check_old_response( &$old_response, &$response_object ) {
        if ( ! empty( $old_response ) && ( empty( $old_response->tried ) || $old_response->tried <= 2 ) ) {
            $old_response->next_request = strtotime('+ 1 hour');
            $old_response->tried = empty( $old_response->tried ) ? 1 : ( $old_response->tried + 1 );
            $response_object = clone $old_response;
            unset( $response_object->next_request );

            if ( isset( $response_object->tried ) ) {
                unset( $response_object->tried );
            }

            $this->set_response_base( $old_response );

            return true;
        }

        return false;
    }


    /**
     * Persist the local license status from a check_license() response object.
     *
     * Stores `joinotify_license_status` as valid/invalid and clears the
     * alternative-activation flag when the license is valid. Shared by the
     * activate and sync REST endpoints.
     *
     * @since 2.0.0
     * @param object|null $response Response object returned via check_license().
     * @return bool Whether the response represents a valid license.
     */
    public static function persist_status_from_response( $response ) {
        $is_valid = $response && ! empty( $response->is_valid );

        update_option( 'joinotify_license_status', $is_valid ? 'valid' : 'invalid' );

        if ( $is_valid ) {
            delete_option( 'joinotify_alternative_license_activation' );
        }

        return $is_valid;
    }


    /**
     * Get license expires time
     *
     * @since 1.1.0
     * @version 2.1.0
     * @param string $license_key | License key
     * @return string|false
     */
    public static function get_expires_time( $license_key ) {
        $obj = self::get_instance( defined('JOINOTIFY_FILE') ? JOINOTIFY_FILE : '' );

        if ( ! $obj ) {
            return false;
        }

        $timestamp = $obj->driver( $license_key )->expires_at( $license_key );

        // ISO-8601 with an explicit offset: callers pass this straight back
        // through strtotime(), and a bare UTC string would be re-read as local.
        return $timestamp ? gmdate( 'c', $timestamp ) : false;
    }


    /**
     * Check if license is valid
     *
     * @since 1.0.0
     * @return bool
     */
    public static function is_valid() {
        $cached_result = get_transient('joinotify_license_status_cached');

        // If the result is cached, return it
        if ( $cached_result !== false ) {
            return $cached_result;
        }

        $object_query = get_option('joinotify_license_response_object');

        if ( is_object( $object_query ) && ! empty( $object_query ) && isset( $object_query->is_valid )  ) {
            // set response cache for 24h
            set_transient('joinotify_license_status_cached', true, 86400);

            return true;
        } else {
            set_transient('joinotify_license_status_cached', false, 86400);
            update_option( 'joinotify_license_status', 'invalid' );

            return false;
        }
    }


    /**
     * Get license title
     *
     * @version 1.4.7
     * @return string
     */
    public static function license_title() {
        $object_query = get_option('joinotify_license_response_object');

        if ( is_object( $object_query ) && ! empty( $object_query ) && isset( $object_query->license_title ) ) {
          return $object_query->license_title;
        } else {
          return esc_html__( 'Not available', 'joinotify' );
        }
    }


    /**
     * Get license expire date
     *
     * @since 1.0.0
     * @return string
     */
    public static function license_expire() {
        $object_query = get_option('joinotify_license_response_object');

        if ( is_object( $object_query ) && ! empty( $object_query ) && isset( $object_query->expire_date ) ) {
            if ( $object_query->expire_date === 'No expiry' ) {
                return esc_html__( 'Never expires', 'joinotify' );
            } else {
                if ( strtotime( $object_query->expire_date ) < time() ) {
                    $object_query->is_valid = false;

                    update_option( 'joinotify_license_response_object', $object_query );
                    update_option( 'joinotify_license_status', 'invalid' );
                    delete_option('joinotify_license_response_object');

                    return esc_html__( 'Expired license', 'joinotify' );
                }

                // get wordpress date format setting
                $date_format = get_option('date_format');

                return date( $date_format, strtotime( $object_query->expire_date ) );
            }
        }
    }


    /**
     * Check if license is expired
     *
     * @since 1.0.0
     * @return bool
     */
    public static function expired_license() {
        $object_query = get_option('joinotify_license_response_object');

        if ( is_object( $object_query ) && ! empty( $object_query ) && isset( $object_query->expire_date ) ) {
            if ( $object_query->expire_date === 'No expiry' ) {
                return false;
            } else {
                if ( strtotime( $object_query->expire_date ) < time() ) {
                    $object_query->is_valid = false;

                    update_option( 'joinotify_license_response_object', $object_query );

                    return false;
                }
            }
        }
    }


    /**
     * Check expiration license on schedule event
     *
     * @since 1.1.0
     * @param int $expiration_timestamp | Expiration timestamp
     * @return void
     */
    public static function schedule_license_expiration_check( $expiration_timestamp = 0 ) {
        // Cancel any previous bookings to avoid duplication
        wp_clear_scheduled_hook('joinotify_check_license_expires_event');

        if ( $expiration_timestamp <= 0 ) {
            $object_query = get_option('joinotify_license_response_object');

            if ( is_object( $object_query ) && ! empty( $object_query->expire_date ) ) {
                $expiration_timestamp = strtotime( $object_query->expire_date );
            }
        }

        if ( $expiration_timestamp > time() ) {
            // Add 24h to timestamp so the check runs after the license lapses
            wp_schedule_single_event( $expiration_timestamp + DAY_IN_SECONDS, 'joinotify_check_license_expires_event' );
        }

        // register runned event
        update_option( 'joinotify_schedule_expiration_check_runned', true );
    }


    /**
     * Schedule a new expiration check for the next day.
     *
     * @since 1.1.0
     * @return void
     */
    private static function schedule_license_expiration_retry_for_next_day() {
        wp_clear_scheduled_hook('joinotify_check_license_expires_event');
        wp_schedule_single_event( time() + DAY_IN_SECONDS, 'joinotify_check_license_expires_event' );
    }


    /**
     * Deactivate license on scheduled event
     *
     * @since 1.1.0
     * @return void
     */
    public static function check_license_expires_time() {
        $license_key = get_option('joinotify_license_key');

        if ( empty( $license_key ) ) {
            delete_option('joinotify_license_expiration_failed_attempts');

            return;
        }

        $api_expiry_time = self::get_expires_time( $license_key );
        $failed_attempts = (int) get_option('joinotify_license_expiration_failed_attempts', 0);
        $attempt_number = $failed_attempts + 1;

        /**
         * Fires whenever the scheduled license expiration validation runs.
         *
         * @since 1.1.0
         * @param int $attempt_number Attempt number for expiration validation.
         * @param string $license_key License key being checked.
         * @param mixed $api_expiry_time Expiration value returned by API. Can be false on failure.
         */
        do_action( 'joinotify_license_expiration_check_attempt', $attempt_number, $license_key, $api_expiry_time );

        if ( $api_expiry_time && strtotime( $api_expiry_time ) >= time() ) {
            delete_option('joinotify_license_expiration_failed_attempts');
            self::schedule_license_expiration_check( strtotime( $api_expiry_time ) );

            return;
        }

        update_option( 'joinotify_license_expiration_failed_attempts', $attempt_number );

        if ( $attempt_number >= 3 ) {
            $message = '';

            self::deactive_license( defined('JOINOTIFY_FILE') ? JOINOTIFY_FILE : '', $message );
            delete_option('joinotify_license_expiration_failed_attempts');

            return;
        }

        self::schedule_license_expiration_retry_for_next_day();
    }
}
