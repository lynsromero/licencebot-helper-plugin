(function ($) {
	'use strict';

	$(document).ready(function () {
		var $button = $('#ac-fetch-chat-widget');
		if (!$button.length) {
			return;
		}

		var $status = $('#ac-chat-widget-fetch-status');
		var $preview = $('#ac-chat-widget-preview');
		var $result = $('#ac-chat-widget-fetch-result');
		var $embedHtml = $('#ac-chat-widget-embed-html');
		var $embedDisplay = $('#ac-chat-widget-embed-display');

		$button.on('click', function (e) {
			e.preventDefault();

			var nonce = $button.data('nonce');
			var storeId = $button.data('store-id');
			var token = $button.data('token');

			if (!storeId || !token) {
				$status.html('<div class="notice notice-error inline"><p>Store not registered with LicenceBot. Please connect first.</p></div>');
				return;
			}

			$button.prop('disabled', true).text('Fetching...');
			$status.html('<div class="notice notice-info inline"><p>Requesting chat widget from LicenceBot...</p></div>');

			$.ajax({
				url: ajaxurl,
				type: 'POST',
				data: {
					action: 'ac_fetch_chat_widget',
					security: nonce
				},
				success: function (response) {
					if (response.success) {
						var html = response.data.embed_html;

						$status.html('<div class="notice notice-success inline"><p>Chat widget fetched and enabled successfully!</p></div>');

						$embedHtml.text(html);
						$result.show();

						$embedDisplay.text(html);
						$preview.show();

						$('#ac_chat_widget_enabled').prop('checked', true);

						var endpointField = $('input[name="ac_chat_widget_endpoint"]');
						if (endpointField.length && response.data.endpoint_url) {
							endpointField.val(response.data.endpoint_url);
						}
					} else {
						var msg = response.data && response.data.msg ? response.data.msg : 'Unknown error.';
						$status.html('<div class="notice notice-error inline"><p>' + msg + '</p></div>');
					}
				},
				error: function (xhr, status, error) {
					$status.html('<div class="notice notice-error inline"><p>Request failed: ' + error + '</p></div>');
				},
				complete: function () {
					$button.prop('disabled', false).text('Add Chat Widget');
				}
			});
		});
	});
})(jQuery);
