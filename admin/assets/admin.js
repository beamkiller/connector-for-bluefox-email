/* global cfbeAdmin */
( function () {
	'use strict';

	function $( id ) {
		return document.getElementById( id );
	}

	function setResult( el, text, ok ) {
		if ( ! el ) {
			return;
		}
		el.textContent = text;
		el.className = 'cfbe-result ' + ( ok ? 'is-ok' : 'is-error' );
	}

	function post( action, extra ) {
		var body = new FormData();
		body.append( 'action', action );
		body.append( 'nonce', cfbeAdmin.nonce );
		Object.keys( extra || {} ).forEach( function ( key ) {
			body.append( key, extra[ key ] );
		} );
		return fetch( cfbeAdmin.ajaxUrl, { method: 'POST', credentials: 'same-origin', body: body } )
			.then( function ( res ) {
				return res.json();
			} )
			.then( function ( json ) {
				if ( ! json || ! json.success ) {
					throw new Error( ( json && json.data && json.data.message ) || cfbeAdmin.i18n.error );
				}
				return json.data;
			} );
	}

	function credentials() {
		var key = $( 'cfbe-api-key' );
		return {
			project_id: $( 'cfbe-project-id' ) ? $( 'cfbe-project-id' ).value : '',
			api_key: key && ! key.disabled ? key.value : '',
		};
	}

	function withButton( button, resultEl, promise ) {
		button.disabled = true;
		setResult( resultEl, cfbeAdmin.i18n.working, true );
		return promise
			.catch( function ( err ) {
				setResult( resultEl, err.message, false );
			} )
			.then( function () {
				button.disabled = false;
			} );
	}

	var test = $( 'cfbe-test-connection' );
	if ( test ) {
		test.addEventListener( 'click', function () {
			var out = $( 'cfbe-test-result' );
			withButton( test, out, post( 'cfbe_test_connection', credentials() ).then( function ( data ) {
				setResult( out, data.message + ( data.warning ? ' — ' + data.warning : '' ), ! data.warning );
			} ) );
		} );
	}

	var load = $( 'cfbe-load-lists' );
	if ( load ) {
		load.addEventListener( 'click', function () {
			var out = $( 'cfbe-lists-result' );
			var select = $( 'cfbe-list-id' );
			withButton( load, out, post( 'cfbe_load_lists', credentials() ).then( function ( data ) {
				var current = select.value;
				while ( select.options.length > 1 ) {
					select.remove( 1 );
				}
				data.lists.forEach( function ( list ) {
					var label = list.name + ( list.doi ? ' (' + cfbeAdmin.i18n.doi + ')' : '' );
					select.add( new Option( label, list.id, false, list.id === current ) );
				} );
				setResult( out, cfbeAdmin.i18n.loaded, true );
			} ) );
		} );
	}

	var setupFields = $( 'cfbe-setup-fields' );
	if ( setupFields ) {
		setupFields.addEventListener( 'click', function () {
			var out = $( 'cfbe-fields-result' );
			withButton( setupFields, out, post( 'cfbe_setup_fields' ).then( function ( data ) {
				setResult( out, data.message, true );
			} ) );
		} );
	}

	var regen = $( 'cfbe-regen-secret' );
	if ( regen ) {
		regen.addEventListener( 'click', function () {
			// eslint-disable-next-line no-alert
			if ( ! window.confirm( cfbeAdmin.i18n.confirm ) ) {
				return;
			}
			withButton( regen, null, post( 'cfbe_regen_secret' ).then( function ( data ) {
				$( 'cfbe-webhook-secret' ).value = data.secret;
			} ) );
		} );
	}

	// Interests editor: add / remove / reorder rows. PHP keeps DOM order, so indexes only need to be unique.
	var interests = $( 'cfbe-interests' );
	var addInterest = $( 'cfbe-interest-add' );
	if ( interests && addInterest ) {
		var body = interests.tBodies[ 0 ];
		interests.addEventListener( 'click', function ( event ) {
			var button = event.target.closest( 'button' );
			var row = button && button.closest( 'tr' );
			if ( ! row ) {
				return;
			}
			if ( button.classList.contains( 'cfbe-row-remove' ) ) {
				if ( body.rows.length > 1 ) {
					row.remove();
				} else {
					// Keep the last row as the clone template; empty rows are dropped on save.
					Array.prototype.forEach.call( row.querySelectorAll( 'input' ), function ( input ) {
						input.value = '';
					} );
				}
			} else if ( button.classList.contains( 'cfbe-row-up' ) && row.previousElementSibling ) {
				body.insertBefore( row, row.previousElementSibling );
			} else if ( button.classList.contains( 'cfbe-row-down' ) && row.nextElementSibling ) {
				body.insertBefore( row.nextElementSibling, row );
			}
		} );
		addInterest.addEventListener( 'click', function () {
			var template = body.rows[ 0 ];
			var index = 'n' + Date.now();
			var option = addInterest.getAttribute( 'data-option' );
			var row = template ? template.cloneNode( true ) : null;
			if ( ! row ) {
				return;
			}
			Array.prototype.forEach.call( row.querySelectorAll( 'input' ), function ( input, i ) {
				input.value = '';
				input.name = option + '[interests][' + index + '][' + ( 0 === i ? 'label' : 'tag' ) + ']';
			} );
			body.appendChild( row );
			row.querySelector( 'input' ).focus();
		} );
	}

	// Form tab live preview: colours/radii → CSS variables on the wrapper, texts → preview elements.
	var preview = $( 'cfbe-preview' );
	if ( preview ) {
		preview.addEventListener( 'submit', function ( event ) {
			event.preventDefault();
		} );
		var applyVar = function ( input ) {
			preview.style.setProperty( input.getAttribute( 'data-cfbe-var' ), input.value + ( input.getAttribute( 'data-cfbe-unit' ) || '' ) );
		};
		Array.prototype.forEach.call( document.querySelectorAll( '[data-cfbe-var]' ), function ( input ) {
			input.addEventListener( 'input', function () {
				applyVar( input );
			} );
		} );
		Array.prototype.forEach.call( document.querySelectorAll( '[data-cfbe-preview-text]' ), function ( input ) {
			input.addEventListener( 'input', function () {
				var target = preview.querySelector( input.getAttribute( 'data-cfbe-preview-text' ) );
				if ( target ) {
					target.textContent = input.value.split( '{discount}' ).join( preview.getAttribute( 'data-cfbe-discount' ) || '' );
					target.hidden = '' === input.value.trim();
				}
			} );
		} );
		// Style variant: swap the cfbe-form--{variant} class on the preview.
		Array.prototype.forEach.call( document.querySelectorAll( '[data-cfbe-variant]' ), function ( radio ) {
			radio.addEventListener( 'change', function () {
				var form = preview.querySelector( '.cfbe-form' );
				if ( form && radio.checked ) {
					form.classList.remove( 'cfbe-form--card', 'cfbe-form--banner', 'cfbe-form--plain' );
					form.classList.add( 'cfbe-form--' + radio.value );
				}
			} );
		} );

		var reset = $( 'cfbe-colors-reset' );
		if ( reset ) {
			reset.addEventListener( 'click', function () {
				Array.prototype.forEach.call( document.querySelectorAll( 'input[type="color"][data-cfbe-default]' ), function ( input ) {
					input.value = input.getAttribute( 'data-cfbe-default' );
					applyVar( input );
				} );
			} );
		}
	}

	Array.prototype.forEach.call( document.querySelectorAll( '.cfbe-copy' ), function ( button ) {
		button.addEventListener( 'click', function () {
			var field = $( button.getAttribute( 'data-target' ) );
			if ( ! field ) {
				return;
			}
			var original = button.textContent;
			var done = function () {
				button.textContent = cfbeAdmin.i18n.copied;
				setTimeout( function () {
					button.textContent = original;
				}, 1500 );
			};
			if ( navigator.clipboard && window.isSecureContext ) {
				navigator.clipboard.writeText( field.value ).then( done );
			} else {
				field.select();
				document.execCommand( 'copy' );
				done();
			}
		} );
	} );
}() );
