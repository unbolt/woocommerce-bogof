<?php
/**
* Plugin Name: WooCommerce BOGOF
* Plugin URI: https://unbo.lt/
* Description: Add buy one get one free option to WooCommerce
* Author: Dan Baker
* Author URI: https://unbo.lt/
* Version: 0.0.1
* Requires at least: 4.3
* Tested up to: 4.3
* WC requires at least: 3.1.1
* WC tested up to: 3.1.1
* Text Domain: wc-bogof
*
* Please see readme.txt for the general outline requirements
* for this plugin and the approximate plan.
*
*/


// If this file is accessed directly then just die
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}


if ( ! class_exists( 'WC_BOGOF' ) ) :

    /**
     * WC_BOGOF is the self contained class for managing the BOGOF offers and
     * applying them to baskets.
     *
     * Note that this is defined as final to prevent extension. This is an
     * attempt at some added security, if the codebase is compromised then
     * this plugin can't be used to piggy back off to do things it shouldn't.
     * (Though if the codebase is compromised we've probably got bigger issues)
     */
    final class WC_BOGOF {

        /**
         * This stores the following options that can be configured in the
         * admin section
         *
         * @var bogof_active 1:0
         * @var percent_discount (optional) 0-100
         * @var excluded_products array of product IDs to exclude
         *
         * @var array
         */
        private $options;

        /**
         * Setup the plugin
         */
        function __construct() {
            // This hooks on the WC init call so that it only happens if WC is
            // present and correct.
            add_action( 'woocommerce_init', array( $this, 'woocommerce_init' ) );
        }

        /**
         * Add the actions and filters to the hooks as required for the
         * plugin to operate.
         *
         * @since   0.0.1
         * @version 0.0.1
         */
        function woocommerce_init() {

            // If we're in the admin section then we can add the menu items.
            if ( is_admin() ) {
                add_action( 'admin_menu', array( $this, 'admin_menu' ) );
                add_action( 'admin_init', array( $this, 'admin_init' ) );
            }

            // Add hooks for our functions to the WC system
            add_action( 'woocommerce_after_calculate_totals',   array( $this, 'woocommerce_after_calculate_totals' ), 10, 2 );
            add_filter( 'woocommerce_get_shop_coupon_data',     array( $this, 'woocommerce_get_shop_coupon_data' ), 10, 2 );
            add_action( 'woocommerce_add_to_cart',              array( $this, 'apply_bogof_test' ) );
            add_action( 'woocommerce_check_cart_items',         array( $this, 'apply_bogof_test' ) );
            add_filter( 'woocommerce_cart_totals_coupon_label', array( $this, 'woocommerce_cart_totals_coupon_label' ), 10, 2 );

        }

        /**
         * ====================================================================================================
         * WP_ADMIN FUNCTIONS
         * ====================================================================================================
         * These functions add the options page and the sidebar menu
         * to the wp_admin section.
         */

        /**
         * Add menu items to the WP dashboard menu.
         *
         * @since   0.0.1
         * @version 0.0.1
         */
        function admin_menu() {
            // This page will be under "Settings"
            add_submenu_page(
                'woocommerce',
                'WC BOGOF Settings',
                'BOGOF Settings',
                'manage_woocommerce',
                'wc-bogof-setting-admin',
                array( $this, 'create_admin_page' )
            );
        }

        /**
         * Creates the BOGOF settings admin page with the form to update
         * the settings.
         *
         * @since   0.0.1
         * @version 0.0.1
         */
        public function create_admin_page() {

            // Get some settings!
            $this->options = get_option( 'wc_bogof_options' );

            ?>
                <div class="wrap">
                    <h1>BOGOF Settings</h1>
                    <form method="post" action="options.php">
                    <?php
                        settings_fields( 'wc_bogof_option_group' );
                        do_settings_sections( 'wc-bogof-setting-admin' );
                        submit_button();
                    ?>
                    </form>
                </div>
            <?php
        }

        /**
         * Build the BOGOF settings admin page
         * Adds the sections, headers and form inputs.
         *
         * @since   0.0.1
         * @version 0.0.1
         */
        function admin_init() {
            register_setting(
                  'wc_bogof_option_group', // Option group
                  'wc_bogof_options', // Option name
                  array( $this, 'sanitize' ) // Sanitize function
              );

              add_settings_section(
                  'wc_bogof_general_settings', // ID
                  'General BOGOF Settings', // Title
                  array( $this, 'print_intro_info' ), // Callback
                  'wc-bogof-setting-admin' // Page
              );

              add_settings_field(
                  'bogof_active', // ID
                  'BOGOF Active', // Title
                  array( $this, 'bogof_active_callback' ), // Callback
                  'wc-bogof-setting-admin', // Page
                  'wc_bogof_general_settings' // Section
              );

              add_settings_field(
                  'percent_discount',
                  'Percent Discount (optional)',
                  array( $this, 'percent_discount_callback' ),
                  'wc-bogof-setting-admin',
                  'wc_bogof_general_settings'
              );

              add_settings_field(
                  'discount_code',
                  'Discount Code',
                  array( $this, 'discount_code_callback' ),
                  'wc-bogof-setting-admin',
                  'wc_bogof_general_settings'
              );

              add_settings_section(
                  'wc_bogof_exclude_settings',
                  'Exclude Product IDs',
                  array( $this, 'print_exclude_info' ),
                  'wc-bogof-setting-admin'
              );

              add_settings_field(
                  'excluded_product_ids',
                  'Excluded Products',
                  array( $this, 'excluded_products_callback' ),
                  'wc-bogof-setting-admin',
                  'wc_bogof_exclude_settings'
              );
        }


        /**
         * Sanitize each setting field. This contains very based sanitisation
         * at this time.
         *
         * @param   array   $input     Array containing all the form inputs
         *
         * @since   0.0.1
         * @version 0.0.2
         */
        public function sanitize( $input ) {

            $new_input = array();

            // If the checkbox is ticked then we set bogof_active to true
            // otherwise we default it to false.
            if( isset( $input['bogof_active'] ) ) {
                if( $input['bogof_active'] == 'on' ) {
                    $new_input['bogof_active'] = true;
                } else {
                    $new_input['bogof_active'] = false;
                }
            }

            // The percent_discount should always be an int.
            // Using intval will push anything not a number to 0
            // We then check if its a number greater than 100 and push it back
            // to 100 if it is.
            if( isset( $input['percent_discount'] ) ) {
                $new_input['percent_discount'] = intval( $input['percent_discount'] );

                if( $new_input['percent_discount'] > 100 ) {
                    $new_input['percent_discount'] = 100;
                }
            }

            // At the moment this simply assigns whatever the user has entered.
            if( isset( $input['excluded_products'] ) ) {

                $excluded_ids = explode(',', $input['excluded_products']);
                $new_excluded_ids = array();

                foreach($excluded_ids as $id) {
                    $new_excluded_ids[] = intval($id);
                }

                $new_input['excluded_products'] = implode(',', $new_excluded_ids);
            }


            if( isset( $input['discount_code'] ) ) {
                if( empty( $input['discount_code'] ) ) {
                    $new_input['discount_code'] = 'BOGOF';
                } else {
                    $new_input['discount_code'] = $input['discount_code'];
                }
            }

            return $new_input;
        }

        /**
         * Output and form input functions below this point for the options page
         */
        public function print_intro_info() {
            print 'Please note that if a value other than <strong>0</strong> is present in the Percent Discount field then that discount will be applied to the cheapest item, rather than the cheapest being free.<br /><br />If no discount code is entered then "BOGOF" will be displayed to the customer.';
        }

        public function print_exclude_info() {
            print 'Use a comma separated list of product IDs to exclude them from the BOGOF offer. Future addition: Add product lookup, or exclude by category/tag options.';
        }

        public function bogof_active_callback() {
            printf(
                '<input type="checkbox" id="bogof_active" name="wc_bogof_options[bogof_active]" %s />',
                isset( $this->options['bogof_active'] ) ? 'checked' : ''
            );
        }

        public function percent_discount_callback() {
            printf(
                '<input type="text" id="percent_discount" name="wc_bogof_options[percent_discount]" value="%s" />',
                isset( $this->options['percent_discount'] ) ? esc_attr( $this->options['percent_discount']) : ''
            );
        }

        public function discount_code_callback() {
            printf(
                '<input type="text" id="discount_code" name="wc_bogof_options[discount_code]" value="%s" />',
                isset( $this->options['discount_code'] ) ? esc_attr( $this->options['discount_code']) : ''
            );
        }

        public function excluded_products_callback() {
            printf(
                '<input type="text" id="excluded_products" name="wc_bogof_options[excluded_products]" value="%s" />',
                isset( $this->options['excluded_products'] ) ? esc_attr( $this->options['excluded_products']) : ''
            );
        }

        /**
         * ====================================================================================================
         * WP_ADMIN FUNCTIONS END
         * ====================================================================================================
         */


         /**
          * ====================================================================================================
          * WOOCOMMERCE CORE FUNCTIONS
          * ====================================================================================================
          */

          /**
           * Processes the request to check if there should be a BOGOF coupon
           * applied to this basket.
           *
           * @since     0.0.1
           * @version   0.0.1
           */
          function woocommerce_after_calculate_totals( ) {
              // We don't want this function to recurse when running
              // so we remove it from the hook, and then add it again after
              remove_action( 'woocommerce_after_calculate_totals', array( $this, 'woocommerce_after_calculate_totals' ) );

              $this->apply_bogof_test();

              add_action( 'woocommerce_after_calculate_totals', array( $this, 'woocommerce_after_calculate_totals' ) );
          }

          /**
           * Checks if the BOGOF offer is currently active
           * @return bool true/false as per admin settings
           *
           * @since     0.0.1
           * @version   0.0.1
           */
          function is_bogof_active() {

              $this->options = get_option( 'wc_bogof_options' );

              if( $this->options['bogof_active'] == 1 ) {
                  return true;
              }

              return false;
          }

          /**
           * Returns the BOGOF code
           * @return string BOGOF code from admin settings
           *
           * @since     0.0.1
           * @version   0.0.1
           */
          function get_discount_code() {

              $this->options = get_option( 'wc_bogof_options' );

              return $this->options['discount_code'];
          }


          /**
           * Returns the percentage discount if its active, or false otherwise
           *
           * @return string/false depending on admin settings
           *
           * @since     0.0.1
           * @version   0.0.1
           */
          function get_percent_discount() {

              $this->options = get_option( 'wc_bogof_options ');

              if( $this->options['percent_discount'] !== 0 ) {
                  return $this->options['percent_discount'];
              }

              return false;
          }


          /**
           * Checks if the given item is discountable. This is currently
           * done by checking if it is in the array of excluded items and
           * responding accordingly.
           *
           * @param  array      $item       The product to check.
           * @return boolean                If the item is discountable or not
           *
           * @since     0.0.1
           * @version   0.0.1
           */
          function is_discountable( $item ) {

              $this->options = get_option( 'wc_bogof_options' );

              // This is currently a comma separated list so we'll explode
              // it into an array so we can check if the product ID exists
              // in it.
              $exclude_ids = explode(',', $this->options['excluded_products']);

              if( in_array( $item['product_id'], $exclude_ids) ) {
                  return false;
              } else {
                  return true;
              }

          }


          /**
           * Check if a give code is a valid BOGOF code
           * This is currently done by checking if the string
           * given is the same as the one in the settings. This isn't
           * totally ideal, but currently banking on there being no
           * active codes that happen to be named the exact same thing
           * as the virtual code we're using here. There is scope
           * for expanding this so that it checks on a more advanced set
           * of variables, e.g. we could store a variable somewhere against
           * the basket to say if its had a virtual coupon applied to it or not.
           *
           * @param  string    $code    The code to check against
           * @return boolean            If code matches or not
           *
           * @since     0.0.1
           * @version   0.0.1
           */
          function check_bogof_code( $code ) {

              $discount_code = strtolower( trim( $this->get_discount_code() ) );
              $code = strtolower( trim( $code ) );

              if( $discount_code == $code ) {
                  return true;
              } else {
                  return false;
              }
          }

          /**
           * Apply the BOGOF tests and then apply the appropriate
           * coupon discount if it's required.
           *
           * @since     0.0.1
           * @version   0.0.1
           */
          function apply_bogof_test() {

              $discount = $this->get_discount();

              // If no discount should be applied then
              // we need to remove any existing coupons that
              // have been applied by the system, incase
              // someone has added an item and then removed it etc.
              if ( empty( $discount ) ) {

                  foreach ( WC()->cart->applied_coupons as $coupon_code ) {
                      if ( $this->check_bogof_code( $coupon_code ) ) {
                          WC()->cart->remove_coupon( $coupon_code );
                      }
                  }

              } else {

                  // Check the existing codes and if we already have
                  // one of our BOGOF codes applied then just use that.
                  foreach ( WC()->cart->applied_coupons as $coupon_code ) {
                      if ( $this->check_bogof_code( $coupon_code ) ) {
                          $code = $coupon_code;
                          break;
                      }
                  }

                  // If there's no code yet then we'll make a new one and apply
                  // it to the basket.
                  if ( !isset( $code ) ) {
                      if ( $this->is_bogof_active() ) {
                          $bogo_coupon_code = $this->get_discount_code();
                          WC()->cart->add_discount( $bogo_coupon_code );
                          WC()->session->set( 'refresh_totals', true );
                      }
                  }
              }
          }

          /**
           * Calculate the valid discount amount to apply to a basket.
           *
           * @return int amount to discount
           */
          function get_discount() {
              // If the cart has less than 2 items
              // then we don't need to do anything!
              if ( WC()->cart->cart_contents_count < 2 ) {
                  return;
              }

              if ( $this->is_bogof_active() ) {
                  foreach ( WC()->cart->get_cart() as $cart_item_key => $cart_item ) {
                      // Note that we are doing this on a per quantity basis
                      // If someone has >1 of the same thing then we still want
                      // to apply the discount.

                      // We also want to exclude any items that are
                      // in our exclude list. This is deferring to an external
                      // function for future expansion.
                      if( $this->is_discountable($cart_item) ) {
                          for ( $i = 0; $i < $cart_item['quantity']; $i++ ) {
                              $product_prices[] = $cart_item['data']->get_price();
                          }
                      }

                  }

                  // Sort by the price here, highest to lowest. That way the
                  // cheapest item will always be free.
                  rsort( $product_prices );

                  // Calculate our discount here
                  $discount = 0;
                  $last = count( $product_prices ) - 1;
                  $percentage_discount = $this->get_percent_discount();
                  // We need to loop through the items, but we need to
                  // discount the opposite item in the array compared to the one
                  // we're looking at... e.g.
                  // 90 <- view 1
                  // 80 <- view 2
                  // 70 <- view 3
                  // 65 <- view 4, nothing to discount!
                  // 60 <- discount 3
                  // 50 <- discount 2
                  // 40 <- discount 1
                  foreach ( $product_prices as $index => $price ) {
                      if ( $last > $index ) {

                          // If there's a percentage discount active
                          // then we want to discount the percentage
                          // otherwise we just reduce by the last item
                          if( $percentage_discount !== false ) {
                              $discount += ($product_prices[ $last ] / 100) * $percentage_discount;
                          } else {
                              $discount += $product_prices[ $last ];
                          }

                          $last--;
                      }
                  }

                  // If there's any discount then return it.
                  if ( !empty( $discount ) ) {
                      return $discount;
                  }
              }

              return 0;
          }

          /**
           * Applies the virtual coupon to the basket.
           *
           * @param  array     $data    Coupon data to apply
           * @param  string    $code    The code to apply
           *                            This will currently match $this->get_discount_code()
           *                            but may change in future.
           *
           * @return array      $data   Coupon data
           */
          function woocommerce_get_shop_coupon_data( $data, $code ) {

              if ( empty( $code ) || empty( WC()->cart ) ) {
                  return $data;
              }

              $discount = $this->get_discount();

              // Fallback checks after fallback checks... If the BOGOF offer isnt
              // active or the code doesn't look right then don't apply the code.
              if ( $this->is_bogof_active() && $this->check_bogof_code( $code ) ) {
                  $data = array(
                      'id' => -1,
                      'code' => $this->get_discount_code(),
                      'description' => 'Automatic BOGOF Promotion',
                      'amount' => $discount,
                      'coupon_amount' => $discount
                  );
              }

              return $data;
          }

          /**
           * Update the label for the promotion if its a BOGOF promotion to
           * make it clear that the coupon was applied automatically. This
           * can prevent any confusion to the customer, hopefully.
           *
           * @param  string     $label      Label to show next to item in basket
           * @param  object     $coupon     The coupon that was applied.
           * @return string     $label      The updated label, if required.
           *
           * @since     0.0.1
           * @version   0.0.1
           */
          function woocommerce_cart_totals_coupon_label( $label, $coupon ) {

              // If the code matches up to what we expect a BOGOF code to look
              // like then we update the label to read automatic promotion
              // instead of the default 'Coupon:'
              if ( $this->check_bogof_code( $coupon->get_code() ) ) {
                  $label = sprintf( __( 'Automatic Promotion: %s', 'woocommerce' ), strtoupper( $coupon->get_code() ) );
              }

              return $label;
          }

          /**
           * ====================================================================================================
           * WOOCOMMERCE CORE FUNCTIONS END
           * ====================================================================================================
           */

    }

    // Initiate the class
    new WC_BOGOF();

// end if class exists
endif;
