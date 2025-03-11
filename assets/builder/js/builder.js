( function($) {
	"use strict";

	/**
	 * Builder params
	 * 
	 * @since 1.1.0
	 */
	const params = joinotify_builder_params;
	
	/**
	 * Joinotify builder object variable
	 * 
	 * @since 1.1.0
	 * @version 1.2.0
	 * @package MeuMouse.com
	 */
	const Builder = {

		/**
		 * Purchased products condition array
		 * 
		 * @since 1.0.0
		 * @version 1.1.0
		 */
		purchasedProducts: [],

		/**
		 * Set condition type for add action
		 * 
		 * @since 1.0.0
		 * @version 1.1.0
		 */
		conditionType: '',

		/**
		 * Check if is condition action
		 * 
		 * @since 1.0.0
		 * @version 1.1.0
		 */
		isConditionAction: false,

		/**
		 * Get action ID
		 * 
		 * @since 1.0.0
		 * @version 1.1.0
		 */
		getActionID: '',

		/**
		 * Init
		 * 
		 * @since 1.1.0
		 */
		init: function() {
			// hide builder loader
			if ( Builder.getParamByName('id') === null ) {
				$('.joinotify-loader-container').addClass('d-none');
			}

			this.addTrigger();
			this.workflowSteps();
			this.triggerTabs();
			this.loadWorkflowData();
			this.uploadWorkflowTemplates();
			this.onWorkflowReady();
			this.onUpdatedWorkflow();
		},

		/**
		 * Run functions on workflow ready
		 * 
		 * @since 1.1.0
		 */
		onWorkflowReady: function() {
			// on workflow ready event
			$(document).on('workflowReady', function() {
				Builder.saveTriggerSettings();
				Builder.sidebarActions();
				Builder.addAction();
				Builder.removeAction();
				Builder.saveActionSettings();
				Builder.codeEditor();
				Builder.codePreview();
				Builder.correctTriggerSettingsModal();
				Builder.emojiPicker();
				Builder.dismissPlaceholdersTip();
				Builder.fetchAllGroups();
				Builder.searchWooProducts();
				Builder.changeWorkflowStatus();
				Builder.updateWorkflowTitle();
				Builder.initBootstrapComponents();
				Builder.adjustHeightCondition();
				Builder.changeVisibilityForElements();
				Builder.initDatePicker();
				Builder.whatsappMessagePreview();
				Builder.exportWorkflow();
				Builder.openMediaLibrary();
				Builder.runTestWorkflow();
				Builder.validateInputCurrency();
			});
		},

		/**
		 * Run functions on workflow updated
		 * 
		 * @since 1.1.0
		 * @version 1.2.0
		 */
		onUpdatedWorkflow: function() {
			// on workflow updated event
			$(document).on('updatedWorkflow', function() {
				Builder.changeVisibilityForElements();
				Builder.codeEditor();
				Builder.codePreview();
				Builder.correctTriggerSettingsModal();
				Builder.validateInputCurrency();
				Builder.emojiPicker();
				Builder.searchWooProducts();
			});
		},

		/**
		 * Select trigger on tabs
		 * 
		 * @since 1.0.0
		 * @version 1.1.0
		 */
		triggerTabs: function() {
			$(document).ready( function() {
				// Activate first builder tab on load page
				setTimeout(() => {
					$('.joinotify-triggers-tab-wrapper a.nav-tab').first().click();
				}, 500);
			});

			// Activate builder tab on click
			$(document).on('click', '.joinotify-triggers-tab-wrapper a.nav-tab', function() {
				let attr_href = $(this).attr('href');

				$('.joinotify-triggers-tab-wrapper a.nav-tab').removeClass('nav-tab-active');
				$('.triggers-content-wrapper .nav-content').removeClass('active');
				$(this).addClass('nav-tab-active');
				$('.triggers-content-wrapper').find(attr_href).addClass('active');
			});
		},

		/**
		 * Keep button width and height state
		 * 
		 * @since 1.1.0
		 * @param {object} btn | Button object
		 * @returns {object}
		 */
		keepButtonState: function(btn) {
			var btn_width = btn.width();
			var btn_height = btn.height();
			var btn_html = btn.html();
	  
			// keep original width and height
			btn.width(btn_width);
			btn.height(btn_height);
	  
			return {
				width: btn_width,
				height: btn_height,
				html: btn_html,
			};
	  	},

		/**
		 * Expand and collapse sidebar actions
		 * 
		 * @since 1.1.0
		 */
		expandSidebar: function() {
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
		},

		/**
		 * Close sidebar actions
		 * 
		 * @since 1.0.0
		 * @version 1.1.0
		 */
		closeSidebar: function() {
			// on close main sidebar actions
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
	
					Builder.isConditionAction = false;
					$('.action-item.locked').removeClass('locked');
				}
			});
		},

		/**
		 * Select condition action
		 * 
		 * @since 1.0.0
		 * @version 1.1.0
		 */
		selectCondition: function() {
			$(document).on('click', '.condition-item', function() {
				var selected_condition = $(this).data('condition');
	
				$('.condition-item').removeClass('active');
				$('.condition-settings-item').removeClass('active');
				$(this).toggleClass('active');
				$('.condition-settings-item[data-condition="'+ selected_condition +'"]').addClass('active');
	
				// enable add action button if has condition item selected
				if ( $('.condition-item').hasClass('active') ) {
					$('#add_action_condition').prop('disabled', false);
				} else {
					$('#add_action_condition').prop('disabled', true);
				}

				// remove class for all required settings
				$('.condition-settings-item .required-setting').removeClass('required-setting');

				// enable required settings
				$('.condition-settings-item[data-condition="' + selected_condition + '"]').find('select, input, textarea').each( function() {
					// Verifica se o elemento originalmente possuía a classe "required-setting"
					if ( $(this).attr('data-original-required') === 'true' ) {
						$(this).addClass('required-setting');
					}
				});

				Builder.enableAddActionButton();
			});
		},

		/**
		 * Reset settings for all actions
		 * 
		 * @since 1.0.0
		 * @version 1.1.0
		 */
		resetActionSettings: function() {
			var container = $('.offcanvas');

			// time delay action and component
			container.find('.set-time-delay-type option:first').attr('selected','selected');
			container.find('.get-wait-value').val('');
			container.find('.get-wait-period option:first').attr('selected','selected');
			container.find('.get-date-value').val('');
			container.find('.get-time-value').val('');

			// whatsapp message action
			container.find('.get-whatsapp-receiver').val('');
			container.find('.get-whatsapp-media-url').val('');

			// condition action
			container.find('.condition-item.active').removeClass('active');
			container.find('.condition-settings-item.active').removeClass('active');
			container.find('.joinotify_condition_node_point').removeClass('active');

			// reset condition selectors
			container.find('.get-condition-type').each( function() {
				$(this).find('option:first').attr('selected','selected');
			});

			// reset condition values
			container.find('.get-condition-value').each( function() {
				$(this).find('option:first').attr('selected','selected') || $(this).val('');
			});

			// get CodeMirror instance
			let code_editor = $('#offcanvas_snippet_php').find('.joinotify-code-editor').next('.CodeMirror').get(0).CodeMirror;

			// set to default text on code editor
			if (code_editor) {
				code_editor.setValue("<?php\n\n");
			}

			// coupon code action
			container.find('.generate-coupon-code').prop('checked', false);
			container.find('.set-coupon-code').val('');
			container.find('.set-coupon-description').val('');
			container.find('.set-coupon-discount-type option:first').attr('selected','selected');
			container.find('.set-coupon-discount-value').val('');
			container.find('.allow-free-shipping').prop('checked', false);
			container.find('.set-expires-coupon').prop('checked', false);
		},

		/**
		 * Upload templates
		 * 
		 * @since 1.0.0
		 * @version 1.1.0
		 */
		uploadWorkflowTemplates: function() {
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
					Builder.importWorkflow( file, $(this) );
				}
			});

			// Adds a change event handler to the input file
			$('#upload_template_file').on('change', function(e) {
				var file = e.target.files[0];

				Builder.importWorkflow( file, $(this).parents('.dropzone-license') );
			});
		},

		/**
		 * Import workflow file
		 * 
		 * @since 1.0.0
		 * @version 1.1.0
		 * @param {string} file | File
		 * @param {string} dropzone | Dropzone div
		 * @returns void
		 */
		importWorkflow: function(file, dropzone) {
			if (file) {
				var filename = file.name;
				var formData = new FormData();

				formData.append('action', 'joinotify_import_workflow_templates');
				formData.append('security', params.import_nonce);
				formData.append('file', file);

				if (filename) {
					dropzone.children('.file-list').removeClass('d-none').text(filename);
				}

				dropzone.addClass('file-processing');
				$('#joinotify_send_import_files').prop('disabled', false);

				// send request on click button
				$('#joinotify_send_import_files').on('click', function(e) {
					e.preventDefault();

					var btn = $(this);
					var button_state = Builder.keepButtonState(btn);

					$.ajax({
						url: params.ajax_url,
						type: 'POST',
						data: formData,
						processData: false,
						contentType: false,
						beforeSend: function() {
							btn.prop('disabled', true).html('<span class="spinner-border spinner-border-sm"></span>');
						},
						success: function(response) {
							if (params.debug_mode) {
								console.log(response);
							}

							try {
								if (response.status === 'success') {
									dropzone.addClass('file-uploaded').removeClass('file-processing');
									dropzone.children('.spinner-border').remove();
									dropzone.append('<div class="upload-notice d-flex flex-column align-items-center"><svg xmlns="http://www.w3.org/2000/svg" width="32" height="32" viewBox="0 0 24 24"><path fill="#22c55e" d="M12 2C6.486 2 2 6.486 2 12s4.486 10 10 10 10-4.486 10-10S17.514 2 12 2zm0 18c-4.411 0-8-3.589-8-8s3.589-8 8-8 8 3.589 8 8-3.589 8-8 8z"></path><path fill="#22c55e" d="M9.999 13.587 7.7 11.292l-1.412 1.416 3.713 3.705 6.706-6.706-1.414-1.414z"></path></svg><span>' + response.dropfile_message + '</span></div>');
									dropzone.children('.file-list').addClass('d-none');

									// success response
									Builder.displayToast('success', response.toast_header_title, response.toast_body_title);

									// redirect for edition of imported workflow
									setTimeout(() => {
										window.location.href = response.redirect;
									}, 1000);
								} else {
									dropzone.addClass('invalid-file').removeClass('file-processing');
									dropzone.children('.drag-text').removeClass('d-none');
									dropzone.children('.drag-and-drop-file').removeClass('d-none');
									dropzone.children('.upload-license-key').removeClass('d-none');
									dropzone.children('.file-list').addClass('d-none');

									// error response
									Builder.displayToast('error', response.toast_header_title, response.toast_body_title);
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
							btn.prop('disabled', false).html(button_state.html);
						},
					});
				});
			} else {
				$('#joinotify_send_import_files').prop('disabled', true);
			}
		},

		/**
		 * Initialize sidebar actions
		 * 
		 * @since 1.1.0
		 */
		sidebarActions: function() {
			Builder.openSidebar();
			Builder.closeSidebar();
			Builder.expandSidebar();
		},

		/**
		 * Open sidebar actions
		 * 
		 * @since 1.0.0
		 * @version 1.1.0
		 */
		openSidebar: function() {
			const container_actions = $('#joinotify_actions_group');

			// on click on plus icon
			$(document).on('click', '.funnel_add_action', function() {
				let btn = $(this);
	
				Builder.conditionType = btn.data('condition');
	
				// when click on add action inside condition
				if ( btn.data('action') === 'condition' ) {
					if ( ! container_actions.hasClass('active') ) {
						container_actions.addClass('active');
						$('#joinotify_builder_funnel').addClass('waiting-select-action');
						$('.funnel_add_action').children('.plusminus').addClass('active');
					}
	
					Builder.isConditionAction = true;
					Builder.getActionID = btn.data('action-id');
	
					$('.joinotify_condition_node_point').removeClass('active'); // remove active container
					btn.parent('.add_condition_inside_node_point').parent('.joinotify_condition_node_point').addClass('active');
					$('.action-item[data-action="time_delay"]').addClass('locked');
					$('.action-item[data-action="condition"]').addClass('locked');
				} else {
					container_actions.toggleClass('active');
					Builder.isConditionAction = false;
					Builder.getActionID = '';
	
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
	
						Builder.isConditionAction = false;
						$('.action-item.locked').removeClass('locked');
					}
				}
			});
		},

		/**
		 * Display toast component
		 * 
		 * @since 1.0.0
		 * @version 1.1.0
		 * @param {string} type | Toast type (success, danger...)
		 * @param {string} header_title | Header title for toast
		 * @param {string} body_title | Body title for toast
		 * @package MeuMouse.com
		 */
		displayToast: function(type, header_title, body_title) {
			var toast_class = '';
			var header_class = '';
			var icon = '';

			if (type === 'success') {
				toast_class = 'toast-success';
				header_class = 'bg-success text-white';
				icon = '<svg class="icon icon-white me-2" xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24"><path d="M12 2C6.486 2 2 6.486 2 12s4.486 10 10 10 10-4.486 10-10S17.514 2 12 2zm0 18c-4.411 0-8-3.589-8-8s3.589-8 8-8 8 3.589 8 8-3.589 8-8 8z"></path><path d="M9.999 13.587 7.7 11.292l-1.412 1.416 3.713 3.705 6.706-6.706-1.414-1.414z"></path></svg>'
			} else if (type === 'error') {
				toast_class = 'toast-danger';
				header_class = 'bg-danger text-white';
				icon = '<svg class="icon icon-white me-2" xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24"><path d="M12 2C6.486 2 2 6.486 2 12s4.486 10 10 10 10-4.486 10-10S17.514 2 12 2zm0 18c-4.411 0-8-3.589-8-8s3.589-8 8-8 8 3.589 8 8-3.589 8-8 8z"></path><path d="M11 11h2v6h-2zm0-4h2v2h-2z"></path></svg>';
			} else if (type === 'warning') {
				toast_class = 'toast-warning';
				header_class = 'bg-warning text-white';
				icon = '<svg class="icon icon-white me-2" xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24"><path d="M12 2C6.486 2 2 6.486 2 12s4.486 10 10 10 10-4.486 10-10S17.514 2 12 2zm0 18c-4.411 0-8-3.589-8-8s3.589-8 8-8 8 3.589 8 8-3.589 8-8 8z"></path><path d="M11 11h2v6h-2zm0-4h2v2h-2z"></path></svg>';
			} else {
				// if unknown type, use default values
				toast_class = 'toast-secondary';
				header_class = 'bg-secondary text-white';
				icon = '';
			}

			// generate uniq id for toast
			var toast_id = 'toast-' + Math.random().toString(36).substr(2, 9);

			// build toast HTML
			var toast_html = `<div id="${toast_id}" class="toast ${toast_class} show">
				<div class="toast-header ${header_class}">
						${icon}
						<span class="me-auto">${header_title}</span>
						<button class="btn-close btn-close-white ms-2 hide-toast" type="button" data-bs-dismiss="toast" aria-label="Close"></button>
				</div>
				<div class="toast-body">${body_title}</div>
			</div>`;

			// add toast on builder DOM
			$('#joinotify-automations-builder').prepend(toast_html);

			// fadeout after 3 seconds
			setTimeout(() => {
				jQuery('#' + toast_id).fadeOut('fast');
			}, 3000);

			// remove toast after 3,5 seconds
			setTimeout(() => {
				jQuery('#' + toast_id).remove();
			}, 3500);
		},

		/**
		 * Initialize Bootstrap components
		 * 
		 * @since 1.0.0
		 * @version 1.1.0
		 */
		initBootstrapComponents: function() {
			// init tooltips
			const tooltipTriggerList = document.querySelectorAll('[data-bs-toggle="tooltip"]');
			const tooltipList = [...tooltipTriggerList].map(tooltipTriggerEl => new bootstrap.Tooltip(tooltipTriggerEl));

			// Prevent reload page for Bootstrap modals and move for prepend #wpcontent for prevent z-index bug
			$(document).on('click', 'button[data-bs-toggle="modal"], a[data-bs-toggle="modal"]', function(e) {
				e.preventDefault();

				let target_modal = $(this).attr('data-bs-target');
				let detached_modal = $(target_modal).detach();

				$('#wpcontent').prepend(detached_modal);
			});
		},

		/**
		 * Get URL parameter by name
		 * 
		 * @since 1.0.0
		 * @version 1.1.0
		 * @param {string} name | Parameter name
		 * @returns Parameter value
		 * @package MeuMouse.com
		 */
		getParamByName: function(name) {
			let url = window.location.href;
			name = name.replace(/[\[\]]/g, "\\{text}");
			let regex = new RegExp("[?&]" + name + "(=([^&#]*)|&|#|$)"), results = regex.exec(url);

			if (!results) return null;
			if (!results[2]) return '';
			
			return decodeURIComponent( results[2].replace(/\+/g, " ") );
		},

		/**
		 * Add query params on URL
		 * 
		 * @since 1.0.0
		 * @version 1.1.0
		 * @param {string} param | Parameter name
		 * @param {string} value | Parameter value
		 */
		addQueryParam: function(param, value) {
			// get current URL
			var url = new URL(window.location.href);

			// add or update URL params
			url.searchParams.set( param, value );

			// update URL without reload page
			window.history.pushState( {}, '', url );
		},

		/**
		 * Correctly display trigger settings modal
		 * 
		 * @since 1.0.0
		 * @version 1.1.0
		 */
		correctTriggerSettingsModal: function() {
			$('.joinotify-trigger-details').each( function() {
				// Removes the current element from the DOM and stores it for reinsertion elsewhere
				var element = $(this).detach();
				
				// Insert the element after #wpadminbar
				element.insertAfter('#wpadminbar');
			});
		},

		/**
		 * Adjust height for condition elements
		 * 
		 * @since 1.0.0
		 * @version 1.1.0
		 */
		adjustHeightCondition: function() {
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

				// Set the height of the container and wrapper elements
				container.css('height', container_height);
				wrapper.css('height', wrapper_height);
			});
		},

		/**
		 * Validate format currency on input
		 * 
		 * @since 1.1.0
		 */
		validateInputCurrency: function() {
			$(document).on('input', '.format-currency', function() {
				let input = $(this);
				let value = input.val();
	
				// remove all non-numeric characters except commas
				value = value.replace(/[^0-9,]/g, '');
	
				// replace multiple commas and keep only one as the decimal separator
				let parts = value.split(',');

				if ( parts.length > 2 ) {
					value = parts[0] + ',' + parts.slice(1).join('');
				}
	
				// remove zeros on the left, but keep at least one zero
				value = value.replace(/^0+(?=\d)/, '');
	
				// if has decimal separator, format thousands correctly
				let int_part = parts[0].replace(/\B(?=(\d{3})+(?!\d))/g, '.');
				let decimal_part = parts[1] !== undefined ? ',' + parts[1] : '';
				let formatted_value = int_part + decimal_part;
	
				// update the input with the formatted value
				input.val(formatted_value);
			});
	  
			// lock invalid characters on keypress
			$(document).on('keypress', '.format-currency', function(e) {
				let char = String.fromCharCode(e.which);
				
				// allow only numbers and comma for decimal separator
				if ( !/[0-9,]/.test(char) ) {
					e.preventDefault();
				}
			});
	  	},

		/**
		 * Enable add trigger button
		 * 
		 * @since 1.0.0
		 * @version 1.2.0
		 */
		enableAddTriggerButton: function() {
			// set default workflow name
			$('#joinotify_set_workflow_name').val(params.i18n.default_workflow_name);
			
			/**
			 * Check if workflow name and trigger are filled
			 * 
			 * @since 1.0.0
			 * @version 1.1.0
			 */
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
				let value = $(this).val();

				check_requirements();
			});

			// Select trigger event
			$(document).on('click', '.trigger-item', function() {
				$('.trigger-item').removeClass('active');
				$(this).toggleClass('active');

				check_requirements();
			});
		},
	
		/**
		 * Add trigger element
		 * 
		 * @since 1.0.0
		 * @version 1.1.0
		 * @package MeuMouse.com
		 */
		addTrigger: function() {
			Builder.enableAddTriggerButton();
			
			// Proceed to builder funnel
			$(document).on('click', '#joinotify_proceed_step_funnel', function(e) {
				e.preventDefault();

				var get_title = $('#joinotify_set_workflow_name').val();
				var post_id = Builder.getParamByName('id');
				var btn = $(this);
				var button_state = Builder.keepButtonState(btn);

				// send request
				$.ajax({
					url: params.ajax_url,
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
						if (params.debug_mode) {
							console.log(response);
						}

						try {
							if ( response.status === 'success' && response.proceed === true ) {
								// If post_id was not set, we need to add it to the URL
								if ( ! post_id ) {
									Builder.addQueryParam('id', response.post_id);
									post_id = response.post_id;

									$('#joinotify_workflow_header_title_container').append(response.workflow_status);
								} else {
									$('#joinotify_workflow_status').replaceWith(response.workflow_status);
								}

								$('#joinotify_actions_wrapper').html(response.sidebar_actions);
								$('#offcanvas_condition').find('.offcanvas-body').html(response.condition_selectors);
								$('#offcanvas_send_whatsapp_message_text').find('.offcanvas-body').append(response.fetch_groups_trigger);
								$('#offcanvas_send_whatsapp_message_text').find('.offcanvas-body').append(response.placeholders_list);
								$('#offcanvas_send_whatsapp_message_media').find('.offcanvas-body').append(response.fetch_groups_trigger);
								$('#send_whatsapp_message_media').find('.offcanvas-body').append(response.placeholders_list);
								$('#add_action_condition').prop('disabled', true);
								$('#joinotify_triggers_group').removeClass('active');
								$('#joinotify_triggers_content').removeClass('active');
								$('#joinotify_workflow_status_switch').prop('disabled', false);
								$('#joinotify_workflow_title').text(get_title);
								$('#joinotify_edit_workflow_title').val(get_title);

								// update browser tab title
								document.title = get_title;

								// add trigger on funnel
								$('#joinotify_builder_funnel').children('.funnel-trigger-group').html(response.workflow_content);

								// check if has trigger settings
								setTimeout(() => {
									if (response.active_settings_modal) {
										let modal = response.active_settings_modal;
										let detached_modal = $(modal).detach();

										$('#wpcontent').prepend(detached_modal);
										$(modal).modal('show'); // display modal
									}
								}, 1000);

								// set workflow content is ready
								$(document).trigger('workflowReady');

								Builder.displayToast('success', response.toast_header_title, response.toast_body_title);
							} else {
								Builder.displayToast('error', response.toast_header_title, response.toast_body_title);
							}
						} catch (error) {
							console.log(error);
						}
					},
					complete: function() {
						btn.prop('disabled', false).html(button_state.html);
					},
				});
			});
		},

		/**
		 * Check requirements for enable add action button
		 * 
		 * @since 1.1.0
		 */
		enableAddActionButton: function() {
			var btn = '';

			// get add action button
			$(document).on('click', '.action-item', function() {
				setTimeout(() => {
					btn = $('.offcanvas.show').find('.add-funnel-action');
				}, 500);
			});

			// save required settings prop on elements for retrieve it later
			$('.condition-settings-item .required-setting').each( function() {
				$(this).attr('data-original-required', 'true');
		  	});

			/**
			 * Validate if required settings are valid
			 * 
			 * @since 1.1.0
			 * @returns boolean
			 */
			function validateRequiredSettings() {
				let is_valid = true;

				// find each required setting inside offcanvas
				$('.offcanvas.show .required-setting').each( function() {
					const element = $(this);
		
					if ( element.is('input') || element.is('textarea') ) {
						// check if input is empty
						if ( element.val().trim() === '' ) {
							is_valid = false;
						}
					} else if ( element.is('select') ) {
						//check if selected option is none
						if ( element.val() === 'none' ) {
							is_valid = false;
						}
					}
				});
		
				// enable button if required settings are valid
				if ( is_valid ) {
					$(btn).prop('disabled', false);
				} else {
					$(btn).prop('disabled', true);
				}
			}

			// validate required settings on change event
			$(document).on('change input blur keyup', '.required-setting', function() {
				validateRequiredSettings();
			});

			// validate required settings on click toggle switch
			$(document).on('click', '.validate-required-settings, .condition-item', function() {
				setTimeout( () => {
					validateRequiredSettings();
				}, 10);
			});

			// enable stop actions button
			setTimeout(() => {
				$('#add_action_stop_funnel').prop('disabled', false);
			}, 500);
		},

		/**
		 * Returns the action_data object formatted based on the action type
		 *
		 * @since 1.1.0
		 * @param {string} action_type - Action type
		 * @param {string} context - DOM context to fetch data from (e.g. '.offcanvas.show' or '.modal.show')
		 * @returns {object} action_data
		 */
		getActionData: function( action_type, context ) {
			var action_data = {};
			var container = $(context);
			const action_title = container.find('.offcanvas-title, .modal-title').text();

			switch ( action_type ) {
				case 'time_delay':
						var delay_type = container.find('.set-time-delay-type, .set-time-delay-type-edit').val();

						action_data = {
							type: 'action',
							data: {
								action: 'time_delay',
								title: action_title,
								delay_type: delay_type,
							},
						};

						if (delay_type === 'period') {
							action_data.data.delay_value = container.find('.get-wait-value').val();
							action_data.data.delay_period = container.find('.get-wait-period').val();
						} else if (delay_type === 'date') {
							action_data.data.date_value = container.find('.get-date-value').val();
							action_data.data.time_value = container.find('.get-time-value').val();
						}

						break;
				case 'send_whatsapp_message_text':
						action_data = {
							type: 'action',
							data: {
								action: 'send_whatsapp_message_text',
								title: action_title,
								sender: container.find('.get-whatsapp-phone-sender').val(),
								receiver: container.find('.get-whatsapp-receiver').val(),
								message: container.find('.set-whatsapp-message-text').val(),
							},
						};
						
						break;
				case 'send_whatsapp_message_media':
						action_data = {
							type: 'action',
							data: {
								action: 'send_whatsapp_message_media',
								title: action_title,
								sender: container.find('.get-whatsapp-phone-sender').val(),
								receiver: container.find('.get-whatsapp-receiver').val(),
								media_type: container.find('.get-whatsapp-media-type').val(),
								media_url: container.find('.get-whatsapp-media-url').val(),
							},
						};

						break;
				case 'condition':
						let condition = container.find('.condition-item.active').data('condition');
						let condition_type = container.find('.condition-settings-item.active .get-condition-type option:selected').val();

						action_data = {
							type: 'action',
							data: {
								action: 'condition',
								condition: condition,
								title: container.find('.condition-item.active .title').text(),
								condition_content: {
									condition: container.find('.condition-settings-item.active').data('condition'),
									type: condition_type,
									type_text: container.find('.condition-settings-item.active .get-condition-type option:selected').text(),
									value: container.find('.condition-settings-item.active .get-condition-value option:selected').val() || container.find('.condition-settings-item.active .get-condition-value').val(),
									value_text: container.find('.condition-settings-item.active .get-condition-value option:selected').text() || container.find('.condition-settings-item.active .get-condition-value').val(),
								},
							},
						};

						if ( condition === 'products_purchased' ) {
							action_data.data.condition_content.products = Builder.purchasedProducts;
							action_data.data.condition_content.value = '';
						} else if ( condition === 'user_meta' ) {
							action_data.data.condition_content.meta_key = container.find('.meta-key-wrapper').children('.get-condition-value').val();

							if ( condition_type === 'empty' || condition_type === 'not_empty' ) {
								action_data.data.condition_content.value = '';
							}
						} else if ( condition === 'field_value' ) {
							action_data.data.condition_content.field_id = container.find('.field-id-wrapper').children('.get-condition-value').val();

							if ( condition_type === 'empty' || condition_type === 'not_empty' ) {
								action_data.data.condition_content.value = '';
							}
						}

						break;
				case 'stop_funnel':
						action_data = {
							type: 'action',
							data: {
								action: 'stop_funnel',
							},
						};

						break;
				case 'snippet_php':
						action_data = {
							type: 'action',
							data: {
								action: 'snippet_php',
								title: action_title,
								snippet_php: container.find('.joinotify-code-editor').val(),
							},
						};

						break;
				case 'create_coupon':
						var delay_type = container.find('.set-time-delay-type').val();

						action_data = {
							type: 'action',
							data: {
								action: 'create_coupon',
								title: action_title,
								settings: {
									generate_coupon: container.find('.generate-coupon-code').prop('checked') ? 'yes' : 'no',
									coupon_code: container.find('.set-coupon-code').val() || '',
									coupon_description: container.find('.set-coupon-description').val(),
									discount_type: container.find('.set-coupon-discount-type').val(),
									coupon_amount: container.find('.set-coupon-discount-value').val(),
									free_shipping: container.find('.allow-free-shipping').prop('checked') ? 'yes' : 'no',
									coupon_expiry: container.find('.set-expires-coupon').prop('checked') ? 'yes' : 'no',
									expiry_data: {
										type: delay_type || '',
									},
									message: {
										sender: container.find('.get-whatsapp-phone-sender').val(),
										receiver: container.find('.get-whatsapp-receiver').val(),
										message: container.find('.set-whatsapp-message-text').val(),
									},
								},
							},
						};

						// add delay data on object
						if (delay_type === 'period') {
							action_data.data.settings.expiry_data.delay_value = container.find('.get-wait-value').val();
							action_data.data.settings.expiry_data.delay_period = container.find('.get-wait-period').val();
						} else if (delay_type === 'date') {
							action_data.data.settings.expiry_data.date_value = container.find('.get-date-value').val();
							action_data.data.settings.expiry_data.time_value = container.find('.get-time-value').val();
						}

						break;
			}

			return action_data;
		},

		/**
		 * Add action
		 * 
		 * @since 1.0.0
		 * @version 1.1.0
		 */
		addAction: function() {
			Builder.selectCondition();
			Builder.enableAddActionButton();

			// add action on workflow
			$(document).on('click', '.add-funnel-action', function(e) {
				e.preventDefault();

				var btn = $(this);
				var btn_state = Builder.keepButtonState(btn);
				var post_id = Builder.getParamByName('id');
				var action_type = btn.data('action');
				var action_data = Builder.getActionData( action_type, '.offcanvas.show' );

				// display action data on debug mode
				if ( params.debug_mode ) {
					console.log(action_data);
					console.log('Post ID: ', post_id);
				}

				// send ajax request
				$.ajax({
					url: params.ajax_url,
					method: 'POST',
					data: {
						action: 'joinotify_add_workflow_action',
						post_id: post_id,
						action_condition: Builder.isConditionAction,
						action_id: Builder.getActionID,
						condition_action: Builder.conditionType,
						workflow_action: JSON.stringify(action_data),
					},
					beforeSend: function() {
						btn.prop('disabled', true).html('<span class="spinner-border spinner-border-sm"></span>');
					},
					success: function(response) {
						if ( params.debug_mode ) {
							console.log(response);
						}

						try {
							if ( response.status === 'success' ) {
								// close active offcanvas
								$('.offcanvas.show').removeClass('show');

								// disable button for require new validation
								btn.prop('disabled', true);

								// reset options
								Builder.resetActionSettings();

								if (response.has_action) {
									$('#joinotify_builder_run_test').prop('disabled', false);
								} else {
									$('#joinotify_builder_run_test').prop('disabled', true);
								}

								// replace with new content updated
								$('#joinotify_builder_funnel').children('.funnel-trigger-group').html(response.workflow_content);

								// fire updated workflow event
								$(document).trigger('updatedWorkflow');

								Builder.displayToast('success', response.toast_header_title, response.toast_body_title);
							} else {
								Builder.displayToast('error', response.toast_header_title, response.toast_body_title);
							}
						} catch (error) {
							console.log(error);
						}
					},
					complete: function() {
						Builder.adjustHeightCondition();

						btn.html(btn_state.html);
						$('#joinotify_actions_group').removeClass('active');
						$('.condition-item.active').removeClass('active');
						$('#add_action_condition').prop('disabled', true);
						$('#joinotify_builder_funnel').removeClass('waiting-select-action');
					},
				});
			});
		},

		/**
		 * Remove action
		 * 
		 * @since 1.0.0
		 * @version 1.2.0
		 */
		removeAction: function() {
			$(document).on('click', '.exclude-action', function(e) {
				e.preventDefault();

				// get confirmation
				if ( ! confirm(params.i18n.confirm_exclude_action) ) {
					return;
				}

				var action = $(this);

				$.ajax({
					url: params.ajax_url,
					method: 'POST',
					data: {
						action: 'joinotify_delete_workflow_action',
						post_id: Builder.getParamByName('id'),
						action_id: action.data('action-id'),
					},
					beforeSend: function() {
						action.prop('disabled', true);
						action.closest('.funnel-action-item').addClass('placeholder-wave removing-action');
					},
					success: function(response) {
						if (params.debug_mode) {
							console.log(response);
						}

						try {
							if (response.status === 'success') {
								if (response.has_action) {
										$('#joinotify_builder_run_test').prop('disabled', false);
								} else {
										$('#joinotify_builder_run_test').prop('disabled', true);
								}

								$('#joinotify_builder_funnel').children('.funnel-trigger-group').html(response.workflow_content);

								// fire updated workflow event
								$(document).trigger('updatedWorkflow');

								Builder.displayToast('success', response.toast_header_title, response.toast_body_title);
							} else {
								Builder.displayToast('error', response.toast_header_title, response.toast_body_title);
							}
						} catch (error) {
							console.log(error);
						}
					},
					complete: function() {
						action.prop('disabled', false);
						action.closest('.funnel-action-item').removeClass('placeholder-wave removing-action');

						Builder.adjustHeightCondition();
					},
				});
			});
		},
	
		/**
		 * Change workflow status
		 * 
		 * @since 1.0.0
		 * @version 1.1.0
		 * @package MeuMouse.com
		 */
		changeWorkflowStatus: function() {
			$(document).on('click', '#joinotify_workflow_status_switch', function() {
				var btn = $(this);

				// send ajax request
				$.ajax({
					url: params.ajax_url,
					method: 'POST',
					data: {
						action: 'joinotify_update_workflow_status',
						status: btn.prop('checked') ? 'publish' : 'draft',
						post_id: Builder.getParamByName('id'),
					},
					beforeSend: function() {
						btn.prop('disabled', true);
					},
					complete: function() {
						btn.prop('disabled', false);
					},
					success: function(response) {
						if (params.debug_mode) {
							console.log(response);
						}

						try {
							if (response.status === 'success') {
								$('#joinotify_workflow_status_title').text(response.workflow_status);
								$('#joinotify_workflow_status').replaceWith(response.display_workflow_status);

								Builder.displayToast('success', response.toast_header_title, response.toast_body_title);
							} else {
								Builder.displayToast('error', response.toast_header_title, response.toast_body_title);
							}
						} catch (error) {
							console.log(error);
						}
					},
				});
			});
		},
	
		/**
		 * Load workflow data
		 * 
		 * @since 1.0.0
		 * @version 1.2.0
		 * @package MeuMouse.com
		 */
		loadWorkflowData: function() {
			// get parameter id from URL
			var id = Builder.getParamByName('id');

			if (id) {
				$.ajax({
					url: params.ajax_url,
					type: 'POST',
					data: {
							action: 'joinotify_load_workflow_data',
							post_id: id,
					},
					beforeSend: function() {
							$('.joinotify-loader-container').removeClass('d-none');
					},
					success: function(response) {
							if (params.debug_mode) {
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

									if ('publish' === response.workflow_status) {
										$('#joinotify_workflow_status_switch').prop('checked', true);
										$('#joinotify_workflow_status_title').text(params.i18n.status_active);
									}

									// workflow content
									$('#joinotify_builder_funnel').children('.funnel-trigger-group').html(response.workflow_content);

									// add conditions on sidebar
									$('#offcanvas_condition').find('.offcanvas-body').html(response.condition_selectors);

									// check if has trigger settings
									if (response.active_settings_modal) {
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
									Builder.displayToast('error', response.toast_header_title, response.toast_body_title);
								}
							} catch (error) {
								console.log(error);
							}
					},
					complete: function() {
						$('.joinotify-loader-container').addClass('d-none');

						Builder.adjustHeightCondition();
					},
					error: function(xhr, status, error) {
						console.error('Error on AJAX request:', xhr.responseText);
					},
				});
			}
		},
	
		/**
		 * Update workflow title action
		 * 
		 * @since 1.0.0
		 * @version 1.1.0
		 * @package MeuMouse.com
		 */
		updateWorkflowTitle: function() {
			$(document).on('click', '#joinotify_update_workflow_title', function(e) {
				e.preventDefault();

				var btn = $(this);
				var button_state = Builder.keepButtonState(btn);

				// send ajax request
				$.ajax({
					url: params.ajax_url,
					method: 'POST',
					data: {
						action: 'joinotify_update_workflow_title',
						post_id: Builder.getParamByName('id'),
						workflow_title: $('#joinotify_edit_workflow_title').val(),
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
								$('#joinotify_workflow_title').text(response.workflow_title);
								$('#joinotify_edit_workflow_title').val(response.workflow_title);
								$('#joinotify_set_workflow_name').val(response.workflow_title);

								// update browser tab title
								document.title = response.workflow_title;

								$('#edit_workflow_title').find('.btn-close').click();

								Builder.displayToast('success', response.toast_header_title, response.toast_body_title);
							} else {
								$('#edit_workflow_title').find('.btn-close').click();
								$('#joinotify_edit_workflow_title').val('');

								Builder.displayToast('error', response.toast_header_title, response.toast_body_title);
							}
						} catch (error) {
							console.log(error);
						}
					},
					complete: function() {
						btn.prop('disabled', false).html(button_state.html);
					},
				});
			});
		},

		/**
		 * Change visibility for time delay action settings
		 * 
		 * @since 1.0.0
		 * @version 1.1.0
		 */
		changeVisibilityForTimeDelay: function() {
			// change visibility for time delay action
			$(document).on('change', '.set-time-delay-type', function() {
				var select = $(this);
				var selected_option = select.val();

				select.parent('div').siblings('.wait-time-period-container').toggleClass('d-none', selected_option !== 'period');
				select.parent('div').siblings('.wait-time-period-container').find('.get-wait-value').toggleClass('required-setting', selected_option === 'period');
				select.parent('div').siblings('.wait-date-container').toggleClass('d-none', selected_option !== 'date');
				select.parent('div').siblings('.wait-date-container').find('.get-date-value').toggleClass('required-setting', selected_option === 'date');
			});

			// wait 500 miliseconds after ready DOM
			setTimeout(() => {
				$('.set-time-delay-type').each( function() {
					var select = $(this);
					var selected_option = select.val();

					select.parent('div').siblings('.wait-time-period-container').toggleClass('d-none', selected_option !== 'period');
					select.parent('div').siblings('.wait-time-period-container').find('.get-wait-value').toggleClass('required-setting', selected_option === 'period');
					select.parent('div').siblings('.wait-date-container').toggleClass('d-none', selected_option !== 'date');
					select.parent('div').siblings('.wait-date-container').find('.get-date-value').toggleClass('required-setting', selected_option === 'date');
				});
			}, 500);
		},

		/**
		 * Change visibility for coupon code action settings
		 * 
		 * @since 1.1.0
		 */
		changeVisibilityForCouponCode: function() {
			/**
			 * Toggle coupon code settings
			 * 
			 * @since 1.1.0
			 * @param {Object} toggle | Input toggle switch
			 */
			function toggleCouponCodeSettings(toggle) {
				var checked = toggle.prop('checked');
	
				// Hide/show coupon code input & add/remove required class
				toggle.parent('div').siblings('.coupon-code-wrapper').toggleClass('d-none', checked).find('.set-coupon-code').toggleClass('required-setting', ! checked);
			}
	  
			/**
			 * Toggle expires coupon code settings
			 * 
			 * @since 1.1.0
			 * @param {Object} toggle | Input toggle switch
			 */
			function toggleExpiresCoupon(toggle) {
				var checked = toggle.prop('checked');
				var select = toggle.parent('div').siblings('.select-time-delay-container').find('.set-time-delay-type');
	
				// Show/hide time delay options
				toggle.parent('div').siblings('.select-time-delay-container').toggleClass('d-none', ! checked);
	
				if ( select.val() === 'period' ) {
					toggle.parent('div').siblings('.wait-time-period-container').toggleClass('d-none', ! checked).find('.get-wait-value').toggleClass('required-setting', checked);
				} else if (select.val() === 'date') {
					toggle.parent('div').siblings('.wait-date-container').toggleClass('d-none', ! checked).find('.get-date-value').toggleClass('required-setting', checked);
				}
			}

			// Listen to change event for coupon code toggle
			$(document).on('change', '.generate-coupon-code', function() {
				toggleCouponCodeSettings( $(this) );
			});
	  
			// Listen to change event for expiration toggle
			$(document).on('change', '.set-expires-coupon', function() {
				toggleExpiresCoupon( $(this) );
			});
	  
			// Hide default container option from coupon time delay
			setTimeout(() => {
				$('#offcanvas_create_coupon').find('.wait-time-period-container').addClass('d-none');
				$('#offcanvas_create_coupon').find('.get-wait-value').removeClass('required-setting');

				// Apply settings on page load (for already checked elements)
				$('.generate-coupon-code').each( function() {
					toggleCouponCodeSettings( $(this) );
				});
		
				$('.set-expires-coupon').each( function() {
					toggleExpiresCoupon( $(this) );
				});
			}, 600);
	  	},

		/**
		 * Change visibility for conditions settings
		 * 
		 * @since 1.2.0
		 */
		changeVisibilityForConditions: function() {
			/**
			 * Toggle conditions settings
			 * 
			 * @since 1.2.0
			 * @param {Object} select | Input select
			 */
			function toggleConditionsSettings(select) {
				var select_value = select.val();

				// check select value
				if ( select_value === 'empty' || select_value === 'not_empty' ) {
					select.parent('div').siblings('.meta-value-wrapper, .field-value-wrapper').addClass('d-none').children('.get-condition-value').removeClass('required-setting');
				} else {
					select.parent('div').siblings('.meta-value-wrapper, .field-value-wrapper').removeClass('d-none').children('.get-condition-value').addClass('required-setting');
				}
			}


			// hide input condition value when is selected "empty" and "not empty"
			$(document).on('change', '.get-condition-type', function() {
				toggleConditionsSettings( $(this) );
			});

			// hide elements on page load
			setTimeout(() => {
				$('.get-condition-type').each( function() {
					toggleConditionsSettings( $(this) );
				});
			}, 600);
		},
	
		/**
		 * Change visibility for elements
		 * 
		 * @since 1.0.0
		 * @version 1.2.0
		 * @package MeuMouse.com
		 */
		changeVisibilityForElements: function() {
			Builder.changeVisibilityForTimeDelay();
			Builder.changeVisibilityForCouponCode();
			Builder.changeVisibilityForConditions();
		},
		
		/**
		 * Include Bootstrap date picker
		 * 
		 * @since 1.0.0
		 * @version 1.1.0
		 */
		initDatePicker: function() {
			/**
			 * Initialize Bootstrap datepicker
			 */
			$('.dateselect').datepicker({
				format: 'dd/mm/yyyy',
				todayHighlight: true,
				language: 'pt-BR',
			}).on('changeDate', function() {
				$(this).datepicker('hide'); // close date picker on select date
		  	});
			
			// open date picker on focus
			$(document).on('focus', '.dateselect', function(e) {
				if ( ! $(this).data('datepicker') ) {
					$(this).datepicker({
						format: 'dd/mm/yyyy',
						todayHighlight: true,
						language: 'pt-BR',
					});
				}

				// close date picker if the click was outside
				if ( ! $(e.target).closest('.dateselect').length ) {
					$('.dateselect').datepicker('hide');
				}
			});
		},
	
		/**
		 * Display WhatsApp message preview
		 * 
		 * @since 1.0.0
		 * @version 1.1.0
		 * @package MeuMouse.com
		 */
		whatsappMessagePreview: function() {
			$(document).on('input change blur change keyup', '.set-whatsapp-message-text', function() {
				var input = $(this);
				var text = input.val();
				var preview_message = $(this).parent('div').siblings('.preview-whatsapp-message-sender');

				// replace \n for <br> break row HTML element
				text = text.replace(/\n/g, '<br>');

				// replace {{ br }} for break row HTML element
				text = text.replace(/{{ br }}/g, '<br>');
	
				if (text.trim() !== '') {
					preview_message.addClass('active').html(text);
				} else {
					preview_message.removeClass('active').html('');
				}
			});
		},
		
		/**
		 * Initialize download data with archive
		 * 
		 * @since 1.0.0
		 * @param {array} data | File data array
		 * @param {string} filename | File name
		 * @param {string} type | File type
		 * @package MeuMouse.com
		 */
		downloadData: function(data, filename, type) {
			var file = new Blob([data], { type: type });
			var a = document.createElement('a');
			var url = URL.createObjectURL(file);
	
			a.href = url;
			a.download = filename;
			document.body.appendChild(a);
			a.click();
	
			setTimeout(() => {
				document.body.removeChild(a);
				window.URL.revokeObjectURL(url);  
			}, 0); 
		},
	
		/**
		 * Export workflow file action
		 * 
		 * @since 1.0.0
		 * @version 1.1.0
		 * @package MeuMouse.com
		 */
		exportWorkflow: function() {
			$('#joinotify_export_workflow').on('click', function(e) {
				e.preventDefault();

				var post_id = Builder.getParamByName('id');

				// send ajax request
				$.ajax({
					url: params.ajax_url,
					method: 'POST',
					data: {
						action: 'joinotify_export_workflow',
						post_id: post_id,
						security: params.export_nonce,
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
								Builder.downloadData(JSON.stringify(response.export_data, null, 2), filename, 'application/json');

								Builder.displayToast('success', response.toast_header_title, response.toast_body_title);
							} else {
								Builder.displayToast('error', response.toast_header_title, response.toast_body_title);
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
		},
		
		/**
		 * Activate workflow steps
		 * 
		 * @since 1.0.0
		 * @version 1.2.0
		 */
		workflowSteps: function() {
			// choose workflow template
			$(document).on('click', '.choose-template', function(e) {
				e.preventDefault();

				var btn = $(this);
				var button_state = Builder.keepButtonState(btn);
				var template_type = btn.data('template');

				btn.prop('disabled', true).html('<span class="spinner-border spinner-border-sm"></span>');

				setTimeout(() => {
					btn.prop('disabled', false).html(button_state.html);
				}, 1000);

				if ( template_type === 'scratch' ) {
					setTimeout(() => {
						$('#joinotify_start_choose_container').removeClass('active');
						$('#joinotify_choose_template_container').removeClass('active');
						$('#joinotify_triggers_group').addClass('active');
						$('#joinotify_triggers_content').addClass('active');
						$('#joinotify_builder_navbar').addClass('active');
					}, 1000);
				} else if ( template_type === 'template' ) {
					let templates_container = $('#joinotify_template_library_container');

					setTimeout(() => {
						$('#joinotify_start_choose_container').removeClass('active slide-left-animation').addClass('slide-right-animation');
						templates_container.addClass('active slide-left-animation').removeClass('slide-right-animation');
					}, 1000);

					if ( ! templates_container.hasClass('templates-loaded') ) {
						// get templates
						$.ajax({
							url: params.ajax_url,
							method: 'POST',
							data: {
								action: 'joinotify_get_workflow_templates',
							},
							success: function(response) {
								if (params.debug_mode) {
									console.log(response);
								}

								try {
									if (response.status === 'success') {
										$('.joinotify-templates-group').html(response.template_html);
										templates_container.addClass('templates-loaded').removeClass('templates-counted');
									} else {
										Builder.displayToast('error', response.toast_header_title, response.toast_body_title);
									}
								} catch (error) {
									console.log(error);
								}
							},
							error: function(error) {
								console.error(error);
							},
						});
					}
				} else if ( template_type === 'import' ) {
					setTimeout(() => {
						$('#joinotify_start_choose_container').removeClass('active slide-left-animation').addClass('slide-right-animation');
						$('#joinotify_import_template_container').addClass('active slide-left-animation').removeClass('slide-right-animation');
					}, 1000);
				}
			});

			// return to start
			$(document).on('click', '.return-to-start', function(e) {
				e.preventDefault();

				$('#joinotify_triggers_group').removeClass('active');
				$('#joinotify_triggers_content').removeClass('active');
				$('#joinotify_builder_navbar').removeClass('active');
				$('#joinotify_template_library_container').removeClass('active');
				$('#joinotify_choose_template_container').addClass('active');
				$('#joinotify_start_choose_container').addClass('active');
			});
	
			// cancel import file and return to start
			$(document).on('click', '#joinotify_cancel_import_template', function(e) {
				e.preventDefault();

				$('#joinotify_import_template_container').removeClass('active');
				$('#joinotify_start_choose_container').addClass('active');
			});
		},
	
		/**
		 * Open WordPress media library popup on click
		 * 
		 * @since 1.0.0
		 * @version 1.2.0
		 */
		openMediaLibrary: function() {
			var file_frame;
	
			// open media library
			$(document).on('click', '.set-media-url', function(e) {
				e.preventDefault();

				var btn = $(this);

				// If the media frame already exists, reopen it
				if (file_frame) {
					file_frame.open();
					return;
				}

				// create media frame
				file_frame = wp.media.frames.file_frame = wp.media({
					title: params.i18n.set_media_title,
					button: {
						text: params.i18n.use_this_media_title,
					},
					multiple: false,
				});

				// When an image is selected, execute the callback function
				file_frame.on('select', function() {
					var attachment = file_frame.state().get('selection').first().toJSON();
					var image_url = attachment.url;
				
					// Update the input value with the URL of the selected image
					btn.siblings('.get-media-url').val(image_url).trigger('change'); // Force change
				});

				file_frame.open();
			});
		},
		
		/**
		 * Run test workflow action
		 * 
		 * @since 1.0.0
		 * @version 1.1.0
		 */
		runTestWorkflow: function() {
			$(document).on('click', '#joinotify_builder_run_test', function(e) {
				e.preventDefault();

				var btn = $(this);
				var button_state = Builder.keepButtonState(btn);

				// send ajax request
				$.ajax({
					url: params.ajax_url,
					method: 'POST',
					data: {
						action: 'joinotify_run_workflow_test',
						post_id: Builder.getParamByName('id'),
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
								Builder.displayToast('success', response.toast_header_title, response.toast_body_title);
							} else {
								Builder.displayToast('error', response.toast_header_title, response.toast_body_title);
							}
						} catch (error) {
							console.log(error);
						}
					},
					complete: function() {
						btn.prop('disabled', false).html(button_state.html);
					},
					error: function(error) {
						console.error(error);
					},
				});
			});
		},
	
		/**
		 * Dismiss placeholders tip
		 * 
		 * @since 1.1.0
		 */
		dismissPlaceholdersTip: function() {
			$(document).on('click', '#joinotify_dismiss_placeholders_tip', function(e) {
				e.preventDefault();

				// send ajax request
				$.ajax({
					url: params.ajax_url,
					method: 'POST',
					data: {
						action: 'joinotify_dismiss_placeholders_tip',
					},
					success: function(response) {
						if (params.debug_mode) {
							console.log(response);
						}
					},
					error: function(error) {
						console.log(error);
					},
				});
			});
		},
		
		/**
		 * Fetch all groups information
		 * 
		 * @since 1.1.0
		 * @version 1.2.0
		 */
		fetchAllGroups: function() {
			// get groups details on open modal
			$(document).on('click', '#joinotify_fetch_all_groups', function(e) {
				e.preventDefault();

				// check if content has been loaded
				if ( $('#joinotify_fetch_all_groups_container').hasClass('content-loaded') ) {
					return;
				}

				// send request
				$.ajax({
					url: params.ajax_url,
					method: 'POST',
					data: {
						action: 'joinotify_fetch_all_groups',
						sender: $('.offcanvas.show').find('.get-whatsapp-phone-sender').val(),
					},
					success: function(response) {
						if ( params.debug_mode ) {
							console.log(response);
						}

						try {
							if (response.status === 'success') {
								$('#joinotify_fetch_all_groups_container').addClass('content-loaded').find('.modal-body').html(response.groups_details_html);
							} else {
								$('#joinotify_fetch_all_groups_container').find('.btn-close').click();
								Builder.displayToast('error', response.toast_header_title, response.toast_body_title);
							}
						} catch (error) {
							console.log(error);
						}
					},
					error: function(error) {
						console.error(error);
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
						<span>${params.i18n.copy_group_id}</span>
					</div>`);

					setTimeout(() => {
						group_item.find('.confirm-copy-ui').removeClass('active');
					}, 800);

					setTimeout(() => {
						group_item.find('.confirm-copy-ui').remove();
					}, 1000);
				}).catch( function(error) {
					console.error('Error on copy group ID: ' + error);
				});
			});
		},
		
		/**
		 * Save action settings
		 * 
		 * @since 1.1.0
		 * @version 1.2.0
		 */
		saveActionSettings: function() {
			// check action on display modal
			$(document).on('shown.bs.modal', '.modal.show', function() {
				var modal = $(this);
				var action_type = modal.find('.save-action-edit').data('action');
				var initial_data = Builder.getActionData(action_type, modal);
				
				// storage initial data in the modal itself
				modal.data('initial_data', JSON.stringify(initial_data));
			
				let save_button = modal.find('.save-action-edit');

				// disable button if action is snippet php
				if ( save_button.data('action') === 'snippet_php' ) {
					save_button.prop('disabled', true);
				}
			});
			
			// check changes in inputs inside the modal
			$(document).on('input change', '.modal.show :input', function() {
				var modal = $(this).closest('.modal.show');
				var action_type = modal.find('.save-action-edit').data('action');
				var current_data = Builder.getActionData(action_type, modal);
			
				// compare the current data with the initial data
				var is_changed = JSON.stringify(current_data) !== modal.data('initial_data');
				let save_button = modal.find('.save-action-edit');

				if ( save_button.data('action') === 'snippet_php' ) {
					// active or disable button based on the change
					save_button.prop('disabled', ! is_changed);
				}
			});
			
			// save action on click button
			$(document).on('click', '.save-action-edit', function(e) {
				e.preventDefault();

				var btn = $(this);
				var button_state = Builder.keepButtonState(btn);
				var action_type = btn.data('action');
				var get_action_id = btn.data('action-id');
				var action_data = Builder.getActionData( action_type, '.modal.show' );

				// display action data on debug mode
				if (params.debug_mode) {
					console.log(action_data);
				}

				// send request
				$.ajax({
					url: params.ajax_url,
					method: 'POST',
					data: {
						action: 'joinotify_save_action_edition',
						post_id: Builder.getParamByName('id'),
						action_id: get_action_id,
						new_action_data: JSON.stringify(action_data),
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
								// close settings modal
								$('.modal.show').find('.btn-close').click(); // close modal

								// update workflow content
								$('#joinotify_builder_funnel').children('.funnel-trigger-group').html(response.workflow_content);

								// fire updated workflow event
								$(document).trigger('updatedWorkflow');

								Builder.displayToast('success', response.toast_header_title, response.toast_body_title);
							} else {
								Builder.displayToast('error', response.toast_header_title, response.toast_body_title);
							}
						} catch (error) {
							console.log(error);
						}
					},
					complete: function() {
						btn.prop('disabled', false).html(button_state.html);
					},
				});
			});
		},
	
		/**
		 * Initialize code editor
		 * 
		 * @since 1.1.0
		 * @version 1.2.0
		 */
		codeEditor: function() {
			// initialize CodeMirror for each code editor
			$('.joinotify-code-editor').each( function() {
				let textarea = this;
				
				// check if editor is already initialized
				if ( ! $(textarea).hasClass('initiliazed-editor') ) {
					var editor = CodeMirror.fromTextArea( textarea, {
						mode: 'application/x-httpd-php',
						theme: 'dracula',
						lineNumbers: true,
						matchBrackets: true,
						autoCloseBrackets: true,
						indentUnit: 4,
						tabSize: 4,
						autoRefresh: true,
					});

					// add class to textarea to prevent multiple initialization
					$(textarea).addClass('initiliazed-editor');

					// set PHP open tag on first line if empty textarea
					if ( editor.getValue().trim() === '' ) {
						editor.setValue('<?php\n\n');
					} else {
						// Load existing content into CodeMirror
						editor.setValue(textarea.value);
					}

					// update the textarea when has changes on editor
					editor.on('change', function() {
						let value = editor.getValue();
						
						// ensures that backslashes are correctly escaped
						value = value.replace(/\\/g, '\\\\');
					
						textarea.value = value;
						$(textarea).trigger('change');
					});

					// wait CodeMirror is ready before add resize element
					setTimeout(() => {
						let codemirror_element = $(textarea).next('.CodeMirror');

						if ( codemirror_element.length > 0 ) {
							let resize_handle = $(`<div class="joinotify-resize-code-area"><svg class="icon-sm icon-dark opacity-75" xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24"><path d="M4 6h16v2H4zm0 5h16v2H4zm0 5h16v2H4z"></path></svg></div>`);

							codemirror_element.after(resize_handle);

							let is_resizing = false;
							let startY = 0;
							let startHeight = 0;

							/**
							 * Handle resize CodeMirror height
							 * 
							 * @since 1.1.0
							 * @param {object} e | Event object
							 */
							resize_handle.on('mousedown', function(e) {
								e.preventDefault();
								is_resizing = true;
								startY = e.clientY;
								startHeight = codemirror_element.outerHeight();

								$(document).on('mousemove', handle_resize);
								$(document).on('mouseup', stop_resize);
							});

							/**
							 * Handle resize CodeMirror height
							 * 
							 * @since 1.1.0
							 * @param {object} e | Event object 
							 * @returns void
							 */
							function handle_resize(e) {
								if ( ! is_resizing ) return;

								let diffY = e.clientY - startY;
								let newHeight = startHeight + diffY;

								if ( newHeight < 100 ) newHeight = 100; // Minimum height
								codemirror_element.css('height', newHeight + 'px');
								codemirror_element.find('.CodeMirror-scroll').css('height', newHeight + 'px');
							}

							/**
							 * Stop resize CodeMirror height
							 * 
							 * @since 1.1.0
							 * @returns void
							 */
							function stop_resize() {
								is_resizing = false;

								$(document).off('mousemove', handle_resize);
								$(document).off('mouseup', stop_resize);
							}
						} else {
							console.warn('CodeMirror element not found for textarea:', textarea);
						}
					}, 300);
				}
			});
		},

		/**
		 * Initialize code preview on workflow
		 * 
		 * @since 1.1.0
		 */
		codePreview: function() {
			// initialize CodeMirror for each code preview
			$('.joinotify-code-preview').each( function() {
				let textarea_preview = this;

				let preview = CodeMirror.fromTextArea( textarea_preview, {
					mode: 'application/x-httpd-php',
					theme: 'dracula',
					lineNumbers: true,
					matchBrackets: true,
					autoCloseBrackets: true,
					indentUnit: 4,
					tabSize: 4,
					readOnly: 'nocursor',
				});

				// set initialized this code preview
				$(textarea_preview).addClass('initiliazed-preview');

				// adjust the height of the editor to fit the code
				preview.setSize("100%", "auto");
				
				$(preview.getWrapperElement()).addClass('preview');
			});
		},

		/**
		 * Save trigger settings
		 * 
		 * @since 1.1.0
		 * @package MeuMouse.com
		 */
		saveTriggerSettings: function() {
			$(document).on('click', '.save-trigger-settings', function(e) {
				e.preventDefault();

				var btn = $(this);
				var button_state = Builder.keepButtonState(btn);
				var get_trigger = btn.data('trigger');
				var get_trigger_id = btn.data('trigger-id');
				var trigger_data = {};

				// collect specific trigger settings data
				if ( get_trigger === 'woocommerce_order_status_changed' ) {
					trigger_data = {
						order_status: $('.modal.show').find('.set-trigger-settings.order-status').val(),
					};
				} else if ( get_trigger === 'wpforms_process_complete' || get_trigger === 'wpforms_paypal_standard_process_complete' ) {
					trigger_data = {
						form_id: $('.modal.show').find('.set-trigger-settings.wpforms-form-id').val(),
					};
				}

				// send request
				$.ajax({
					url: params.ajax_url,
					method: 'POST',
					data: {
						action: 'joinotify_save_trigger_settings',
						post_id: Builder.getParamByName('id'),
						trigger_id: get_trigger_id,
						settings: JSON.stringify(trigger_data),
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
								$('.modal.show').find('.btn-close').click(); // close modal

								Builder.displayToast('success', response.toast_header_title, response.toast_body_title);
							} else {
								Builder.displayToast('error', response.toast_header_title, response.toast_body_title);
							}
						} catch (error) {
							console.log(error);
						}
					},
					complete: function() {
						btn.prop('disabled', false).html(button_state.html);
					},
				});
			});
		},

		/**
		 * Emoji picker
		 * 
		 * @since 1.1.0
		 * @package MeuMouse.com
		 */
		emojiPicker: function() {
			var i18n = params.i18n.emoji_picker;

			// check if emoji picker is already initialized
			if ( ! $('.add-emoji-picker').hasClass('emoji-initialized') ) {
				// wait DOM is ready for initialize	
				setTimeout( () => {
					// initialize emoji picker
					$('.add-emoji-picker').emojioneArea({
						tones: true,
						hidePickerOnBlur: true,
						recentEmojis: true,
						pickerPosition: 'bottom',
						searchPlaceholder: i18n.placeholder,
						buttonTitle: i18n.button_title,
						filters: {
							tones: {
								title: i18n.filters.tones_title,
							},
							recent: {
								title: i18n.filters.recent_title,
							},
							smileys_people: {
								title: i18n.filters.smileys_people_title,
							},
							animals_nature: {
								title: i18n.filters.animals_nature_title,
							},
							food_drink: {
								title: i18n.filters.food_drink_title,
							},
							activity: {
								title: i18n.filters.activity_title,
							},
							travel_places: {
								title: i18n.filters.travel_places_title,
							},
							objects: {
								title: i18n.filters.objects_title,
							},
							symbols: {
								title: i18n.filters.symbols_title,
							},
							flags: {
								title: i18n.filters.flags_title,
							},
						},
					});
				}, 600);

				// initialize emoji picker
				$('.add-emoji-picker').addClass('emoji-initialized');
			}

			// Update the textarea on keyup event in the emojionearea editor
			$(document).on('keyup change', '.emojionearea-editor', function() {
				var content = $(this).html();

				$(this).closest('.add-emoji-picker').val(content); // Update the textarea with the current content
		  	});
		},

		/**
		 * Search WooCommerce products
		 * 
		 * @since 1.1.0
		 * @version 1.2.0
		 * @package MeuMouse.com
		 */
		searchWooProducts: function() {
			setTimeout(() => {
				$('.search-products').each( function() {
					let select_products_element = $(this);
            		let select_products = JSON.parse(select_products_element.attr('data-selected-products') || '[]');

					// initialize selectize
					let product_select = select_products_element.selectize({
						plugins: {
							remove_button: {
								title: params.i18n.remove_product_selectize,
							}
						},
						delimiter: ',',
						persist: false,
						valueField: 'id',
						labelField: 'product_title',
						searchField: 'product_title',
						create: false,
						maxItems: null, // allow multiple selection
						options: select_products, // load saved products
						items: select_products.map( p => p.id ), // set saved products as selected
						render: {
							option: function(data, escape) {
								return `<div class="option" data-value="${escape(data.id)}">${escape(data.product_title)}</div>`;
							},
						},
						load: function(query, callback) {
							if (query.length < 3) return callback(); // Require at least 3 characters
							
							let selectize = this;
							
							// Show loading indicator
							selectize.$wrapper.append('<span class="spinner-border spinner-border-sm specific-search-spinner"></span>');
							selectize.$wrapper.addClass('loading');
			
							$.ajax({
								url: params.ajax_url,
								type: 'POST',
								dataType: 'json',
								data: {
									action: 'joinotify_get_woo_products',
									search_query: query,
								},
								success: function(response) {
									selectize.$wrapper.find('.specific-search-spinner').remove();
									selectize.$wrapper.removeClass('loading');
	
									callback(response);
								},
								error: function() {
									selectize.$wrapper.find('.specific-search-spinner').remove();
									selectize.$wrapper.removeClass('loading');
									
									callback();
								},
							});
						}
					});
			
					// Get Selectize instance
					let selectize_instance = product_select[0].selectize;
			
					// Handle item selection (only add to purchasedProducts when clicked)
					selectize_instance.on('item_add', function(value, data) {
						let product_id = parseInt(value);
						let product_data = selectize_instance.options[product_id];
    					let product_title = product_data ? product_data.product_title : '';
			
						// Ensure unique addition in object format { id: title }
						if ( ! Builder.purchasedProducts.some( p => p.id === product_id ) ) {
							Builder.purchasedProducts.push({ id: product_id, title: product_title });
						}
	
						// display the updated array on development mode
						if ( params.dev_mode ) {
							console.log(Builder.purchasedProducts);
						}
					});
			
					// Handle item removal
					selectize_instance.on('item_remove', function(value) {
						let product_id = parseInt(value);
	
						Builder.purchasedProducts = Builder.purchasedProducts.filter( p => p.id !== product_id );
	
						// display the updated array on development mode
						if ( params.dev_mode ) {
							console.log(Builder.purchasedProducts);
						}
					});
				});
			}, 500);
		},
	};

	// Initialize the Joinotify Builder on document ready
	jQuery(document).ready( function($) {
		Builder.init();
  	});
})(jQuery);