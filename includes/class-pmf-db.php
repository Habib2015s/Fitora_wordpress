<?php
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class PMF_DB {

	const MAX_LEVELS = 5;

	public static function t( $name ) {
		global $wpdb;
		return $wpdb->prefix . 'pmf_' . $name;
	}

	/* ---------- تنظیمات ---------- */

	public static function defaults() {
		return array(
			'btn_text'    => 'نمایش محصولات سازگار',
			'empty_text'  => 'متأسفانه محصولی با این مشخصات در فروشگاه ثبت نشده است.',
			'per_page'    => 24,
			'hide_oos'    => 0,
			'product_tab' => 1,
			'garage'      => 1,
			'primary'     => '#1d4ed8',
			'delete_data' => 0,
			'notify_product' => 1,
			'catalog_days' => 14,
			'email_on' => 1,
			'sms_url' => '',
			'sms_body' => '',
			'sms_ok' => '',
			'email_subject' => 'محصول مورد نظر شما در {site} موجود شد',
			'catalog_title' => 'تازه‌های موجودشده',
			'consent'     => 'با ثبت درخواست، هنگام موجود شدن کالا یک پیامک دریافت می‌کنید.',
			'sms_provider' => 'none',
			'sms_user'    => '',
			'sms_pass'    => '',
			'sms_key'     => '',
			'sms_sender'  => '',
			'sms_template' => "{name}، محصول مورد نظر شما در {site} موجود شد.\nمشاهدهٔ لیست و دانلود PDF:\n{link}",
		);
	}

	public static function settings() {
		$o = get_option( 'pmf_settings', array() );
		if ( ! is_array( $o ) ) {
			$o = array();
		}
		return wp_parse_args( $o, self::defaults() );
	}

	public static function setting( $key ) {
		$s = self::settings();
		return isset( $s[ $key ] ) ? $s[ $key ] : null;
	}

	/* ---------- ابزارهای متنی ---------- */

	public static function digits( $s ) {
		return strtr(
			(string) $s,
			array(
				'۰' => '0', '۱' => '1', '۲' => '2', '۳' => '3', '۴' => '4',
				'۵' => '5', '۶' => '6', '۷' => '7', '۸' => '8', '۹' => '9',
				'٠' => '0', '١' => '1', '٢' => '2', '٣' => '3', '٤' => '4',
				'٥' => '5', '٦' => '6', '٧' => '7', '٨' => '8', '٩' => '9',
			)
		);
	}

	public static function lc( $s ) {
		return function_exists( 'mb_strtolower' ) ? mb_strtolower( $s, 'UTF-8' ) : strtolower( $s );
	}

	/** نرمال‌سازی کد قطعه برای مقایسه: ارقام فارسی، حروف کوچک، بدون فاصله و خط تیره. */
	public static function norm_code( $s ) {
		$s = self::lc( self::digits( trim( (string) $s ) ) );
		return preg_replace( '/[\s\-_\.\/]+/u', '', $s );
	}

	/* ---------- فرم‌ها ---------- */

	protected static function decode_fields( $json ) {
		$a = json_decode( (string) $json, true );
		if ( ! is_array( $a ) ) {
			return array();
		}
		return array_values( array_filter( array_map( 'strval', $a ), 'strlen' ) );
	}

	public static function forms() {
		global $wpdb;
		$rows = $wpdb->get_results( 'SELECT * FROM ' . self::t( 'forms' ) . ' ORDER BY id DESC' );
		$rows = $rows ? $rows : array();
		foreach ( $rows as $r ) {
			$r->fields = self::decode_fields( $r->fields );
		}
		return $rows;
	}

	public static function form( $id ) {
		global $wpdb;
		$r = $wpdb->get_row( $wpdb->prepare( 'SELECT * FROM ' . self::t( 'forms' ) . ' WHERE id=%d', $id ) );
		if ( $r ) {
			$r->fields = self::decode_fields( $r->fields );
		}
		return $r;
	}

	public static function save_form( $id, $title, $fields ) {
		global $wpdb;
		$json = wp_json_encode( array_values( $fields ) );
		if ( $id ) {
			$wpdb->update( self::t( 'forms' ), array( 'title' => $title, 'fields' => $json ), array( 'id' => $id ), array( '%s', '%s' ), array( '%d' ) );
			return $id;
		}
		$wpdb->insert(
			self::t( 'forms' ),
			array( 'title' => $title, 'fields' => $json, 'created_at' => current_time( 'mysql' ) ),
			array( '%s', '%s', '%s' )
		);
		return (int) $wpdb->insert_id;
	}

	public static function delete_form( $id ) {
		global $wpdb;
		$wpdb->query( $wpdb->prepare( 'DELETE c FROM ' . self::t( 'compat' ) . ' c INNER JOIN ' . self::t( 'devices' ) . ' d ON d.id=c.device_id WHERE d.form_id=%d', $id ) );
		$wpdb->delete( self::t( 'devices' ), array( 'form_id' => $id ), array( '%d' ) );
		$wpdb->delete( self::t( 'missed' ), array( 'form_id' => $id ), array( '%d' ) );
		$wpdb->delete( self::t( 'forms' ), array( 'id' => $id ), array( '%d' ) );
	}

	/* ---------- دستگاه‌ها ---------- */

	public static function device_count( $form_id ) {
		global $wpdb;
		return (int) $wpdb->get_var( $wpdb->prepare( 'SELECT COUNT(*) FROM ' . self::t( 'devices' ) . ' WHERE form_id=%d', $form_id ) );
	}

	public static function label( $row ) {
		$p = array();
		for ( $i = 1; $i <= self::MAX_LEVELS; $i++ ) {
			$c = 'l' . $i;
			if ( isset( $row->$c ) && '' !== $row->$c ) {
				$p[] = $row->$c;
			}
		}
		return implode( ' › ', $p );
	}

	protected static function key( $arr ) {
		$arr = array_map( array( __CLASS__, 'lc' ), array_values( $arr ) );
		return md5( implode( "\x1f", $arr ) );
	}

	public static function parse_line( $line, $n ) {
		$line = trim( (string) $line );
		if ( '' === $line ) {
			return null;
		}
		$parts = preg_split( '/\s*(?:>|›|»|\||\t|،|,)\s*/u', $line );
		$parts = array_slice( $parts, 0, max( 1, min( self::MAX_LEVELS, (int) $n ) ) );
		$parts = array_map( 'sanitize_text_field', $parts );
		while ( $parts && '' === end( $parts ) ) {
			array_pop( $parts );
		}
		if ( ! $parts || '' === $parts[0] ) {
			return null;
		}
		foreach ( $parts as $i => $p ) {
			$parts[ $i ] = function_exists( 'mb_substr' ) ? mb_substr( $p, 0, 150 ) : substr( $p, 0, 150 );
		}
		return $parts;
	}

	/** @return array(افزوده‌شده، تکراری) */
	public static function import_lines( $form_id, $text, $n ) {
		global $wpdb;
		$t        = self::t( 'devices' );
		$existing = array();
		$rows     = $wpdb->get_results( $wpdb->prepare( "SELECT l1,l2,l3,l4,l5 FROM $t WHERE form_id=%d", $form_id ), ARRAY_N );
		if ( $rows ) {
			foreach ( $rows as $r ) {
				$existing[ self::key( $r ) ] = 1;
			}
		}
		$added   = 0;
		$skipped = 0;
		foreach ( preg_split( '/\r\n|\r|\n/', (string) $text ) as $line ) {
			$p = self::parse_line( $line, $n );
			if ( ! $p ) {
				continue;
			}
			$p = array_pad( $p, 5, '' );
			$k = self::key( $p );
			if ( isset( $existing[ $k ] ) ) {
				$skipped++;
				continue;
			}
			$wpdb->insert(
				$t,
				array( 'form_id' => $form_id, 'l1' => $p[0], 'l2' => $p[1], 'l3' => $p[2], 'l4' => $p[3], 'l5' => $p[4] ),
				array( '%d', '%s', '%s', '%s', '%s', '%s' )
			);
			$existing[ $k ] = 1;
			$added++;
		}
		return array( $added, $skipped );
	}

	public static function delete_device( $id ) {
		global $wpdb;
		$wpdb->delete( self::t( 'compat' ), array( 'device_id' => $id ), array( '%d' ) );
		$wpdb->delete( self::t( 'devices' ), array( 'id' => $id ), array( '%d' ) );
	}

	/** شرط WHERE برای مسیر انتخاب‌شده (پیشوند مسیر). */
	public static function where( $alias, $form_id, $values ) {
		global $wpdb;
		$w = $wpdb->prepare( "{$alias}.form_id=%d", $form_id );
		$i = 1;
		foreach ( (array) $values as $v ) {
			if ( '' === $v || $i > self::MAX_LEVELS ) {
				break;
			}
			$w .= $wpdb->prepare( " AND {$alias}.l{$i}=%s", $v );
			$i++;
		}
		return $w;
	}

	public static function next_options( $form_id, $values ) {
		global $wpdb;
		$k = count( (array) $values );
		if ( $k >= self::MAX_LEVELS ) {
			return array();
		}
		$col  = 'l' . ( $k + 1 );
		$list = $wpdb->get_col( 'SELECT DISTINCT d.' . $col . ' FROM ' . self::t( 'devices' ) . ' d WHERE ' . self::where( 'd', $form_id, $values ) . " AND d.$col<>''" );
		$list = $list ? $list : array();
		$year = ! empty( $list );
		foreach ( $list as $v ) {
			if ( ! preg_match( '/^[0-9]{4}$/', self::digits( $v ) ) ) {
				$year = false;
				break;
			}
		}
		usort(
			$list,
			function ( $a, $b ) use ( $year ) {
				$r = strnatcasecmp( self::digits( $a ), self::digits( $b ) );
				return $year ? -$r : $r;
			}
		);
		return $list;
	}

	public static function devices_by_prefix( $form_id, $values, $limit = 1000 ) {
		global $wpdb;
		$rows = $wpdb->get_results( 'SELECT d.* FROM ' . self::t( 'devices' ) . ' d WHERE ' . self::where( 'd', $form_id, $values ) . ' ORDER BY d.id LIMIT ' . (int) $limit );
		return $rows ? $rows : array();
	}

	/* ---------- سازگاری محصولات ---------- */

	public static function rank( $type ) {
		$r = array( 'verified' => 0, 'conditional' => 1, 'alt' => 2 );
		return isset( $r[ $type ] ) ? $r[ $type ] : 0;
	}

	/** @return array product_id => array(type, note, rank) */
	public static function find_by_device( $form_id, $values ) {
		global $wpdb;
		$rows = $wpdb->get_results(
			'SELECT c.product_id,c.ctype,c.note FROM ' . self::t( 'compat' ) . ' c INNER JOIN ' . self::t( 'devices' ) . ' d ON d.id=c.device_id WHERE ' . self::where( 'd', $form_id, $values )
		);
		$out = array();
		if ( $rows ) {
			foreach ( $rows as $r ) {
				$pid = (int) $r->product_id;
				$rk  = self::rank( $r->ctype );
				if ( ! isset( $out[ $pid ] ) || $rk < $out[ $pid ]['rank'] ) {
					$out[ $pid ] = array( 'type' => $r->ctype, 'note' => $r->note, 'rank' => $rk );
				}
			}
		}
		return $out;
	}

	public static function product_compat( $product_id ) {
		global $wpdb;
		$rows = $wpdb->get_results(
			$wpdb->prepare(
				'SELECT c.device_id,c.ctype,c.note,d.* FROM ' . self::t( 'compat' ) . ' c INNER JOIN ' . self::t( 'devices' ) . ' d ON d.id=c.device_id WHERE c.product_id=%d ORDER BY d.form_id,d.l1,d.l2,d.l3,d.l4,d.l5',
				$product_id
			)
		);
		return $rows ? $rows : array();
	}

	public static function product_has_compat( $product_id ) {
		global $wpdb;
		return (bool) $wpdb->get_var( $wpdb->prepare( 'SELECT 1 FROM ' . self::t( 'compat' ) . ' WHERE product_id=%d LIMIT 1', $product_id ) );
	}

	public static function save_product_compat( $product_id, $items ) {
		global $wpdb;
		$t   = self::t( 'compat' );
		$ids = array();
		foreach ( (array) $items as $it ) {
			if ( is_array( $it ) && ! empty( $it['device_id'] ) ) {
				$ids[] = absint( $it['device_id'] );
			}
		}
		$valid = array();
		if ( $ids ) {
			$ph    = implode( ',', array_fill( 0, count( $ids ), '%d' ) );
			$found = $wpdb->get_col( $wpdb->prepare( 'SELECT id FROM ' . self::t( 'devices' ) . " WHERE id IN ($ph)", $ids ) );
			foreach ( (array) $found as $f ) {
				$valid[ (int) $f ] = true;
			}
		}
		$wpdb->delete( $t, array( 'product_id' => $product_id ), array( '%d' ) );
		$seen = array();
		foreach ( (array) $items as $it ) {
			if ( ! is_array( $it ) || empty( $it['device_id'] ) ) {
				continue;
			}
			$did = absint( $it['device_id'] );
			if ( ! isset( $valid[ $did ] ) || isset( $seen[ $did ] ) ) {
				continue;
			}
			$seen[ $did ] = true;
			$type         = ( isset( $it['ctype'] ) && in_array( $it['ctype'], array( 'verified', 'conditional', 'alt' ), true ) ) ? $it['ctype'] : 'verified';
			$note         = isset( $it['note'] ) ? sanitize_text_field( $it['note'] ) : '';
			$note         = function_exists( 'mb_substr' ) ? mb_substr( $note, 0, 250 ) : substr( $note, 0, 250 );
			$wpdb->insert(
				$t,
				array( 'product_id' => $product_id, 'device_id' => $did, 'ctype' => $type, 'note' => $note ),
				array( '%d', '%d', '%s', '%s' )
			);
		}
	}

	/* ---------- ابزارهای اتصال آسان ---------- */

	protected static $dev_cache = array();

	/** @return array(id, created) */
	public static function ensure_device( $form_id, $levels ) {
		global $wpdb;
		$levels = array_pad( array_slice( array_values( $levels ), 0, self::MAX_LEVELS ), self::MAX_LEVELS, '' );
		$k      = $form_id . '|' . self::key( $levels );
		if ( isset( self::$dev_cache[ $k ] ) ) {
			return array( self::$dev_cache[ $k ], false );
		}
		$t  = self::t( 'devices' );
		$id = (int) $wpdb->get_var( $wpdb->prepare( "SELECT id FROM $t WHERE form_id=%d AND l1=%s AND l2=%s AND l3=%s AND l4=%s AND l5=%s LIMIT 1", $form_id, $levels[0], $levels[1], $levels[2], $levels[3], $levels[4] ) );
		$created = false;
		if ( ! $id ) {
			$wpdb->insert(
				$t,
				array( 'form_id' => $form_id, 'l1' => $levels[0], 'l2' => $levels[1], 'l3' => $levels[2], 'l4' => $levels[3], 'l5' => $levels[4] ),
				array( '%d', '%s', '%s', '%s', '%s', '%s' )
			);
			$id      = (int) $wpdb->insert_id;
			$created = true;
		}
		self::$dev_cache[ $k ] = $id;
		return array( $id, $created );
	}

	public static function link_products( $product_ids, $device_ids, $type, $note = '' ) {
		global $wpdb;
		$type = in_array( $type, array( 'verified', 'conditional', 'alt' ), true ) ? $type : 'verified';
		$n    = 0;
		$rows = array();
		foreach ( $product_ids as $p ) {
			foreach ( $device_ids as $d ) {
				$rows[] = $wpdb->prepare( '(%d,%d,%s,%s)', $p, $d, $type, $note );
				if ( count( $rows ) >= 200 ) {
					$n   += (int) $wpdb->query( 'INSERT IGNORE INTO ' . self::t( 'compat' ) . ' (product_id,device_id,ctype,note) VALUES ' . implode( ',', $rows ) );
					$rows = array();
				}
			}
		}
		if ( $rows ) {
			$n += (int) $wpdb->query( 'INSERT IGNORE INTO ' . self::t( 'compat' ) . ' (product_id,device_id,ctype,note) VALUES ' . implode( ',', $rows ) );
		}
		return $n;
	}

	/** اتصال یک محصول به یک مشخصات؛ در صورت وجود، نوع و توضیح به‌روز می‌شود. */
	public static function link_one( $product_id, $device_id, $type, $note = '' ) {
		global $wpdb;
		$type = in_array( $type, array( 'verified', 'conditional', 'alt' ), true ) ? $type : 'verified';
		return (int) $wpdb->query(
			$wpdb->prepare(
				'INSERT INTO ' . self::t( 'compat' ) . ' (product_id,device_id,ctype,note) VALUES (%d,%d,%s,%s) ON DUPLICATE KEY UPDATE ctype=VALUES(ctype),note=VALUES(note)',
				$product_id,
				$device_id,
				$type,
				$note
			)
		);
	}

	/** جستجوی کد قطعه: محصولات مطابق و جایگزین‌هایشان. @return array(main=>ids, alts=>ids) */
	public static function code_lookup( $raw ) {
		global $wpdb;
		$norm = self::norm_code( $raw );
		$main = array();
		$alts = array();
		if ( strlen( $norm ) < 2 ) {
			return array( 'main' => array(), 'alts' => array() );
		}
		$sku_id = wc_get_product_id_by_sku( self::digits( trim( $raw ) ) );
		if ( $sku_id ) {
			$p = wc_get_product( $sku_id );
			if ( $p && $p->is_type( 'variation' ) ) {
				$sku_id = $p->get_parent_id();
			}
			$main[ (int) $sku_id ] = true;
		}
		$like  = '%' . $wpdb->esc_like( trim( self::digits( $raw ) ) ) . '%';
		$metas = $wpdb->get_results( $wpdb->prepare( "SELECT post_id, meta_value FROM {$wpdb->postmeta} WHERE meta_key='_pmf_codes' AND meta_value LIKE %s LIMIT 200", $like ) );
		if ( $metas ) {
			foreach ( $metas as $m ) {
				foreach ( preg_split( '/[\r\n,،;]+/u', $m->meta_value ) as $c ) {
					if ( '' !== trim( $c ) && self::norm_code( $c ) === $norm ) {
						$main[ (int) $m->post_id ] = true;
						break;
					}
				}
			}
		}
		foreach ( array_keys( $main ) as $pid ) {
			$list = trim( (string) get_post_meta( $pid, '_pmf_alts', true ), ',' );
			if ( '' !== $list ) {
				foreach ( explode( ',', $list ) as $a ) {
					$alts[ (int) $a ] = true;
				}
			}
			$rev = $wpdb->get_col( $wpdb->prepare( "SELECT post_id FROM {$wpdb->postmeta} WHERE meta_key='_pmf_alts' AND meta_value LIKE %s LIMIT 100", '%,' . (int) $pid . ',%' ) );
			foreach ( (array) $rev as $r ) {
				$alts[ (int) $r ] = true;
			}
		}
		foreach ( array_keys( $main ) as $pid ) {
			unset( $alts[ $pid ] );
		}
		return array( 'main' => array_keys( $main ), 'alts' => array_keys( $alts ) );
	}

	/* ---------- جستجوهای بی‌نتیجه ---------- */

	public static function log_missed( $form_id, $kind, $q ) {
		global $wpdb;
		$q = trim( (string) $q );
		if ( '' === $q ) {
			return;
		}
		$q = function_exists( 'mb_substr' ) ? mb_substr( $q, 0, 170 ) : substr( $q, 0, 170 );
		$wpdb->query(
			$wpdb->prepare(
				'INSERT INTO ' . self::t( 'missed' ) . ' (form_id,kind,q,hits,last_at) VALUES (%d,%s,%s,1,%s) ON DUPLICATE KEY UPDATE hits=hits+1,last_at=VALUES(last_at)',
				$form_id,
				$kind,
				$q,
				current_time( 'mysql' )
			)
		);
	}
}
