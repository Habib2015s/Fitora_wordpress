<?php
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * اطلاع‌رسانی «موجود شد»: وقتی محصولی موجود می‌شود (یا سازگاری‌اش ثبت می‌شود)،
 * به مشتریانی که درخواست داده‌اند پیامک با لینک لیست (قابل ذخیره به‌صورت PDF) می‌رود.
 */
class PMF_Notify {

	public static function init() {
		add_action( 'woocommerce_product_set_stock_status', array( __CLASS__, 'on_stock' ), 10, 3 );
		add_action( 'woocommerce_variation_set_stock_status', array( __CLASS__, 'on_variation_stock' ), 10, 3 );
		add_action( 'woocommerce_new_product', array( __CLASS__, 'on_new_product' ) );
		add_action( 'woocommerce_update_product', array( __CLASS__, 'schedule' ) );
		add_action( 'pmf_notify_product', array( __CLASS__, 'process_product' ), 10, 2 );
		add_action( 'template_redirect', array( __CLASS__, 'maybe_render_list' ), 1 );
		add_shortcode( 'partmatch_notify', array( __CLASS__, 'shortcode' ) );
		if ( PMF_DB::setting( 'notify_product' ) ) {
			add_action( 'woocommerce_single_product_summary', array( __CLASS__, 'notify_box' ), 31 );
		}
	}

	/* ---------- محرک‌ها ---------- */

	public static function on_stock( $product_id, $status, $product = null ) {
		if ( 'instock' === $status || 'onbackorder' === $status ) {
			self::mark_restocked( $product_id );
			self::schedule( $product_id );
		}
	}

	public static function on_new_product( $product_id ) {
		$p = wc_get_product( $product_id );
		if ( $p && $p->is_in_stock() && 'publish' === $p->get_status() && ! get_post_meta( $product_id, '_pmf_restocked_at', true ) ) {
			self::mark_restocked( $product_id );
		}
		self::schedule( $product_id );
	}

	public static function on_variation_stock( $variation_id, $status, $variation = null ) {
		if ( ( 'instock' === $status || 'onbackorder' === $status ) && $variation && is_object( $variation ) ) {
			self::mark_restocked( $variation->get_parent_id() );
			self::schedule( $variation->get_parent_id() );
		}
	}

	public static function has_pending() {
		global $wpdb;
		return (bool) $wpdb->get_var( 'SELECT 1 FROM ' . PMF_DB::t( 'requests' ) . " WHERE status='new' AND (email<>'' OR phone<>'') LIMIT 1" );
	}

	public static function schedule( $product_id ) {
		if ( ! $product_id || ! self::has_pending() ) {
			return;
		}
		wp_schedule_single_event( time() + 10, 'pmf_notify_product', array( (int) $product_id, time() ) );
	}

	/* ---------- پردازش ---------- */

	public static function process_product( $product_id, $stamp = 0 ) {
		global $wpdb;
		$p = wc_get_product( $product_id );
		if ( ! $p ) {
			return;
		}
		if ( $p->is_type( 'variation' ) ) {
			$product_id = $p->get_parent_id();
		}
		$product_id = (int) $product_id;
		$reqs       = $wpdb->get_results( 'SELECT * FROM ' . PMF_DB::t( 'requests' ) . " WHERE status='new' AND (email<>'' OR phone<>'') ORDER BY id LIMIT 500" );
		$cache      = array();
		foreach ( (array) $reqs as $r ) {
			$ids = self::available_for_request( $r, $cache );
			if ( $ids && in_array( $product_id, $ids, true ) ) {
				self::notify( $r, $ids );
			}
		}
	}

	protected static function is_available( $p ) {
		return $p && 'publish' === $p->get_status() && $p->is_in_stock();
	}

