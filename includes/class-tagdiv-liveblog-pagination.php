<?php
/**
 * Per-block page-size adapter for Automattic Liveblog's native pagination.
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
	 * @return void
	 */
	public static function init() {
		add_action( 'wp_footer', array( __CLASS__, 'attach_runtime_bootstrap' ), 1 );
		add_filter( 'liveblog_number_of_entries', array( __CLASS__, 'filter_entries_per_page' ), 20 );
	}

	/**
	 * Attach the per-block page size immediately before Automattic Liveblog's app.
	 *
	 * The Liveblog handle has already been localized by this point, so
	 * window.liveblog_settings exists before this inline script runs.
	 *
	 * @return void
	 */
	public static function attach_runtime_bootstrap() {
		if (
			is_admin()
			|| ! class_exists( 'Tagdiv_Liveblog_Plugin' )
			|| ! Tagdiv_Liveblog_Plugin::is_liveblog_post()
			|| ! wp_script_is( 'liveblog', 'enqueued' )
		) {
			return;
		}

		$script = <<<'JS'
(function () {
	'use strict';

	var slot = document.querySelector('[data-tagdiv-liveblog-slot="1"]');

	if (!slot || !window.liveblog_settings) {
		return;
	}

	var perPage = parseInt(
		slot.getAttribute('data-liveblog-entries-per-page') || '20',
		10
	);

	if (!perPage || perPage < 1) {
		perPage = 20;
	}

	perPage = Math.min(perPage, 100);

	slot.setAttribute('data-liveblog-entries-per-page', String(perPage));

	/*
	 * Automattic Liveblog copies this object into its Redux config when app.js
	 * mounts. This inline script runs after wp_localize_script() output but before
	 * app.js itself.
	 */
	window.liveblog_settings.entries_per_page = perPage;
	window.__tdlbEntriesPerPageBootstrap = true;

	/*
	 * The REST request independently determines its page size in PHP. Pass the
	 * same block-configured value on upstream native paginated requests, including
	 * the special entry deep-link jump endpoint.
	 */
	if (window.XMLHttpRequest && !window.__tdlbEntriesPerPageXhrPatched) {
		var originalOpen = window.XMLHttpRequest.prototype.open;

		window.XMLHttpRequest.prototype.open = function (method, url) {
			var args = Array.prototype.slice.call(arguments);
			var requestUrl = typeof url === 'string' ? url : '';
			var isNativePaginationRequest =
				requestUrl.indexOf('get-entries/') !== -1 ||
				requestUrl.indexOf('jump-to-key-event/') !== -1;

			if (
				isNativePaginationRequest &&
				requestUrl.indexOf('tdlb_per_page=') === -1
			) {
				requestUrl +=
					(requestUrl.indexOf('?') === -1 ? '?' : '&') +
					'tdlb_per_page=' +
					encodeURIComponent(String(perPage));

				args[1] = requestUrl;
			}

			return originalOpen.apply(this, args);
		};

		window.__tdlbEntriesPerPageXhrPatched = true;
	}

	/*
	 * A numeric fragment is an upstream deep link to one Liveblog entry. Once the
	 * visitor uses native pagination or merges newly available entries, that deep
	 * link no longer represents the currently displayed page. Remove only that
	 * numeric fragment, without reloading or changing Liveblog pagination state.
	 */
	function clearEntryDeepLinkHash() {
		if (
			!/^#\d+$/.test(window.location.hash) ||
			!window.history ||
			typeof window.history.replaceState !== 'function'
		) {
			return;
		}

		window.history.replaceState(
			window.history.state,
			document.title,
			window.location.pathname + window.location.search
		);
	}

	document.addEventListener('click', function (event) {
		var target = event.target;

		if (!target || typeof target.closest !== 'function') {
			return;
		}

		var button = target.closest(
			'.liveblog-pagination-btn, .liveblog-update-btn'
		);

		if (!button || !slot.contains(button)) {
			return;
		}

		clearEntryDeepLinkHash();
	});
}());
JS;

		wp_add_inline_script( 'liveblog', $script, 'before' );
	}

	/**
	 * Apply the requested page size to Automattic Liveblog's native REST query.
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
	 * Clamp page size to Liveblog's supported range.
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
