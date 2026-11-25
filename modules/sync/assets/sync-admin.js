/* Contact sync tab: create fields, start / resume / cancel the backfill, live progress. */
( function () {
	'use strict';

	var cfg = window.cfbeSync;
	if ( ! cfg ) {
		return;
	}

	var POLL_MS = 4000;
	var fieldsBtn = document.getElementById( 'cfbe-sync-fields' );
	var fieldsOut = document.getElementById( 'cfbe-sync-fields-result' );
	var startBtn = document.getElementById( 'cfbe-sync-start' );
	var resumeBtn = document.getElementById( 'cfbe-sync-resume' );
	var cancelBtn = document.getElementById( 'cfbe-sync-cancel' );
	var live = document.getElementById( 'cfbe-sync-live' );
	var progress = document.getElementById( 'cfbe-sync-progress' );
	var bar = document.getElementById( 'cfbe-sync-bar' );
	var percent = document.getElementById( 'cfbe-sync-percent' );
	var stats = document.getElementById( 'cfbe-sync-stats' );
	var health = document.getElementById( 'cfbe-sync-health' );
	var errors = document.getElementById( 'cfbe-sync-errors' );
	var errorsBody = document.getElementById( 'cfbe-sync-errors-body' );
	var errorsCount = document.getElementById( 'cfbe-sync-errors-count' );
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

	function format( text, a, b ) {
		return text.replace( '%1$d', a ).replace( '%2$d', b );
	}

	function cell( row, text ) {
		var td = document.createElement( 'td' );
		td.textContent = text;
		row.appendChild( td );
	}

	function renderErrors( list ) {
		if ( ! errors || ! errorsBody ) {
			return;
		}
		errors.hidden = ! list.length;
		errorsCount.textContent = String( list.length );
		errorsBody.textContent = '';
		list.forEach( function ( e ) {
			var row = document.createElement( 'tr' );
			cell( row, new Date( e.time * 1000 ).toLocaleString() );
			cell( row, e.email || '' );
			cell( row, ( e.status ? 'HTTP ' + e.status + ': ' : '' ) + ( e.message || e.code || '' ) );
			errorsBody.appendChild( row );
		} );
	}

	function showStatus( json ) {
		if ( ! progress ) {
			return;
		}
		if ( ! json || ! json.success ) {
			progress.textContent = message( json );
			schedule( true );
			return;
		}
		var d = json.data;
		progress.textContent = d.message;
		if ( bar ) {
			bar.value = d.percent;
			bar.textContent = d.percent + '%';
		}
		if ( percent ) {
			percent.textContent = d.percent + '%';
		}
		if ( stats ) {
			stats.textContent = d.running && d.per_minute > 0 ? format( cfg.i18n.stats, d.per_minute, d.eta_minutes ) : '';
		}
		if ( health ) {
			health.hidden = ! d.health.text;
			health.firstElementChild.textContent = d.health.text;
		}
		renderErrors( d.errors || [] );
		if ( startBtn ) {
			startBtn.disabled = d.running;
		}
		if ( resumeBtn ) {
			// WordPress' .button sets display: inline-block, which beats the hidden attribute.
			resumeBtn.hidden = ! d.can_resume;
			resumeBtn.style.display = d.can_resume ? '' : 'none';
		}
		if ( cancelBtn ) {
			cancelBtn.disabled = ! d.running && d.status !== 'paused';
		}
		live.setAttribute( 'data-running', d.running ? '1' : '0' );
		live.setAttribute( 'data-health', d.health.code );
		schedule( d.running );
	}

	function schedule( running ) {
		clearTimeout( timer );
		if ( running ) {
			timer = setTimeout( poll, POLL_MS );
		}
	}

	// A stalled sync is pushed on from this page (the server runs the due batch); otherwise just read the status.
	function poll() {
		var stalled = /^stalled/.test( live.getAttribute( 'data-health' ) || '' );
		post( stalled ? 'cfbe_sync_tick' : 'cfbe_sync_status' ).then( showStatus, function () {
			schedule( true );
		} );
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

	if ( resumeBtn ) {
		resumeBtn.addEventListener( 'click', function () {
			resumeBtn.disabled = true;
			post( 'cfbe_sync_resume' ).then( showStatus, function () {} ).then( function () {
				resumeBtn.disabled = false;
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

	if ( live ) {
		// Show the live numbers (and push on a stalled sync) right away.
		poll();
	}
}() );
