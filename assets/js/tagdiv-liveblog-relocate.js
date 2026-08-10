(function () {
	'use strict';

	var slot = document.querySelector('[data-tagdiv-liveblog-slot="1"]');
	if (!slot) {
		return;
	}

	var postId = parseInt(slot.getAttribute('data-liveblog-post-id') || '0', 10);
	var perPage = parseInt(slot.getAttribute('data-liveblog-entries-per-page') || '20', 10);
	var mode = slot.getAttribute('data-liveblog-pagination-mode') || 'infinite';

	if (!perPage || perPage < 1) {
		perPage = 20;
	}
	perPage = Math.min(perPage, 100);

	if (['native', 'load_more', 'infinite'].indexOf(mode) === -1) {
		mode = 'infinite';
	}

	slot.setAttribute('data-liveblog-entries-per-page', String(perPage));
	slot.setAttribute('data-liveblog-pagination-mode', mode);

	/*
	 * Automattic Liveblog copies window.liveblog_settings into Redux when its
	 * application mounts. This helper is intentionally printed before app.js, so
	 * the block-level batch size becomes the frontend entries_per_page value.
	 */
	if (window.liveblog_settings) {
		window.liveblog_settings.entries_per_page = perPage;
	}

	/*
	 * Liveblog's server-side get_entries_paged() independently asks its PHP
	 * lazyloader for the page size. RxJS ajax uses XMLHttpRequest, so add the
	 * block-level size only to the upstream paged-entry requests. The PHP adapter
	 * consumes this public read parameter through liveblog_number_of_entries.
	 */
	if (postId && window.XMLHttpRequest && !window.__tdlbPaginationXhrPatched) {
		var originalOpen = window.XMLHttpRequest.prototype.open;

		window.XMLHttpRequest.prototype.open = function (method, url) {
			var args = Array.prototype.slice.call(arguments);
			var requestUrl = typeof url === 'string' ? url : '';
			var postToken = '/' + postId + '/get-entries/';
			var restRouteToken = encodeURIComponent('/' + postId + '/get-entries/');

			if (
				requestUrl.indexOf('get-entries/') !== -1 &&
				(requestUrl.indexOf(postToken) !== -1 || requestUrl.indexOf(restRouteToken) !== -1)
			) {
				requestUrl += (requestUrl.indexOf('?') === -1 ? '?' : '&') + 'tdlb_per_page=' + encodeURIComponent(String(perPage));
				args[1] = requestUrl;
			}

			return originalOpen.apply(this, args);
		};

		window.__tdlbPaginationXhrPatched = true;
	}

	var container = document.getElementById('wpcom-liveblog-container');

	// Normally Automattic Liveblog has already injected its native root through
	// the_content. If a Newspaper template omits the Post Content element, create
	// the same minimal root contract so the upstream frontend app can still mount.
	if (!container) {
		if (!postId) {
			return;
		}

		container = document.createElement('div');
		container.id = 'wpcom-liveblog-container';
		container.className = String(postId);
	}

	if (!slot.contains(container)) {
		slot.appendChild(container);
	}

	slot.classList.add('tdlb-mounted');
}());
