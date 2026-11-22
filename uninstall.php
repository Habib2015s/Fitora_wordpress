<?php
if ( ! defined( 'WP_UNINSTALL_PLUGIN' ) ) {
	exit;
}

$pmf_settings = get_option( 'pmf_settings' );
if ( is_array( $pmf_settings ) && ! empty( $pmf_settings['delete_data'] ) ) {
	global $wpdb;
	foreach ( array( 'forms', 'devices', 'compat', 'missed', 'requests' ) as $pmf_t ) {
		$wpdb->query( "DROP TABLE IF EXISTS {$wpdb->prefix}pmf_{$pmf_t}" ); // phpcs:ignore WordPress.DB
	}
	delete_option( 'pmf_settings' );
	delete_option( 'pmf_db_version' );
	delete_post_meta_by_key( '_pmf_codes' );
	delete_post_meta_by_key( '_pmf_alts' );
	delete_post_meta_by_key( '_pmf_restocked_at' );
	delete_option( 'pmf_pages' );
	delete_transient( 'pmf_recent_rows' );
}
