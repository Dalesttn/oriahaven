/**
 * Work in Wellness — the small amount of behaviour the pages need.
 *
 * 1. The post-a-job wizard: turns the form's fieldsets into steps with
 *    Back / Next, a progress line and a preview. Without this file the form
 *    is one page and still submits.
 * 2. Analytics (brief section 105): pushes the work event vocabulary to
 *    dataLayer — job_view, shift_view, practitioner_view on load; clicks and
 *    submits from [data-wk-event] / [data-wk-submit]; job_search on a search.
 */
( function () {
	'use strict';

	var dl = ( window.dataLayer = window.dataLayer || [] );
	function push( name, extra ) {
		var e = { event: name };
		for ( var k in extra || {} ) { e[ k ] = extra[ k ]; }
		dl.push( e );
	}

	/* ---------------------------------------------------------- analytics */
	/**
	 * The employer/practitioner dashboards read the same per-post daily
	 * counters as listing analytics (POST /wp-json/oria/v1/track). Pages are
	 * cached, so the view is counted by this beacon, not by PHP.
	 */
	var page = document.querySelector( '[data-wk-view]' );
	var pageId = page ? parseInt( page.getAttribute( 'data-wk-id' ), 10 ) : 0;
	function beacon( type ) {
		if ( ! pageId || ! window.ORIA_TRACK || ! navigator.sendBeacon ) { return; }
		try {
			navigator.sendBeacon( window.ORIA_TRACK.url, new Blob( [ JSON.stringify( { id: pageId, type: type } ) ], { type: 'application/json' } ) );
		} catch ( e ) {}
	}
	if ( page ) {
		push( page.getAttribute( 'data-wk-view' ), { wk_id: pageId } );
		beacon( 'view' );
	}
	var applied = false;
	function applyCount( name ) {
		if ( ! applied && ( name === 'job_apply_click' || name === 'shift_available_click' ) ) {
			applied = true;
			beacon( 'apply' );
		}
	}
	document.addEventListener( 'click', function ( ev ) {
		var el = ev.target.closest && ev.target.closest( '[data-wk-event]' );
		if ( el && el.tagName !== 'BUTTON' ) {
			push( el.getAttribute( 'data-wk-event' ), { wk_href: el.getAttribute( 'href' ) || '' } );
			applyCount( el.getAttribute( 'data-wk-event' ) );
		}
	} );
	document.addEventListener( 'submit', function ( ev ) {
		var f = ev.target;
		if ( f.hasAttribute( 'data-wk-search' ) ) {
			push( 'job_search', { wk_list: f.getAttribute( 'data-wk-search' ) } );
		}
		var btn = ev.submitter;
		if ( btn && btn.hasAttribute( 'data-wk-event' ) ) {
			push( btn.getAttribute( 'data-wk-event' ) );
			applyCount( btn.getAttribute( 'data-wk-event' ) );
		}
		if ( f.hasAttribute( 'data-wk-submit' ) && ( ! btn || btn.value !== 'draft' ) ) {
			push( f.getAttribute( 'data-wk-submit' ) );
		}
	} );

	/* ------------------------------------------------------------- wizard */
	var form = document.querySelector( '[data-wk-wizard]' );
	if ( ! form ) {
		return;
	}
	var steps = Array.prototype.slice.call( form.querySelectorAll( '.wkstep' ) );
	var back = form.querySelector( '[data-wk-back]' );
	var next = form.querySelector( '[data-wk-next]' );
	var publish = form.querySelector( '[data-wk-publish]' );
	var bar = form.querySelector( '.wkwizard__progress' );
	var now = 0;
	if ( steps.length < 2 ) {
		return;
	}
	form.classList.add( 'is-stepped' );

	steps.forEach( function ( s ) {
		var li = document.createElement( 'li' );
		li.textContent = s.getAttribute( 'data-step' ) || '';
		bar.appendChild( li );
	} );
	// On a phone the step names don't fit: "Step 2 of 8 · Location" says it instead.
	var count = document.createElement( 'p' );
	count.className = 'wkwizard__count';
	count.setAttribute( 'aria-live', 'polite' );
	bar.parentNode.insertBefore( count, bar.nextSibling );

	function valid( step ) {
		var fields = step.querySelectorAll( 'input, select, textarea' );
		for ( var i = 0; i < fields.length; i++ ) {
			if ( ! fields[ i ].checkValidity() ) {
				fields[ i ].reportValidity();
				return false;
			}
		}
		return true;
	}

	function label( field ) {
		var l = field.id && form.querySelector( 'label[for="' + field.id + '"]' );
		return l ? l.textContent.trim() : field.name;
	}

	/** The preview: every filled field, in form order, as the employer typed it. */
	function preview() {
		var box = form.querySelector( '[data-wk-preview]' );
		if ( ! box ) {
			return;
		}
		var dlist = document.createElement( 'dl' );
		steps.slice( 0, -1 ).forEach( function ( s ) {
			s.querySelectorAll( 'input:not([type=hidden]), select, textarea' ).forEach( function ( f ) {
				var v = '';
				if ( f.type === 'radio' || f.type === 'checkbox' ) {
					if ( ! f.checked ) { return; }
					var wrap = f.closest( 'label' );
					v = wrap ? wrap.textContent.trim() : f.value;
					var legend = s.querySelector( 'legend' );
					var dt0 = document.createElement( 'dt' );
					dt0.textContent = f.type === 'radio' ? ( f.closest( 'fieldset' ).querySelector( '.wklabel' ) || legend ).textContent : legend.textContent;
					var dd0 = document.createElement( 'dd' );
					dd0.textContent = v;
					dlist.appendChild( dt0 );
					dlist.appendChild( dd0 );
					return;
				}
				if ( f.tagName === 'SELECT' ) {
					v = f.value ? f.options[ f.selectedIndex ].text : '';
				} else {
					v = f.value;
				}
				if ( ! v ) { return; }
				var dt = document.createElement( 'dt' );
				dt.textContent = label( f );
				var dd = document.createElement( 'dd' );
				dd.textContent = v.length > 600 ? v.slice( 0, 600 ) + '…' : v;
				dlist.appendChild( dt );
				dlist.appendChild( dd );
			} );
		} );
		box.innerHTML = '';
		box.appendChild( dlist );
	}

	function show( i, quiet ) {
		now = Math.max( 0, Math.min( steps.length - 1, i ) );
		count.textContent = 'Step ' + ( now + 1 ) + ' of ' + steps.length + ' · ' + ( steps[ now ].getAttribute( 'data-step' ) || '' );
		steps.forEach( function ( s, k ) { s.classList.toggle( 'is-now', k === now ); } );
		Array.prototype.forEach.call( bar.children, function ( li, k ) {
			li.classList.toggle( 'is-done', k < now );
			li.classList.toggle( 'is-now', k === now );
		} );
		var last = now === steps.length - 1;
		back.hidden = now === 0;
		next.hidden = last;
		publish.hidden = ! last;
		if ( last ) {
			preview();
		}
		if ( quiet ) {
			return; // First paint: leave focus and scroll where the visitor is.
		}
		var legend = steps[ now ].querySelector( 'legend' );
		if ( legend ) {
			legend.setAttribute( 'tabindex', '-1' );
			legend.focus( { preventScroll: true } );
		}
		steps[ now ].scrollIntoView( { block: 'start', behavior: window.matchMedia( '(prefers-reduced-motion: reduce)' ).matches ? 'auto' : 'smooth' } );
	}

	next.addEventListener( 'click', function () {
		if ( valid( steps[ now ] ) ) {
			show( now + 1 );
		}
	} );
	back.addEventListener( 'click', function () { show( now - 1 ); } );
	form.addEventListener( 'keydown', function ( ev ) {
		// Enter in a text field moves on rather than publishing early.
		if ( ev.key === 'Enter' && ev.target.tagName === 'INPUT' && now < steps.length - 1 ) {
			ev.preventDefault();
			next.click();
		}
	} );
	show( 0, true );
	push( 'job_post_started' );
} )();
