<?php

namespace MeuMouse\Joinotify\Licensing;

use MeuMouse\Joinotify\Api\License;
use MeuMouse\Joinotify\Core\Logger;
use MeuMouse\Joinotify\Licensing\Drivers\Mds_Driver;

use MeuMouse\MDS\SDK\Api\Client as Mds_Client;
use MeuMouse\MDS\SDK\Config\Product;
use MeuMouse\MDS\SDK\License\LicenseStatus;
use MeuMouse\MDS\SDK\License\Manager as Mds_License_Manager;
use MeuMouse\MDS\SDK\Security\SignatureVerifier;
use MeuMouse\MDS\SDK\Support\Cache;
use MeuMouse\MDS\SDK\Support\Logger as Mds_Logger;
use MeuMouse\MDS\SDK\Updates\PluginUpdater;

// Exit if accessed directly.
defined('ABSPATH') || exit;

/**
 * Serves plugin updates from MDS once a site has migrated.
 *
 * Update packages on the new server are gated by license and handed out through
 * short-lived tokens, so the update check has to go through the SDK rather than
 * the plain version manifest the legacy updater reads.
 *
 * The SDK's updater asks a license manager whether the site is entitled, and
 * that manager keeps its own copy of the license. Rather than let a second copy
 * drift, this class mirrors the plugin's license state into the keys the SDK
 * reads. The plugin stays the single source of truth; the SDK just sees it.
 *
 * @since 2.1.0
 * @package MeuMouse\Joinotify\Licensing
 * @author MeuMouse.com
 */
class Updates {

    /**
     * Register the update handler when this site is served by MDS.
     *
     * @since 2.1.0
     * @return void
     */
    public function __construct() {
        if ( ! self::is_active() ) {
            return;
        }

        // Keep the mirror in step with every path that changes the license.
        add_action( 'Joinotify/Licensing/Driver_Elected', array( __CLASS__, 'sync_license_state' ) );
        add_action( 'update_option_joinotify_license_response_object', array( __CLASS__, 'sync_license_state' ) );
        add_action( 'delete_option_joinotify_license_response_object', array( __CLASS__, 'sync_license_state' ) );

        self::sync_license_state();

        $updater = self::build_updater();

        if ( $updater instanceof PluginUpdater ) {
            $updater->register();
        }
    }


    /**
     * Whether MDS is responsible for this site's updates.
     *
     * @since 2.1.0
     * @return bool
     */
    public static function is_active() {
        if ( Mds_Driver::ID !== Driver_State::current() ) {
            return false;
        }

        if ( ! class_exists( PluginUpdater::class ) || ! Mds_Driver::is_configured() ) {
            return false;
        }

        /**
         * Filters whether plugin updates are served by MDS.
         *
         * @since 2.1.0
         * @param bool $is_active | Whether MDS serves updates
         */
        return (bool) apply_filters( 'Joinotify/Licensing/Mds_Updates', true );
    }


    /**
     * Copy the plugin's license state into the options the SDK reads.
     *
     * @since 2.1.0
     * @return void
     */
    public static function sync_license_state() {
        $product = self::product();

        if ( ! $product instanceof Product ) {
            return;
        }

        $license_key = (string) get_option( 'joinotify_license_key', '' );
        $license = get_option('joinotify_license_response_object');
        $is_valid = License::is_valid();

        $expires_at = null;

        if ( is_object( $license ) && ! empty( $license->expire_date ) && 'No expiry' !== $license->expire_date ) {
            $expires_at = (string) $license->expire_date;
        }

        $status = new LicenseStatus( array(
            'status' => $is_valid ? LicenseStatus::STATUS_ACTIVE : LicenseStatus::STATUS_INVALID,
            'valid' => $is_valid,
            'domain' => Support\Site::domain(),
            'expires_at' => $expires_at,
            'checked_at' => time(),
            'last_success_at' => $is_valid ? time() : 0,
            'signed' => true,
        ));

        self::write_option( $product->key('license_key'), $license_key );
        self::write_option( $product->key('license_state'), $status->to_array() );
    }


    /**
     * Build the SDK updater.
     *
     * @since 2.1.0
     * @return PluginUpdater|null
     */
    protected static function build_updater() {
        $product = self::product();

        if ( ! $product instanceof Product ) {
            return null;
        }

        try {
            $logger = new Mds_Logger( $product->slug() );
            $client = new Mds_Client( $product, new SignatureVerifier( $product->public_key() ), $logger );

            return new PluginUpdater(
                $product,
                $client,
                new Mds_License_Manager( $product, $client, $logger ),
                new Cache( $product ),
                $logger
            );
        } catch ( \Throwable $e ) {
            Logger::register_log( 'Could not build the MDS updater: ' . $e->getMessage(), 'ERROR' );

            return null;
        }
    }


    /**
     * Product configuration shared by the updater and the license mirror.
     *
     * @since 2.1.0
     * @return Product|null
     */
    protected static function product() {
        static $product = null;

        if ( null !== $product ) {
            return $product;
        }

        try {
            $product = new Product( array(
                'product_slug' => Mds_Driver::PRODUCT_SLUG,
                'file' => defined('JOINOTIFY_BASENAME') ? JOINOTIFY_BASENAME : 'joinotify/joinotify.php',
                'current_version' => defined('JOINOTIFY_VERSION') ? JOINOTIFY_VERSION : '0.0.0',
                'api_base_url' => (string) apply_filters( 'Joinotify/Licensing/Mds/Api_Url', defined('JOINOTIFY_MDS_API_URL') ? JOINOTIFY_MDS_API_URL : '' ),
                'api_key' => (string) apply_filters( 'Joinotify/Licensing/Mds/Api_Key', defined('JOINOTIFY_MDS_API_KEY') ? JOINOTIFY_MDS_API_KEY : '' ),
                'public_key' => (string) apply_filters( 'Joinotify/Licensing/Mds/Public_Key', defined('JOINOTIFY_MDS_PUBLIC_KEY') ? JOINOTIFY_MDS_PUBLIC_KEY : '' ),
                'item_name' => 'Joinotify',
                'text_domain' => 'joinotify',
            ));
        } catch ( \Throwable $e ) {
            Logger::register_log( 'Could not build the MDS product configuration: ' . $e->getMessage(), 'ERROR' );

            return null;
        }

        return $product;
    }


    /**
     * Write an option, network-wide on multisite so every subsite sees the same
     * entitlement, matching how the SDK reads it back.
     *
     * @since 2.1.0
     * @param string $name | Option name
     * @param mixed $value | Option value
     * @return void
     */
    protected static function write_option( $name, $value ) {
        if ( is_multisite() ) {
            update_site_option( $name, $value );

            return;
        }

        update_option( $name, $value, false );
    }
}
