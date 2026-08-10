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
	 * On the frontend this block deliberately does not render Liveblog entries.
	 * Automattic Liveblog keeps ownership of the root container and its runtime;
	 * our early footer script only relocates that root into this slot.
	 *
	 * @param array       $atts    Shortcode attributes.
	 * @param string|null $content Shortcode content.
	 * @return string
	 */
	public function render( $atts, $content = null ) {
		parent::render( $atts );

		$this->shortcode_atts = shortcode_atts(
			array(
				'title'                 => '',
				'show_author'           => 'yes',
				'show_avatar'           => 'yes',
				'show_timestamp'        => 'yes',
				'meta_alignment'        => 'left',
				'entry_background'      => '',
				'entry_text_color'      => '',
				'entry_border_color'    => '',
				'entry_border_width'    => '0',
				'entry_radius'          => '0',
				'entry_padding'         => '16',
				'entry_gap'             => '20',
				'meta_background'       => '',
				'meta_text_color'       => '',
				'meta_padding'          => '0',
				'meta_gap'              => '8',
				'content_background'    => '',
				'content_text_color'    => '',
				'content_padding'       => '0',
				'timeline'              => 'no',
				'timeline_color'        => '',
				'timeline_width'        => '2',
				'timeline_offset'       => '10',
			),
			$atts,
			'td_block_liveblog'
		);

		$classes = $this->get_block_classes() . ' tdlb-block';
		$style   = $this->build_css_variables();
		$title   = sanitize_text_field( (string) $this->get_shortcode_att( 'title' ) );
		$post_id = get_queried_object_id();

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
		$this->add_color_variable( $variables, '--tdlb-content-bg', 'content_background' );
		$this->add_color_variable( $variables, '--tdlb-content-color', 'content_text_color' );
		$this->add_px_variable( $variables, '--tdlb-content-padding', 'content_padding' );
		$this->add_color_variable( $variables, '--tdlb-timeline-color', 'timeline_color' );
		$this->add_px_variable( $variables, '--tdlb-timeline-width', 'timeline_width' );
		$this->add_px_variable( $variables, '--tdlb-timeline-offset', 'timeline_offset' );

		$variables[] = '--tdlb-show-author:' . ( 'no' === $this->get_shortcode_att( 'show_author' ) ? 'none' : 'inline-flex' );
		$variables[] = '--tdlb-show-avatar:' . ( 'no' === $this->get_shortcode_att( 'show_avatar' ) ? 'none' : 'inline-flex' );
		$variables[] = '--tdlb-show-timestamp:' . ( 'no' === $this->get_shortcode_att( 'show_timestamp' ) ? 'none' : 'inline-flex' );
		$variables[] = '--tdlb-meta-align:' . $this->sanitize_choice( $this->get_shortcode_att( 'meta_alignment' ), array( 'left', 'center', 'right' ), 'left' );
		$variables[] = '--tdlb-meta-justify:' . $this->alignment_to_flex( $this->get_shortcode_att( 'meta_alignment' ) );
		$variables[] = '--tdlb-timeline-display:' . ( 'yes' === $this->get_shortcode_att( 'timeline' ) ? 'block' : 'none' );

		return implode( ';', $variables ) . ';';
	}

	/**
	 * Add a CSS color custom property.
	 *
	 * tagDiv's color picker supports hexadecimal and RGBA values. Global colors
	 * may also arrive as a simple CSS custom-property reference. Validate only
	 * those formats rather than accepting arbitrary inline CSS.
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
				'time'   => '12:34',
				'author' => __( 'Reporter', 'tagdiv-liveblog' ),
				'text'   => __( 'A live update appears here. Typography, spacing, borders and metadata can be adjusted from the Composer controls.', 'tagdiv-liveblog' ),
			),
			array(
				'time'   => '12:41',
				'author' => __( 'Editor', 'tagdiv-liveblog' ),
				'text'   => __( 'New entries inserted by Liveblog will inherit the same scoped presentation automatically.', 'tagdiv-liveblog' ),
			),
		);

		$out = '<div id="liveblog-entries" class="tdlb-preview" aria-label="' . esc_attr__( 'Liveblog preview', 'tagdiv-liveblog' ) . '">';
		foreach ( $entries as $index => $entry ) {
			$out .= '<div class="liveblog-entry tdlb-preview-entry" data-timestamp="' . esc_attr( (string) ( time() - ( $index * 420 ) ) ) . '">';
			$out .= '<header class="liveblog-meta">';
			$out .= '<span class="liveblog-author-avatar"><span class="tdlb-preview-avatar" aria-hidden="true"></span></span>';
			$out .= '<span class="liveblog-author-name">' . esc_html( $entry['author'] ) . '</span>';
			$out .= '<span class="liveblog-meta-time"><span class="liveblog-time-update">' . esc_html( $entry['time'] ) . '</span></span>';
			$out .= '</header>';
			$out .= '<div class="liveblog-entry-text"><p>' . esc_html( $entry['text'] ) . '</p></div>';
			$out .= '</div>';
		}
		$out .= '</div>';

		return $out;
	}
}
