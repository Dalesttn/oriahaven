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
			var n = row.querySelector( '.myrow__n' );
			if ( n ) { n.textContent = String( i + 1 ); }
			var up = row.querySelector( '[data-myrep-up]' );
			var dn = row.querySelector( '[data-myrep-down]' );
			if ( up ) { up.disabled = 0 === i; }
			if ( dn ) { dn.disabled = i === rows.length - 1; }
		} );
	}

	/**
	 * Hide the Add button once the row cap is reached.
	 *
	 * The cap is enforced on the server too -- ACF's own max only stops the
	 * browser, and update_field() never fires the save hook that would trim
	 * an over-long list. This just stops somebody filling in a fifth
	 * practitioner before being told it will not be kept.
	 */
	function capCheck( rep ) {
		var max = parseInt( rep.getAttribute( 'data-max' ) || '0', 10 );
		var add = rep.querySelector( '[data-myrep-add]' );
		var note = rep.querySelector( '[data-myrep-cap]' );
		if ( ! max || ! add ) { return; }
		var full = rep.querySelectorAll( '[data-myrep-row]' ).length >= max;
		add.hidden = full;
		if ( note ) { note.hidden = ! full; }
	}

	function setupRepeater( rep ) {
		var box = rep.querySelector( '[data-myrep-rows]' );
		var tpl = rep.querySelector( '[data-myrep-tpl]' );
		var add = rep.querySelector( '[data-myrep-add]' );
		if ( ! box ) { return; }

		reindex( rep );
		capCheck( rep );

		if ( add && tpl ) {
			add.addEventListener( 'click', function () {
				var row = tpl.content.firstElementChild.cloneNode( true );
				box.appendChild( row );
				reindex( rep );
				capCheck( rep );
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
				capCheck( rep );
				dirty();
				return;
			}
			if ( e.target.closest( '[data-myrep-up]' ) && row.previousElementSibling ) {
				box.insertBefore( row, row.previousElementSibling );
				reindex( rep );
				capCheck( rep );
				dirty();
				focusSame( row, e.target );
				return;
			}
			if ( e.target.closest( '[data-myrep-down]' ) && row.nextElementSibling ) {
				box.insertBefore( row.nextElementSibling, row );
				reindex( rep );
				capCheck( rep );
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

	/* --------------------------------------------------------- photo picker */

	/*
	 * WordPress's own media modal, scoped by the server to this person's
	 * uploads. The posted value is only ever an attachment id, and the id
	 * is checked again on save -- it must be an image, and one they
	 * uploaded or one already attached to their listing -- so a hand-edited
	 * number cannot pull somebody else's file onto a profile.
	 */
	var frame = null;
	var target = null;

	function openPicker( pick ) {
		if ( ! window.wp || ! window.wp.media ) { return; }
		target = pick;

		if ( ! frame ) {
			frame = window.wp.media( {
				title: 'Choose a photo',
				library: { type: 'image' },
				button: { text: 'Use this photo' },
				multiple: false
			} );

			frame.on( 'select', function () {
				var att = frame.state().get( 'selection' ).first().toJSON();
				if ( ! target ) { return; }
				var val = target.querySelector( '[data-mypick-val]' );
				var box = target.querySelector( '[data-mypick-frame]' );
				var clear = target.querySelector( '[data-mypick-clear]' );
				var choose = target.querySelector( '[data-mypick-choose]' );
				var src = ( att.sizes && att.sizes.thumbnail ) ? att.sizes.thumbnail.url : att.url;

				val.value = att.id;
				box.innerHTML = '';
				var img = document.createElement( 'img' );
				img.src = src;
				img.alt = '';
				img.width = 64;
				img.height = 64;
				box.appendChild( img );
				if ( clear ) { clear.hidden = false; }
				if ( choose ) { choose.textContent = 'Change'; }
				dirty();
			} );
		}

		frame.open();
	}

	document.addEventListener( 'click', function ( e ) {
		if ( ! e.target.closest ) { return; }

		var choose = e.target.closest( '[data-mypick-choose]' );
		if ( choose ) {
			e.preventDefault();
			openPicker( choose.closest( '[data-mypick]' ) );
			return;
		}

		var clear = e.target.closest( '[data-mypick-clear]' );
		if ( clear ) {
			e.preventDefault();
			var pick = clear.closest( '[data-mypick]' );
			pick.querySelector( '[data-mypick-val]' ).value = '';
			pick.querySelector( '[data-mypick-frame]' ).innerHTML = '';
			clear.hidden = true;
			var btn = pick.querySelector( '[data-mypick-choose]' );
			if ( btn ) { btn.textContent = 'Choose a photo'; btn.focus(); }
			dirty();
		}
	} );

	/* ------------------------------------------------------------- gallery */

	/*
	 * The photo grid. Order is the whole point of it -- the first photo is
	 * the one that leads the profile and every card the listing appears in
	 * -- so the tiles move with arrows rather than only by dragging, and
	 * the first one is labelled Cover so nobody has to infer it.
	 */
	var galFrame = null;
	var galTarget = null;

	function galSync( gal ) {
		var grid = gal.querySelector( '[data-mygal-grid]' );
		var items = grid.querySelectorAll( '[data-mygal-item]' );
		var max = parseInt( gal.getAttribute( 'data-max' ) || '0', 10 );
		var add = gal.querySelector( '[data-mygal-add]' );
		var none = gal.querySelector( '[data-mygal-none]' );
		var count = gal.querySelector( '[data-mygal-count]' );

		Array.prototype.forEach.call( items, function ( item, i ) {
			var lead = item.querySelector( '[data-mygal-lead]' );
			if ( lead ) { lead.hidden = 0 !== i; }
			var up = item.querySelector( '[data-mygal-up]' );
			var dn = item.querySelector( '[data-mygal-down]' );
			if ( up ) { up.disabled = 0 === i; }
			if ( dn ) { dn.disabled = i === items.length - 1; }
		} );

		if ( none ) { none.hidden = items.length > 0; }
		if ( add && max > 0 ) { add.hidden = items.length >= max; }

		if ( count ) {
			if ( ! items.length ) {
				count.textContent = '';
			} else if ( max > 0 ) {
				count.textContent = items.length + ' of ' + max + ' used';
			} else {
				count.textContent = items.length + ( 1 === items.length ? ' photo' : ' photos' );
			}
		}
	}

	function galAdd( gal, atts ) {
		var grid = gal.querySelector( '[data-mygal-grid]' );
		var name = gal.getAttribute( 'data-name' );
		var max = parseInt( gal.getAttribute( 'data-max' ) || '0', 10 );
		var have = [];
		Array.prototype.forEach.call( grid.querySelectorAll( 'input[type=hidden]' ), function ( i ) {
			have.push( i.value );
		} );

		atts.forEach( function ( att ) {
			if ( max > 0 && grid.querySelectorAll( '[data-mygal-item]' ).length >= max ) { return; }
			if ( have.indexOf( String( att.id ) ) !== -1 ) { return; }
			have.push( String( att.id ) );

			var src = ( att.sizes && att.sizes.medium ) ? att.sizes.medium.url : att.url;
			var li = document.createElement( 'li' );
			li.className = 'mygal__item';
			li.setAttribute( 'data-mygal-item', '' );
			li.innerHTML =
				'<input type="hidden" name="' + name + '[]" value="' + att.id + '">' +
				'<span class="mygal__shot"><img src="" alt="" loading="lazy" decoding="async"></span>' +
				'<span class="mygal__lead" data-mygal-lead hidden>Cover</span>' +
				'<span class="mygal__acts">' +
					'<button class="mygal__btn" type="button" data-mygal-up><span aria-hidden="true">←</span><span class="sr-only">Move earlier</span></button>' +
					'<button class="mygal__btn" type="button" data-mygal-down><span aria-hidden="true">→</span><span class="sr-only">Move later</span></button>' +
					'<button class="mygal__btn mygal__btn--x" type="button" data-mygal-del><span aria-hidden="true">×</span><span class="sr-only">Remove this photo</span></button>' +
				'</span>';
			// src set as a property, never interpolated into the markup above.
			li.querySelector( 'img' ).src = src;
			grid.appendChild( li );
		} );

		galSync( gal );
		dirty();
	}

	function openGallery( gal ) {
		if ( ! window.wp || ! window.wp.media ) { return; }
		galTarget = gal;

		if ( ! galFrame ) {
			galFrame = window.wp.media( {
				title: 'Add photos',
				library: { type: 'image' },
				button: { text: 'Add to my listing' },
				multiple: 'add'
			} );

			galFrame.on( 'select', function () {
				if ( ! galTarget ) { return; }
				galAdd( galTarget, galFrame.state().get( 'selection' ).toJSON() );
			} );
		}

		galFrame.open();
	}

	document.addEventListener( 'click', function ( e ) {
		if ( ! e.target.closest ) { return; }
		var gal = e.target.closest( '[data-mygal]' );
		if ( ! gal ) { return; }

		if ( e.target.closest( '[data-mygal-add]' ) ) {
			e.preventDefault();
			openGallery( gal );
			return;
		}

		var item = e.target.closest( '[data-mygal-item]' );
		if ( ! item ) { return; }
		var pressed = e.target.closest( 'button' );

		if ( e.target.closest( '[data-mygal-del]' ) ) {
			e.preventDefault();
			var next = item.nextElementSibling || item.previousElementSibling;
			item.remove();
			galSync( gal );
			dirty();
			var focusOn = next ? next.querySelector( 'button:not(:disabled)' ) : gal.querySelector( '[data-mygal-add]' );
			if ( focusOn ) { focusOn.focus(); }
			return;
		}
		if ( e.target.closest( '[data-mygal-up]' ) && item.previousElementSibling ) {
			e.preventDefault();
			item.parentNode.insertBefore( item, item.previousElementSibling );
			galSync( gal );
			dirty();
			if ( pressed && ! pressed.disabled ) { pressed.focus(); }
			return;
		}
		if ( e.target.closest( '[data-mygal-down]' ) && item.nextElementSibling ) {
			e.preventDefault();
			item.parentNode.insertBefore( item.nextElementSibling, item );
			galSync( gal );
			dirty();
			if ( pressed && ! pressed.disabled ) { pressed.focus(); }
		}
	} );

	Array.prototype.forEach.call( document.querySelectorAll( '[data-mygal]' ), galSync );

	Array.prototype.forEach.call( document.querySelectorAll( '[data-myrep]' ), setupRepeater );
}() );
