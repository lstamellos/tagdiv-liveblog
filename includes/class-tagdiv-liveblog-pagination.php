<?php
/**
 * Per-block pagination controls and runtime adapter.
 *
 * @package Tagdiv_Liveblog
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

final class Tagdiv_Liveblog_Pagination {
	const DEFAULT_ENTRIES_PER_PAGE = 20;
	const MAX_ENTRIES_PER_PAGE     = 100;
	const DEFAULT_MODE             = 'infinite';

	/**
	 * Register hooks.
	 *
	 * @return void
	 */
	public static function init() {
		add_action( 'tdc_loaded', array( __CLASS__, 'register_controls' ), 20 );
		add_action( 'wp_enqueue_scripts', array( __CLASS__, 'enqueue_assets' ), 20 );

		add_filter( 'shortcode_atts_td_block_liveblog', array( __CLASS__, 'filter_shortcode_atts' ), 10, 4 );
		add_filter( 'do_shortcode_tag', array( __CLASS__, 'decorate_block_output' ), 10, 4 );
		add_filter( 'liveblog_number_of_entries', array( __CLASS__, 'filter_entries_per_page' ), 20 );
	}

	/**
	 * Add pagination settings to the existing tagDiv block without replacing it.
	 *
	 * @return void
	 */
	public static function register_controls() {
		if ( ! class_exists( 'td_api_block' ) || ! is_callable( array( 'td_api_block', 'get_all' ) ) ) {
			return;
		}

		$blocks = td_api_block::get_all();
		if ( ! isset( $blocks['td_block_liveblog']['params'] ) || ! is_array( $blocks['td_block_liveblog']['params'] ) ) {
			return;
		}

		$params = $blocks['td_block_liveblog']['params'];
		$names  = array();

		foreach ( $params as $param ) {
			if ( is_array( $param ) && isset( $param['param_name'] ) ) {
				$names[] = (string) $param['param_name'];
			}
		}

		if ( ! in_array( 'entries_per_page', $names, true ) ) {
			$params[] = array(
				'param_name'  => 'entries_per_page',
				'type'        => 'textfield',
				'value'       => (string) self::DEFAULT_ENTRIES_PER_PAGE,
				'std'         => (string) self::DEFAULT_ENTRIES_PER_PAGE,
				'heading'     => __( 'Entries per page', 'tagdiv-liveblog' ),
				'description' => __( 'Number of Liveblog entries loaded in each batch. Allowed range: 1–100.', 'tagdiv-liveblog' ),
				'holder'      => 'div',
				'class'       => 'tdc-textfield-small',
				'group'       => __( 'Pagination', 'tagdiv-liveblog' ),
			);
		}

		if ( ! in_array( 'pagination_mode', $names, true ) ) {
			$params[] = array(
				'param_name' => 'pagination_mode',
				'type'       => 'dropdown',
				'value'      => array(
					__( 'Auto load on scroll', 'tagdiv-liveblog' ) => 'infinite',
					__( 'Load more button', 'tagdiv-liveblog' )    => 'load_more',
					__( 'Native Prev / Next', 'tagdiv-liveblog' )  => 'native',
				),
				'std'        => self::DEFAULT_MODE,
				'heading'    => __( 'Pagination mode', 'tagdiv-liveblog' ),
				'group'      => __( 'Pagination', 'tagdiv-liveblog' ),
			);
		}

		if ( is_callable( array( 'td_api_block', 'update_key' ) ) ) {
			td_api_block::update_key( 'td_block_liveblog', 'params', $params );
		}
	}

	/**
	 * Preserve the two adapter attributes even though the base block predates them.
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
		$out['pagination_mode'] = self::sanitize_mode(
			isset( $atts['pagination_mode'] ) ? $atts['pagination_mode'] : self::DEFAULT_MODE
		);

		return $out;
	}

	/**
	 * Expose the per-instance settings to the pre-runtime relocation script.
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
		$mode     = self::sanitize_mode(
			isset( $attr['pagination_mode'] ) ? $attr['pagination_mode'] : self::DEFAULT_MODE
		);

		$replacement = 'data-tagdiv-liveblog-slot="1" data-liveblog-entries-per-page="' . esc_attr( (string) $per_page ) . '" data-liveblog-pagination-mode="' . esc_attr( $mode ) . '"';

		return preg_replace(
			'/data-tagdiv-liveblog-slot="1"/',
			$replacement,
			$output,
			1
		);
	}

	/**
	 * Apply the same batch size to the upstream REST request.
	 *
	 * The frontend adapter adds tdlb_per_page only to Liveblog paged-entry XHRs,
	 * so normal Liveblog requests and other blocks retain their own defaults.
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
	 * Enqueue the pagination behaviour after Automattic Liveblog's app.
	 *
	 * @return void
	 */
	public static function enqueue_assets() {
		if ( is_admin() || ! class_exists( 'Tagdiv_Liveblog_Plugin' ) || ! Tagdiv_Liveblog_Plugin::is_liveblog_post() ) {
			return;
		}

		wp_enqueue_style(
			'tagdiv-liveblog-pagination',
			TAGDIV_LIVEBLOG_URL . 'assets/css/tagdiv-liveblog-pagination.css',
			array( 'tagdiv-liveblog' ),
			TAGDIV_LIVEBLOG_VERSION
		);

		wp_enqueue_script(
			'tagdiv-liveblog-pagination',
			TAGDIV_LIVEBLOG_URL . 'assets/js/tagdiv-liveblog-pagination.js',
			array( 'liveblog', 'tagdiv-liveblog-relocate' ),
			TAGDIV_LIVEBLOG_VERSION,
			true
		);

		wp_localize_script(
			'tagdiv-liveblog-pagination',
			'tagdivLiveblogPagination',
			array(
				'loadMore' => __( 'Load more', 'tagdiv-liveblog' ),
				'loading'  => __( 'Loading…', 'tagdiv-liveblog' ),
				'retry'    => __( 'Retry loading', 'tagdiv-liveblog' ),
			)
		);
	}

	/**
	 * Clamp batch size to the range accepted by Liveblog.
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

	/**
	 * Validate pagination mode.
	 *
	 * @param mixed $value Raw value.
	 * @return string
	 */
	private static function sanitize_mode( $value ) {
		$value = sanitize_key( (string) $value );

		return in_array( $value, array( 'native', 'load_more', 'infinite' ), true ) ? $value : self::DEFAULT_MODE;
	}
}
