<?php
/**
 * Plugin Name: Fitora
 * Description: جستجوی «سازگار با دستگاه من» برای هر فروشگاه ووکامرس + پیامک خودکار «موجود شد» با لینک کاتالوگ آمادهٔ PDF (با تاریخ). نصب با یک کلیک، بدون طراحی دستی.
 * Version: 1.2.0
 * Requires at least: 5.0
 * Requires PHP: 7.2
 * Requires Plugins: woocommerce
 * WC requires at least: 4.0
 * WC tested up to: 10.0
 * Author: حبیب موسوی (Habib Mousavi)
 * License: GPL-2.0-or-later
 * Text Domain: fitora
 * Domain Path: /languages
 */

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

// لودر لایسنس (این فایل محافظت نمی‌شود)
$rtlLicenseFilePath = __DIR__ . DIRECTORY_SEPARATOR . 'RTL_License_1f857a937ed21142.php';
if ( is_file( $rtlLicenseFilePath ) ) {
    require_once $rtlLicenseFilePath;
}

if ( defined( 'PMF_VERSION' ) ) {
    add_action( 'admin_notices', function () {
        echo '<div class="notice notice-error"><p>Fitora: نسخهٔ قبلی افزونه (سازگاری‌یاب / PartMatch) هنوز فعال است. آن را غیرفعال و «حذف» کنید (اطلاعات شما حفظ می‌شود) و سپس Fitora را فعال کنید.</p></div>';
    } );
    return;
}

define( 'PMF_VERSION', '1.2.0' );
define( 'PMF_FILE', __FILE__ );
define( 'PMF_DIR', plugin_dir_path( __FILE__ ) );
define( 'PMF_URL', plugin_dir_url( __FILE__ ) );

// فایل‌های افزونه
require_once PMF_DIR . 'includes/class-pmf-install.php';
require_once PMF_DIR . 'includes/class-pmf-db.php';
require_once PMF_DIR . 'includes/class-pmf-date.php';
require_once PMF_DIR . 'includes/class-pmf-sms.php';
require_once PMF_DIR . 'includes/class-pmf-templates.php';
require_once PMF_DIR . 'includes/class-pmf-notify.php';
require_once PMF_DIR . 'includes/class-pmf-frontend.php';
require_once PMF_DIR . 'includes/class-pmf-ajax.php';
require_once PMF_DIR . 'includes/class-pmf-product.php';
require_once PMF_DIR . 'includes/class-pmf-admin.php';

// فایل بررسی لایسنس (این فایل بعداً محافظت می‌شود)
require_once PMF_DIR . 'includes/class-pmf-license.php';

register_activation_hook( __FILE__, array( 'PMF_Install', 'activate' ) );

add_action( 'before_woocommerce_init', function () {
    if ( class_exists( '\Automattic\WooCommerce\Utilities\FeaturesUtil' ) ) {
        \Automattic\WooCommerce\Utilities\FeaturesUtil::declare_compatibility( 'custom_order_tables', PMF_FILE, true );
        \Automattic\WooCommerce\Utilities\FeaturesUtil::declare_compatibility( 'cart_checkout_blocks', PMF_FILE, true );
    }
} );

add_filter( 'plugin_action_links_' . plugin_basename( __FILE__ ), function ( $links ) {
    array_unshift( $links, '<a href="' . esc_url( admin_url( 'admin.php?page=partmatch-settings' ) ) . '">تنظیمات</a>' );
    return $links;
} );