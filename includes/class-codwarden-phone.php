<?php
/**
 * Phone number checks.
 *
 * @package CODWarden
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Normalises and validates mobile numbers for common cash on delivery markets.
 */
class CODWarden_Phone {

	/**
	 * Country rules: calling code and the national mobile pattern (without leading zero).
	 *
	 * @return array
	 */
	public static function rules() {
		return array(
			'BD' => array( '880', '/^1[3-9]\d{8}$/' ),
			'IN' => array( '91', '/^[6-9]\d{9}$/' ),
			'PK' => array( '92', '/^3\d{9}$/' ),
			'AE' => array( '971', '/^5\d{8}$/' ),
			'SA' => array( '966', '/^5\d{8}$/' ),
			'EG' => array( '20', '/^1[0125]\d{8}$/' ),
			'MA' => array( '212', '/^[67]\d{8}$/' ),
			'NG' => array( '234', '/^[789][01]\d{8}$/' ),
			'KE' => array( '254', '/^[17]\d{8}$/' ),
			'NP' => array( '977', '/^9[78]\d{8}$/' ),
			'LK' => array( '94', '/^7\d{8}$/' ),
		);
	}

	/**
	 * Normalise to calling code + national number, digits only.
	 *
	 * @param string $phone   Raw phone.
	 * @param string $country Country code.
	 * @return string Empty when it cannot be a valid number.
	 */
	public static function normalize( $phone, $country ) {
		$digits = preg_replace( '/\D+/', '', (string) $phone );
		if ( '' === $digits ) {
			return '';
		}
		$country = strtoupper( (string) $country );
		$rules   = self::rules();

		if ( isset( $rules[ $country ] ) ) {
			list( $code, $pattern ) = $rules[ $country ];
			$candidates             = array();
			if ( 0 === strpos( $digits, '00' . $code ) ) {
				$candidates[] = substr( $digits, 2 + strlen( $code ) );
			}
			if ( 0 === strpos( $digits, $code ) ) {
				$candidates[] = substr( $digits, strlen( $code ) );
			}
			$candidates[] = $digits;
			foreach ( $candidates as $national ) {
				$national = ltrim( $national, '0' );
				if ( preg_match( $pattern, $national ) ) {
					return $code . $national;
				}
			}
			return '';
		}

		$digits = ltrim( $digits, '0' );
		return ( strlen( $digits ) >= 7 && strlen( $digits ) <= 15 ) ? $digits : '';
	}

	/**
	 * Keyed hash of a normalised number.
	 *
	 * @param string $normalized Normalised phone.
	 * @return string
	 */
	public static function hash( $normalized ) {
		return hash_hmac( 'sha256', 'phone|' . $normalized, wp_salt( 'auth' ) );
	}

	/**
	 * +8801712•••678 style label.
	 *
	 * @param string $normalized Normalised phone.
	 * @return string
	 */
	public static function mask( $normalized ) {
		$len = strlen( $normalized );
		if ( $len < 7 ) {
			return '+' . $normalized;
		}
		return '+' . substr( $normalized, 0, $len - 6 ) . 'xxx' . substr( $normalized, -3 );
	}
}
