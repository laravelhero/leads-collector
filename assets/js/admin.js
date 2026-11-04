/* global leadsCollectorAdmin */
( function () {
	'use strict';

	var config = window.leadsCollectorAdmin;
	var button = document.getElementById( 'lc-test' );
	var result = document.getElementById( 'lc-test-result' );

	function show( message, ok ) {
		result.textContent = message;
		result.className = 'lc-test-result ' + ( ok ? 'is-ok' : 'is-error' );
	}

	if ( button && result && config ) {
		button.addEventListener( 'click', function () {
			var body = new FormData();

			body.append( 'action', 'leads_collector_test' );
			body.append( '_ajax_nonce', config.nonce );
			body.append( 'site_key', document.getElementById( 'lc-site-key' ).value );
			body.append( 'server_url', document.getElementById( 'lc-server-url' ).value );

			button.disabled = true;
			result.className = 'lc-test-result';
			result.textContent = config.testing;

			fetch( config.ajaxUrl, { method: 'POST', body: body, credentials: 'same-origin' } )
				.then( function ( response ) {
					return response.json();
				} )
				.then( function ( json ) {
					show( json && json.data && json.data.message ? json.data.message : config.failed, !! ( json && json.success ) );
				} )
				.catch( function () {
					show( config.failed, false );
				} )
				.then( function () {
					button.disabled = false;
				} );
		} );
	}

	document.addEventListener( 'click', function ( event ) {
		var copy = event.target.closest ? event.target.closest( '.lc-copy' ) : null;

		if ( ! copy || ! navigator.clipboard ) {
			return;
		}

		navigator.clipboard.writeText( copy.getAttribute( 'data-copy' ) ).then( function () {
			var label = copy.textContent;

			copy.textContent = config ? config.copied : 'Copied';
			setTimeout( function () {
				copy.textContent = label;
			}, 1500 );
		} );
	} );
} )();
