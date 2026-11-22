<?php
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class PMF_Admin {

	const CAP = 'manage_woocommerce';

	public static function init() {
		add_action( 'admin_menu', array( __CLASS__, 'menu' ) );
		add_action( 'admin_enqueue_scripts', array( __CLASS__, 'assets' ), 20 );
		$actions = array(
			'save_form', 'delete_form', 'import', 'delete_device', 'clear_devices', 'save_settings',
			'del_missed', 'clear_missed', 'req_status', 'del_req', 'export', 'quick_start', 'make_page',
			'sku_import', 'quick_link', 'sample_csv', 'test_sms', 'notify_now',
		);
		foreach ( $actions as $a ) {
			add_action( 'admin_post_pmf_' . $a, array( __CLASS__, $a ) );
		}
	}


	protected static function p( $k ) {
		return ( isset( $_POST[ $k ] ) && is_scalar( $_POST[ $k ] ) ) ? wp_unslash( (string) $_POST[ $k ] ) : ''; // phpcs:ignore WordPress.Security.NonceVerification
	}

	protected static function g( $k ) {
		return ( isset( $_GET[ $k ] ) && is_scalar( $_GET[ $k ] ) ) ? sanitize_text_field( wp_unslash( (string) $_GET[ $k ] ) ) : ''; // phpcs:ignore WordPress.Security.NonceVerification
	}

	protected static function arr( $k ) {
		$out = array();
		if ( isset( $_POST[ $k ] ) && is_array( $_POST[ $k ] ) ) { // phpcs:ignore WordPress.Security.NonceVerification
			foreach ( wp_unslash( $_POST[ $k ] ) as $v ) { // phpcs:ignore WordPress.Security.NonceVerification,WordPress.Security.ValidatedSanitizedInput
				if ( is_scalar( $v ) ) {
					$out[] = sanitize_text_field( (string) $v );
				}
			}
		}
		return $out;
	}

	protected static function guard( $action ) {
		if ( ! current_user_can( self::CAP ) ) {
			wp_die( 'دسترسی لازم را ندارید.' );
		}
		check_admin_referer( $action );
	}

	protected static function check_cap() {
		if ( ! current_user_can( self::CAP ) ) {
			wp_die( 'دسترسی لازم را ندارید.' );
		}
	}

	protected static function url( $page, $args = array() ) {
		return add_query_arg( array_merge( array( 'page' => $page ), $args ), admin_url( 'admin.php' ) );
	}

	protected static function back( $url, $msg, $extra = array() ) {
		wp_safe_redirect( add_query_arg( array_merge( array( 'pmf_msg' => $msg ), $extra ), $url ) );
		exit;
	}

	protected static function action_url( $action, $args = array() ) {
		$args['action'] = 'pmf_' . $action;
		return wp_nonce_url( add_query_arg( $args, admin_url( 'admin-post.php' ) ), 'pmf_' . $action );
	}

	protected static function h( $s ) {
		return esc_html( $s );
	}

	protected static function notices() {
		$m = self::g( 'pmf_msg' );
		if ( '' === $m ) {
			return;
		}
		$n    = absint( self::g( 'n' ) );
		$sk   = absint( self::g( 's' ) );
		$miss = absint( self::g( 'm' ) );
		$txt  = self::g( 'txt' );
		$map  = array(
			'saved'       => array( 'success', 'ذخیره شد.' ),
			'deleted'     => array( 'success', 'حذف شد.' ),
			'updated'     => array( 'success', 'به‌روزرسانی شد.' ),
			'imported'    => array( 'success', "$n مورد اضافه شد" . ( $sk ? " ($sk مورد تکراری نادیده گرفته شد)" : '' ) . '.' ),
			'nofile'      => array( 'error', 'متنی برای افزودن وارد نشده یا فایل خوانده نشد.' ),
			'badform'     => array( 'error', 'عنوان فرم و حداقل یک فیلد لازم است.' ),
			'fields_less' => array( 'error', 'چون برای این فرم مشخصات ثبت شده، نمی‌توانید تعداد فیلدها را کمتر کنید.' ),
			'quick'       => array( 'success', 'همه‌چیز ساخته شد! حالا محصولاتتان را به مشخصات وصل کنید (مرحلهٔ بعدی در فهرست زیر).' ),
			'page'        => array( 'success', 'برگه ساخته شد و شورت‌کد داخلش قرار گرفت.' ),
			'skuimp'      => array( 'success', "$n اتصال ثبت شد و $sk مشخصات جدید ساخته شد." . ( $miss ? " $miss ردیف رد شد (SKU پیدا نشد یا ردیف ناقص بود)" . ( '' !== $txt ? ': ' . $txt : '' ) . '.' : '' ) ),
			'linked'      => array( 'success', "$n اتصال جدید ثبت شد." ),
			'needprod'    => array( 'error', 'حداقل یک دسته یا محصول و یک مشخصات را انتخاب کنید.' ),
			'toomany'     => array( 'error', 'تعداد اتصال‌ها بیش‌ازحد زیاد است. انتخاب را کوچک‌تر کنید.' ),
			'smsok'       => array( 'success', 'پیامک آزمایشی: ' . $txt ),
			'smsfail'     => array( 'error', 'ارسال ناموفق بود: ' . $txt ),
			'notified'    => array( 'success', 'پیامک ارسال شد.' ),
			'nostock'     => array( 'error', 'فعلاً هیچ محصول موجودی برای این درخواست پیدا نشد.' ),
		);
		if ( isset( $map[ $m ] ) ) {
			printf( '<div class="notice notice-%s is-dismissible"><p>%s</p></div>', esc_attr( $map[ $m ][0] ), esc_html( $map[ $m ][1] ) );
		}
	}

	protected static function wrap_open() {
		self::check_cap();
		echo '<div class="wrap pmf-wrap">';
		self::notices();
	}

	protected static function counts() {
		global $wpdb;
		$c                 = array();
		$c['forms']        = (int) $wpdb->get_var( 'SELECT COUNT(*) FROM ' . PMF_DB::t( 'forms' ) );
		$c['devices']      = (int) $wpdb->get_var( 'SELECT COUNT(*) FROM ' . PMF_DB::t( 'devices' ) );
		$c['linked']       = (int) $wpdb->get_var( 'SELECT COUNT(DISTINCT product_id) FROM ' . PMF_DB::t( 'compat' ) );
		$c['missed']       = (int) $wpdb->get_var( 'SELECT COALESCE(SUM(hits),0) FROM ' . PMF_DB::t( 'missed' ) );
		$c['req_new']      = (int) $wpdb->get_var( 'SELECT COUNT(*) FROM ' . PMF_DB::t( 'requests' ) . " WHERE status='new'" );
		$c['req_notified'] = (int) $wpdb->get_var( 'SELECT COUNT(*) FROM ' . PMF_DB::t( 'requests' ) . " WHERE status='notified'" );
		return $c;
	}

	protected static function pages() {
		$p = get_option( 'pmf_pages', array() );
		return is_array( $p ) ? $p : array();
	}

	protected static function create_page( $form_id, $title ) {
		$id = wp_insert_post(
			array(
				'post_title'   => $title,
				'post_content' => "<!-- wp:shortcode -->\n[partmatch id=\"" . (int) $form_id . "\"]\n<!-- /wp:shortcode -->",
				'post_status'  => 'publish',
				'post_type'    => 'page',
			),
			true
		);
		if ( is_wp_error( $id ) || ! $id ) {
			return 0;
		}
		$pages             = self::pages();
		$pages[ $form_id ] = (int) $id;
		update_option( 'pmf_pages', $pages );
		return (int) $id;
	}

	/* ================= منو و فایل‌ها ================= */

	public static function menu() {
		global $wpdb;
		$new   = (int) $wpdb->get_var( 'SELECT COUNT(*) FROM ' . PMF_DB::t( 'requests' ) . " WHERE status='new'" );
		$badge = $new ? ' <span class="awaiting-mod">' . $new . '</span>' : '';

		add_menu_page( 'Fitora', 'Fitora' . $badge, self::CAP, 'partmatch', array( __CLASS__, 'page_dashboard' ), 'dashicons-search', 56 );
		add_submenu_page( 'partmatch', 'پیشخوان', 'پیشخوان', self::CAP, 'partmatch', array( __CLASS__, 'page_dashboard' ) );
		add_submenu_page( 'partmatch', 'فرم‌ها و مشخصات', 'فرم‌ها و مشخصات', self::CAP, 'partmatch-forms', array( __CLASS__, 'page_forms' ) );
		add_submenu_page( 'partmatch', 'اتصال محصولات', 'اتصال محصولات', self::CAP, 'partmatch-link', array( __CLASS__, 'page_link' ) );
		add_submenu_page( 'partmatch', 'درخواست‌ها و پیامک', 'درخواست‌ها و پیامک' . $badge, self::CAP, 'partmatch-requests', array( __CLASS__, 'page_requests' ) );
		add_submenu_page( 'partmatch', 'جستجوهای بی‌نتیجه', 'جستجوهای بی‌نتیجه', self::CAP, 'partmatch-missed', array( __CLASS__, 'page_missed' ) );
		add_submenu_page( 'partmatch', 'تنظیمات', 'تنظیمات', self::CAP, 'partmatch-settings', array( __CLASS__, 'page_settings' ) );
		add_submenu_page( 'partmatch', 'راهنما', 'راهنما', self::CAP, 'partmatch-help', array( __CLASS__, 'page_help' ) );
	}

	public static function assets( $hook ) {
		if ( false === strpos( (string) $hook, 'partmatch' ) ) {
			return;
		}
		wp_enqueue_style( 'pmf-admin', PMF_URL . 'assets/css/admin.css', array(), PMF_VERSION );
		wp_enqueue_script( 'pmf-admin-js', PMF_URL . 'assets/js/admin.js', array(), PMF_VERSION, true );

		if ( false !== strpos( (string) $hook, 'partmatch-link' ) ) {
			if ( wp_script_is( 'wc-enhanced-select', 'registered' ) ) {
				wp_enqueue_script( 'wc-enhanced-select' );
				wp_enqueue_style( 'woocommerce_admin_styles' );
			}
			wp_enqueue_script( 'pmf-link', PMF_URL . 'assets/js/link.js', array(), PMF_VERSION, true );
			$forms = array();
			foreach ( PMF_DB::forms() as $f ) {
				$forms[ (int) $f->id ] = array( 'title' => $f->title, 'fields' => $f->fields );
			}
			wp_localize_script( 'pmf-link', 'PMF_LINK', array( 'ajax' => admin_url( 'admin-ajax.php' ), 'forms' => (object) $forms ) );
		}
	}

	/* ================= پیشخوان ================= */

	public static function page_dashboard() {
		self::wrap_open();
		$forms = PMF_DB::forms();
		$c     = self::counts();
		$pages = self::pages();
		?>
		<div class="pmf-hero">
			<div>
				<h1>Fitora <small style="font-size:.45em;opacity:.8">توسعه: حبیب موسوی</small></h1>
				<p>مشتری مشخصاتش را انتخاب می‌کند و فقط محصولات سازگار را می‌بیند؛ اگر هم محصول نبود، شمارهٔ او را می‌گیرید و به‌محض موجود شدن، پیامک می‌رود.</p>
			</div>
			<div class="pmf-hero-art" aria-hidden="true">🔎</div>
		</div>

		<div class="pmf-panel pmf-cat">
			<h2 class="pmf-h2">📄 کاتالوگ خودکار تازه‌های موجودشده <small>بدون هیچ کار دستی</small></h2>
			<p>هر محصولی که موجود شود، خودش با تاریخ شمسی داخل یک کاتالوگ آمادهٔ زیبا قرار می‌گیرد و بعد از <?php echo self::h( PMF_Date::fa_digits( (int) PMF_DB::setting( 'catalog_days' ) ) ); ?> روز خودکار کنار می‌رود. مشتری با یک دکمه آن را PDF می‌کند. این لینک را هر جا خواستید (کانال، اینستاگرام، پیامک) بگذارید:</p>
			<div class="pmf-code-row"><code dir="ltr"><?php echo esc_html( PMF_Notify::catalog_url() ); ?></code> <button type="button" class="button pmf-copy" data-copy="<?php echo esc_attr( PMF_Notify::catalog_url() ); ?>">کپی</button> <a class="button button-primary" target="_blank" href="<?php echo esc_url( PMF_Notify::catalog_url() ); ?>">مشاهدهٔ کاتالوگ</a> <a class="button" target="_blank" href="<?php echo esc_url( add_query_arg( 'print', '1', PMF_Notify::catalog_url() ) ); ?>">دریافت PDF</a></div>
			<p class="description">پیامک «موجود شد» هم برای هر مشتری یک لینک اختصاصی از همین کاتالوگ می‌سازد؛ بخش «مخصوص شما» بالای فهرست می‌آید.</p>
		</div>

		<?php if ( ! $forms ) : ?>
			<h2 class="pmf-h2">از چه نوع فروشگاهی دارید؟ یکی را انتخاب کنید</h2>
			<p class="pmf-lead">با یک کلیک فرم، نمونه‌ها و صفحهٔ آمادهٔ جستجو ساخته می‌شود. بعداً هر چیزی را می‌توانید تغییر دهید.</p>
			<?php self::template_picker(); ?>
		<?php else : ?>
			<div class="pmf-stats">
				<div class="pmf-stat"><b><?php echo self::h( number_format_i18n( $c['forms'] ) ); ?></b><span>فرم</span></div>
				<div class="pmf-stat"><b><?php echo self::h( number_format_i18n( $c['devices'] ) ); ?></b><span>مشخصات ثبت‌شده</span></div>
				<div class="pmf-stat"><b><?php echo self::h( number_format_i18n( $c['linked'] ) ); ?></b><span>محصول متصل‌شده</span></div>
				<div class="pmf-stat"><b><?php echo self::h( number_format_i18n( $c['missed'] ) ); ?></b><span>جستجوی بی‌نتیجه</span></div>
				<div class="pmf-stat <?php echo $c['req_new'] ? 'is-hot' : ''; ?>"><b><?php echo self::h( number_format_i18n( $c['req_new'] ) ); ?></b><span>درخواست در انتظار</span></div>
				<div class="pmf-stat"><b><?php echo self::h( number_format_i18n( $c['req_notified'] ) ); ?></b><span>پیامک ارسال‌شده</span></div>
			</div>

			<?php
			$steps = array(
				array( $c['forms'] > 0, 'فرم ساخته شد', '', '' ),
				array( $c['devices'] > 0, 'مشخصات (مثلاً مدل‌ها) اضافه شد', 'افزودن مشخصات', self::url( 'partmatch-forms' ) ),
				array( $c['linked'] > 0, 'حداقل یک محصول به مشخصات وصل شد', 'اتصال محصولات', self::url( 'partmatch-link' ) ),
				array( ! empty( $pages ), 'برگهٔ جستجو ساخته شد', '', '' ),
				array( true, 'اطلاع‌رسانی ایمیلی «موجود شد» فعال است', '', '' ),
			);
			$done  = 0;
			foreach ( $steps as $s ) {
				$done += $s[0] ? 1 : 0;
			}
			?>
			<div class="pmf-panel">
				<h2 class="pmf-h2">شروع سریع <small><?php echo self::h( PMF_Date::fa_digits( $done . ' از ' . count( $steps ) ) ); ?></small></h2>
				<div class="pmf-bar"><i style="width:<?php echo (int) round( $done / count( $steps ) * 100 ); ?>%"></i></div>
				<ul class="pmf-check">
					<?php foreach ( $steps as $s ) : ?>
						<li class="<?php echo $s[0] ? 'is-done' : ''; ?>">
							<span class="pmf-tick"><?php echo $s[0] ? '✓' : ''; ?></span>
							<span class="pmf-lbl"><?php echo self::h( $s[1] ); ?></span>
							<?php if ( ! $s[0] && $s[2] ) : ?>
								<a class="button button-primary" href="<?php echo esc_url( $s[3] ); ?>"><?php echo self::h( $s[2] ); ?></a>
							<?php endif; ?>
						</li>
					<?php endforeach; ?>
				</ul>
			</div>

			<h2 class="pmf-h2">فرم‌های شما</h2>
			<div class="pmf-forms">
				<?php
				global $wpdb;
				foreach ( $forms as $f ) :
					$linked = (int) $wpdb->get_var( $wpdb->prepare( 'SELECT COUNT(DISTINCT c.product_id) FROM ' . PMF_DB::t( 'compat' ) . ' c INNER JOIN ' . PMF_DB::t( 'devices' ) . ' d ON d.id=c.device_id WHERE d.form_id=%d', $f->id ) );
					$pid    = isset( $pages[ $f->id ] ) ? (int) $pages[ $f->id ] : 0;
					?>
					<div class="pmf-form-card">
						<h3><?php echo self::h( $f->title ); ?></h3>
						<p class="pmf-chain"><?php echo self::h( implode( ' ← ', $f->fields ) ); ?></p>
						<p class="pmf-mini"><?php echo self::h( number_format_i18n( PMF_DB::device_count( $f->id ) ) ); ?> مشخصات · <?php echo self::h( number_format_i18n( $linked ) ); ?> محصول متصل</p>
						<div class="pmf-code-row"><code>[partmatch id="<?php echo (int) $f->id; ?>"]</code> <button type="button" class="button pmf-copy" data-copy='[partmatch id="<?php echo (int) $f->id; ?>"]'>کپی</button></div>
						<div class="pmf-actions">
							<a class="button" href="<?php echo esc_url( self::url( 'partmatch-forms', array( 'action' => 'edit', 'form' => $f->id ) ) ); ?>">ویرایش و مشخصات</a>
							<?php if ( $pid && 'publish' === get_post_status( $pid ) ) : ?>
								<a class="button button-primary" target="_blank" href="<?php echo esc_url( get_permalink( $pid ) ); ?>">مشاهدهٔ صفحه</a>
							<?php else : ?>
								<a class="button button-primary" href="<?php echo esc_url( self::action_url( 'make_page', array( 'form' => $f->id ) ) ); ?>">ساخت برگه</a>
							<?php endif; ?>
						</div>
					</div>
				<?php endforeach; ?>
			</div>

			<details class="pmf-panel">
				<summary>ساخت فرم جدید از قالب آماده</summary>
				<?php self::template_picker(); ?>
			</details>
		<?php endif; ?>
		</div>
		<?php
	}

	protected static function template_picker() {
		?>
		<form method="post" action="<?php echo esc_url( admin_url( 'admin-post.php' ) ); ?>" class="pmf-picker">
			<input type="hidden" name="action" value="pmf_quick_start">
			<?php wp_nonce_field( 'pmf_quick_start' ); ?>
			<div class="pmf-tpl-opts">
				<label><input type="checkbox" name="with_samples" value="1" checked> نمونه‌های آماده اضافه شود (بعداً قابل حذف)</label>
				<label><input type="checkbox" name="create_page" value="1" checked> برگهٔ جستجو هم ساخته شود</label>
			</div>
			<div class="pmf-tpls">
				<?php foreach ( PMF_Templates::all() as $key => $t ) : ?>
					<button type="submit" name="tpl" value="<?php echo esc_attr( $key ); ?>" class="pmf-tpl">
						<span class="pmf-tpl-i"><?php echo esc_html( $t['icon'] ); ?></span>
						<strong><?php echo self::h( $t['title'] ); ?></strong>
						<em><?php echo self::h( $t['desc'] ); ?></em>
					</button>
				<?php endforeach; ?>
			</div>
		</form>
		<?php
	}

	public static function quick_start() {
		self::guard( 'pmf_quick_start' );
		$key = sanitize_key( self::p( 'tpl' ) );
		$all = PMF_Templates::all();
		if ( ! isset( $all[ $key ] ) ) {
			self::back( self::url( 'partmatch' ), 'badform' );
		}
		if ( 'custom' === $key ) {
			wp_safe_redirect( self::url( 'partmatch-forms', array( 'action' => 'edit', 'form' => 0 ) ) );
			exit;
		}
		$t   = $all[ $key ];
		$fid = PMF_DB::save_form( 0, $t['form'], $t['fields'] );
		if ( self::p( 'with_samples' ) ) {
			PMF_DB::import_lines( $fid, $t['samples'], count( $t['fields'] ) );
		}
		if ( self::p( 'create_page' ) ) {
			self::create_page( $fid, $t['form'] );
		}
		self::back( self::url( 'partmatch' ), 'quick' );
	}

	public static function make_page() {
		self::guard( 'pmf_make_page' );
		$fid  = absint( self::g( 'form' ) );
		$form = PMF_DB::form( $fid );
		if ( $form ) {
			self::create_page( $fid, $form->title );
		}
		self::back( self::url( 'partmatch' ), 'page' );
	}

	/* ================= فرم‌ها و مشخصات ================= */

	public static function page_forms() {
		self::wrap_open();
		if ( 'edit' === self::g( 'action' ) ) {
			self::screen_edit();
		} else {
			self::screen_list();
		}
		echo '</div>';
	}

	protected static function screen_list() {
		$forms = PMF_DB::forms();
		echo '<h1 class="wp-heading-inline">فرم‌ها و مشخصات</h1> <a class="page-title-action" href="' . esc_url( self::url( 'partmatch-forms', array( 'action' => 'edit', 'form' => 0 ) ) ) . '">افزودن فرم</a><hr class="wp-header-end">';
		if ( ! $forms ) {
			echo '<div class="pmf-panel"><p>هنوز فرمی ندارید. ساده‌ترین راه، ساخت از «قالب آماده» در <a href="' . esc_url( self::url( 'partmatch' ) ) . '">پیشخوان</a> است.</p></div>';
			return;
		}
		echo '<table class="widefat striped pmf-table"><thead><tr><th>عنوان</th><th>فیلدها</th><th>مشخصات</th><th>شورت‌کد</th><th></th></tr></thead><tbody>';
		foreach ( $forms as $f ) {
			printf(
				'<tr><td><strong><a href="%s">%s</a></strong></td><td>%s</td><td>%s</td><td><code>[partmatch id="%d"]</code></td><td><a href="%s">ویرایش</a> | <a href="%s" onclick="return confirm(\'این فرم با تمام مشخصات و اتصال‌هایش حذف می‌شود. مطمئنید؟\')" style="color:#b32d2e">حذف</a></td></tr>',
				esc_url( self::url( 'partmatch-forms', array( 'action' => 'edit', 'form' => $f->id ) ) ),
				self::h( $f->title ),
				self::h( implode( ' ← ', $f->fields ) ),
				self::h( number_format_i18n( PMF_DB::device_count( $f->id ) ) ),
				(int) $f->id,
				esc_url( self::url( 'partmatch-forms', array( 'action' => 'edit', 'form' => $f->id ) ) ),
				esc_url( self::action_url( 'delete_form', array( 'form' => $f->id ) ) )
			);
		}
		echo '</tbody></table>';
	}

	protected static function screen_edit() {
		global $wpdb;
		$fid  = absint( self::g( 'form' ) );
		$form = $fid ? PMF_DB::form( $fid ) : null;
		if ( $fid && ! $form ) {
			echo '<p>فرم پیدا نشد.</p>';
			return;
		}
		echo '<h1>' . ( $form ? 'ویرایش فرم: ' . self::h( $form->title ) : 'افزودن فرم جدید' ) . '</h1>';
		echo '<p><a href="' . esc_url( self::url( 'partmatch-forms' ) ) . '">← بازگشت به فهرست فرم‌ها</a></p>';
		?>
		<div class="pmf-panel">
			<form method="post" action="<?php echo esc_url( admin_url( 'admin-post.php' ) ); ?>">
				<input type="hidden" name="action" value="pmf_save_form">
				<input type="hidden" name="form_id" value="<?php echo (int) $fid; ?>">
				<?php wp_nonce_field( 'pmf_save_form' ); ?>
				<table class="form-table">
					<tr>
						<th><label for="pmf_title">عنوان فرم</label></th>
						<td><input type="text" class="regular-text" id="pmf_title" name="title" value="<?php echo esc_attr( $form ? $form->title : '' ); ?>" required></td>
					</tr>
					<tr>
						<th><label for="pmf_fields">فیلدهای فرم</label></th>
						<td>
							<textarea id="pmf_fields" name="fields" rows="5" class="regular-text" placeholder="برند&#10;مدل&#10;سال" required><?php echo esc_textarea( $form ? implode( "\n", $form->fields ) : '' ); ?></textarea>
							<p class="description">هر خط یک فیلد، به ترتیب انتخاب مشتری (حداکثر ۵). هر فیلد بعد از انتخاب فیلد قبلی باز می‌شود.</p>
						</td>
					</tr>
				</table>
				<?php submit_button( $form ? 'ذخیره تغییرات' : 'ساخت فرم' ); ?>
			</form>
		</div>
		<?php
		if ( ! $form ) {
			return;
		}
		$n = count( $form->fields );
		?>
		<div class="pmf-panel">
			<h2 class="pmf-h2">افزودن مشخصات</h2>
			<p>هر خط یک مورد؛ مقدارها را با <code>&gt;</code> جدا کنید. مثال برای همین فرم:</p>
			<pre class="pmf-sample"><?php echo esc_html( implode( ' > ', array_map( function ( $l ) { return $l . ' ۱'; }, $form->fields ) ) ); ?></pre>
			<form method="post" enctype="multipart/form-data" action="<?php echo esc_url( admin_url( 'admin-post.php' ) ); ?>">
				<input type="hidden" name="action" value="pmf_import">
				<input type="hidden" name="form_id" value="<?php echo (int) $form->id; ?>">
				<?php wp_nonce_field( 'pmf_import' ); ?>
				<textarea name="lines" rows="7" class="large-text code" placeholder="هر خط یک مورد، با > جدا کنید"></textarea>
				<p>
					یا فایل CSV / TXT:
					<input type="file" name="csv" accept=".csv,.txt">
					<label><input type="checkbox" name="skip_header" value="1"> سطر اول عنوان ستون‌هاست</label>
				</p>
				<?php submit_button( 'افزودن مشخصات', 'primary', 'submit', false ); ?>
			</form>
		</div>
		<?php

		$s     = self::g( 's_dev' );
		$paged = max( 1, absint( self::g( 'paged' ) ) );
		$per   = 50;
		$t     = PMF_DB::t( 'devices' );
		$where = $wpdb->prepare( 'form_id=%d', $form->id );
		if ( '' !== $s ) {
			$like   = '%' . $wpdb->esc_like( $s ) . '%';
			$where .= $wpdb->prepare( ' AND (l1 LIKE %s OR l2 LIKE %s OR l3 LIKE %s OR l4 LIKE %s OR l5 LIKE %s)', $like, $like, $like, $like, $like );
		}
		$total = (int) $wpdb->get_var( "SELECT COUNT(*) FROM $t WHERE $where" );
		$rows  = $wpdb->get_results( "SELECT * FROM $t WHERE $where ORDER BY l1,l2,l3,l4,l5 LIMIT " . (int) $per . ' OFFSET ' . (int) ( ( $paged - 1 ) * $per ) );
		?>
		<div class="pmf-panel">
			<h2 class="pmf-h2">مشخصات ثبت‌شده (<?php echo self::h( number_format_i18n( $total ) ); ?>)</h2>
			<form method="get" class="pmf-inline">
				<input type="hidden" name="page" value="partmatch-forms">
				<input type="hidden" name="action" value="edit">
				<input type="hidden" name="form" value="<?php echo (int) $form->id; ?>">
				<input type="search" name="s_dev" value="<?php echo esc_attr( $s ); ?>" placeholder="جستجو">
				<button class="button">جستجو</button>
				<?php if ( $total ) : ?>
					<a class="button" style="color:#b32d2e" href="<?php echo esc_url( self::action_url( 'clear_devices', array( 'form' => $form->id ) ) ); ?>" onclick="return confirm('همهٔ مشخصات این فرم و اتصال‌هایشان پاک شود؟')">پاک‌کردن همه</a>
				<?php endif; ?>
			</form>
			<table class="widefat striped pmf-table" style="margin-top:10px">
				<thead><tr><?php foreach ( $form->fields as $f ) { echo '<th>' . self::h( $f ) . '</th>'; } ?><th style="width:60px"></th></tr></thead>
				<tbody>
				<?php
				if ( ! $rows ) {
					echo '<tr><td colspan="' . (int) ( $n + 1 ) . '">موردی یافت نشد.</td></tr>';
				}
				foreach ( $rows as $r ) {
					echo '<tr>';
					for ( $i = 1; $i <= $n; $i++ ) {
						$col = 'l' . $i;
						echo '<td>' . self::h( $r->$col ) . '</td>';
					}
					echo '<td><a style="color:#b32d2e" href="' . esc_url( self::action_url( 'delete_device', array( 'id' => $r->id, 'form' => $form->id ) ) ) . '" onclick="return confirm(\'حذف شود؟\')">حذف</a></td></tr>';
				}
				?>
				</tbody>
			</table>
			<?php
			$links = paginate_links(
				array(
					'base'      => add_query_arg( 'paged', '%#%' ),
					'format'    => '',
					'current'   => $paged,
					'total'     => max( 1, (int) ceil( $total / $per ) ),
					'prev_text' => '‹',
					'next_text' => '›',
				)
			);
			if ( $links ) {
				echo '<div class="tablenav"><div class="tablenav-pages">' . wp_kses_post( $links ) . '</div></div>';
			}
			?>
		</div>
		<?php
	}

	public static function save_form() {
		self::guard( 'pmf_save_form' );
		$id     = absint( self::p( 'form_id' ) );
		$title  = sanitize_text_field( self::p( 'title' ) );
		$fields = array_values( array_filter( array_map( 'sanitize_text_field', preg_split( '/\r\n|\r|\n/', self::p( 'fields' ) ) ), 'strlen' ) );
		$fields = array_slice( $fields, 0, PMF_DB::MAX_LEVELS );
		$edit   = self::url( 'partmatch-forms', array( 'action' => 'edit', 'form' => $id ) );
		if ( '' === $title || ! $fields ) {
			self::back( $edit, 'badform' );
		}
		if ( $id ) {
			$old = PMF_DB::form( $id );
			if ( $old && PMF_DB::device_count( $id ) > 0 && count( $fields ) < count( $old->fields ) ) {
				self::back( $edit, 'fields_less' );
			}
		}
		$id = PMF_DB::save_form( $id, $title, $fields );
		self::back( self::url( 'partmatch-forms', array( 'action' => 'edit', 'form' => $id ) ), 'saved' );
	}

	public static function delete_form() {
		self::guard( 'pmf_delete_form' );
		PMF_DB::delete_form( absint( self::g( 'form' ) ) );
		self::back( self::url( 'partmatch-forms' ), 'deleted' );
	}

	/** متن فایل آپلودشده (CSV/TXT) با پشتیبانی از BOM و ویندوز-۱۲۵۶. */
	protected static function read_upload( $skip_header ) {
		if ( empty( $_FILES['csv']['tmp_name'] ) || ! is_uploaded_file( $_FILES['csv']['tmp_name'] ) ) { // phpcs:ignore
			return '';
		}
		$name = isset( $_FILES['csv']['name'] ) ? sanitize_file_name( wp_unslash( $_FILES['csv']['name'] ) ) : ''; // phpcs:ignore
		$ext  = strtolower( pathinfo( $name, PATHINFO_EXTENSION ) );
		if ( ! in_array( $ext, array( 'csv', 'txt' ), true ) ) {
			return '';
		}
		$c = file_get_contents( $_FILES['csv']['tmp_name'] ); // phpcs:ignore
		if ( false === $c ) {
			return '';
		}
		$c = preg_replace( '/^\xEF\xBB\xBF/', '', $c );
		if ( function_exists( 'mb_check_encoding' ) && ! mb_check_encoding( $c, 'UTF-8' ) && function_exists( 'iconv' ) ) {
			$conv = @iconv( 'Windows-1256', 'UTF-8//IGNORE', $c ); // phpcs:ignore
			if ( false !== $conv ) {
				$c = $conv;
			}
		}
		if ( $skip_header ) {
			$c = preg_replace( '/^[^\r\n]*(\r\n|\r|\n)/', '', $c, 1 );
		}
		return $c;
	}

	public static function import() {
		self::guard( 'pmf_import' );
		$fid  = absint( self::p( 'form_id' ) );
		$form = PMF_DB::form( $fid );
		$edit = self::url( 'partmatch-forms', array( 'action' => 'edit', 'form' => $fid ) );
		if ( ! $form ) {
			self::back( self::url( 'partmatch-forms' ), 'badform' );
		}
		$text = self::p( 'lines' ) . "\n" . self::read_upload( (bool) self::p( 'skip_header' ) );
		if ( '' === trim( $text ) ) {
			self::back( $edit, 'nofile' );
		}
		if ( function_exists( 'set_time_limit' ) ) {
			@set_time_limit( 300 ); // phpcs:ignore
		}
		list( $added, $skipped ) = PMF_DB::import_lines( $fid, $text, count( $form->fields ) );
		self::back( $edit, 'imported', array( 'n' => $added, 's' => $skipped ) );
	}

	public static function delete_device() {
		self::guard( 'pmf_delete_device' );
		PMF_DB::delete_device( absint( self::g( 'id' ) ) );
		self::back( self::url( 'partmatch-forms', array( 'action' => 'edit', 'form' => absint( self::g( 'form' ) ) ) ), 'deleted' );
	}

	public static function clear_devices() {
		self::guard( 'pmf_clear_devices' );
		global $wpdb;
		$fid = absint( self::g( 'form' ) );
		$wpdb->query( $wpdb->prepare( 'DELETE c FROM ' . PMF_DB::t( 'compat' ) . ' c INNER JOIN ' . PMF_DB::t( 'devices' ) . ' d ON d.id=c.device_id WHERE d.form_id=%d', $fid ) );
		$wpdb->delete( PMF_DB::t( 'devices' ), array( 'form_id' => $fid ), array( '%d' ) );
		self::back( self::url( 'partmatch-forms', array( 'action' => 'edit', 'form' => $fid ) ), 'deleted' );
	}

	/* ================= اتصال محصولات ================= */

	public static function page_link() {
		self::wrap_open();
		$forms = PMF_DB::forms();
		echo '<h1>اتصال محصولات به مشخصات</h1>';
		if ( ! $forms ) {
			echo '<div class="pmf-panel"><p>اول یک فرم بسازید (پیشخوان ← قالب آماده).</p></div></div>';
			return;
		}
		?>
		<div class="pmf-panel">
			<h2 class="pmf-h2">۱) ورود یک‌جای سازگاری با SKU <small>سریع‌ترین راه برای فروشگاه‌های بزرگ</small></h2>
			<p>هر سطر: <b>SKU محصول</b>، بعد مقدارهای فیلدها، و در صورت نیاز «نوع» (تأیید / شرط / جایگزین) و «توضیح». مشخصاتی که وجود نداشته باشد خودکار ساخته می‌شود.</p>
			<form method="post" enctype="multipart/form-data" action="<?php echo esc_url( admin_url( 'admin-post.php' ) ); ?>">
				<input type="hidden" name="action" value="pmf_sku_import">
				<?php wp_nonce_field( 'pmf_sku_import' ); ?>
				<p>
					<label>فرم: <select name="form_id"><?php foreach ( $forms as $f ) { echo '<option value="' . (int) $f->id . '">' . self::h( $f->title ) . '</option>'; } ?></select></label>
					— دانلود نمونهٔ CSV:
					<?php foreach ( $forms as $f ) { echo '<a class="button button-small" href="' . esc_url( self::action_url( 'sample_csv', array( 'form' => $f->id ) ) ) . '">' . self::h( $f->title ) . '</a> '; } ?>
				</p>
				<textarea name="lines" rows="6" class="large-text code" placeholder="SKU-1001 | پژو | 206 | 1398 | تأیید&#10;SKU-1002 | پژو | 206 | 1399 | شرط | فقط مدل دارای ABS"></textarea>
				<p>
					یا فایل CSV (از اکسل با «Save as CSV UTF-8»):
					<input type="file" name="csv" accept=".csv,.txt">
					<label><input type="checkbox" name="skip_header" value="1" checked> سطر اول عنوان ستون‌هاست</label>
				</p>
				<?php submit_button( 'ثبت سازگاری‌ها', 'primary', 'submit', false ); ?>
			</form>
		</div>

		<div class="pmf-panel">
			<h2 class="pmf-h2">۲) اتصال سریع بر اساس دسته یا محصولات <small>بدون رفتن سراغ تک‌تک محصولات</small></h2>
			<form method="post" action="<?php echo esc_url( admin_url( 'admin-post.php' ) ); ?>">
				<input type="hidden" name="action" value="pmf_quick_link">
				<?php wp_nonce_field( 'pmf_quick_link' ); ?>
				<table class="form-table">
					<tr><th>فرم</th><td><select name="form_id" id="pmf_ql_form"><?php foreach ( $forms as $f ) { echo '<option value="' . (int) $f->id . '">' . self::h( $f->title ) . '</option>'; } ?></select></td></tr>
					<tr><th>مشخصات</th><td><span id="pmf_ql_levels" class="pmf-levels"></span><p class="description">سطح‌های بعدی را «همه» بگذارید تا کل زیرمجموعه (مثلاً همهٔ مدل‌های یک برند) انتخاب شود.</p></td></tr>
					<tr><th>نوع سازگاری</th><td>
						<select name="ctype"><option value="verified">سازگار و تأیید شده</option><option value="conditional">سازگار با شرایط مشخص</option><option value="alt">جایگزین تأیید شده</option></select>
						<input type="text" name="note" class="regular-text" placeholder="شرط / توضیح (اختیاری)">
					</td></tr>
					<tr><th>دسته‌های محصول</th><td>
						<select name="cats[]" multiple size="6" style="min-width:300px">
							<?php
							$terms = get_terms( array( 'taxonomy' => 'product_cat', 'hide_empty' => false ) );
							if ( ! is_wp_error( $terms ) ) {
								foreach ( $terms as $tm ) {
									echo '<option value="' . (int) $tm->term_id . '">' . self::h( $tm->name ) . '</option>';
								}
							}
							?>
						</select>
						<p class="description">برای انتخاب چند دسته، Ctrl (یا Cmd) را نگه دارید.</p>
					</td></tr>
					<tr><th>و/یا محصولات مشخص</th><td>
						<select class="wc-product-search" multiple="multiple" style="width:50%;min-width:300px" name="products[]" data-placeholder="جستجوی محصول..." data-action="woocommerce_json_search_products_and_variations"></select>
					</td></tr>
				</table>
				<?php submit_button( 'اتصال انجام شود', 'primary', 'submit', false ); ?>
			</form>
		</div>
		<p class="pmf-lead">برای ثبت دقیق‌ترِ شرط‌ها روی یک محصول، می‌توانید از تب «سازگاری محصول» در صفحهٔ ویرایش همان محصول هم استفاده کنید.</p>
		</div>
		<?php
	}

	protected static function map_type( $s ) {
		$s = PMF_DB::lc( trim( (string) $s ) );
		if ( false !== strpos( $s, 'شرط' ) || false !== strpos( $s, 'cond' ) ) {
			return 'conditional';
		}
		if ( false !== strpos( $s, 'جایگزین' ) || false !== strpos( $s, 'alt' ) ) {
			return 'alt';
		}
		return 'verified';
	}

	protected static function parse_row( $line ) {
		if ( false !== strpos( $line, "\t" ) ) {
			$cols = explode( "\t", $line );
		} elseif ( false !== strpos( $line, '|' ) ) {
			$cols = explode( '|', $line );
		} else {
			$cols = str_getcsv( $line );
		}
		return array_map( 'trim', $cols );
	}

	protected static function product_by_sku( $sku ) {
		$id = wc_get_product_id_by_sku( PMF_DB::digits( $sku ) );
		if ( ! $id ) {
			return 0;
		}
		$p = wc_get_product( $id );
		return ( $p && $p->is_type( 'variation' ) ) ? (int) $p->get_parent_id() : (int) $id;
	}

	public static function sku_import() {
		self::guard( 'pmf_sku_import' );
		$fid  = absint( self::p( 'form_id' ) );
		$form = PMF_DB::form( $fid );
		$back = self::url( 'partmatch-link' );
		if ( ! $form ) {
			self::back( $back, 'badform' );
		}
		$text = self::p( 'lines' ) . "\n" . self::read_upload( (bool) self::p( 'skip_header' ) );
		if ( '' === trim( $text ) ) {
			self::back( $back, 'nofile' );
		}
		if ( function_exists( 'set_time_limit' ) ) {
			@set_time_limit( 300 ); // phpcs:ignore
		}
		$n       = count( $form->fields );
		$links   = 0;
		$new     = 0;
		$bad     = 0;
		$missing = array();
		foreach ( preg_split( '/\r\n|\r|\n/', $text ) as $line ) {
			if ( '' === trim( $line ) ) {
				continue;
			}
			$cols   = self::parse_row( $line );
			$sku    = isset( $cols[0] ) ? sanitize_text_field( $cols[0] ) : '';
			$levels = array_map( 'sanitize_text_field', array_slice( $cols, 1, $n ) );
			while ( $levels && '' === end( $levels ) ) {
				array_pop( $levels );
			}
			if ( '' === $sku || ! $levels || '' === $levels[0] ) {
				$bad++;
				continue;
			}
			$pid = self::product_by_sku( $sku );
			if ( ! $pid ) {
				$bad++;
				$missing[] = $sku;
				continue;
			}
			$type = self::map_type( isset( $cols[ $n + 1 ] ) ? $cols[ $n + 1 ] : '' );
			$note = isset( $cols[ $n + 2 ] ) ? sanitize_text_field( $cols[ $n + 2 ] ) : '';
			list( $did, $created ) = PMF_DB::ensure_device( $fid, $levels );
			if ( $created ) {
				$new++;
			}
			$links += PMF_DB::link_one( $pid, $did, $type, $note ) ? 1 : 0;
		}
		self::back( $back, 'skuimp', array( 'n' => $links, 's' => $new, 'm' => $bad, 'txt' => rawurlencode( implode( '، ', array_slice( array_unique( $missing ), 0, 8 ) ) ) ) );
	}

	public static function quick_link() {
		self::guard( 'pmf_quick_link' );
		$fid  = absint( self::p( 'form_id' ) );
		$back = self::url( 'partmatch-link' );
		$vals = array();
		foreach ( self::arr( 'values' ) as $v ) {
			if ( '' === $v ) {
				break;
			}
			$vals[] = $v;
		}
		$pids = array_filter( array_map( 'absint', self::arr( 'products' ) ) );
		$slug = array();
		foreach ( array_filter( array_map( 'absint', self::arr( 'cats' ) ) ) as $tid ) {
			$tm = get_term( $tid, 'product_cat' );
			if ( $tm && ! is_wp_error( $tm ) ) {
				$slug[] = $tm->slug;
			}
		}
		if ( $slug ) {
			$ids  = wc_get_products( array( 'limit' => -1, 'status' => 'publish', 'category' => $slug, 'return' => 'ids' ) );
			$pids = array_merge( $pids, (array) $ids );
		}
		$pids = array_values( array_unique( array_map( 'intval', $pids ) ) );
		if ( ! PMF_DB::form( $fid ) || ! $vals || ! $pids ) {
			self::back( $back, 'needprod' );
		}
		$devs = array();
		foreach ( PMF_DB::devices_by_prefix( $fid, $vals, 5000 ) as $d ) {
			$devs[] = (int) $d->id;
		}
		if ( ! $devs ) {
			self::back( $back, 'needprod' );
		}
		if ( count( $pids ) * count( $devs ) > 100000 ) {
			self::back( $back, 'toomany' );
		}
		$added = PMF_DB::link_products( $pids, $devs, self::p( 'ctype' ), sanitize_text_field( self::p( 'note' ) ) );
		self::back( $back, 'linked', array( 'n' => $added ) );
	}

	public static function sample_csv() {
		self::guard( 'pmf_sample_csv' );
		$form = PMF_DB::form( absint( self::g( 'form' ) ) );
		if ( ! $form ) {
			wp_die( 'فرم پیدا نشد.' );
		}
		nocache_headers();
		header( 'Content-Type: text/csv; charset=utf-8' );
		header( 'Content-Disposition: attachment; filename=partmatch-sample-' . (int) $form->id . '.csv' );
		$out = fopen( 'php://output', 'w' );
		fwrite( $out, "\xEF\xBB\xBF" );
		fputcsv( $out, array_merge( array( 'SKU' ), $form->fields, array( 'نوع (تأیید / شرط / جایگزین)', 'توضیح' ) ) );
		$ex1 = array( 'SKU-1001' );
		$ex2 = array( 'SKU-1002' );
		foreach ( $form->fields as $i => $f ) {
			$ex1[] = 'نمونه ' . $f . ' ۱';
			$ex2[] = 'نمونه ' . $f . ' ۲';
		}
		fputcsv( $out, array_merge( $ex1, array( 'تأیید', '' ) ) );
		fputcsv( $out, array_merge( $ex2, array( 'شرط', 'مثلاً: فقط نسخهٔ دارای ABS' ) ) );
		fclose( $out ); // phpcs:ignore
		exit;
	}

	/* ================= درخواست‌ها و پیامک ================= */

	public static function page_requests() {
		global $wpdb;
		self::wrap_open();
		$rows = $wpdb->get_results( 'SELECT * FROM ' . PMF_DB::t( 'requests' ) . " ORDER BY (status='new') DESC, id DESC LIMIT 500" );
		echo '<h1 class="wp-heading-inline">درخواست‌ها و پیامک</h1> ';
		if ( $rows ) {
			echo '<a class="page-title-action" href="' . esc_url( self::action_url( 'export', array( 'what' => 'requests' ) ) ) . '">خروجی CSV</a>';
		}
		echo '<hr class="wp-header-end">';
		echo '<div class="notice notice-info inline"><p>اطلاع‌رسانی به‌صورت <b>ایمیل</b> انجام می‌شود (رایگان و بدون تنظیمات). پیامک اختیاری است و از «تنظیمات» فعال می‌شود.</p></div>';
		echo '<p class="pmf-lead">مشتری‌ها از دو راه درخواست می‌دهند: وقتی محصولی پیدا نکرده‌اند، یا دکمهٔ «خبرم کن» در صفحهٔ محصولِ ناموجود. به‌محض موجود شدن محصول، پیامک خودکار می‌رود.</p>';
		echo '<table class="widefat striped pmf-table"><thead><tr><th>تاریخ</th><th>مشتری</th><th>درخواست</th><th>وضعیت</th><th></th></tr></thead><tbody>';
		if ( ! $rows ) {
			echo '<tr><td colspan="5">هنوز درخواستی ثبت نشده است.</td></tr>';
		}
		$labels = array(
			'new'      => '<span class="pmf-pill is-new">در انتظار موجود شدن</span>',
			'notified' => '<span class="pmf-pill is-ok">اطلاع‌رسانی شد</span>',
			'failed'   => '<span class="pmf-pill is-bad">ارسال ناموفق</span>',
			'done'     => '<span class="pmf-pill">پیگیری شد</span>',
		);
		$kinds  = array( 'product' => 'خبرم کن', 'code' => 'جستجوی کد', 'search' => 'جستجوی مشخصات' );
		foreach ( (array) $rows as $r ) {
			$st  = isset( $labels[ $r->status ] ) ? $labels[ $r->status ] : self::h( $r->status );
			$sub = isset( $kinds[ $r->kind ] ) ? $kinds[ $r->kind ] : '';
			printf(
				'<tr><td>%s%s</td><td>%s<br><a dir="ltr" href="mailto:%s">%s</a><br><a dir="ltr" href="tel:%s">%s</a></td><td><small>%s</small><br>%s%s</td><td>%s%s</td><td class="pmf-row-actions">%s<a href="%s">%s</a> <a style="color:#b32d2e" href="%s" onclick="return confirm(\'حذف شود؟\')">حذف</a></td></tr>',
				self::h( PMF_Date::format( $r->created_at ) ),
				$r->notified_at ? '<br><small>اطلاع‌رسانی: ' . self::h( PMF_Date::format( $r->notified_at ) ) . '</small>' : '',
				self::h( $r->name ),
				esc_attr( $r->email ),
				self::h( $r->email ),
				esc_attr( $r->phone ),
				self::h( $r->phone ),
				self::h( $sub ),
				self::h( $r->query ),
				'' !== $r->details && $r->details !== $r->query ? '<br><small>' . self::h( $r->details ) . '</small>' : '',
				$st,
				$r->sms_status ? '<br><small>' . self::h( $r->sms_status ) . '</small>' : '',
				in_array( $r->status, array( 'new', 'failed', 'notified' ), true ) ? '<a href="' . esc_url( self::action_url( 'notify_now', array( 'id' => $r->id ) ) ) . '">ارسال اکنون</a> ' : '',
				esc_url( self::action_url( 'req_status', array( 'id' => $r->id, 'to' => 'done' === $r->status ? 'new' : 'done' ) ) ),
				'done' === $r->status ? 'بازگردانی' : 'پیگیری شد',
				esc_url( self::action_url( 'del_req', array( 'id' => $r->id ) ) )
			);
		}
		echo '</tbody></table></div>';
	}

	public static function notify_now() {
		self::guard( 'pmf_notify_now' );
		global $wpdb;
		$r    = $wpdb->get_row( $wpdb->prepare( 'SELECT * FROM ' . PMF_DB::t( 'requests' ) . ' WHERE id=%d', absint( self::g( 'id' ) ) ) );
		$back = self::url( 'partmatch-requests' );
		if ( ! $r ) {
			self::back( $back, 'deleted' );
		}
		$cache = array();
		$ids   = PMF_Notify::available_for_request( $r, $cache );
		if ( ! $ids ) {
			self::back( $back, 'nostock' );
		}
		$res = PMF_Notify::notify( $r, $ids );
		if ( $res[0] ) {
			self::back( $back, 'notified' );
		}
		self::back( $back, 'smsfail', array( 'txt' => rawurlencode( $res[1] ) ) );
	}

	public static function req_status() {
		self::guard( 'pmf_req_status' );
		global $wpdb;
		$to = 'done' === self::g( 'to' ) ? 'done' : 'new';
		$wpdb->update( PMF_DB::t( 'requests' ), array( 'status' => $to ), array( 'id' => absint( self::g( 'id' ) ) ), array( '%s' ), array( '%d' ) );
		self::back( self::url( 'partmatch-requests' ), 'updated' );
	}

	public static function del_req() {
		self::guard( 'pmf_del_req' );
		global $wpdb;
		$wpdb->delete( PMF_DB::t( 'requests' ), array( 'id' => absint( self::g( 'id' ) ) ), array( '%d' ) );
		self::back( self::url( 'partmatch-requests' ), 'deleted' );
	}

	/* ================= جستجوهای بی‌نتیجه ================= */

	public static function page_missed() {
		global $wpdb;
		self::wrap_open();
		$rows = $wpdb->get_results( 'SELECT m.*, f.title FROM ' . PMF_DB::t( 'missed' ) . ' m LEFT JOIN ' . PMF_DB::t( 'forms' ) . ' f ON f.id=m.form_id ORDER BY m.hits DESC, m.last_at DESC LIMIT 500' );
		echo '<h1 class="wp-heading-inline">جستجوهای بی‌نتیجه</h1> ';
		if ( $rows ) {
			echo '<a class="page-title-action" href="' . esc_url( self::action_url( 'export', array( 'what' => 'missed' ) ) ) . '">خروجی CSV</a> ';
			echo '<a class="page-title-action" style="color:#b32d2e" href="' . esc_url( self::action_url( 'clear_missed' ) ) . '" onclick="return confirm(\'همهٔ گزارش پاک شود؟\')">پاک‌کردن همه</a>';
		}
		echo '<hr class="wp-header-end"><p class="pmf-lead">مشخصات و کدهایی که مشتری‌ها جستجو کرده‌اند ولی محصولی برایشان ثبت نشده؛ فهرست کالاهای پرتقاضایی که باید تأمین کنید.</p>';
		echo '<table class="widefat striped pmf-table"><thead><tr><th>جستجو</th><th>نوع</th><th>فرم</th><th>دفعات</th><th>آخرین بار</th><th></th></tr></thead><tbody>';
		if ( ! $rows ) {
			echo '<tr><td colspan="6">هنوز جستجوی بی‌نتیجه‌ای ثبت نشده است.</td></tr>';
		}
		foreach ( (array) $rows as $r ) {
			printf(
				'<tr><td>%s</td><td>%s</td><td>%s</td><td><strong>%s</strong></td><td>%s</td><td><a style="color:#b32d2e" href="%s">حذف</a></td></tr>',
				self::h( $r->q ),
				'code' === $r->kind ? 'کد قطعه' : 'مشخصات',
				self::h( (string) $r->title ),
				self::h( number_format_i18n( $r->hits ) ),
				self::h( PMF_Date::format( $r->last_at ) ),
				esc_url( self::action_url( 'del_missed', array( 'id' => $r->id ) ) )
			);
		}
		echo '</tbody></table></div>';
	}

	public static function del_missed() {
		self::guard( 'pmf_del_missed' );
		global $wpdb;
		$wpdb->delete( PMF_DB::t( 'missed' ), array( 'id' => absint( self::g( 'id' ) ) ), array( '%d' ) );
		self::back( self::url( 'partmatch-missed' ), 'deleted' );
	}

	public static function clear_missed() {
		self::guard( 'pmf_clear_missed' );
		global $wpdb;
		$wpdb->query( 'TRUNCATE TABLE ' . PMF_DB::t( 'missed' ) );
		self::back( self::url( 'partmatch-missed' ), 'deleted' );
	}

	public static function export() {
		self::guard( 'pmf_export' );
		global $wpdb;
		$what = self::g( 'what' );
		if ( 'requests' === $what ) {
			$rows = $wpdb->get_results( 'SELECT created_at,name,email,phone,kind,query,details,status,sms_status FROM ' . PMF_DB::t( 'requests' ) . ' ORDER BY id DESC', ARRAY_N );
			$head = array( 'تاریخ', 'نام', 'ایمیل', 'تماس', 'نوع', 'درخواست', 'توضیحات', 'وضعیت', 'وضعیت پیامک' );
		} else {
			$rows = $wpdb->get_results( 'SELECT q,kind,hits,last_at FROM ' . PMF_DB::t( 'missed' ) . ' ORDER BY hits DESC', ARRAY_N );
			$head = array( 'جستجو', 'نوع', 'تعداد', 'آخرین بار' );
			$what = 'missed';
		}
		nocache_headers();
		header( 'Content-Type: text/csv; charset=utf-8' );
		header( 'Content-Disposition: attachment; filename=partmatch-' . $what . '-' . gmdate( 'Ymd' ) . '.csv' );
		$out = fopen( 'php://output', 'w' );
		fwrite( $out, "\xEF\xBB\xBF" );
		fputcsv( $out, $head );
		foreach ( (array) $rows as $r ) {
			foreach ( $r as $k => $v ) {
				if ( is_string( $v ) && preg_match( '/^[=+\-@]/', $v ) ) {
					$r[ $k ] = "'" . $v;
				}
			}
			fputcsv( $out, $r );
		}
		fclose( $out ); // phpcs:ignore
		exit;
	}

	/* ================= تنظیمات ================= */

	public static function page_settings() {
		self::wrap_open();
		$s = PMF_DB::settings();
		$presets = array( '#1d4ed8', '#7c3aed', '#059669', '#dc2626', '#ea580c', '#0f172a' );
		?>
		<h1>تنظیمات</h1>
		<form method="post" action="<?php echo esc_url( admin_url( 'admin-post.php' ) ); ?>">
			<input type="hidden" name="action" value="pmf_save_settings">
			<?php wp_nonce_field( 'pmf_save_settings' ); ?>

			<div class="pmf-panel">
				<h2 class="pmf-h2">ظاهر</h2>
				<table class="form-table">
					<tr><th>رنگ اصلی</th><td>
						<input type="color" id="pmf_primary" name="primary" value="<?php echo esc_attr( $s['primary'] ); ?>">
						<?php foreach ( $presets as $c ) { echo '<button type="button" class="pmf-swatch" data-c="' . esc_attr( $c ) . '" style="background:' . esc_attr( $c ) . '" aria-label="' . esc_attr( $c ) . '"></button>'; } ?>
					</td></tr>
					<tr><th>متن دکمهٔ جستجو</th><td><input type="text" class="regular-text" name="btn_text" value="<?php echo esc_attr( $s['btn_text'] ); ?>"></td></tr>
					<tr><th>پیام نبودن نتیجه</th><td><input type="text" class="large-text" name="empty_text" value="<?php echo esc_attr( $s['empty_text'] ); ?>"></td></tr>
				</table>
			</div>

			<div class="pmf-panel">
				<h2 class="pmf-h2">نمایش در سایت</h2>
				<table class="form-table">
					<tr><th>حداکثر محصول در نتایج</th><td><input type="number" min="1" max="200" name="per_page" value="<?php echo esc_attr( $s['per_page'] ); ?>"></td></tr>
					<tr><th>محصولات ناموجود</th><td><label><input type="checkbox" name="hide_oos" value="1" <?php checked( $s['hide_oos'], 1 ); ?>> در نتایج نمایش داده نشوند</label></td></tr>
					<tr><th>تب «سازگاری»</th><td><label><input type="checkbox" name="product_tab" value="1" <?php checked( $s['product_tab'], 1 ); ?>> در صفحهٔ محصول، فهرست مشخصاتِ سازگار نمایش داده شود</label></td></tr>
					<tr><th>«انتخاب قبلی من»</th><td><label><input type="checkbox" name="garage" value="1" <?php checked( $s['garage'], 1 ); ?>> آخرین انتخاب مشتری یادآوری شود و در صفحهٔ محصول سازگاری نشان داده شود</label></td></tr>
				</table>
			</div>

			<div class="pmf-panel">
				<h2 class="pmf-h2">اطلاع‌رسانی «موجود شد» (ایمیل / پیامک)</h2>
				<table class="form-table">
					<tr><th>«خبرم کن» در صفحهٔ محصول</th><td><label><input type="checkbox" name="notify_product" value="1" <?php checked( $s['notify_product'], 1 ); ?>> برای محصولات ناموجود، فرم دریافت شماره نمایش داده شود</label></td></tr>
					<tr><th>ایمیل «موجود شد»</th><td><label><input type="checkbox" name="email_on" value="1" <?php checked( $s['email_on'], 1 ); ?>> ارسال ایمیل (پیش‌فرض و رایگان)</label><br><input type="text" class="large-text" name="email_subject" value="<?php echo esc_attr( $s['email_subject'] ); ?>"><p class="description">موضوع ایمیل؛ <code>{site}</code> نام فروشگاه. اگر ایمیل نمی‌رسد، یک افزونهٔ SMTP (مثل WP Mail SMTP) نصب کنید.</p></td></tr>
					<tr><th>سرویس‌دهندهٔ پیامک (اختیاری)</th><td>
						<select name="sms_provider" id="pmf_sms_provider">
							<?php foreach ( PMF_SMS::providers() as $k => $l ) { echo '<option value="' . esc_attr( $k ) . '"' . selected( $s['sms_provider'], $k, false ) . '>' . self::h( $l ) . '</option>'; } ?>
						</select>
					</td></tr>
					<tr data-prov="melipayamak custom"><th>نام کاربری</th><td><input type="text" class="regular-text" name="sms_user" value="<?php echo esc_attr( $s['sms_user'] ); ?>" autocomplete="off"></td></tr>
					<tr data-prov="melipayamak custom"><th>رمز عبور</th><td><input type="password" class="regular-text" name="sms_pass" value="<?php echo esc_attr( $s['sms_pass'] ); ?>" autocomplete="new-password"></td></tr>
					<tr data-prov="kavenegar custom"><th>کلید API</th><td><input type="text" class="regular-text" name="sms_key" value="<?php echo esc_attr( $s['sms_key'] ); ?>" autocomplete="off" dir="ltr"></td></tr>
					<tr data-prov="melipayamak kavenegar custom"><th>شمارهٔ فرستنده</th><td><input type="text" class="regular-text" name="sms_sender" value="<?php echo esc_attr( $s['sms_sender'] ); ?>" dir="ltr"></td></tr>
					<tr data-prov="custom"><th>آدرس API پنل</th><td><input type="text" class="large-text" dir="ltr" name="sms_url" value="<?php echo esc_attr( $s['sms_url'] ); ?>" placeholder="https://example.com/api/send"><p class="description">آدرس ارسال پیامک را از مستندات پنل‌تان بردارید. متغیرهای مجاز: <code>{phone}</code> <code>{text}</code> <code>{sender}</code> <code>{user}</code> <code>{pass}</code> <code>{key}</code></p></td></tr>
					<tr data-prov="custom"><th>بدنهٔ درخواست</th><td><textarea name="sms_body" rows="3" class="large-text code" dir="ltr" placeholder="to={phone}&amp;message={text}&amp;from={sender}"><?php echo esc_textarea( $s['sms_body'] ); ?></textarea><p class="description">خالی = درخواست GET (متغیرها را داخل آدرس بگذارید). اگر با <code>{</code> شروع شود، JSON ارسال می‌شود، وگرنه فرم معمولی. اگر پنل با نام کاربری/رمز یا کلید کار می‌کند، از <code>{user}</code> و <code>{pass}</code> یا <code>{key}</code> استفاده کنید.</p></td></tr>
					<tr data-prov="custom"><th>متن موفقیت (اختیاری)</th><td><input type="text" class="regular-text" dir="ltr" name="sms_ok" value="<?php echo esc_attr( $s['sms_ok'] ); ?>"><p class="description">اگر پاسخ موفق پنل شامل متن خاصی است (مثلاً <code>success</code>) اینجا بنویسید؛ خالی = هر پاسخ ۲۰۰ موفق است.</p></td></tr>
					<tr data-prov="melipayamak kavenegar log custom"><th>متن پیامک</th><td>
						<textarea name="sms_template" rows="4" class="large-text"><?php echo esc_textarea( $s['sms_template'] ); ?></textarea>
						<p class="description">متغیرها: <code>{name}</code> نام مشتری، <code>{site}</code> نام فروشگاه، <code>{link}</code> لینک لیست محصولات (با تاریخ و دکمهٔ ذخیره PDF). حتماً <code>{link}</code> را نگه دارید.</p>
					</td></tr>
					<tr><th>متن رضایت زیر فرم</th><td><input type="text" class="large-text" name="consent" value="<?php echo esc_attr( $s['consent'] ); ?>"></td></tr>
				</table>
			</div>

			<div class="pmf-panel">
				<h2 class="pmf-h2">کاتالوگ خودکار تازه‌های موجودشده</h2>
				<table class="form-table">
					<tr><th>عنوان کاتالوگ</th><td><input type="text" class="regular-text" name="catalog_title" value="<?php echo esc_attr( $s['catalog_title'] ); ?>"></td></tr>
					<tr><th>محصولات چند روز اخیر</th><td><input type="number" min="1" max="120" name="catalog_days" value="<?php echo esc_attr( $s['catalog_days'] ); ?>"> روز <p class="description">هر محصول بعد از موجود شدن این‌قدر در کاتالوگ می‌ماند و بعد خودکار حذف می‌شود.</p></td></tr>
				</table>
			</div>

			<div class="pmf-panel">
				<h2 class="pmf-h2">پیشرفته</h2>
				<table class="form-table">
					<tr><th>حذف اطلاعات</th><td><label><input type="checkbox" name="delete_data" value="1" <?php checked( $s['delete_data'], 1 ); ?>> هنگام حذف افزونه، جدول‌ها و داده‌ها هم پاک شوند</label></td></tr>
				</table>
			</div>
			<?php submit_button( 'ذخیرهٔ تنظیمات' ); ?>
		</form>

		<div class="pmf-panel">
			<h2 class="pmf-h2">ارسال پیامک آزمایشی</h2>
			<p>بعد از ذخیرهٔ تنظیمات بالا، با شمارهٔ خودتان آزمایش کنید.</p>
			<form method="post" action="<?php echo esc_url( admin_url( 'admin-post.php' ) ); ?>" class="pmf-inline">
				<input type="hidden" name="action" value="pmf_test_sms">
				<?php wp_nonce_field( 'pmf_test_sms' ); ?>
				<input type="tel" name="phone" placeholder="09123456789" dir="ltr" required>
				<button class="button button-primary">ارسال آزمایشی</button>
			</form>
		</div>
		</div>
		<?php
	}

	public static function save_settings() {
		self::guard( 'pmf_save_settings' );
		$color = sanitize_hex_color( self::p( 'primary' ) );
		$prov  = sanitize_key( self::p( 'sms_provider' ) );
		$prov  = array_key_exists( $prov, PMF_SMS::providers() ) ? $prov : 'none';
		$def   = PMF_DB::defaults();
		$tpl   = sanitize_textarea_field( self::p( 'sms_template' ) );
		if ( '' === trim( $tpl ) ) {
			$tpl = $def['sms_template'];
		}
		if ( false === strpos( $tpl, '{link}' ) ) {
			$tpl .= "\n{link}";
		}
		$new = array(
			'btn_text'       => sanitize_text_field( self::p( 'btn_text' ) ),
			'empty_text'     => sanitize_text_field( self::p( 'empty_text' ) ),
			'per_page'       => max( 1, min( 200, absint( self::p( 'per_page' ) ) ) ),
			'primary'        => $color ? $color : '#1d4ed8',
			'hide_oos'       => self::p( 'hide_oos' ) ? 1 : 0,
			'product_tab'    => self::p( 'product_tab' ) ? 1 : 0,
			'garage'         => self::p( 'garage' ) ? 1 : 0,
			'delete_data'    => self::p( 'delete_data' ) ? 1 : 0,
			'notify_product' => self::p( 'notify_product' ) ? 1 : 0,
			'consent'        => sanitize_text_field( self::p( 'consent' ) ),
			'email_on'       => self::p( 'email_on' ) ? 1 : 0,
			'email_subject'  => '' !== trim( self::p( 'email_subject' ) ) ? sanitize_text_field( self::p( 'email_subject' ) ) : $def['email_subject'],
			'catalog_days'   => max( 1, min( 120, absint( self::p( 'catalog_days' ) ) ) ),
			'catalog_title'  => sanitize_text_field( self::p( 'catalog_title' ) ),
			'sms_provider'   => $prov,
			'sms_user'       => sanitize_text_field( self::p( 'sms_user' ) ),
			'sms_pass'       => (string) self::p( 'sms_pass' ),
			'sms_key'        => sanitize_text_field( self::p( 'sms_key' ) ),
			'sms_sender'     => sanitize_text_field( self::p( 'sms_sender' ) ),
			'sms_template'   => $tpl,
			'sms_url'        => esc_url_raw( trim( self::p( 'sms_url' ) ) ),
			'sms_body'       => sanitize_textarea_field( self::p( 'sms_body' ) ),
			'sms_ok'         => sanitize_text_field( self::p( 'sms_ok' ) ),
		);
		foreach ( array( 'btn_text', 'empty_text', 'consent', 'catalog_title' ) as $k ) {
			if ( '' === $new[ $k ] ) {
				$new[ $k ] = $def[ $k ];
			}
		}
		update_option( 'pmf_settings', $new );
		self::back( self::url( 'partmatch-settings' ), 'saved' );
	}

	public static function test_sms() {
		self::guard( 'pmf_test_sms' );
		$res = PMF_SMS::send( self::p( 'phone' ), 'پیامک آزمایشی Fitora از ' . wp_specialchars_decode( get_bloginfo( 'name' ), ENT_QUOTES ) );
		self::back( self::url( 'partmatch-settings' ), $res[0] ? 'smsok' : 'smsfail', array( 'txt' => rawurlencode( $res[1] ) ) );
	}

	/* ================= راهنما ================= */

	public static function page_help() {
		self::wrap_open();
		?>
		<h1>راهنما</h1>
		<div class="pmf-panel">
			<h2 class="pmf-h2">سه قدم تا اولین جستجو</h2>
			<ol class="pmf-steps">
				<li><strong>قالب را انتخاب کنید:</strong> در «پیشخوان» نوع فروشگاهتان را بزنید؛ فرم، نمونه‌ها و برگهٔ جستجو ساخته می‌شود.</li>
				<li><strong>مشخصات واقعی را وارد کنید:</strong> در «فرم‌ها و مشخصات»، هر خط یک مورد (مثلاً <code>پژو &gt; 206 &gt; 1398</code>).</li>
				<li><strong>محصولات را وصل کنید:</strong> از «اتصال محصولات»، یا با فایل CSV بر اساس SKU، یا با انتخاب دسته.</li>
			</ol>
			<h2 class="pmf-h2">پیامک و لیست PDF</h2>
			<p>وقتی محصولی که مشتری خواسته (یا محصولی مطابق جستجویش) موجود شود، پیامکی با لینک می‌رود. مشتری لینک را باز می‌کند، لیست محصولات را با تاریخ می‌بیند و با دکمهٔ «دانلود / ذخیره PDF» فایل را ذخیره می‌کند. برای فعال‌سازی، از «تنظیمات» سرویس‌دهندهٔ پیامک را انتخاب و با «ارسال آزمایشی» امتحان کنید.</p>
			<h2 class="pmf-h2">شورت‌کد</h2>
			<p><code>[partmatch id="1"]</code> — حالت‌ها: <code>mode="both"</code> (فیلتر و کد)، <code>filter</code>، <code>code</code>. با <code>title="..."</code> عنوان را عوض کنید.</p>
			<h2 class="pmf-h2">انواع سازگاری</h2>
			<p><span class="pmf-badge pmf-b-verified">سازگار و تأیید شده</span> <span class="pmf-badge pmf-b-conditional">سازگار با شرایط مشخص</span> <span class="pmf-badge pmf-b-alt">جایگزین تأیید شده</span></p>
		</div>
		</div>
		<?php
	}
}