	/** محصولاتی که اکنون برای این درخواست موجودند. */
	public static function available_for_request( $r, &$cache = array() ) {
		$out = array();
		if ( 'product' === $r->kind ) {
			$p = wc_get_product( (int) $r->product_id );
			return self::is_available( $p ) ? array( (int) $p->get_id() ) : array();
		}
		if ( 'search' === $r->kind ) {
			$vals = json_decode( (string) $r->vals, true );
			if ( ! is_array( $vals ) || ! $vals ) {
				return array();
			}
			$key = $r->form_id . '|' . md5( (string) $r->vals );
			if ( ! isset( $cache[ $key ] ) ) {
				$cache[ $key ] = PMF_DB::find_by_device( (int) $r->form_id, $vals );
			}
			$found = $cache[ $key ];
			uasort(
				$found,
				function ( $a, $b ) {
					return $a['rank'] - $b['rank'];
				}
			);
			foreach ( array_keys( $found ) as $pid ) {
				if ( self::is_available( wc_get_product( $pid ) ) ) {
					$out[] = (int) $pid;
				}
				if ( count( $out ) >= 30 ) {
					break;
				}
			}
			return $out;
		}
		if ( 'code' === $r->kind ) {
			$look = PMF_DB::code_lookup( (string) $r->query );
			foreach ( array_merge( $look['main'], $look['alts'] ) as $pid ) {
				if ( self::is_available( wc_get_product( $pid ) ) ) {
					$out[] = (int) $pid;
				}
			}
			return array_slice( array_values( array_unique( $out ) ), 0, 30 );
		}
		return array();
	}

	public static function send_email( $r ) {
		$site  = wp_specialchars_decode( get_bloginfo( 'name' ), ENT_QUOTES );
		$name  = '' !== trim( (string) $r->name ) ? trim( $r->name ) . ' عزیز' : 'مشتری گرامی';
		$link  = self::list_url( $r->token );
		$color = sanitize_hex_color( PMF_DB::setting( 'primary' ) );
		$color = $color ? $color : '#1d4ed8';
		$subj  = strtr( PMF_DB::setting( 'email_subject' ), array( '{site}' => $site ) );
		$body  = '<div dir="rtl" style="background:#eef1f6;padding:24px;font-family:Tahoma,Arial,sans-serif">'
			. '<div style="max-width:520px;margin:0 auto;background:#fff;border-radius:16px;overflow:hidden">'
			. '<div style="background:' . esc_attr( $color ) . ';color:#fff;padding:20px 24px;font-size:18px;font-weight:bold">' . esc_html( $site ) . '</div>'
			. '<div style="padding:24px;line-height:2;color:#111827">'
			. '<p>' . esc_html( $name ) . '، سلام!</p>'
			. '<p>محصولی که منتظرش بودید موجود شد. لیست محصولات (با تاریخ و امکان ذخیره PDF) از لینک زیر در دسترس است:</p>'
			. '<p style="text-align:center;margin:24px 0"><a href="' . esc_url( $link ) . '" style="background:' . esc_attr( $color ) . ';color:#fff;text-decoration:none;padding:12px 26px;border-radius:10px;font-weight:bold;display:inline-block">مشاهدهٔ لیست محصولات</a></p>'
			. '</div></div></div>';
		return (bool) wp_mail( $r->email, $subj, $body, array( 'Content-Type: text/html; charset=UTF-8' ) );
	}

	public static function message( $r ) {
		$tpl  = PMF_DB::setting( 'sms_template' );
		$name = '' !== trim( (string) $r->name ) ? trim( $r->name ) . ' عزیز' : 'مشتری گرامی';
		return strtr(
			$tpl,
			array(
				'{name}' => $name,
				'{site}' => wp_specialchars_decode( get_bloginfo( 'name' ), ENT_QUOTES ),
				'{link}' => self::list_url( $r->token ),
			)
		);
	}

	public static function list_url( $token ) {
		return add_query_arg( 'pmf_list', rawurlencode( $token ), home_url( '/' ) );
	}

