/**
 * Gutenberg sidebar panel.
 *
 * Plain JS against the wp.* globals, no build step.
 *
 * @package Html2Img
 */

( function ( wp ) {
	'use strict';

	if ( ! wp || ! wp.plugins || ! wp.editPost ) {
		return;
	}

	var el = wp.element.createElement;
	var useState = wp.element.useState;
	var useEffect = wp.element.useEffect;
	var __ = wp.i18n.__;
	var config = window.html2imgEditor || {};

	function request( action, postId ) {
		var body = new FormData();
		body.append( 'action', action );
		body.append( 'nonce', config.nonce );
		body.append( 'post_id', postId );

		return fetch( config.ajaxUrl, {
			method: 'POST',
			credentials: 'same-origin',
			body: body
		} ).then( function ( response ) {
			return response.json();
		} );
	}

	function statusLabel( state ) {
		if ( state.disabled ) {
			return __( 'Generation is off for this post.', 'html2img' );
		}

		switch ( state.status ) {
			case 'queued':
			case 'generating':
				return __( 'Generating…', 'html2img' );
			case 'failed_credits':
				return __( 'Waiting for credits. The previous image is untouched.', 'html2img' );
			case 'failed':
				return state.error || __( 'The last render failed.', 'html2img' );
			case 'ok':
				return state.generatedAt ? __( 'Generated', 'html2img' ) + ' ' + state.generatedAt : __( 'Generated', 'html2img' );
			default:
				return __( 'No image yet. It generates on publish.', 'html2img' );
		}
	}

	function Panel() {
		var postId = wp.data.select( 'core/editor' ).getCurrentPostId();
		var stateHook = useState( null );
		var state = stateHook[ 0 ];
		var setState = stateHook[ 1 ];
		var busyHook = useState( false );
		var busy = busyHook[ 0 ];
		var setBusy = busyHook[ 1 ];

		var refresh = function () {
			request( 'html2img_status', postId ).then( function ( result ) {
				if ( result.success ) {
					setState( result.data );
				}
			} );
		};

		useEffect( function () {
			refresh();

			var timer = window.setInterval( function () {
				request( 'html2img_status', postId ).then( function ( result ) {
					if ( result.success ) {
						setState( result.data );
					}
				} );
			}, 8000 );

			return function () {
				window.clearInterval( timer );
			};
		}, [] );

		var act = function ( action ) {
			setBusy( true );
			request( action, postId ).then( function ( result ) {
				setBusy( false );
				if ( result.success ) {
					setState( result.data );
				}
			} );
		};

		if ( ! config.hasKey ) {
			return el(
				wp.editPost.PluginDocumentSettingPanel,
				{ name: 'html2img', title: __( 'OG Image', 'html2img' ) },
				el( 'p', {}, __( 'Connect your HTML to Image account to generate OG images.', 'html2img' ) ),
				el( 'a', { href: config.settingsUrl }, __( 'Open the settings', 'html2img' ) )
			);
		}

		if ( ! state ) {
			return el(
				wp.editPost.PluginDocumentSettingPanel,
				{ name: 'html2img', title: __( 'OG Image', 'html2img' ) },
				el( wp.components.Spinner, {} )
			);
		}

		var children = [];

		if ( state.imageUrl && ! state.disabled ) {
			children.push(
				el( 'img', {
					key: 'thumb',
					src: state.imageUrl,
					style: { width: '100%', height: 'auto', borderRadius: '2px', marginBottom: '8px' },
					alt: __( 'Current OG image', 'html2img' )
				} )
			);
		}

		children.push( el( 'p', { key: 'status' }, statusLabel( state ) ) );

		if ( state.stuck ) {
			children.push(
				el( 'p', { key: 'stuck' }, __( 'The queued render has not started. Scheduled tasks may be off on this site.', 'html2img' ) ),
				el(
					wp.components.Button,
					{ key: 'run', variant: 'secondary', isBusy: busy, onClick: function () { act( 'html2img_run_now' ); } },
					__( 'Run it now', 'html2img' )
				)
			);
		} else if ( ! state.disabled ) {
			children.push(
				el(
					wp.components.Button,
					{ key: 'regen', variant: 'secondary', isBusy: busy, disabled: busy, onClick: function () { act( 'html2img_run_now' ); } },
					__( 'Regenerate', 'html2img' )
				)
			);
		}

		children.push(
			el( wp.components.ToggleControl, {
				key: 'toggle',
				label: __( "Don't generate for this post", 'html2img' ),
				checked: !! state.disabled,
				onChange: function () {
					act( 'html2img_toggle' );
				}
			} )
		);

		return el(
			wp.editPost.PluginDocumentSettingPanel,
			{ name: 'html2img', title: __( 'OG Image', 'html2img' ) },
			children
		);
	}

	wp.plugins.registerPlugin( 'html2img', { render: Panel } );
}( window.wp ) );
