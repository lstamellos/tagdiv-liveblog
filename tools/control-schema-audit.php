<?php
/**
 * Compact read-only audit of native tagDiv typography and icon controls.
 *
 * Run with:
 *   wp eval-file /path/to/tagdiv-liveblog/tools/control-schema-audit.php
 *
 * @package Tagdiv_Liveblog
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

function tdlb_control_schema_compact_value( $value ) {
	if ( is_scalar( $value ) || null === $value ) {
		return $value;
	}

	if ( ! is_array( $value ) ) {
		return gettype( $value );
	}

	$out = array();
	$count = 0;
	foreach ( $value as $key => $item ) {
		if ( $count >= 12 ) {
			$out['__truncated__'] = count( $value );
			break;
		}

		if ( is_scalar( $item ) || null === $item ) {
			$out[ $key ] = $item;
		} elseif ( is_array( $item ) ) {
			$out[ $key ] = array_slice( $item, 0, 8, true );
		} else {
			$out[ $key ] = gettype( $item );
		}
		$count++;
	}
	return $out;
}

function tdlb_control_schema_print( $kind, $block_id, $param ) {
	$keys = array(
		'param_name',
		'type',
		'heading',
		'group',
		'value',
		'std',
		'placeholder',
		'class',
		'css',
		'selector',
		'dependency',
	);

	$out = array();
	foreach ( $keys as $key ) {
		if ( array_key_exists( $key, $param ) ) {
			$out[ $key ] = tdlb_control_schema_compact_value( $param[ $key ] );
		}
	}

	echo $kind . '_sample[' . $block_id . ']=' .
		wp_json_encode( $out, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE ) .
		PHP_EOL;
}

function tdlb_control_schema_walk( $block_id, $value, &$font_count, &$icon_count, &$font_types, &$icon_types ) {
	if ( ! is_array( $value ) ) {
		return;
	}

	if ( isset( $value['param_name'] ) || isset( $value['type'] ) ) {
		$name    = isset( $value['param_name'] ) ? strtolower( (string) $value['param_name'] ) : '';
		$type    = isset( $value['type'] ) ? strtolower( (string) $value['type'] ) : '';
		$heading = isset( $value['heading'] ) ? strtolower( (string) $value['heading'] ) : '';
		$group   = isset( $value['group'] ) ? strtolower( (string) $value['group'] ) : '';
		$haystack = implode( ' ', array( $name, $type, $heading, $group ) );

		$is_font = (
			false !== strpos( $haystack, 'font' )
			|| false !== strpos( $haystack, 'typograph' )
			|| false !== strpos( $haystack, 'line_height' )
			|| false !== strpos( $haystack, 'letter_spacing' )
			|| preg_match( '/(^|_)f_[a-z0-9_]+/', $name )
		);

		$is_icon = (
			false !== strpos( $haystack, 'icon' )
			|| false !== strpos( $type, 'glyph' )
		);

		if ( $is_font ) {
			$font_types[ $type ?: '(none)' ] = isset( $font_types[ $type ?: '(none)' ] )
				? $font_types[ $type ?: '(none)' ] + 1
				: 1;
			if ( $font_count < 24 ) {
				tdlb_control_schema_print( 'font', $block_id, $value );
			}
			$font_count++;
		}

		if ( $is_icon ) {
			$icon_types[ $type ?: '(none)' ] = isset( $icon_types[ $type ?: '(none)' ] )
				? $icon_types[ $type ?: '(none)' ] + 1
				: 1;
			if ( $icon_count < 24 ) {
				tdlb_control_schema_print( 'icon', $block_id, $value );
			}
			$icon_count++;
		}
	}

	foreach ( $value as $child ) {
		if ( is_array( $child ) ) {
			tdlb_control_schema_walk( $block_id, $child, $font_count, $icon_count, $font_types, $icon_types );
		}
	}
}

echo "=== TAGDIV NATIVE CONTROL SCHEMA ===\n";
echo 'wordpress_version=' . get_bloginfo( 'version' ) . PHP_EOL;
echo 'php_version=' . PHP_VERSION . PHP_EOL;
echo 'td_api_block=' . ( class_exists( 'td_api_block' ) ? 'yes' : 'no' ) . PHP_EOL;
echo 'td_config=' . ( class_exists( 'td_config' ) ? 'yes' : 'no' ) . PHP_EOL;

$font_count = 0;
$icon_count = 0;
$font_types = array();
$icon_types = array();

if ( class_exists( 'td_api_block' ) && is_callable( array( 'td_api_block', 'get_all' ) ) ) {
	$blocks = td_api_block::get_all();
	echo 'block_registry_type=' . gettype( $blocks ) . PHP_EOL;

	if ( is_array( $blocks ) ) {
		echo 'block_registry_count=' . count( $blocks ) . PHP_EOL;
		foreach ( $blocks as $block_id => $config ) {
			tdlb_control_schema_walk(
				(string) $block_id,
				$config,
				$font_count,
				$icon_count,
				$font_types,
				$icon_types
			);
		}
	}
}

echo 'font_match_count=' . $font_count . PHP_EOL;
echo 'icon_match_count=' . $icon_count . PHP_EOL;
echo 'font_type_counts=' . wp_json_encode( $font_types, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE ) . PHP_EOL;
echo 'icon_type_counts=' . wp_json_encode( $icon_types, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE ) . PHP_EOL;
echo "read_only_control_schema_audit_completed=yes\n";
