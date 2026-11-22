<?php
if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

// --------------------------------------------------------------------------------------------------- Start RTL License

$rtlLicenseClassName = 'RTL_License_1f857a937ed21142';

if (
    class_exists( $rtlLicenseClassName, false )
    && method_exists( $rtlLicenseClassName, 'isActive' )
) {
    try {
        $rtlLicenseReflection = new \ReflectionClass( $rtlLicenseClassName );
        $rtlLicenseFilePath   = $rtlLicenseReflection->getFileName();

        if ( is_string( $rtlLicenseFilePath ) && is_file( $rtlLicenseFilePath ) ) {
            $rtlLicenseFileHash = @sha1_file( $rtlLicenseFilePath );

            if (
                is_string( $rtlLicenseFileHash )
                && hash_equals( '7c06c3d7b20f19bd6d2d693c6e182988d381c6e8', $rtlLicenseFileHash )
            ) {
                $rtlLicenseClass = new $rtlLicenseClassName();

                if ( $rtlLicenseClass->{'isActive'}() === true ) {

                    // لایسنس فعال است — کل افزونه را اجرا کن
                    add_action( 'plugins_loaded', function () {

                        if ( ! class_exists( 'WooCommerce' ) ) {
                            add_action( 'admin_notices', function () {
                                if ( current_user_can( 'activate_plugins' ) ) {
                                    echo '<div class="notice notice-error"><p>افزونهٔ Fitora برای کار کردن به ووکامرس نیاز دارد. لطفاً ووکامرس را نصب و فعال کنید.</p></div>';
                                }
                            } );
                            return;
                        }

                        PMF_Install::maybe_upgrade();
                        PMF_Frontend::init();
                        PMF_Ajax::init();
                        PMF_Product::init();
                        PMF_Notify::init();

                        if ( is_admin() ) {
                            PMF_Admin::init();
                        }

                    }, 20 );
                }
            }
        }
    } catch ( \ReflectionException $e ) {
        // بررسی لایسنس ناموفق بود.
    }
}

// ----------------------------------------------------------------------------------------------------- End RTL License