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
	 * Upstream per-post Key Events template meta key.
	 *
	 * @var string
	 */
	const TEMPLATE_META_KEY = '_liveblog_key_entry_template';

	/**
	 * Register upstream Liveblog extension hooks.
	 *
	 * @return void
	 */
	public static function init() {
		add_filter( 'liveblog_key_formats', array( __CLASS__, 'register_formats' ) );
		add_filter( 'body_class', array( __CLASS__, 'add_template_body_class' ) );
		add_action( 'wp_enqueue_scripts', array( __CLASS__, 'enqueue_template_presentation' ), 20 );
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
	 * Return the first non-empty editorial paragraph/block as plain text.
	 *
	 * Liveblog command tokens such as /key are stored as semantic command spans,
	 * for example <span class="liveblog-command type-key">key</span>. Those spans
	 * are control metadata rather than editorial content, so remove them before
	 * selecting the first visual block.
	 *
	 * @param string $content Liveblog entry HTML/content.
	 * @return string
	 */
	public static function format_first_paragraph( $content ) {
		$content = trim( (string) $content );
		if ( '' === $content ) {
			return '';
		}

		$editorial_content = preg_replace(
			'~<span\b[^>]*class=(["\'])[^"\']*\bliveblog-command\b[^"\']*\1[^>]*>.*?</span>~is',
			'',
			$content
		);

		if ( ! is_string( $editorial_content ) ) {
			$editorial_content = $content;
		}

		$normalized = preg_replace(
			'/<br\s*\/?\s*>|<\/p\s*>|<\/div\s*>|<\/h[1-6]\s*>|<\/li\s*>|\r\n|\r|\n/i',
			"\n",
			$editorial_content
		);

		if ( ! is_string( $normalized ) ) {
			return trim( wp_strip_all_tags( $editorial_content ) );
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

		return trim( wp_strip_all_tags( $editorial_content ) );
	}

	/**
	 * Resolve the intended upstream Key Events presentation mode.
	 *
	 * Automattic Liveblog 1.12.x still stores the legacy timeline/list choice,
	 * but its React EventsContainer no longer consumes that value. The adapter
	 * reuses it only as a presentation hint for the existing native React DOM.
	 *
	 * @param int $post_id Liveblog post ID.
	 * @return string
	 */
	public static function get_template_mode( $post_id ) {
		$template = get_post_meta( absint( $post_id ), self::TEMPLATE_META_KEY, true );

		return 'list' === $template ? 'list' : 'timeline';
	}

	/**
	 * Expose the resolved per-liveblog template mode as a page presentation class.
	 *
	 * @param array<int,string> $classes Existing body classes.
	 * @return array<int,string>
	 */
	public static function add_template_body_class( $classes ) {
		if ( ! class_exists( 'Tagdiv_Liveblog_Plugin' ) || ! Tagdiv_Liveblog_Plugin::is_liveblog_post() ) {
			return $classes;
		}

		$post_id = Tagdiv_Liveblog_Plugin::get_liveblog_post_id();
		if ( $post_id <= 0 ) {
			return $classes;
		}

		$classes[] = 'tdlb-key-events-template-' . self::get_template_mode( $post_id );

		return array_values( array_unique( $classes ) );
	}

	/**
	 * Add the minimal React presentation mapping for the upstream list mode.
	 *
	 * The timeline mode remains the default stylesheet layout: timestamp plus
	 * content. The legacy list meaning is content-only, so hide the React meta
	 * timestamp and collapse each event to one content column. No React markup,
	 * navigation, state or delete behavior is changed.
	 *
	 * @return void
	 */
	public static function enqueue_template_presentation() {
		if ( ! wp_style_is( 'tagdiv-liveblog', 'enqueued' ) ) {
			return;
		}

		$css = implode(
			"\n",
			array(
				'.tdlb-key-events-template-list .tdlb-liveblog .tdlb-key-events .liveblog-event-body { grid-template-columns: minmax(0, 1fr); }',
				'.tdlb-key-events-template-list .tdlb-liveblog .tdlb-key-events .liveblog-event-meta { display: none; }',
			)
		);

		wp_add_inline_style( 'tagdiv-liveblog', $css );
	}
}
