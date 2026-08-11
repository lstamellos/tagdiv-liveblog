(function () {
	'use strict';

	var slot = document.querySelector('[data-tagdiv-liveblog-slot="1"]');
	if (!slot) {
		return;
	}

	var keyEventsWrapper = slot.querySelector('.tdlb-key-events');
	if (keyEventsWrapper) {
		keyEventsWrapper.classList.add('liveblog-key-events');
	}

	var container = document.getElementById('wpcom-liveblog-container');

	// Normally Automattic Liveblog has already injected its native root through
	// the_content. If a Newspaper template omits the Post Content element, create
	// the same minimal root contract so the upstream frontend app can still mount.
	if (!container) {
		var postId = parseInt(slot.getAttribute('data-liveblog-post-id') || '0', 10);
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
