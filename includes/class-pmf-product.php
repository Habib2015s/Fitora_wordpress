<?php
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/** تب «سازگاری» در صفحهٔ ویرایش محصول. */
class PMF_Product {

	public static function init() {
		if ( is_admin() ) {
			add_filter( 'woocommerce_product_data_tabs', array( __CLASS__, 'tab' ) );
			add_action( 'woocommerce_product_data_panels', array( __CLASS__, 'panel' ) );
			add_action( 'woocommerce_process_product_meta', array( __CLASS__, 'save' ) );
			add_action( 'admin_enqueue_scripts', array( __CLASS__, 'assets' ) );
		}
	}

	public static function tab( $tabs ) {
		$tabs['pmf'] = array(
			'label'    => 'سازگاری محصول',
			'target'   => 'pmf_product_data',
			'class'    => array( 'show_if_simple', 'show_if_variable', 'show_if_external', 'show_if_grouped' ),
			'priority' => 75,
		);
		return $tabs;
	}

	public static function assets( $hook ) {
		global $post;
		if ( ! in_array( $hook, array( 'post.php', 'post-new.php' ), true ) || ! $post || 'product' !== $post->post_type ) {
			return;
		}
		wp_enqueue_style( 'pmf-admin', PMF_URL . 'assets/css/admin.css', array(), PMF_VERSION );
		wp_enqueue_script( 'pmf-product', PMF_URL . 'assets/js/product.js', array( 'jquery' ), PMF_VERSION, true );

		$forms = array();
		foreach ( PMF_DB::forms() as $f ) {
			$forms[ (int) $f->id ] = array( 'title' => $f->title, 'fields' => $f->fields );
		}
		$items = array();
		foreach ( PMF_DB::product_compat( (int) $post->ID ) as $r ) {
			$items[] = array(
				'device_id' => (int) $r->device_id,
				'form_id'   => (int) $r->form_id,
				'label'     => PMF_DB::label( $r ),
				'ctype'     => $r->ctype,
				'note'      => $r->note,
			);
		}
		wp_localize_script(
			'pmf-product',
			'PMF_PROD',
			array(
				'ajax'  => admin_url( 'admin-ajax.php' ),
				'nonce' => wp_create_nonce( 'pmf_admin' ),
				'forms' => (object) $forms,
				'items' => $items,
			)
		);
	}

	public static function panel() {
		global $post;
		$forms = PMF_DB::forms();
		$codes = (string) get_post_meta( $post->ID, '_pmf_codes', true );
		$alts  = array_filter( array_map( 'absint', explode( ',', trim( (string) get_post_meta( $post->ID, '_pmf_alts', true ), ',' ) ) ) );
		?>
		<div id="pmf_product_data" class="panel woocommerce_options_panel hidden">
			<?php if ( ! $forms ) : ?>
				<p class="pmf-p-note">هنوز فرمی نساخته‌اید. از منوی «Fitora» یک فرم و دستگاه‌هایش را تعریف کنید، سپس به این‌جا برگردید.</p>
			<?php else : ?>
				<div class="options_group" id="pmf_ui">
					<p class="form-field">
						<label for="pmf_form_sel">فرم</label>
						<select id="pmf_form_sel">
							<?php foreach ( $forms as $f ) : ?>
								<option value="<?php echo esc_attr( $f->id ); ?>"><?php echo esc_html( $f->title ); ?></option>
							<?php endforeach; ?>
						</select>
					</p>
					<p class="form-field">
						<label>انتخاب دستگاه</label>
						<span id="pmf_levels" class="pmf-levels"></span>
						<button type="button" class="button" id="pmf_add">افزودن به لیست سازگاری</button>
						<span class="description" style="display:block;clear:both;margin:6px 0 0">اگر سطح‌های بعدی را «همه» بگذارید، تمام دستگاه‌های زیرمجموعه (مثلاً همهٔ مدل‌های یک برند) یک‌جا اضافه می‌شوند.</span>
					</p>
					<div class="pmf-list-wrap">
						<table class="widefat striped" id="pmf_table">
							<thead><tr><th>دستگاه</th><th style="width:170px">نوع سازگاری</th><th>شرط / توضیح</th><th style="width:40px"></th></tr></thead>
							<tbody></tbody>
						</table>
						<p class="pmf-empty-row">هنوز دستگاهی برای این محصول ثبت نشده است.</p>
					</div>
					<input type="hidden" name="pmf_compat" id="pmf_compat_json" value="[]">
				</div>
			<?php endif; ?>

			<div class="options_group">
				<p class="form-field">
					<label for="pmf_codes">کدهای قطعه</label>
					<textarea id="pmf_codes" name="pmf_codes" rows="3" style="width:50%" placeholder="هر کد در یک خط یا با ویرگول جدا شود"><?php echo esc_textarea( $codes ); ?></textarea>
				</p>
				<p class="form-field">
					<label for="pmf_alts">محصولات جایگزین</label>
					<select class="wc-product-search" multiple="multiple" style="width:50%;" id="pmf_alts" name="pmf_alts[]" data-placeholder="جستجوی محصول..." data-action="woocommerce_json_search_products_and_variations" data-exclude="<?php echo (int) $post->ID; ?>">
						<?php
						foreach ( $alts as $aid ) {
							$ap = wc_get_product( $aid );
							if ( $ap ) {
								echo '<option value="' . esc_attr( $aid ) . '" selected="selected">' . esc_html( wp_strip_all_tags( $ap->get_formatted_name() ) ) . '</option>';
							}
						}
						?>
					</select>
					<input type="hidden" name="pmf_alts_present" value="1">
				</p>
			</div>
		</div>
		<?php
	}

	public static function save( $post_id ) {
		if ( ! current_user_can( 'edit_post', $post_id ) ) {
			return;
		}
		// phpcs:disable WordPress.Security.NonceVerification -- nonce توسط خود ووکامرس بررسی شده است.
		if ( isset( $_POST['pmf_compat'] ) ) {
			$json = json_decode( wp_unslash( $_POST['pmf_compat'] ), true ); // phpcs:ignore WordPress.Security.ValidatedSanitizedInput
			if ( is_array( $json ) ) {
				PMF_DB::save_product_compat( (int) $post_id, $json );
			}
		}
		if ( isset( $_POST['pmf_codes'] ) ) {
			update_post_meta( $post_id, '_pmf_codes', sanitize_textarea_field( wp_unslash( $_POST['pmf_codes'] ) ) );
		}
		if ( isset( $_POST['pmf_alts_present'] ) ) {
			$ids = isset( $_POST['pmf_alts'] ) ? array_filter( array_map( 'absint', (array) $_POST['pmf_alts'] ) ) : array();
			$ids = array_diff( $ids, array( (int) $post_id ) );
			if ( $ids ) {
				update_post_meta( $post_id, '_pmf_alts', ',' . implode( ',', $ids ) . ',' );
			} else {
				delete_post_meta( $post_id, '_pmf_alts' );
			}
		}
		// phpcs:enable
	}
}
