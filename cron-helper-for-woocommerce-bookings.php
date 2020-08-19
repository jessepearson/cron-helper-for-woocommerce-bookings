<?php
/**
 * Plugin Name: Cron Helper For WooCommerce Bookings
 * Plugin URI: https://github.com/jessepearson/cron-helper-for-woocommerce-bookings
 * Description: Makes sure In Cart bookings are removed and past bookings are Completed.
 * Author: Jesse Pearson
 * Author URI: https://jessepearson.net
 * Text Domain: cron-helper-for-wcb
 * Version: 1.0.1
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

if ( ! class_exists( 'Cron_Helper_For_WCB' ) ) {

	/**
	 * Main class.
	 *
	 * @since    1.0.0
	 * @version  1.0.1
	 */
	class Cron_Helper_For_WCB {

		/**
		 * Init and hook in the integration.
		 *
		 * @since    1.0.0
		 * @version  1.0.0
		 */
		public function __construct() {
			add_action( 'init', [ $this, 'maybe_schedule_helper' ] );
			add_action( 'cron-helper-for-woocommerce-bookings', [ $this, 'remove_expired_in_cart' ] );
			add_action( 'cron-helper-for-woocommerce-bookings', [ $this, 'maybe_complete_bookings' ] );
		}

		/**
		 * To log.
		 *
		 * @since    1.0.0
		 * @version  1.0.0
		 * @param    mixed $log What you want to log.
		 */
		static function log( $log ) {
			$logger = wc_get_logger();
			$logger->debug( print_r( $log, true ), [ 'source' => 'cron-helper-for-wcb' ] );
		}

		/**
		 * failed orders, too
		 * woocommerce_bookings_failed_order_expire_scheduled_time_stamp
		 */

		/**
		 * Maybe schedule the helper to run via Action Scheduler.
		 *
		 * @since    1.0.0
		 * @version  1.0.0
		 */
		public function maybe_schedule_helper() {
			// Set our hook and group for AS. 
			$hook  = 'cron-helper-for-woocommerce-bookings';
			$group = 'cron-helper-wcb';

			// Check for current pending scheduled actions.
			$actions = as_get_scheduled_actions( [
					'hook'   => $hook,
					'status' => ActionScheduler_Store::STATUS_PENDING,
					'group'  => $group,
			] );

			// If we somehow have more than 1, remove them all.
			if ( count( $actions ) > 1 ) {
				as_unschedule_all_actions( $hook );
			}

			// If there are none set, add one.
			if ( ! as_next_scheduled_action( $hook ) ) {
				as_unschedule_all_actions( $hook );
				as_schedule_recurring_action( time(), 300, $hook, [], $group );
			}
		}

		/**
		 * This simply calls a function already in Bookings to remove any expired In Cart bookings.
		 *
		 * @since    1.0.0
		 * @version  1.0.0
		 */
		public function remove_expired_in_cart() {
			// If Bookings isn't active, exit.
			if ( ! class_exists( 'WC_Bookings' ) ) {
				return;
			}

			WC_Bookings_Tools::remove_in_cart_bookings( 'expired' );
		}

		/**
		 * This will set Paid and Confirmed bookings to Completed if their time has passed.
		 *
		 * @since    1.0.0
		 * @version  1.0.1
		 */
		public function maybe_complete_bookings() {
			// If Bookings isn't active, exit.
			if ( ! class_exists( 'WC_Bookings' ) ) {
				return;
			}

			// Get any bookings that have completed before now.
			$booking_ids = WC_Booking_Data_Store::get_booking_ids_by( [
				'status'       => get_wc_booking_statuses( 'scheduled' ),
				'date_before'  => current_time( 'timestamp' ),
			] );

			if ( count( $booking_ids ) > 0 ) {
				// Get the Cron manager object and mark the bookings as complete.
				$cron_manager = new WC_Booking_Cron_Manager();
				foreach( $booking_ids as $id ) {
					$cron_manager->maybe_mark_booking_complete( $id );
				}
			}
		}
	}

	new Cron_Helper_For_WCB();
}