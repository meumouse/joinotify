( function($) {

    "use strict";

    /**
     * Joinotify workflows table object variable
     *
     * @since 1.1.0
     * @package MeuMouse.com
     */
    var WorkflowsTable = {
        toastId: null,

        /**
         * Hide toasts
         * 
         * @since 1.1.0
         */
        hideToasts: function() {
            this.toastId = $('.toast.show').attr('id');

            if ( ! this.toastId ) {
                return;
            }
        
            // hide with fade out effect
            $('#' + WorkflowsTable.toastId).fadeOut('fast');
        
            // remove toast from HTML after 500 miliseconds
            setTimeout( function() {
                $('#' + WorkflowsTable.toastId).remove();
            }, 500);
        },        

        /**
         * Change post status
         *
         * @since 1.1.0
         */
        changePostStatus: function() {
            $(document).on('change', '.toggle-switch', function() {
                var input = $(this);
                const postId = $(this).data('id');
                const status = $(this).is(':checked') ? 'publish' : 'draft';

                // send AJAX request
                $.ajax({
                    url: joinotify_workflows_table_params.ajax_url,
                    type: 'POST',
                    dataType: 'json',
                    data: {
                        action: 'joinotify_toggle_post_status',
                        post_id: postId,
                        status: status,
                    },
                    beforeSend: function() {
                        input.prop('disabled', true);
                    },
                    success: function(response) {
                        if (response.status === 'success') {
                            display_toast('success', response.toast_header_title, response.toast_body_title, $('.wrap form'));
                        } else {
                            display_toast('error', response.toast_header_title, response.toast_body_title, $('.wrap form'));
                        }
                    },
                    error: function(xhr, status, error) {
                        console.error('Error on AJAX request:', xhr.responseText);
                    },
                    complete: function() {
                        input.prop('disabled', false);
                    },
                });
            });
        },

        // Public API
        init: function() {
            $(document).ready( function() {
                $(document).on('click', '.hide-toast', function() {
                    WorkflowsTable.hideToasts();
                });

                WorkflowsTable.changePostStatus();
            });
        }
    };

    // Initialize the module
    $(document).ready( function() {
        WorkflowsTable.init();
    });
})(jQuery);