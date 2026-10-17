/* global cfbeWooEvents */
/**
 * WooCommerce emails tab: repeatable rule rows, email select per type, list refresh.
 */
( function () {
	'use strict';

	if ( typeof cfbeWooEvents === 'undefined' ) {
		return;
	}

	var body = document.getElementById( 'cfbe-rules-body' );
	var tpl = document.getElementById( 'cfbe-rule-template' );
	var emails = cfbeWooEvents.emails;

	if ( ! body || ! tpl ) {
		return;
	}

	function fillEmails( row ) {
		var type = row.querySelector( '.cfbe-rule-type' ).value;
		var select = row.querySelector( '.cfbe-rule-email' );
		var current = select.value || select.getAttribute( 'data-value' ) || '';
		var list = emails[ type ] || {};
		var found = false;

		while ( select.firstChild ) {
			select.removeChild( select.firstChild );
		}
		select.appendChild( new Option( cfbeWooEvents.i18n.choose, '' ) );
		Object.keys( list ).forEach( function ( id ) {
			var opt = new Option( list[ id ], id, false, id === current );
			found = found || id === current;
			select.appendChild( opt );
		} );
		// Keep a stored ID that is not in the (possibly stale) list instead of silently dropping it.
		if ( current && ! found ) {
			select.appendChild( new Option( current + ' ' + cfbeWooEvents.i18n.unknown, current, true, true ) );
		}
		select.setAttribute( 'data-value', select.value );
	}

	function enforceType( row ) {
		var event = row.querySelector( '.cfbe-rule-event' ).value;
		var type = row.querySelector( '.cfbe-rule-type' );
		var only = cfbeWooEvents.triggeredOnly.indexOf( event ) !== -1;
		Array.prototype.forEach.call( type.options, function ( opt ) {
			opt.disabled = only && opt.value !== 'triggered';
		} );
		if ( only && type.value !== 'triggered' ) {
			type.value = 'triggered';
			fillEmails( row );
		}
	}

	function reindex() {
		Array.prototype.forEach.call( body.querySelectorAll( 'tr.cfbe-rule' ), function ( row, i ) {
			Array.prototype.forEach.call( row.querySelectorAll( '[name]' ), function ( el ) {
				el.name = el.name.replace( /\[rules\]\[[^\]]*\]/, '[rules][' + i + ']' );
			} );
		} );
	}

	function initRow( row ) {
		enforceType( row );
	}

	body.addEventListener( 'change', function ( e ) {
		var row = e.target.closest( 'tr.cfbe-rule' );
		if ( ! row ) {
			return;
		}
		if ( e.target.classList.contains( 'cfbe-rule-type' ) ) {
			fillEmails( row );
		} else if ( e.target.classList.contains( 'cfbe-rule-event' ) ) {
			enforceType( row );
		} else if ( e.target.classList.contains( 'cfbe-rule-email' ) ) {
			e.target.setAttribute( 'data-value', e.target.value );
		}
	} );

	body.addEventListener( 'click', function ( e ) {
		if ( ! e.target.classList.contains( 'cfbe-rule-remove' ) ) {
			return;
		}
		e.preventDefault();
		if ( window.confirm( cfbeWooEvents.i18n.remove ) ) {
			e.target.closest( 'tr.cfbe-rule' ).remove();
			reindex();
		}
	} );

	var add = document.getElementById( 'cfbe-rule-add' );
	if ( add ) {
		add.addEventListener( 'click', function () {
			var row = tpl.content.firstElementChild.cloneNode( true );
			body.appendChild( row );
			reindex();
			fillEmails( row );
			initRow( row );
			row.querySelector( '.cfbe-rule-event' ).focus();
		} );
	}

	Array.prototype.forEach.call( body.querySelectorAll( 'tr.cfbe-rule' ), initRow );

	var refresh = document.getElementById( 'cfbe-woo-events-refresh' );
	var out = document.getElementById( 'cfbe-woo-events-refresh-result' );
	if ( refresh ) {
		refresh.addEventListener( 'click', function () {
			var data = new FormData();
			data.append( 'action', cfbeWooEvents.action );
			data.append( 'nonce', cfbeWooEvents.nonce );
			refresh.disabled = true;
			out.textContent = cfbeWooEvents.i18n.working;
			out.className = 'cfbe-result';
			fetch( cfbeWooEvents.ajaxUrl, { method: 'POST', credentials: 'same-origin', body: data } )
				.then( function ( res ) {
					return res.json();
				} )
				.then( function ( json ) {
					if ( ! json || ! json.success ) {
						throw new Error( ( json && json.data && json.data.message ) || cfbeWooEvents.i18n.error );
					}
					emails = { transactional: json.data.transactional || {}, triggered: json.data.triggered || {} };
					Array.prototype.forEach.call( body.querySelectorAll( 'tr.cfbe-rule' ), fillEmails );
					out.textContent = cfbeWooEvents.i18n.loaded;
					out.className = 'cfbe-result is-ok';
				} )
				.catch( function ( err ) {
					out.textContent = err.message;
					out.className = 'cfbe-result is-error';
				} )
				.then( function () {
					refresh.disabled = false;
				} );
		} );
	}
} )();
