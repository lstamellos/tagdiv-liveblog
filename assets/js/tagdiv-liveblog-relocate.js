(function () {
	'use strict';

	var slot = document.querySelector('[data-tagdiv-liveblog-slot="1"]');
	if (!slot) {
		return;
	}

	var postId = parseInt(slot.getAttribute('data-liveblog-post-id') || '0', 10);
	var perPage = parseInt(slot.getAttribute('data-liveblog-entries-per-page') || '20', 10);

	if (!perPage || perPage < 1) {
		perPage = 20;
	}
	perPage = Math.min(perPage, 100);

	slot.setAttribute('data-liveblog-entries-per-page', String(perPage));

	/*
	 * Automattic Liveblog copies window.liveblog_settings into Redux when its
	 * application mounts. This helper is printed before app.js, so the block-level
	 * page size becomes the native frontend entries_per_page value.
	 */
	if (window.liveblog_settings) {
		window.liveblog_settings.entries_per_page = perPage;
	}

	/*
	 * The upstream REST endpoint independently obtains the page size through
	 * WPCOM_Liveblog_Lazyloader::get_number_of_entries(). Add the block value only
	 * to this Liveblog's native get-entries XHR; no pagination UI or state is
	 * replaced by this integration.
	 */
	if (postId && window.XMLHttpRequest && !window.__tdlbEntriesPerPageXhrPatched) {
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

		window.__tdlbEntriesPerPageXhrPatched = true;
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
