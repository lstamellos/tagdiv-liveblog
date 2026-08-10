<?php
/**
 * Read-only audit of tagDiv block settings / CSS control registration.
 *
 * Run with:
 *   wp eval-file /path/to/tagdiv-liveblog/tools/block-controls-audit.php
 *
 * @package Tagdiv_Liveblog
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

function tdlb_control_summary( $param ) {
	if ( ! is_array( $param ) ) {
		return null;
	}

	$out = array();
	foreach ( array( 'param_name', 'type', 'heading', 'group', 'class' ) as $key ) {
		if ( isset( $param[ $key ] ) && is_scalar( $param[ $key ] ) ) {
			$out[ $key ] = (string) $param[ $key ];
		}
	}

	return $out;
}

function tdlb_print_param_set( $label, $params ) {
	echo $label . '_type=' . gettype( $params ) . PHP_EOL;
	if ( ! is_array( $params ) ) {
		return;
	}

	echo $label . '_count=' . count( $params ) . PHP_EOL;
	$groups = array();
	$types  = array();
	$names  = array();

	foreach ( $params as $param ) {
		if ( ! is_array( $param ) ) {
			continue;
		}

		$name  = isset( $param['param_name'] ) ? (string) $param['param_name'] : '';
		$type  = isset( $param['type'] ) ? (string) $param['type'] : '';
		$group = isset( $param['group'] ) ? (string) $param['group'] : '';

		if ( '' !== $name ) {
			$names[] = $name;
		}
		if ( '' !== $type ) {
			$types[ $type ] = isset( $types[ $type ] ) ? $types[ $type ] + 1 : 1;
		}
		if ( '' !== $group ) {
			$groups[ $group ] = isset( $groups[ $group ] ) ? $groups[ $group ] + 1 : 1;
		}

		if (
			'tdc_css' === $name
			|| false !== stripos( $name, 'css' )
			|| false !== stripos( $group, 'css' )
			|| false !== stripos( $group, 'design' )
			|| false !== stripos( $group, 'block' )
			|| 'tdc_css_editor' === $type
		) {
			echo $label . '_interesting=' . wp_json_encode( tdlb_control_summary( $param ), JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE ) . PHP_EOL;
		}
	}

	echo $label . '_groups=' . wp_json_encode( $groups, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE ) . PHP_EOL;
	echo $label . '_types=' . wp_json_encode( $types, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE ) . PHP_EOL;
	echo $label . '_has_tdc_css=' . ( in_array( 'tdc_css', $names, true ) ? 'yes' : 'no' ) . PHP_EOL;
}

echo "=== TAGDIV BLOCK CONTROL AUDIT ===\n";
echo 'wordpress_version=' . get_bloginfo( 'version' ) . PHP_EOL;
echo 'td_config=' . ( class_exists( 'td_config' ) ? 'yes' : 'no' ) . PHP_EOL;
echo 'td_api_block=' . ( class_exists( 'td_api_block' ) ? 'yes' : 'no' ) . PHP_EOL;

$general = array();
if ( class_exists( 'td_config' ) && is_callable( array( 'td_config', 'get_map_block_general_array' ) ) ) {
	$general = td_config::get_map_block_general_array();
}
tdlb_print_param_set( 'general', $general );

$blocks = array();
if ( class_exists( 'td_api_block' ) && is_callable( array( 'td_api_block', 'get_all' ) ) ) {
	$blocks = td_api_block::get_all();
}

$targets = array( 'td_block_liveblog', 'td_block_text_with_title', 'td_flex_block_1', 'td_block_1' );
foreach ( $targets as $target ) {
	if ( isset( $blocks[ $target ] ) && is_array( $blocks[ $target ] ) ) {
		echo 'block_found[' . $target . ']=yes' . PHP_EOL;
		$params = isset( $blocks[ $target ]['params'] ) ? $blocks[ $target ]['params'] : null;
		tdlb_print_param_set( 'block_' . $target, $params );
	} else {
		echo 'block_found[' . $target . ']=no' . PHP_EOL;
	}
}

echo "read_only_block_control_audit_completed=yes\n";
