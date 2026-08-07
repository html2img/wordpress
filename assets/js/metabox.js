/**
 * Classic editor metabox, driven by the same ajax endpoints as the
 * Gutenberg panel.
 *
 * @package Html2Img
 */

( function () {
	'use strict';

	var box = document.getElementById( 'html2img-metabox' );

	if ( ! box ) {
		return;
	}

	var config = window.html2imgEditor || {};
	var postId = box.getAttribute( 'data-post' );
	var body = box.querySelector( '.html2img-metabox-body' );
	var timer = null;

	function request( action ) {
		var data = new FormData();
		data.append( 'action', action );
		data.append( 'nonce', config.nonce );
		data.append( 'post_id', postId );

		return fetch( config.ajaxUrl, {
			method: 'POST',
			credentials: 'same-origin',
			body: data
		} ).then( function ( response ) {
			return response.json();
		} );
	}

	function render( state ) {
		body.innerHTML = '';

		if ( state.imageUrl && ! state.disabled ) {
			var img = document.createElement( 'img' );
			img.src = state.imageUrl;
			img.style.width = '100%';
			img.style.height = 'auto';
			img.style.marginBottom = '8px';
			body.appendChild( img );
		}

		var status = document.createElement( 'p' );

		if ( state.disabled ) {
			status.textContent = box.getAttribute( 'data-i18n-disabled' ) || 'Generation is off for this post.';
		} else if ( 'queued' === state.status || 'generating' === state.status ) {
			status.textContent = 'Generating…';
		} else if ( 'failed_credits' === state.status ) {
			status.textContent = 'Waiting for credits. The previous image is untouched.';
		} else if ( 'failed' === state.status ) {
			status.textContent = state.error || 'The last render failed.';
		} else if ( state.imageUrl ) {
			status.textContent = state.generatedAt ? 'Generated ' + state.generatedAt : 'Generated';
		} else {
			status.textContent = 'No image yet. It generates on publish.';
		}

		body.appendChild( status );

		if ( ! state.disabled ) {
			var regen = document.createElement( 'button' );
			regen.type = 'button';
			regen.className = 'button';
			regen.textContent = state.stuck ? 'Run it now' : 'Regenerate';
			regen.addEventListener( 'click', function () {
				regen.disabled = true;
				request( 'html2img_run_now' ).then( function ( result ) {
					if ( result.success ) {
						render( result.data );
					}
				} );
			} );
			body.appendChild( regen );
		}

		var toggleWrap = document.createElement( 'p' );
		var toggleLabel = document.createElement( 'label' );
		var toggle = document.createElement( 'input' );
		toggle.type = 'checkbox';
		toggle.checked = !! state.disabled;
		toggle.addEventListener( 'change', function () {
			request( 'html2img_toggle' ).then( function ( result ) {
				if ( result.success ) {
					render( result.data );
				}
			} );
		} );
		toggleLabel.appendChild( toggle );
		toggleLabel.appendChild( document.createTextNode( " Don't generate for this post" ) );
		toggleWrap.appendChild( toggleLabel );
		body.appendChild( toggleWrap );

		if ( ( 'queued' === state.status || 'generating' === state.status ) && ! timer ) {
			timer = window.setInterval( poll, 8000 );
		} else if ( 'queued' !== state.status && 'generating' !== state.status && timer ) {
			window.clearInterval( timer );
			timer = null;
		}
	}

	function poll() {
		request( 'html2img_status' ).then( function ( result ) {
			if ( result.success ) {
				render( result.data );
			}
		} );
	}

	poll();
}() );
