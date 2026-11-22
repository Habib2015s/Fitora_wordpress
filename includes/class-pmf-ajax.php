<?php
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class PMF_Ajax {

	public static function init() {
		foreach ( array( 'options', 'search', 'code', 'request', 'check' ) as $a ) {
			add_action( 'wp_ajax_pmf_' . $a, array( __CLASS__, $a ) );
			add_action( 'wp_ajax_nopriv_pmf_' . $a, array( __CLASS__, $a ) );
		}
		add_action( 'wp_ajax_pmf_admin_devices', array( __CLASS__, 'admin_devices' ) );
	}

	/* ---------- ورودی‌ها ---------- */

	protected static function post( $key ) {
		return ( isset( $_POST[ $key ] ) && is_scalar( $_POST[ $key ] ) ) ? sanitize_text_field( wp_unslash( (string) $_POST[ $key ] ) ) : ''; // phpcs:ignore WordPress.Security.NonceVerification
	}

	protected static function values() {
		$out = array();
		if ( isset( $_POST['values'] ) && is_array( $_POST['values'] ) ) { // phpcs:ignore WordPress.Security.NonceVerification
			foreach ( wp_unslash( $_POST['values'] ) as $v ) { // phpcs:ignore WordPress.Security.NonceVerification,WordPress.Security.ValidatedSanitizedInput
				if ( ! is_scalar( $v ) ) {
					break;
				}
				$v = trim( sanitize_text_field( (string) $v ) );
				if ( '' === $v ) {
					break;
				}
				$out[] = $v;
			}
		}
		return array_slice( $out, 0, PMF_DB::MAX_LEVELS );
	}

	protected static function form() {
		$f = PMF_DB::form( absint( self::post( 'form' ) ) );
		if ( ! $f ) {
			wp_send_json_error( array( 'message' => 'فرم پیدا نشد.' ) );
		}
		return $f;
	}

	/* ---------- گزینه‌های سطح بعد ---------- */

	public static function options() {
		$form   = self::form();
		$values = self::values();
		wp_send_json_success( array( 'options' => PMF_DB::next_options( $form->id, $values ) ) );
	}

	/* ---------- جستجو با فیلتر ---------- */

	public static function search() {
		$form   = self::form();
		$values = self::values();
		if ( ! $values ) {
			wp_send_json_error( array( 'message' => 'ابتدا دستگاه را انتخاب کنید.' ) );
		}
		$found = PMF_DB::find_by_device( $form->id, $values );
		$res   = PMF_Frontend::render_products( $found );

		if ( 0 === $res['count'] ) {
			PMF_DB::log_missed( $form->id, 'filter', implode( ' › ', $values ) );
			wp_send_json_success(
				array(
					'count' => 0,
					'query' => implode( ' › ', $values ),
					'html'  => self::empty_html(),
				)
			);
		}
		wp_send_json_success(
			array(
				'count' => $res['count'],
				'query' => implode( ' › ', $values ),
				'html'  => self::results_html( $res, 'محصول سازگار با دستگاه شما' ),
			)
		);
	}

	/* ---------- جستجو با کد ---------- */

	public static function code() {
		$form = self::form();
		$raw  = self::post( 'code' );
		if ( strlen( PMF_DB::norm_code( $raw ) ) < 2 ) {
			wp_send_json_error( array( 'message' => 'کد قطعه را وارد کنید.' ) );
		}
		$look       = PMF_DB::code_lookup( $raw );
		$main_found = array();
		foreach ( $look['main'] as $pid ) {
			$main_found[ $pid ] = array( 'type' => 'match', 'note' => '', 'rank' => 0 );
		}
		$alt_found = array();
		foreach ( $look['alts'] as $pid ) {
			$alt_found[ $pid ] = array( 'type' => 'alt', 'note' => '', 'rank' => 2 );
		}
		$m_res = PMF_Frontend::render_products( $main_found, 12 );
		$a_res = PMF_Frontend::render_products( $alt_found, 12 );

		if ( 0 === $m_res['count'] && 0 === $a_res['count'] ) {
			PMF_DB::log_missed( $form->id, 'code', $raw );
			wp_send_json_success( array( 'count' => 0, 'query' => $raw, 'html' => self::empty_html() ) );
		}
		$html = '';
		if ( $m_res['count'] ) {
			$html .= self::results_html( $m_res, 'محصول مطابق با کد' );
		}
		if ( $a_res['count'] ) {
			$html .= '<h4 class="pmf-sub">جایگزین‌های این قطعه</h4>' . self::results_html( $a_res, '', false );
		}
		wp_send_json_success( array( 'count' => $m_res['count'] + $a_res['count'], 'query' => $raw, 'html' => $html ) );
	}

	/* ---------- بررسی سازگاری در صفحهٔ محصول ---------- */

	public static function check() {
		$form   = self::form();
		$values = self::values();
		$pid    = absint( self::post( 'product' ) );
		if ( ! $values || ! $pid ) {
			wp_send_json_error();
		}
		$found = PMF_DB::find_by_device( $form->id, $values );
		if ( isset( $found[ $pid ] ) ) {
			wp_send_json_success( array( 'status' => $found[ $pid ]['type'], 'note' => $found[ $pid ]['note'] ) );
		}
		wp_send_json_success( array( 'status' => 'none', 'note' => '' ) );
	}

	/* ---------- فرم درخواست محصول ---------- */

	public static function request() {
		if ( '' !== self::post( 'website' ) ) { // honeypot
			wp_send_json_success( array( 'message' => 'ok' ) );
		}
		$loaded = (int) self::post( 'loaded' );
		if ( $loaded && ( time() - $loaded ) < 3 ) {
			wp_send_json_error( array( 'message' => 'لطفاً چند لحظه صبر کنید و دوباره تلاش کنید.' ) );
		}
		$ip  = isset( $_SERVER['REMOTE_ADDR'] ) ? sanitize_text_field( wp_unslash( $_SERVER['REMOTE_ADDR'] ) ) : 'x';
		$key = 'pmf_rl_' . md5( $ip );
		$n   = (int) get_transient( $key );
		if ( $n >= 8 ) {
			wp_send_json_error( array( 'message' => 'تعداد درخواست‌های شما زیاد بوده است. بعداً دوباره تلاش کنید.' ) );
		}

		$name  = self::post( 'name' );
		$email = sanitize_email( self::post( 'email' ) );
		$phone = '' !== self::post( 'phone' ) ? PMF_SMS::normalize_phone( self::post( 'phone' ) ) : '';
		if ( '' !== $phone && ! preg_match( '/^(09[0-9]{9}|\+?[0-9]{7,15})$/', $phone ) ) {
			wp_send_json_error( array( 'message' => 'شمارهٔ موبایل معتبر نیست.' ) );
		}
		if ( ! $email && ! $phone ) {
			wp_send_json_error( array( 'message' => 'ایمیل خود را وارد کنید.' ) );
		}
		if ( '' !== self::post( 'email' ) && ! is_email( $email ) ) {
			wp_send_json_error( array( 'message' => 'ایمیل معتبر وارد کنید.' ) );
		}
		$kind = self::post( 'kind' );
		$kind = in_array( $kind, array( 'search', 'code', 'product' ), true ) ? $kind : 'search';
		$pid  = absint( self::post( 'product' ) );
		$vals = self::values();
		$query = self::post( 'query' );
		if ( 'product' === $kind ) {
			$p = $pid ? wc_get_product( $pid ) : null;
			if ( ! $p ) {
				wp_send_json_error( array( 'message' => 'محصول پیدا نشد.' ) );
			}
			$query = $p->get_name();
		}
		$details = isset( $_POST['details'] ) ? sanitize_textarea_field( wp_unslash( $_POST['details'] ) ) : ''; // phpcs:ignore WordPress.Security.NonceVerification

		global $wpdb;
		$token = wp_generate_password( 24, false, false );
		$wpdb->insert(
			PMF_DB::t( 'requests' ),
			array(
				'form_id'      => absint( self::post( 'form' ) ),
				'product_id'   => $pid,
				'kind'         => $kind,
				'name'         => mb_substr( $name, 0, 150 ),
				'phone'        => $phone,
					'email'        => $email,
				'details'      => mb_substr( $details, 0, 2000 ),
				'query'        => mb_substr( $query, 0, 250 ),
				'vals'         => 'search' === $kind ? wp_json_encode( $vals ) : '',
				'token'        => $token,
				'status'       => 'new',
				'sms_status'   => '',
				'notified_ids' => '',
				'created_at'   => current_time( 'mysql' ),
			),
			array( '%d', '%d', '%s', '%s', '%s', '%s', '%s', '%s', '%s', '%s', '%s', '%s', '%s', '%s' )
		);
		set_transient( $key, $n + 1, HOUR_IN_SECONDS );
		$rid = (int) $wpdb->insert_id;
		do_action( 'pmf_request_created', $rid );

		$to = get_option( 'admin_email' );
		if ( $to ) {
			wp_mail( $to, 'درخواست جدید در ' . wp_specialchars_decode( get_bloginfo( 'name' ), ENT_QUOTES ), "نام: $name\nایمیل: $email\nتماس: $phone\nدرخواست: $query\nتوضیحات: $details\n\n" . admin_url( 'admin.php?page=partmatch-requests' ) );
		}

		$msg = 'ok';
		wp_send_json_success( array( 'message' => $msg ) );
	}

	/* ---------- ادمین: لیست مشخصات برای تب محصول ---------- */

	public static function admin_devices() {
		check_ajax_referer( 'pmf_admin', 'nonce' );
		if ( ! current_user_can( 'edit_products' ) ) {
			wp_send_json_error();
		}
		$form = self::form();
		$rows = PMF_DB::devices_by_prefix( $form->id, self::values(), 1000 );
		$out  = array();
		foreach ( $rows as $r ) {
			$out[] = array( 'id' => (int) $r->id, 'label' => PMF_DB::label( $r ), 'form_id' => (int) $form->id );
		}
		wp_send_json_success( array( 'devices' => $out ) );
	}

	/* ---------- HTML مشترک ---------- */

	protected static function results_html( $res, $caption, $with_more = true ) {
		$h = '';
		if ( '' !== $caption ) {
			$h .= '<div class="pmf-count"><strong>' . esc_html( number_format_i18n( $res['total'] ) ) . '</strong> ' . esc_html( $caption ) . '</div>';
		}
		$h .= '<div class="pmf-grid">' . $res['html'] . '</div>';
		if ( $with_more ) {
			$h .= '<p class="pmf-more"><a href="#" class="pmf-open-req">محصول مورد نظرتان را پیدا نکردید؟ درخواست ثبت کنید</a></p>';
		}
		return $h;
	}

	protected static function empty_html() {
		return '<div class="pmf-empty"><p>' . esc_html( PMF_DB::setting( 'empty_text' ) ) . '</p><button type="button" class="pmf-btn pmf-open-req">ثبت درخواست محصول</button></div>';
	}
}
