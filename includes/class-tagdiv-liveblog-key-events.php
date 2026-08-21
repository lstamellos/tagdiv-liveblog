<?php
/**
 * Key Events integration helpers.
 *
 * @package Tagdiv_Liveblog
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

final class Tagdiv_Liveblog_Key_Events {
	/**
	 * Register upstream Liveblog extension hooks.
	 *
	 * @return void
	 */
	public static function init() {
		add_filter( 'liveblog_key_formats', array( __CLASS__, 'register_formats' ) );
	}

	/**
	 * Add an HTML-aware first-paragraph Key Event format to Automattic Liveblog.
	 *
	 * Automattic Liveblog's built-in first-linebreak formatter only recognises
	 * literal CR/LF characters. Modern editor content can serialize adjacent
	 * paragraphs as HTML without literal newlines, causing the whole entry to be
	 * stripped into one line. This formatter treats common HTML block endings and
	 * BR elements as visual paragraph/line boundaries while remaining inside the
	 * upstream liveblog_key_formats extension contract.
	 *
	 * @param array<string,callable|false> $formats Existing upstream formats.
	 * @return array<string,callable|false>
	 */
	public static function register_formats( $formats ) {
		if ( ! is_array( $formats ) ) {
			$formats = array();
		}

		$formats['first-paragraph'] = array( __CLASS__, 'format_first_paragraph' );

		return $formats;
	}

	/**
	 * Return the first non-empty visual paragraph/block as plain text.
	 *
	 * @param string $content Liveblog entry HTML/content.
	 * @return string
	 */
	public static function format_first_paragraph( $content ) {
		$content = trim( (string) $content );
		if ( '' === $content ) {
			return '';
		}

		$normalized = preg_replace(
			'/<br\s*\/?\s*>|<\/p\s*>|<\/div\s*>|<\/h[1-6]\s*>|<\/li\s*>|\r\n|\r|\n/i',
			"\n",
			$content
		);

		if ( ! is_string( $normalized ) ) {
			return trim( wp_strip_all_tags( $content ) );
		}

		$blocks = preg_split( '/\n+/', $normalized );
		if ( is_array( $blocks ) ) {
			foreach ( $blocks as $block ) {
				$text = trim( wp_strip_all_tags( $block ) );
				if ( '' !== $text ) {
					return $text;
				}
		}
		}

		return trim( wp_strip_all_tags( $content ) );
	}
}
