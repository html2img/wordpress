/**
 * Settings and tools screens.
 *
 * @package Html2Img
 */

( function () {
	'use strict';

	var config = window.html2imgAdmin || {};

	function post( action, data ) {
		var body = new FormData();
		body.append( 'action', action );
		body.append( 'nonce', config.nonce );
		Object.keys( data || {} ).forEach( function ( key ) {
			body.append( key, data[ key ] );
		} );

		return fetch( config.ajaxUrl, {
			method: 'POST',
			credentials: 'same-origin',
			body: body
		} ).then( function ( response ) {
			return response.json();
		} );
	}

	function currentDesign() {
		var checked = document.querySelector( 'input[name$="[design]"]:checked' );
		return checked ? checked.value : '';
	}

	/* Settings: design preview. */
	var previewFrame = document.getElementById( 'html2img-preview' );

	function refreshPreview() {
		if ( ! previewFrame ) {
			return;
		}

		var accent = document.getElementById( 'html2img-accent' );
		var background = document.getElementById( 'html2img-background' );
		var url = config.ajaxUrl +
			'?action=html2img_preview' +
			'&nonce=' + encodeURIComponent( config.nonce ) +
			'&design=' + encodeURIComponent( currentDesign() ) +
			( accent ? '&accent=' + encodeURIComponent( accent.value ) : '' ) +
			( background ? '&background=' + encodeURIComponent( background.value ) : '' ) +
			'&t=' + Date.now();

		previewFrame.src = url;
	}

	if ( previewFrame ) {
		refreshPreview();

		var refreshButton = document.getElementById( 'html2img-preview-refresh' );
		if ( refreshButton ) {
			refreshButton.addEventListener( 'click', refreshPreview );
		}

		document.querySelectorAll( 'input[name$="[design]"], #html2img-accent, #html2img-background' ).forEach( function ( input ) {
			input.addEventListener( 'change', refreshPreview );
		} );
	}

	/* Settings: API test render. */
	var testButton = document.getElementById( 'html2img-test-render' );

	if ( testButton ) {
		testButton.addEventListener( 'click', function () {
			if ( ! window.confirm( config.i18n.testRenderConfirm ) ) {
				return;
			}

			testButton.disabled = true;
			testButton.textContent = config.i18n.rendering;

			post( 'html2img_test_render', { design: currentDesign() } ).then( function ( result ) {
				testButton.disabled = false;
				testButton.textContent = testButton.getAttribute( 'data-label' ) || testButton.textContent;

				var box = document.getElementById( 'html2img-test-result' );

				if ( result.success && result.data.url && box ) {
					box.hidden = false;
					box.querySelector( 'img' ).src = result.data.url;
					testButton.textContent = testButton.textContent.replace( config.i18n.rendering, '' );
				} else {
					window.alert( ( result.data && result.data.message ) || config.i18n.failed );
				}
				window.location.reload();
			} );
		} );
		testButton.setAttribute( 'data-label', testButton.textContent );
	}

	/* Settings: logo picker. */
	var logoSelect = document.getElementById( 'html2img-logo-select' );

	if ( logoSelect && window.wp && window.wp.media ) {
		var logoFrame = null;

		logoSelect.addEventListener( 'click', function () {
			if ( ! logoFrame ) {
				logoFrame = window.wp.media( {
					title: logoSelect.textContent,
					multiple: false,
					library: { type: 'image' }
				} );

				logoFrame.on( 'select', function () {
					var attachment = logoFrame.state().get( 'selection' ).first().toJSON();
					document.getElementById( 'html2img-logo-id' ).value = attachment.id;
					var previewBox = document.getElementById( 'html2img-logo-preview' );
					previewBox.innerHTML = '';
					var img = document.createElement( 'img' );
					img.src = attachment.sizes && attachment.sizes.medium ? attachment.sizes.medium.url : attachment.url;
					img.style.maxWidth = '200px';
					previewBox.appendChild( img );
					document.getElementById( 'html2img-logo-remove' ).hidden = false;
				} );
			}

			logoFrame.open();
		} );

		var logoRemove = document.getElementById( 'html2img-logo-remove' );

		if ( logoRemove ) {
			logoRemove.addEventListener( 'click', function () {
				document.getElementById( 'html2img-logo-id' ).value = '0';
				document.getElementById( 'html2img-logo-preview' ).innerHTML = '';
				logoRemove.hidden = true;
			} );
		}
	}

	/* Tools: bulk regeneration. */
	var progressBox = document.getElementById( 'html2img-bulk-progress' );

	if ( progressBox ) {
		var stopRequested = false;

		var setMessage = function ( text ) {
			document.getElementById( 'html2img-bulk-message' ).textContent = text;
		};

		var setBar = function ( done, total ) {
			var percent = total > 0 ? Math.round( ( done / total ) * 100 ) : 100;
			progressBox.querySelector( '.html2img-progress-bar' ).style.width = percent + '%';
		};

		var step = function () {
			post( 'html2img_bulk_step', {} ).then( function ( result ) {
				if ( ! result.success ) {
					setMessage( config.i18n.failed );
					return;
				}

				var data = result.data;
				setBar( data.done, data.total );
				setMessage( data.done + ' / ' + data.total + ( data.credits !== null && data.credits !== undefined ? ' — ' + data.credits + ' credits' : '' ) );

				if ( data.outOfCredits ) {
					setMessage( config.i18n.bulkCredits );
					return;
				}

				if ( data.complete ) {
					setMessage( config.i18n.bulkDone + ( data.failed ? ' (' + data.failed + ' failed)' : '' ) );
					window.setTimeout( function () {
						window.location.reload();
					}, 1200 );
					return;
				}

				if ( stopRequested ) {
					setMessage( config.i18n.bulkStopped );
					return;
				}

				step();
			} );
		};

		var run = function ( mode ) {
			stopRequested = false;
			progressBox.hidden = false;

			if ( mode ) {
				post( 'html2img_bulk_start', { mode: mode } ).then( function () {
					step();
				} );
			} else {
				step();
			}
		};

		var staleButton = document.getElementById( 'html2img-bulk-stale' );
		var allButton = document.getElementById( 'html2img-bulk-all' );
		var resumeButton = document.getElementById( 'html2img-bulk-resume' );
		var discardButton = document.getElementById( 'html2img-bulk-discard' );
		var stopButton = document.getElementById( 'html2img-bulk-stop' );

		if ( staleButton ) {
			staleButton.addEventListener( 'click', function () {
				run( 'stale' );
			} );
		}

		if ( allButton ) {
			allButton.addEventListener( 'click', function () {
				run( 'all' );
			} );
		}

		if ( resumeButton ) {
			resumeButton.addEventListener( 'click', function () {
				run( null );
			} );
		}

		if ( discardButton ) {
			discardButton.addEventListener( 'click', function () {
				post( 'html2img_bulk_stop', {} ).then( function () {
					window.location.reload();
				} );
			} );
		}

		if ( stopButton ) {
			stopButton.addEventListener( 'click', function () {
				stopRequested = true;
			} );
		}
	}
}() );
