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
 * Change post status on click on toggle switch
 * 
 * @since 1.0.0
 */
jQuery(document).ready( function($) {
    $(document).on('change', '.toggle-switch', function() {
        const post_id = $(this).data('id');
        const status = $(this).is(':checked') ? 'publish' : 'draft';

        $.ajax({
            url: joinotify_workflows_table_params.ajax_url,
            type: 'POST',
            dataType: 'json',
            data: {
                action: 'joinotify_toggle_post_status',
                post_id: post_id,
                status: status,
            },
            success: function(response) {
                if ( response.status === 'success' ) {
                    display_toast('success', response.toast_header_title, response.toast_body_title, $('.wrap form'));
                } else {
                    display_toast('error', response.toast_header_title, response.toast_body_title, $('.wrap form'));
                }
            },
            error: function(xhr, status, error) {
                console.error('Error on AJAX request:', xhr.responseText);
            },
        });
    });
});