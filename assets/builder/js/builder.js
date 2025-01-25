( function($) {
    "use strict";

    /**
	 * Builder tabs
	 * 
	 * @since 1.0.0
	 */
	jQuery(document).ready( function($) {
        // Activate first builder tab on load page
		setTimeout( function() {
			$('.joinotify-triggers-tab-wrapper a.nav-tab').first().click();
		}, 500);

        // Activate builder tab on click
        $(document).on('click', '.joinotify-triggers-tab-wrapper a.nav-tab', function() {
            let attr_href = $(this).attr('href');

            $('.joinotify-triggers-tab-wrapper a.nav-tab').removeClass('nav-tab-active');
            $('.triggers-content-wrapper .nav-content').removeClass('active');
            $(this).addClass('nav-tab-active');
            $('.triggers-content-wrapper').find(attr_href).addClass('active');
        });
	});


    /**
	 * Upload template process
	 * 
	 * @since 1.0.0
	 */
	jQuery(document).ready( function($) {
		// Add event handlers for dragover and dragleave
		$('#joinotify_import_file_zone').on('dragover dragleave', function(e) {
			e.preventDefault();
			
			$(this).toggleClass('drag-over', e.type === 'dragover');
		});
	
		// Add event handlers for drop
		$('#joinotify_import_file_zone').on('drop', function(e) {
			e.preventDefault();
	
			var file = e.originalEvent.dataTransfer.files[0];

			if ( ! $(this).hasClass('file-uploaded') ) {
				handle_file(file, $(this));
			}
		});
	
		// Adds a change event handler to the input file
		$('#upload_template_file').on('change', function(e) {
			var file = e.target.files[0];

			handle_file(file, $(this).parents('.dropzone-license'));
		});
	
		/**
		 * Handle sent file
		 * 
		 * @since 1.0.0
		 * @param {string} file | File
		 * @param {string} dropzone | Dropzone div
		 * @returns void
		 */
		function handle_file(file, dropzone) {
			if (file) {
				var filename = file.name;
				var hook_toast = $('.woo-custom-installments-wrapper');

				var formData = new FormData();
				formData.append('action', 'wci_alternative_activation_license');
				formData.append('file', file);

				dropzone.children('.file-list').removeClass('d-none').text(filename);
				dropzone.addClass('file-processing');
				dropzone.append('<div class="spinner-border"></div>');
	
				$.ajax({
					url: wci_params.ajax_url,
					type: 'POST',
					data: formData,
					processData: false,
					contentType: false,
					success: function(response) {
						if ( joinotify_builder_params.debug_mode ) {
							console.log(response);
						}

						try {
							if (response.status === 'success') {
								hook_toast.before(`<div id="toast_success_alternative_license" class="toast toast-success show">
									<div class="toast-header bg-success text-white">
										<svg class="woo-custom-installments-toast-check-icon" viewBox="0 0 24 24" fill="none" xmlns="http://www.w3.org/2000/svg" stroke="#ffffff"><g stroke-width="0"/><g stroke-linecap="round" stroke-linejoin="round"/><g><path d="M10.5 15.25C10.307 15.2353 10.1276 15.1455 9.99998 15L6.99998 12C6.93314 11.8601 6.91133 11.7029 6.93756 11.55C6.96379 11.3971 7.03676 11.2562 7.14643 11.1465C7.2561 11.0368 7.39707 10.9638 7.54993 10.9376C7.70279 10.9114 7.86003 10.9332 7.99998 11L10.47 13.47L19 5.00004C19.1399 4.9332 19.2972 4.91139 19.45 4.93762C19.6029 4.96385 19.7439 5.03682 19.8535 5.14649C19.9632 5.25616 20.0362 5.39713 20.0624 5.54999C20.0886 5.70286 20.0668 5.86009 20 6.00004L11 15C10.8724 15.1455 10.6929 15.2353 10.5 15.25Z" fill="#ffffff"/> <path d="M12 21C10.3915 20.9974 8.813 20.5638 7.42891 19.7443C6.04481 18.9247 4.90566 17.7492 4.12999 16.34C3.54037 15.29 3.17596 14.1287 3.05999 12.93C2.87697 11.1721 3.2156 9.39921 4.03363 7.83249C4.85167 6.26578 6.1129 4.9746 7.65999 4.12003C8.71001 3.53041 9.87134 3.166 11.07 3.05003C12.2641 2.92157 13.4719 3.03725 14.62 3.39003C14.7224 3.4105 14.8195 3.45215 14.9049 3.51232C14.9903 3.57248 15.0622 3.64983 15.116 3.73941C15.1698 3.82898 15.2043 3.92881 15.2173 4.03249C15.2302 4.13616 15.2214 4.2414 15.1913 4.34146C15.1612 4.44152 15.1105 4.53419 15.0425 4.61352C14.9745 4.69286 14.8907 4.75712 14.7965 4.80217C14.7022 4.84723 14.5995 4.87209 14.4951 4.87516C14.3907 4.87824 14.2867 4.85946 14.19 4.82003C13.2186 4.52795 12.1987 4.43275 11.19 4.54003C10.193 4.64212 9.22694 4.94485 8.34999 5.43003C7.50512 5.89613 6.75813 6.52088 6.14999 7.27003C5.52385 8.03319 5.05628 8.91361 4.77467 9.85974C4.49307 10.8059 4.40308 11.7987 4.50999 12.78C4.61208 13.777 4.91482 14.7431 5.39999 15.62C5.86609 16.4649 6.49084 17.2119 7.23999 17.82C8.00315 18.4462 8.88357 18.9137 9.8297 19.1953C10.7758 19.4769 11.7686 19.5669 12.75 19.46C13.747 19.3579 14.713 19.0552 15.59 18.57C16.4349 18.1039 17.1818 17.4792 17.79 16.73C18.4161 15.9669 18.8837 15.0864 19.1653 14.1403C19.4469 13.1942 19.5369 12.2014 19.43 11.22C19.4201 11.1169 19.4307 11.0129 19.461 10.9139C19.4914 10.8149 19.5409 10.7228 19.6069 10.643C19.6728 10.5631 19.7538 10.497 19.8453 10.4485C19.9368 10.3999 20.0369 10.3699 20.14 10.36C20.2431 10.3502 20.3471 10.3607 20.4461 10.3911C20.5451 10.4214 20.6372 10.471 20.717 10.5369C20.7969 10.6028 20.863 10.6839 20.9115 10.7753C20.9601 10.8668 20.9901 10.9669 21 11.07C21.1821 12.829 20.842 14.6026 20.0221 16.1695C19.2022 17.7363 17.9389 19.0269 16.39 19.88C15.3288 20.4938 14.1495 20.8755 12.93 21C12.62 21 12.3 21 12 21Z" fill="#ffffff"/></g></svg>
										<span class="me-auto">${response.toast_header}</span>
										<button class="btn-close btn-close-white ms-2 hide-toast" type="button" data-bs-dismiss="toast" aria-label="Close"></button>
									</div>
									<div class="toast-body">${response.toast_body}</div>
								</div>`);

								setTimeout( function() {
									$('#toast_success_alternative_license').fadeOut('fast');
								}, 3000);

								setTimeout( function() {
									$('#toast_success_alternative_license').remove();
								}, 3500);

								dropzone.addClass('file-uploaded').removeClass('file-processing');
								dropzone.children('.spinner-border').remove();
								dropzone.append('<div class="upload-notice d-flex flex-column align-items-center"><svg xmlns="http://www.w3.org/2000/svg" width="32" height="32" viewBox="0 0 24 24"><path fill="#22c55e" d="M12 2C6.486 2 2 6.486 2 12s4.486 10 10 10 10-4.486 10-10S17.514 2 12 2zm0 18c-4.411 0-8-3.589-8-8s3.589-8 8-8 8 3.589 8 8-3.589 8-8 8z"></path><path fill="#22c55e" d="M9.999 13.587 7.7 11.292l-1.412 1.416 3.713 3.705 6.706-6.706-1.414-1.414z"></path></svg><span>'+ response.dropfile_message +'</span></div>');
								dropzone.children('.file-list').addClass('d-none');

								setTimeout( function() {
									location.reload();
								}, 1000);
							} else {
								hook_toast.before(`<div id="toast_danger_alternative_license" class="toast toast-danger show">
									<div class="toast-header bg-danger text-white">
										<svg class="icon icon-white me-2" xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24"><path d="M12 2C6.486 2 2 6.486 2 12s4.486 10 10 10 10-4.486 10-10S17.514 2 12 2zm0 18c-4.411 0-8-3.589-8-8s3.589-8 8-8 8 3.589 8 8-3.589 8-8 8z"></path><path d="M11 11h2v6h-2zm0-4h2v2h-2z"></path></svg>
										<span class="me-auto">${response.toast_header}</span>
										<button class="btn-close btn-close-white ms-2 hide-toast" type="button" data-bs-dismiss="toast" aria-label="Close"></button>
									</div>
									<div class="toast-body">${response.toast_body}</div>
								</div>`);

								setTimeout( function() {
									$('#toast_danger_alternative_license').fadeOut('fast');
								}, 3000);

								setTimeout( function() {
									$('#toast_danger_alternative_license').remove();
								}, 3500);

								dropzone.addClass('invalid-file').removeClass('file-processing');
								dropzone.children('.spinner-border').remove();
								dropzone.children('.drag-text').removeClass('d-none');
								dropzone.children('.drag-and-drop-file').removeClass('d-none');
								dropzone.children('.upload-license-key').removeClass('d-none');
								dropzone.children('.file-list').addClass('d-none');
							}
						} catch (error) {
							console.log(error);
						}
					},
					error: function(xhr, status, error) {
						dropzone.addClass('fail-upload').removeClass('file-processing');
						console.log('Error on upload file');
						console.log(xhr.responseText);
					}
				});
			}
		}
	});


	/**
	 * Initialize Bootstrap components
	 * 
	 * @since 1.0.0
	 */
	jQuery(document).ready( function() {
		// init tooltips
		const tooltipTriggerList = document.querySelectorAll('[data-bs-toggle="tooltip"]');
		const tooltipList = [...tooltipTriggerList].map(tooltipTriggerEl => new bootstrap.Tooltip(tooltipTriggerEl));
	});


	/**
	 * Prevent reload page for Bootstrap modals and move for prepend #wpcontent for prevent z-index bug
	 * 
	 * @since 1.0.0
	 */
	jQuery(document).ready( function($) {
		$(document).on('click', 'button[data-bs-toggle="modal"], a[data-bs-toggle="modal"]', function(e) {
			e.preventDefault();

			let target_modal = $(this).attr('data-bs-target');
			let detached_modal = $(target_modal).detach();

			$('#wpcontent').prepend(detached_modal);

			// initialize Tooltips inside modals
			var tooltipTriggerList = document.querySelectorAll('[data-bs-toggle="tooltip"]');
			var tooltipList = [...tooltipTriggerList].map(tooltipTriggerEl => new bootstrap.Tooltip(tooltipTriggerEl));

			// change visibility for time delay action
			multi_select_visibility_controller( $('.set-time-delay-type'), {
				'period': '.wait-time-period-container',
				'date': '.wait-date-container',
			});
		});
	});


	/**
	 * Start workflows templates
	 * 
	 * @since 1.0.0
	 * @package MeuMouse.com
	 */
	jQuery(document).ready( function($) {
		// get parameter id from URL
        var id = get_param_by_name('id');

		// Make the AJAX request to get the number of templates
        if ( ! id ) {
			$.ajax({
				url: joinotify_builder_params.ajax_url,
				method: 'POST',
				data: {
					action: 'joinotify_get_templates_count',
					template: 'template',
				},
				success: function(response) {
					if ( joinotify_builder_params.debug_mode ) {
						console.log(response);
					}

					if (response.status === 'success') {
						var template_count = response.template_count;

						// Get the container element
						var templates_group = $('.joinotify-templates-group');

						// Clear any existing content
						templates_group.empty();

						// Loop over the number of templates and create placeholder elements
						for (var i = 0; i < template_count; i++) {
							templates_group.append('<div class="template-item placeholder-content"></div>');
						}

						$('#joinotify_template_library_container').addClass('templates-counted');
					} else {
						// Handle error
						display_toast('error', response.toast_header_title, response.toast_body_title, $('#joinotify-automations-builder'));
					}
				},
				error: function(xhr, status, error) {
					// Handle AJAX error
					console.error('AJAX Error:', error);
				},
			});
		}

		$(document).on('click', '.choose-template', function(e) {
			e.preventDefault();

			var btn = $(this);
			var btn_width = btn.width();
			var btn_height = btn.height();
			var btn_html = btn.html();
			var template_type = btn.data('template');

			// keep original width and height
			btn.width(btn_width);
			btn.height(btn_height);

			btn.prop('disabled', true).html('<span class="spinner-border spinner-border-sm"></span>');

			setTimeout( function() {
				btn.prop('disabled', false).html(btn_html);
			}, 1000);

			if ( template_type === 'scratch' ) {
				setTimeout( function() {
					$('#joinotify_start_choose_container').removeClass('active');
					$('#joinotify_choose_template_container').removeClass('active');
					$('#joinotify_triggers_group').addClass('active');
					$('#joinotify_triggers_content').addClass('active');
					$('#joinotify_builder_navbar').addClass('active');
				}, 1000);
			} else if ( template_type === 'template' ) {
				setTimeout( function() {
					$('#joinotify_start_choose_container').removeClass('active slide-left-animation').addClass('slide-right-animation');
					$('#joinotify_template_library_container').addClass('active slide-left-animation').removeClass('slide-right-animation');
				}, 1000);

				// get templates
				$.ajax({
					url: joinotify_builder_params.ajax_url,
					method: 'POST',
					data: {
						action: 'joinotify_get_workflow_templates',
						template: template_type,
					},
					success: function(response) {
						if ( joinotify_builder_params.debug_mode ) {
							console.log(response);
						}
	
						try {
							if (response.status === 'success') {
								$('.joinotify-templates-group').html(response.template_html);
								$('#joinotify_template_library_container').addClass('templates-loaded').removeClass('templates-counted');
							} else {
								display_toast('error', response.toast_header_title, response.toast_body_title, $('#joinotify-automations-builder'));
							}
						} catch (error) {
							console.log(error);
						}
					},
				});
			} else if ( template_type === 'import' ) {
				setTimeout( function() {
					$('#joinotify_start_choose_container').removeClass('active slide-left-animation').addClass('slide-right-animation');
					$('#joinotify_import_template_container').addClass('active slide-left-animation').removeClass('slide-right-animation');
				}, 1000);
			}
		});
	});


	/**
	 * Get URL parameter by name
	 * 
	 * @since 1.0.0
	 * @param {string} name | Parameter name
	 * @returns Parameter value
	 * @package MeuMouse.com
	 */
	function get_param_by_name(name) {
		let url = window.location.href;
		name = name.replace(/[\[\]]/g, "\\$&");
		let regex = new RegExp("[?&]" + name + "(=([^&#]*)|&|#|$)"),
			results = regex.exec(url);
		if (!results) return null;
		if (!results[2]) return '';
		
		return decodeURIComponent(results[2].replace(/\+/g, " "));
	}


	/**
	 * Add query params on URL
	 * 
	 * @since 1.0.0
	 * @param {string} param | Parameter name
	 * @param {string} value | Parameter value
	 * @package MeuMouse.com
	 */
	function add_query_param(param, value) {
		// get current URL
		var url = new URL(window.location.href);

		// add or update URL params
		url.searchParams.set(param, value);

		// update URL without reload page
		window.history.pushState({}, '', url);
	}


	/**
	 * Correctly display trigger settings modal
	 * 
	 * @since 1.0.0
	 * @package MeuMouse.com
	 */
	jQuery(document).ready( function($) {
		$('.joinotify-trigger-details').each( function() {
			// Removes the current element from the DOM and stores it for reinsertion elsewhere
			var element = $(this).detach();
			
			// Insert the element after #wpadminbar
			element.insertAfter('#wpadminbar');
		});

		if ( get_param_by_name('id') === null ) {
			$('.joinotify-loader-container').addClass('d-none');
		}
	});


	/**
	 * Add trigger action
	 * 
	 * @since 1.0.0
	 * @package MeuMouse.com
	 */
	jQuery(document).ready( function($) {
		$(document).on('click', '#joinotify_proceed_step_funnel', function(e) {
			e.preventDefault();

			var btn = $(this);
			var btn_width = btn.width();
			var btn_height = btn.height();
			var btn_html = btn.html();
			var get_title = $('#joinotify_set_workflow_name').val();
			var post_id = get_param_by_name('id');

			// keep original width and height
			btn.width(btn_width);
			btn.height(btn_height);

			$.ajax({
				url: joinotify_builder_params.ajax_url,
				method: 'POST',
				data: {
					action: 'joinotify_create_workflow',
					title: get_title,
					context: $('.trigger-item.active').data('context'),
					trigger: $('.trigger-item.active').data('trigger'),
					post_id: post_id,
				},
				beforeSend: function() {
					btn.prop('disabled', true).html('<span class="spinner-border spinner-border-sm"></span>');
				},
				success: function(response) {
					if ( joinotify_builder_params.debug_mode ) {
						console.log(response);
					}

					try {
						if ( response.status === 'success' && response.proceed === true ) {
							// If post_id was not set, we need to add it to the URL
							if ( ! post_id ) {
								add_query_param('id', response.post_id);
								post_id = response.post_id;

								$('#joinotify_workflow_header_title_container').append(response.workflow_status);
							} else {
								$('#joinotify_workflow_status').replaceWith(response.workflow_status);
							}

							$('#offcanvas_condition').find('.offcanvas-body').html(response.condition_selectors);
							$('#offcanvas_send_whatsapp_message_text').find('.offcanvas-body').append(response.placeholders_list);
							$('#send_whatsapp_message_media').find('.offcanvas-body').append(response.placeholders_list);
							$('#add_action_condition').prop('disabled', true);
							$('#joinotify_triggers_group').removeClass('active');
							$('#joinotify_triggers_content').removeClass('active');
							$('#joinotify_workflow_status_switch').prop('disabled', false);
							$('#joinotify_workflow_title').text(get_title);

							// update browser tab title
							document.title = get_title;

							// add trigger on funnel
							$('#joinotify_builder_funnel').children('.funnel-trigger-group').html(response.workflow_content);

							display_toast('success', response.toast_header_title, response.toast_body_title, $('#joinotify-automations-builder'));
						} else {
							display_toast('error', response.toast_header_title, response.toast_body_title, $('#joinotify-automations-builder'));
						}
					} catch (error) {
						console.log(error);
					}
				},
				complete: function() {
					btn.prop('disabled', false).html(btn_html);

					adjust_condition_container_height();
				},
			});
		});
	});


	/**
	 * Adjust container height for condition elements
	 * 
	 * @since 1.0.0
	 */
	function adjust_condition_container_height() {
		$('.funnel-trigger-group .funnel_block_item_condition').each( function() {
			var container = $(this);
			var condition_false = container.find('.add_condition_inside_node_point.condition_false');
			var condition_true = container.find('.add_condition_inside_node_point.condition_true');
			var wrapper = container.find('.end_condition_wrapper');
	
			// get height of "false" and "true" elements
			var condition_false_height = condition_false.length > 0 ? condition_false.outerHeight(true) : 0;
			var condition_true_height = condition_true.length > 0 ? condition_true.outerHeight(true) : 0;
	
			// Determine the greater height between the two conditions
			var max_height = Math.max(condition_false_height, condition_true_height);
	
			// Set the height of elements according to the largest height
			var container_height = `calc(1rem + ${max_height}px)`;
			var wrapper_height = `calc(${max_height}px - 100px)`;
	
			container.css('height', container_height);
			wrapper.css('height', wrapper_height);
		});
	}


	/**
	 * Add builder actions
	 * 
	 * @since 1.0.0
	 * @version 1.1.0
	 * @package MeuMouse.com
	 */
	jQuery(document).ready( function($) {
		var data_action_condition = false;
		var data_condition_type = '';
		var get_action_id = '';

		// set default workflow name
		$('#joinotify_set_workflow_name').val(joinotify_builder_params.default_workflow_name);

		function check_requirements() {
			let name = $('#joinotify_set_workflow_name').val();
			let trigger = $('.trigger-item.active').data('trigger');
			let proceed_btn = $('#joinotify_proceed_step_funnel');

			if ( name !== '' && trigger ) {
				proceed_btn.prop('disabled', false);
			} else {
				proceed_btn.prop('disabled', true);
			}
		}

		// check add trigger requirements on change or input trigger name
		$(document).on('change input', '#joinotify_set_workflow_name', function() {
			check_requirements();
		});

		// Select trigger event
		$(document).on('click', '.trigger-item', function() {
			$('.trigger-item').removeClass('active');
			$(this).toggleClass('active');

			check_requirements();
		});

		// enable offcanvas action event
		$(document).on('click', '.funnel_add_action', function() {
			let container_actions = $('#joinotify_actions_group');
			let btn = $(this);

			data_condition_type = btn.data('condition');

			// when click on add action inside condition
			if ( btn.data('action') === 'condition' ) {
				if ( ! container_actions.hasClass('active') ) {
					container_actions.addClass('active');
					$('#joinotify_builder_funnel').addClass('waiting-select-action');
					$('.funnel_add_action').children('.plusminus').addClass('active');
				}

				data_action_condition = true;
				get_action_id = btn.data('action-id');

				$('.joinotify_condition_node_point').removeClass('active'); // remove active container
				btn.parent('.add_condition_inside_node_point').parent('.joinotify_condition_node_point').addClass('active');
				$('.action-item[data-action="time_delay"]').addClass('locked');
				$('.action-item[data-action="condition"]').addClass('locked');
			} else {
				container_actions.toggleClass('active');
				data_action_condition = false;
				get_action_id = '';

				$('.joinotify_condition_node_point').removeClass('active');
				$('.action-item[data-action="time_delay"]').removeClass('locked');
				$('.action-item[data-action="condition"]').removeClass('locked');

				// manipulate width from builder funnel
				if ( container_actions.hasClass('active') ) {
					$('#joinotify_builder_funnel').addClass('waiting-select-action');
					$('.funnel_add_action').children('.plusminus').addClass('active');
				} else {
					$('#joinotify_builder_funnel').removeClass('waiting-select-action');
					$('.funnel_add_action').children('.plusminus').removeClass('active');
					$('.joinotify_condition_node_point').removeClass('active');

					data_action_condition = false;
					$('.action-item.locked').removeClass('locked');
				}
			}
		});

		// close actions sidebar
		$(document).on('click', '#joinotify_close_actions_group', function(e) {
			e.preventDefault();

			let container_actions = $('#joinotify_actions_group');

			container_actions.removeClass('active');
			$('.joinotify_condition_node_point').removeClass('active');
			$('.plusminus.active').removeClass('active');

			if ( container_actions.hasClass('active') ) {
				$('#joinotify_builder_funnel').addClass('waiting-select-action');
			} else {
				$('#joinotify_builder_funnel').removeClass('waiting-select-action');

				data_action_condition = false;
				$('.action-item.locked').removeClass('locked');
			}
		});

		// expand actions sidebar
		$(document).on('click', '.expand-offcanvas', function(e) {
			e.preventDefault();

			// add collapse icon
			$(this).addClass('d-none');
			$(this).siblings('.collapse-offcanvas').removeClass('d-none');
			$('.offcanvas.show').addClass('offcanvas-expanded');
			$('#joinotify_builder_funnel').addClass('offcanvas-expanded');
		});

		// collapse actions sidebar
		$(document).on('click', '.btn-close[data-bs-dismiss="offcanvas"], .collapse-offcanvas', function() {
			// add expand icon
			$(this).siblings('.expand-offcanvas').removeClass('d-none');
			$(this).siblings('.collapse-offcanvas').addClass('d-none');
			$(this).closest('.collapse-offcanvas').addClass('d-none');
			$('.offcanvas.offcanvas-expanded').removeClass('offcanvas-expanded');
			$('#joinotify_builder_funnel').removeClass('offcanvas-expanded');
		});

		/**
		 * Reset settings for all offcanvas actions
		 * 
		 * @since 1.0.0
		 * @package MeuMouse.com
		 */
		function reset_actions_settings() {
			$('.set-time-delay-type option:first').attr('selected','selected');
			$('.get-wait-value').val('');
			$('.get-wait-period option:first').attr('selected','selected');
			$('.get-date-value').val('');
			$('.get-time-value').val('');
			$('#joinotify_get_whatsapp_message_text').val('').change();
			$('#joinotify_get_url_media').val('');
			$('.condition-item.active').removeClass('active');
			$('.condition-settings-item.active').removeClass('active');
			$('.joinotify_condition_node_point').removeClass('active');

			$('.get-condition-type').each( function() {
				$(this).find('option:first').attr('selected','selected');
			});

			$('.get-condition-value').each( function() {
				$(this).find('option:first').attr('selected','selected') || $(this).val('');
			});
		}

		// Select condition event
		$(document).on('click', '.condition-item', function() {
			var condition = $(this).data('condition');

			$('.condition-item').removeClass('active');
			$('.condition-settings-item').removeClass('active');
			$(this).toggleClass('active');
			$('.condition-settings-item[data-condition="'+ condition +'"]').addClass('active');

			if ( $('.condition-item').hasClass('active') ) {
				$('#add_action_condition').prop('disabled', false);
			} else {
				$('#add_action_condition').prop('disabled', true);
			}
		});

		// add action on workflow
		$(document).on('click', '.add-funnel-action', function(e) {
			e.preventDefault();

			var btn = $(this);
			var btn_width = btn.width();
			var btn_height = btn.height();
			var btn_html = btn.html();
			
			// keep original width and height
			btn.width(btn_width);
			btn.height(btn_height);

			var post_id = get_param_by_name('id');
			var action_type = $('.offcanvas.show').data('action');
    		var action_data = {};

			// collect specific action data
			if ( action_type === 'time_delay' ) {
				var delay_type = $('.set-time-delay-type').val();

				action_data = {
					type: 'action',
					data: {
						action: 'time_delay',
						title: $('.offcanvas.show').find('.offcanvas-title').text(),
						delay_type: delay_type,
					},
				};
		
				if (delay_type === 'period') {
					action_data.data.delay_value = $('.get-wait-value').val();
					action_data.data.delay_period = $('.get-wait-period').val();
				} else if (delay_type === 'date') {
					action_data.data.date_value = $('.get-date-value').val();
					action_data.data.time_value = $('.get-time-value').val();
				}
			} else if ( action_type === 'send_whatsapp_message_text' ) {
				action_data = {
					type: 'action',
					data: {
						action: 'send_whatsapp_message_text',
						title: $('.offcanvas.show').find('.offcanvas-title').text(),
						sender: $('#joinotify_get_whatsapp_phone_sender').val(),
						receiver: $('#joinotify_get_whatsapp_number_msg_text').val(),
						message: $('#joinotify_get_whatsapp_message_text').val(),
					},
				};
			} else if ( action_type === 'send_whatsapp_message_media' ) {
				action_data = {
					type: 'action',
					data: {
						action: 'send_whatsapp_message_media',
						title: $('.offcanvas.show').find('.offcanvas-title').text(),
						sender: $('#joinotify_get_whatsapp_phone_sender_media').val(),
						receiver: $('#joinotify_get_whatsapp_number_msg_media').val(),
						media_type: $('#joinotify_get_media_type').val(),
						media_url: $('#joinotify_get_url_media').val(),
					},
				};
			} else if ( action_type === 'condition' ) {
				action_data = {
					type: 'action',
					data: {
						action: 'condition',
						condition: $('.condition-item.active').data('condition'),
						title: $('.condition-item.active').find('.title').text(),
						condition_content: {
							condition: $('.condition-settings-item.active').data('condition'),
							type: $('.condition-settings-item.active').find('.get-condition-type option:selected').val(),
							type_text: $('.condition-settings-item.active').find('.get-condition-type option:selected').text(),
							value: $('.condition-settings-item.active').find('.get-condition-value option:selected').val() || $('.condition-settings-item.active').find('.get-condition-value').val(),
							value_text: $('.condition-settings-item.active').find('.get-condition-value option:selected').text() || $('.condition-settings-item.active').find('.get-condition-value').val(),
						},
					},
				};
			} else if ( action_type === 'stop_funnel' ) {
				action_data = {
					type: 'action',
					data: {
						action: 'stop_funnel',
					},
				};
			}

			// display action data on debug mode
			if ( joinotify_builder_params.debug_mode ) {
				console.log(action_data);
				console.log('Post ID: ', post_id);
			}

			$.ajax({
				url: joinotify_builder_params.ajax_url,
				method: 'POST',
				data: {
					action: 'joinotify_add_workflow_action',
					post_id: post_id,
					action_condition: data_action_condition,
					action_id: get_action_id,
					condition_action: data_condition_type,
					workflow_action: JSON.stringify(action_data),
				},
				beforeSend: function() {
					btn.prop('disabled', true).html('<span class="spinner-border spinner-border-sm"></span>');
				},
				success: function(response) {
					if ( joinotify_builder_params.debug_mode ) {
						console.log(response);
					}

					try {
						if ( response.status === 'success' ) {
							// close active offcanvas
							$('.offcanvas.show').removeClass('show');

							// reset options
							reset_actions_settings();

							if (response.has_action) {
								$('#joinotify_builder_run_test').prop('disabled', false);
							} else {
								$('#joinotify_builder_run_test').prop('disabled', true);
							}

							// replace with new content updated
							$('#joinotify_builder_funnel').children('.funnel-trigger-group').html(response.workflow_content);

							display_toast('success', response.toast_header_title, response.toast_body_title, $('#joinotify-automations-builder'));
						} else {
							display_toast('error', response.toast_header_title, response.toast_body_title, $('#joinotify-automations-builder'));
						}
					} catch (error) {
						console.log(error);
					}
				},
				complete: function() {
					adjust_condition_container_height();

					btn.prop('disabled', false).html(btn_html);
					$('#joinotify_actions_group').removeClass('active');
					$('.condition-item.active').removeClass('active');
					$('#add_action_condition').prop('disabled', true);
					$('#joinotify_builder_funnel').removeClass('waiting-select-action');
				},
			});
		});
	});


	/**
	 * Remove trigger action
	 * 
	 * @since 1.0.0
	 * @package MeuMouse.com
	 */
	jQuery(document).ready( function($) {
		$(document).on('click', '.exclude-trigger', function(e) {
			e.preventDefault();
	
			// get confirmation
			if ( ! confirm(joinotify_builder_params.confirm_exclude_trigger) ) {
				return;
			}

			var trigger = $(this);

			$.ajax({
				url: joinotify_builder_params.ajax_url,
				method: 'POST',
				data: {
					action: 'joinotify_delete_trigger',
					post_id: get_param_by_name('id'),
					trigger_id: trigger.data('trigger-id'),
				},
				beforeSend: function() {
					trigger.prop('disabled', true);
					trigger.closest('.funnel-trigger-item').addClass('placeholder-wave removing-trigger');
				},
				success: function(response) {
					if ( joinotify_builder_params.debug_mode ) {
						console.log(response);
					}

					try {
						if ( response.status === 'success' ) {
							if (response.has_trigger) {
								$('#joinotify_builder_run_test').prop('disabled', false);
							} else {
								$('#joinotify_triggers_group').addClass('active');
								$('#joinotify_triggers_content').addClass('active');
								$('#joinotify_builder_run_test').prop('disabled', true);

								// reset triggers selection
								setTimeout( function() {
									$('.trigger-item').removeClass('active');
									$('.joinotify-triggers-tab-wrapper a.nav-tab').first().click();
								}, 500);
							}

							$('#joinotify_builder_funnel').children('.funnel-trigger-group').html(response.workflow_content);

							display_toast('success', response.toast_header_title, response.toast_body_title, $('#joinotify-automations-builder'));
						} else {
							display_toast('error', response.toast_header_title, response.toast_body_title, $('#joinotify-automations-builder'));
						}
					} catch (error) {
						console.log(error);
					}
				},
				complete: function() {
					trigger.prop('disabled', false);
					trigger.closest('.funnel-trigger-item').removeClass('placeholder-wave removing-trigger');
				},
			});
		});
	});


	/**
	 * Remove action
	 * 
	 * @since 1.0.0
	 * @package MeuMouse.com
	 */
	jQuery(document).ready( function($) {
		$(document).on('click', '.exclude-action', function(e) {
			e.preventDefault();
	
			// get confirmation
			if ( ! confirm(joinotify_builder_params.confirm_exclude_action) ) {
				return;
			}

			var action = $(this);

			$.ajax({
				url: joinotify_builder_params.ajax_url,
				method: 'POST',
				data: {
					action: 'joinotify_delete_workflow_action',
					post_id: get_param_by_name('id'),
					action_id: action.data('action-id'),
				},
				beforeSend: function() {
					action.prop('disabled', true);
					action.closest('.funnel-action-item').addClass('placeholder-wave removing-action');
				},
				success: function(response) {
					if ( joinotify_builder_params.debug_mode ) {
						console.log(response);
					}

					try {
						if ( response.status === 'success' ) {
							if (response.has_action) {
								$('#joinotify_builder_run_test').prop('disabled', false);
							} else {
								$('#joinotify_builder_run_test').prop('disabled', true);
							}

							$('#joinotify_builder_funnel').children('.funnel-trigger-group').html(response.workflow_content);

							display_toast('success', response.toast_header_title, response.toast_body_title, $('#joinotify-automations-builder'));
						} else {
							display_toast('error', response.toast_header_title, response.toast_body_title, $('#joinotify-automations-builder'));
						}
					} catch (error) {
						console.log(error);
					}
				},
				complete: function() {
					action.prop('disabled', false);
					action.closest('.funnel-action-item').removeClass('placeholder-wave removing-action');
					adjust_condition_container_height();
				},
			});
		});
	});


	/**
	 * Change workflow status
	 * 
	 * @since 1.0.0
	 * @package MeuMouse.com
	 */
	jQuery(document).ready( function($) {
		$(document).on('click', '#joinotify_workflow_status_switch', function() {
			var btn = $(this);

			$.ajax({
				url: joinotify_builder_params.ajax_url,
				method: 'POST',
				data: {
					action: 'joinotify_update_workflow_status',
					status: btn.prop('checked') ? 'publish' : 'draft',
					post_id: get_param_by_name('id'),
				},
				beforeSend: function() {
					btn.prop('disabled', true);
				},
				complete: function() {
					btn.prop('disabled', false);
				},
				success: function(response) {
					if ( joinotify_builder_params.debug_mode ) {
						console.log(response);
					}

					try {
						if ( response.status === 'success' ) {
							$('#joinotify_workflow_status_title').text(response.workflow_status);
							$('#joinotify_workflow_status').replaceWith(response.display_workflow_status);

							display_toast('success', response.toast_header_title, response.toast_body_title, $('#joinotify-automations-builder'));
						} else {
							display_toast('error', response.toast_header_title, response.toast_body_title, $('#joinotify-automations-builder'));
						}
					} catch (error) {
						console.log(error);
					}
				},
			});
		});
	});


	/**
	 * Load workflow data
	 * 
	 * @since 1.0.0
	 * @package MeuMouse.com
	 */
	jQuery(document).ready( function($) {
		// get parameter id from URL
        var id = get_param_by_name('id');

        if (id) {
            $.ajax({
                url: joinotify_builder_params.ajax_url,
                type: 'POST',
                data: {
                    action: 'joinotify_load_workflow_data',
                    post_id: id,
                },
                beforeSend: function() {
                    $('.joinotify-loader-container').removeClass('d-none');
                },
                success: function(response) {
					if ( joinotify_builder_params.debug_mode ) {
						console.log(response);
					}
					
					try {
						if (response.status === 'success') {
							$('#joinotify_choose_template_container').removeClass('active');
							$('#joinotify_builder_navbar').addClass('active');

							if (response.has_trigger) {
								$('#joinotify_triggers_group').removeClass('active');
								$('#joinotify_triggers_content').removeClass('active');
							} else {
								$('#joinotify_triggers_group').addClass('active');
								$('#joinotify_triggers_content').addClass('active');
							}

							if (response.has_action) {
								$('#joinotify_builder_run_test').prop('disabled', false);
							} else {
								$('#joinotify_builder_run_test').prop('disabled', true);
							}
							
							$('#offcanvas_condition').find('.offcanvas-body').html(response.condition_selectors);
							$('#offcanvas_send_whatsapp_message_text').find('.offcanvas-body').append(response.placeholders_list);
							$('#send_whatsapp_message_media').find('.offcanvas-body').append(response.placeholders_list);
							$('#add_action_condition').prop('disabled', true);
							$('#joinotify_workflow_title').text(response.workflow_title);
							$('#joinotify_edit_workflow_title').val(response.workflow_title);
							$('#joinotify_set_workflow_name').val(response.workflow_title);

							// update browser tab title
							document.title = response.workflow_title;

							$('#joinotify_workflow_header_title_container').append(response.display_workflow_status);
							$('#joinotify_builder_funnel .funnel-trigger-group').prepend(response.workflow_data);
							$('#joinotify_workflow_status_switch').prop('disabled', false);

							if ( 'publish' === response.workflow_status ) {
								$('#joinotify_workflow_status_switch').prop('checked', true);
								$('#joinotify_workflow_status_title').text(joinotify_builder_params.status_active);
							}

							// workflow content
							$('#joinotify_builder_funnel').children('.funnel-trigger-group').html(response.workflow_content);

							// add "active" class for trigger item on workflow content
							if ($('.funnel-trigger-item').length > 0) {
								$('.funnel-trigger-item').each( function() {
									var data_context = $(this).data('context');
									var data_trigger = $(this).data('trigger');
	
									// find matching trigger
									var matching_trigger_item = $('.trigger-item[data-context="' + data_context + '"][data-trigger="' + data_trigger + '"]');
									
									if (matching_trigger_item.length > 0) {
										matching_trigger_item.addClass('active');
									}
								});
							}
						} else {
							display_toast('error', response.toast_header_title, response.toast_body_title, $('#joinotify-automations-builder'));
						}
					} catch (error) {
						console.log(error);
					}
                },
				complete: function() {
					$('.joinotify-loader-container').addClass('d-none');
					adjust_condition_container_height();
				},
                error: function(xhr, status, error) {
                    console.error('Error on AJAX request:', xhr.responseText);
                }
            });
        }
	});


	/**
	 * Update workflow title action
	 * 
	 * @since 1.0.0
	 * @package MeuMouse.com
	 */
	jQuery(document).ready( function($) {
		$(document).on('click', '#joinotify_update_workflow_title', function(e) {
			e.preventDefault();

			var btn = $(this);
			var btn_width = btn.width();
			var btn_height = btn.height();
			var btn_html = btn.html();

			// keep original width and height
			btn.width(btn_width);
			btn.height(btn_height);

			$.ajax({
				url: joinotify_builder_params.ajax_url,
				method: 'POST',
				data: {
					action: 'joinotify_update_workflow_title',
					post_id: get_param_by_name('id'),
					workflow_title: $('#joinotify_edit_workflow_title').val(),
				},
				beforeSend: function() {
					btn.prop('disabled', true).html('<span class="spinner-border spinner-border-sm"></span>');
				},
				success: function(response) {
					if ( joinotify_builder_params.debug_mode ) {
						console.log(response);
					}

					try {
						if ( response.status === 'success' ) {
							$('#joinotify_workflow_title').text(response.workflow_title);
							$('#joinotify_edit_workflow_title').val(response.workflow_title);
							$('#joinotify_set_workflow_name').val(response.workflow_title);

							// update browser tab title
							document.title = response.workflow_title;

							$('#edit_workflow_title').find('.btn-close').click();

							display_toast('success', response.toast_header_title, response.toast_body_title, $('#joinotify-automations-builder'));
						} else {
							$('#edit_workflow_title').find('.btn-close').click();
							$('#joinotify_edit_workflow_title').val('');
							
							display_toast('error', response.toast_header_title, response.toast_body_title, $('#joinotify-automations-builder'));
						}
					} catch (error) {
						console.log(error);
					}
				},
				complete: function() {
					btn.prop('disabled', false).html(btn_html);
				},
			});
		});
	})


	/**
	 * Change visibility for elements
	 * 
	 * @since 1.0.0
	 * @package MeuMouse.com
	 */
	jQuery(document).ready( function() {
		// change visibility for time delay action
		multi_select_visibility_controller( $('.set-time-delay-type'), {
			'period': '.wait-time-period-container',
			'date': '.wait-date-container',
		});
	});


	/**
	 * Include Bootstrap date picker
	 * 
	 * @since 1.0.0
	 */
	jQuery(document).ready( function($) {
		/**
		 * Initialize Bootstrap datepicker
		 */
		$('.dateselect').datepicker({
			format: 'dd/mm/yyyy',
			todayHighlight: true,
			language: 'pt-BR',
		});
		
		$(document).on('focus', '.dateselect', function() {
			if ( ! $(this).data('datepicker') ) {
				$(this).datepicker({
					format: 'dd/mm/yyyy',
					todayHighlight: true,
					language: 'pt-BR',
				});
			}
		});
	});


	/**
	 * WhatsApp message input
	 * 
	 * @since 1.0.0
	 * @version 1.1.0
	 * @package MeuMouse.com
	 */
	jQuery(document).ready( function($) {
		$('.set-whatsapp-message-text').on('input change blur', function() {
			let message = $(this).val();
			let preview_element = $(this).closest('.input-group').siblings('.preview-whatsapp-message-sender');

			// replace \n for <br> break row HTML element
			message = message.replace(/\n/g, '<br>');

			// replace {{ br }} for break row HTML element
			message = message.replace(/{{ br }}/g, '<br>');
	
			if (message.trim() !== '') {
				preview_element.addClass('active').html(message);
			} else {
				preview_element.removeClass('active').html('');
			}
		});
	});	


	/**
	 * Initialize download data with archive
	 * 
	 * @since 1.0.0
	 * @param {array} data | File data array
	 * @param {string} filename | File name
	 * @param {string} type | File type
	 * @package MeuMouse.com
	 */
	function download_data(data, filename, type) {
		var file = new Blob([data], { type: type });
		var a = document.createElement('a');
		var url = URL.createObjectURL(file);

		a.href = url;
		a.download = filename;
		document.body.appendChild(a);
		a.click();

		setTimeout( function() {
			document.body.removeChild(a);
			window.URL.revokeObjectURL(url);  
		}, 0); 
	}
	

	/**
	 * Export workflow file action
	 * 
	 * @since 1.0.0
	 * @package MeuMouse.com
	 */
	jQuery(document).ready( function($) {
		$('#joinotify_export_workflow').on('click', function(e) {
			e.preventDefault();

			var post_id = get_param_by_name('id');

			$.ajax({
				url: joinotify_builder_params.ajax_url,
				method: 'POST',
				data: {
					action: 'joinotify_export_workflow',
					post_id: post_id,
					security: joinotify_builder_params.export_nonce,
				},
				dataType: 'json',
				beforeSend: function() {
					$('.joinotify-loader-container').removeClass('d-none').addClass('exporting-workflow');
				},
				complete: function() {
					$('.joinotify-loader-container').addClass('d-none').removeClass('exporting-workflow');
				},
				success: function(response) {
					try {
						if (response.status === 'success') {
							var filename = 'joinotify-workflow-' + post_id + '.json';
							download_data( JSON.stringify(response.export_data, null, 2), filename, 'application/json' );

							display_toast('success', response.toast_header_title, response.toast_body_title, $('#joinotify-automations-builder'));
						} else {
							display_toast('error', response.toast_header_title, response.toast_body_title, $('#joinotify-automations-builder'));
						}
					} catch (error) {
						console.log(error);
					}
				},
				error: function(xhr, status, error) {
					alert('AJAX error: ' + error);
				}
			});
		});
	});


	/**
	 * Activate workflow steps
	 * 
	 * @since 1.0.0
	 */
	jQuery(document).ready( function($) {
		// return to start
		$(document).on('click', '.return-to-start', function(e) {
			e.preventDefault();

			$('#joinotify_triggers_group').removeClass('active');
			$('#joinotify_triggers_content').removeClass('active');
			$('#joinotify_builder_navbar').removeClass('active');
			$('#joinotify_choose_template_container').addClass('active');
			$('#joinotify_start_choose_container').addClass('active');
		});

		// cancel import file and return to start
		$(document).on('click', '#joinotify_cancel_import_template', function(e) {
			e.preventDefault();

			$('#joinotify_import_template_container').removeClass('active');
			$('#joinotify_start_choose_container').addClass('active');
		});
	});


	/**
	 * Open WordPress midia library popup on click
	 * 
	 * @since 1.0.0
	 */
	jQuery(document).ready( function($) {
		var file_frame;
	
		$('#joinotify_set_url_media').on('click', function(e) {
			e.preventDefault();
	
			// If the media frame already exists, reopen it
			if (file_frame) {
				file_frame.open();
				return;
			}
	
			// create midia frame
			file_frame = wp.media.frames.file_frame = wp.media({
				title: joinotify_builder_params.set_media_title,
				button: {
					text: joinotify_builder_params.use_this_media_title,
				},
				multiple: false,
			});
	
			// When an image is selected, execute the callback function
			file_frame.on('select', function() {
				var attachment = file_frame.state().get('selection').first().toJSON();
				var imageUrl = attachment.url;
			
				// Update the input value with the URL of the selected image
				$('#joinotify_get_url_media').val(imageUrl).trigger('change'); // Force change
			});

			file_frame.open();
		});
	});


	/**
	 * Send message test
	 * 
	 * @since 1.0.0
	 */
	jQuery(document).ready( function($) {
		$(document).on('click', '#joinotify_builder_run_test', function(e) {
			e.preventDefault();

			var btn = $(this);
			var btn_width = btn.width();
			var btn_height = btn.height();
			var btn_html = btn.html();

			// keep original width and height
			btn.width(btn_width);
			btn.height(btn_height);

			$.ajax({
				url: joinotify_builder_params.ajax_url,
				method: 'POST',
				data: {
					action: 'joinotify_run_workflow_test',
					post_id: get_param_by_name('id'),
				},
				beforeSend: function() {
					btn.prop('disabled', true).html('<span class="spinner-border spinner-border-sm"></span>');
				},
				success: function(response) {
					if ( joinotify_builder_params.debug_mode ) {
						console.log(response);
					}

					try {
						if ( response.status === 'success' ) {
							display_toast('success', response.toast_header_title, response.toast_body_title, $('#joinotify-automations-builder'));
						} else {
							display_toast('error', response.toast_header_title, response.toast_body_title, $('#joinotify-automations-builder'));
						}
					} catch (error) {
						console.log(error);
					}
				},
				complete: function() {
					btn.prop('disabled', false).html(btn_html);
				},
			});
		});
	});


	/**
	 * Dismiss placeholders tip
	 * 
	 * @since 1.1.0
	 */
	jQuery(document).ready( function($) {
		$(document).on('click', '#joinotify_dismiss_placeholders_tip', function(e) {
			e.preventDefault();

			$.ajax({
				url: joinotify_builder_params.ajax_url,
				method: 'POST',
				data: {
					action: 'joinotify_dismiss_placeholders_tip',
				},
				success: function(response) {
					if ( joinotify_builder_params.debug_mode ) {
						console.log(response);
					}
				},
				error: function(error) {
					console.log(error);
				},
			});
		});
	});

})(jQuery);