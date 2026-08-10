(function () {
	'use strict';

	function ready(callback) {
		if (document.readyState === 'loading') {
			document.addEventListener('DOMContentLoaded', callback, { once: true });
		} else {
			callback();
		}
	}

	ready(function () {
		var settings = window.tagdiv_liveblog_management;

		if (!settings || !settings.ajax_url || !settings.nonce || !settings.post_id) {
			return;
		}

		document.querySelectorAll('[data-tdlb-management="1"] .tdlb-management-button').forEach(function (button) {
			button.addEventListener('click', function () {
				var targetState = button.getAttribute('data-tdlb-target-state');
				var wrapper = button.closest('[data-tdlb-management="1"]');
				var feedback = wrapper ? wrapper.querySelector('.tdlb-management-feedback') : null;

				if (targetState !== 'archive' && targetState !== 'enable') {
					return;
				}

				if (targetState === 'archive' && settings.archive_confirm && !window.confirm(settings.archive_confirm)) {
					return;
				}

				var body = new URLSearchParams();
				body.set('action', settings.action || 'set_liveblog_state_for_post');
				body.set('post_id', String(settings.post_id));
				body.set('state', targetState);
				body.set(settings.nonce_key || '_wpnonce', settings.nonce);

				button.disabled = true;
				button.setAttribute('aria-busy', 'true');
				if (feedback) {
					feedback.textContent = settings.working || '';
				}

				window.fetch(settings.ajax_url, {
					method: 'POST',
					credentials: 'same-origin',
					headers: {
						'Content-Type': 'application/x-www-form-urlencoded; charset=UTF-8'
					},
					body: body.toString()
				}).then(function (response) {
					if (!response.ok) {
						throw new Error('Liveblog state request failed with HTTP ' + response.status);
					}

					window.location.reload();
				}).catch(function (error) {
					button.disabled = false;
					button.removeAttribute('aria-busy');
					if (feedback) {
						feedback.textContent = settings.error || 'The Liveblog state could not be updated.';
					}
					if (window.console && typeof window.console.error === 'function') {
						window.console.error(error);
					}
				});
			});
		});
	});
}());
