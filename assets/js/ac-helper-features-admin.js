(function ($) {
	'use strict';

	$(document).ready(function () {
		var $buttons = $('.ac-fetch-feature');
		if (!$buttons.length) return;

		$buttons.on('click', function (e) {
			e.preventDefault();
			var $btn = $(this);
			var slug = $btn.data('feature');

			var $status = $('#ac-' + slug + '-fetch-status');
			var $textarea = $('#ac-' + slug + '-code-display');

			$btn.prop('disabled', true).text('Fetching...');
			$status.html('<span class="ac-feature-status ac-status-info">Requesting from LicenceBot...</span>');

			$.ajax({
				url: acHelperFeatures.ajaxurl,
				type: 'POST',
				data: {
					action: 'ac_fetch_helper_feature',
					feature: slug,
					security: acHelperFeatures.nonce
				},
				success: function (response) {
					if (response.success) {
						var html = response.data.embed_html;
						$textarea.val(html);
						$status.html('<span class="ac-feature-status ac-status-success">Code fetched successfully! Click "Save Changes" to persist.</span>');
					} else {
						var msg = response.data && response.data.msg ? response.data.msg : 'Unknown error.';
						$status.html('<span class="ac-feature-status ac-status-error">' + msg + '</span>');
					}
				},
				error: function (xhr, status, error) {
					$status.html('<span class="ac-feature-status ac-status-error">Request failed: ' + error + '</span>');
				},
				complete: function () {
					$btn.prop('disabled', false).text('Fetch Code');
				}
			});
		});

		$('.ac-helper-feature-card').each(function () {
			var $card = $(this);
			var $btn = $card.find('.ac-fetch-feature');
			var slug = $btn.data('feature');
			var $textarea = $('#ac-' + slug + '-code-display');
			if ($textarea.length && $textarea.val() === '') {
				$btn.trigger('click');
			}
		});
	});
})(jQuery);
