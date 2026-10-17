/* Connector for bluefox.email – automatic popup: triggers, guards, frequency. Depends on cfbe-form.js (window.cfbeDialog). */
( function () {
	'use strict';

	var KEY = 'cfbe_popup';
	var SESSION_SHOWN = 'cfbe_popup_shown';
	var SESSION_VIEWS = 'cfbe_popup_views';
	var DAY = 86400000;
	var RETRY_MS = 2000;
	var RETRY_MAX = 30;
	// Cookie bars (e.g. GDPR Cookie Compliance) can appear ~1.2 s after `load`; opening earlier would cover them.
	var SETTLE_MS = 3000;

	function readState() {
		try {
			return JSON.parse( window.localStorage.getItem( KEY ) || '{}' ) || {};
		} catch ( e ) {
			return {};
		}
	}

	function writeState( state ) {
		try {
			window.localStorage.setItem( KEY, JSON.stringify( state ) );
		} catch ( e ) {}
	}

	function sessionGet( key ) {
		try {
			return window.sessionStorage.getItem( key );
		} catch ( e ) {
			return null;
		}
	}

	function sessionSet( key, value ) {
		try {
			window.sessionStorage.setItem( key, value );
		} catch ( e ) {}
	}

	// Frequency: never after a signup; not while a close is still fresh for this version; once per session.
	function blocked( state, cfg, now, shownThisSession ) {
		if ( state.subscribed ) {
			return true;
		}
		if ( state.v === cfg.version && ( state.dismissedUntil || 0 ) > now ) {
			return true;
		}
		return !! shownThisSession;
	}

	function matchDevice( device ) {
		if ( device === 'all' || ! window.matchMedia ) {
			return true;
		}
		var mobile = window.matchMedia( '(max-width: 767px)' ).matches;
		return device === 'mobile' ? mobile : ! mobile;
	}

	function scrollPercent() {
		var doc = document.documentElement;
		var room = Math.max( doc.scrollHeight, document.body.scrollHeight ) - window.innerHeight;
		if ( room <= 0 ) {
			return 100;
		}
		return ( ( window.pageYOffset || doc.scrollTop ) / room ) * 100;
	}

	function visible( el ) {
		var rect = el.getBoundingClientRect();
		return rect.width > 0 && rect.height > 0 && rect.bottom > 0 && rect.top < window.innerHeight;
	}

	// Reasons to wait: another dialog, typing, an inline form on screen, the cookie bar.
	function busy( cfg, dialog ) {
		if ( document.querySelector( 'dialog[open]' ) ) {
			return true;
		}
		var active = document.activeElement;
		if ( active && /^(INPUT|TEXTAREA|SELECT)$/.test( active.tagName ) ) {
			return true;
		}
		var forms = document.querySelectorAll( '.cfbe-form' );
		for ( var i = 0; i < forms.length; i++ ) {
			if ( ! dialog.contains( forms[ i ] ) && ! forms[ i ].closest( '.cfbe-dialog' ) && visible( forms[ i ] ) ) {
				return true;
			}
		}
		if ( cfg.cookieBar ) {
			try {
				var bar = document.querySelector( cfg.cookieBar );
				if ( bar && visible( bar ) ) {
					return true;
				}
			} catch ( e ) {}
		}
		return false;
	}

	function init( root ) {
		var dialog = root.querySelector( 'dialog' );
		var cfgEl = root.querySelector( '.cfbe-popup__config' );
		if ( ! dialog || ! cfgEl || typeof dialog.showModal !== 'function' || ! window.cfbeDialog ) {
			return;
		}
		var cfg;
		try {
			cfg = JSON.parse( cfgEl.textContent );
		} catch ( e ) {
			return;
		}

		var opened = false;

		function open() {
			if ( opened ) {
				return;
			}
			opened = true;
			sessionSet( SESSION_SHOWN, '1' );
			// Touch screens: no automatic keyboard; the dialog gets focus and the first field is one tap away.
			var coarse = window.matchMedia && window.matchMedia( '(pointer: coarse)' ).matches;
			window.cfbeDialog.open( dialog, null, ! coarse );
			document.dispatchEvent( new CustomEvent( 'cfbe:popup-shown' ) );
		}

		window.cfbeDialog.bind( dialog, { backdrop: cfg.closeOnBackdrop !== false } );
		dialog.addEventListener( 'cfbe:dialog-close', function ( event ) {
			var state = readState();
			var reason = state.subscribed ? 'subscribed' : event.detail.reason;
			if ( ! cfg.preview && ! state.subscribed ) {
				state.v = cfg.version;
				state.dismissedUntil = Date.now() + Math.max( 0, cfg.dismissDays || 0 ) * DAY;
				writeState( state );
			}
			document.dispatchEvent( new CustomEvent( 'cfbe:popup-closed', { detail: { reason: reason } } ) );
		} );

		window.cfbePopup = { open: open, state: readState };

		if ( cfg.preview ) {
			open();
			return;
		}

		// Page views count on every allowed page, also when the popup ends up blocked.
		var views = ( parseInt( sessionGet( SESSION_VIEWS ), 10 ) || 0 ) + 1;
		sessionSet( SESSION_VIEWS, String( views ) );

		if ( blocked( readState(), cfg, Date.now(), sessionGet( SESSION_SHOWN ) ) || ! matchDevice( cfg.device ) ) {
			return;
		}

		var triggers = cfg.triggers || {};
		var enabled = [];
		var fired = {};
		var retries = 0;
		var retryTimer = null;
		var settled = false;
		var waiting = false;

		function attempt() {
			retryTimer = null;
			if ( opened || readState().subscribed ) {
				return;
			}
			if ( ! settled ) {
				waiting = true; // Tried again once the page (and its cookie bar) has settled.
				return;
			}
			if ( busy( cfg, dialog ) ) {
				if ( ++retries <= RETRY_MAX ) {
					retryTimer = setTimeout( attempt, RETRY_MS );
				}
				return;
			}
			open();
		}

		function fire( name ) {
			if ( fired[ name ] || opened ) {
				return;
			}
			fired[ name ] = true;
			var ready = cfg.combine === 'all' ? enabled.every( function ( n ) {
				return fired[ n ];
			} ) : true;
			if ( ready && ! retryTimer ) {
				attempt();
			}
		}

		if ( triggers.scroll ) {
			enabled.push( 'scroll' );
		}
		if ( triggers.delay ) {
			enabled.push( 'delay' );
		}
		if ( triggers.exit && window.matchMedia && window.matchMedia( '(pointer: fine)' ).matches ) {
			enabled.push( 'exit' );
		}
		if ( triggers.pageviews ) {
			enabled.push( 'pageviews' );
		}
		if ( ! enabled.length ) {
			return;
		}

		if ( triggers.scroll ) {
			var ticking = false;
			var onScroll = function () {
				if ( ticking ) {
					return;
				}
				ticking = true;
				window.requestAnimationFrame( function () {
					ticking = false;
					if ( scrollPercent() >= triggers.scroll ) {
						window.removeEventListener( 'scroll', onScroll );
						fire( 'scroll' );
					}
				} );
			};
			window.addEventListener( 'scroll', onScroll, { passive: true } );
			// Short pages that can't scroll count as fully read once the layout has settled.
			setTimeout( onScroll, 1000 );
		}

		if ( triggers.delay ) {
			setTimeout( function () {
				fire( 'delay' );
			}, triggers.delay * 1000 );
		}

		if ( enabled.indexOf( 'exit' ) !== -1 ) {
			var loadedAt = Date.now();
			var onLeave = function ( event ) {
				// Leaving the window through the top edge; a fast flick is often last seen a few pixels inside the page.
				if ( ! event.relatedTarget && event.clientY < 10 && Date.now() - loadedAt > 3000 ) {
					document.removeEventListener( 'mouseout', onLeave );
					fire( 'exit' );
				}
			};
			document.addEventListener( 'mouseout', onLeave );
		}

		if ( triggers.pageviews && views >= triggers.pageviews ) {
			fire( 'pageviews' );
		}

		var settle = function () {
			setTimeout( function () {
				settled = true;
				if ( waiting && ! retryTimer ) {
					attempt();
				}
			}, SETTLE_MS );
		};
		if ( document.readyState === 'complete' ) {
			settle();
		} else {
			window.addEventListener( 'load', settle );
		}
	}

	function start() {
		Array.prototype.forEach.call( document.querySelectorAll( '[data-cfbe-popup]' ), init );
	}

	if ( document.readyState === 'loading' ) {
		document.addEventListener( 'DOMContentLoaded', start );
	} else {
		start();
	}
}() );
