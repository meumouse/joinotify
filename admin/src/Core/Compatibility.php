<?php

namespace MeuMouse\Joinotify\Core;

/**
 * Compatibility class
 * 
 * @since 1.1.0
 * @package MeuMouse\Joinotify\Core
 * @author MeuMouse.com
 */
class Compatibility {

   /**
    * Construct function
    * 
    * @since 1.1.0
    * @version 2.3.4
    * @return void
    */
   public function __construct() {
      self::init();
   }


   /**
    * Register the compatibility declarations.
    *
    * Called from Core\Init at plugin load time, not from the `init` bootstrap:
    * WooCommerce fires `before_woocommerce_init` inside `WooCommerce::init()`,
    * which is hooked to `init` at priority 0. Registering from a constructor
    * built on `init` at priority 10 always missed it, so the HPOS declaration
    * never reached WooCommerce and the plugin was listed as incompatible.
    *
    * Safe to call twice: `add_action()` keys the callback by class and method,
    * so a repeat registration replaces the first instead of stacking.
    *
    * @since 2.3.4
    * @return void
    */
   public static function init() {
      // HPOS compatibility
      add_action( 'before_woocommerce_init', array( __CLASS__, 'hpos_compatibility' ) );
   }


   /**
    * Setup WooCommerce High-Performance Order Storage (HPOS) compatibility
    * 
    * @since 1.0.0
    * @version 2.3.4
    * @return void
    */
   public static function hpos_compatibility() {
      if ( defined('WC_VERSION') && version_compare( WC_VERSION, '7.1', '>' ) ) {
         if ( class_exists( \Automattic\WooCommerce\Utilities\FeaturesUtil::class ) ) {
            \Automattic\WooCommerce\Utilities\FeaturesUtil::declare_compatibility( 'custom_order_tables', JOINOTIFY_FILE, true );
         }
      }
   }
}