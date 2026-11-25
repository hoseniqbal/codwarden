<?php
/**
 * Bootstrap.
 *
 * @package CODWarden
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Wires everything together.
 */
class CODWarden_Plugin {

	const CRON = 'codwarden_daily';

	/**
	 * Runs on plugins_loaded.
	 *
	 * @return void
	 */
	public static function init() {
		if ( ! class_exists( 'WooCommerce' ) ) {
			return;
		}
		if ( get_option( 'codwarden_db_version' ) !== CODWARDEN_DB_VERSION ) {
			CODWarden_Store::install();
		}
		add_action( 'init', array( __CLASS__, 'register_status' ) );
		add_filter( 'wc_order_statuses', array( __CLASS__, 'add_status' ) );
		add_action( self::CRON, array( 'CODWarden_Store', 'purge' ) );
		CODWarden_Guard::init();
		if ( is_admin() ) {
			CODWarden_Admin::init();
		}
	}

	/**
	 * "Refused" order status for parcels the customer would not accept.
	 *
	 * @return void
	 */
	public static function register_status() {
		register_post_status(
			'wc-refused',
			array(
				'label'                     => _x( 'Refused', 'Order status', 'codwarden' ),
				'public'                    => false,
				'exclude_from_search'       => false,
				'show_in_admin_all_list'    => true,
				'show_in_admin_status_list' => true,
				/* translators: %s: number of orders */
				'label_count'               => _n_noop( 'Refused <span class="count">(%s)</span>', 'Refused <span class="count">(%s)</span>', 'codwarden' ),
			)
		);
	}

	/**
	 * Add Refused after Failed in the status list.
	 *
	 * @param array $statuses Statuses.
	 * @return array
	 */
	public static function add_status( $statuses ) {
		$out = array();
		foreach ( $statuses as $key => $label ) {
			$out[ $key ] = $label;
			if ( 'wc-failed' === $key ) {
				$out['wc-refused'] = _x( 'Refused', 'Order status', 'codwarden' );
			}
		}
		if ( ! isset( $out['wc-refused'] ) ) {
			$out['wc-refused'] = _x( 'Refused', 'Order status', 'codwarden' );
		}
		return $out;
	}

	/**
	 * Activation.
	 *
	 * @return void
	 */
	public static function activate() {
		CODWarden_Store::install();
		if ( ! wp_next_scheduled( self::CRON ) ) {
			wp_schedule_event( time() + HOUR_IN_SECONDS, 'daily', self::CRON );
		}
	}

	/**
	 * Deactivation.
	 *
	 * @return void
	 */
	public static function deactivate() {
		wp_clear_scheduled_hook( self::CRON );
	}
}
