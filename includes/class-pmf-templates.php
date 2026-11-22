<?php
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/** قالب‌های آمادهٔ راه‌اندازی یک‌کلیکی. */
class PMF_Templates {

	public static function all() {
		return array(
			'auto'      => array(
				'icon'    => '🚗',
				'title'   => 'قطعات خودرو و موتورسیکلت',
				'desc'    => 'برند ← مدل ← سال ساخت',
				'form'    => 'جستجوی قطعهٔ سازگار با خودرو شما',
				'fields'  => array( 'برند خودرو', 'مدل', 'سال ساخت' ),
				'samples' => "پژو > 206 > 1398\nپژو > 206 > 1399\nپژو > 405 > 1395\nسمند > LX > 1398\nپراید > 111 > 1390\nتیبا > تیبا ۲ > 1399",
			),
			'mobile'    => array(
				'icon'    => '📱',
				'title'   => 'موبایل و لپ‌تاپ',
				'desc'    => 'برند ← مدل دستگاه',
				'form'    => 'لوازم جانبی سازگار با دستگاه شما',
				'fields'  => array( 'برند', 'مدل دستگاه' ),
				'samples' => "سامسونگ > Galaxy S24\nسامسونگ > Galaxy A55\nاپل > iPhone 15\nاپل > iPhone 14\nشیائومی > Redmi Note 13",
			),
			'printer'   => array(
				'icon'    => '🖨️',
				'title'   => 'کارتریج و لوازم چاپگر',
				'desc'    => 'برند ← مدل چاپگر',
				'form'    => 'کارتریج و جوهر سازگار با چاپگر شما',
				'fields'  => array( 'برند چاپگر', 'مدل چاپگر' ),
				'samples' => "HP > LaserJet Pro M404dn\nHP > LaserJet Pro M130fw\nکانن > i-SENSYS LBP6030\nبرادر > HL-L2321D",
			),
			'appliance' => array(
				'icon'    => '🏠',
				'title'   => 'لوازم خانگی',
				'desc'    => 'نوع کالا ← برند ← مدل',
				'form'    => 'فیلتر و لوازم یدکی لوازم خانگی',
				'fields'  => array( 'نوع کالا', 'برند', 'مدل' ),
				'samples' => "یخچال > سامسونگ > RT38\nیخچال > ال‌جی > GR-B247\nماشین لباسشویی > بوش > WAT28\nجاروبرقی > فیلیپس > FC9",
			),
			'tools'     => array(
				'icon'    => '🛠️',
				'title'   => 'ابزار و تجهیزات صنعتی',
				'desc'    => 'نوع ابزار ← برند ← مدل',
				'form'    => 'لوازم و قطعات سازگار با ابزار شما',
				'fields'  => array( 'نوع ابزار', 'برند', 'مدل' ),
				'samples' => "دریل > بوش > GSB 13 RE\nدریل > مکیتا > HP1630\nفرز > مکیتا > 9553\nفرز > بوش > GWS 750",
			),
			'custom'    => array(
				'icon'    => '✨',
				'title'   => 'فرم سفارشی',
				'desc'    => 'فیلدها را خودتان تعریف می‌کنید',
				'form'    => '',
				'fields'  => array(),
				'samples' => '',
			),
		);
	}
}
