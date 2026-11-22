<?php
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/** تبدیل تاریخ میلادی به شمسی (بدون نیاز به افزونهٔ دیگر). */
class PMF_Date {

	protected static $months = array( '', 'فروردین', 'اردیبهشت', 'خرداد', 'تیر', 'مرداد', 'شهریور', 'مهر', 'آبان', 'آذر', 'دی', 'بهمن', 'اسفند' );

	public static function g2j( $gy, $gm, $gd ) {
		$g_d_m = array( 0, 31, 59, 90, 120, 151, 181, 212, 243, 273, 304, 334 );
		$gy2   = ( $gm > 2 ) ? ( $gy + 1 ) : $gy;
		$days  = 355666 + ( 365 * $gy ) + (int) ( ( $gy2 + 3 ) / 4 ) - (int) ( ( $gy2 + 99 ) / 100 ) + (int) ( ( $gy2 + 399 ) / 400 ) + $gd + $g_d_m[ $gm - 1 ];
		$jy    = -1595 + ( 33 * (int) ( $days / 12053 ) );
		$days %= 12053;
		$jy   += 4 * (int) ( $days / 1461 );
		$days %= 1461;
		if ( $days > 365 ) {
			$jy  += (int) ( ( $days - 1 ) / 365 );
			$days = ( $days - 1 ) % 365;
		}
		if ( $days < 186 ) {
			$jm = 1 + (int) ( $days / 31 );
			$jd = 1 + ( $days % 31 );
		} else {
			$jm = 7 + (int) ( ( $days - 186 ) / 30 );
			$jd = 1 + ( ( $days - 186 ) % 30 );
		}
		return array( $jy, $jm, $jd );
	}

	public static function fa_digits( $s ) {
		return strtr( (string) $s, array( '0' => '۰', '1' => '۱', '2' => '۲', '3' => '۳', '4' => '۴', '5' => '۵', '6' => '۶', '7' => '۷', '8' => '۸', '9' => '۹' ) );
	}

	/**
	 * @param string $mysql تاریخ ذخیره‌شده (به وقت سایت)
	 * @param bool   $time  نمایش ساعت
	 * @param bool   $words ماه به حروف (۲۴ آبان ۱۴۰۵) یا عددی (۱۴۰۵/۰۸/۲۴)
	 */
	public static function format( $mysql, $time = true, $words = true ) {
		if ( empty( $mysql ) || '0000-00-00 00:00:00' === $mysql ) {
			return '—';
		}
		$ts = strtotime( $mysql . ' UTC' ); // مؤلفه‌های زمان محلی همان‌طور که ذخیره شده‌اند
		if ( false === $ts ) {
			return '—';
		}
		list( $jy, $jm, $jd ) = self::g2j( (int) gmdate( 'Y', $ts ), (int) gmdate( 'n', $ts ), (int) gmdate( 'j', $ts ) );
		$out = $words
			? $jd . ' ' . self::$months[ $jm ] . ' ' . $jy
			: sprintf( '%04d/%02d/%02d', $jy, $jm, $jd );
		if ( $time ) {
			$out .= ' — ' . gmdate( 'H:i', $ts );
		}
		return self::fa_digits( $out );
	}
}
