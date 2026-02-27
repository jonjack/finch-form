/**
 * Finch Form – AJAX form submission and Turnstile integration
 */
(function ($) {
	'use strict';

	function init() {
		var $form = $('#finch-forms-form');
		if (!$form.length) return;

		var $wrapper = $form.closest('.finch-forms-wrapper');
		var $feedbackBlock = $wrapper.find('.finch-forms-feedback-block');
		var $submit = $form.find('.finch-forms-submit');
		var ajaxUrl = (window.finchForms && window.finchForms.ajaxUrl) || '';
		var action = (window.finchForms && window.finchForms.action) || 'finch_forms_submit';
		var nonce = (window.finchForms && window.finchForms.nonce) || '';

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
				$submit.addClass('finch-forms-loading');
			} else {
				$submit.removeClass('finch-forms-loading');
			}
		}

		$form.on('submit', function (e) {
			e.preventDefault();
			if ($submit.prop('disabled')) return;

			var formData = new FormData($form[0]);
			formData.append('action', action);
			if (nonce) formData.append('finch_forms_nonce', nonce);

			setSubmitting(true);
			hideFeedback();

			$.ajax({
				url: ajaxUrl,
				type: 'POST',
				data: formData,
				processData: false,
				contentType: false,
				dataType: 'json'
			})
				.done(function (data) {
					if (data && data.success) {
						var successMsg = (data.message || 'Thank you. Your message has been sent.');
						showFeedback(
							'<div class="finch-forms-feedback-content">' + $('<div>').text(successMsg).html() + '</div>',
							false
						);
						$form[0].reset();
						if (typeof turnstile !== 'undefined' && typeof turnstile.reset === 'function') {
							var el = document.querySelector('.cf-turnstile');
							if (el && el.id) turnstile.reset(el.id);
						}
					} else {
						var msg = (data && data.message) || 'Something went wrong. Please try again.';
						if (data && data.errors && data.errors.length > 0) {
							var list = '<div class="finch-forms-feedback-list">';
							for (var i = 0; i < data.errors.length; i++) {
								list += '<div class="finch-forms-feedback-item">' + $('<div>').text(data.errors[i]).html() + '</div>';
							}
							list += '</div>';
							showFeedback(list, true);
						} else {
							showFeedback('<div class="finch-forms-feedback-content">' + $('<div>').text(msg).html() + '</div>', true);
						}
					}
				})
				.fail(function () {
					showFeedback(
						'<div class="finch-forms-feedback-content">Network error. Please try again.</div>',
						true
					);
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
	$(document).on('finch_forms_init', function () {
		init();
	});
})(jQuery);
