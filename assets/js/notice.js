/**
 * Remembers the dismissal of the connect notice.
 *
 * @package Html2Img
 */

( function () {
	'use strict';

	var config = window.html2imgNotice || {};

	document.addEventListener( 'click', function ( event ) {
		var target = event.target;

		if ( ! target || ! target.classList || ! target.classList.contains( 'notice-dismiss' ) ) {
			return;
		}

		if ( ! target.closest || ! target.closest( '.html2img-notice' ) ) {
			return;
		}

		var body = new FormData();
		body.append( 'action', 'html2img_dismiss_notice' );
		body.append( 'nonce', config.nonce );

		window.fetch( config.ajaxUrl, {
			method: 'POST',
			credentials: 'same-origin',
			body: body
		} );
	} );
}() );
