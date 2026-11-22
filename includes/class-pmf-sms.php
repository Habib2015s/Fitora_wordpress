<?php
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/** ارسال پیامک؛ هر سرویس‌دهنده یک «درایور» کوچک است و قابل‌افزودن با فیلتر pmf_sms_send. */
class PMF_SMS {

	public static function providers() {
		return array(
			'none'         => 'غیرفعال',
			'log'          => 'حالت آزمایشی (فقط ثبت می‌شود، پیامک نمی‌رود)',
			'melipayamak'  => 'ملی‌پیامک',
			'kavenegar'    => 'کاوه‌نگار',
			'custom'       => 'سرویس دلخواه (از طریق آدرس API هر پنل پیامکی)',
		);
	}

	public static function normalize_phone( $p ) {
		$p = preg_replace( '/[^0-9+]/', '', PMF_DB::digits( $p ) );
		$p = preg_replace( '/^(\+98|0098|98)/', '0', $p );
		if ( preg_match( '/^9[0-9]{9}$/', $p ) ) {
			$p = '0' . $p;
		}
		return $p;
	}

	public static function enabled() {
		return 'none' !== PMF_DB::setting( 'sms_provider' );
	}

	/** @return array( bool $ok, string $message ) */
	public static function send( $phone, $text ) {
		$s        = PMF_DB::settings();
		$provider = $s['sms_provider'];
		$phone    = self::normalize_phone( $phone );

		$pre = apply_filters( 'pmf_sms_send', null, $phone, $text, $s );
		if ( is_array( $pre ) ) {
			return $pre;
		}
		if ( 'none' === $provider ) {
			return array( false, 'سرویس پیامک در تنظیمات فعال نشده است.' );
		}
		if ( 'log' === $provider ) {
			return array( true, 'حالت آزمایشی (ارسال واقعی انجام نشد)' );
		}
		if ( ! preg_match( '/^09[0-9]{9}$/', $phone ) ) {
			return array( false, 'شمارهٔ موبایل نامعتبر است.' );
		}

		if ( 'melipayamak' === $provider ) {
			$r = wp_remote_post(
				'https://rest.payamak-panel.com/api/SendSMS/SendSMS',
				array(
					'timeout' => 15,
					'body'    => array(
						'username' => $s['sms_user'],
						'password' => $s['sms_pass'],
						'to'       => $phone,
						'from'     => $s['sms_sender'],
						'text'     => $text,
						'isFlash'  => 'false',
					),
				)
			);
			if ( is_wp_error( $r ) ) {
				return array( false, $r->get_error_message() );
			}
			$j = json_decode( wp_remote_retrieve_body( $r ), true );
			if ( is_array( $j ) && isset( $j['RetStatus'] ) && 1 === (int) $j['RetStatus'] ) {
				return array( true, 'ارسال شد' );
			}
			return array( false, is_array( $j ) && ! empty( $j['StrRetStatus'] ) ? (string) $j['StrRetStatus'] : 'پاسخ نامعتبر از ملی‌پیامک' );
		}

		if ( 'kavenegar' === $provider ) {
			$body = array( 'receptor' => $phone, 'message' => $text );
			if ( '' !== $s['sms_sender'] ) {
				$body['sender'] = $s['sms_sender'];
			}
			$r = wp_remote_post( 'https://api.kavenegar.com/v1/' . rawurlencode( $s['sms_key'] ) . '/sms/send.json', array( 'timeout' => 15, 'body' => $body ) );
			if ( is_wp_error( $r ) ) {
				return array( false, $r->get_error_message() );
			}
			$j = json_decode( wp_remote_retrieve_body( $r ), true );
			if ( is_array( $j ) && isset( $j['return']['status'] ) && 200 === (int) $j['return']['status'] ) {
				return array( true, 'ارسال شد' );
			}
			return array( false, is_array( $j ) && ! empty( $j['return']['message'] ) ? (string) $j['return']['message'] : 'پاسخ نامعتبر از کاوه‌نگار' );
		}

		if ( 'custom' === $provider ) {
			$url = trim( (string) $s['sms_url'] );
			if ( ! preg_match( '#^https?://#i', $url ) ) {
				return array( false, 'آدرس API در تنظیمات وارد نشده است.' );
			}
			$rep  = array( '{phone}' => $phone, '{text}' => $text, '{sender}' => $s['sms_sender'], '{user}' => $s['sms_user'], '{pass}' => $s['sms_pass'], '{key}' => $s['sms_key'] );
			$tpl  = trim( (string) $s['sms_body'] );
			$json = ( '' !== $tpl && '{' === $tpl[0] );
			if ( $json ) {
				$enc = array();
				foreach ( $rep as $k => $v ) {
					$enc[ $k ] = trim( wp_json_encode( (string) $v ), '"' );
				}
				$body = strtr( $tpl, $enc );
			} else {
				$enc = array();
				foreach ( $rep as $k => $v ) {
					$enc[ $k ] = rawurlencode( (string) $v );
				}
				$body = strtr( $tpl, $enc );
			}
			$url = strtr( $url, array_map( 'rawurlencode', $rep ) );
			$args = array( 'timeout' => 15 );
			if ( '' === $body ) {
				$r = wp_remote_get( $url, $args );
			} else {
				$args['body']    = $body;
				$args['headers'] = array( 'Content-Type' => $json ? 'application/json' : 'application/x-www-form-urlencoded' );
				$r               = wp_remote_post( $url, $args );
			}
			if ( is_wp_error( $r ) ) {
				return array( false, $r->get_error_message() );
			}
			$code = (int) wp_remote_retrieve_response_code( $r );
			$resp = (string) wp_remote_retrieve_body( $r );
			$need = trim( (string) $s['sms_ok'] );
			$ok   = $code >= 200 && $code < 300 && ( '' === $need || false !== stripos( $resp, $need ) );
			$snip = function_exists( 'mb_substr' ) ? mb_substr( wp_strip_all_tags( $resp ), 0, 120 ) : substr( $resp, 0, 120 );
			return array( $ok, ( $ok ? 'ارسال شد' : 'ناموفق' ) . ' (HTTP ' . $code . ') ' . $snip );
		}

		return array( false, 'سرویس‌دهندهٔ ناشناخته' );
	}
}
