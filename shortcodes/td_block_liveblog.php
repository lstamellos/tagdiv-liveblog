<?php
/**
 * tagDiv Composer Liveblog block.
 *
 * @package Tagdiv_Liveblog
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class td_block_liveblog extends td_block {
	/**
	 * Render the block.
	 *
	 * @param array       $atts    Shortcode attributes.
	 * @param string|null $content Shortcode content.
	 * @return string
	 */
	public function render( $atts, $content = null ) {
		parent::render( $atts );

		$raw_atts = is_array( $atts ) ? $atts : array();

		$this->shortcode_atts = shortcode_atts(
			array(
				'entries_per_page'             => '20',
				'archive_notice_text'           => 'Η ανταπόκριση έχει ολοκληρωθεί',
				'archive_notice_background'     => '',
				'archive_notice_text_color'     => '',
				'archive_notice_border_color'   => '',
				'archive_notice_border_width'   => '1',
				'archive_notice_border_style'   => 'solid',
				'archive_notice_radius'         => '0',
				'archive_notice_padding'        => '12',
				'archive_notice_margin_bottom'  => '20',
				'archive_notice_font_size'      => '16',
				'archive_notice_line_height'    => '24',
				'archive_notice_font_weight'    => '600',
				'archive_notice_letter_spacing' => '0',
				'archive_notice_font_style'     => 'normal',
				'archive_notice_text_align'     => 'left',
				'archive_notice_text_transform' => 'none',
				'key_events_summary'            => 'no',
				'key_events_title'              => 'Κύρια σημεία',
				'key_summary_background'        => '',
				'key_summary_text_color'        => '',
				'key_summary_border_color'      => '',
				'key_summary_border_width'      => '1',
				'key_summary_border_style'      => 'solid',
				'key_summary_radius'            => '0',
				'key_summary_padding'           => '16',
				'key_summary_margin_bottom'     => '24',
				'key_summary_title_size'        => '20',
				'key_summary_title_weight'      => '700',
				'key_summary_item_gap'          => '10',
				'key_summary_item_border_color' => '',
				'key_summary_item_border_width' => '0',
				'key_highlight'                 => 'no',
				'key_entry_background'          => '',
				'key_entry_text_color'          => '',
				'key_entry_border_color'        => '',
				'key_entry_border_width'        => '0',
				'key_entry_border_style'        => 'solid',
				'key_entry_radius'              => '0',
				'key_entry_accent_color'        => '',
				'key_entry_accent_width'        => '3',
				'key_label'                     => 'no',
				'key_label_text'                => 'Κύριο σημείο',
				'key_label_background'          => '',
				'key_label_text_color'          => '',
				'key_label_font_size'           => '12',
				'key_label_font_weight'         => '700',
				'key_label_letter_spacing'      => '0',
				'key_label_margin_bottom'       => '8',
				'show_author'                   => 'yes',
				'show_avatar'                   => 'yes',
				'show_timestamp'                => 'yes',
				'time_display'                  => 'exact',
				'meta_layout'                   => 'stacked',
				'meta_position'                 => 'top',
				'meta_order'                    => 'time_author',
				'meta_alignment'                => 'left',
				'meta_separator'                => '',
				'timestamp_prefix'              => '',
				'author_prefix'                 => '',
				'meta_stack_gap'                => '12',
				'meta_inline_gap'               => '8',
				'meta_background'               => '',
				'meta_text_color'               => '',
				'meta_padding'                  => '0',
				'meta_gap'                      => '8',
				'entry_background'              => '',
				'entry_text_color'              => '',
				'entry_border_color'            => '',
				'entry_border_width'            => '0',
				'entry_radius'                  => '0',
				'entry_padding'                 => '16',
				'entry_gap'                     => '20',
				'content_background'            => '',
				'content_text_color'            => '',
				'content_border_color'          => '',
				'content_border_width'          => '0',
				'content_border_style'          => 'solid',
				'content_radius'                => '0',
				'content_padding'               => '0',
				'timeline'                      => 'no',
				'timeline_color'                => '',
				'timeline_width'                => '2',
				'timeline_offset'               => '10',
			),
			$raw_atts,
			'td_block_liveblog'
		);

		$is_composer = Tagdiv_Liveblog_Plugin::is_composer_request();

		if ( ! $is_composer && ! Tagdiv_Liveblog_Plugin::is_liveblog_post() ) {
			return '';
		}

		$classes = preg_replace( '/\btd-pb-border-top\b/', '', $this->get_block_classes() );
		$classes = trim( preg_replace( '/\s+/', ' ', (string) $classes ) ) . ' tdlb-block';

		$style       = $this->build_css_variables();
		$block_title = $this->get_block_title();
		$post_id     = Tagdiv_Liveblog_Plugin::get_liveblog_post_id();

		$entries_per_page = absint( $this->get_shortcode_att( 'entries_per_page' ) );
		if ( $entries_per_page < 1 ) {
			$entries_per_page = 20;
		}
		$entries_per_page = min( $entries_per_page, 100 );
		$key_highlight     = 'yes' === $this->get_shortcode_att( 'key_highlight' ) ? '1' : '0';

		$buffy  = '<div class="' . esc_attr( $classes ) . '">';
		$buffy .= $this->get_block_css();

		if ( '' !== $block_title ) {
			$buffy .= '<div class="td-block-title-wrap">' . $block_title . '</div>';
		}

		$buffy .= '<div class="tdlb-liveblog" data-tagdiv-liveblog-slot="1" data-liveblog-post-id="' . esc_attr( (string) absint( $post_id ) ) . '" data-liveblog-entries-per-page="' . esc_attr( (string) $entries_per_page ) . '" data-key-highlight="' . esc_attr( $key_highlight ) . '"' . ( $style ? ' style="' . esc_attr( $style ) . '"' : '' ) . '>';

		if ( ! $is_composer && Tagdiv_Liveblog_Plugin::current_user_can_manage_liveblog() ) {
			$buffy .= $this->render_management_control( Tagdiv_Liveblog_Plugin::get_liveblog_state() );
		}

		if ( $is_composer || Tagdiv_Liveblog_Plugin::is_archived_liveblog_post() ) {
			$preview_attribute = $is_composer ? ' data-tdlb-archive-notice-preview="1"' : '';
			$buffy            .= '<div class="tdlb-archive-notice"' . $preview_attribute . '>' . esc_html( $this->get_archive_notice_text( $post_id ) ) . '</div>';
		}

		$buffy .= $this->render_key_events_summary( $is_composer );

		if ( $is_composer ) {
			$buffy .= $this->render_preview();
		}

		$buffy .= '</div>';
		$buffy .= '</div>';

		return $buffy;
	}

	/**
	 * Archived-state notice text.
	 *
	 * @param int $post_id Liveblog post ID.
	 * @return string
	 */
	private function get_archive_notice_text( $post_id ) {
		$message = wp_strip_all_tags( trim( (string) $this->get_shortcode_att( 'archive_notice_text' ) ) );
		if ( '' === $message ) {
			$message = __( 'Η ανταπόκριση έχει ολοκληρωθεί', 'tagdiv-liveblog' );
		}

		$message = apply_filters( 'tagdiv_liveblog_archive_notice_text', $message, absint( $post_id ) );

		return wp_strip_all_tags( (string) $message );
	}

	/**
	 * Render the native Automattic Liveblog key-events portal target.
	 *
	 * On the frontend the upstream React app detects #liveblog-key-events during
	 * construction, loads key events through its own API and portals its native
	 * EventsContainer into this mount. Composer uses a static representation so
	 * no second Liveblog runtime is started in the editor.
	 *
	 * @param bool $is_composer Whether this is a Composer request.
	 * @return string
	 */
	private function render_key_events_summary( $is_composer ) {
		if ( 'yes' !== $this->get_shortcode_att( 'key_events_summary' ) ) {
			return '';
		}

		$title = wp_strip_all_tags( trim( (string) $this->get_shortcode_att( 'key_events_title' ) ) );
		if ( '' === $title ) {
			$title = __( 'Κύρια σημεία', 'tagdiv-liveblog' );
		}

		if ( $is_composer ) {
			$out  = '<section class="tdlb-key-events tdlb-key-events-preview">';
			$out .= '<h2 class="widget-title">' . esc_html( $title ) . '</h2>';
			$out .= '<ul class="liveblog-events">';
			$out .= '<li class="liveblog-event"><div class="liveblog-event-body"><div class="liveblog-event-meta">' . esc_html__( 'Just now', 'tagdiv-liveblog' ) . '</div><div><span class="liveblog-event-content">' . esc_html__( 'A key event appears here and links to the corresponding entry in the live feed.', 'tagdiv-liveblog' ) . '</span></div></div></li>';
			$out .= '<li class="liveblog-event"><div class="liveblog-event-body"><div class="liveblog-event-meta">' . esc_html__( '7 minutes ago', 'tagdiv-liveblog' ) . '</div><div><span class="liveblog-event-content">' . esc_html__( 'Key-event content and navigation remain owned by Automattic Liveblog.', 'tagdiv-liveblog' ) . '</span></div></div></li>';
			$out .= '</ul>';
			$out .= '</section>';
			return $out;
		}

		static $mount_rendered = false;
		if ( $mount_rendered ) {
			return '';
		}
		$mount_rendered = true;

		return '<section class="tdlb-key-events"><div id="liveblog-key-events" data-title="' . esc_attr( $title ) . '"></div></section>';
	}

	/**
	 * Render the context-sensitive frontend Liveblog management status.
	 *
	 * @param string|false $state Current Liveblog state.
	 * @return string
	 */
	private function render_management_control( $state ) {
		if ( 'enable' === $state ) {
			$status       = __( 'This liveblog is active.', 'tagdiv-liveblog' );
			$button       = __( 'Archive', 'tagdiv-liveblog' );
			$target_state = 'archive';
		} elseif ( 'archive' === $state ) {
			$status       = __( 'This liveblog is archived.', 'tagdiv-liveblog' );
			$button       = __( 'Reopen', 'tagdiv-liveblog' );
			$target_state = 'enable';
		} else {
			return '';
		}

		$out  = '<div class="tdlb-management" data-tdlb-management="1">';
		$out .= '<span class="tdlb-management-status">' . esc_html( $status ) . '</span>';
		$out .= '<button type="button" class="tdlb-management-button" data-tdlb-target-state="' . esc_attr( $target_state ) . '">' . esc_html( $button ) . '</button>';
		$out .= '<span class="tdlb-management-feedback" role="status" aria-live="polite"></span>';
		$out .= '</div>';

		return $out;
	}

	/**
	 * Build per-instance CSS custom properties from Composer controls.
	 *
	 * @return string
	 */
	private function build_css_variables() {
		$variables = array();

		$this->add_color_variable( $variables, '--tdlb-archive-notice-bg', 'archive_notice_background' );
		$this->add_color_variable( $variables, '--tdlb-archive-notice-color', 'archive_notice_text_color' );
		$this->add_color_variable( $variables, '--tdlb-archive-notice-border-color', 'archive_notice_border_color' );
		$this->add_px_variable( $variables, '--tdlb-archive-notice-border-width', 'archive_notice_border_width' );
		$this->add_px_variable( $variables, '--tdlb-archive-notice-radius', 'archive_notice_radius' );
		$this->add_px_variable( $variables, '--tdlb-archive-notice-padding', 'archive_notice_padding' );
		$this->add_px_variable( $variables, '--tdlb-archive-notice-margin-bottom', 'archive_notice_margin_bottom' );
		$this->add_px_variable( $variables, '--tdlb-archive-notice-font-size', 'archive_notice_font_size' );
		$this->add_px_variable( $variables, '--tdlb-archive-notice-line-height', 'archive_notice_line_height' );
		$this->add_px_variable( $variables, '--tdlb-archive-notice-letter-spacing', 'archive_notice_letter_spacing' );
		$variables[] = '--tdlb-archive-notice-border-style:' . $this->sanitize_choice( $this->get_shortcode_att( 'archive_notice_border_style' ), array( 'solid', 'dashed', 'dotted', 'double', 'none' ), 'solid' );
		$variables[] = '--tdlb-archive-notice-font-weight:' . $this->sanitize_choice( $this->get_shortcode_att( 'archive_notice_font_weight' ), array( '400', '500', '600', '700', '800' ), '600' );
		$variables[] = '--tdlb-archive-notice-font-style:' . $this->sanitize_choice( $this->get_shortcode_att( 'archive_notice_font_style' ), array( 'normal', 'italic' ), 'normal' );
		$variables[] = '--tdlb-archive-notice-text-align:' . $this->sanitize_choice( $this->get_shortcode_att( 'archive_notice_text_align' ), array( 'left', 'center', 'right' ), 'left' );
		$variables[] = '--tdlb-archive-notice-text-transform:' . $this->sanitize_choice( $this->get_shortcode_att( 'archive_notice_text_transform' ), array( 'none', 'uppercase', 'lowercase', 'capitalize' ), 'none' );

		$this->add_color_variable( $variables, '--tdlb-key-summary-bg', 'key_summary_background' );
		$this->add_color_variable( $variables, '--tdlb-key-summary-color', 'key_summary_text_color' );
		$this->add_color_variable( $variables, '--tdlb-key-summary-border-color', 'key_summary_border_color' );
		$this->add_px_variable( $variables, '--tdlb-key-summary-border-width', 'key_summary_border_width' );
		$this->add_px_variable( $variables, '--tdlb-key-summary-radius', 'key_summary_radius' );
		$this->add_px_variable( $variables, '--tdlb-key-summary-padding', 'key_summary_padding' );
		$this->add_px_variable( $variables, '--tdlb-key-summary-margin-bottom', 'key_summary_margin_bottom' );
		$this->add_px_variable( $variables, '--tdlb-key-summary-title-size', 'key_summary_title_size' );
		$this->add_px_variable( $variables, '--tdlb-key-summary-item-gap', 'key_summary_item_gap' );
		$this->add_color_variable( $variables, '--tdlb-key-summary-item-border-color', 'key_summary_item_border_color' );
		$this->add_px_variable( $variables, '--tdlb-key-summary-item-border-width', 'key_summary_item_border_width' );
		$variables[] = '--tdlb-key-summary-border-style:' . $this->sanitize_choice( $this->get_shortcode_att( 'key_summary_border_style' ), array( 'solid', 'dashed', 'dotted', 'double', 'none' ), 'solid' );
		$variables[] = '--tdlb-key-summary-title-weight:' . $this->sanitize_choice( $this->get_shortcode_att( 'key_summary_title_weight' ), array( '400', '500', '600', '700', '800' ), '700' );

		$this->add_color_variable( $variables, '--tdlb-key-entry-bg', 'key_entry_background' );
		$this->add_color_variable( $variables, '--tdlb-key-entry-color', 'key_entry_text_color' );
		$this->add_color_variable( $variables, '--tdlb-key-entry-border-color', 'key_entry_border_color' );
		$this->add_px_variable( $variables, '--tdlb-key-entry-border-width', 'key_entry_border_width' );
		$this->add_px_variable( $variables, '--tdlb-key-entry-radius', 'key_entry_radius' );
		$this->add_color_variable( $variables, '--tdlb-key-entry-accent-color', 'key_entry_accent_color' );
		$this->add_px_variable( $variables, '--tdlb-key-entry-accent-width', 'key_entry_accent_width' );
		$variables[] = '--tdlb-key-entry-border-style:' . $this->sanitize_choice( $this->get_shortcode_att( 'key_entry_border_style' ), array( 'solid', 'dashed', 'dotted', 'double', 'none' ), 'solid' );
		$this->add_color_variable( $variables, '--tdlb-key-label-bg', 'key_label_background' );
		$this->add_color_variable( $variables, '--tdlb-key-label-color', 'key_label_text_color' );
		$this->add_px_variable( $variables, '--tdlb-key-label-font-size', 'key_label_font_size' );
		$this->add_px_variable( $variables, '--tdlb-key-label-letter-spacing', 'key_label_letter_spacing' );
		$this->add_px_variable( $variables, '--tdlb-key-label-margin-bottom', 'key_label_margin_bottom' );
		$variables[] = '--tdlb-key-label-font-weight:' . $this->sanitize_choice( $this->get_shortcode_att( 'key_label_font_weight' ), array( '400', '500', '600', '700', '800' ), '700' );
		$variables[] = '--tdlb-key-label-display:' . ( 'yes' === $this->get_shortcode_att( 'key_label' ) ? 'block' : 'none' );
		$this->add_content_variable( $variables, '--tdlb-key-label-content', 'key_label_text' );

		$this->add_color_variable( $variables, '--tdlb-entry-bg', 'entry_background' );
		$this->add_color_variable( $variables, '--tdlb-entry-color', 'entry_text_color' );
		$this->add_color_variable( $variables, '--tdlb-entry-border-color', 'entry_border_color' );
		$this->add_px_variable( $variables, '--tdlb-entry-border-width', 'entry_border_width' );
		$this->add_px_variable( $variables, '--tdlb-entry-radius', 'entry_radius' );
		$this->add_px_variable( $variables, '--tdlb-entry-padding', 'entry_padding' );
		$this->add_px_variable( $variables, '--tdlb-entry-gap', 'entry_gap' );

		$this->add_color_variable( $variables, '--tdlb-meta-bg', 'meta_background' );
		$this->add_color_variable( $variables, '--tdlb-meta-color', 'meta_text_color' );
		$this->add_px_variable( $variables, '--tdlb-meta-padding', 'meta_padding' );
		$this->add_px_variable( $variables, '--tdlb-meta-gap', 'meta_gap' );
		$this->add_px_variable( $variables, '--tdlb-meta-stack-gap', 'meta_stack_gap' );
		$this->add_px_variable( $variables, '--tdlb-meta-inline-gap', 'meta_inline_gap' );

		$this->add_color_variable( $variables, '--tdlb-content-bg', 'content_background' );
		$this->add_color_variable( $variables, '--tdlb-content-color', 'content_text_color' );
		$this->add_color_variable( $variables, '--tdlb-content-border-color', 'content_border_color' );
		$this->add_px_variable( $variables, '--tdlb-content-border-width', 'content_border_width' );
		$this->add_px_variable( $variables, '--tdlb-content-radius', 'content_radius' );
		$this->add_px_variable( $variables, '--tdlb-content-padding', 'content_padding' );
		$variables[] = '--tdlb-content-border-style:' . $this->sanitize_choice( $this->get_shortcode_att( 'content_border_style' ), array( 'solid', 'dashed', 'dotted', 'double', 'none' ), 'solid' );

		$this->add_color_variable( $variables, '--tdlb-timeline-color', 'timeline_color' );
		$this->add_px_variable( $variables, '--tdlb-timeline-width', 'timeline_width' );
		$this->add_px_variable( $variables, '--tdlb-timeline-offset', 'timeline_offset' );

		$show_author    = 'no' !== $this->get_shortcode_att( 'show_author' );
		$show_avatar    = 'no' !== $this->get_shortcode_att( 'show_avatar' );
		$show_time      = 'no' !== $this->get_shortcode_att( 'show_timestamp' );
		$author_meta    = $show_author || $show_avatar;
		$time_mode      = $this->sanitize_choice( $this->get_shortcode_att( 'time_display' ), array( 'exact', 'relative', 'both' ), 'exact' );
		$meta_layout    = $this->sanitize_choice( $this->get_shortcode_att( 'meta_layout' ), array( 'stacked', 'inline' ), 'stacked' );
		$meta_position  = $this->sanitize_choice( $this->get_shortcode_att( 'meta_position' ), array( 'top', 'bottom' ), 'top' );
		$meta_order     = $this->sanitize_choice( $this->get_shortcode_att( 'meta_order' ), array( 'time_author', 'author_time' ), 'time_author' );
		$meta_alignment = $this->sanitize_choice( $this->get_shortcode_att( 'meta_alignment' ), array( 'left', 'center', 'right' ), 'left' );

		$variables[] = '--tdlb-show-author:' . ( $show_author ? 'inline-flex' : 'none' );
		$variables[] = '--tdlb-show-avatar:' . ( $show_avatar ? 'inline-flex' : 'none' );
		$variables[] = '--tdlb-author-section-display:' . ( $author_meta ? 'flex' : 'none' );
		$variables[] = '--tdlb-time-section-display:' . ( $show_time ? 'flex' : 'none' );
		$variables[] = '--tdlb-relative-time-display:' . ( $show_time && in_array( $time_mode, array( 'relative', 'both' ), true ) ? 'block' : 'none' );
		$variables[] = '--tdlb-exact-time-display:' . ( $show_time && in_array( $time_mode, array( 'exact', 'both' ), true ) ? 'block' : 'none' );
		$variables[] = '--tdlb-meta-basis:' . ( 'inline' === $meta_layout ? 'auto' : '100%' );
		$variables[] = '--tdlb-meta-group-justify:' . $this->alignment_to_flex( $meta_alignment );
		$variables[] = '--tdlb-meta-text-align:' . $meta_alignment;

		$base = 'bottom' === $meta_position ? 60 : 10;
		if ( 'author_time' === $meta_order ) {
			$author_order    = $base;
			$timestamp_order = $base + 10;
			$author_first    = true;
		} else {
			$timestamp_order = $base;
			$author_order    = $base + 10;
			$author_first    = false;
		}

		$variables[] = '--tdlb-author-order:' . $author_order;
		$variables[] = '--tdlb-timestamp-order:' . $timestamp_order;
		$variables[] = '--tdlb-content-order:50';
		$variables[] = '--tdlb-timeline-display:' . ( 'yes' === $this->get_shortcode_att( 'timeline' ) ? 'block' : 'none' );

		$separator_enabled = 'inline' === $meta_layout && $author_meta && $show_time && '' !== trim( (string) $this->get_shortcode_att( 'meta_separator' ) );
		$variables[]       = '--tdlb-author-separator-display:' . ( $separator_enabled && $author_first ? 'inline-block' : 'none' );
		$variables[]       = '--tdlb-time-separator-display:' . ( $separator_enabled && ! $author_first ? 'inline-block' : 'none' );

		$this->add_content_variable( $variables, '--tdlb-meta-separator-content', 'meta_separator' );
		$this->add_content_variable( $variables, '--tdlb-author-prefix-content', 'author_prefix' );
		$this->add_content_variable( $variables, '--tdlb-timestamp-prefix-content', 'timestamp_prefix' );

		return implode( ';', $variables ) . ';';
	}

	private function add_color_variable( &$variables, $css_name, $att_name ) {
		$value = $this->sanitize_color_value( $this->get_shortcode_att( $att_name ) );
		if ( '' !== $value ) {
			$variables[] = $css_name . ':' . $value;
		}
	}

	private function add_content_variable( &$variables, $css_name, $att_name ) {
		$value = wp_strip_all_tags( trim( (string) $this->get_shortcode_att( $att_name ) ) );
		$value = preg_replace( '/[\x00-\x1F\x7F]/u', '', $value );
		if ( ! is_string( $value ) ) {
			$value = '';
		}
		if ( strlen( $value ) > 64 ) {
			$value = substr( $value, 0, 64 );
		}
		$encoded     = wp_json_encode( $value, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE );
		$variables[] = $css_name . ':' . ( is_string( $encoded ) ? $encoded : '""' );
	}

	private function sanitize_color_value( $value ) {
		$value = trim( (string) $value );
		if ( '' === $value ) {
			return '';
		}
		if ( preg_match( '/^#[0-9a-f]{3}(?:[0-9a-f]{3})?$/i', $value ) || preg_match( '/^#[0-9a-f]{4}(?:[0-9a-f]{4})?$/i', $value ) ) {
			return $value;
		}
		if ( preg_match( '/^var\(--[a-z0-9_-]+\)$/i', $value ) ) {
			return $value;
		}
		if ( in_array( strtolower( $value ), array( 'transparent', 'currentcolor' ), true ) ) {
			return $value;
		}
		if ( preg_match( '/^rgba?\((.+)\)$/i', $value, $matches ) ) {
			$parts = array_map( 'trim', explode( ',', $matches[1] ) );
			if ( 3 !== count( $parts ) && 4 !== count( $parts ) ) {
				return '';
			}
			for ( $i = 0; $i < 3; $i++ ) {
				if ( ! preg_match( '/^\d{1,3}$/', $parts[ $i ] ) || (int) $parts[ $i ] > 255 ) {
					return '';
				}
			}
			if ( 4 === count( $parts ) ) {
				if ( ! is_numeric( $parts[3] ) ) {
					return '';
				}
				$alpha = (float) $parts[3];
				if ( $alpha < 0 || $alpha > 1 ) {
					return '';
				}
			}
			return $value;
		}
		return '';
	}

	private function add_px_variable( &$variables, $css_name, $att_name ) {
		$variables[] = $css_name . ':' . absint( $this->get_shortcode_att( $att_name ) ) . 'px';
	}

	private function alignment_to_flex( $alignment ) {
		$alignment = $this->sanitize_choice( $alignment, array( 'left', 'center', 'right' ), 'left' );
		if ( 'center' === $alignment ) {
			return 'center';
		}
		if ( 'right' === $alignment ) {
			return 'flex-end';
		}
		return 'flex-start';
	}

	private function sanitize_choice( $value, $allowed, $default ) {
		$value = sanitize_key( (string) $value );
		return in_array( $value, $allowed, true ) ? $value : $default;
	}

	/**
	 * Static representative preview for tagDiv Composer.
	 *
	 * @return string
	 */
	private function render_preview() {
		$entries = array(
			array(
				'relative' => __( 'Just now', 'tagdiv-liveblog' ),
				'time'     => '12:34',
				'author'   => __( 'Reporter', 'tagdiv-liveblog' ),
				'text'     => __( 'A live update appears here. Typography, spacing, borders and metadata can be adjusted from the Composer controls.', 'tagdiv-liveblog' ),
				'key'      => true,
			),
			array(
				'relative' => __( '7 minutes ago', 'tagdiv-liveblog' ),
				'time'     => '12:27',
				'author'   => __( 'Editor', 'tagdiv-liveblog' ),
				'text'     => __( 'New entries inserted by Liveblog will inherit the same scoped presentation automatically.', 'tagdiv-liveblog' ),
				'key'      => false,
			),
		);

		$out = '<div class="liveblog-feed tdlb-preview" aria-label="' . esc_attr__( 'Liveblog preview', 'tagdiv-liveblog' ) . '">';

		foreach ( $entries as $entry ) {
			$entry_class = 'liveblog-entry tdlb-preview-entry' . ( $entry['key'] ? ' type-key' : '' );
			$out        .= '<article class="' . esc_attr( $entry_class ) . '">';
			$out        .= '<aside class="liveblog-entry-aside">';
			$out        .= '<span class="liveblog-meta-time">';
			$out        .= '<span>' . esc_html( $entry['relative'] ) . '</span>';
			$out        .= '<span>' . esc_html( $entry['time'] ) . '</span>';
			$out        .= '</span>';
			$out        .= '</aside>';
			$out        .= '<div class="liveblog-entry-main">';
			$out        .= '<header class="liveblog-meta-authors">';
			$out        .= '<div class="liveblog-meta-author">';
			$out        .= '<div class="liveblog-meta-author-avatar"><span class="tdlb-preview-avatar" aria-hidden="true"></span></div>';
			$out        .= '<span class="liveblog-meta-author-name">' . esc_html( $entry['author'] ) . '</span>';
			$out        .= '</div>';
			$out        .= '</header>';
			$out        .= '<div class="liveblog-entry-content"><p>' . esc_html( $entry['text'] ) . '</p></div>';
			$out        .= '</div>';
			$out        .= '</article>';
		}

		$out .= '</div>';
		return $out;
	}
}
