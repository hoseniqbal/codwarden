<?php
/**
 * Uninstall: remove data only when the owner asked for it.
 *
 * @package CODWarden
 */

if ( ! defined( 'WP_UNINSTALL_PLUGIN' ) ) {
	exit;
}

$codwarden_settings = get_option( 'codwarden_settings', array() );
if ( ! is_array( $codwarden_settings ) || empty( $codwarden_settings['delete_data'] ) || 'yes' !== $codwarden_settings['delete_data'] ) {
	return;
}

global $wpdb;
foreach ( array( $wpdb->prefix . 'codwarden_phones', $wpdb->prefix . 'codwarden_events' ) as $codwarden_table ) {
	// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching, WordPress.DB.DirectDatabaseQuery.SchemaChange -- uninstall.
	$wpdb->query( $wpdb->prepare( 'DROP TABLE IF EXISTS %i', $codwarden_table ) );
}
delete_option( 'codwarden_settings' );
delete_option( 'codwarden_db_version' );
wp_clear_scheduled_hook( 'codwarden_daily' );
