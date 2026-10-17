/* global cfbeCartCapture */
/**
 * Classic checkout: report the typed billing email (and newsletter checkbox) so an abandoned cart can be reminded.
 */
( function () {
	'use strict';

	if ( typeof cfbeCartCapture === 'undefined' || ! window.fetch ) {
		return;
	}

	var EMAIL_RE = /^[^\s@]+@[^\s@]+\.[^\s@]+$/;
	var last = '';
	var timer = null;

	function field( id ) {
		return document.getElementById( id );
	}

	function send() {
		var input = field( 'billing_email' );
		if ( ! input ) {
			return;
		}
		var email = ( input.value || '' ).trim();
		if ( ! EMAIL_RE.test( email ) || email === last ) {
			return;
		}
		last = email;
		fetch( cfbeCartCapture.url, {
			method: 'POST',
			credentials: 'same-origin',
			headers: {
				'Content-Type': 'application/json',
				'X-WP-Nonce': cfbeCartCapture.nonce,
			},
			body: JSON.stringify( { email: email } ),
		} ).catch( function () {} );
	}

	function schedule() {
		window.clearTimeout( timer );
		timer = window.setTimeout( send, 800 );
	}

	document.addEventListener( 'change', function ( e ) {
		if ( e.target && e.target.id === 'billing_email' ) {
			schedule();
		}
	} );
	document.addEventListener( 'focusout', function ( e ) {
		if ( e.target && e.target.id === 'billing_email' ) {
			schedule();
		}
	} );
	// Prefilled email (returning customer, browser autofill).
	window.addEventListener( 'load', schedule );
} )();
