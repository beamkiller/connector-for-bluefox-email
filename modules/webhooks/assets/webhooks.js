/* global cfbeAdmin, cfbeWebhooks */
( function () {
	'use strict';

	var result = document.getElementById( 'cfbe-webhook-result' );
	var register = document.getElementById( 'cfbe-webhook-register' );
	var test = document.getElementById( 'cfbe-webhook-test' );
	var refresh = document.getElementById( 'cfbe-webhook-refresh' );
	if ( ! result || ! register ) {
		return;
	}

	function show( text, ok ) {
		result.textContent = text;
		result.className = 'cfbe-result ' + ( ok ? 'is-ok' : 'is-error' );
	}

	function post( action, fields ) {
		var body = new FormData();
		body.append( 'action', action );
		body.append( 'nonce', cfbeAdmin.nonce );
		( fields || [] ).forEach( function ( pair ) {
			body.append( pair[ 0 ], pair[ 1 ] );
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

	function run( button, promise, onOk ) {
		button.disabled = true;
		show( cfbeAdmin.i18n.working, true );
		promise.then( onOk ).catch( function ( err ) {
			show( err.message, false );
		} ).then( function () {
			button.disabled = false;
		} );
	}

	function describe( data ) {
		show( data.message, data.configured && data.matches && data.key_matches );
	}

	register.addEventListener( 'click', function () {
		var fields = [];
		Array.prototype.forEach.call( document.querySelectorAll( '#cfbe-webhook-events input[data-event]' ), function ( box ) {
			if ( box.checked ) {
				fields.push( [ 'events[]', box.getAttribute( 'data-event' ) ] );
			}
		} );
		run( register, post( 'cfbe_webhook_register', fields ), function ( data ) {
			describe( data );
			if ( data.matches && data.key_matches ) {
				result.textContent = cfbeWebhooks.i18n.registered + ' ' + data.message;
			}
		} );
	} );

	if ( test ) {
		test.addEventListener( 'click', function () {
			var type = document.getElementById( 'cfbe-webhook-test-type' );
			run( test, post( 'cfbe_webhook_test', [ [ 'type', type ? type.value : 'subscribe' ] ] ), function () {
				show( cfbeWebhooks.i18n.sent, true );
			} );
		} );
	}

	function status() {
		run( refresh || register, post( 'cfbe_webhook_status' ), describe );
	}
	if ( refresh ) {
		refresh.addEventListener( 'click', status );
	}
	status();
}() );
