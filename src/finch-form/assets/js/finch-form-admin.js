/* Finch Form – admin subject list management */
(function ($) {
	'use strict';

	function initSubjects() {
		var $wrapper = $('.finch-form-subjects-wrapper');
		if (!$wrapper.length) {
			return;
		}

		var max = parseInt($wrapper.data('max'), 10) || (finchFormAdmin && finchFormAdmin.maxSubjects) || 10;
		var $input = $wrapper.find('.finch-form-subject-input');
		var $add = $wrapper.find('.finch-form-subject-add');
		var $list = $wrapper.find('.finch-form-subject-list');
		var $error = $wrapper.find('.finch-form-subject-error');

		function setError(msg) {
			if (!msg) {
				$error.text('').hide();
			} else {
				$error.text(msg).show();
			}
		}

		function currentCount() {
			return $list.find('.finch-form-subject-item').length;
		}

		function updateState() {
			var count = currentCount();
			if (count >= max) {
				$input.prop('disabled', true);
				$add.prop('disabled', true);
				$wrapper.addClass('finch-form-subjects-full');
			} else {
				$input.prop('disabled', false);
				$add.prop('disabled', false);
				$wrapper.removeClass('finch-form-subjects-full');
			}
		}

		function addSubject(text) {
			text = $.trim(text || '');
			if (!text) {
				return;
			}

			var minLen = (finchFormAdmin && finchFormAdmin.subjectMinLength != null) ? finchFormAdmin.subjectMinLength : 10;
			var maxLen = (finchFormAdmin && finchFormAdmin.subjectMaxLength != null) ? finchFormAdmin.subjectMaxLength : 50;
			if (text.length < minLen || text.length > maxLen) {
				setError(finchFormAdmin && finchFormAdmin.subjectLengthError ? finchFormAdmin.subjectLengthError : 'Each subject must be between 10 and 50 characters.');
				return;
			}

			if (currentCount() >= max) {
				updateState();
				return;
			}

			setError('');

			var $item = $('<li class=\"finch-form-subject-item\" />');
			var $textSpan = $('<span class=\"finch-form-subject-text\" />').text(text);
			var $remove = $('<button type=\"button\" class=\"button-link finch-form-subject-remove\" aria-label=\"Remove subject\">×</button>');
			var $hidden = $('<input type=\"hidden\" />')
				.attr('name', 'finch_form_settings[subjects][]')
				.val(text);

			$item.append($textSpan).append(' ').append($remove).append($hidden);
			$list.append($item);

			$input.val('');
			updateState();
		}

		$add.on('click', function (e) {
			e.preventDefault();
			if ($add.prop('disabled')) {
				return;
			}
			addSubject($input.val());
		});

		$input.on('keypress', function (e) {
			if (e.which === 13) {
				e.preventDefault();
				$add.trigger('click');
			}
		});

		$list.on('click', '.finch-form-subject-remove', function (e) {
			e.preventDefault();
			$(this).closest('.finch-form-subject-item').remove();
			updateState();
			setError('');
		});

		updateState();
	}

	$(function () {
		initSubjects();
	});
})(jQuery);

