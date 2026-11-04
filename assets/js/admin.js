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

	// No "Update now" button where the host installs updates; checking still works.
	if ( updates && checkButton && updateResult && config ) {
		checkButton.addEventListener( 'click', function () {
			checkButton.disabled = true;
			updateMessage( config.checking );

			post( { action: 'leads_collector_check_update', _ajax_nonce: config.updateNonce } )
				.then( function ( json ) {
					var data = json && json.data ? json.data : {};

					updateMessage( data.message || config.failed, json && json.success ? ( data.hasUpdate ? 'warn' : 'ok' ) : 'error' );
					if ( runButton ) {
						runButton.hidden = ! ( json && json.success && data.hasUpdate );
					}
				} )
				.catch( function () {
					updateMessage( config.failed, 'error' );
				} )
				.then( function () {
					checkButton.disabled = false;
				} );
		} );

	}

	if ( updates && checkButton && runButton && updateResult && config ) {
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

	// Form design: switches, live preview, colour code and the unsaved-changes bar.
	var settingsForm = document.getElementById( 'lc-settings' );
	var mock = document.getElementById( 'lc-mock' );
	var mockSuccess = document.getElementById( 'lc-mock-success' );
	var colorInput = document.getElementById( 'lc-color' );
	var colorHex = document.getElementById( 'lc-color-hex' );
	var buttonText = document.getElementById( 'lc-button-text' );
	var successText = document.getElementById( 'lc-success' );
	var saveState = document.getElementById( 'lc-save-state' );
	var rows = Array.prototype.slice.call( document.querySelectorAll( '.lc-row[data-field]' ) );
	var dirty = false;

	function make( tag, className, text ) {
		var node = document.createElement( tag );

		if ( className ) {
			node.className = className;
		}
		if ( text ) {
			node.textContent = text;
		}

		return node;
	}

	function syncRows() {
		rows.forEach( function ( row ) {
			var show = row.querySelector( '.lc-show' );
			var required = row.querySelector( '.lc-required' );

			row.classList.toggle( 'is-hidden', ! show.checked );

			// A hidden field cannot be required; locked fields stay as they are.
			if ( ! show.disabled ) {
				required.disabled = ! show.checked;
			}
		} );
	}

	function renderPreview() {
		if ( ! mock || ! config ) {
			return;
		}

		var fields = rows.filter( function ( row ) {
			return row.querySelector( '.lc-show' ).checked;
		} ).map( function ( row ) {
			var width = row.querySelector( '.lc-width:checked' );
			var field = row.getAttribute( 'data-field' );

			return {
				name: field,
				required: row.querySelector( '.lc-required' ).checked,
				full: field === 'message' || ( width && width.value === 'full' ),
			};
		} );

		// As on the site: a half-width field left alone at the end of a run takes the whole line.
		var run = [];

		function closeRun() {
			if ( run.length % 2 !== 0 ) {
				run[ run.length - 1 ].full = true;
			}
			run = [];
		}

		fields.forEach( function ( field ) {
			if ( field.full ) {
				closeRun();
			} else {
				run.push( field );
			}
		} );
		closeRun();

		var color = colorInput ? colorInput.value : '#4f46e5';

		mock.textContent = '';
		fields.forEach( function ( field ) {
			var wrap = make( 'div', 'lc-mock-field' + ( field.full ? ' is-full' : '' ) );
			var label = make( 'span', 'lc-mock-label', config.labels[ field.name ] || field.name );

			if ( field.required ) {
				label.appendChild( make( 'b', '', ' *' ) );
			}

			wrap.appendChild( label );
			wrap.appendChild( make( 'div', 'lc-mock-input' + ( field.name === 'message' ? ' is-area' : '' ), config.placeholders[ field.name ] || '' ) );
			mock.appendChild( wrap );
		} );

		var button = make( 'div', 'lc-mock-button', buttonText && buttonText.value ? buttonText.value : 'Send' );

		button.style.background = color;
		mock.appendChild( button );

		if ( mockSuccess ) {
			mockSuccess.textContent = successText && successText.value ? '✓ ' + successText.value : '';
		}
	}

	function markDirty() {
		if ( dirty || ! saveState ) {
			return;
		}

		dirty = true;
		saveState.textContent = config.unsaved;
		saveState.classList.add( 'is-dirty' );
	}

	if ( settingsForm ) {
		settingsForm.addEventListener( 'input', function ( event ) {
			if ( event.target === colorHex && /^#[0-9a-f]{6}$/i.test( colorHex.value ) ) {
				colorInput.value = colorHex.value;
			}
			if ( event.target === colorInput && colorHex ) {
				colorHex.value = colorInput.value;
			}

			markDirty();
			renderPreview();
		} );

		settingsForm.addEventListener( 'change', function () {
			syncRows();
			markDirty();
			renderPreview();
		} );

		settingsForm.addEventListener( 'submit', function () {
			dirty = false;
		} );

		window.addEventListener( 'beforeunload', function ( event ) {
			if ( dirty ) {
				event.preventDefault();
				event.returnValue = '';
			}
		} );

		syncRows();
		renderPreview();
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
