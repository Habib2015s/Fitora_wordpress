<?php
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class PMF_Frontend {

	protected static $localized = false;

	public static function init() {
		add_shortcode( 'partmatch', array( __CLASS__, 'shortcode' ) );
		add_action( 'wp_enqueue_scripts', array( __CLASS__, 'register_assets' ) );
		add_filter( 'woocommerce_product_tabs', array( __CLASS__, 'product_tabs' ) );
		add_action( 'woocommerce_single_product_summary', array( __CLASS__, 'garage_check_box' ), 25 );
	}

	public static function type_label( $type ) {
		$l = array(
			'verified'    => 'سازگار و تأیید شده',
			'conditional' => 'سازگار با شرایط مشخص',
			'alt'         => 'جایگزین تأیید شده',
			'match'       => 'مطابق با کد شما',
		);
		return isset( $l[ $type ] ) ? $l[ $type ] : $l['verified'];
	}

	/* ---------- فایل‌ها ---------- */

	public static function register_assets() {
		if ( ! wp_style_is( 'pmf-front', 'registered' ) ) {
			wp_register_style( 'pmf-front', PMF_URL . 'assets/css/front.css', array(), PMF_VERSION );
		}
		if ( ! wp_script_is( 'pmf-front', 'registered' ) ) {
			wp_register_script( 'pmf-front', PMF_URL . 'assets/js/front.js', array(), PMF_VERSION, true );
		}
		if ( function_exists( 'is_product' ) && is_product() && ( PMF_DB::setting( 'garage' ) || PMF_DB::setting( 'notify_product' ) ) ) {
			self::enqueue();
		}
	}

	public static function enqueue() {
		self::register_assets_safe();
		if ( ! self::$localized ) {
			wp_localize_script(
				'pmf-front',
				'PMF',
				array(
					'ajax' => admin_url( 'admin-ajax.php' ),
					't'    => array(
						'choose'   => 'انتخاب کنید',
						'loading'  => 'در حال جستجو...',
						'error'    => 'خطایی رخ داد. دوباره تلاش کنید.',
						'mydev'    => 'دستگاه من:',
						'remove'   => 'حذف',
						'showmine' => 'نمایش محصولات این دستگاه',
						'sent'     => 'ثبت شد! به‌محض موجود شدن، برایتان ایمیل می‌فرستیم.',
						'needphone' => 'ایمیل خود را وارد کنید.',
						'needcode' => 'کد قطعه را وارد کنید.',
						'ok'       => 'این قطعه با دستگاه شما سازگار است:',
						'cond'     => 'این قطعه با دستگاه شما سازگار است، اما با شرایط زیر:',
						'alt'      => 'این قطعه برای دستگاه شما «جایگزین تأیید شده» است:',
						'no'       => 'برای دستگاه ذخیره‌شدهٔ شما سازگاری این قطعه ثبت نشده است:',
					),
				)
			);
			self::$localized = true;
		}
		wp_enqueue_style( 'pmf-front' );
		wp_enqueue_script( 'pmf-front' );
	}

	protected static function register_assets_safe() {
		if ( ! wp_script_is( 'pmf-front', 'registered' ) || ! wp_style_is( 'pmf-front', 'registered' ) ) {
			self::register_assets();
		}
	}

	/* ---------- شورت‌کد ---------- */

	public static function shortcode( $atts ) {
		$a    = shortcode_atts( array( 'id' => 0, 'mode' => 'both', 'title' => '' ), $atts, 'partmatch' );
		$form = PMF_DB::form( absint( $a['id'] ) );
		if ( ! $form || empty( $form->fields ) ) {
			return current_user_can( 'manage_woocommerce' ) ? '<p>[partmatch] فرم با این شناسه پیدا نشد.</p>' : '';
		}
		self::enqueue();

		$mode    = in_array( $a['mode'], array( 'both', 'filter', 'code' ), true ) ? $a['mode'] : 'both';
		$first   = PMF_DB::next_options( $form->id, array() );
		$color   = sanitize_hex_color( PMF_DB::setting( 'primary' ) );
		$color   = $color ? $color : '#1d4ed8';
		$title   = '' !== $a['title'] ? $a['title'] : $form->title;
		$btn     = PMF_DB::setting( 'btn_text' );
		$empty   = PMF_DB::setting( 'empty_text' );
		$nfields = count( $form->fields );

		ob_start();
		?>
		<div class="pmf" dir="rtl" data-form="<?php echo esc_attr( $form->id ); ?>" data-empty="<?php echo esc_attr( $empty ); ?>" style="--pmf-primary:<?php echo esc_attr( $color ); ?>">
			<?php if ( '' !== $title ) : ?>
				<h3 class="pmf-title"><?php echo esc_html( $title ); ?></h3>
			<?php endif; ?>

			<?php if ( 'both' === $mode ) : ?>
				<div class="pmf-tabs" role="tablist">
					<button type="button" class="pmf-tab is-active" data-tab="filter">جستجو با مشخصات دستگاه</button>
					<button type="button" class="pmf-tab" data-tab="code">جستجو با کد قطعه</button>
				</div>
			<?php endif; ?>

			<?php if ( 'code' !== $mode ) : ?>
				<div class="pmf-pane" data-pane="filter">
					<div class="pmf-fields">
						<?php foreach ( $form->fields as $i => $label ) : ?>
							<label class="pmf-field">
								<span><?php echo esc_html( $label ); ?></span>
								<select class="pmf-sel" data-level="<?php echo esc_attr( $i ); ?>" <?php echo $i > 0 ? 'disabled' : ''; ?>>
									<option value="">انتخاب <?php echo esc_html( $label ); ?></option>
									<?php
									if ( 0 === $i ) {
										foreach ( $first as $v ) {
											echo '<option value="' . esc_attr( $v ) . '">' . esc_html( $v ) . '</option>';
										}
									}
									?>
								</select>
							</label>
						<?php endforeach; ?>
					</div>
					<button type="button" class="pmf-btn pmf-go" disabled><?php echo esc_html( $btn ); ?></button>
					<div class="pmf-garage" hidden></div>
				</div>
			<?php endif; ?>

			<?php if ( 'filter' !== $mode ) : ?>
				<div class="pmf-pane" data-pane="code" <?php echo 'both' === $mode ? 'hidden' : ''; ?>>
					<div class="pmf-codebox">
						<input type="text" class="pmf-code" placeholder="کد قطعه یا SKU را وارد کنید" autocomplete="off">
						<button type="button" class="pmf-btn pmf-code-go">جستجو</button>
					</div>
				</div>
			<?php endif; ?>

			<div class="pmf-results" aria-live="polite"></div>

			<form class="pmf-req" hidden>
				<h4>محصولتان را پیدا نکردید؟</h4>
				<p>ایمیل‌تان را بگذارید؛ به‌محض موجود شدن، ایمیلی با لینک لیست محصولات (قابل ذخیره به‌صورت PDF) می‌فرستیم.</p>
				<input type="text" name="name" placeholder="نام (اختیاری)">
				<input type="email" name="email" placeholder="ایمیل شما" required inputmode="email" dir="ltr" autocomplete="email">
				<?php if ( PMF_SMS::enabled() ) : ?><input type="tel" name="phone" placeholder="موبایل (اختیاری)" inputmode="tel"><?php endif; ?>
				<textarea name="details" rows="2" placeholder="توضیحات (اختیاری)"></textarea>
				<input type="text" name="website" class="pmf-hp" tabindex="-1" autocomplete="off" aria-hidden="true">
				<input type="hidden" name="query" value="">
				<input type="hidden" name="kind" value="search">
				<input type="hidden" name="loaded" value="<?php echo esc_attr( time() ); ?>">
				<small class="pmf-consent"><?php echo esc_html( PMF_DB::setting( 'consent' ) ); ?></small>
				<button type="submit" class="pmf-btn">ثبت و خبرم کن</button>
				<div class="pmf-req-msg"></div>
			</form>
			<input type="hidden" class="pmf-nfields" value="<?php echo esc_attr( $nfields ); ?>">
		</div>
		<?php
		return ob_get_clean();
	}

	/* ---------- کارت محصول ---------- */

	public static function card( $product, $type, $note = '' ) {
		$in_stock = $product->is_in_stock();
		$class    = 'pmf-btn pmf-add button';
		if ( $product->is_purchasable() && $in_stock && $product->supports( 'ajax_add_to_cart' ) ) {
			$class .= ' add_to_cart_button ajax_add_to_cart';
		}
		ob_start();
		?>
		<div class="pmf-card">
			<a class="pmf-img" href="<?php echo esc_url( $product->get_permalink() ); ?>"><?php echo wp_kses_post( $product->get_image( 'woocommerce_thumbnail' ) ); ?></a>
			<span class="pmf-badge pmf-b-<?php echo esc_attr( $type ); ?>"><?php echo esc_html( self::type_label( $type ) ); ?></span>
			<a class="pmf-name" href="<?php echo esc_url( $product->get_permalink() ); ?>"><?php echo esc_html( $product->get_name() ); ?></a>
			<?php if ( 'conditional' === $type && '' !== $note ) : ?>
				<div class="pmf-note"><?php echo esc_html( $note ); ?></div>
			<?php endif; ?>
			<div class="pmf-price"><?php echo wp_kses_post( $product->get_price_html() ); ?></div>
			<div class="pmf-stock <?php echo $in_stock ? 'is-in' : 'is-out'; ?>"><?php echo $in_stock ? 'موجود' : 'ناموجود'; ?></div>
			<a href="<?php echo esc_url( $product->add_to_cart_url() ); ?>" data-quantity="1" class="<?php echo esc_attr( $class ); ?>" data-product_id="<?php echo esc_attr( $product->get_id() ); ?>" data-product_sku="<?php echo esc_attr( $product->get_sku() ); ?>" rel="nofollow"><?php echo esc_html( $product->add_to_cart_text() ); ?></a>
		</div>
		<?php
		return ob_get_clean();
	}

	/**
	 * @param array $found product_id => array(type, note, rank)
	 * @return array html,count,total
	 */
	public static function render_products( $found, $limit = 0 ) {
		$limit = $limit ? $limit : max( 1, (int) PMF_DB::setting( 'per_page' ) );
		$hide  = (bool) PMF_DB::setting( 'hide_oos' );
		$items = array();
		foreach ( $found as $pid => $info ) {
			$p = wc_get_product( $pid );
			if ( ! $p || 'publish' !== $p->get_status() ) {
				continue;
			}
			if ( $hide && ! $p->is_in_stock() ) {
				continue;
			}
			$items[] = array( 'p' => $p, 'info' => $info );
		}
		usort(
			$items,
			function ( $a, $b ) {
				if ( $a['info']['rank'] !== $b['info']['rank'] ) {
					return $a['info']['rank'] - $b['info']['rank'];
				}
				$sa = $a['p']->is_in_stock() ? 0 : 1;
				$sb = $b['p']->is_in_stock() ? 0 : 1;
				if ( $sa !== $sb ) {
					return $sa - $sb;
				}
				return strcmp( $a['p']->get_name(), $b['p']->get_name() );
			}
		);
		$total = count( $items );
		$items = array_slice( $items, 0, $limit );
		$html  = '';
		foreach ( $items as $it ) {
			$html .= self::card( $it['p'], $it['info']['type'], $it['info']['note'] );
		}
		return array( 'html' => $html, 'count' => count( $items ), 'total' => $total );
	}

	/* ---------- صفحهٔ محصول ---------- */

	public static function product_tabs( $tabs ) {
		global $product;
		if ( ! PMF_DB::setting( 'product_tab' ) || ! $product || ! PMF_DB::product_has_compat( $product->get_id() ) ) {
			return $tabs;
		}
		$tabs['pmf'] = array(
			'title'    => 'سازگاری',
			'priority' => 35,
			'callback' => array( __CLASS__, 'tab_content' ),
		);
		return $tabs;
	}

	public static function tab_content() {
		global $product;
		$rows = PMF_DB::product_compat( $product->get_id() );
		if ( ! $rows ) {
			return;
		}
		$forms = array();
		foreach ( PMF_DB::forms() as $f ) {
			$forms[ (int) $f->id ] = $f;
		}
		$rows   = array_slice( $rows, 0, 500 );
		$groups = array();
		foreach ( $rows as $r ) {
			$groups[ (int) $r->form_id ][] = $r;
		}
		echo '<h2>این محصول با دستگاه‌های زیر سازگار است</h2>';
		foreach ( $groups as $fid => $list ) {
			if ( isset( $forms[ $fid ] ) && count( $groups ) > 1 ) {
				echo '<h3>' . esc_html( $forms[ $fid ]->title ) . '</h3>';
			}
			echo '<table class="shop_attributes pmf-compat-table"><thead><tr><th>دستگاه</th><th>وضعیت</th><th>توضیح</th></tr></thead><tbody>';
			foreach ( $list as $r ) {
				echo '<tr><td>' . esc_html( PMF_DB::label( $r ) ) . '</td><td><span class="pmf-badge pmf-b-' . esc_attr( $r->ctype ) . '">' . esc_html( self::type_label( $r->ctype ) ) . '</span></td><td>' . esc_html( $r->note ) . '</td></tr>';
			}
			echo '</tbody></table>';
		}
	}

	public static function garage_check_box() {
		global $product;
		if ( ! PMF_DB::setting( 'garage' ) || ! $product || ! PMF_DB::product_has_compat( $product->get_id() ) ) {
			return;
		}
		echo '<div class="pmf-gcheck" data-product="' . esc_attr( $product->get_id() ) . '" hidden></div>';
	}
}
