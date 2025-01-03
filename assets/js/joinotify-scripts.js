( function($) {
    "use strict";

	/**
	 * Activate tabs and save on Cookies
	 * 
	 * @since 1.0.0
	 */
	jQuery(document).ready( function($) {
		// Checks if there is a hash in the URL
		let url_hash = window.location.hash;
		let active_tab_index = localStorage.getItem('joinotify_get_admin_tab_index');

		if (url_hash) {
			// If there is a hash in the URL, activate the corresponding tab
			let target_tab = $('.joinotify-wrapper a.nav-tab[href="' + url_hash + '"]');

			if (target_tab.length) {
				target_tab.click();
			}
		} else if (active_tab_index !== null) {
			// If there is no hash, activate the saved tab in localStorage
			$('.joinotify-wrapper a.nav-tab').eq(active_tab_index).click();
		} else {
			// If there is no hash and localStorage is null, activate the general tab
			$('.joinotify-wrapper a.nav-tab[href="#general"]').click();
		}
	});

    /**
     * Activate tab on click
     * 
     * @since 1.0.0
     */
	$(document).on('click', '.joinotify-wrapper a.nav-tab', function() {
		// Stores the index of the active tab in localStorage
		let tab_index = $(this).index();
		
		localStorage.setItem('joinotify_get_admin_tab_index', tab_index);

		let attr_href = $(this).attr('href');

		$('.joinotify-wrapper a.nav-tab').removeClass('nav-tab-active');
		$('.joinotify-form .nav-content').removeClass('active');
		$(this).addClass('nav-tab-active');
		$('.joinotify-form').find(attr_href).addClass('active');

		return false;
	});


	/**
	 * Hide toasts
	 * 
	 * @since 1.0.0
	 */
	jQuery(document).ready( function($) {
		$(document).on('click', '.hide-toast', function() {
			var toast_id = $('.toast.show').attr('id');

			// fadeout after 3 seconds
			setTimeout( function() {
				$('#' + toast_id).fadeOut('fast');
			}, 3000);
		
			// remove toast after 3,5 seconds
			setTimeout( function() {
				$('#' + toast_id).remove();
			}, 3500);
		});
	});


	/**
	 * Save options in AJAX
	 * 
	 * @since 1.0.0
	 * @package MeuMouse.com
	 */
	jQuery(document).ready( function($) {
		let settings_form = $('form[name="joinotify-options-form"]');
		let original_values = settings_form.serialize();
		var notification_delay;
		var debounce_timeout;
	
		/**
		 * Save options in AJAX
		 * 
		 * @since 1.0.0
		 */
		function ajax_save_options() {
			$.ajax({
				url: joinotify_params.ajax_url,
				type: 'POST',
				data: {
					action: 'joinotify_save_options',
					form_data: settings_form.serialize(),
				},
				success: function(response) {
					if (joinotify_params.debug_mode) {
						console.log(response);
					}

					try {
						if (response.status === 'success') {
							original_values = settings_form.serialize();

							display_toast( 'success', response.toast_header_title, response.toast_body_title, $('.joinotify-wrapper') );
	
							// clear notification time on var
							if (notification_delay) {
								clearTimeout(notification_delay);
							}
	
							// set notification 3 seconds on var
							notification_delay = setTimeout( function() {
								$('.toast-save-options').fadeOut('fast', function() {
									$('.toast-save-options').remove();
								});
							}, 3000);
						}
					} catch (error) {
						console.log(error);
					}
				}
			});
		}
	
		/**
		 * Monitor changes in the form
		 * 
		 * @since 1.0.0
		 */
		settings_form.on('change input', 'input, select, textarea', function() {
			if (settings_form.serialize() !== original_values) {
				if (debounce_timeout) {
					clearTimeout(debounce_timeout);
				}
	
				debounce_timeout = setTimeout(ajax_save_options, 2000); // debounce delay of 2 seconds
			}
		});
	});


	/**
	 * Display popups
	 * 
	 * @since 1.0.0
	 */
	jQuery(document).ready( function($) {
		// reset plugin modal
		display_popup( $('#joinotify_reset_settings_trigger'), $('#joinotify_reset_settings_container'), $('#joinotify_reset_settings_close') );

		// add new phone
		display_popup( $('#joinotify_add_new_phone_trigger'), $('#joinotify_add_new_phone_container'), $('#joinotify_add_new_phone_close') );

		// send message test
		display_popup( $('#joinotify_send_message_test_trigger'), $('#joinotify_send_message_test_container'), $('#joinotify_send_message_test_close') );
	});


	/**
	 * Get phone numbers
	 * 
	 * @since 1.0.0
	 */
	jQuery(document).ready( function($) {
		$(document).on('click', '#joinotify_add_new_phone_trigger', function() {
			$.ajax({
				url: joinotify_params.ajax_url,
				method: 'POST',
				data: {
					action: 'joinotify_get_phone_numbers',
				},
				beforeSend: function() {
					$('#joinotify_add_new_phone_container').find('.joinotify-popup-body').html('<div class="placeholder-content" style="width: 100%; height: 5rem;"></div>');
				},
				success: function(response) {
					if (joinotify_params.debug_mode) {
						console.log(response);
					}
	
					try {
						if ( response.status === 'success' ) {
							if ( response.empty_phone_message ) {
								$('#joinotify_add_new_phone_container').find('.joinotify-popup-body').html('<div class="alert alert-info">'+ response.empty_phone_message +'</div>');
							} else if ( response.phone_numbers_html ) {
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

		// Start countdown when the OTP input component appears
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
					$('#joinotify_add_new_phone_container').find('.resend-otp').html('<button class="btn btn-sm btn-outline-primary request-new-otp" data-phone="'+ phone +'">'+ joinotify_params.resend_otp_button +'</button>');
				}
			}, 1000);
		}
	
		/**
		 * Handle OTP input focus and auto-move to the next input
		 * 
		 * @since 1.0.0
		 */
		function handle_otp_input() {
			$('.otp-input-item').on('input', function () {
				var $this = $(this);

				if ($this.val().length === 1) {
					// Move to next input if exists
					$this.next('.otp-input-item').focus();
				}

				// Trigger OTP submission when all inputs are filled
				if ($('.otp-input-item').filter( function() {
					return $(this).val() === '';
				}).length === 0) {
					validate_otp_code();
				}
			});
	
			// Handle paste event
			$('.otp-input-item').on('paste', function (e) {
				var pasted_data = e.originalEvent.clipboardData.getData('text');

				if (pasted_data.length === 4 && $.isNumeric(pasted_data)) {
					$('.otp-input-item').each(function (index) {
						$(this).val(pasted_data[index]);
					});

					validate_otp_code();
				}
			});
		}
	
		/**
		 * Validate OTP code
		 * 
		 * @since 1.0.0
		 */
		function validate_otp_code() {
			var phone = $('.validate-otp-code').data('phone');
			var otp_code = '';

			$('.otp-input-item').each( function() {
				otp_code += $(this).val();
			});
	
			$.ajax({
				url: joinotify_params.ajax_url,
				method: 'POST',
				data: {
					action: 'joinotify_validate_otp',
					phone: phone,
					otp: otp_code,
				},
				beforeSend: function () {
					$('.validate-otp-code').prepend('<div class="validating-otp-loader"><span class="spinner-border"></span></div>');
				},
				success: function (response) {
					if (joinotify_params.debug_mode) {
						console.log(response);
					}
	
					if ( response.status === 'success' ) {
						$('#joinotify_current_phones_senders').html(response.current_phone_senders);
						$('#joinotify_add_new_phone_container').removeClass('show');

						display_toast('success', response.toast_header_title, response.toast_body_title, $('.joinotify-wrapper'));
					} else {
						display_toast('error', response.toast_header_title, response.toast_body_title, $('.joinotify-wrapper'));
					}
				},
				error: function (xhr, status, error) {
					console.error('Error on OTP validation request:', xhr.responseText);
				},
			});
		}

		// get OTP validation code for register sender
		$(document).on('click', '.register-sender, .request-new-otp', function(e) {
			e.preventDefault();

			var btn = $(this);
			var btn_width = btn.width();
			var btn_height = btn.height();
			var btn_html = btn.html();
			var get_phone = btn.data('phone');

			// keep original width and height
			btn.width(btn_width);
			btn.height(btn_height);

			$.ajax({
				url: joinotify_params.ajax_url,
				method: 'POST',
				data: {
					action: 'joinotify_register_phone_sender',
					phone: get_phone,
				},
				beforeSend: function() {
					btn.prop('disabled', true).html('<span class="spinner-border spinner-border-sm"></span>');
				},
				success: function(response) {
					if (joinotify_params.debug_mode) {
						console.log(response);
					}
	
					try {
						if ( response.status === 'success' ) {
							$('#joinotify_add_new_phone_container').find('.joinotify-popup-body').html(response.otp_input_component);
							start_otp_countdown();
                        	handle_otp_input();
						} else {
							display_toast( 'error', response.toast_header_title, response.toast_body_title, $('.joinotify-wrapper') );
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
	});


	/**
	 * Remove phone sender
	 * 
	 * @since 1.0.0
	 */
	jQuery(document).ready( function($) {
		$(document).on('click', '.remove-phone-sender', function(e) {
			e.preventDefault();

			// get confirmation
			if ( ! confirm(joinotify_params.confirm_remove_sender) ) {
				return;
			}
	
			var btn = $(this);
			var btn_width = btn.width();
			var btn_height = btn.height();
			var btn_html = btn.html();
			var get_phone = btn.data('phone');
	
			// Keep original width and height
			btn.width(btn_width);
			btn.height(btn_height);
	
			// AJAX Request
			$.ajax({
				url: joinotify_params.ajax_url,
				method: 'POST',
				data: {
					action: 'joinotify_remove_phone_sender',
					phone: get_phone,
				},
				beforeSend: function() {
					btn.prop('disabled', true).html('<span class="spinner-border spinner-border-sm"></span>');
				},
				success: function(response) {
					if (joinotify_params.debug_mode) {
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
	});

	
	/**
	 * Display modal reset plugin
	 * 
	 * @since 1.0.0
	 */
	jQuery(document).ready( function($) {
		$(document).on('click', '#confirm_reset_settings', function(e) {
			e.preventDefault();
			
			let btn = $(this);
			let btn_html = btn.html();
			let btn_width = btn.width();
			let btn_height = btn.height();

			// keep original width and height
			btn.width(btn_width);
			btn.height(btn_height);

			$.ajax({
				url: joinotify_params.ajax_url,
				type: 'POST',
				data: {
					action: 'joinotify_reset_plugin_action',
				},
				beforeSend: function() {
					btn.prop('disabled', true).html('<span class="spinner-border spinner-border-sm"></span>');
				},
				success: function(response) {
					try {
						if ( response.status === 'success' ) {
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
	});


	/**
	 * Send message test
	 * 
	 * @since 1.0.0
	 */
	jQuery(document).ready( function($) {
		var get_receiver = $('#joinotify_get_phone_receive_test');
		var get_message = $('#joinotify_get_test_message');

		function check_send_test_inputs() {
			if ( $(get_receiver).val() !== '' && $(get_message).val() !== '' ) {
				$('#joinotify_send_test_message').prop('disabled', false);
			} else {
				$('#joinotify_send_test_message').prop('disabled', true);
			}
		}

		// check if inputs are filled
		$(document).on('change input keyup', $(get_receiver, get_message), function() {
			check_send_test_inputs();
		});

		// send AJAX request
		$(document).on('click', '#joinotify_send_test_message', function(e) {
			e.preventDefault();
			
			let btn = $(this);
			let btn_html = btn.html();
			let btn_width = btn.width();
			let btn_height = btn.height();

			// keep original width and height
			btn.width(btn_width);
			btn.height(btn_height);

			$.ajax({
				url: joinotify_params.ajax_url,
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
					if (joinotify_params.debug_mode) {
						console.log(response);
					}
	
					try {
						if ( response.status === 'success' ) {
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
	});


	/**
	 * Proxy API settings
	 * 
	 * @since 1.0.0
	 */
	jQuery(document).ready( function($) {
		// display trigger modal if proxy is enabled
		visibility_controller( $('#enable_proxy_api'), $('.require-proxy-api') );

		// display settings modal
		display_popup( $('#proxy_api_settings_trigger'), $('#proxy_api_settings_container'), $('#proxy_api_settings_close') );

		// generate api key for proxy api
		$('#joinotify_generate_proxy_api_key').on('click', function(e) {
			e.preventDefault();
			
			/**
			 * Generate random api key
			 * 
			 * @since 1.0.0
			 * @param {int} length | Size api key
			 * @returns 
			 */
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
	});


	/**
	 * Handle with debug logs
	 * 
	 * @since 1.1.0
	 */
	jQuery(document).ready( function($) {
		// display trigger modal if proxy is enabled
		visibility_controller( $('#enable_debug_mode'), $('.require-debug-mode') );

		// display debug details modal
		display_popup( $('#joinotify_get_debug_details_trigger'), $('#joinotify_get_debug_details_container'), $('#joinotify_get_debug_details_close') );

		// get debug log details
		$('#joinotify_get_debug_details_trigger').on('click', function(e) {
			e.preventDefault();

			$.ajax({
				url: joinotify_params.ajax_url,
				method: 'POST',
				data: {
					action: 'joinotify_get_debug_logs',
				},
				success: function(response) {
					if (joinotify_params.debug_mode) {
						console.log(response);
					}
	
					try {
						if ( response.status === 'success' ) {
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

		// return to default
		$('#joinotify_get_debug_details_container').on('click', function(e) {
			if (e.target === this) {
				$('#joinotify_get_debug_details_container').find('.joinotify-popup-body').html('<div class="placeholder-content" style="width: 100%; height: 10rem;"></div>');
				$('#joinotify_clear_log_file').prop('disabled', true);
				$('#joinotify_download_log_file').prop('disabled', true);
			}
		});

		// clear log file
		$('#joinotify_clear_log_file').on('click', function(e) {
			e.preventDefault();

			// get confirmation
			if ( ! confirm(joinotify_params.confirm_clear_debug_logs) ) {
				return;
			}
	
			var btn = $(this);
			var btn_width = btn.width();
			var btn_height = btn.height();
			var btn_html = btn.html();
	
			// Keep original width and height
			btn.width(btn_width);
			btn.height(btn_height);

			$.ajax({
				url: joinotify_params.ajax_url,
				method: 'POST',
				data: {
					action: 'joinotify_clear_debug_logs',
				},
				beforeSend: function() {
					btn.prop('disabled', true).html('<span class="spinner-border spinner-border-sm"></span>');
				},
				success: function(response) {
					if (joinotify_params.debug_mode) {
						console.log(response);
					}
	
					try {
						if ( response.status === 'success' ) {
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

		// download the log file
		$('#joinotify_download_log_file').on('click', function(e) {
			e.preventDefault();

			var btn = $(this);
			var btn_width = btn.width();
			var btn_height = btn.height();
			var btn_html = btn.html();
	
			// Keep original width and height
			btn.width(btn_width);
			btn.height(btn_height);

			$.ajax({
				url: joinotify_params.ajax_url,
				method: 'POST',
				data: {
					action: 'joinotify_download_debug_logs',
				},
				beforeSend: function() {
					btn.prop('disabled', true).html('<span class="spinner-border spinner-border-sm"></span>');
				},
				success: function(response) {
					if (joinotify_params.debug_mode) {
						console.log(response);
					}
	
					try {
						if ( response.status === 'success' ) {
							$('#joinotify_get_debug_details_container').removeClass('show');

							display_toast('success', response.toast_header_title, response.toast_body_title, $('.joinotify-wrapper'));

							// initialize the download creating a temporaty link
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
	});


	/**
	 * Display WooCommerce integration settings
	 * 
	 * @since 1.1.0
	 * @package MeuMouse.com
	 */
	jQuery(document).ready( function(e) {
		// display settings modal
		display_popup( $('#woocommerce_settings_trigger'), $('#woocommerce_settings_container'), $('#woocommerce_settings_container') );
	});
})(jQuery);