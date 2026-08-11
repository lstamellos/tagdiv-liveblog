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
	 * @return array<int,array<string,mixed>>
	 */
	private static function get_block_params() {
		$params = array();

		if ( is_callable( array( 'td_config', 'get_map_block_general_array' ) ) ) {
			$general = td_config::get_map_block_general_array();
			if ( is_array( $general ) ) {
				$params = $general;
			}
		}

		$params = array_merge( $params, self::get_custom_block_params() );

		$params[] = array(
			'param_name' => 'css',
			'value'      => '',
			'type'       => 'css_editor',
			'heading'    => 'Css',
			'group'      => 'Design options',
		);

		$params[] = array(
			'param_name' => 'tdc_css',
			'value'      => '',
			'type'       => 'tdc_css_editor',
			'heading'    => '',
			'group'      => 'Design options',
		);

		return $params;
	}

	/**
	 * Presentation controls owned by this integration.
	 *
	 * @return array<int,array<string,mixed>>
	 */
	private static function get_custom_block_params() {
		return array(
			array(
				'param_name'  => 'entries_per_page',
				'type'        => 'textfield',
				'value'       => '20',
				'std'         => '20',
				'heading'     => __( 'Liveblog entries per page', 'tagdiv-liveblog' ),
				'description' => __( 'Number of Liveblog entries shown on each native Liveblog pagination page. Allowed range: 1–100.', 'tagdiv-liveblog' ),
				'holder'      => 'div',
				'class'       => 'tdc-textfield-small',
				'group'       => __( 'General', 'tagdiv-liveblog' ),
			),

			// Archived-state notice.
			array(
				'param_name'  => 'archive_notice_text',
				'type'        => 'textfield',
				'value'       => 'Η ανταπόκριση έχει ολοκληρωθεί',
				'std'         => 'Η ανταπόκριση έχει ολοκληρωθεί',
				'heading'     => __( 'Archived notice text', 'tagdiv-liveblog' ),
				'description' => __( 'Plain-text message shown above an archived Liveblog on every native Liveblog pagination page.', 'tagdiv-liveblog' ),
				'holder'      => 'div',
				'group'       => __( 'Archived notice', 'tagdiv-liveblog' ),
			),
			self::color_param( 'archive_notice_background', __( 'Notice background', 'tagdiv-liveblog' ), 'Archived notice' ),
			self::color_param( 'archive_notice_text_color', __( 'Notice text color', 'tagdiv-liveblog' ), 'Archived notice' ),
			self::color_param( 'archive_notice_border_color', __( 'Notice border color', 'tagdiv-liveblog' ), 'Archived notice' ),
			self::number_param( 'archive_notice_border_width', __( 'Notice border width', 'tagdiv-liveblog' ), 'Archived notice', 1 ),
			self::choice_param(
				'archive_notice_border_style',
				__( 'Notice border style', 'tagdiv-liveblog' ),
				'Archived notice',
				array(
					__( 'Solid', 'tagdiv-liveblog' )  => 'solid',
					__( 'Dashed', 'tagdiv-liveblog' ) => 'dashed',
					__( 'Dotted', 'tagdiv-liveblog' ) => 'dotted',
					__( 'Double', 'tagdiv-liveblog' ) => 'double',
					__( 'None', 'tagdiv-liveblog' )   => 'none',
				),
				'solid'
			),
			self::number_param( 'archive_notice_radius', __( 'Notice border radius', 'tagdiv-liveblog' ), 'Archived notice', 0 ),
			self::number_param( 'archive_notice_padding', __( 'Notice padding', 'tagdiv-liveblog' ), 'Archived notice', 12 ),
			self::number_param( 'archive_notice_margin_bottom', __( 'Space below notice', 'tagdiv-liveblog' ), 'Archived notice', 20 ),
			self::number_param( 'archive_notice_font_size', __( 'Notice font size', 'tagdiv-liveblog' ), 'Archived notice', 16 ),
			self::number_param( 'archive_notice_line_height', __( 'Notice line height', 'tagdiv-liveblog' ), 'Archived notice', 24 ),
			self::choice_param(
				'archive_notice_font_weight',
				__( 'Notice font weight', 'tagdiv-liveblog' ),
				'Archived notice',
				array(
					__( 'Normal (400)', 'tagdiv-liveblog' )     => '400',
					__( 'Medium (500)', 'tagdiv-liveblog' )     => '500',
					__( 'Semi-bold (600)', 'tagdiv-liveblog' )  => '600',
					__( 'Bold (700)', 'tagdiv-liveblog' )       => '700',
					__( 'Extra-bold (800)', 'tagdiv-liveblog' ) => '800',
				),
				'600'
			),
			self::number_param( 'archive_notice_letter_spacing', __( 'Notice letter spacing', 'tagdiv-liveblog' ), 'Archived notice', 0 ),
			self::choice_param(
				'archive_notice_font_style',
				__( 'Notice font style', 'tagdiv-liveblog' ),
				'Archived notice',
				array(
					__( 'Normal', 'tagdiv-liveblog' ) => 'normal',
					__( 'Italic', 'tagdiv-liveblog' ) => 'italic',
				),
				'normal'
			),
			self::alignment_param( 'archive_notice_text_align', __( 'Notice text alignment', 'tagdiv-liveblog' ), 'Archived notice', 'left' ),
			self::choice_param(
				'archive_notice_text_transform',
				__( 'Notice text transform', 'tagdiv-liveblog' ),
				'Archived notice',
				array(
					__( 'None', 'tagdiv-liveblog' )       => 'none',
					__( 'Uppercase', 'tagdiv-liveblog' )  => 'uppercase',
					__( 'Lowercase', 'tagdiv-liveblog' )  => 'lowercase',
					__( 'Capitalize', 'tagdiv-liveblog' ) => 'capitalize',
				),
				'none'
			),

			// Key Events: native upstream summary plus optional presentation of authoritative key entries.
			self::choice_param(
				'key_events_summary',
				__( 'Key Events summary', 'tagdiv-liveblog' ),
				'Key Events',
				array(
					__( 'Off', 'tagdiv-liveblog' ) => 'no',
					__( 'On', 'tagdiv-liveblog' )  => 'yes',
				),
				'no'
			),
			array(
				'param_name'  => 'key_events_title',
				'type'        => 'textfield',
				'value'       => 'Κύρια σημεία',
				'std'         => 'Κύρια σημεία',
				'heading'     => __( 'Summary title', 'tagdiv-liveblog' ),
				'description' => __( 'Title above the native Automattic Liveblog Key Events list. Key-event content format remains the upstream per-post Liveblog setting.', 'tagdiv-liveblog' ),
				'holder'      => 'div',
				'group'       => __( 'Key Events', 'tagdiv-liveblog' ),
			),
			self::color_param( 'key_summary_background', __( 'Summary background', 'tagdiv-liveblog' ), 'Key Events' ),
			self::color_param( 'key_summary_text_color', __( 'Summary text color', 'tagdiv-liveblog' ), 'Key Events' ),
			self::color_param( 'key_summary_border_color', __( 'Summary border color', 'tagdiv-liveblog' ), 'Key Events' ),
			self::number_param( 'key_summary_border_width', __( 'Summary border width', 'tagdiv-liveblog' ), 'Key Events', 1 ),
			self::choice_param(
				'key_summary_border_style',
				__( 'Summary border style', 'tagdiv-liveblog' ),
				'Key Events',
				array(
					__( 'Solid', 'tagdiv-liveblog' )  => 'solid',
					__( 'Dashed', 'tagdiv-liveblog' ) => 'dashed',
					__( 'Dotted', 'tagdiv-liveblog' ) => 'dotted',
					__( 'Double', 'tagdiv-liveblog' ) => 'double',
					__( 'None', 'tagdiv-liveblog' )   => 'none',
				),
				'solid'
			),
			self::number_param( 'key_summary_radius', __( 'Summary border radius', 'tagdiv-liveblog' ), 'Key Events', 0 ),
			self::number_param( 'key_summary_padding', __( 'Summary padding', 'tagdiv-liveblog' ), 'Key Events', 16 ),
			self::number_param( 'key_summary_margin_bottom', __( 'Space below summary', 'tagdiv-liveblog' ), 'Key Events', 24 ),
			self::number_param( 'key_summary_title_size', __( 'Summary title size', 'tagdiv-liveblog' ), 'Key Events', 20 ),
			self::choice_param(
				'key_summary_title_weight',
				__( 'Summary title weight', 'tagdiv-liveblog' ),
				'Key Events',
				array(
					__( 'Normal (400)', 'tagdiv-liveblog' )     => '400',
					__( 'Medium (500)', 'tagdiv-liveblog' )     => '500',
					__( 'Semi-bold (600)', 'tagdiv-liveblog' )  => '600',
					__( 'Bold (700)', 'tagdiv-liveblog' )       => '700',
					__( 'Extra-bold (800)', 'tagdiv-liveblog' ) => '800',
				),
				'700'
			),
			self::number_param( 'key_summary_item_gap', __( 'Summary item gap', 'tagdiv-liveblog' ), 'Key Events', 10 ),
			self::color_param( 'key_summary_item_border_color', __( 'Summary item border color', 'tagdiv-liveblog' ), 'Key Events' ),
			self::number_param( 'key_summary_item_border_width', __( 'Summary item border width', 'tagdiv-liveblog' ), 'Key Events', 0 ),
			self::choice_param(
				'key_highlight',
				__( 'Highlight key entries in feed', 'tagdiv-liveblog' ),
				'Key Events',
				array(
					__( 'Off', 'tagdiv-liveblog' ) => 'no',
					__( 'On', 'tagdiv-liveblog' )  => 'yes',
				),
				'no'
			),
			self::color_param( 'key_entry_background', __( 'Key entry background', 'tagdiv-liveblog' ), 'Key Events' ),
			self::color_param( 'key_entry_text_color', __( 'Key entry text color', 'tagdiv-liveblog' ), 'Key Events' ),
			self::color_param( 'key_entry_border_color', __( 'Key entry border color', 'tagdiv-liveblog' ), 'Key Events' ),
			self::number_param( 'key_entry_border_width', __( 'Key entry border width', 'tagdiv-liveblog' ), 'Key Events', 0 ),
			self::choice_param(
				'key_entry_border_style',
				__( 'Key entry border style', 'tagdiv-liveblog' ),
				'Key Events',
				array(
					__( 'Solid', 'tagdiv-liveblog' )  => 'solid',
					__( 'Dashed', 'tagdiv-liveblog' ) => 'dashed',
					__( 'Dotted', 'tagdiv-liveblog' ) => 'dotted',
					__( 'Double', 'tagdiv-liveblog' ) => 'double',
					__( 'None', 'tagdiv-liveblog' )   => 'none',
				),
				'solid'
			),
			self::number_param( 'key_entry_radius', __( 'Key entry border radius', 'tagdiv-liveblog' ), 'Key Events', 0 ),
			self::color_param( 'key_entry_accent_color', __( 'Key entry accent color', 'tagdiv-liveblog' ), 'Key Events' ),
			self::number_param( 'key_entry_accent_width', __( 'Key entry accent width', 'tagdiv-liveblog' ), 'Key Events', 3 ),
			self::choice_param(
				'key_label',
				__( 'Key entry label', 'tagdiv-liveblog' ),
				'Key Events',
				array(
					__( 'Hide', 'tagdiv-liveblog' ) => 'no',
					__( 'Show', 'tagdiv-liveblog' ) => 'yes',
				),
				'no'
			),
			array(
				'param_name'  => 'key_label_text',
				'type'        => 'textfield',
				'value'       => 'Κύριο σημείο',
				'std'         => 'Κύριο σημείο',
				'heading'     => __( 'Key entry label text', 'tagdiv-liveblog' ),
				'holder'      => 'div',
				'group'       => __( 'Key Events', 'tagdiv-liveblog' ),
			),
			self::color_param( 'key_label_background', __( 'Label background', 'tagdiv-liveblog' ), 'Key Events' ),
			self::color_param( 'key_label_text_color', __( 'Label text color', 'tagdiv-liveblog' ), 'Key Events' ),
			self::number_param( 'key_label_font_size', __( 'Label font size', 'tagdiv-liveblog' ), 'Key Events', 12 ),
			self::choice_param(
				'key_label_font_weight',
				__( 'Label font weight', 'tagdiv-liveblog' ),
				'Key Events',
				array(
					__( 'Normal (400)', 'tagdiv-liveblog' )     => '400',
					__( 'Medium (500)', 'tagdiv-liveblog' )     => '500',
					__( 'Semi-bold (600)', 'tagdiv-liveblog' )  => '600',
					__( 'Bold (700)', 'tagdiv-liveblog' )       => '700',
					__( 'Extra-bold (800)', 'tagdiv-liveblog' ) => '800',
				),
				'700'
			),
			self::number_param( 'key_label_letter_spacing', __( 'Label letter spacing', 'tagdiv-liveblog' ), 'Key Events', 0 ),
			self::number_param( 'key_label_margin_bottom', __( 'Space below label', 'tagdiv-liveblog' ), 'Key Events', 8 ),

			// Metadata.
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
				'param_name' => 'time_display',
				'type'       => 'dropdown',
				'value'      => array(
					__( 'Exact time', 'tagdiv-liveblog' )    => 'exact',
					__( 'Relative time', 'tagdiv-liveblog' ) => 'relative',
					__( 'Both', 'tagdiv-liveblog' )          => 'both',
				),
				'heading'    => __( 'Time display', 'tagdiv-liveblog' ),
				'group'      => __( 'Meta', 'tagdiv-liveblog' ),
			),
			array(
				'param_name' => 'meta_layout',
				'type'       => 'dropdown',
				'value'      => array(
					__( 'Stacked', 'tagdiv-liveblog' ) => 'stacked',
					__( 'Inline', 'tagdiv-liveblog' )  => 'inline',
				),
				'heading'    => __( 'Meta layout', 'tagdiv-liveblog' ),
				'group'      => __( 'Meta', 'tagdiv-liveblog' ),
			),
			self::position_param( 'meta_position', __( 'Meta position', 'tagdiv-liveblog' ), 'Meta', 'top' ),
			array(
				'param_name' => 'meta_order',
				'type'       => 'dropdown',
				'value'      => array(
					__( 'Timestamp first', 'tagdiv-liveblog' ) => 'time_author',
					__( 'Author first', 'tagdiv-liveblog' )    => 'author_time',
				),
				'heading'    => __( 'Meta order', 'tagdiv-liveblog' ),
				'group'      => __( 'Meta', 'tagdiv-liveblog' ),
			),
			self::alignment_param( 'meta_alignment', __( 'Meta alignment', 'tagdiv-liveblog' ), 'Meta', 'left' ),
			array(
				'param_name'  => 'meta_separator',
				'type'        => 'textfield',
				'value'       => '',
				'heading'     => __( 'Inline separator', 'tagdiv-liveblog' ),
				'description' => __( 'Character or symbol shown between timestamp and author in Inline layout, e.g. ·, /, —.', 'tagdiv-liveblog' ),
				'holder'      => 'div',
				'class'       => 'tdc-textfield-small',
				'group'       => __( 'Meta', 'tagdiv-liveblog' ),
			),
			array(
				'param_name'  => 'timestamp_prefix',
				'type'        => 'textfield',
				'value'       => '',
				'heading'     => __( 'Timestamp prefix', 'tagdiv-liveblog' ),
				'description' => __( 'Optional character or symbol before the timestamp.', 'tagdiv-liveblog' ),
				'holder'      => 'div',
				'class'       => 'tdc-textfield-small',
				'group'       => __( 'Meta', 'tagdiv-liveblog' ),
			),
			array(
				'param_name'  => 'author_prefix',
				'type'        => 'textfield',
				'value'       => '',
				'heading'     => __( 'Author prefix', 'tagdiv-liveblog' ),
				'description' => __( 'Optional character or symbol before the author metadata.', 'tagdiv-liveblog' ),
				'holder'      => 'div',
				'class'       => 'tdc-textfield-small',
				'group'       => __( 'Meta', 'tagdiv-liveblog' ),
			),
			self::number_param( 'meta_stack_gap', __( 'Meta/content vertical gap', 'tagdiv-liveblog' ), 'Meta', 12 ),
			self::number_param( 'meta_inline_gap', __( 'Inline meta gap', 'tagdiv-liveblog' ), 'Meta', 8 ),
			self::color_param( 'meta_background', __( 'Meta background', 'tagdiv-liveblog' ), 'Meta' ),
			self::color_param( 'meta_text_color', __( 'Meta text color', 'tagdiv-liveblog' ), 'Meta' ),
			self::number_param( 'meta_padding', __( 'Meta padding', 'tagdiv-liveblog' ), 'Meta', 0 ),
			self::number_param( 'meta_gap', __( 'Avatar/name gap', 'tagdiv-liveblog' ), 'Meta', 8 ),

			// Entry surface.
			self::color_param( 'entry_background', __( 'Entry background', 'tagdiv-liveblog' ), 'Entry' ),
			self::color_param( 'entry_text_color', __( 'Entry text color', 'tagdiv-liveblog' ), 'Entry' ),
			self::color_param( 'entry_border_color', __( 'Entry border color', 'tagdiv-liveblog' ), 'Entry' ),
			self::number_param( 'entry_border_width', __( 'Entry border width', 'tagdiv-liveblog' ), 'Entry', 0 ),
			self::number_param( 'entry_radius', __( 'Entry border radius', 'tagdiv-liveblog' ), 'Entry', 0 ),
			self::number_param( 'entry_padding', __( 'Entry padding', 'tagdiv-liveblog' ), 'Entry', 16 ),
			self::number_param( 'entry_gap', __( 'Space between entries', 'tagdiv-liveblog' ), 'Entry', 20 ),

			// Entry content surface.
			self::color_param( 'content_background', __( 'Content background', 'tagdiv-liveblog' ), 'Content' ),
			self::color_param( 'content_text_color', __( 'Content text color', 'tagdiv-liveblog' ), 'Content' ),
			self::color_param( 'content_border_color', __( 'Content border color', 'tagdiv-liveblog' ), 'Content' ),
			self::number_param( 'content_border_width', __( 'Content border width', 'tagdiv-liveblog' ), 'Content', 0 ),
			self::choice_param(
				'content_border_style',
				__( 'Content border style', 'tagdiv-liveblog' ),
				'Content',
				array(
					__( 'Solid', 'tagdiv-liveblog' )  => 'solid',
					__( 'Dashed', 'tagdiv-liveblog' ) => 'dashed',
					__( 'Dotted', 'tagdiv-liveblog' ) => 'dotted',
					__( 'Double', 'tagdiv-liveblog' ) => 'double',
					__( 'None', 'tagdiv-liveblog' )   => 'none',
				),
				'solid'
			),
			self::number_param( 'content_radius', __( 'Content border radius', 'tagdiv-liveblog' ), 'Content', 0 ),
			self::number_param( 'content_padding', __( 'Content padding', 'tagdiv-liveblog' ), 'Content', 0 ),

			// Timeline stays behind complete entry boxes.
			self::choice_param(
				'timeline',
				__( 'Timeline line', 'tagdiv-liveblog' ),
				'Timeline',
				array(
					__( 'Off', 'tagdiv-liveblog' ) => 'no',
					__( 'On', 'tagdiv-liveblog' )  => 'yes',
				),
				'no'
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
	 * Build a dropdown parameter.
	 *
	 * @param string               $name    Parameter name.
	 * @param string               $label   Label.
	 * @param string               $group   Group.
	 * @param array<string,string> $values  Display labels mapped to values.
	 * @param string               $default Default value.
	 * @return array<string,mixed>
	 */
	private static function choice_param( $name, $label, $group, $values, $default ) {
		return array(
			'param_name' => $name,
			'type'       => 'dropdown',
			'value'      => $values,
			'std'        => $default,
			'heading'    => $label,
			'group'      => $group,
		);
	}

	/**
	 * Build top/bottom placement control.
	 *
	 * @param string $name    Parameter name.
	 * @param string $label   Label.
	 * @param string $group   Group.
	 * @param string $default Default value.
	 * @return array<string,mixed>
	 */
	private static function position_param( $name, $label, $group, $default ) {
		return array(
			'param_name' => $name,
			'type'       => 'dropdown',
			'value'      => array(
				__( 'Above content', 'tagdiv-liveblog' ) => 'top',
				__( 'Below content', 'tagdiv-liveblog' ) => 'bottom',
			),
			'std'        => $default,
			'heading'    => $label,
			'group'      => $group,
		);
	}

	/**
	 * Build horizontal alignment control.
	 *
	 * @param string $name    Parameter name.
	 * @param string $label   Label.
	 * @param string $group   Group.
	 * @param string $default Default value.
	 * @return array<string,mixed>
	 */
	private static function alignment_param( $name, $label, $group, $default ) {
		return array(
			'param_name' => $name,
			'type'       => 'dropdown',
			'value'      => array(
				__( 'Left', 'tagdiv-liveblog' )   => 'left',
				__( 'Center', 'tagdiv-liveblog' ) => 'center',
				__( 'Right', 'tagdiv-liveblog' )  => 'right',
			),
			'std'        => $default,
			'heading'    => $label,
			'group'      => $group,
		);
	}

	/**
	 * Load presentation CSS and, on actual liveblog posts, integration scripts.
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

			if ( self::current_user_can_manage_liveblog() ) {
				wp_enqueue_style(
					'tagdiv-liveblog-management',
					TAGDIV_LIVEBLOG_URL . 'assets/css/tagdiv-liveblog-management.css',
					array( 'tagdiv-liveblog' ),
					TAGDIV_LIVEBLOG_VERSION
				);

				wp_enqueue_script(
					'tagdiv-liveblog-management',
					TAGDIV_LIVEBLOG_URL . 'assets/js/tagdiv-liveblog-management.js',
					array(),
					TAGDIV_LIVEBLOG_VERSION,
					true
				);

				wp_localize_script(
					'tagdiv-liveblog-management',
					'tagdiv_liveblog_management',
					array(
						'ajax_url'        => admin_url( 'admin-ajax.php' ),
						'action'          => 'set_liveblog_state_for_post',
						'nonce_key'       => WPCOM_Liveblog::NONCE_KEY,
						'nonce'           => wp_create_nonce( WPCOM_Liveblog::NONCE_ACTION ),
						'post_id'         => self::get_liveblog_post_id(),
						'archive_confirm' => __( 'Archive this liveblog?', 'tagdiv-liveblog' ),
						'working'         => __( 'Updating…', 'tagdiv-liveblog' ),
						'error'           => __( 'The Liveblog state could not be updated. Please try again.', 'tagdiv-liveblog' ),
					)
				);
			}
		}
	}

	/**
	 * Whether this request is the tagDiv live editor or its AJAX renderer.
	 *
	 * @return bool
	 */
	public static function is_composer_request() {
		if ( ! class_exists( 'td_util' ) ) {
			return false;
		}

		$iframe = method_exists( 'td_util', 'tdc_is_live_editor_iframe' )
			&& td_util::tdc_is_live_editor_iframe();
		$ajax   = method_exists( 'td_util', 'tdc_is_live_editor_ajax' )
			&& td_util::tdc_is_live_editor_ajax();

		return $iframe || $ajax;
	}

	/**
	 * Get the actual Liveblog post ID for this request.
	 *
	 * @return int
	 */
	public static function get_liveblog_post_id() {
		if ( class_exists( 'WPCOM_Liveblog' ) ) {
			$post_id = absint( WPCOM_Liveblog::$post_id );
			if ( $post_id > 0 ) {
				return $post_id;
			}
		}

		if ( self::is_composer_request() ) {
			return 0;
		}

		return absint( get_queried_object_id() );
	}

	/**
	 * Get the normalized Liveblog state for the resolved article.
	 *
	 * @return string|false
	 */
	public static function get_liveblog_state() {
		if ( ! class_exists( 'WPCOM_Liveblog' ) ) {
			return false;
		}

		$post_id = self::get_liveblog_post_id();
		if ( $post_id <= 0 ) {
			return false;
		}

		$state = get_post_meta( $post_id, WPCOM_Liveblog::KEY, true );
		if ( 1 === $state || '1' === $state ) {
			$state = 'enable';
		}

		return is_string( $state ) ? $state : false;
	}

	/**
	 * Whether the resolved post has an active or archived Liveblog state.
	 *
	 * @return bool
	 */
	public static function is_liveblog_post() {
		return in_array( self::get_liveblog_state(), array( 'enable', 'archive' ), true );
	}

	/**
	 * Whether the resolved post is an archived Liveblog.
	 *
	 * @return bool
	 */
	public static function is_archived_liveblog_post() {
		return 'archive' === self::get_liveblog_state();
	}

	/**
	 * Whether the current user may change the current Liveblog state.
	 *
	 * Prefer the post-scoped capability helper provided by Automattic Liveblog
	 * 1.12.x. The fallback keeps this adapter usable with older compatible
	 * releases while still authorising against the specific post.
	 *
	 * @return bool
	 */
	public static function current_user_can_manage_liveblog() {
		if ( ! is_user_logged_in() || ! class_exists( 'WPCOM_Liveblog' ) ) {
			return false;
		}

		$post_id = self::get_liveblog_post_id();
		if ( $post_id <= 0 || ! self::is_liveblog_post() ) {
			return false;
		}

		if ( method_exists( 'WPCOM_Liveblog', 'current_user_can_edit_liveblog_for_post' ) ) {
			return (bool) WPCOM_Liveblog::current_user_can_edit_liveblog_for_post( $post_id );
		}

		return current_user_can( 'edit_post', $post_id );
	}
}
