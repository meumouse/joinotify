( function($) {
    "use strict";

    /**
	 * Display custom toasts
	 * 
	 * @since 1.0.0
	 * @param {string} type | Toast type (success, danger...)
	 * @param {string} header_title | Header title for toast
	 * @param {string} body_title | Body title for toast
	 * @package MeuMouse.com
	 */
	function display_toast(type, header_title, body_title) {
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
		} else {
			// if unknown type, use default values
			toast_class = 'toast-secondary';
			header_class = 'bg-secondary text-white';
			icon = '';
		}
	
		// generate uniq id for toast
		var toast_id = 'toast-' + Math.random().toString(36).substr(2, 9);
	
		// builde toast HTML
		var toast_html = `<div id="${toast_id}" class="toast ${toast_class} show">
			<div class="toast-header ${header_class}">
				${icon}
				<span class="me-auto">${header_title}</span>
				<button class="btn-close btn-close-white ms-2 hide-toast" type="button" data-bs-dismiss="toast" aria-label="${joinotify_license_params.arial_label_toasts}"></button>
			</div>
			<div class="toast-body">${body_title}</div>
		</div>`;
	
		// add toast on builder DOM
		$('#joinotify_license_area').prepend(toast_html);
	
		// fadeout after 3 seconds
		setTimeout( function() {
			$('#' + toast_id).fadeOut('fast');
		}, 3000);

		// remove toast after 3,5 seconds
		setTimeout( function() {
			$('#' + toast_id).remove();
		}, 3500);
	}

    /**
	 * Active license process
	 * 
	 * @since 1.0.0
	 * @package MeuMouse.com
	 */
	jQuery(document).ready( function($) {
		$('#joinotify_active_license').on('click', function(e) {
			e.preventDefault();

            var btn = $(this);
			var btn_width = btn.width();
			var btn_height = btn.height();
			var btn_html = btn.html();
            var get_license_key = $('#joinotify_license_key').val();

			// keep original width and height
			btn.width(btn_width);
			btn.height(btn_height);

			$.ajax({
				url: joinotify_license_params.ajax_url,
				method: 'POST',
				data: {
					action: 'joinotify_active_license',
                    license_key: get_license_key,
				},
				beforeSend: function() {
					btn.prop('disabled', true).html('<span class="spinner-border spinner-border-sm"></span>');
				},
				success: function(response) {
					try {
						if (response.status === 'success') {
							display_toast('success', response.toast_header_title, response.toast_body_title);

                            setTimeout( function() {
                                location.reload();
                            }, 1000);
						} else {
							display_toast('error', response.toast_header_title, response.toast_body_title);
						}
					} catch (error) {
						console.log(error);
					}
				},
				complete: function() {
					btn.prop('disabled', false).html(btn_html);
				},
				error: function(xhr, status, error) {
					alert('AJAX error: ' + error);
				}
			});
		});
	});

    /**
	 * Deactive license process
	 * 
	 * @since 1.0.0
	 * @package MeuMouse.com
	 */
	jQuery(document).ready( function($) {
		$('#joinotify_deactive_license').on('click', function(e) {
			e.preventDefault();

            // get confirmation
			if ( ! confirm(joinotify_license_params.confirm_deactivate_license) ) {
				return;
			}

            var btn = $(this);
			var btn_width = btn.width();
			var btn_height = btn.height();
			var btn_html = btn.html();

			// keep original width and height
			btn.width(btn_width);
			btn.height(btn_height);

			$.ajax({
				url: joinotify_license_params.ajax_url,
				method: 'POST',
				data: {
					action: 'joinotify_deactive_license',
				},
				beforeSend: function() {
					btn.prop('disabled', true).html('<span class="spinner-border spinner-border-sm"></span>');
				},
				success: function(response) {
					try {
						if (response.status === 'success') {
							display_toast('success', response.toast_header_title, response.toast_body_title);

                            setTimeout( function() {
                                location.reload();
                            }, 1000);
						} else {
							display_toast('error', response.toast_header_title, response.toast_body_title);
						}
					} catch (error) {
						console.log(error);
					}
				},
				complete: function() {
					btn.prop('disabled', false).html(btn_html);
				},
				error: function(xhr, status, error) {
					alert('AJAX error: ' + error);
				}
			});
		});
	});


    /**
	 * Alternative license process
	 * 
	 * @since 1.0.0
	 */
	jQuery(document).ready( function($) {
		// Add event handlers for dragover and dragleave
		$('#license_key_zone').on('dragover dragleave', function(e) {
			e.preventDefault();
			
			$(this).toggleClass('drag-over', e.type === 'dragover');
		});
	
		// Add event handlers for drop
		$('#license_key_zone').on('drop', function(e) {
			e.preventDefault();
	
			var file = e.originalEvent.dataTransfer.files[0];

			if ( ! $(this).hasClass('file-uploaded') ) {
				handle_file(file, $(this));
			}
		});
	
		// Adds a change event handler to the input file
		$('#upload_license_key').on('change', function(e) {
			var file = e.target.files[0];

			handle_file( file, $(this).parents('.dropzone-license') );
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
				var formData = new FormData();

				formData.append('action', 'joinotify_alternative_activation_license');
				formData.append('file', file);

				dropzone.children('.file-list').removeClass('d-none').text(filename);
				dropzone.addClass('file-processing');
				dropzone.append('<div class="spinner-border"></div>');
	
				$.ajax({
					url: joinotify_license_params.ajax_url,
					type: 'POST',
					data: formData,
					processData: false,
					contentType: false,
					beforeSend: function() {

					},
					success: function(response) {
						if ( joinotify_license_params.debug_mode ) {
							console.log(response);
						}

						try {
							if (response.status === 'success') {
								display_toast( 'success', response.toast_header_title, response.toast_body_title, $('#joinotify_license_area') );

								dropzone.addClass('file-uploaded').removeClass('file-processing');
								dropzone.children('.spinner-border').remove();
								dropzone.append('<div class="upload-notice d-flex flex-column align-items-center"><svg xmlns="http://www.w3.org/2000/svg" width="32" height="32" viewBox="0 0 24 24"><path fill="#22c55e" d="M12 2C6.486 2 2 6.486 2 12s4.486 10 10 10 10-4.486 10-10S17.514 2 12 2zm0 18c-4.411 0-8-3.589-8-8s3.589-8 8-8 8 3.589 8 8-3.589 8-8 8z"></path><path fill="#22c55e" d="M9.999 13.587 7.7 11.292l-1.412 1.416 3.713 3.705 6.706-6.706-1.414-1.414z"></path></svg><span>'+ response.dropfile_message +'</span></div>');
								dropzone.children('.file-list').addClass('d-none');

								setTimeout( function() {
									location.reload();
								}, 1000);
							} else {
								display_toast( 'error', response.toast_header_title, response.toast_body_title, $('#joinotify_license_area') );

								dropzone.addClass('invalid-file').removeClass('drag-over file-processing');
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
					},
					complete: function() {

					},
				});
			}
		}
	});
})(jQuery);