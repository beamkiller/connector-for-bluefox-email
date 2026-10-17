/* Contact sync tab: create fields, start / cancel the backfill, poll progress. */
( function () {
	'use strict';

	var cfg = window.cfbeSync;
	if ( ! cfg ) {
		return;
	}

	var fieldsBtn = document.getElementById( 'cfbe-sync-fields' );
	var fieldsOut = document.getElementById( 'cfbe-sync-fields-result' );
	var startBtn = document.getElementById( 'cfbe-sync-start' );
	var cancelBtn = document.getElementById( 'cfbe-sync-cancel' );
	var progress = document.getElementById( 'cfbe-sync-progress' );
	var timer = null;

	function post( action ) {
		var body = new FormData();
		body.append( 'action', action );
		body.append( 'nonce', cfg.nonce );
		return fetch( cfg.ajaxUrl, { method: 'POST', credentials: 'same-origin', body: body } ).then( function ( res ) {
			return res.json().catch( function () {
				return { success: false, data: {} };
			} );
		} );
	}

	function message( json ) {
		return ( json && json.data && json.data.message ) || cfg.i18n.error;
	}

	function showStatus( json ) {
		if ( ! progress ) {
			return;
		}
		progress.textContent = message( json );
		var running = !! ( json && json.success && json.data.running );
		if ( startBtn ) {
			startBtn.disabled = running;
		}
		if ( cancelBtn ) {
			cancelBtn.disabled = ! running;
		}
		clearTimeout( timer );
		if ( running ) {
			timer = setTimeout( poll, 15000 );
		}
	}

	function poll() {
		post( 'cfbe_sync_status' ).then( showStatus, function () {} );
	}

	if ( fieldsBtn && fieldsOut ) {
		fieldsBtn.addEventListener( 'click', function () {
			fieldsBtn.disabled = true;
			fieldsOut.textContent = cfg.i18n.working;
			post( 'cfbe_sync_fields' )
				.then( function ( json ) {
					fieldsOut.textContent = message( json );
				}, function () {
					fieldsOut.textContent = cfg.i18n.error;
				} )
				.then( function () {
					fieldsBtn.disabled = false;
				} );
		} );
	}

	if ( startBtn ) {
		startBtn.addEventListener( 'click', function () {
			if ( ! window.confirm( cfg.i18n.confirmStart ) ) {
				return;
			}
			startBtn.disabled = true;
			progress.textContent = cfg.i18n.working;
			post( 'cfbe_sync_start' ).then( showStatus, function () {
				progress.textContent = cfg.i18n.error;
				startBtn.disabled = false;
			} );
		} );
	}

	if ( cancelBtn ) {
		cancelBtn.addEventListener( 'click', function () {
			if ( ! window.confirm( cfg.i18n.confirmCancel ) ) {
				return;
			}
			cancelBtn.disabled = true;
			post( 'cfbe_sync_cancel' ).then( showStatus, function () {
				cancelBtn.disabled = false;
			} );
		} );
	}

	if ( progress && '1' === progress.getAttribute( 'data-running' ) ) {
		timer = setTimeout( poll, 15000 );
	}
}() );