	/** ارسال پیامک و ثبت نتیجه. @return array(ok,msg) */
	public static function notify( $r, $ids ) {
		global $wpdb;
		$res  = array( false, 'راه تماسی ثبت نشده' );
		$notes = array();
		$ok   = false;
		if ( ! empty( $r->email ) && is_email( $r->email ) && PMF_DB::setting( 'email_on' ) ) {
			$sent    = self::send_email( $r );
			$ok      = $ok || $sent;
			$notes[] = $sent ? 'ایمیل ارسال شد' : 'ارسال ایمیل ناموفق بود (تنظیمات ایمیل سرور را بررسی کنید)';
		}
		if ( ! empty( $r->phone ) && PMF_SMS::enabled() ) {
			$sms     = PMF_SMS::send( $r->phone, self::message( $r ) );
			$ok      = $ok || $sms[0];
			$notes[] = 'پیامک: ' . $sms[1];
		}
		if ( $notes ) {
			$res = array( $ok, implode( ' | ', $notes ) );
		}
		$wpdb->update(
			PMF_DB::t( 'requests' ),
			array(
				'status'       => $res[0] ? 'notified' : 'failed',
				'sms_status'   => function_exists( 'mb_substr' ) ? mb_substr( $res[1], 0, 180 ) : substr( $res[1], 0, 180 ),
				'notified_ids' => wp_json_encode( array_values( $ids ) ),
				'notified_at'  => current_time( 'mysql' ),
			),
			array( 'id' => (int) $r->id ),
			array( '%s', '%s', '%s', '%s' ),
			array( '%d' )
		);
		return $res;
	}

	/* ---------- باکس «خبرم کن» در صفحهٔ محصول ---------- */

	protected static $rendered = array();

	/** شورت‌کد [partmatch_notify] — برای قالب‌های ساخته‌شده با المنتور و... */
	public static function shortcode( $atts ) {
		$a  = shortcode_atts( array( 'id' => 0, 'always' => '' ), $atts, 'partmatch_notify' );
		$id = absint( $a['id'] );
		if ( ! $id ) {
			$id = get_the_ID();
		}
		$p = $id ? wc_get_product( $id ) : null;
		if ( ! $p ) {
			return '';
		}
		PMF_Frontend::enqueue();
		ob_start();
		self::notify_box( $p, '' !== $a['always'] );
		return ob_get_clean();
	}

	public static function notify_box( $force_product = null, $always = false ) {
		global $product;
		$pr = ( is_object( $force_product ) && method_exists( $force_product, 'get_id' ) ) ? $force_product : $product;
		if ( ! $pr || ( ! $always && $pr->is_in_stock() ) || isset( self::$rendered[ $pr->get_id() ] ) ) {
			return;
		}
		self::$rendered[ $pr->get_id() ] = true;
		$product = $pr; // phpcs:ignore WordPress.WP.GlobalVariablesOverride
		?>
		<div class="pmf-notify" dir="rtl" style="--pmf-primary:<?php echo esc_attr( sanitize_hex_color( PMF_DB::setting( 'primary' ) ) ? sanitize_hex_color( PMF_DB::setting( 'primary' ) ) : '#1d4ed8' ); ?>" data-product="<?php echo esc_attr( $product->get_id() ); ?>" data-loaded="<?php echo esc_attr( time() ); ?>">
			<strong>این محصول فعلاً ناموجود است</strong>
			<p>ایمیل‌تان را بگذارید؛ به‌محض موجود شدن برایتان ایمیل می‌فرستیم.</p>
			<div class="pmf-notify-row">
				<input type="text" class="pmf-n-name" placeholder="نام (اختیاری)">
				<input type="email" class="pmf-n-email" placeholder="ایمیل شما" inputmode="email" dir="ltr" autocomplete="email">
				<?php if ( PMF_SMS::enabled() ) : ?><input type="tel" class="pmf-n-phone" placeholder="موبایل (اختیاری)" inputmode="tel"><?php endif; ?>
				<input type="text" class="pmf-hp pmf-n-web" tabindex="-1" autocomplete="off" aria-hidden="true">
				<button type="button" class="pmf-btn pmf-n-go">خبرم کن</button>
			</div>
			<small><?php echo esc_html( PMF_DB::setting( 'consent' ) ); ?></small>
			<div class="pmf-n-msg"></div>
		</div>
		<?php
	}

