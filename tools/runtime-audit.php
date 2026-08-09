<?php
/**
 * Read-only runtime audit for tagDiv Liveblog integration.
 *
 * Run with:
 *   wp eval-file tools/runtime-audit.php
 *
 * This script performs no writes.
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

if ( ! function_exists( 'get_plugins' ) ) {
	require_once ABSPATH . 'wp-admin/includes/plugin.php';
}

function tdlb_audit_line( $key, $value ) {
	if ( is_bool( $value ) ) {
		$value = $value ? 'yes' : 'no';
	} elseif ( is_array( $value ) ) {
		$value = implode( ',', array_map( 'strval', $value ) );
	} elseif ( null === $value ) {
		$value = 'null';
	}

	$line = $key . '=' . (string) $value;
	if ( defined( 'WP_CLI' ) && WP_CLI ) {
		WP_CLI::line( $line );
	} else {
		echo $line . "\n";
	}
}

function tdlb_audit_section( $title ) {
	$line = "\n=== " . $title . " ===";
	if ( defined( 'WP_CLI' ) && WP_CLI ) {
		WP_CLI::line( $line );
	} else {
		echo $line . "\n";
	}
}

function tdlb_audit_method( $class, $method ) {
	$key = 'method[' . $class . '::' . $method . ']';
	if ( ! class_exists( $class ) || ! method_exists( $class, $method ) ) {
		tdlb_audit_line( $key, 'missing' );
		return;
	}

	try {
		$reflection = new ReflectionMethod( $class, $method );
		$visibility = $reflection->isPublic() ? 'public' : ( $reflection->isProtected() ? 'protected' : 'private' );
		tdlb_audit_line(
			$key,
			$visibility . ';static=' . ( $reflection->isStatic() ? 'yes' : 'no' ) . ';params=' . $reflection->getNumberOfParameters() . ';required=' . $reflection->getNumberOfRequiredParameters()
		);
	} catch ( ReflectionException $exception ) {
		tdlb_audit_line( $key, 'reflection_error' );
	}
}

tdlb_audit_section( 'WORDPRESS / PHP' );
global $wp_version, $wpdb;
tdlb_audit_line( 'wordpress_version', $wp_version );
tdlb_audit_line( 'php_version', PHP_VERSION );
tdlb_audit_line( 'multisite', is_multisite() );
tdlb_audit_line( 'home_url', home_url( '/' ) );
tdlb_audit_line( 'site_url', site_url( '/' ) );

$current_theme = wp_get_theme();
$parent_theme  = $current_theme->parent();

tdlb_audit_section( 'THEME' );
tdlb_audit_line( 'stylesheet', $current_theme->get_stylesheet() );
tdlb_audit_line( 'theme_name', $current_theme->get( 'Name' ) );
tdlb_audit_line( 'theme_version', $current_theme->get( 'Version' ) );
tdlb_audit_line( 'template', $current_theme->get_template() );
tdlb_audit_line( 'parent_name', $parent_theme ? $parent_theme->get( 'Name' ) : '' );
tdlb_audit_line( 'parent_version', $parent_theme ? $parent_theme->get( 'Version' ) : '' );

$plugins = get_plugins();
$active  = (array) get_option( 'active_plugins', array() );
$network = is_multisite() ? array_keys( (array) get_site_option( 'active_sitewide_plugins', array() ) ) : array();

tdlb_audit_section( 'RELEVANT PLUGINS' );
foreach ( $plugins as $plugin_file => $data ) {
	$name = isset( $data['Name'] ) ? (string) $data['Name'] : '';
	$haystack = strtolower( $plugin_file . ' ' . $name );
	if (
		false === strpos( $haystack, 'td-composer' ) &&
		false === strpos( $haystack, 'tagdiv composer' ) &&
		false === strpos( $haystack, 'liveblog' )
	) {
		continue;
	}

	$state = in_array( $plugin_file, $active, true ) || in_array( $plugin_file, $network, true ) ? 'active' : 'inactive';
	tdlb_audit_line(
		'plugin[' . $plugin_file . ']',
		$name . ';version=' . ( isset( $data['Version'] ) ? $data['Version'] : '' ) . ';state=' . $state
	);
}

tdlb_audit_section( 'TAGDIV RUNTIME' );
foreach ( array( 'td_api_block', 'td_block', 'td_config', 'td_util' ) as $class ) {
	tdlb_audit_line( 'class[' . $class . ']', class_exists( $class ) );
	if ( class_exists( $class ) ) {
		try {
			$reflection = new ReflectionClass( $class );
			tdlb_audit_line( 'class_file[' . $class . ']', $reflection->getFileName() );
		} catch ( ReflectionException $exception ) {
			tdlb_audit_line( 'class_file[' . $class . ']', 'reflection_error' );
		}
	}
}

tdlb_audit_method( 'td_api_block', 'add' );
tdlb_audit_method( 'td_block', 'render' );
tdlb_audit_method( 'td_block', 'get_block_classes' );
tdlb_audit_method( 'td_block', 'get_block_css' );
tdlb_audit_method( 'td_block', 'get_shortcode_att' );
tdlb_audit_method( 'td_util', 'tdc_is_live_editor_iframe' );
tdlb_audit_method( 'td_util', 'tdc_is_live_editor_ajax' );
tdlb_audit_line( 'did_action[tdc_init]', did_action( 'tdc_init' ) );
tdlb_audit_line( 'did_action[tdc_loaded]', did_action( 'tdc_loaded' ) );

if ( class_exists( 'td_config' ) ) {
	tdlb_audit_method( 'td_config', 'get_map_block_general_array' );
}

tdlb_audit_section( 'LIVEBLOG RUNTIME' );
tdlb_audit_line( 'class[WPCOM_Liveblog]', class_exists( 'WPCOM_Liveblog' ) );
if ( class_exists( 'WPCOM_Liveblog' ) ) {
	tdlb_audit_line( 'liveblog_version_constant', defined( 'WPCOM_Liveblog::VERSION' ) ? WPCOM_Liveblog::VERSION : '' );
	tdlb_audit_line( 'liveblog_key', defined( 'WPCOM_Liveblog::KEY' ) ? WPCOM_Liveblog::KEY : '' );
	tdlb_audit_line( 'supported_post_types', isset( WPCOM_Liveblog::$supported_post_types ) ? WPCOM_Liveblog::$supported_post_types : array() );
	tdlb_audit_method( 'WPCOM_Liveblog', 'is_liveblog_post' );
	tdlb_audit_method( 'WPCOM_Liveblog', 'add_liveblog_to_content' );
	tdlb_audit_method( 'WPCOM_Liveblog', 'enqueue_scripts' );
	tdlb_audit_line( 'hook[wp_enqueue_scripts:WPCOM_Liveblog::enqueue_scripts]', has_action( 'wp_enqueue_scripts', array( 'WPCOM_Liveblog', 'enqueue_scripts' ) ) );
}

if ( class_exists( 'WPCOM_Liveblog_Entry' ) ) {
	try {
		$reflection = new ReflectionClass( 'WPCOM_Liveblog_Entry' );
		tdlb_audit_line( 'class_file[WPCOM_Liveblog_Entry]', $reflection->getFileName() );
	} catch ( ReflectionException $exception ) {
		tdlb_audit_line( 'class_file[WPCOM_Liveblog_Entry]', 'reflection_error' );
	}
}

tdlb_audit_section( 'LIVEBLOG POSTS' );
$states = $wpdb->get_results(
	"SELECT pm.meta_value AS state, COUNT(*) AS total
	 FROM {$wpdb->postmeta} pm
	 INNER JOIN {$wpdb->posts} p ON p.ID = pm.post_id
	 WHERE pm.meta_key = 'liveblog'
	 GROUP BY pm.meta_value
	 ORDER BY pm.meta_value",
	ARRAY_A
);

if ( empty( $states ) ) {
	tdlb_audit_line( 'liveblog_state_counts', 'none' );
} else {
	foreach ( $states as $row ) {
		tdlb_audit_line( 'liveblog_state[' . (string) $row['state'] . ']', (int) $row['total'] );
	}
}

$recent = $wpdb->get_results(
	"SELECT p.ID, p.post_type, p.post_status, p.post_date, p.post_title, pm.meta_value AS state
	 FROM {$wpdb->posts} p
	 INNER JOIN {$wpdb->postmeta} pm ON pm.post_id = p.ID AND pm.meta_key = 'liveblog'
	 ORDER BY p.post_date DESC
	 LIMIT 10",
	ARRAY_A
);

foreach ( $recent as $row ) {
	tdlb_audit_line(
		'liveblog_post[' . (int) $row['ID'] . ']',
		'state=' . $row['state'] . ';type=' . $row['post_type'] . ';status=' . $row['post_status'] . ';date=' . $row['post_date'] . ';title=' . preg_replace( '/\s+/', ' ', (string) $row['post_title'] )
	);
}

tdlb_audit_section( 'LEGACY CONTROL PANEL' );
tdlb_audit_line( 'class[WP_Liveblog_Control_Panel]', class_exists( 'WP_Liveblog_Control_Panel' ) );
tdlb_audit_line( 'option[wlcp_settings]', false !== get_option( 'wlcp_settings', false ) );
tdlb_audit_line( 'option[wlcp_scan_state]', false !== get_option( 'wlcp_scan_state', false ) );
tdlb_audit_line( 'option[wlcp_scan_results]', false !== get_option( 'wlcp_scan_results', false ) );

tdlb_audit_section( 'DONE' );
tdlb_audit_line( 'read_only_audit_completed', true );
