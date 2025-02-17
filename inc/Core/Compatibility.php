<?php

namespace MeuMouse\Joinotify\Core;

/**
 * Compatibility class
 * 
 * @since 1.1.0
 * @package MeuMouse.com
 */
class Compatibility {

   /**
    * Construct function
    * 
    * @since 1.1.0
    * @return void
    */
   public function __construct() {
      // HPOS compatibility
      add_action( 'before_woocommerce_init', array( $this, 'hpos_compatibility' ) );
   }


   /**
    * Setup WooCommerce High-Performance Order Storage (HPOS) compatibility
    * 
    * @since 1.0.0
    * @version 1.1.0
    * @return void
    */
   public function hpos_compatibility() {
      if ( defined('WC_VERSION') && version_compare( WC_VERSION, '7.1', '>' ) ) {
         if ( class_exists( \Automattic\WooCommerce\Utilities\FeaturesUtil::class ) ) {
            \Automattic\WooCommerce\Utilities\FeaturesUtil::declare_compatibility( 'custom_order_tables', JOINOTIFY_FILE, true );
         }
      }
   }
}