	/* ---------- کاتالوگ خودکار «تازه موجودشده‌ها» ---------- */

	public static function catalog_url() {
		return add_query_arg( 'pmf_catalog', '1', home_url( '/' ) );
	}

	/** ثبت لحظهٔ موجود شدن محصول؛ کاتالوگ خودکار از همین استفاده می‌کند. */
	public static function mark_restocked( $product_id ) {
		$product_id = (int) $product_id;
		if ( ! $product_id ) {
			return;
		}
		update_post_meta( $product_id, '_pmf_restocked_at', current_time( 'mysql' ) );
		delete_transient( 'pmf_recent_rows' );
	}

	/** @return array of array( WC_Product, restock_datetime ) */
	public static function recent_products( $limit = 100, $exclude = array() ) {
		global $wpdb;
		$days = max( 1, (int) PMF_DB::setting( 'catalog_days' ) );
		$rows = get_transient( 'pmf_recent_rows' );
		if ( ! is_array( $rows ) || ! isset( $rows['days'] ) || (int) $rows['days'] !== $days ) {
			$since = gmdate( 'Y-m-d H:i:s', strtotime( current_time( 'mysql' ) . ' UTC' ) - $days * DAY_IN_SECONDS );
			$list  = $wpdb->get_results( $wpdb->prepare( "SELECT post_id, meta_value FROM {$wpdb->postmeta} WHERE meta_key='_pmf_restocked_at' AND meta_value >= %s ORDER BY meta_value DESC LIMIT 300", $since ) );
			$rows  = array( 'days' => $days, 'list' => $list ? $list : array() );
			set_transient( 'pmf_recent_rows', $rows, 5 * MINUTE_IN_SECONDS );
		}
		$out = array();
		foreach ( $rows['list'] as $r ) {
			$pid = (int) $r->post_id;
			if ( in_array( $pid, $exclude, true ) ) {
				continue;
			}
			$p = wc_get_product( $pid );
			if ( ! self::is_available( $p ) || 'hidden' === $p->get_catalog_visibility() ) {
				continue;
			}
			$out[] = array( $p, $r->meta_value );
			if ( count( $out ) >= $limit ) {
				break;
			}
		}
		return $out;
	}

	public static function maybe_render_list() {
		$is_list = ! empty( $_GET['pmf_list'] ) && is_scalar( $_GET['pmf_list'] ); // phpcs:ignore WordPress.Security.NonceVerification
		$is_cat  = isset( $_GET['pmf_catalog'] ); // phpcs:ignore WordPress.Security.NonceVerification
		if ( ! $is_list && ! $is_cat ) {
			return;
		}
		global $wpdb;
		nocache_headers();
		header( 'X-Robots-Tag: noindex, nofollow' );

		$sections = array();
		$meta     = array();
		$when     = current_time( 'mysql' );
		$heading  = PMF_DB::setting( 'catalog_title' );

		if ( $is_list ) {
			$token = preg_replace( '/[^A-Za-z0-9]/', '', (string) wp_unslash( $_GET['pmf_list'] ) ); // phpcs:ignore
			$r     = $token ? $wpdb->get_row( $wpdb->prepare( 'SELECT * FROM ' . PMF_DB::t( 'requests' ) . ' WHERE token=%s LIMIT 1', $token ) ) : null;
			if ( ! $r ) {
				status_header( 404 );
				wp_die( 'این لینک معتبر نیست یا منقضی شده است.', 'لینک نامعتبر', array( 'response' => 404 ) );
			}
			$ids = json_decode( (string) $r->notified_ids, true );
			if ( ! is_array( $ids ) || ! $ids ) {
				$cache = array();
				$ids   = self::available_for_request( $r, $cache );
			}
			$mine = array();
			foreach ( $ids as $id ) {
				$p = wc_get_product( (int) $id );
				if ( $p && 'publish' === $p->get_status() ) {
					$mine[] = array( $p, (string) get_post_meta( $p->get_id(), '_pmf_restocked_at', true ) );
				}
			}
			$when    = $r->notified_at ? $r->notified_at : $when;
			$heading = 'لیست محصولات موجودشده برای شما';
			$ask     = 'product' === $r->kind ? 'محصول: ' . $r->query : ( 'code' === $r->kind ? 'کد قطعه: ' . $r->query : $r->query );
			if ( '' !== trim( $ask ) ) {
				$meta[] = 'درخواست شما: ' . $ask;
			}
			$sections[] = array( 'title' => 'مخصوص شما', 'items' => $mine );
			$sections[] = array( 'title' => 'سایر محصولات تازه موجودشده', 'items' => self::recent_products( 30, $ids ) );
		} else {
			$sections[] = array( 'title' => '', 'items' => self::recent_products( 100 ) );
		}
		self::render_page( $heading, $meta, $sections, $when, isset( $_GET['print'] ) ); // phpcs:ignore WordPress.Security.NonceVerification
		exit;
	}

