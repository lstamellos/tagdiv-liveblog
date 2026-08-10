<?php
/**
 * Core integration bootstrap.
 *
 * @package Tagdiv_Liveblog
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

final class Tagdiv_Liveblog_Plugin {
	/**
	 * Whether the tagDiv block has already been registered in this request.
	 *
	 * @var bool
	 */
	private static $block_registered = false;

	/**
	 * Register WordPress hooks.
	 *
	 * @return void
	 */
	public static function init() {
		add_action( 'tdc_init', array( __CLASS__, 'on_tdc_init' ), 11 );

		// Enqueue before Liveblog's default priority (10). Both scripts are footer
		// scripts, so the relocation helper is printed before Liveblog's app.
		add_action( 'wp_enqueue_scripts', array( __CLASS__, 'enqueue_assets' ), 5 );
	}

	/**
	 * Register after tagDiv Composer has loaded its API.
	 *
	 * @return void
	 */
	public static function on_tdc_init() {
		// The normal Newspaper lifecycle reaches tdc_loaded after tdc_init. The
		// fallback also makes the integration safe if another bootstrap path has
		// already fired tdc_loaded before this callback executes.
		if ( did_action( 'tdc_loaded' ) ) {
			self::register_block();
			return;
		}

		add_action( 'tdc_loaded', array( __CLASS__, 'register_block' ) );
	}

	/**
	 * Register the native tagDiv Composer block.
	 *
	 * @return void
	 */
	public static function register_block() {
		if (
			self::$block_registered
			|| ! class_exists( 'td_api_block' )
			|| ! class_exists( 'td_block' )
			|| ! class_exists( 'td_config' )
		) {
			return;
		}

		self::$block_registered = true;

		td_api_block::add(
			'td_block_liveblog',
			array(
				'map_in_visual_composer' => false,
				'map_in_td_composer'     => true,
				'name'                   => __( 'Liveblog', 'tagdiv-liveblog' ),
				'base'                   => 'td_block_liveblog',
				'class'                  => 'td_block_liveblog',
				'controls'               => 'full',
				'category'               => 'Blocks',
				'tdc_category'           => 'Blocks',
				'icon'                   => '',
				'file'                   => TAGDIV_LIVEBLOG_PATH . 'shortcodes/td_block_liveblog.php',
				'params'                 => self::get_block_params(),
			)
		);
	}

	/**
	 * Composer control map.
	 *
	 * The installed Newspaper/td-composer runtime exposes
	 * td_config::get_map_block_general_array(). tagDiv's documented plugin
	 * pattern is to merge these native parameters into custom block controls,
	 * so Design Options and other standard block properties stay owned by
	 * tagDiv rather than being reimplemented here.
	 *
	 * @return array<int,array<string,mixed>>
	 */
	private static function get_block_params() {
		$custom = self::get_custom_block_params();

		if ( is_callable( array( 'td_config', 'get_map_block_general_array' ) ) ) {
			$general = td_config::get_map_block_general_array();
			if ( is_array( $general ) ) {
				return array_merge( $general, $custom );
			}
		}

		// Defensive fallback for an unexpected tagDiv build. This keeps Design
		// Options available without assuming anything else about its internals.
		$custom[] = array(
			'param_name' => 'tdc_css',
			'value'      => '',
			'type'       => 'tdc_css_editor',
			'heading'    => '',
			'group'      => 'Design options',
		);

		return $custom;
	}

	/**
	 * Presentation-only controls owned by this integration.
	 *
	 * Liveblog ordering, polling, pagination and administration remain upstream.
	 *
	 * @return array<int,array<string,mixed>>
	 */
	private static function get_custom_block_params() {
		return array(
			array(
				'param_name'  => 'title',
				'type'        => 'textfield',
				'value'       => '',
				'heading'     => __( 'Liveblog title', 'tagdiv-liveblog' ),
				'description' => __( 'Optional heading displayed above the live updates.', 'tagdiv-liveblog' ),
				'holder'      => 'div',
				'class'       => 'tdc-textfield-extrabig',
			),
			array(
				'param_name' => 'show_author',
				'type'       => 'dropdown',
				'value'      => array(
					__( 'Show', 'tagdiv-liveblog' ) => 'yes',
					__( 'Hide', 'tagdiv-liveblog' ) => 'no',
				),
				'heading'    => __( 'Author name', 'tagdiv-liveblog' ),
				'group'      => __( 'Meta', 'tagdiv-liveblog' ),
			),
			array(
				'param_name' => 'show_avatar',
				'type'       => 'dropdown',
				'value'      => array(
					__( 'Show', 'tagdiv-liveblog' ) => 'yes',
					__( 'Hide', 'tagdiv-liveblog' ) => 'no',
				),
				'heading'    => __( 'Avatar', 'tagdiv-liveblog' ),
				'group'      => __( 'Meta', 'tagdiv-liveblog' ),
			),
			array(
				'param_name' => 'show_timestamp',
				'type'       => 'dropdown',
				'value'      => array(
					__( 'Show', 'tagdiv-liveblog' ) => 'yes',
					__( 'Hide', 'tagdiv-liveblog' ) => 'no',
				),
				'heading'    => __( 'Timestamp', 'tagdiv-liveblog' ),
				'group'      => __( 'Meta', 'tagdiv-liveblog' ),
			),
			array(
				'param_name' => 'meta_alignment',
				'type'       => 'dropdown',
				'value'      => array(
					__( 'Left', 'tagdiv-liveblog' )   => 'left',
					__( 'Center', 'tagdiv-liveblog' ) => 'center',
					__( 'Right', 'tagdiv-liveblog' )  => 'right',
				),
				'heading'    => __( 'Meta alignment', 'tagdiv-liveblog' ),
				'group'      => __( 'Meta', 'tagdiv-liveblog' ),
			),
			self::color_param( 'entry_background', __( 'Entry background', 'tagdiv-liveblog' ), 'Entry' ),
			self::color_param( 'entry_text_color', __( 'Entry text color', 'tagdiv-liveblog' ), 'Entry' ),
			self::color_param( 'entry_border_color', __( 'Entry border color', 'tagdiv-liveblog' ), 'Entry' ),
			self::number_param( 'entry_border_width', __( 'Entry border width', 'tagdiv-liveblog' ), 'Entry', 0 ),
			self::number_param( 'entry_radius', __( 'Entry border radius', 'tagdiv-liveblog' ), 'Entry', 0 ),
			self::number_param( 'entry_padding', __( 'Entry padding', 'tagdiv-liveblog' ), 'Entry', 16 ),
			self::number_param( 'entry_gap', __( 'Space between entries', 'tagdiv-liveblog' ), 'Entry', 20 ),
			self::color_param( 'meta_background', __( 'Meta background', 'tagdiv-liveblog' ), 'Meta' ),
			self::color_param( 'meta_text_color', __( 'Meta text color', 'tagdiv-liveblog' ), 'Meta' ),
			self::number_param( 'meta_padding', __( 'Meta padding', 'tagdiv-liveblog' ), 'Meta', 0 ),
			self::number_param( 'meta_gap', __( 'Meta item gap', 'tagdiv-liveblog' ), 'Meta', 8 ),
			self::color_param( 'content_background', __( 'Content background', 'tagdiv-liveblog' ), 'Content' ),
			self::color_param( 'content_text_color', __( 'Content text color', 'tagdiv-liveblog' ), 'Content' ),
			self::number_param( 'content_padding', __( 'Content padding', 'tagdiv-liveblog' ), 'Content', 0 ),
			array(
				'param_name' => 'timeline',
				'type'       => 'dropdown',
				'value'      => array(
					__( 'Off', 'tagdiv-liveblog' ) => 'no',
					__( 'On', 'tagdiv-liveblog' )  => 'yes',
				),
				'heading'    => __( 'Timeline line', 'tagdiv-liveblog' ),
				'group'      => __( 'Timeline', 'tagdiv-liveblog' ),
			),
			self::color_param( 'timeline_color', __( 'Timeline color', 'tagdiv-liveblog' ), 'Timeline' ),
			self::number_param( 'timeline_width', __( 'Timeline width', 'tagdiv-liveblog' ), 'Timeline', 2 ),
			self::number_param( 'timeline_offset', __( 'Timeline left offset', 'tagdiv-liveblog' ), 'Timeline', 10 ),
		);
	}

	/**
	 * Build a colorpicker parameter.
	 *
	 * @param string $name  Parameter name.
	 * @param string $label Label.
	 * @param string $group Group.
	 * @return array<string,mixed>
	 */
	private static function color_param( $name, $label, $group ) {
		return array(
			'param_name' => $name,
			'type'       => 'colorpicker',
			'value'      => '',
			'heading'    => $label,
			'group'      => $group,
		);
	}

	/**
	 * Build a numeric text field parameter, interpreted as pixels.
	 *
	 * @param string $name    Parameter name.
	 * @param string $label   Label.
	 * @param string $group   Group.
	 * @param int    $default Default value.
	 * @return array<string,mixed>
	 */
	private static function number_param( $name, $label, $group, $default ) {
		return array(
			'param_name'  => $name,
			'type'        => 'textfield',
			'value'       => (string) $default,
			'heading'     => $label,
			'group'       => $group,
			'holder'      => 'div',
			'class'       => 'tdc-textfield-small',
			'description' => __( 'Pixels.', 'tagdiv-liveblog' ),
		);
	}

	/**
	 * Load presentation CSS and, on actual liveblog posts, the root relocation script.
	 *
	 * @return void
	 */
	public static function enqueue_assets() {
		if ( is_admin() ) {
			return;
		}

		if ( ! is_singular() && ! self::is_composer_request() ) {
			return;
		}

		wp_enqueue_style(
			'tagdiv-liveblog',
			TAGDIV_LIVEBLOG_URL . 'assets/css/tagdiv-liveblog.css',
			array(),
			TAGDIV_LIVEBLOG_VERSION
		);

		if ( self::is_liveblog_post() ) {
			wp_enqueue_script(
				'tagdiv-liveblog-relocate',
				TAGDIV_LIVEBLOG_URL . 'assets/js/tagdiv-liveblog-relocate.js',
				array(),
				TAGDIV_LIVEBLOG_VERSION,
				true
			);
		}
	}

	/**
	 * Whether this request is the tagDiv live editor or its AJAX renderer.
	 *
	 * @return bool
	 */
	public static function is_composer_request() {
		return class_exists( 'td_util' )
			&& ( td_util::tdc_is_live_editor_iframe() || td_util::tdc_is_live_editor_ajax() );
	}

	/**
	 * Whether the current singular post is a Liveblog post.
	 *
	 * @return bool
	 */
	public static function is_liveblog_post() {
		if ( ! class_exists( 'WPCOM_Liveblog' ) || ! is_singular() ) {
			return false;
		}

		$post_id = get_queried_object_id();
		return $post_id > 0 && WPCOM_Liveblog::is_liveblog_post( $post_id );
	}
}
