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
				'title'                 => '',
				'block_background'      => '',
				'block_border_color'    => '',
				'block_border_width'    => '0',
				'block_radius'          => '0',
				'block_padding'         => '0',
				'show_author'           => 'yes',
				'show_avatar'           => 'yes',
				'author_position'       => 'top',
				'author_order'          => '2',
				'author_alignment'      => 'left',
				'show_timestamp'        => 'yes',
				'time_display'          => 'exact',
				'timestamp_position'    => 'top',
				'timestamp_order'       => '1',
				'timestamp_alignment'   => 'left',
				'meta_alignment'        => 'left',
				'meta_stack_gap'        => '12',
				'meta_background'       => '',
				'meta_text_color'       => '',
				'meta_padding'          => '0',
				'meta_gap'              => '8',
				'entry_background'      => '',
				'entry_text_color'      => '',
				'entry_border_color'    => '',
				'entry_border_width'    => '0',
				'entry_radius'          => '0',
				'entry_padding'         => '16',
				'entry_gap'             => '20',
				'content_background'    => '',
				'content_text_color'    => '',
				'content_padding'       => '0',
				'timeline'              => 'no',
				'timeline_color'        => '',
				'timeline_width'        => '2',
				'timeline_offset'       => '10',
			),
			$raw_atts,
			'td_block_liveblog'
		);

		// Preserve the old shared alignment until a saved block explicitly uses
		// the new author/timestamp-specific alignment controls.
		if ( ! array_key_exists( 'author_alignment', $raw_atts ) && array_key_exists( 'meta_alignment', $raw_atts ) ) {
			$this->shortcode_atts['author_alignment'] = $raw_atts['meta_alignment'];
		}
		if ( ! array_key_exists( 'timestamp_alignment', $raw_atts ) && array_key_exists( 'meta_alignment', $raw_atts ) ) {
			$this->shortcode_atts['timestamp_alignment'] = $raw_atts['meta_alignment'];
		}

		$classes = $this->get_block_classes() . ' tdlb-block';
		$style   = $this->build_css_variables();
		$title   = sanitize_text_field( (string) $this->get_shortcode_att( 'title' ) );
		$post_id = Tagdiv_Liveblog_Plugin::get_liveblog_post_id();

		$buffy  = '<div class="' . esc_attr( $classes ) . '">';
		$buffy .= $this->get_block_css();
		$buffy .= '<div class="tdlb-liveblog" data-tagdiv-liveblog-slot="1" data-liveblog-post-id="' . esc_attr( (string) absint( $post_id ) ) . '"' . ( $style ? ' style="' . esc_attr( $style ) . '"' : '' ) . '>';

		if ( '' !== $title ) {
			$buffy .= '<div class="tdlb-title">' . esc_html( $title ) . '</div>';
		}

		if ( Tagdiv_Liveblog_Plugin::is_composer_request() ) {
			$buffy .= $this->render_preview();
		}

		$buffy .= '</div>';
		$buffy .= '</div>';

		return $buffy;
	}

	/**
	 * Build per-instance CSS custom properties from Composer controls.
	 *
	 * @return string
	 */
	private function build_css_variables() {
		$variables = array();

		$this->add_color_variable( $variables, '--tdlb-block-bg', 'block_background' );
		$this->add_color_variable( $variables, '--tdlb-block-border-color', 'block_border_color' );
		$this->add_px_variable( $variables, '--tdlb-block-border-width', 'block_border_width' );
		$this->add_px_variable( $variables, '--tdlb-block-radius', 'block_radius' );
		$this->add_px_variable( $variables, '--tdlb-block-padding', 'block_padding' );

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

		$this->add_color_variable( $variables, '--tdlb-content-bg', 'content_background' );
		$this->add_color_variable( $variables, '--tdlb-content-color', 'content_text_color' );
		$this->add_px_variable( $variables, '--tdlb-content-padding', 'content_padding' );

		$this->add_color_variable( $variables, '--tdlb-timeline-color', 'timeline_color' );
		$this->add_px_variable( $variables, '--tdlb-timeline-width', 'timeline_width' );
		$this->add_px_variable( $variables, '--tdlb-timeline-offset', 'timeline_offset' );

		$show_author = 'no' !== $this->get_shortcode_att( 'show_author' );
		$show_avatar = 'no' !== $this->get_shortcode_att( 'show_avatar' );
		$show_time   = 'no' !== $this->get_shortcode_att( 'show_timestamp' );
		$time_mode   = $this->sanitize_choice( $this->get_shortcode_att( 'time_display' ), array( 'exact', 'relative', 'both' ), 'exact' );

		$variables[] = '--tdlb-show-author:' . ( $show_author ? 'inline-flex' : 'none' );
		$variables[] = '--tdlb-show-avatar:' . ( $show_avatar ? 'inline-flex' : 'none' );
		$variables[] = '--tdlb-author-section-display:' . ( $show_author || $show_avatar ? 'flex' : 'none' );
		$variables[] = '--tdlb-time-section-display:' . ( $show_time ? 'block' : 'none' );
		$variables[] = '--tdlb-relative-time-display:' . ( $show_time && in_array( $time_mode, array( 'relative', 'both' ), true ) ? 'block' : 'none' );
		$variables[] = '--tdlb-exact-time-display:' . ( $show_time && in_array( $time_mode, array( 'exact', 'both' ), true ) ? 'block' : 'none' );

		$author_alignment    = $this->sanitize_choice( $this->get_shortcode_att( 'author_alignment' ), array( 'left', 'center', 'right' ), 'left' );
		$timestamp_alignment = $this->sanitize_choice( $this->get_shortcode_att( 'timestamp_alignment' ), array( 'left', 'center', 'right' ), 'left' );

		$variables[] = '--tdlb-author-align:' . $author_alignment;
		$variables[] = '--tdlb-author-justify:' . $this->alignment_to_flex( $author_alignment );
		$variables[] = '--tdlb-timestamp-align:' . $timestamp_alignment;
		$variables[] = '--tdlb-author-order:' . $this->meta_order( $this->get_shortcode_att( 'author_position' ), $this->get_shortcode_att( 'author_order' ) );
		$variables[] = '--tdlb-timestamp-order:' . $this->meta_order( $this->get_shortcode_att( 'timestamp_position' ), $this->get_shortcode_att( 'timestamp_order' ) );
		$variables[] = '--tdlb-content-order:50';
		$variables[] = '--tdlb-timeline-display:' . ( 'yes' === $this->get_shortcode_att( 'timeline' ) ? 'block' : 'none' );

		return implode( ';', $variables ) . ';';
	}

	/**
	 * Add a CSS color custom property.
	 *
	 * @param array<int,string> $variables CSS custom property declarations.
	 * @param string            $css_name  CSS custom property name.
	 * @param string            $att_name  Shortcode attribute name.
	 * @return void
	 */
	private function add_color_variable( &$variables, $css_name, $att_name ) {
		$value = $this->sanitize_color_value( $this->get_shortcode_att( $att_name ) );
		if ( '' !== $value ) {
			$variables[] = $css_name . ':' . $value;
		}
	}

	/**
	 * Validate a color value emitted by tagDiv Composer.
	 *
	 * @param mixed $value Raw shortcode attribute.
	 * @return string
	 */
	private function sanitize_color_value( $value ) {
		$value = trim( (string) $value );
		if ( '' === $value ) {
			return '';
		}

		if ( preg_match( '/^#[0-9a-f]{3}(?:[0-9a-f]{3})?$/i', $value ) ) {
			return $value;
		}

		if ( preg_match( '/^#[0-9a-f]{4}(?:[0-9a-f]{4})?$/i', $value ) ) {
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
				if ( ! preg_match( '/^\d{1,3}$/', $parts[ $i ] ) ) {
					return '';
				}

				$channel = (int) $parts[ $i ];
				if ( $channel < 0 || $channel > 255 ) {
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

	/**
	 * Add a pixel custom property.
	 *
	 * @param array<int,string> $variables CSS custom property declarations.
	 * @param string            $css_name  CSS custom property name.
	 * @param string            $att_name  Shortcode attribute name.
	 * @return void
	 */
	private function add_px_variable( &$variables, $css_name, $att_name ) {
		$variables[] = $css_name . ':' . absint( $this->get_shortcode_att( $att_name ) ) . 'px';
	}

	/**
	 * Translate alignment to flex justification.
	 *
	 * @param mixed $alignment Alignment.
	 * @return string
	 */
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

	/**
	 * Compute flex order for independently positioned metadata.
	 *
	 * @param mixed $position top|bottom.
	 * @param mixed $order    1|2.
	 * @return int
	 */
	private function meta_order( $position, $order ) {
		$position = $this->sanitize_choice( $position, array( 'top', 'bottom' ), 'top' );
		$order    = $this->sanitize_choice( (string) $order, array( '1', '2' ), '1' );
		$base     = 'bottom' === $position ? 60 : 10;
		return $base + ( '2' === $order ? 10 : 0 );
	}

	/**
	 * Sanitize an enum value.
	 *
	 * @param mixed         $value   Raw value.
	 * @param array<string> $allowed Allowed values.
	 * @param string        $default Default value.
	 * @return string
	 */
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
			),
			array(
				'relative' => __( '7 minutes ago', 'tagdiv-liveblog' ),
				'time'     => '12:27',
				'author'   => __( 'Editor', 'tagdiv-liveblog' ),
				'text'     => __( 'New entries inserted by Liveblog will inherit the same scoped presentation automatically.', 'tagdiv-liveblog' ),
			),
		);

		$out = '<div class="liveblog-feed tdlb-preview" aria-label="' . esc_attr__( 'Liveblog preview', 'tagdiv-liveblog' ) . '">';

		foreach ( $entries as $entry ) {
			$out .= '<article class="liveblog-entry tdlb-preview-entry">';
			$out .= '<aside class="liveblog-entry-aside">';
			$out .= '<span class="liveblog-meta-time">';
			$out .= '<span>' . esc_html( $entry['relative'] ) . '</span>';
			$out .= '<span>' . esc_html( $entry['time'] ) . '</span>';
			$out .= '</span>';
			$out .= '</aside>';
			$out .= '<div class="liveblog-entry-main">';
			$out .= '<header class="liveblog-meta-authors">';
			$out .= '<div class="liveblog-meta-author">';
			$out .= '<div class="liveblog-meta-author-avatar"><span class="tdlb-preview-avatar" aria-hidden="true"></span></div>';
			$out .= '<span class="liveblog-meta-author-name">' . esc_html( $entry['author'] ) . '</span>';
			$out .= '</div>';
			$out .= '</header>';
			$out .= '<div class="liveblog-entry-content"><p>' . esc_html( $entry['text'] ) . '</p></div>';
			$out .= '</div>';
			$out .= '</article>';
		}

		$out .= '</div>';

		return $out;
	}
}
