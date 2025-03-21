( function($) {
	"use strict";

	/**
	 * Joinotify settings params
	 * 
	 * @since 1.1.0
	 * @return Object
	 */
	const params = joinotify_params;

	/**
	 * Joinotify settings object variable
	 * 
	 * @since 1.1.0
	 * @package MeuMouse.com
	 */
	const Settings = {
		init: function() {
			this.activateTabs();
			this.hideToasts();
			this.saveOptions();
			this.getPhoneNumbers();
			this.removePhoneSender();
			this.resetSettings();
			this.sendMessageTest();
			this.proxyApiSettings();
			this.handleDebugLogs();
			this.displayWooCommerceSettings();
		},

		/**
		 * Activate tabs and save on Cookies
		 * 
		 * @since 1.0.0
		 * @version 1.1.0
		 */
		activateTabs: function() {
			$(document).ready( function() {
				let url_hash = window.location.hash;
				let active_tab_index = localStorage.getItem('joinotify_get_admin_tab_index');
		
				if (url_hash) {
					let target_tab = $('.joinotify-wrapper a.nav-tab[href="' + url_hash + '"]');
		
					if (target_tab.length) {
						target_tab.click();
					}
				} else if (active_tab_index !== null) {
					$('.joinotify-wrapper a.nav-tab').eq(active_tab_index).click();
				} else {
					$('.joinotify-wrapper a.nav-tab[href="#general"]').click();
				}
			});
	  
			// Activate tab on click
			$(document).on('click', '.joinotify-wrapper a.nav-tab', function() {
				 let tab_index = $(this).index();
				 localStorage.setItem('joinotify_get_admin_tab_index', tab_index);
				 let attr_href = $(this).attr('href');
	  
				 $('.joinotify-wrapper a.nav-tab').removeClass('nav-tab-active');
				 $('.joinotify-form .nav-content').removeClass('active');
				 $(this).addClass('nav-tab-active');
				 $('.joinotify-form').find(attr_href).addClass('active');
	  
				 return false;
			});
	  	},	  

		/**
		 * Hide toasts
		 * 
		 * @since 1.0.0
		 * @version 1.1.0
		 */
		hideToasts: function() {
			$(document).on('click', '.hide-toast', function() {
				var toast_id = $('.toast.show').attr('id');

				$('#' + toast_id).fadeOut('fast');
		
				// Remove toast from DOM
				setTimeout( function() {
					$('#' + toast_id).remove();
				}, 500);
			});
		},

		/**
		 * Save options in AJAX
		 * 
		 * @since 1.0.0
		 * @version 1.1.0
		 * @package MeuMouse.com
		 */
		saveOptions: function() {
			let settings_form = $('form[name="joinotify-options-form"]');
			let original_values = settings_form.serialize();
			var notification_delay;

			// save options on click button
			$('#joinotify_save_options').on('click', function(e) {
				e.preventDefault();
				
				let btn = $(this);
				let btn_html = btn.html();
				let btn_width = btn.width();
				let btn_height = btn.height();

				btn.width(btn_width);
				btn.height(btn_height);

				// send request
				$.ajax({
					url: params.ajax_url,
					type: 'POST',
					data: {
						action: 'joinotify_save_options',
						form_data: settings_form.serialize(),
					},
					beforeSend: function() {
						btn.prop('disabled', true).html('<span class="spinner-border spinner-border-sm"></span>');
					},
					success: function(response) {
						if (params.debug_mode) {
							console.log(response);
						}

						try {
							if (response.status === 'success') {
								original_values = settings_form.serialize();
								display_toast('success', response.toast_header_title, response.toast_body_title, $('.joinotify-wrapper'));

								if (notification_delay) {
									clearTimeout(notification_delay);
								}

								notification_delay = setTimeout(function() {
									$('.toast-save-options').fadeOut('fast', function() {
											$('.toast-save-options').remove();
									});
								}, 3000);
							}
						} catch (error) {
							console.log(error);
						}
					},
					error: function(jqXHR, textStatus, errorThrown) {
						console.error('AJAX Error:', textStatus, errorThrown);
					},
					complete: function() {
						btn.html(btn_html);
					},
				});
			});

			// Activate save button on change options
			settings_form.on('change input', 'input, select, textarea', function() {
				if (settings_form.serialize() !== original_values) {
					$('#joinotify_save_options').prop('disabled', false);
				} else {
					$('#joinotify_save_options').prop('disabled', true);
				}
			});
		},

		/**
		 * Get phone numbers
		 * 
		 * @since 1.0.0
		 * @version 1.1.0
		 */
		getPhoneNumbers: function() {
			// display modal
			display_popup( $('#joinotify_add_new_phone_trigger'), $('#joinotify_add_new_phone_container'), $('#joinotify_add_new_phone_close') );

			// send request on click button
			$(document).on('click', '#joinotify_add_new_phone_trigger', function() {
				$.ajax({
					url: params.ajax_url,
					method: 'POST',
					data: {
						action: 'joinotify_get_phone_numbers',
					},
					beforeSend: function() {
						$('#joinotify_add_new_phone_container').find('.joinotify-popup-body').html('<div class="placeholder-content" style="width: 100%; height: 5rem;"></div>');
					},
					success: function(response) {
						if (params.debug_mode) {
								console.log(response);
						}

						try {
							if (response.status === 'success') {
								if (response.empty_phone_message) {
									$('#joinotify_add_new_phone_container').find('.joinotify-popup-body').html('<div class="alert alert-info">' + response.empty_phone_message + '</div>');
								} else if (response.phone_numbers_html) {
									$('#joinotify_add_new_phone_container').find('.joinotify-popup-body').html(response.phone_numbers_html);
								}
							}
						} catch (error) {
							console.log(error);
						}
					},
					error: function(xhr, status, error) {
						console.error('Error on AJAX request:', xhr.responseText);
					},
				});
			});

			function start_otp_countdown() {
				var countdown_element = $('.countdown-otp-resend');
				var phone = $('.validate-otp-code').data('phone');
				var time_left = 60;

				countdown_element.text(time_left);

				var countdown_interval = setInterval( function() {
					time_left--;
					countdown_element.text(time_left);

					if (time_left <= 0) {
						clearInterval(countdown_interval);
						$('#joinotify_add_new_phone_container').find('.resend-otp').html('<button class="btn btn-sm btn-outline-primary request-new-otp" data-phone="' + phone + '">' + params.resend_otp_button + '</button>');
					}
				}, 1000);
			}

			function handle_otp_input() {
				$('.otp-input-item').on('input', function() {
					var $this = $(this);

					if ($this.val().length === 1) {
						$this.next('.otp-input-item').focus();
					}

					if ($('.otp-input-item').filter(function() {
						return $(this).val() === '';
					}).length === 0) {
						validate_otp_code();
					}
				});

				$('.otp-input-item').on('paste', function(e) {
					var pasted_data = e.originalEvent.clipboardData.getData('text');

					if (pasted_data.length === 4 && $.isNumeric(pasted_data)) {
						$('.otp-input-item').each(function(index) {
							$(this).val(pasted_data[index]);
						});

						validate_otp_code();
					}
				});
			}

			function validate_otp_code() {
				var phone = $('.validate-otp-code').data('phone');
				var otp_code = '';

				$('.otp-input-item').each(function() {
					otp_code += $(this).val();
				});

				// send request
				$.ajax({
					url: params.ajax_url,
					method: 'POST',
					data: {
						action: 'joinotify_validate_otp',
						phone: phone,
						otp: otp_code,
					},
					beforeSend: function() {
						$('.validate-otp-code').prepend('<div class="validating-otp-loader"><span class="spinner-border"></span></div>');
					},
					success: function(response) {
						if (params.debug_mode) {
							console.log(response);
						}

						if (response.status === 'success') {
							$('#joinotify_current_phones_senders').html(response.current_phone_senders);
							$('#joinotify_add_new_phone_container').removeClass('show');

							display_toast('success', response.toast_header_title, response.toast_body_title, $('.joinotify-wrapper'));
						} else {
							display_toast('error', response.toast_header_title, response.toast_body_title, $('.joinotify-wrapper'));
						}
					},
					error: function(xhr, status, error) {
						console.error('Error on OTP validation request:', xhr.responseText);
					},
				});
			}

			$(document).on('click', '.register-sender, .request-new-otp', function(e) {
				e.preventDefault();

				var btn = $(this);
				var btn_width = btn.width();
				var btn_height = btn.height();
				var btn_html = btn.html();
				var get_phone = btn.data('phone');

				btn.width(btn_width);
				btn.height(btn_height);

				$.ajax({
					url: params.ajax_url,
					method: 'POST',
					data: {
						action: 'joinotify_register_phone_sender',
						phone: get_phone,
					},
					beforeSend: function() {
						btn.prop('disabled', true).html('<span class="spinner-border spinner-border-sm"></span>');
					},
					success: function(response) {
						if (params.debug_mode) {
							console.log(response);
						}

						try {
							if (response.status === 'success') {
								$('#joinotify_add_new_phone_container').find('.joinotify-popup-body').html(response.otp_input_component);
								start_otp_countdown();
								handle_otp_input();
							} else {
								display_toast('error', response.toast_header_title, response.toast_body_title, $('.joinotify-wrapper'));
							}
						} catch (error) {
							console.log(error);
						}
					},
					error: function(xhr, status, error) {
						console.error('Error on AJAX request:', xhr.responseText);
					},
					complete: function() {
						btn.prop('disabled', false).html(btn_html);
					},
				});
			});
		},

		/**
		 * Remove phone sender
		 * 
		 * @since 1.0.0
		 * @version 1.1.0
		 */
		removePhoneSender: function() {
			$(document).on('click', '.remove-phone-sender', function(e) {
				e.preventDefault();

				if ( ! confirm(params.confirm_remove_sender) ) {
					return;
				}

				var btn = $(this);
				var btn_width = btn.width();
				var btn_height = btn.height();
				var btn_html = btn.html();
				var get_phone = btn.data('phone');

				btn.width(btn_width);
				btn.height(btn_height);

				// send request
				$.ajax({
					url: params.ajax_url,
					method: 'POST',
					data: {
						action: 'joinotify_remove_phone_sender',
						phone: get_phone,
					},
					beforeSend: function() {
						btn.prop('disabled', true).html('<span class="spinner-border spinner-border-sm"></span>');
					},
					success: function(response) {
						if (params.debug_mode) {
							console.log(response);
						}

						try {
							if (response.status === 'success') {
								$('#joinotify_current_phones_senders').find('.joinotify-phone-list').html(response.updated_list_html);
								display_toast('success', response.toast_header_title, response.toast_body_title, $('.joinotify-wrapper'));
							} else {
								display_toast('error', response.toast_header_title, response.toast_body_title, $('.joinotify-wrapper'));
							}
						} catch (error) {
							console.log(error);
						}
					},
					error: function(xhr, status, error) {
						console.error('Error on AJAX request:', xhr.responseText);
					},
					complete: function() {
						btn.prop('disabled', false).html(btn_html);
					},
				});
			});
		},

		/**
		 * Reset plugin settings
		 * 
		 * @since 1.0.0
		 * @version 1.1.0
		 */
		resetSettings: function() {
			// display reset modal
			display_popup( $('#joinotify_reset_settings_trigger'), $('#joinotify_reset_settings_container'), $('#joinotify_reset_settings_close') );
				
			// Reset plugin settings
			$(document).on('click', '#confirm_reset_settings', function(e) {
				e.preventDefault();
				
				let btn = $(this);
				let btn_html = btn.html();
				let btn_width = btn.width();
				let btn_height = btn.height();

				btn.width(btn_width);
				btn.height(btn_height);

				// send request
				$.ajax({
					url: params.ajax_url,
					type: 'POST',
					data: {
						action: 'joinotify_reset_plugin_action',
					},
					beforeSend: function() {
						btn.prop('disabled', true).html('<span class="spinner-border spinner-border-sm"></span>');
					},
					success: function(response) {
						try {
							if (response.status === 'success') {
								$('#joinotify_reset_settings_container').removeClass('show');
								display_toast('success', response.toast_header_title, response.toast_body_title, $('.joinotify-wrapper'));
							} else {
								display_toast('error', response.toast_header_title, response.toast_body_title, $('.joinotify-wrapper'));
							}
						} catch (error) {
							console.log(error);
						}
					},
					error: function(xhr, status, error) {
						console.error('Error on AJAX request:', xhr.responseText);
					},
					complete: function() {
						btn.prop('disabled', false).html(btn_html);
					},
				});
			});
		},

		/**
		 * Send message test
		 * 
		 * @since 1.0.0
		 * @version 1.1.0
		 */
		sendMessageTest: function() {
			// display modal
			display_popup( $('#joinotify_send_message_test_trigger'), $('#joinotify_send_message_test_container'), $('#joinotify_send_message_test_close') );

			var get_receiver = $('#joinotify_get_phone_receive_test');
			var get_message = $('#joinotify_get_test_message');

			function check_send_test_inputs() {
				if ($(get_receiver).val() !== '' && $(get_message).val() !== '') {
					$('#joinotify_send_test_message').prop('disabled', false);
				} else {
					$('#joinotify_send_test_message').prop('disabled', true);
				}
			}

			// check inputs on change
			$(document).on('change input keyup', $(get_receiver, get_message), function() {
				check_send_test_inputs();
			});

			// send test message
			$(document).on('click', '#joinotify_send_test_message', function(e) {
				e.preventDefault();
				
				let btn = $(this);
				let btn_html = btn.html();
				let btn_width = btn.width();
				let btn_height = btn.height();

				btn.width(btn_width);
				btn.height(btn_height);

				$.ajax({
					url: params.ajax_url,
					method: 'POST',
					data: {
						action: 'joinotify_send_message_test',
						sender: $('#joinotify_select_sender_test').val(),
						receiver: $(get_receiver).val(),
						message: $(get_message).val(),
					},
					beforeSend: function() {
						btn.prop('disabled', true).html('<span class="spinner-border spinner-border-sm"></span>');
					},
					success: function(response) {
						if (params.debug_mode) {
							console.log(response);
						}

						try {
							if (response.status === 'success') {
								$(get_message).val('').change();
								$('#joinotify_send_message_test_container').removeClass('show');
								
								display_toast('success', response.toast_header_title, response.toast_body_title, $('.joinotify-wrapper'));
							} else {
								display_toast('error', response.toast_header_title, response.toast_body_title, $('.joinotify-wrapper'));
							}
						} catch (error) {
							console.log(error);
						}
					},
					error: function(xhr, status, error) {
						console.error('Error on AJAX request:', xhr.responseText);
					},
					complete: function() {
						btn.prop('disabled', false).html(btn_html);
					},
				});
			});
		},

		/**
		 * Proxy API settings
		 * 
		 * @since 1.0.0
		 */
		proxyApiSettings: function() {
			// change trigger modal visibility
			visibility_controller( $('#enable_proxy_api'), $('.require-proxy-api') );

			// display modal
			display_popup( $('#proxy_api_settings_trigger'), $('#proxy_api_settings_container'), $('#proxy_api_settings_close') );

			// generate API key on click button
			$('#joinotify_generate_proxy_api_key').on('click', function(e) {
				e.preventDefault();
				
				function generate_api_key(length) {
					const characters = 'ABCDEFGHIJKLMNOPQRSTUVWXYZabcdefghijklmnopqrstuvwxyz0123456789';
					let api_key = '';

					for (let i = 0; i < length; i++) {
						api_key += characters.charAt(Math.floor(Math.random() * characters.length));
					}

					return api_key;
				}

				const api_key = generate_api_key(32);
				$('#proxy_api_key').val(api_key).change();
			});
		},

		/**
		 * Handle with debug logs
		 * 
		 * @since 1.1.0
		 */
		handleDebugLogs: function() {
			// change trigger modal visibility
			visibility_controller( $('#enable_debug_mode'), $('.require-debug-mode') );

			// display modal
			display_popup( $('#joinotify_get_debug_details_trigger'), $('#joinotify_get_debug_details_container'), $('#joinotify_get_debug_details_close') );

			$('#joinotify_get_debug_details_trigger').on('click', function(e) {
				e.preventDefault();

				$.ajax({
					url: params.ajax_url,
					method: 'POST',
					data: {
						action: 'joinotify_get_debug_logs',
					},
					success: function(response) {
						if (params.debug_mode) {
							console.log(response);
						}

						try {
							if (response.status === 'success') {
								$('#joinotify_get_debug_details_container').find('.joinotify-popup-body').html(response.log_content);
								$('#joinotify_clear_log_file').prop('disabled', false);
								$('#joinotify_download_log_file').prop('disabled', false);
							} else {
								$('#joinotify_get_debug_details_container').find('.joinotify-popup-body').html(`<div class="alert alert-info">${response.toast_body_title}</div>`);
								display_toast('warning', response.toast_header_title, response.toast_body_title, $('.joinotify-wrapper'));
							}
						} catch (error) {
							console.log(error);
						}
					},
					error: function(xhr, status, error) {
						console.error('Error on AJAX request:', xhr.responseText);
					},
				});
			});

			$('#joinotify_get_debug_details_container').on('click', function(e) {
				if (e.target === this) {
					$('#joinotify_get_debug_details_container').find('.joinotify-popup-body').html('<div class="placeholder-content" style="width: 100%; height: 10rem;"></div>');
					$('#joinotify_clear_log_file').prop('disabled', true);
					$('#joinotify_download_log_file').prop('disabled', true);
				}
			});

			// clear debug logs
			$('#joinotify_clear_log_file').on('click', function(e) {
				e.preventDefault();

				if ( ! confirm(params.confirm_clear_debug_logs) ) {
					return;
				}

				var btn = $(this);
				var btn_width = btn.width();
				var btn_height = btn.height();
				var btn_html = btn.html();

				btn.width(btn_width);
				btn.height(btn_height);

				// send AJAX request
				$.ajax({
					url: params.ajax_url,
					method: 'POST',
					data: {
						action: 'joinotify_clear_debug_logs',
					},
					beforeSend: function() {
						btn.prop('disabled', true).html('<span class="spinner-border spinner-border-sm"></span>');
					},
					success: function(response) {
						if (params.debug_mode) {
							console.log(response);
						}

						try {
							if (response.status === 'success') {
								$('#joinotify_get_debug_details_container').removeClass('show').find('.joinotify-popup-body').html('<div class="placeholder-content" style="width: 100%; height: 10rem;"></div>');
								display_toast('success', response.toast_header_title, response.toast_body_title, $('.joinotify-wrapper'));
							} else {
								display_toast('error', response.toast_header_title, response.toast_body_title, $('.joinotify-wrapper'));
							}
						} catch (error) {
							console.log(error);
						}
					},
					error: function(xhr, status, error) {
						console.error('Error on AJAX request:', xhr.responseText);
					},
					complete: function() {
						btn.prop('disabled', false).html(btn_html);
					},
				});
			});

			// download debug logs
			$('#joinotify_download_log_file').on('click', function(e) {
				e.preventDefault();

				var btn = $(this);
				var btn_width = btn.width();
				var btn_height = btn.height();
				var btn_html = btn.html();

				btn.width(btn_width);
				btn.height(btn_height);

				// send AJAX request
				$.ajax({
					url: params.ajax_url,
					method: 'POST',
					data: {
						action: 'joinotify_download_debug_logs',
					},
					beforeSend: function() {
						btn.prop('disabled', true).html('<span class="spinner-border spinner-border-sm"></span>');
					},
					success: function(response) {
						if (params.debug_mode) {
							console.log(response);
						}

						try {
							if (response.status === 'success') {
								$('#joinotify_get_debug_details_container').removeClass('show');
								display_toast('success', response.toast_header_title, response.toast_body_title, $('.joinotify-wrapper'));

								const download_url = response.download_url;
								const a = document.createElement('a');
								a.href = download_url;
								a.download = 'joinotify-debug-logs.txt';
								document.body.appendChild(a);
								a.click();
								document.body.removeChild(a);
							} else {
								display_toast('error', response.toast_header_title, response.toast_body_title, $('.joinotify-wrapper'));
							}
						} catch (error) {
							console.log(error);
						}
					},
					error: function(xhr, status, error) {
						console.error('Error on AJAX request:', xhr.responseText);
					},
					complete: function() {
						btn.prop('disabled', false).html(btn_html);
					},
				});
			});
		},

		/**
		* Display WooCommerce integration settings
		* 
		* @since 1.1.0
		*/
		displayWooCommerceSettings: function() {
			// change trigger modal visibility
			visibility_controller( $('#enable_woocommerce_integration'), $('#woocommerce_settings_trigger') );

			// display modal
			display_popup( $('#woocommerce_settings_trigger'), $('#woocommerce_settings_container'), $('#woocommerce_settings_close') );

			// change visibility for prefix dynamic coupons
			visibility_controller( $('#enable_create_coupon_action'), $('.create-coupon-wrapper') );
		}
	};

	// Initialize the Settings object on ready event
	jQuery(document).ready( function($) {
		Settings.init();
	});
})(jQuery);