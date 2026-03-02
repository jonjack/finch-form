/**
 * Finch Form – AJAX form submission and Turnstile integration
 */
(function ($) {
	'use strict';

	// Enable debugging - writes to console.log
	var debug = true; 

	function init() {
		var $form = $('#finch-form-form');
		if (!$form.length) return;

		var $wrapper = $form.closest('.finch-form-wrapper');
		var $feedbackBlock = $wrapper.find('.finch-form-feedback-block');
		var $submit = $form.find('.finch-form-submit');
		var ajaxUrl = (window.finchForm && window.finchForm.ajaxUrl) || '';
		var action = (window.finchForm && window.finchForm.action) || 'finch-form_submit';
		var nonce = (window.finchForm && window.finchForm.nonce) || '';

		// DEBUG ↓↓↓
		if (debug) {
			console.log("init() → window.finchForm: " + JSON.stringify(window.finchForm, null, 2));
		}
		// DEBUG ↑↑↑

		function showFeedback(content, isError) {
			$feedbackBlock
				.removeClass('is-visible is-error is-success')
				.addClass(isError ? 'is-error is-visible' : 'is-success is-visible')
				.html(content);
		}

		function hideFeedback() {
			$feedbackBlock.removeClass('is-visible is-error is-success').empty();
		}

		function setSubmitting(loading) {
			$submit.prop('disabled', loading);
			if (loading) {
				$submit.addClass('finch-form-loading');
			} else {
				$submit.removeClass('finch-form-loading');
			}
		}

		function resetTurnstile() {
			if (typeof turnstile === 'undefined' || typeof turnstile.reset !== 'function') return;

			// Clear the response field so the next submit never sends the previous (one-time) token.
			// form.reset() does not clear Turnstile's injected field, so the old token would still be sent.
			$form.find('[name="cf-turnstile-response"]').val('');

			var el = $wrapper.find('.cf-turnstile')[0];
			if (el && el.id) {
				turnstile.reset(el.id);
			} else {
				// Implicit rendering may not set an id on the container; reset all widgets.
				turnstile.reset();
			}

			if (debug) console.log('Turnstile reset');
		}

		$form.on('submit', function (e) {
			e.preventDefault();
			if ($submit.prop('disabled')) return;

			hideFeedback();

			var formData = new FormData($form[0]);
			formData.append('action', action);
			if (nonce) formData.append('finch-form_nonce', nonce);

			if (debug) {
				console.log("formData: " + formData);
			}

			setSubmitting(true);

			$.ajax({
				url: ajaxUrl,
				type: 'POST',
				data: formData,
				processData: false,
				contentType: false,
				dataType: 'json'
			})
				.done(function (data) {

					// DEBUG ↓↓↓ 
					if (debug) {
						console.log("form.on('submit').done() → data");
						console.log(JSON.stringify(data, null, 2));
					} 
					// DEBUG ↑↑↑

					if (data && data.success) {
						var successMsg = (data.message || 'Thank you. Your message has been sent.');
						showFeedback(
							'<div class="finch-form-feedback-content">' + $('<div>').text(successMsg).html() + '</div>',
							false
						);
						$form[0].reset();
						// Reset Turnstile after form reset so the next submit gets a new token (and clear response so we never resend the old one).
						resetTurnstile();
					} else {
						var msg = (data && data.message) || 'Something went wrong. Please try again.';
						if (data && data.errors && data.errors.length > 0) {
							var list = '<div class="finch-form-feedback-list">';
							for (var i = 0; i < data.errors.length; i++) {
								list += '<div class="finch-form-feedback-item">' + $('<div>').text(data.errors[i]).html() + '</div>';
							}
							list += '</div>';
							showFeedback(list, true);
						} else {
							showFeedback('<div class="finch-form-feedback-content">' + $('<div>').text(msg).html() + '</div>', true);
						}
						resetTurnstile();
					}
				})
				.fail(function () {
					showFeedback(
						'<div class="finch-form-feedback-content">Network error. Please try again.</div>',
						true
					);
					resetTurnstile();
				})
				.always(function () {
					setSubmitting(false);
				});
		});
	}

	$(function () {
		init();
	});

	// Re-init when content is injected (e.g. WPBakery / AJAX-loaded content)
	$(document).on('finch-form_init', function () {
		init();
	});
})(jQuery);
