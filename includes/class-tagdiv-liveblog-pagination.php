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
	 * Numeric entry deep links use jump-to-key-event rather than the normal first
	 * page request. Cached HTML can contain an old latest_entry_id/timestamp, so
	 * capture a fresh native pagination anchor in parallel. Until it is available,
	 * the upstream-safe `latest` token is used. This affects only sessions that
	 * started from a numeric entry fragment.
	 */
	var deepLinkSession = /^#\d+$/.test(window.location.hash);
	var deepLinkAnchor = '';
	var endpointUrl = String(window.liveblog_settings.endpoint_url || '');

	if (deepLinkSession && endpointUrl && typeof window.fetch === 'function') {
		var anchorUrl = endpointUrl + 'get-entries/1/latest';
		anchorUrl +=
			(anchorUrl.indexOf('?') === -1 ? '?' : '&') +
			'tdlb_per_page=' +
			encodeURIComponent(String(perPage));

		window.fetch(anchorUrl, { credentials: 'same-origin' })
			.then(function (response) {
				return response.ok ? response.json() : null;
			})
			.then(function (payload) {
				var latestEntry =
					payload && payload.entries && payload.entries.length
						? payload.entries[0]
						: null;

				if (
					latestEntry &&
					latestEntry.id &&
					latestEntry.timestamp
				) {
					deepLinkAnchor =
						String(latestEntry.id) + '-' + String(latestEntry.timestamp);
					window.__tdlbDeepLinkAnchor = deepLinkAnchor;
				}
			})
			.catch(function () {
				/* Native `latest` fallback remains sufficient for navigation. */
			});
	}

	function replaceLastKnownEntry(requestUrl, anchor) {
		if (!anchor) {
			return requestUrl;
		}

		requestUrl = requestUrl.replace(
			/(jump-to-key-event\/\d+\/)[^/?]+/,
			'$1' + anchor
		);

		requestUrl = requestUrl.replace(
			/(get-entries\/\d+\/)[^/?]+/,
			'$1' + anchor
		);

		return requestUrl;
	}

	/*
	 * The REST request independently determines its page size in PHP. Pass the
	 * same block-configured value on upstream native paginated requests. For a
	 * numeric deep-link session, also make the jump request use the current full
	 * dataset and keep subsequent non-first pages on the captured fresh anchor.
	 */
	if (window.XMLHttpRequest && !window.__tdlbEntriesPerPageXhrPatched) {
		var originalOpen = window.XMLHttpRequest.prototype.open;

		window.XMLHttpRequest.prototype.open = function (method, url) {
			var args = Array.prototype.slice.call(arguments);
			var requestUrl = typeof url === 'string' ? url : '';
			var isJumpRequest = requestUrl.indexOf('jump-to-key-event/') !== -1;
			var pageMatch = requestUrl.match(/get-entries\/(\d+)\//);
			var isEntriesRequest = !!pageMatch;
			var isNativePaginationRequest = isJumpRequest || isEntriesRequest;

			if (deepLinkSession && isJumpRequest) {
				requestUrl = replaceLastKnownEntry(requestUrl, 'latest');
			}

			if (deepLinkSession && isEntriesRequest) {
				var requestedPage = parseInt(pageMatch[1], 10);
				var requestAnchor =
					requestedPage === 1 ? 'latest' : deepLinkAnchor || 'latest';

				requestUrl = replaceLastKnownEntry(requestUrl, requestAnchor);

				if (requestedPage === 1 && typeof this.addEventListener === 'function') {
					var xhr = this;
					xhr.addEventListener(
						'load',
						function () {
							if (xhr.status >= 200 && xhr.status < 300) {
								deepLinkSession = false;
							}
						},
						{ once: true }
					);
				}
			}

			if (
				isNativePaginationRequest &&
				requestUrl.indexOf('tdlb_per_page=') === -1
			) {
				requestUrl +=
					(requestUrl.indexOf('?') === -1 ? '?' : '&') +
					'tdlb_per_page=' +
					encodeURIComponent(String(perPage));
			}

			args[1] = requestUrl;

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
