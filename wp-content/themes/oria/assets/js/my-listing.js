/**
 * The listing editor's two pieces of behaviour.
 *
 * Everything else on the page is a form that works without this file:
 * the sections are links, Save is a submit button, and every field an
 * owner has already filled in renders from the server. What JavaScript
 * adds is adding and ordering repeater rows without a round trip, and
 * telling somebody whether their last change is safe yet.
 *
 * Rows are renumbered after every change rather than given clever names,
 * because PHP reads services[0], services[1] in order: a gap left by a
 * deleted row would be read as a row.
 */
( function () {
	'use strict';

	/* ----------------------------------------------------------- repeaters */

	/** Rewrite every name="thing[N][col]" so N matches the row's position. */
	function reindex( rep ) {
		var rows = rep.querySelectorAll( '[data-myrep-row]' );
		Array.prototype.forEach.call( rows, function ( row, i ) {
			Array.prototype.forEach.call( row.querySelectorAll( '[name]' ), function ( el ) {
				el.name = el.name.replace( /\[(?:\d+|__i__)\]/, '[' + i + ']' );
			} );
		} );
		// The first row cannot move up and the last cannot move down; saying
		// so with disabled is clearer than a button that does nothing.
		Array.prototype.forEach.call( rows, function ( row, i ) {
			var up = row.querySelector( '[data-myrep-up]' );
			var dn = row.querySelector( '[data-myrep-down]' );
			if ( up ) { up.disabled = 0 === i; }
			if ( dn ) { dn.disabled = i === rows.length - 1; }
		} );
	}

	function setupRepeater( rep ) {
		var box = rep.querySelector( '[data-myrep-rows]' );
		var tpl = rep.querySelector( '[data-myrep-tpl]' );
		var add = rep.querySelector( '[data-myrep-add]' );
		if ( ! box ) { return; }

		reindex( rep );

		if ( add && tpl ) {
			add.addEventListener( 'click', function () {
				var row = tpl.content.firstElementChild.cloneNode( true );
				box.appendChild( row );
				reindex( rep );
				dirty();
				// Land the cursor in the row that was just asked for.
				var first = row.querySelector( 'input, textarea' );
				if ( first ) { first.focus(); }
			} );
		}

		rep.addEventListener( 'click', function ( e ) {
			var row = e.target.closest ? e.target.closest( '[data-myrep-row]' ) : null;
			if ( ! row || ! box.contains( row ) ) { return; }

			if ( e.target.closest( '[data-myrep-del]' ) ) {
				row.remove();
				reindex( rep );
				dirty();
				return;
			}
			if ( e.target.closest( '[data-myrep-up]' ) && row.previousElementSibling ) {
				box.insertBefore( row, row.previousElementSibling );
				reindex( rep );
				dirty();
				focusSame( row, e.target );
				return;
			}
			if ( e.target.closest( '[data-myrep-down]' ) && row.nextElementSibling ) {
				box.insertBefore( row.nextElementSibling, row );
				reindex( rep );
				dirty();
				focusSame( row, e.target );
			}
		} );
	}

	/**
	 * Keep focus on the button that was pressed after the row moves.
	 *
	 * Moving a node in the DOM drops focus to the body, which sends a
	 * keyboard user back to the top of the page every time they nudge a
	 * row -- so the same control is refocused where it now lives.
	 */
	function focusSame( row, pressed ) {
		var btn = pressed.closest( 'button' );
		if ( ! btn || btn.disabled ) {
			var fallback = row.querySelector( 'button:not(:disabled)' );
			if ( fallback ) { fallback.focus(); }
			return;
		}
		btn.focus();
	}

	/* ------------------------------------------------- unsaved-change state */

	var form = document.querySelector( '[data-myedit]' );
	var state = form ? form.querySelector( '[data-myedit-state]' ) : null;
	var isDirty = false;
	var saving = false;

	function dirty() {
		if ( isDirty || ! state ) { return; }
		isDirty = true;
		state.textContent = state.getAttribute( 'data-unsaved' ) || 'Unsaved changes';
	}

	if ( form ) {
		form.addEventListener( 'input', dirty );
		form.addEventListener( 'change', dirty );

		form.addEventListener( 'submit', function () {
			saving = true;
			if ( state ) {
				state.textContent = state.getAttribute( 'data-saving' ) || 'Saving…';
			}
		} );

		/*
		 * Warn only about work that is genuinely at risk: something was
		 * typed, and the form is not on its way to the server. A prompt
		 * that fires on the way out of a saved page trains people to
		 * dismiss it without reading.
		 */
		window.addEventListener( 'beforeunload', function ( e ) {
			if ( ! isDirty || saving ) { return; }
			e.preventDefault();
			e.returnValue = '';
		} );
	}

	Array.prototype.forEach.call( document.querySelectorAll( '[data-myrep]' ), setupRepeater );
}() );
