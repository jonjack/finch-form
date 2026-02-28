/**
 * Finch Form – AJAX form submission and Turnstile integration
 */
(function ($) {
	'use strict';

	function init() {
		var $form = $('#finch-form-form');
		if (!$form.length) return;

		var $wrapper = $form.closest('.finch-form-wrapper');
		var $feedbackBlock = $wrapper.find('.finch-form-feedback-block');
		var $submit = $form.find('.finch-form-submit');
		var ajaxUrl = (window.finchForm && window.finchForm.ajaxUrl) || '';
		var action = (window.finchForm && window.finchForm.action) || 'finch-form_submit';
		var nonce = (window.finchForm && window.finchForm.nonce) || '';
		var limits = (window.finchForm && window.finchForm.limits) || {};

		function validateEmail(email) {
			var re = /^[^\s@]+@[^\s@]+\.[^\s@]+$/;
			return typeof email === 'string' && re.test(email);
		}

		function validateForm() {
			var errors = [];
			var name = $.trim($form.find('#finch_name').val() || '');
			var email = $.trim($form.find('#finch_email').val() || '');
			var subject = $.trim($form.find('#finch_subject').val() || '');
			var message = $.trim($form.find('#finch_message').val() || '');

			if (limits.nameMin != null && limits.nameMax != null) {
				if (name.length < limits.nameMin || name.length > limits.nameMax) {
					errors.push('Name cannot be empty.');
				}
			}
			if (limits.emailMin != null && limits.emailMax != null) {
				if (!validateEmail(email)) {
					errors.push('Please enter a valid email address.');
				} else if (email.length < limits.emailMin || email.length > limits.emailMax) {
					errors.push('Email must be between ' + limits.emailMin + ' and ' + limits.emailMax + ' characters.');
				}
			}
			if (limits.subjectMin != null && limits.subjectMax != null && subject !== '') {
				if (subject.length < limits.subjectMin || subject.length > limits.subjectMax) {
					errors.push('Subject must be between ' + limits.subjectMin + ' and ' + limits.subjectMax + ' characters.');
				}
			}
			if (limits.messageMin != null && limits.messageMax != null) {
				if (message.length < limits.messageMin || message.length > limits.messageMax) {
					errors.push('Message must be between ' + limits.messageMin + ' and ' + limits.messageMax + ' characters.');
				}
			}

			return errors;
		}

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
			if (typeof turnstile !== 'undefined' && typeof turnstile.reset === 'function') {
				var el = $wrapper.find('.cf-turnstile')[0];
				if (el && el.id) turnstile.reset(el.id);
			}
		}

		$form.on('submit', function (e) {
			e.preventDefault();
			if ($submit.prop('disabled')) return;

			hideFeedback();

			var clientErrors = validateForm();
			if (clientErrors.length > 0) {
				var list = '<div class="finch-form-feedback-list">';
				for (var i = 0; i < clientErrors.length; i++) {
					list += '<div class="finch-form-feedback-item">' + $('<div>').text(clientErrors[i]).html() + '</div>';
				}
				list += '</div>';
				showFeedback(list, true);
				return;
			}

			var formData = new FormData($form[0]);
			formData.append('action', action);
			if (nonce) formData.append('finch-form_nonce', nonce);

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
					if (data && data.success) {
						var successMsg = (data.message || 'Thank you. Your message has been sent.');
						showFeedback(
							'<div class="finch-form-feedback-content">' + $('<div>').text(successMsg).html() + '</div>',
							false
						);
						$form[0].reset();
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
