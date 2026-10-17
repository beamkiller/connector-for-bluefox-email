/* Connector for bluefox.email – signup form. Vanilla JS, no build, cache-safe (config is static). */
/* global cfbeForm */
( function () {
	'use strict';

	var config = window.cfbeForm || {};
	var i18n = config.i18n || {};
	var EMAIL_RE = /^[^\s@]+@[^\s@]+\.[^\s@]{2,}$/;

	function now() {
		return window.performance && performance.now ? performance.now() : Date.now();
	}

	function field( form, name ) {
		return form.querySelector( '[name="' + name + '"]' );
	}

	function errorSlot( el ) {
		var id = el && el.getAttribute( 'aria-describedby' );
		return id ? document.getElementById( id ) : null;
	}

	function setError( el, message ) {
		var slot = errorSlot( el );
		if ( el.tagName !== 'FIELDSET' ) {
			el.setAttribute( 'aria-invalid', message ? 'true' : 'false' );
		}
		if ( slot ) {
			slot.textContent = message || '';
			slot.hidden = ! message;
		}
	}

	function clearErrors( form ) {
		Array.prototype.forEach.call( form.querySelectorAll( '[aria-describedby]' ), function ( el ) {
			setError( el, '' );
		} );
	}

	function interestsTarget( form ) {
		var select = form.querySelector( 'select[name="interests"]' );
		return select || form.querySelector( '.cfbe-field--checkboxes' );
	}

	function collect( form ) {
		var interests = [];
		var select = form.querySelector( 'select[name="interests"]' );
		if ( select && select.value ) {
			interests.push( select.value );
		}
		Array.prototype.forEach.call( form.querySelectorAll( 'input[name="interests[]"]:checked' ), function ( box ) {
			interests.push( box.value );
		} );
		var name = field( form, 'name' );
		var hp = field( form, 'cfbe_hp' );
		var cfg = field( form, 'cfbe_cfg' );
		return {
			email: field( form, 'email' ).value.trim(),
			name: name ? name.value.trim() : '',
			interests: interests,
			consent: field( form, 'consent' ).checked,
			source: form.getAttribute( 'data-cfbe-source' ) || 'shortcode',
			cfbe_cfg: cfg ? cfg.value : '',
			cfbe_hp: hp ? hp.value : '',
		};
	}

	// Fill-time token signed by the site (bot trap). Fetched when the form shows, so the page itself can be cached.
	function fetchToken( form ) {
		if ( form.cfbeTokenPromise ) {
			return form.cfbeTokenPromise;
		}
		form.cfbeTokenPromise = fetch( config.tokenUrl, { credentials: 'same-origin', cache: 'no-store' } )
			.then( function ( res ) {
				return res.json();
			} )
			.then( function ( json ) {
				form.cfbeToken = ( json && json.token ) || '';
				form.cfbeTokenAt = now();
				return form.cfbeToken;
			} )
			.catch( function () {
				form.cfbeTokenPromise = null; // Try again on submit.
				return '';
			} );
		return form.cfbeTokenPromise;
	}

	// Mirrors the server rules; the server stays the authority.
	function validate( form, data ) {
		var errors = [];
		var email = field( form, 'email' );
		var name = field( form, 'name' );
		var interests = interestsTarget( form );

		if ( ! EMAIL_RE.test( data.email ) ) {
			errors.push( [ email, i18n.emailInvalid ] );
		}
		if ( name && name.required && ! data.name ) {
			errors.push( [ name, i18n.nameRequired ] );
		}
		if ( interests && form.querySelector( '.cfbe-field--interests .cfbe-req' ) && ! data.interests.length ) {
			errors.push( [ interests, i18n.interestsRequired ] );
		}
		if ( ! data.consent ) {
			errors.push( [ field( form, 'consent' ), i18n.consentRequired ] );
		}
		return errors;
	}

	function showErrors( form, errors ) {
		errors.forEach( function ( pair ) {
			setError( pair[ 0 ], pair[ 1 ] );
		} );
		var first = errors[ 0 ] && errors[ 0 ][ 0 ];
		if ( first ) {
			( first.tagName === 'FIELDSET' ? first.querySelector( 'input' ) : first ).focus();
		}
	}

	function serverFieldTarget( form, key ) {
		if ( key === 'interests' ) {
			return interestsTarget( form );
		}
		return field( form, key );
	}

	function status( form, message, success ) {
		var box = form.querySelector( '.cfbe-form__status' );
		box.textContent = message || '';
		box.classList.toggle( 'is-success', !! success );
		return box;
	}

	function busy( form, on ) {
		var button = form.querySelector( '.cfbe-form__submit' );
		form.cfbeBusy = on;
		button.disabled = on;
		button.setAttribute( 'aria-busy', on ? 'true' : 'false' );
	}

	function done( form, json ) {
		var wrapper = form.closest( '.cfbe-form' );
		var box = status( form, json.message, true );
		if ( wrapper ) {
			wrapper.classList.add( 'is-done' );
		}
		box.focus();
		document.dispatchEvent( new CustomEvent( 'cfbe:subscribed', { detail: { code: json.code, source: form.getAttribute( 'data-cfbe-source' ) } } ) );
	}

	function submit( event ) {
		var form = event.currentTarget;
		event.preventDefault();
		if ( form.cfbeBusy ) {
			return;
		}
		clearErrors( form );
		status( form, '' );

		var data = collect( form );
		var errors = validate( form, data );
		if ( errors.length ) {
			showErrors( form, errors );
			status( form, i18n.fixFields );
			return;
		}

		busy( form, true );

		// The server silently drops a submission whose token is younger than minElapsed (bot trap). A real person
		// who autofills and clicks quickly must not be dropped, so wait out the remainder first.
		// wp_localize_script() stringifies values: "3000" + 300 would concatenate, so cast.
		fetchToken( form )
			.then( function () {
				var age = form.cfbeTokenAt ? now() - form.cfbeTokenAt : 0;
				var wait = Math.max( 0, ( Number( config.minElapsed ) || 0 ) + 300 - age );
				return new Promise( function ( resolve ) {
					setTimeout( resolve, wait );
				} );
			} )
			.then( function () {
				data.cfbe_token = form.cfbeToken || '';
				return fetch( config.restUrl, {
					method: 'POST',
					credentials: 'same-origin',
					headers: { 'Content-Type': 'application/json', Accept: 'application/json' },
					body: JSON.stringify( data ),
				} );
			} )
			.then( function ( res ) {
				return res.json().catch( function () {
					return {};
				} );
			} )
			.then( function ( json ) {
				if ( json && json.success ) {
					done( form, json );
					return;
				}
				var fields = ( json && json.fields ) || {};
				var serverErrors = Object.keys( fields ).map( function ( key ) {
					return [ serverFieldTarget( form, key ), fields[ key ] ];
				} ).filter( function ( pair ) {
					return pair[ 0 ];
				} );
				if ( serverErrors.length ) {
					showErrors( form, serverErrors );
				}
				status( form, ( json && json.message ) || i18n.error );
			} )
			.catch( function () {
				status( form, i18n.error );
			} )
			.then( function () {
				busy( form, false );
			} );
	}

	function startTimer( form ) {
		if ( ! form.cfbeShownAt ) {
			form.cfbeShownAt = now();
			fetchToken( form );
		}
	}

	function init( form ) {
		if ( form.cfbeReady ) {
			return;
		}
		form.cfbeReady = true;
		form.addEventListener( 'submit', submit );

		// Clear a field's error as soon as it is corrected.
		form.addEventListener( 'change', function ( event ) {
			var target = event.target.closest( '.cfbe-field' );
			var el = target && target.querySelector( '[aria-describedby]' );
			if ( target && target.tagName === 'FIELDSET' ) {
				el = target;
			}
			if ( el && el.getAttribute( 'aria-invalid' ) !== null ) {
				setError( el, '' );
			}
		} );

		// The fill-time token is fetched when the form is actually visible (popup forms: on open).
		if ( form.getAttribute( 'data-cfbe-source' ) === 'popup' ) {
			return;
		}
		if ( 'IntersectionObserver' in window ) {
			var observer = new IntersectionObserver( function ( entries ) {
				if ( entries[ 0 ].isIntersecting ) {
					startTimer( form );
					observer.disconnect();
				}
			} );
			observer.observe( form );
		} else {
			startTimer( form );
		}
	}

	// Shared <dialog> helpers (promo teaser and popup).
	// focusField false: focus the dialog itself (an unrequested popup must not pop up the phone keyboard).
	function dialogOpened( dialog, focusField ) {
		document.dispatchEvent( new CustomEvent( 'cfbe:popup-open', { detail: { root: dialog } } ) );
		var first = focusField === false ? null : dialog.querySelector( '.cfbe-field__input' );
		if ( first ) {
			first.focus();
		} else {
			dialog.setAttribute( 'tabindex', '-1' );
			dialog.focus();
		}
	}

	function openDialog( dialog, returnTo, focusField ) {
		dialog.cfbeReturn = returnTo || document.activeElement;
		dialog.cfbeReason = '';
		dialog.showModal();
		document.documentElement.classList.add( 'cfbe-dialog-open' );
		dialogOpened( dialog, focusField );
	}

	function closeDialog( dialog, reason ) {
		dialog.cfbeReason = reason || 'close';
		dialog.close();
	}

	// Close button, backdrop click (optional), ESC (native cancel). Restores focus and reports why it closed.
	function bindDialog( dialog, options ) {
		if ( dialog.cfbeBound ) {
			return;
		}
		dialog.cfbeBound = true;
		var closeBtn = dialog.querySelector( '.cfbe-dialog__close' );
		if ( closeBtn ) {
			closeBtn.addEventListener( 'click', function () {
				closeDialog( dialog, 'close' );
			} );
		}
		if ( ! options || options.backdrop !== false ) {
			// The click target is the dialog itself only outside its content box.
			dialog.addEventListener( 'click', function ( event ) {
				if ( event.target === dialog ) {
					closeDialog( dialog, 'backdrop' );
				}
			} );
		}
		dialog.addEventListener( 'cancel', function () {
			dialog.cfbeReason = 'esc';
		} );
		dialog.addEventListener( 'close', function () {
			document.documentElement.classList.remove( 'cfbe-dialog-open' );
			var back = dialog.cfbeReturn;
			if ( back && back.focus && document.body.contains( back ) ) {
				back.focus();
			}
			dialog.dispatchEvent( new CustomEvent( 'cfbe:dialog-close', { detail: { reason: dialog.cfbeReason || 'close' } } ) );
		} );
	}

	window.cfbeDialog = { open: openDialog, close: closeDialog, bind: bindDialog };

	// [cfbe_promo action="scroll"]: the form to scroll to - target selector, else the last inline form on the page.
	function promoTarget( promo ) {
		var selector = promo.getAttribute( 'data-cfbe-target' );
		if ( selector ) {
			try {
				var el = document.querySelector( selector );
				var block = el && ( el.classList.contains( 'cfbe-form' ) ? el : el.querySelector( '.cfbe-form' ) );
				if ( block ) {
					return block;
				}
			} catch ( e ) {}
		}
		var inline = Array.prototype.filter.call( document.querySelectorAll( '.cfbe-form' ), function ( block ) {
			return ! block.closest( '.cfbe-dialog' ) && ! block.closest( '[data-cfbe-promo]' ) && block.getClientRects().length;
		} );
		return inline.length ? inline[ inline.length - 1 ] : null;
	}

	// Scrolls the form into view and puts the cursor in the e-mail field.
	function scrollToForm( block ) {
		var reduced = window.matchMedia && window.matchMedia( '(prefers-reduced-motion: reduce)' ).matches;
		block.scrollIntoView( { behavior: reduced ? 'auto' : 'smooth', block: 'center' } );
		var email = block.querySelector( 'input[name="email"]' );
		if ( email ) {
			email.focus( { preventScroll: true } );
		}
	}

	// [cfbe_promo]: the button opens the form in a native <dialog> (without showModal() it expands inline),
	// or with action="scroll" scrolls to a form already on the page (falls back to the dialog when there is none).
	function initPromo( promo ) {
		if ( promo.cfbeReady ) {
			return;
		}
		promo.cfbeReady = true;
		var opener = promo.querySelector( '.cfbe-promo__open' );
		var dialog = promo.querySelector( '.cfbe-dialog' );
		if ( ! opener || ! dialog ) {
			return;
		}
		var scroll = promo.getAttribute( 'data-cfbe-action' ) === 'scroll';
		var modal = typeof dialog.showModal === 'function';
		var openHere;

		if ( modal ) {
			bindDialog( dialog );
			openHere = function () {
				openDialog( dialog, opener );
			};
		} else {
			var closeBtn = dialog.querySelector( '.cfbe-dialog__close' );
			dialog.classList.add( 'is-inline' );
			dialog.hidden = true;
			openHere = function () {
				dialog.hidden = false;
				opener.setAttribute( 'aria-expanded', 'true' );
				dialogOpened( dialog );
			};
			if ( closeBtn ) {
				closeBtn.addEventListener( 'click', function () {
					dialog.hidden = true;
					opener.setAttribute( 'aria-expanded', 'false' );
					opener.focus();
				} );
			}
		}

		// Button semantics follow what the click will really do.
		if ( scroll && promoTarget( promo ) ) {
			opener.removeAttribute( 'aria-haspopup' );
			opener.removeAttribute( 'aria-controls' );
		} else if ( modal ) {
			opener.setAttribute( 'aria-haspopup', 'dialog' );
			opener.setAttribute( 'aria-controls', dialog.id );
		} else {
			opener.removeAttribute( 'aria-haspopup' );
			opener.setAttribute( 'aria-expanded', 'false' );
			opener.setAttribute( 'aria-controls', dialog.id );
		}

		opener.addEventListener( 'click', function () {
			var target = scroll ? promoTarget( promo ) : null;
			if ( target ) {
				scrollToForm( target );
			} else {
				openHere();
			}
		} );
	}

	// Any successful signup hides the automatic popup for good (also on pages without it).
	document.addEventListener( 'cfbe:subscribed', function () {
		try {
			var state = JSON.parse( window.localStorage.getItem( 'cfbe_popup' ) || '{}' ) || {};
			state.subscribed = true;
			window.localStorage.setItem( 'cfbe_popup', JSON.stringify( state ) );
		} catch ( e ) {}
	} );

	function initAll( root ) {
		var scope = root || document;
		Array.prototype.forEach.call( scope.querySelectorAll( '.cfbe-form__form' ), init );
		Array.prototype.forEach.call( scope.querySelectorAll( '[data-cfbe-promo]' ), initPromo );
	}

	document.addEventListener( 'cfbe:popup-open', function ( event ) {
		var root = event.detail && event.detail.root ? event.detail.root : document;
		Array.prototype.forEach.call( root.querySelectorAll( '.cfbe-form__form' ), function ( form ) {
			init( form );
			startTimer( form );
		} );
	} );

	if ( document.readyState === 'loading' ) {
		document.addEventListener( 'DOMContentLoaded', function () {
			initAll();
		} );
	} else {
		initAll();
	}

	window.cfbeInitForms = initAll;
}() );
