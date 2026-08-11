<?php
/**
 * Read-only audit for GitHub update metadata and archived Liveblog state.
 *
 * Run with:
 *   wp eval-file /path/to/tagdiv-liveblog/tools/updater-archive-audit.php
 *
 * To resolve the Liveblog state for a specific article, run WP-CLI with --url
 * pointing to that article URL.
 *
 * @package Tagdiv_Liveblog
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

if ( ! function_exists( 'get_plugin_data' ) ) {
	require_once ABSPATH . 'wp-admin/includes/plugin.php';
}

$plugin_basename = 'tagdiv-liveblog/tagdiv-liveblog.php';
$plugin_file     = TAGDIV_LIVEBLOG_PATH . 'tagdiv-liveblog.php';
$plugin_data     = get_plugin_data( $plugin_file, false, false );

$auto_updates = get_site_option( 'auto_update_plugins', array() );
if ( ! is_array( $auto_updates ) ) {
	$auto_updates = array();
}

$update_transient = get_site_transient( 'update_plugins' );
$in_response      = is_object( $update_transient ) && isset( $update_transient->response[ $plugin_basename ] );
$in_no_update     = is_object( $update_transient ) && isset( $update_transient->no_update[ $plugin_basename ] );

echo "=== PLUGIN ===\n";
echo 'version=' . ( isset( $plugin_data['Version'] ) ? $plugin_data['Version'] : '' ) . PHP_EOL;
echo 'update_uri=' . ( isset( $plugin_data['UpdateURI'] ) ? $plugin_data['UpdateURI'] : '' ) . PHP_EOL;
echo 'updater_class=' . ( class_exists( 'Tagdiv_Liveblog_Updater' ) ? 'yes' : 'no' ) . PHP_EOL;
echo 'update_filter=' . ( has_filter( 'update_plugins_github.com', array( 'Tagdiv_Liveblog_Updater', 'filter_update' ) ) ? 'yes' : 'no' ) . PHP_EOL;
echo 'auto_update_enabled=' . ( in_array( $plugin_basename, $auto_updates, true ) ? 'yes' : 'no' ) . PHP_EOL;

echo "\n=== WORDPRESS UPDATE TRANSIENT ===\n";
echo 'update_response=' . ( $in_response ? 'PRESENT' : 'ABSENT' ) . PHP_EOL;
echo 'no_update_response=' . ( $in_no_update ? 'PRESENT' : 'ABSENT' ) . PHP_EOL;
echo 'update_supported_metadata=' . ( $in_response || $in_no_update ? 'yes' : 'no' ) . PHP_EOL;

echo "\n=== GITHUB LATEST STABLE RELEASE ===\n";
if ( class_exists( 'Tagdiv_Liveblog_Updater' ) ) {
	$release = Tagdiv_Liveblog_Updater::get_latest_release( true );
	if ( is_wp_error( $release ) ) {
		echo 'github_release_error=' . $release->get_error_code() . PHP_EOL;
		echo 'github_release_error_message=' . $release->get_error_message() . PHP_EOL;
	} else {
		echo 'latest_version=' . $release['version'] . PHP_EOL;
		echo 'package=' . $release['package'] . PHP_EOL;
		echo 'sha256=' . $release['sha256'] . PHP_EOL;
		echo 'published_at=' . $release['published_at'] . PHP_EOL;
	}
}

echo "\n=== LIVEBLOG STATE ===\n";
echo 'resolved_post_id=' . Tagdiv_Liveblog_Plugin::get_liveblog_post_id() . PHP_EOL;
echo 'liveblog_state=' . ( Tagdiv_Liveblog_Plugin::get_liveblog_state() ?: 'none' ) . PHP_EOL;
echo 'is_liveblog=' . ( Tagdiv_Liveblog_Plugin::is_liveblog_post() ? 'yes' : 'no' ) . PHP_EOL;
echo 'is_archived=' . ( Tagdiv_Liveblog_Plugin::is_archived_liveblog_post() ? 'yes' : 'no' ) . PHP_EOL;

echo "\n=== UPDATE POLICY CONTRACT ===\n";
echo "forced_auto_update=no\n";
echo "native_wordpress_auto_update_setting_authoritative=yes\n";
echo "audit_completed=yes\n";
