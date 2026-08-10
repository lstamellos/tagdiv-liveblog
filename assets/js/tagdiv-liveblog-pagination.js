(function () {
	'use strict';

	var slot = document.querySelector('[data-tagdiv-liveblog-slot="1"]');
	var root = document.getElementById('wpcom-liveblog-container');

	if (!slot || !root || !slot.contains(root)) {
		return;
	}

	var mode = slot.getAttribute('data-liveblog-pagination-mode') || 'infinite';
	if (['native', 'load_more', 'infinite'].indexOf(mode) === -1) {
		mode = 'infinite';
	}

	slot.classList.add('tdlb-pagination-' + mode.replace('_', '-'));

	if (mode === 'native') {
		return;
	}

	var labels = window.tagdivLiveblogPagination || {};
	var history = document.createElement('div');
	var controls = document.createElement('div');
	var button = document.createElement('button');
	var status = document.createElement('div');
	var sentinel = document.createElement('div');
	var observer = null;
	var rootObserver = null;
	var loading = false;
	var pending = null;
	var failureTimer = null;
	var initialized = false;

	history.className = 'tdlb-loaded-pages';
	history.setAttribute('aria-live', 'off');
	controls.className = 'tdlb-pagination-controls';
	button.className = 'tdlb-load-more';
	button.type = 'button';
	button.textContent = labels.loadMore || 'Load more';
	status.className = 'tdlb-pagination-status';
	status.setAttribute('role', 'status');
	status.setAttribute('aria-live', 'polite');
	sentinel.className = 'tdlb-pagination-sentinel';
	sentinel.setAttribute('aria-hidden', 'true');

	slot.insertBefore(history, root);
	controls.appendChild(button);
	controls.appendChild(status);
	controls.appendChild(sentinel);
	slot.appendChild(controls);

	function getPageState() {
		var pageLabel = root.querySelector('.liveblog-pagination-pages');
		var next = root.querySelector('.liveblog-pagination-next:not([disabled])');
		var current = 1;
		var total = 1;

		if (pageLabel) {
			var match = pageLabel.textContent.match(/(\d+)\s+of\s+(\d+)/i);
			if (match) {
				current = parseInt(match[1], 10) || 1;
				total = parseInt(match[2], 10) || 1;
			}
		}

		return {
			current: current,
			total: total,
			next: next,
			hasMore: !!next && current < total
		};
	}

	function cloneCurrentFeed() {
		var feed = root.querySelector('.liveblog-feed');
		if (!feed) {
			return null;
		}

		var clone = feed.cloneNode(true);
		clone.classList.add('tdlb-loaded-page');
		clone.querySelectorAll('.liveblog-entry-tools, .liveblog-entry-edit').forEach(function (node) {
			node.remove();
		});
		return clone;
	}

	function setLoading(value) {
		loading = value;
		slot.classList.toggle('tdlb-pagination-loading', value);
		button.disabled = value;
		button.textContent = value ? (labels.loading || 'Loading…') : (labels.loadMore || 'Load more');
		status.textContent = value ? (labels.loading || 'Loading…') : '';
	}

	function finishFailure() {
		if (!loading) {
			return;
		}

		pending = null;
		setLoading(false);
		button.textContent = labels.retry || 'Retry loading';
		button.hidden = false;
		status.textContent = '';
		slot.classList.add('tdlb-pagination-error');
	}

	function syncControls() {
		var state = getPageState();
		if (!state.hasMore) {
			button.hidden = true;
			sentinel.hidden = true;
			if (observer) {
				observer.disconnect();
			}
			return;
		}

		sentinel.hidden = false;
		button.hidden = mode === 'infinite';
	}

	function detectCompletedLoad() {
		if (!loading || !pending) {
			return;
		}

		var state = getPageState();
		if (state.current <= pending.page) {
			return;
		}

		if (failureTimer) {
			window.clearTimeout(failureTimer);
			failureTimer = null;
		}

		if (pending.feed) {
			history.appendChild(pending.feed);
		}

		pending = null;
		slot.classList.remove('tdlb-pagination-error');
		setLoading(false);
		syncControls();
	}

	function loadNextPage() {
		if (loading) {
			return;
		}

		var state = getPageState();
		if (!state.hasMore || !state.next) {
			syncControls();
			return;
		}

		pending = {
			page: state.current,
			feed: cloneCurrentFeed()
		};

		setLoading(true);
		slot.classList.remove('tdlb-pagination-error');
		state.next.click();

		failureTimer = window.setTimeout(finishFailure, 12000);
	}

	function initialize() {
		if (initialized) {
			return;
		}

		if (!root.querySelector('.liveblog-pagination') || !root.querySelector('.liveblog-feed')) {
			return;
		}

		initialized = true;
		button.addEventListener('click', loadNextPage);
		syncControls();

		if (mode === 'infinite' && 'IntersectionObserver' in window) {
			observer = new IntersectionObserver(function (entries) {
				entries.forEach(function (entry) {
					if (entry.isIntersecting) {
						loadNextPage();
					}
				});
			}, {
				root: null,
				rootMargin: '800px 0px',
				threshold: 0
			});
			observer.observe(sentinel);
		} else if (mode === 'infinite') {
			// Progressive enhancement: browsers without IntersectionObserver retain
			// the same batching behaviour through an explicit Load more control.
			button.hidden = false;
		}
	}

	rootObserver = new MutationObserver(function () {
		initialize();
		detectCompletedLoad();
	});
	rootObserver.observe(root, { childList: true, subtree: true, characterData: true });

	initialize();
}());
