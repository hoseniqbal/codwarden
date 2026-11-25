<?php
/**
 * Data: phone list and event log.
 *
 * @package CODWarden
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Two small tables. Phones and IPs are stored only as keyed hashes plus a masked label.
 */
class CODWarden_Store {

	/**
	 * Phones table.
	 *
	 * @return string
	 */
	public static function phones() {
		global $wpdb;
		return $wpdb->prefix . 'codwarden_phones';
	}

	/**
	 * Events table.
	 *
	 * @return string
	 */
	public static function events() {
		global $wpdb;
		return $wpdb->prefix . 'codwarden_events';
	}

	/**
	 * Create or update tables.
	 *
	 * @return void
	 */
	public static function install() {
		global $wpdb;
		require_once ABSPATH . 'wp-admin/includes/upgrade.php';
		$charset = $wpdb->get_charset_collate();
		$phones  = self::phones();
		$events  = self::events();
		dbDelta(
			"CREATE TABLE {$phones} (
			phone_hash char(64) NOT NULL,
			phone_masked varchar(40) NOT NULL DEFAULT '',
			strikes int(10) unsigned NOT NULL DEFAULT 0,
			blocked tinyint(1) NOT NULL DEFAULT 0,
			manual tinyint(1) NOT NULL DEFAULT 0,
			updated_at datetime NOT NULL,
			PRIMARY KEY  (phone_hash),
			KEY blocked (blocked)
		) {$charset};"
		);
		dbDelta(
			"CREATE TABLE {$events} (
			id bigint(20) unsigned NOT NULL AUTO_INCREMENT,
			created_at datetime NOT NULL,
			event varchar(20) NOT NULL,
			reason varchar(32) NOT NULL DEFAULT '',
			channel varchar(20) NOT NULL DEFAULT '',
			phone_hash char(64) NOT NULL DEFAULT '',
			phone_masked varchar(40) NOT NULL DEFAULT '',
			ip_hash char(64) NOT NULL DEFAULT '',
			cart_hash char(32) NOT NULL DEFAULT '',
			PRIMARY KEY  (id),
			KEY event_created (event, created_at),
			KEY phone_created (phone_hash, created_at),
			KEY ip_created (ip_hash, created_at)
		) {$charset};"
		);
		update_option( 'codwarden_db_version', CODWARDEN_DB_VERSION, false );
	}

	/**
	 * Visitor IP (REMOTE_ADDR only; proxies can be trusted with a filter).
	 *
	 * @return string
	 */
	public static function ip() {
		$ip = isset( $_SERVER['REMOTE_ADDR'] ) ? sanitize_text_field( wp_unslash( $_SERVER['REMOTE_ADDR'] ) ) : '';
		/**
		 * Change how the visitor IP is read (for example behind Cloudflare).
		 *
		 * @param string $ip IP address.
		 */
		$ip = (string) apply_filters( 'codwarden_client_ip', $ip );
		return filter_var( $ip, FILTER_VALIDATE_IP ) ? $ip : '';
	}

	/**
	 * Keyed IP hash.
	 *
	 * @param string $ip IP.
	 * @return string
	 */
	public static function ip_hash( $ip ) {
		return '' === $ip ? '' : hash_hmac( 'sha256', 'ip|' . $ip, wp_salt( 'auth' ) );
	}

	/**
	 * Write an event.
	 *
	 * @param string $event        placed | stopped.
	 * @param string $reason       Reason code.
	 * @param string $channel      classic | store_api.
	 * @param string $phone_hash   Phone hash.
	 * @param string $phone_masked Masked phone.
	 * @param string $cart_hash    Cart fingerprint.
	 * @return void
	 */
	public static function log( $event, $reason, $channel, $phone_hash = '', $phone_masked = '', $cart_hash = '' ) {
		global $wpdb;
		// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery -- custom table.
		$wpdb->insert(
			self::events(),
			array(
				'created_at'   => current_time( 'mysql', true ),
				'event'        => substr( sanitize_key( $event ), 0, 20 ),
				'reason'       => substr( sanitize_key( $reason ), 0, 32 ),
				'channel'      => substr( sanitize_key( $channel ), 0, 20 ),
				'phone_hash'   => $phone_hash,
				'phone_masked' => substr( $phone_masked, 0, 40 ),
				'ip_hash'      => self::ip_hash( self::ip() ),
				'cart_hash'    => substr( $cart_hash, 0, 32 ),
			),
			array( '%s', '%s', '%s', '%s', '%s', '%s', '%s', '%s' )
		);
	}

	/**
	 * Count placed COD orders for a phone or IP since a time.
	 *
	 * @param string $column phone_hash | ip_hash.
	 * @param string $hash   Hash.
	 * @param int    $hours  Hours back.
	 * @return int
	 */
	public static function count_placed( $column, $hash, $hours ) {
		global $wpdb;
		if ( '' === $hash || ! in_array( $column, array( 'phone_hash', 'ip_hash' ), true ) ) {
			return 0;
		}
		$since = gmdate( 'Y-m-d H:i:s', time() - (int) $hours * HOUR_IN_SECONDS );
		// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching -- live count.
		return (int) $wpdb->get_var( $wpdb->prepare( 'SELECT COUNT(*) FROM %i WHERE event = %s AND %i = %s AND created_at >= %s', self::events(), 'placed', $column, $hash, $since ) );
	}

	/**
	 * Whether the same phone placed the same cart recently.
	 *
	 * @param string $phone_hash Phone hash.
	 * @param string $cart_hash  Cart fingerprint.
	 * @param int    $minutes    Minutes back.
	 * @return bool
	 */
	public static function is_duplicate( $phone_hash, $cart_hash, $minutes ) {
		global $wpdb;
		if ( '' === $phone_hash || '' === $cart_hash ) {
			return false;
		}
		$since = gmdate( 'Y-m-d H:i:s', time() - (int) $minutes * MINUTE_IN_SECONDS );
		// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching -- live check.
		return (bool) $wpdb->get_var( $wpdb->prepare( 'SELECT id FROM %i WHERE event = %s AND phone_hash = %s AND cart_hash = %s AND created_at >= %s LIMIT 1', self::events(), 'placed', $phone_hash, $cart_hash, $since ) );
	}

	/**
	 * Phone row.
	 *
	 * @param string $hash Phone hash.
	 * @return array|null
	 */
	public static function phone( $hash ) {
		global $wpdb;
		// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching -- live check.
		$row = $wpdb->get_row( $wpdb->prepare( 'SELECT * FROM %i WHERE phone_hash = %s', self::phones(), $hash ), ARRAY_A );
		return is_array( $row ) ? $row : null;
	}

	/**
	 * Whether a phone is blocked from cash on delivery.
	 *
	 * @param string $hash Phone hash.
	 * @return bool
	 */
	public static function is_blocked( $hash ) {
		$row = '' === $hash ? null : self::phone( $hash );
		return $row && (int) $row['blocked'];
	}

	/**
	 * Add a strike; block when the limit is reached.
	 *
	 * @param string $hash   Phone hash.
	 * @param string $masked Masked phone.
	 * @return void
	 */
	public static function strike( $hash, $masked ) {
		global $wpdb;
		$limit = (int) CODWarden_Settings::get( 'strikes_to_block' );
		// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching -- upsert.
		$wpdb->query(
			$wpdb->prepare(
				'INSERT INTO %i (phone_hash, phone_masked, strikes, blocked, manual, updated_at) VALUES (%s, %s, 1, %d, 0, %s)
				ON DUPLICATE KEY UPDATE strikes = strikes + 1, blocked = IF(blocked = 1 OR strikes >= %d, 1, 0), updated_at = VALUES(updated_at)',
				self::phones(),
				$hash,
				$masked,
				$limit <= 1 ? 1 : 0,
				current_time( 'mysql', true ),
				$limit
			)
		);
	}

	/**
	 * Block or unblock by hand.
	 *
	 * @param string $hash    Phone hash.
	 * @param string $masked  Masked phone.
	 * @param bool   $blocked Block or unblock.
	 * @return void
	 */
	public static function set_blocked( $hash, $masked, $blocked ) {
		global $wpdb;
		// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching -- upsert.
		$wpdb->query(
			$wpdb->prepare(
				'INSERT INTO %i (phone_hash, phone_masked, strikes, blocked, manual, updated_at) VALUES (%s, %s, 0, %d, 1, %s)
				ON DUPLICATE KEY UPDATE blocked = VALUES(blocked), manual = 1, strikes = IF(VALUES(blocked) = 0, 0, strikes), updated_at = VALUES(updated_at)',
				self::phones(),
				$hash,
				$masked,
				$blocked ? 1 : 0,
				current_time( 'mysql', true )
			)
		);
	}

	/**
	 * Phones with strikes or blocks.
	 *
	 * @param int $limit Rows.
	 * @return array
	 */
	public static function list_phones( $limit = 200 ) {
		global $wpdb;
		// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching -- admin list.
		$rows = $wpdb->get_results( $wpdb->prepare( 'SELECT * FROM %i WHERE strikes > 0 OR blocked = 1 ORDER BY blocked DESC, updated_at DESC LIMIT %d', self::phones(), (int) $limit ), ARRAY_A );
		return is_array( $rows ) ? $rows : array();
	}

	/**
	 * Count events of a type since N hours.
	 *
	 * @param string $event Event.
	 * @param int    $hours Hours.
	 * @return int
	 */
	public static function count_since( $event, $hours ) {
		global $wpdb;
		$since = gmdate( 'Y-m-d H:i:s', time() - (int) $hours * HOUR_IN_SECONDS );
		// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching -- live count.
		return (int) $wpdb->get_var( $wpdb->prepare( 'SELECT COUNT(*) FROM %i WHERE event = %s AND created_at >= %s', self::events(), $event, $since ) );
	}

	/**
	 * Number of blocked phones.
	 *
	 * @return int
	 */
	public static function blocked_count() {
		global $wpdb;
		// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching -- live count.
		return (int) $wpdb->get_var( $wpdb->prepare( 'SELECT COUNT(*) FROM %i WHERE blocked = %d', self::phones(), 1 ) );
	}

	/**
	 * Recent stopped attempts.
	 *
	 * @param int $limit Rows.
	 * @return array
	 */
	public static function recent_stopped( $limit = 20 ) {
		global $wpdb;
		// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching -- live list.
		$rows = $wpdb->get_results( $wpdb->prepare( 'SELECT * FROM %i WHERE event = %s ORDER BY id DESC LIMIT %d', self::events(), 'stopped', (int) $limit ), ARRAY_A );
		return is_array( $rows ) ? $rows : array();
	}

	/**
	 * Delete old events.
	 *
	 * @return void
	 */
	public static function purge() {
		global $wpdb;
		$until = gmdate( 'Y-m-d H:i:s', time() - (int) CODWarden_Settings::get( 'retention_days' ) * DAY_IN_SECONDS );
		// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching -- cleanup.
		$wpdb->query( $wpdb->prepare( 'DELETE FROM %i WHERE created_at < %s', self::events(), $until ) );
	}
}
