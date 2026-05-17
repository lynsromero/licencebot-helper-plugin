/**
 * WC Serial Numbers
 * https://tic.com.bd
 *
 * Copyright (c) 2018 autocircled
 * Licensed under the GPLv2+ license.
 */

(function ($, window) {
	'use strict';
	$.ac_serial_numbers_admin = function () {
		var plugin = this;
		plugin.init = function () {
			plugin.init_select2_mapping('.ac-serial-numbers-map-product', ac_serial_numbers_admin_i10n.remote_data, ac_serial_numbers_admin_i10n.i18n.search_product);
			plugin.init_select2_mapping_from_order_page('.ac-serial-numbers-map-product-shop-order', ac_serial_numbers_admin_i10n.remote_data, ac_serial_numbers_admin_i10n.i18n.search_product);
			plugin.init_select2('.ac-serial-numbers-select-product', 'ac_serial_numbers_search_products', ac_serial_numbers_admin_i10n.i18n.search_product);
			plugin.init_datepicker('.ac-serial-numbers-select-date');
			plugin.save_source('.keysource');
			plugin.save_source_from_order_page('.keysource_order_page');
			plugin.enable_select2_on_product_tab("#_ac_remote_product_id", ac_serial_numbers_admin_i10n.remote_data, ac_serial_numbers_admin_i10n.i18n.search_product);
			plugin.clear_cache(".ac-serial-numbers-settings input[name=flush]");
			plugin.encrypt_decrypt();
			plugin.request_keys('#request-keys-items');
			plugin.sync_order();
		};

		plugin.init_select2_mapping = function (el, data, placeholder) {
			
			placeholder = placeholder || 'Select..';

			$(el).select2({
				data: data,
				placeholder: placeholder,
			});

			$(el).each(function() {
				let tr = $(this).closest('tr');
				const data = tr.attr('data-remote_products_data');
				const old_data = tr.attr('data-remote_product_id');
				
				if (data) {
					let parsedData = JSON.parse(data);
					let selectedIds = parsedData.map(item => item.id);
					$(this).val(selectedIds).trigger('change');
					
				}else if (old_data) {
					$(this).val(old_data).trigger('change');
				}
						
			})
			

			$(el).on('select2:select', function(e) {
				const currentEl = $(this);
				const parentEl = currentEl.parent();
				const parentRow = currentEl.closest('tr');
				var product_id = parentRow.attr('data-product_id');

				const selectedData = e.params.data;
				$.ajax({
					url: ac_serial_numbers_admin_i10n.ajaxurl,
					type: 'POST',
					data: {
						action: 'ac_serial_numbers_update_product_mapping',
						type: 'update',
						remote_product_id: selectedData.id,
						remote_product_title: selectedData.text,
						local_product_id: product_id,
						nonce: ac_serial_numbers_admin_i10n.nonce
					},
					beforeSend: function() {
						parentEl.removeClass('success');
						parentEl.addClass('loading');
						$(currentEl).attr('disabled', 'disabled'); 
						
					},
					success: function(response) {
						parentEl.removeClass('loading');
						$(currentEl).removeAttr('disabled');
						parentEl.addClass('success');
					},
					error: function(xhr, status, error) {
						console.log(error);
					} 
				});
			});
		
			// Event listener for unselect (when cleared)
			$(el).on('select2:unselect', function(e) {
				const currentEl = $(this);
				const parentEl = currentEl.parent();
				const parentRow = currentEl.closest('tr');
				var product_id = parentRow.attr('data-product_id');
				const selectedData = e.params.data;
				$.ajax({
					url: ac_serial_numbers_admin_i10n.ajaxurl,
					type: 'POST',
					data: {
						action: 'ac_serial_numbers_update_product_mapping',
						type: 'clear',
						remote_product_id: selectedData.id,
						local_product_id: product_id,
						nonce: ac_serial_numbers_admin_i10n.nonce
					},
					beforeSend: function() {
						parentEl.removeClass('success');
						parentEl.addClass('loading');
						$(currentEl).attr('disabled', 'disabled'); 
						
					},
					success: function(response) {
						parentEl.removeClass('loading');
						$(currentEl).removeAttr('disabled');
						parentEl.addClass('success');
					},
					error: function(xhr, status, error) {
						console.log(error);
					} 
				});
			});
		};
		plugin.init_select2_mapping_from_order_page = function (el, data, placeholder) {
			
			placeholder = placeholder || 'Select..';

			$(el).select2({
				data: data,
				placeholder: placeholder,
			});

			$(el).each(function() {
				let tr = $(this).closest('tr');
				const data = tr.attr('data-remote_products_data');
				const old_data = tr.attr('data-remote_product_id');
				
				if (data) {
					let parsedData = JSON.parse(data);
					let selectedIds = parsedData.map(item => item.id);
					$(this).val(selectedIds).trigger('change');
					
				}else if (old_data) {
					$(this).val(old_data).trigger('change');
				}
						
			})
			

			$(el).on('select2:select', function(e) {
				const currentEl = $(this);
				const parentEl = currentEl.parent();
				const parentRow = currentEl.closest('tr');
				const selectedDatas = $(this).select2('data'); // Get all selected items as objects
				const selectedItems = selectedDatas.map(item => ({
					id: item.id,
					text: item.text
				}));
				var order_id = parentRow.attr('data-order_id');
				var order_item_id = parentRow.attr('data-order_item_id');
	
				$.ajax({
					url: ac_serial_numbers_admin_i10n.ajaxurl,
					type: 'POST',
					data: {
						action: 'ac_serial_numbers_update_product_mapping_from_shop_order',
						type: 'update',
						data: selectedItems,
						order_id: order_id,
						order_item_id: order_item_id,
						nonce: ac_serial_numbers_admin_i10n.nonce
					},
					beforeSend: function() {
						parentEl.removeClass('success');
						parentEl.addClass('loading');
						$(currentEl).attr('disabled', 'disabled'); 
						
					},
					success: function(response) {
						parentEl.removeClass('loading');
						$(currentEl).removeAttr('disabled');
						parentEl.addClass('success');
						console.log(response);
					},
					error: function(xhr, status, error) {
						console.log(error);
					} 
				});
			});
		
			// Event listener for unselect (when cleared)
			$(el).on('select2:unselect', function(e) {
				const currentEl = $(this);
				const parentEl = currentEl.parent();
				const parentRow = currentEl.closest('tr');
				var order_id = parentRow.attr('data-order_id');
				var order_item_id = parentRow.attr('data-order_item_id');
				const selectedDatas = $(this).select2('data'); // Get all selected items as objects
				const selectedItems = selectedDatas.map(item => ({
					id: item.id,
					text: item.text
				}));
				$.ajax({
					url: ac_serial_numbers_admin_i10n.ajaxurl,
					type: 'POST',
					data: {
						action: 'ac_serial_numbers_update_product_mapping_from_shop_order',
						type: 'clear',
						data: selectedItems,
						order_id: order_id,
						order_item_id: order_item_id,
						nonce: ac_serial_numbers_admin_i10n.nonce
					},
					beforeSend: function() {
						parentEl.removeClass('success');
						parentEl.addClass('loading');
						$(currentEl).attr('disabled', 'disabled'); 
						
					},
					success: function(response) {
						parentEl.removeClass('loading');
						$(currentEl).removeAttr('disabled');
						parentEl.addClass('success');
						console.log(response);
					},
					error: function(xhr, status, error) {
						console.log(error);
					} 
				});
			});
		};

		plugin.init_select2 = function (el, action, placeholder) {
			placeholder = placeholder || 'Select..';
			$(el).select2({
				ajax: {
					cache: true,
					delay: 500,
					url: window.ac_serial_numbers_admin_i10n.ajaxurl,
					method: 'POST',
					dataType: 'json',
					data: function (params) {
						return {
							action: action,
							nonce: window.ac_serial_numbers_admin_i10n.nonce,
							search: params.term,
							page: params.page
						};
					},
					processResults: function (data, params) {
						params.page = params.page || 1;
						return {
							results: data.results,
							pagination: {
								more: data.pagination.more
							}
						};
					}
				},
				placeholder: placeholder,
				minimumInputLength: 1,
				allowClear: true
			});
		};

		plugin.init_datepicker = function (el) {
			$(el).datepicker({
				changeMonth: true,
				changeYear: true,
				dateFormat: 'yy-mm-dd',
				firstDay: 7,
				minDate: new Date()
			});
		};

		plugin.encrypt_decrypt = function () {
			//show decrypted value
			$(document).on('click', '.ac-serial-numbers-decrypt-key', function (e) {
				e.preventDefault();
				var self = $(this);
				var id = self.data('serial-id');
				var nonce = self.data('nonce') || null;
				var td = self.closest('td');
				var code = td.find('.serial-key');
				var spinner = td.find('.serial-spinner');
				spinner.show();
				if (!code.hasClass('encrypted')) {
					code.addClass('encrypted');
					spinner.hide();
					code.text('');
					self.text(ac_serial_numbers_admin_i10n.i18n.show);
					return false;
				}
				wp.ajax.send('ac_serial_numbers_decrypt_key', {
					data: {
						serial_id: id,
						nonce: nonce
					},
					success: function (res) {
						code.text(res.key);
						spinner.hide();
						code.removeClass('encrypted');
						self.text(ac_serial_numbers_admin_i10n.i18n.hide);
					},
					error: function () {
						spinner.hide();
						code.text('');
						code.addClass('encrypted');
						self.text(ac_serial_numbers_admin_i10n.i18n.show);
						alert('Decrypting key failed');
					}
				});

				return false;
			});
		};

		plugin.save_source = function(el){
			$(el).on('change', function(){
				let currentEl = $(this);
				let parentEl = currentEl.parent();
				var key_source = currentEl.val();
				const parentRow = currentEl.closest('tr');
				var product_id = parentRow.attr('data-product_id');
		
				$.ajax({
					url: ac_serial_numbers_admin_i10n.ajaxurl,
					type: 'POST',
					data: {
						action: 'ac_serial_numbers_update_product_key_source',
						key_source: key_source,
						product_id: product_id,
						nonce: ac_serial_numbers_admin_i10n.nonce
					},
					// we need to show loading animation
					beforeSend: function() {
						const isConfirmed = confirm('Are you sure?');
						if (!isConfirmed) {
							return false;
						}
						parentEl.removeClass('success');
						parentEl.addClass('loading');
						$(currentEl).attr('disabled', 'disabled'); 
						
					},
		
					success: function(response) {
						parentEl.removeClass('loading');
						$(currentEl).removeAttr('disabled');
						parentEl.addClass('success');
					},
					error: function(xhr, status, error) {
						console.log(error);
					}            
				});
			});
		};
		plugin.save_source_from_order_page = function(el){
			$(el).on('change', function(){
				let currentEl = $(this);
				let parentEl = currentEl.parent();
				var key_source = currentEl.val();
				const parentRow = currentEl.closest('tr');
				var order_id = parentRow.attr('data-order_id');
				var order_item_id = parentRow.attr('data-order_item_id');
		
				$.ajax({
					url: ac_serial_numbers_admin_i10n.ajaxurl,
					type: 'POST',
					data: {
						action: 'ac_serial_numbers_update_product_key_source_from_order_page',
						key_source: key_source,
						order_id: order_id,
						order_item_id: order_item_id,
						nonce: ac_serial_numbers_admin_i10n.nonce
					},
					// we need to show loading animation
					beforeSend: function() {
						const isConfirmed = confirm('Are you sure?');
						if (!isConfirmed) {
							return false;
						}
						parentEl.removeClass('success');
						parentEl.addClass('loading');
						$(currentEl).attr('disabled', 'disabled'); 
						
					},
		
					success: function(response) {
						parentEl.removeClass('loading');
						$(currentEl).removeAttr('disabled');
						parentEl.addClass('success');
					},
					error: function(xhr, status, error) {
						console.log(error);
					}            
				});
			});
		};

		plugin.enable_select2_on_product_tab = function(el, data, placeholder){
			placeholder = placeholder || 'Select Remote Product';
			$(el).select2({
				data: data,
				placeholder: placeholder,
				allowClear: true,
				width: '90%'
			});

			$(el).on('select2:select', function(e) {
				const selectedData = e.params.data;
				$("#_ac_remote_product_title").val(selectedData.text);
			});
			$(el).on('select2:unselect', function(e) {
				$("#_ac_remote_product_title").val('');
			});
		};

		plugin.clear_cache = function(el){
			$(el).on('click', function(e){
				e.preventDefault();
				const isConfirmed = confirm('Are you sure?');
				if (!isConfirmed) {
					return false;
				}
				$.ajax({
					url: ac_serial_numbers_admin_i10n.ajaxurl,
					type: 'POST',
					data: {
						action: 'ac_serial_numbers_clear_transient_data',
						nonce: ac_serial_numbers_admin_i10n.nonce
					},
					success: function(response) {
						alert(response);
					},
					error: function(xhr, status, error) {
						console.log(error);
					}            
				});
			});
		};

		plugin.request_keys = function(el){

			$(el).on('click', function(){
				const order_id = $(this).attr('data-order_id');
				$.ajax({
					url: ac_serial_numbers_admin_i10n.ajaxurl,
					type: 'POST',
					data: {
						action: 'ac_serial_numbers_request_new_keys',
						order_id,
						nonce: ac_serial_numbers_admin_i10n.nonce
					},
					// we need to show loading animation
					beforeSend: function() {
						const isConfirmed = confirm('Are you sure?');
						if (!isConfirmed) {
							return false;
						}
						console.log("beforeSend");
						// parentEl.removeClass('success');
						// parentEl.addClass('loading');
						// $(currentEl).attr('disabled', 'disabled'); 
						
					},
		
					success: function(response) {
						// parentEl.removeClass('loading');
						// $(currentEl).removeAttr('disabled');
						// parentEl.addClass('success');
						console.log("success", response);
					},
					error: function(xhr, status, error) {
						console.log(error);
					}
				});
			});
		}

		plugin.sync_order = function(){
			$(document).on('click', '.acsn-sync-order', function(){
				var $btn = $(this);
				var order_id = $btn.data('order-id');
				var originalText = $btn.text();

				if (!confirm('Sync license keys from LicenceBot for this order?')) {
					return;
				}

				$btn.prop('disabled', true).text('Syncing...');

				$.ajax({
					url: ac_serial_numbers_admin_i10n.ajaxurl,
					type: 'POST',
					data: {
						action: 'ac_serial_numbers_sync_order',
						order_id: order_id,
						nonce: ac_serial_numbers_admin_i10n.nonce
					},
					success: function(response) {
						if (response.success) {
							alert(response.data.message);
							location.reload();
						} else {
							alert(response.data && response.data.message ? response.data.message : 'Sync failed.');
							$btn.prop('disabled', false).text(originalText);
						}
					},
					error: function() {
						alert('Request failed. Please try again.');
						$btn.prop('disabled', false).text(originalText);
					}
				});
			});
		}

		plugin.init();
	};

	$.fn.ac_serial_numbers_admin = function () {
		return new $.ac_serial_numbers_admin();
	};

	$.ac_serial_numbers_admin();
})(jQuery, window);
