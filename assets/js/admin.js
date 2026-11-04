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

	// Plugin updates: ask GitHub now, then install with WordPress's own updater.
	var updates = document.getElementById( 'lc-updates' );
	var checkButton = document.getElementById( 'lc-check-update' );
	var runButton = document.getElementById( 'lc-run-update' );
	var updateResult = document.getElementById( 'lc-update-result' );

	function updateMessage( message, state ) {
		updateResult.textContent = message;
		updateResult.className = 'lc-test-result' + ( state ? ' is-' + state : '' );
	}

	function post( fields ) {
		var body = new FormData();

		Object.keys( fields ).forEach( function ( key ) {
			body.append( key, fields[ key ] );
		} );

		return fetch( config.ajaxUrl, { method: 'POST', body: body, credentials: 'same-origin' } ).then( function ( response ) {
			return response.json();
		} );
	}

	if ( updates && checkButton && runButton && updateResult && config ) {
		checkButton.addEventListener( 'click', function () {
			checkButton.disabled = true;
			updateMessage( config.checking );

			post( { action: 'leads_collector_check_update', _ajax_nonce: config.updateNonce } )
				.then( function ( json ) {
					var data = json && json.data ? json.data : {};

					updateMessage( data.message || config.failed, json && json.success ? ( data.hasUpdate ? 'warn' : 'ok' ) : 'error' );
					runButton.hidden = ! ( json && json.success && data.hasUpdate );
				} )
				.catch( function () {
					updateMessage( config.failed, 'error' );
				} )
				.then( function () {
					checkButton.disabled = false;
				} );
		} );

		runButton.addEventListener( 'click', function () {
			checkButton.disabled = true;
			runButton.disabled = true;
			updateMessage( config.updating );

			post( {
				action: 'update-plugin',
				_ajax_nonce: config.updatesNonce,
				plugin: updates.getAttribute( 'data-plugin' ),
				slug: updates.getAttribute( 'data-slug' ),
			} )
				.then( function ( json ) {
					if ( json && json.success ) {
						updateMessage( config.updated, 'ok' );
						setTimeout( function () {
							window.location.reload();
						}, 1200 );
						return;
					}

					updateMessage( json && json.data && json.data.errorMessage ? json.data.errorMessage : config.updateFailed, 'error' );
					checkButton.disabled = false;
					runButton.disabled = false;
				} )
				.catch( function () {
					updateMessage( config.updateFailed, 'error' );
					checkButton.disabled = false;
					runButton.disabled = false;
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
