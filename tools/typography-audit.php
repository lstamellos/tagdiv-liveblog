<?php
/**
 * Read-only audit of the installed tagDiv typography control contract.
 *
 * Run with:
 *   wp eval-file /path/to/tagdiv-liveblog/tools/typography-audit.php
 *
 * @package Tagdiv_Liveblog
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

function tdlb_typography_print_param( $block_id, $param ) {
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
	);
	$out = array();

	foreach ( $keys as $key ) {
		if ( array_key_exists( $key, $param ) ) {
			$value = $param[ $key ];
			if ( is_scalar( $value ) || null === $value ) {
				$out[ $key ] = $value;
			} elseif ( is_array( $value ) ) {
				$out[ $key ] = $value;
			}
		}
	}

	echo 'typography_param[' . $block_id . ']=' . wp_json_encode( $out, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE ) . PHP_EOL;
}

function tdlb_typography_walk( $block_id, $value, &$count ) {
	if ( $count >= 100 || ! is_array( $value ) ) {
		return;
	}

	if ( isset( $value['param_name'] ) || isset( $value['type'] ) ) {
		$name    = isset( $value['param_name'] ) ? strtolower( (string) $value['param_name'] ) : '';
		$type    = isset( $value['type'] ) ? strtolower( (string) $value['type'] ) : '';
		$heading = isset( $value['heading'] ) ? strtolower( (string) $value['heading'] ) : '';
		$group   = isset( $value['group'] ) ? strtolower( (string) $value['group'] ) : '';
		$haystack = implode( ' ', array( $name, $type, $heading, $group ) );

		if (
			false !== strpos( $haystack, 'font' )
			|| false !== strpos( $haystack, 'typograph' )
			|| false !== strpos( $haystack, 'line_height' )
			|| false !== strpos( $haystack, 'letter_spacing' )
			|| preg_match( '/(^|_)f_[a-z0-9_]+/', $name )
		) {
			tdlb_typography_print_param( $block_id, $value );
			$count++;
		}
	}

	foreach ( $value as $child ) {
		if ( is_array( $child ) ) {
			tdlb_typography_walk( $block_id, $child, $count );
		}
	}
}

function tdlb_typography_source_scan( $root ) {
	if ( ! is_dir( $root ) ) {
		echo 'source_root_missing=' . $root . PHP_EOL;
		return;
	}

	$needles = array(
		'google_fonts',
		'font_family',
		'font_size',
		'line_height',
		'letter_spacing',
		'typography',
	);
	$matches = 0;
	$iterator = new RecursiveIteratorIterator(
		new RecursiveDirectoryIterator( $root, FilesystemIterator::SKIP_DOTS )
	);

	foreach ( $iterator as $file ) {
		if ( $matches >= 80 || ! $file->isFile() || 'php' !== strtolower( $file->getExtension() ) ) {
			continue;
		}

		$path = $file->getPathname();
		if ( $file->getSize() > 2 * 1024 * 1024 ) {
			continue;
		}

		$lines = @file( $path, FILE_IGNORE_NEW_LINES );
		if ( ! is_array( $lines ) ) {
			continue;
		}

		foreach ( $lines as $index => $line ) {
			$lower = strtolower( $line );
			$hit   = false;
			foreach ( $needles as $needle ) {
				if ( false !== strpos( $lower, $needle ) ) {
					$hit = true;
					break;
				}
			}

			if ( ! $hit ) {
				continue;
			}

			$relative = ltrim( str_replace( $root, '', $path ), DIRECTORY_SEPARATOR );
			echo 'source_match=' . $relative . ':' . ( $index + 1 ) . ':' . trim( $line ) . PHP_EOL;
			$matches++;

			if ( $matches >= 80 ) {
				break 2;
			}
		}
	}
}

echo "=== TAGDIV TYPOGRAPHY RUNTIME ===\n";

echo 'wordpress_version=' . get_bloginfo( 'version' ) . PHP_EOL;
echo 'php_version=' . PHP_VERSION . PHP_EOL;
echo 'td_api_block=' . ( class_exists( 'td_api_block' ) ? 'yes' : 'no' ) . PHP_EOL;
echo 'td_config=' . ( class_exists( 'td_config' ) ? 'yes' : 'no' ) . PHP_EOL;

if ( class_exists( 'td_config' ) ) {
	$ref = new ReflectionClass( 'td_config' );
	echo 'td_config_file=' . $ref->getFileName() . PHP_EOL;
}

if ( class_exists( 'td_api_block' ) && is_callable( array( 'td_api_block', 'get_all' ) ) ) {
	$blocks = td_api_block::get_all();
	echo 'block_registry_type=' . gettype( $blocks ) . PHP_EOL;

	if ( is_array( $blocks ) ) {
		echo 'block_registry_count=' . count( $blocks ) . PHP_EOL;
		$count = 0;
		foreach ( $blocks as $block_id => $config ) {
			tdlb_typography_walk( (string) $block_id, $config, $count );
			if ( $count >= 100 ) {
				break;
			}
		}
		echo 'typography_param_count=' . $count . PHP_EOL;
	}
}

echo "\n=== TAGDIV TYPOGRAPHY SOURCE MATCHES ===\n";

$roots = array(
	WP_PLUGIN_DIR . '/td-composer/legacy/Newspaper/includes',
	WP_PLUGIN_DIR . '/td-composer/legacy/common',
);

foreach ( $roots as $root ) {
	echo 'source_root=' . $root . PHP_EOL;
	tdlb_typography_source_scan( $root );
}

echo "\n=== AUDIT COMPLETE ===\n";
echo "read_only_typography_audit_completed=yes\n";
