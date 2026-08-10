<?php
/**
 * Per-block Liveblog pagination size adapter.
 *
 * @package Tagdiv_Liveblog
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

final class Tagdiv_Liveblog_Pagination {
	const DEFAULT_ENTRIES_PER_PAGE = 20;
	const MAX_ENTRIES_PER_PAGE     = 100;

	/**
	 * Register hooks.
	 *
	 * Newspaper can fire tdc_loaded before or after tdc_init depending on the
	 * request surface. Register on both lifecycle points so the control is added
	 * after td_block_liveblog exists in either ordering. register_control() is
	 * idempotent and returns immediately when the parameter is already present.
	 *
	 * @return void
	 */
	public static function init() {
		add_action( 'tdc_init', array( __CLASS__, 'register_control' ), 12 );
		add_action( 'tdc_loaded', array( __CLASS__, 'register_control' ), 20 );

		add_filter( 'shortcode_atts_td_block_liveblog', array( __CLASS__, 'filter_shortcode_atts' ), 10, 4 );
		add_filter( 'do_shortcode_tag', array( __CLASS__, 'decorate_block_output' ), 10, 4 );
		add_filter( 'liveblog_number_of_entries', array( __CLASS__, 'filter_entries_per_page' ), 20 );
	}

	/**
	 * Add the entries-per-page setting to the existing tagDiv block.
	 *
	 * @return void
	 */
	public static function register_control() {
		if ( ! class_exists( 'td_api_block' ) || ! is_callable( array( 'td_api_block', 'get_all' ) ) ) {
			return;
		}

		$blocks = td_api_block::get_all();
		if ( ! isset( $blocks['td_block_liveblog']['params'] ) || ! is_array( $blocks['td_block_liveblog']['params'] ) ) {
			return;
		}

		$params = $blocks['td_block_liveblog']['params'];

		foreach ( $params as $param ) {
			if ( is_array( $param ) && isset( $param['param_name'] ) && 'entries_per_page' === $param['param_name'] ) {
				return;
			}
		}

		$params[] = array(
			'param_name'  => 'entries_per_page',
			'type'        => 'textfield',
			'value'       => (string) self::DEFAULT_ENTRIES_PER_PAGE,
			'std'         => (string) self::DEFAULT_ENTRIES_PER_PAGE,
			'heading'     => __( 'Entries per page', 'tagdiv-liveblog' ),
			'description' => __( 'Number of Liveblog entries shown on each native pagination page. Allowed range: 1–100.', 'tagdiv-liveblog' ),
			'holder'      => 'div',
			'class'       => 'tdc-textfield-small',
			'group'       => __( 'Pagination', 'tagdiv-liveblog' ),
		);

		if ( is_callable( array( 'td_api_block', 'update_key' ) ) ) {
			td_api_block::update_key( 'td_block_liveblog', 'params', $params );
		}
	}

	/**
	 * Preserve the adapter attribute even though the base block predates it.
	 *
	 * @param array  $out       Sanitized shortcode attributes.
	 * @param array  $pairs     Registered defaults in the block class.
	 * @param array  $atts      Raw shortcode attributes.
	 * @param string $shortcode Shortcode name.
	 * @return array
	 */
	public static function filter_shortcode_atts( $out, $pairs, $atts, $shortcode ) {
		unset( $pairs, $shortcode );

		$atts = is_array( $atts ) ? $atts : array();

		$out['entries_per_page'] = self::sanitize_entries_per_page(
			isset( $atts['entries_per_page'] ) ? $atts['entries_per_page'] : self::DEFAULT_ENTRIES_PER_PAGE
		);

		return $out;
	}

	/**
	 * Expose the per-instance page size to the pre-runtime relocation helper.
	 *
	 * @param string $output Shortcode output.
	 * @param string $tag    Shortcode tag.
	 * @param array  $attr   Raw shortcode attributes.
	 * @param array  $m      Regex match data.
	 * @return string
	 */
	public static function decorate_block_output( $output, $tag, $attr, $m ) {
		unset( $m );

		if ( 'td_block_liveblog' !== $tag || ! is_string( $output ) || false === strpos( $output, 'data-tagdiv-liveblog-slot="1"' ) ) {
			return $output;
		}

		$attr     = is_array( $attr ) ? $attr : array();
		$per_page = self::sanitize_entries_per_page(
			isset( $attr['entries_per_page'] ) ? $attr['entries_per_page'] : self::DEFAULT_ENTRIES_PER_PAGE
		);

		$replacement = 'data-tagdiv-liveblog-slot="1" data-liveblog-entries-per-page="' . esc_attr( (string) $per_page ) . '"';

		return preg_replace(
			'/data-tagdiv-liveblog-slot="1"/',
			$replacement,
			$output,
			1
		);
	}

	/**
	 * Apply the block page size to Automattic Liveblog's native paged request.
	 *
	 * The relocation helper adds tdlb_per_page only to the upstream get-entries
	 * request for the current Liveblog. All pagination UI and state remain native.
	 *
	 * @param int $number Upstream number of entries.
	 * @return int
	 */
	public static function filter_entries_per_page( $number ) {
		if ( ! isset( $_GET['tdlb_per_page'] ) ) { // phpcs:ignore WordPress.Security.NonceVerification.Recommended -- Public read pagination parameter.
			return $number;
		}

		$value = wp_unslash( $_GET['tdlb_per_page'] ); // phpcs:ignore WordPress.Security.NonceVerification.Recommended -- Public read pagination parameter.

		return self::sanitize_entries_per_page( $value );
	}

	/**
	 * Clamp page size to the range accepted by Liveblog.
	 *
	 * @param mixed $value Raw value.
	 * @return int
	 */
	private static function sanitize_entries_per_page( $value ) {
		$value = absint( $value );
		if ( $value < 1 ) {
			$value = self::DEFAULT_ENTRIES_PER_PAGE;
		}

		return min( $value, self::MAX_ENTRIES_PER_PAGE );
	}
}
