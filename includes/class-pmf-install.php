<?php
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class PMF_Install {

	const DB_VERSION = '3';

	public static function activate() {
		self::create_tables();
		update_option( 'pmf_db_version', self::DB_VERSION );
		if ( false === get_option( 'pmf_settings' ) ) {
			add_option( 'pmf_settings', PMF_DB::defaults() );
		}
	}

	public static function maybe_upgrade() {
		if ( get_option( 'pmf_db_version' ) !== self::DB_VERSION ) {
			self::create_tables();
			update_option( 'pmf_db_version', self::DB_VERSION );
		}
	}

	public static function create_tables() {
		global $wpdb;
		require_once ABSPATH . 'wp-admin/includes/upgrade.php';
		$c = $wpdb->get_charset_collate();
		$p = $wpdb->prefix . 'pmf_';

		dbDelta(
			"CREATE TABLE {$p}forms (
  id bigint(20) unsigned NOT NULL AUTO_INCREMENT,
  title varchar(190) NOT NULL DEFAULT '',
  fields text NOT NULL,
  created_at datetime NOT NULL,
  PRIMARY KEY  (id)
) $c;"
		);

		dbDelta(
			"CREATE TABLE {$p}devices (
  id bigint(20) unsigned NOT NULL AUTO_INCREMENT,
  form_id bigint(20) unsigned NOT NULL,
  l1 varchar(150) NOT NULL DEFAULT '',
  l2 varchar(150) NOT NULL DEFAULT '',
  l3 varchar(150) NOT NULL DEFAULT '',
  l4 varchar(150) NOT NULL DEFAULT '',
  l5 varchar(150) NOT NULL DEFAULT '',
  PRIMARY KEY  (id),
  KEY form_id (form_id)
) $c;"
		);

		dbDelta(
			"CREATE TABLE {$p}compat (
  id bigint(20) unsigned NOT NULL AUTO_INCREMENT,
  product_id bigint(20) unsigned NOT NULL,
  device_id bigint(20) unsigned NOT NULL,
  ctype varchar(12) NOT NULL DEFAULT 'verified',
  note varchar(255) NOT NULL DEFAULT '',
  PRIMARY KEY  (id),
  UNIQUE KEY prod_dev (product_id,device_id),
  KEY device_id (device_id)
) $c;"
		);

		dbDelta(
			"CREATE TABLE {$p}missed (
  id bigint(20) unsigned NOT NULL AUTO_INCREMENT,
  form_id bigint(20) unsigned NOT NULL,
  kind varchar(10) NOT NULL DEFAULT 'filter',
  q varchar(170) NOT NULL DEFAULT '',
  hits int(10) unsigned NOT NULL DEFAULT 1,
  last_at datetime NOT NULL,
  PRIMARY KEY  (id),
  UNIQUE KEY uniq_q (form_id,kind,q)
) $c;"
		);

		dbDelta(
			"CREATE TABLE {$p}requests (
  id bigint(20) unsigned NOT NULL AUTO_INCREMENT,
  form_id bigint(20) unsigned NOT NULL DEFAULT 0,
  product_id bigint(20) unsigned NOT NULL DEFAULT 0,
  kind varchar(10) NOT NULL DEFAULT 'search',
  name varchar(150) NOT NULL DEFAULT '',
  phone varchar(40) NOT NULL DEFAULT '',
  email varchar(190) NOT NULL DEFAULT '',
  details text NOT NULL,
  query varchar(255) NOT NULL DEFAULT '',
  vals text NOT NULL,
  token varchar(40) NOT NULL DEFAULT '',
  status varchar(12) NOT NULL DEFAULT 'new',
  sms_status varchar(190) NOT NULL DEFAULT '',
  notified_ids text NOT NULL,
  created_at datetime NOT NULL,
  notified_at datetime DEFAULT NULL,
  PRIMARY KEY  (id),
  KEY status (status),
  KEY token (token),
  KEY product_id (product_id)
) $c;"
		);
	}
}
