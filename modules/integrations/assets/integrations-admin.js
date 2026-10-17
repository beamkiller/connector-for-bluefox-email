/**
 * Form integrations tab: repeatable form rules and custom field rows (vanilla JS, no build step).
 */
( function () {
	'use strict';

	var form = document.querySelector( '.cfbe-integrations' );
	if ( ! form ) {
		return;
	}

	function nextIndex( container ) {
		var next = parseInt( container.getAttribute( 'data-next' ), 10 ) || 0;
		container.setAttribute( 'data-next', String( next + 1 ) );
		return next;
	}

	function append( container, template, placeholder ) {
		var index = nextIndex( container );
		var html = template.innerHTML.split( placeholder ).join( String( index ) );
		var wrapper = document.createElement( 'div' );
		wrapper.innerHTML = html.trim();
		var node = wrapper.firstElementChild;
		if ( node ) {
			container.appendChild( node );
			var first = node.querySelector( 'select, input[type="text"]' );
			if ( first ) {
				first.focus();
			}
		}
	}

	function updateWarnings( rule ) {
		var consent = rule.querySelector( '.cfbe-int-consent' );
		var implied = rule.querySelector( '.cfbe-int-implied' );
		var impliedWarning = rule.querySelector( '.cfbe-int-warning--implied' );
		var missingWarning = rule.querySelector( '.cfbe-int-warning--missing' );
		if ( ! consent || ! implied ) {
			return;
		}
		if ( impliedWarning ) {
			impliedWarning.hidden = ! implied.checked;
		}
		if ( missingWarning ) {
			missingWarning.hidden = implied.checked || '' !== consent.value.trim();
		}
	}

	form.addEventListener( 'click', function ( event ) {
		var button = event.target.closest( 'button' );
		if ( ! button ) {
			return;
		}
		var section, rule, row, template;

		if ( button.classList.contains( 'cfbe-int-add-rule' ) ) {
			section = button.closest( '.cfbe-int' );
			template = section && section.querySelector( '.cfbe-int-rule-template' );
			if ( template ) {
				append( section.querySelector( '.cfbe-int__rules' ), template, '__RULE__' );
			}
		} else if ( button.classList.contains( 'cfbe-int-remove-rule' ) ) {
			rule = button.closest( '.cfbe-int-rule' );
			if ( rule ) {
				rule.remove();
			}
		} else if ( button.classList.contains( 'cfbe-int-add-map' ) ) {
			rule = button.closest( 'td' );
			template = rule && rule.querySelector( '.cfbe-int-map-template' );
			if ( template ) {
				append( rule.querySelector( '.cfbe-int-maps' ), template, '__MAP__' );
			}
		} else if ( button.classList.contains( 'cfbe-int-remove-map' ) ) {
			row = button.closest( '.cfbe-int-map' );
			if ( row ) {
				row.remove();
			}
		}
	} );

	function onFieldChange( event ) {
		var target = event.target;
		if ( target.classList.contains( 'cfbe-int-consent' ) || target.classList.contains( 'cfbe-int-implied' ) ) {
			var rule = target.closest( '.cfbe-int-rule' );
			if ( rule ) {
				updateWarnings( rule );
			}
		}
	}

	form.addEventListener( 'input', onFieldChange );
	form.addEventListener( 'change', onFieldChange );
}() );