	protected static function render_page( $heading, $meta, $sections, $when, $auto_print ) {
		$site  = wp_specialchars_decode( get_bloginfo( 'name' ), ENT_QUOTES );
		$icon  = get_site_icon_url( 96 );
		$color = sanitize_hex_color( PMF_DB::setting( 'primary' ) );
		$color = $color ? $color : '#1d4ed8';
		$total = 0;
		foreach ( $sections as $sec ) {
			$total += count( $sec['items'] );
		}
		$days = (int) PMF_DB::setting( 'catalog_days' );

		header( 'Content-Type: text/html; charset=utf-8' );
		?>
<!doctype html>
<html lang="fa" dir="rtl">
<head>
<meta charset="utf-8">
<meta name="viewport" content="width=device-width,initial-scale=1">
<meta name="robots" content="noindex,nofollow">
<title><?php echo esc_html( $heading . ' — ' . $site ); ?></title>
<style>
:root{--c:<?php echo esc_html( $color ); ?>}
*{box-sizing:border-box}
body{margin:0;background:#eef1f6;font-family:Tahoma,"Vazirmatn","IRANSans",sans-serif;color:#111827;line-height:1.8}
.wrap{max-width:900px;margin:0 auto;padding:22px 14px 48px}
.head{position:relative;overflow:hidden;background:linear-gradient(135deg,var(--c),#0f172a);color:#fff;border-radius:22px;padding:26px 28px;display:flex;gap:16px;align-items:center}
.head::after{content:"";position:absolute;left:-60px;top:-60px;width:220px;height:220px;border-radius:50%;background:rgba(255,255,255,.08)}
.head img{width:58px;height:58px;border-radius:14px;background:#fff;flex:none}
.head h1{margin:0;font-size:1.35em}.head p{margin:2px 0 0;opacity:.85;font-size:.92em}
.meta{display:flex;flex-wrap:wrap;gap:8px 20px;margin:16px 4px;color:#4b5563;font-size:.93em}
.meta b{color:#111827}
.actions{display:flex;flex-wrap:wrap;gap:8px;margin:4px 0 8px}
.btn{border:0;background:var(--c);color:#fff;border-radius:12px;padding:11px 20px;font:inherit;font-weight:700;cursor:pointer;text-decoration:none;display:inline-block}
.btn.alt{background:#fff;color:var(--c);border:1px solid var(--c)}
.tip{font-size:.82em;color:#6b7280;margin:0 4px 14px}
h2{font-size:1.05em;margin:22px 4px 10px;color:#374151}
.item{display:flex;gap:14px;align-items:center;background:#fff;border:1px solid #e5e7eb;border-radius:16px;padding:12px;margin-bottom:10px;box-shadow:0 1px 2px rgba(0,0,0,.03)}
.item img{width:88px;height:88px;object-fit:cover;border-radius:12px;background:#f3f4f6;flex:none}
.item .info{flex:1;min-width:0}
.item .n{font-weight:700;color:#111827;text-decoration:none;display:block}
.item .sku{font-size:.8em;color:#6b7280}
.item .pr{margin-top:2px;font-weight:700}
.item .dt{display:inline-block;margin-top:4px;font-size:.78em;background:#ecfdf5;color:#047857;border-radius:999px;padding:1px 10px}
.empty{background:#fff;border-radius:16px;padding:32px;text-align:center;color:#6b7280}
.foot{margin-top:22px;color:#6b7280;font-size:.84em;text-align:center}
@media (max-width:520px){.item{flex-wrap:wrap}.item .btn{width:100%;text-align:center}.head{padding:20px}}
@media print{body{background:#fff}.actions,.tip,.item .btn{display:none}.item{break-inside:avoid;box-shadow:none}.head{-webkit-print-color-adjust:exact;print-color-adjust:exact}}
</style>
</head>
<body>
<div class="wrap">
	<div class="head">
		<?php if ( $icon ) : ?><img src="<?php echo esc_url( $icon ); ?>" alt=""><?php endif; ?>
		<div><h1><?php echo esc_html( $heading ); ?></h1><p><?php echo esc_html( $site ); ?></p></div>
	</div>
	<div class="meta">
		<span>تاریخ: <b><?php echo esc_html( PMF_Date::format( $when, true, true ) ); ?></b></span>
		<span>تعداد: <b><?php echo esc_html( PMF_Date::fa_digits( $total ) ); ?></b> محصول</span>
		<?php foreach ( $meta as $m ) : ?><span><?php echo esc_html( $m ); ?></span><?php endforeach; ?>
	</div>
	<div class="actions">
		<button class="btn" type="button" onclick="window.print()">ذخیره به‌صورت PDF</button>
		<a class="btn alt" href="<?php echo esc_url( home_url( '/' ) ); ?>">رفتن به فروشگاه</a>
	</div>
	<p class="tip">در پنجرهٔ بازشده، مقصد را «Save as PDF» (ذخیره به‌صورت PDF) بگذارید.</p>
	<?php if ( ! $total ) : ?>
		<div class="empty">در <?php echo esc_html( PMF_Date::fa_digits( $days ) ); ?> روز گذشته محصولی تازه موجود نشده است.</div>
	<?php endif; ?>
	<?php foreach ( $sections as $sec ) : ?>
		<?php if ( ! $sec['items'] ) { continue; } ?>
		<?php if ( '' !== $sec['title'] ) : ?><h2><?php echo esc_html( $sec['title'] ); ?></h2><?php endif; ?>
		<?php
		foreach ( $sec['items'] as $it ) :
			$p   = $it[0];
			$ts  = $it[1];
			$img = $p->get_image_id() ? wp_get_attachment_image_url( $p->get_image_id(), 'woocommerce_thumbnail' ) : wc_placeholder_img_src();
			?>
			<div class="item">
				<img src="<?php echo esc_url( $img ); ?>" alt="">
				<div class="info">
					<a class="n" href="<?php echo esc_url( $p->get_permalink() ); ?>"><?php echo esc_html( $p->get_name() ); ?></a>
					<?php if ( $p->get_sku() ) : ?><div class="sku">کد: <?php echo esc_html( $p->get_sku() ); ?></div><?php endif; ?>
					<div class="pr"><?php echo wp_kses_post( $p->get_price_html() ); ?></div>
					<?php if ( $ts ) : ?><span class="dt">موجود شد: <?php echo esc_html( PMF_Date::format( $ts, false, true ) ); ?></span><?php endif; ?>
				</div>
				<a class="btn" href="<?php echo esc_url( $p->get_permalink() ); ?>">مشاهده و خرید</a>
			</div>
		<?php endforeach; ?>
	<?php endforeach; ?>
	<div class="foot">این فهرست خودکار ساخته شده و فقط محصولاتِ موجود در آن است. قیمت و موجودی ممکن است تغییر کند. تاریخ تهیه: <?php echo esc_html( PMF_Date::format( $when, false, true ) ); ?></div>
</div>
<?php if ( $auto_print ) : ?><script>window.addEventListener('load',function(){setTimeout(function(){window.print();},400);});</script><?php endif; ?>
</body>
</html>
		<?php
	}
}
