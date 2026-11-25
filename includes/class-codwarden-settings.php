<?php
/**
 * Settings.
 *
 * @package CODWarden
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * All settings in one option.
 */
class CODWarden_Settings {

	const OPTION = 'codwarden_settings';

	/**
	 * Cache.
	 *
	 * @var array|null
	 */
	private static $cache = null;

	/**
	 * Defaults.
	 *
	 * @return array
	 */
	public static function defaults() {
		return array(
			'enabled'          => 'yes',
			'phone_check'      => 'yes',
			'limit_enabled'    => 'yes',
			'limit_orders'     => 2,
			'limit_hours'      => 24,
			'duplicate_guard'  => 'yes',
			'duplicate_mins'   => 30,
			'strikes_enabled'  => 'yes',
			'strikes_to_block' => 2,
			'max_total'        => '0',
			'new_max_total'    => '0',
			'trust_customers'  => 'yes',
			'message'          => '',
			'retention_days'   => 90,
			'delete_data'      => 'no',
		);
	}

	/**
	 * All values.
	 *
	 * @return array
	 */
	public static function all() {
		if ( null === self::$cache ) {
			$saved       = get_option( self::OPTION, array() );
			self::$cache = wp_parse_args( is_array( $saved ) ? $saved : array(), self::defaults() );
		}
		return self::$cache;
	}

	/**
	 * One value.
	 *
	 * @param string $key Key.
	 * @return mixed
	 */
	public static function get( $key ) {
		$all = self::all();
		return isset( $all[ $key ] ) ? $all[ $key ] : null;
	}

	/**
	 * Yes/no check.
	 *
	 * @param string $key Key.
	 * @return bool
	 */
	public static function on( $key ) {
		return 'yes' === self::get( $key );
	}

	/**
	 * Forget the cache (after saving).
	 *
	 * @return void
	 */
	public static function flush() {
		self::$cache = null;
	}

	/**
	 * Sanitize.
	 *
	 * @param mixed $in Raw.
	 * @return array
	 */
	public static function sanitize( $in ) {
		$d   = self::defaults();
		$in  = is_array( $in ) ? $in : array();
		$out = array();
		foreach ( array( 'enabled', 'phone_check', 'limit_enabled', 'duplicate_guard', 'strikes_enabled', 'trust_customers', 'delete_data' ) as $key ) {
			$out[ $key ] = ( isset( $in[ $key ] ) && 'yes' === $in[ $key ] ) ? 'yes' : 'no';
		}
		$ints = array(
			'limit_orders'     => array( 1, 50 ),
			'limit_hours'      => array( 1, 720 ),
			'duplicate_mins'   => array( 1, 1440 ),
			'strikes_to_block' => array( 1, 10 ),
			'retention_days'   => array( 7, 730 ),
		);
		foreach ( $ints as $key => $range ) {
			$val         = isset( $in[ $key ] ) ? absint( $in[ $key ] ) : $d[ $key ];
			$out[ $key ] = min( $range[1], max( $range[0], $val ) );
		}
		foreach ( array( 'max_total', 'new_max_total' ) as $key ) {
			$val         = isset( $in[ $key ] ) ? (float) str_replace( ',', '.', (string) $in[ $key ] ) : 0;
			$out[ $key ] = (string) round( max( 0, min( 100000000, $val ) ), 2 );
		}
		$out['message'] = isset( $in['message'] ) ? sanitize_text_field( wp_unslash( $in['message'] ) ) : '';
		self::$cache    = null;
		return $out;
	}
}
