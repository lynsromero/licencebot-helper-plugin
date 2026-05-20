(function ($) {
	'use strict';

	$(document).on('click', '#ac-check-updates', function (e) {
		e.preventDefault();
		var $btn = $(this);
		var $status = $('#ac-update-status');

		$btn.prop('disabled', true).text('Checking...');
		$status.html('<span class="ac-feature-status ac-status-info">Checking for updates...</span>');

		$.ajax({
			url: acHelperUpdates.ajaxurl,
			type: 'POST',
			data: {
				action: 'ac_check_for_updates',
				security: acHelperUpdates.nonce
			},
			success: function (response) {
				if (response.success) {
					$('#ac-update-section').replaceWith(response.data.html);
				} else {
					var msg = response.data && response.data.msg ? response.data.msg : 'Unknown error.';
					$status.html('<span class="ac-feature-status ac-status-error">' + msg + '</span>');
					$btn.prop('disabled', false).text('Check for Updates');
				}
			},
			error: function (xhr, status, error) {
				$status.html('<span class="ac-feature-status ac-status-error">Request failed: ' + error + '</span>');
				$btn.prop('disabled', false).text('Check for Updates');
			}
		});
	});
})(jQuery);
