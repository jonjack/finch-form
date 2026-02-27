/* Finch Form – admin subject list management */
(function ($) {
	'use strict';

	function initSubjects() {
		var $wrapper = $('.finch-forms-subjects-wrapper');
		if (!$wrapper.length) {
			return;
		}

		var max = parseInt($wrapper.data('max'), 10) || (finchFormsAdmin && finchFormsAdmin.maxSubjects) || 10;
		var $input = $wrapper.find('.finch-forms-subject-input');
		var $add = $wrapper.find('.finch-forms-subject-add');
		var $list = $wrapper.find('.finch-forms-subject-list');
		var $error = $wrapper.find('.finch-forms-subject-error');

		function setError(msg) {
			if (!msg) {
				$error.text('').hide();
			} else {
				$error.text(msg).show();
			}
		}

		function currentCount() {
			return $list.find('.finch-forms-subject-item').length;
		}

		function updateState() {
			var count = currentCount();
			if (count >= max) {
				$input.prop('disabled', true);
				$add.prop('disabled', true);
				$wrapper.addClass('finch-forms-subjects-full');
			} else {
				$input.prop('disabled', false);
				$add.prop('disabled', false);
				$wrapper.removeClass('finch-forms-subjects-full');
			}
		}

		function addSubject(text) {
			text = $.trim(text || '');
			if (!text) {
				return;
			}

			if (text.length < 10 || text.length > 100) {
				setError(finchFormsAdmin && finchFormsAdmin.subjectLengthError ? finchFormsAdmin.subjectLengthError : 'Each subject must be between 10 and 100 characters.');
				return;
			}

			if (currentCount() >= max) {
				updateState();
				return;
			}

			setError('');

			var $item = $('<li class=\"finch-forms-subject-item\" />');
			var $textSpan = $('<span class=\"finch-forms-subject-text\" />').text(text);
			var $remove = $('<button type=\"button\" class=\"button-link finch-forms-subject-remove\" aria-label=\"Remove subject\">×</button>');
			var $hidden = $('<input type=\"hidden\" />')
				.attr('name', 'finch_forms_settings[subjects][]')
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

		$list.on('click', '.finch-forms-subject-remove', function (e) {
			e.preventDefault();
			$(this).closest('.finch-forms-subject-item').remove();
			updateState();
			setError('');
		});

		updateState();
	}

	$(function () {
		initSubjects();
	});
})(jQuery);

