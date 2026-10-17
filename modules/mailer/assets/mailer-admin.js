/**
 * Email delivery tab: refresh transactional emails, create the template, send a test.
 */
( function () {
	'use strict';

	if ( 'undefined' === typeof window.cfbeMailer ) {
		return;
	}
	var cfg = window.cfbeMailer;

	function $( id ) {
		return document.getElementById( id );
	}

	function post( action, data ) {
		var body = new FormData();
		body.append( 'action', action );
		body.append( 'nonce', cfg.nonce );
		Object.keys( data || {} ).forEach( function ( key ) {
			body.append( key, data[ key ] );
		} );
		return fetch( cfg.ajaxUrl, { method: 'POST', credentials: 'same-origin', body: body } )
			.then( function ( response ) {
				return response.json().catch( function () {
					return { success: false, data: { message: cfg.i18n.error } };
				} );
			} )
			.then( function ( json ) {
				if ( ! json || ! json.success ) {
					throw new Error( ( json && json.data && json.data.message ) || cfg.i18n.error );
				}
				return json.data || {};
			} );
	}

	function status( el, text, isError ) {
		if ( ! el ) {
			return;
		}
		el.textContent = text;
		el.style.color = isError ? '#b32d2e' : '';
	}

	function run( button, statusEl, action, data, onSuccess ) {
		button.disabled = true;
		status( statusEl, cfg.i18n.working, false );
		return post( action, data )
			.then( function ( result ) {
				onSuccess( result );
			} )
			.catch( function ( error ) {
				status( statusEl, error.message, true );
			} )
			.then( function () {
				button.disabled = false;
			} );
	}

	function fillEmails( emails, selected ) {
		var select = $( 'cfbe-mailer-email' );
		if ( ! select ) {
			return;
		}
		var current = selected || select.value;
		select.textContent = '';
		var empty = document.createElement( 'option' );
		empty.value = '';
		empty.textContent = cfg.i18n.none;
		select.appendChild( empty );
		emails.forEach( function ( item ) {
			var option = document.createElement( 'option' );
			option.value = item.id;
			option.textContent = item.name;
			option.selected = item.id === current;
			select.appendChild( option );
		} );
	}

	var refresh = $( 'cfbe-mailer-refresh' );
	if ( refresh ) {
		refresh.addEventListener( 'click', function () {
			var statusEl = $( 'cfbe-mailer-refresh-status' );
			run( refresh, statusEl, 'cfbe_mailer_load_emails', {}, function ( data ) {
				fillEmails( data.emails || [] );
				status( statusEl, cfg.i18n.loaded, false );
			} );
		} );
	}

	var identities = $( 'cfbe-mailer-identities' );
	if ( identities ) {
		identities.addEventListener( 'click', function () {
			var statusEl = $( 'cfbe-mailer-create-status' );
			run( identities, statusEl, 'cfbe_mailer_load_identities', {}, function ( data ) {
				var list = data.identities || [];
				if ( ! list.length ) {
					// No verified sender yet (e.g. a sandbox project): bluefox then uses the project's default sender.
					list = [ { id: '', name: cfg.i18n.defaultSender } ];
				}
				var select = $( 'cfbe-mailer-identity' );
				select.textContent = '';
				list.forEach( function ( item ) {
					var option = document.createElement( 'option' );
					option.value = item.id;
					option.textContent = item.name;
					select.appendChild( option );
				} );
				$( 'cfbe-mailer-identity-wrap' ).hidden = false;
				status( statusEl, cfg.i18n.identities, false );
				select.focus();
			} );
		} );
	}

	var create = $( 'cfbe-mailer-create' );
	if ( create ) {
		create.addEventListener( 'click', function () {
			var statusEl = $( 'cfbe-mailer-create-status' );
			run( create, statusEl, 'cfbe_mailer_create_template', { identity: $( 'cfbe-mailer-identity' ).value }, function ( data ) {
				fillEmails( data.emails || [], data.id );
				$( 'cfbe-mailer-identity-wrap' ).hidden = true;
				status( statusEl, data.message || '', false );
				var test = $( 'cfbe-mailer-test' );
				if ( test ) {
					test.disabled = false;
				}
			} );
		} );
	}

	var test = $( 'cfbe-mailer-test' );
	if ( test ) {
		test.addEventListener( 'click', function () {
			var statusEl = $( 'cfbe-mailer-test-status' );
			run( test, statusEl, 'cfbe_mailer_send_test', { to: $( 'cfbe-mailer-test-to' ).value }, function ( data ) {
				status( statusEl, data.message || '', false );
			} );
		} );
	}
}() );
