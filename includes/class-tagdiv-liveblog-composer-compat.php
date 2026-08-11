<?php
/**
 * tagDiv Composer compatibility adjustments.
 *
 * @package Tagdiv_Liveblog
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

final class Tagdiv_Liveblog_Composer_Compat {
	/**
	 * Register compatibility hooks.
	 *
	 * @return void
	 */
	public static function init() {
		add_action( 'tdc_init', array( __CLASS__, 'on_tdc_init' ), 12 );
		add_filter( 'shortcode_atts_td_block_liveblog', array( __CLASS__, 'normalize_shortcode_atts' ), 10, 4 );
	}

	/**
	 * Patch the registered block after tagDiv Composer is ready.
	 *
	 * @return void
	 */
	public static function on_tdc_init() {
		if ( did_action( 'tdc_loaded' ) ) {
			self::patch_registered_block();
			return;
		}

		add_action( 'tdc_loaded', array( __CLASS__, 'patch_registered_block' ), 20 );
	}

	/**
	 * Rename the label toggle parameter in the Composer registry.
	 *
	 * Some tagDiv Composer builds expose the original key_label dropdown but do
	 * not serialize its selected non-default value into the saved shortcode.
	 * A dedicated parameter name avoids that save-layer quirk while the runtime
	 * filter below preserves the existing renderer contract.
	 *
	 * @return void
	 */
	public static function patch_registered_block() {
		if (
			! class_exists( 'td_api_block' )
			|| ! is_callable( array( 'td_api_block', 'get_all' ) )
			|| ! is_callable( array( 'td_api_block', 'update_key' ) )
		) {
			return;
		}

		$blocks = td_api_block::get_all();
		if (
			! is_array( $blocks )
			|| ! isset( $blocks['td_block_liveblog'] )
			|| ! is_array( $blocks['td_block_liveblog'] )
			|| ! isset( $blocks['td_block_liveblog']['params'] )
			|| ! is_array( $blocks['td_block_liveblog']['params'] )
		) {
			return;
		}

		$params  = $blocks['td_block_liveblog']['params'];
		$changed = false;

		foreach ( $params as &$param ) {
			if (
				is_array( $param )
				&& isset( $param['param_name'] )
				&& 'key_label' === $param['param_name']
			) {
				$param['param_name'] = 'key_entry_label_enabled';
				$changed             = true;
				break;
			}
		}
		unset( $param );

		if ( $changed ) {
			td_api_block::update_key( 'td_block_liveblog', 'params', $params );
		}
	}

	/**
	 * Map the Composer-safe parameter back to the legacy renderer attribute.
	 *
	 * Existing content using key_label continues to work unchanged. When the new
	 * parameter is present it takes precedence and is normalized to yes/no.
	 *
	 * @param array  $out       Parsed shortcode attributes.
	 * @param array  $pairs     Supported shortcode defaults.
	 * @param array  $atts      Raw shortcode attributes.
	 * @param string $shortcode Shortcode name.
	 * @return array
	 */
	public static function normalize_shortcode_atts( $out, $pairs, $atts, $shortcode ) {
		unset( $pairs, $shortcode );

		if ( ! is_array( $out ) || ! is_array( $atts ) || ! array_key_exists( 'key_entry_label_enabled', $atts ) ) {
			return $out;
		}

		$value = sanitize_key( (string) $atts['key_entry_label_enabled'] );
		if ( in_array( $value, array( 'yes', 'no' ), true ) ) {
			$out['key_label'] = $value;
		}

		return $out;
	}
}
