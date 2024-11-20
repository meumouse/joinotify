/**
 * Display custom toasts
 * 
 * @since 1.0.0
 * @param {string} type | Toast type (success, danger...)
 * @param {string} header_title | Header title for toast
 * @param {string} body_title | Body title for toast
 * @param {string} container | Container for display toasts
 * @package MeuMouse.com
 */
function display_toast(type, header_title, body_title, container) {
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
    container.prepend(toast_html);

    // fadeout after 3 seconds
    setTimeout( function() {
        jQuery('#' + toast_id).fadeOut('fast');
    }, 3000);

    // remove toast after 3,5 seconds
    setTimeout( function() {
        jQuery('#' + toast_id).remove();
    }, 3500);
}