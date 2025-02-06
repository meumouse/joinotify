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
	 * @version 1.1.0
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
				import_workflow(file, $(this));
			}
		});
	
		// Adds a change event handler to the input file
		$('#upload_template_file').on('change', function(e) {
			var file = e.target.files[0];

			import_workflow(file, $(this).parents('.dropzone-license'));
		});
	
		/**
		 * Import workflow file
		 * 
		 * @since 1.0.0
		 * @version 1.1.0
		 * @param {string} file | File
		 * @param {string} dropzone | Dropzone div
		 * @returns void
		 */
		function import_workflow(file, dropzone) {
			if (file) {
				var filename = file.name;
				var formData = new FormData();

				formData.append('action', 'joinotify_import_workflow_templates');
				formData.append('security', joinotify_builder_params.import_nonce);
				formData.append('file', file);

				if ( filename ) {
					dropzone.children('.file-list').removeClass('d-none').text(filename);
				}

				dropzone.addClass('file-processing');

				$('#joinotify_send_import_files').prop('disabled', false);
	
				// send request
				$('#joinotify_send_import_files').on('click', function(e) {
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
						type: 'POST',
						data: formData,
						processData: false,
						contentType: false,
						beforeSend: function() {
							btn.prop('disabled', true).html('<span class="spinner-border spinner-border-sm"></span>');
						},
						success: function(response) {
							if ( joinotify_builder_params.debug_mode ) {
								console.log(response);
							}
	
							try {
								if (response.status === 'success') {
									dropzone.addClass('file-uploaded').removeClass('file-processing');
									dropzone.children('.spinner-border').remove();
									dropzone.append('<div class="upload-notice d-flex flex-column align-items-center"><svg xmlns="http://www.w3.org/2000/svg" width="32" height="32" viewBox="0 0 24 24"><path fill="#22c55e" d="M12 2C6.486 2 2 6.486 2 12s4.486 10 10 10 10-4.486 10-10S17.514 2 12 2zm0 18c-4.411 0-8-3.589-8-8s3.589-8 8-8 8 3.589 8 8-3.589 8-8 8z"></path><path fill="#22c55e" d="M9.999 13.587 7.7 11.292l-1.412 1.416 3.713 3.705 6.706-6.706-1.414-1.414z"></path></svg><span>'+ response.dropfile_message +'</span></div>');
									dropzone.children('.file-list').addClass('d-none');

									// success response
									display_toast('success', response.toast_header_title, response.toast_body_title, $('#joinotify-automations-builder'));

									// redirect for edition of imported workflow
									setTimeout( function() {
										window.location.href = response.redirect;
								  }, 1000);
								} else {
									dropzone.addClass('invalid-file').removeClass('file-processing');
									dropzone.children('.drag-text').removeClass('d-none');
									dropzone.children('.drag-and-drop-file').removeClass('d-none');
									dropzone.children('.upload-license-key').removeClass('d-none');
									dropzone.children('.file-list').addClass('d-none');
	
									// error response
									display_toast('error', response.toast_header_title, response.toast_body_title, $('#joinotify-automations-builder'));
								}
							} catch (error) {
								console.log(error);
							}
						},
						error: function(xhr, status, error) {
							dropzone.addClass('fail-upload').removeClass('file-processing');
							console.log('Error on upload file');
							console.log(xhr.responseText);
						},
						complete: function() {
							btn.prop('disabled', false).html(btn_html);
						},
					});
				});
			} else {
				$('#joinotify_send_import_files').prop('disabled', true);
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
	 * @version 1.1.0
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
							$('#joinotify_actions_wrapper').html(response.sidebar_actions);
							$('#offcanvas_send_whatsapp_message_text').find('.offcanvas-body').append(response.fetch_groups_trigger);
							$('#offcanvas_send_whatsapp_message_text').find('.offcanvas-body').append(response.placeholders_list);
							$('#offcanvas_send_whatsapp_message_media').find('.offcanvas-body').append(response.fetch_groups_trigger);
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

							// check if has trigger settings
							setTimeout( function() {
								if ( response.active_settings_modal ) {
									let modal = response.active_settings_modal;
									let detached_modal = $(modal).detach();
	
									$('#wpcontent').prepend(detached_modal);
									$(modal).modal('show'); // display modal
								}
							}, 1000);

							// set workflow content is ready
							$(document).trigger('workflowReady');

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
			} else if ( action_type === 'snippet_php' ) {
				action_data = {
					type: 'action',
					data: {
						action: 'snippet_php',
						title: $('.offcanvas.show').find('.offcanvas-title').text(),
						snippet_php: $('#joinotify_set_snippet_php').val(),
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

							// set workflow content is ready
							$(document).trigger('workflowReady');

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

							// set workflow content is ready
							$(document).trigger('workflowReady');

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

							// set workflow content is ready
							$(document).trigger('workflowReady');

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
	 * @version 1.1.0
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
								$('#joinotify_actions_wrapper').html(response.sidebar_actions);
								$('#offcanvas_send_whatsapp_message_text').find('.offcanvas-body').append(response.fetch_groups_trigger);
								$('#offcanvas_send_whatsapp_message_text').find('.offcanvas-body').append(response.placeholders_list);
								$('#offcanvas_send_whatsapp_message_media').find('.offcanvas-body').append(response.fetch_groups_trigger);
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

								// check if has trigger settings
								if ( response.active_settings_modal ) {
									let modal = response.active_settings_modal;
									let detached_modal = $(modal).detach();

									$('#wpcontent').prepend(detached_modal);
									$(modal).modal('show'); // display modal
								}
								
								// set workflow content is ready
								$(document).trigger('workflowReady');

								// add "active" class for trigger item on workflow content
								if ( $('.funnel-trigger-item').length > 0 ) {
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
					},
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
	 * @version 1.1.0
	 * @package MeuMouse.com
	 */
	jQuery(document).on('workflowReady', function() {
		// change visibility for time delay action
		$(document).on('change', '.set-time-delay-type', function() {
			var select = $(this);
			var selected_option = select.val();

			select.parent('div').siblings('.wait-time-period-container').toggleClass('d-none', selected_option !== 'period');
			select.parent('div').siblings('.wait-date-container').toggleClass('d-none', selected_option !== 'date');
		});

		// initialize visibility on load components
		$('.set-time-delay-type').each( function() {
			var select = $(this);
			var selected_option = select.val();

			select.parent('div').siblings('.wait-time-period-container').toggleClass('d-none', selected_option !== 'period');
			select.parent('div').siblings('.wait-date-container').toggleClass('d-none', selected_option !== 'date');
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
		$(document).on('input change blur', '.set-whatsapp-message-text', function() {
			var input = $(this);
			var text = input.val();
			var preview_message = $(this).closest('.input-group').siblings('.preview-whatsapp-message-sender');

			// replace \n for <br> break row HTML element
			text = text.replace(/\n/g, '<br>');

			// replace {{ br }} for break row HTML element
			text = text.replace(/{{ br }}/g, '<br>');
	
			if ( text.trim() !== '' ) {
				preview_message.addClass('active').html(text);
			} else {
				preview_message.removeClass('active').html('');
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


	/**
	 * Fetch all groups information
	 * 
	 * @since 1.1.0
	 */
	jQuery(document).ready( function($) {
		// get groups details on open modal
		$(document).on('click', '#joinotify_fetch_all_groups', function(e) {
			e.preventDefault();

			// check if content has been loaded
			if ( $('#joinotify_fetch_all_groups_container').hasClass('content-loaded') ) {
				return;
			}

			// make request
			$.ajax({
				url: joinotify_builder_params.ajax_url,
				method: 'POST',
				data: {
					action: 'joinotify_fetch_all_groups',
					sender: $('#joinotify_get_whatsapp_phone_sender').val(),
				},
				success: function(response) {
					if ( joinotify_builder_params.debug_mode ) {
						console.log(response);
					}

					try {
						if ( response.status === 'success' ) {
							$('#joinotify_fetch_all_groups_container').addClass('content-loaded').find('.modal-body').html(response.groups_details_html);
						} else {
							$('#joinotify_fetch_all_groups_container').find('.btn-close').click();
							display_toast('error', response.toast_header_title, response.toast_body_title, $('#joinotify-automations-builder'));
						}
					} catch (error) {
						console.log(error);
					}
				},
				error: function(error) {
					console.log(error);
				},
			});
		});

		// copy group id
		$(document).on('click', '.get-group-id', function() {
			var group_item = $(this);
			var group_id = group_item.data('group-id');

			// copy id event
			navigator.clipboard.writeText(group_id).then( function() {
				group_item.append(`<div class="confirm-copy-ui active">
						<svg class="icon icon-lg icon-white me-2" xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24"><path d="M12 2C6.486 2 2 6.486 2 12s4.486 10 10 10 10-4.486 10-10S17.514 2 12 2zm0 18c-4.411 0-8-3.589-8-8s3.589-8 8-8 8 3.589 8 8-3.589 8-8 8z"></path><path d="M9.999 13.587 7.7 11.292l-1.412 1.416 3.713 3.705 6.706-6.706-1.414-1.414z"></path></svg>
						<span>${joinotify_builder_params.copy_group_id}</span>
				</div>`);

				setTimeout( function() {
					group_item.find('.confirm-copy-ui').removeClass('active');
				}, 800);

				setTimeout( function() {
					group_item.find('.confirm-copy-ui').remove();
				}, 1000);
			}).catch( function(error) {
				console.error('Error on copy group ID: ' + error);
			});
		});
	});


	/**
	 * Save action edition
	 * 
	 * @since 1.1.0
	 * @package MeuMouse.com
	 */
	jQuery(document).ready( function($) {
		$(document).on('click', '.save-action-edit', function(e) {
			e.preventDefault();

			var btn = $(this);
			var btn_width = btn.width();
			var btn_height = btn.height();
			var btn_html = btn.html();
			var get_action = btn.data('action');
			var get_action_id = btn.data('action-id');

			// keep original width and height
			btn.width(btn_width);
			btn.height(btn_height);

			var action_data = {};

			// collect specific action data
			if ( get_action === 'time_delay' ) {
				var delay_type = $('.modal.show').find('.set-time-delay-type-edit').val();

				action_data = {
					action: 'time_delay',
					data: {
						delay_type: delay_type,
					},
				};
		
				if (delay_type === 'period') {
					action_data.data.delay_value = $('.modal.show').find('.get-wait-value').val();
					action_data.data.delay_period = $('.modal.show').find('.get-wait-period').val();
				} else if (delay_type === 'date') {
					action_data.data.date_value = $('.modal.show').find('.get-date-value').val();
					action_data.data.time_value = $('.modal.show').find('.get-time-value').val();
				}
			} else if ( get_action === 'send_whatsapp_message_text' ) {
				action_data = {
					action: 'send_whatsapp_message_text',
					data: {
						sender: $('.modal.show').find('.get-phone-sender-edit').val(),
						receiver: $('.modal.show').find('.get-whatsapp-number-edit').val(),
						message: $('.modal.show').find('.edit-whatsapp-message-text').val(),
					},
				};
			} else if ( get_action === 'send_whatsapp_message_media' ) {
				action_data = {
					action: 'send_whatsapp_message_media',
					data: {
						sender: $('.modal.show').find('.get-phone-sender-edit').val(),
						receiver: $('.modal.show').find('.get-whatsapp-number-edit').val(),
						media_type: $('.modal.show').find('.get-media-type-edit').val(),
						media_url: $('.modal.show').find('.get-media-url-edit').val(),
					},
				};
			} else if ( get_action === 'condition' ) {
				action_data = {
					data: {
						action: 'condition',
						/*
						condition_content: {
							condition: $('.condition-settings-item.active').data('condition'),
							type: $('.condition-settings-item.active').find('.get-condition-type option:selected').val(),
							type_text: $('.condition-settings-item.active').find('.get-condition-type option:selected').text(),
							value: $('.condition-settings-item.active').find('.get-condition-value option:selected').val() || $('.condition-settings-item.active').find('.get-condition-value').val(),
							value_text: $('.condition-settings-item.active').find('.get-condition-value option:selected').text() || $('.condition-settings-item.active').find('.get-condition-value').val(),
						},*/
					},
				};
			}

			// display action data on debug mode
			if ( joinotify_builder_params.debug_mode ) {
				console.log(action_data);
			}

			// send request
			$.ajax({
				url: joinotify_builder_params.ajax_url,
				method: 'POST',
				data: {
					action: 'joinotify_save_action_edition',
					post_id: get_param_by_name('id'),
					action_id: get_action_id,
					new_action_data: JSON.stringify(action_data),
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
							// update workflow content
							$('#joinotify_builder_funnel').children('.funnel-trigger-group').html(response.workflow_content);
							$('.modal.show').find('.btn-close').click(); // close modal

							// set workflow content is ready
							$(document).trigger('workflowReady');

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
	 * Snippets PHP action
	 * 
	 * @since 1.1.0
	 * @package MeuMouse.com
	 */
	jQuery(document).on('workflowReady', function() {
		let textarea = document.querySelector('.joinotify-code-editor');
		// initialize CodeMirror
		var editor = CodeMirror.fromTextArea( textarea, {
			mode: 'application/x-httpd-php',
			theme: 'dracula',
			lineNumbers: true,
			styleActiveLine: true,
			matchBrackets: true,
			autoCloseBrackets: true,
			indentUnit: 4,
			tabSize: 4,
	  	});

		// set first line as <?php, only empty
		if ( editor.getValue().trim() === '' ) {
			editor.setValue('<?php\n\n');
	  	}

		// update textarea when there are changes in code editor
		editor.on('change', function() {
			textarea.value = editor.getValue();
	  	});

		// wait codemirror is ready
		setTimeout( function() {
			$('.joinotify-code-editor').siblings('.CodeMirror').after(`<div class="joinotify-resize-code-area">
				<svg class="icon-sm icon-dark opacity-75" xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24"><path d="M4 6h16v2H4zm0 5h16v2H4zm0 5h16v2H4z"></path></svg>
			</div>`);

			// change height from Codemirror
			$('.joinotify-resize-code-area').each( function() {
				let isResizing = false;
				let startY = 0;
				let startHeight = 0;
				let codeMirrorElement = $(this).siblings('.CodeMirror');
	
				if (codeMirrorElement.length === 0) {
					console.warn('Code area not found');
					
					return;
				}
	
				// on move mouse down
				$(this).on('mousedown', function(e) {
					e.preventDefault();
					isResizing = true;
					startY = e.clientY;
					startHeight = codeMirrorElement.outerHeight();
	
					$(document).on('mousemove', handleResize);
					$(document).on('mouseup', stopResize);
				});
	
				function handleResize(e) {
					if ( ! isResizing) {
						return;
					}

					let diffY = e.clientY - startY;
					let newHeight = startHeight + diffY;

					if (newHeight < 100) newHeight = 100; // min height
					codeMirrorElement.css('height', newHeight + 'px');
					codeMirrorElement.find('.CodeMirror-scroll').css('height', newHeight + 'px'); // set height on inside editor
				}
	
				function stopResize() {
					isResizing = false;
					$(document).off('mousemove', handleResize);
					$(document).off('mouseup', stopResize);
				}
			});
		}, 500);
	});
})(jQuery);