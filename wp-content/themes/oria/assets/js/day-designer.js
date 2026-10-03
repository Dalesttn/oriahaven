/**
 * Oria Day Designer -- the widget's behaviour.
 *
 * Every number on screen comes from the server (GET /oria/v1/day-plan): the
 * browser never prices anything. The server returns the best plan and up to
 * five costed alternatives, so "Swap experience" is instant and always shows
 * a plan that met the same constraints. The latest request wins; an older
 * response arriving late is ignored.
 *
 * Saving is on this device only (localStorage), stores preferences and the
 * experience id -- never a location -- and re-checks the plan on reopening.
 */
( function () {
	'use strict';

	var dl = ( window.dataLayer = window.dataLayer || [] );
	function track( name, extra ) {
		var e = { event: name };
		for ( var k in extra || {} ) { e[ k ] = extra[ k ]; }
		dl.push( e );
	}
	function band( n ) {
		return n < 100 ? 'under-100' : n < 250 ? '100-249' : n < 500 ? '250-499' : '500-plus';
	}
	function esc( s ) {
		return String( s == null ? '' : s ).replace( /[&<>"']/g, function ( c ) {
			return { '&': '&amp;', '<': '&lt;', '>': '&gt;', '"': '&quot;', "'": '&#39;' }[ c ];
		} );
	}
	function safeUrl( u ) {
		return /^https?:\/\//i.test( String( u || '' ) ) ? esc( u ) : '';
	}
	var STORE = 'oria_day_designer_saved';
	function readSaved() {
		try { return JSON.parse( localStorage.getItem( STORE ) || '[]' ) || []; } catch ( e ) { return []; }
	}
	function writeSaved( list ) {
		try { localStorage.setItem( STORE, JSON.stringify( list.slice( 0, 6 ) ) ); return true; } catch ( e ) { return false; }
	}

	document.querySelectorAll( '[data-dd]' ).forEach( function ( root ) {
		var ctx = root.getAttribute( 'data-dd-ctx' );
		var endpoint = root.getAttribute( 'data-dd-endpoint' );
		var form = root.querySelector( '[data-dd-form]' );
		var out = root.querySelector( '[data-dd-result]' );
		var savedBox = root.querySelector( '[data-dd-saved]' );
		var live = root.querySelector( '[data-dd-live]' );
		var range = root.querySelector( '[data-dd-range]' );
		var budget = root.querySelector( '[data-dd-budget]' );
		var people = root.querySelector( '[data-dd-people]' );
		var go = root.querySelector( '[data-dd-go]' );
		var goLabel = root.querySelector( '[data-dd-golabel]' );
		var goText = goLabel ? goLabel.textContent : '';
		var moreLabel = root.querySelector( '[data-dd-morelabel]' );
		var moreText = moreLabel ? moreLabel.textContent : '';
		var seq = 0;
		var ctrl = null;
		var state = null; // { data, index, cafe, prefs }
		var viewed = false;

		// The widget is "seen" once it scrolls into view -- no request is made for that.
		if ( 'IntersectionObserver' in window ) {
			var io = new IntersectionObserver( function ( en ) {
				if ( en[ 0 ].isIntersecting && ! viewed ) { viewed = true; track( 'day_designer_view', { dd_context: ctx } ); io.disconnect(); }
			}, { threshold: 0.4 } );
			io.observe( root );
		}

		// Budget: slider and number box stay in step; typing a bigger number widens nothing silently.
		range.addEventListener( 'input', function () { budget.value = range.value; } );
		budget.addEventListener( 'input', function () {
			var v = parseInt( budget.value, 10 );
			if ( ! isNaN( v ) ) { range.value = Math.min( v, parseInt( range.max, 10 ) ); }
		} );

		// "Time for two" means two people; the radio group itself is the preset.
		root.querySelectorAll( '[data-dd-preset]' ).forEach( function ( r ) {
			r.addEventListener( 'change', function () {
				if ( r.checked && 'two' === r.value ) { people.value = '2'; }
			} );
		} );

		// "More preferences · 2 selected": counts only real changes from the defaults.
		function moreCount() {
			if ( ! moreLabel ) { return; }
			var n = 0;
			if ( '0' !== String( form.querySelector( '[name="km"]' ).value ) ) { n++; }
			if ( form.querySelector( '[name="indoor"]' ).checked ) { n++; }
			if ( form.querySelector( '[name="cafe"]' ).checked ) { n++; }
			moreLabel.textContent = n ? moreText + ' · ' + n + ' selected' : moreText;
		}
		form.addEventListener( 'change', moreCount );

		/* Category pages: sit inside the listing grid after its first full row, and go
		   back there after every re-render (app.js rewrites the grid on each filter
		   change). The same node moves, so what the visitor typed is kept. */
		var slot = root.parentElement && root.parentElement.matches( '[data-dd-slot]' ) ? root.parentElement : null;
		var grid = 'category' === root.getAttribute( 'data-dd-variant' ) ? document.getElementById( 'dirResults' ) : null;
		var mo = null;
		function place() {
			if ( ! slot || ! grid ) { return; }
			var cols = getComputedStyle( grid ).gridTemplateColumns.split( ' ' ).filter( Boolean ).length || 1;
			var cards = Array.prototype.filter.call( grid.children, function ( el ) { return el !== slot && el.matches( '.listing, article' ); } );
			var after = cards.length >= cols ? cards[ cols - 1 ] : cards[ cards.length - 1 ];
			if ( mo ) { mo.disconnect(); }
			if ( after ) {
				if ( after.nextElementSibling !== slot ) { after.insertAdjacentElement( 'afterend', slot ); }
			} else if ( slot.parentElement !== grid ) {
				grid.appendChild( slot );
			}
			if ( mo ) { mo.observe( grid, { childList: true } ); }
		}
		if ( slot && grid ) {
			mo = new MutationObserver( function () { place(); } );
			place();
			mo.observe( grid, { childList: true } );
			var rt = null;
			window.addEventListener( 'resize', function () { clearTimeout( rt ); rt = setTimeout( place, 200 ); } );
		}

		function prefs() {
			var fd = new FormData( form );
			return {
				ctx: ctx,
				preset: fd.get( 'preset' ) || '',
				budget: parseInt( fd.get( 'budget' ), 10 ) || 0,
				people: parseInt( fd.get( 'people' ), 10 ) || 1,
				near: fd.get( 'near' ) || '',
				minutes: parseInt( fd.get( 'minutes' ), 10 ) || 120,
				km: parseInt( fd.get( 'km' ), 10 ) || 0,
				indoor: fd.get( 'indoor' ) ? 1 : 0,
				cafe: fd.get( 'cafe' ) ? 1 : 0,
				cafe_each: parseInt( fd.get( 'cafe_each' ), 10 ) || 0
			};
		}
		function setForm( p ) {
			budget.value = p.budget; range.value = Math.min( p.budget, parseInt( range.max, 10 ) );
			people.value = String( p.people );
			var near = form.querySelector( '[name="near"]' ); if ( near && p.near ) { near.value = p.near; }
			var m = form.querySelector( '[name="minutes"][value="' + p.minutes + '"]' ); if ( m ) { m.checked = true; }
			form.querySelector( '[name="km"]' ).value = String( p.km || 0 );
			form.querySelector( '[name="indoor"]' ).checked = !! p.indoor;
			form.querySelector( '[name="cafe"]' ).checked = !! p.cafe;
			form.querySelector( '[name="cafe_each"]' ).value = p.cafe_each;
			root.querySelectorAll( '[data-dd-preset]' ).forEach( function ( o ) { o.checked = o.value === p.preset; } );
			moreCount();
		}

		function run( after ) {
			var p = prefs();
			var mine = ++seq;
			if ( ctrl ) { ctrl.abort(); }
			ctrl = window.AbortController ? new AbortController() : null;
			out.hidden = false;
			out.setAttribute( 'aria-busy', 'true' );
			go.disabled = true;
			if ( goLabel ) { goLabel.textContent = 'Designing…'; }
			var qs = Object.keys( p ).map( function ( k ) { return encodeURIComponent( k ) + '=' + encodeURIComponent( p[ k ] ); } ).join( '&' );
			fetch( endpoint + ( endpoint.indexOf( '?' ) < 0 ? '?' : '&' ) + qs, { credentials: 'omit', signal: ctrl ? ctrl.signal : undefined } )
				.then( function ( r ) { return r.ok ? r.json() : Promise.reject( r.status ); } )
				.then( function ( data ) {
					if ( mine !== seq ) { return; } // A newer request has been made.
					state = { data: data, index: 0, cafe: !! p.cafe, prefs: p };
					draw();
					track( data.ok ? 'day_designer_plan' : 'day_designer_empty', { dd_context: ctx, dd_people: p.people, dd_budget_band: band( p.budget ), dd_results: ( data.plans || [] ).length } );
					if ( after ) { after( data ); }
				} )
				.catch( function ( err ) {
					if ( mine !== seq || ( err && 'AbortError' === err.name ) ) { return; }
					out.innerHTML = '<p class="dd__empty">' + esc( 429 === err ? 'Too many plans in a short time. Try again in a few minutes.' : 'The planner could not be reached just now. Every venue is listed below.' ) + '</p>';
				} )
				.finally( function () {
					if ( mine === seq ) {
						out.removeAttribute( 'aria-busy' );
						go.disabled = false;
						if ( goLabel ) { goLabel.textContent = goText; }
					}
				} );
		}

		function draw() {
			var d = state.data;
			if ( ! d.ok || ! d.plans || ! d.plans.length ) {
				out.innerHTML = '<p class="dd__empty">' + esc( d.empty || 'Nothing fits those choices.' ) + '</p>';
				live.textContent = d.empty || '';
				return;
			}
			var pl = d.plans[ state.index ];
			var cafe = state.cafe && pl.cafe;
			var total = cafe ? pl.money.total : pl.money.cost;
			var stops = cafe ? 2 : 1;
			var remaining = d.budget && ( cafe ? pl.remaining : pl.remaining + ( pl.cafe ? pl.cafe.cost : 0 ) );
			var who = 2 === d.people ? 'Two people' : 'One person';
			var mins = pl.session + 15 + ( cafe ? 30 : 0 );
			var label = cafe ? 'Estimated activities & extras' : 'Published activity cost';
			var html = '';
			html += '<div class="dd__summary"><p class="dd__sumline"><strong>' + esc( who ) + '</strong> · ' + stops + ( 1 === stops ? ' stop' : ' stops' ) + ' · about ' + esc( dur( mins ) ) + ' plus travel</p>';
			html += '<p class="dd__total"><span>' + esc( label ) + '</span> <strong>' + esc( total ) + '</strong>' + ( remaining > 0 ? ' <em>' + esc( money( remaining ) ) + ' left of ' + esc( d.budget ) + '</em>' : '' ) + '</p>';
			html += '<p class="dd__help">' + ( d.from ? 'Starting near ' + esc( d.from ) + '. ' : '' ) + 'Travel time is not calculated; distances are straight-line. Prices as published, not a booking.</p></div>';
			html += '<ol class="dd__stops">';
			html += '<li class="dd__stop dd__stop--main"><span class="dd__num" aria-hidden="true">1</span><div class="dd__stopbody">';
			if ( pl.listing.image ) { html += '<img class="dd__img" src="' + esc( pl.listing.image ) + '" alt="" loading="lazy" width="96" height="96">'; }
			html += '<div><p class="dd__service">' + esc( pl.service ) + '</p>';
			html += '<p class="dd__venue"><a href="' + esc( pl.listing.url ) + '" data-dd-out="venue">' + esc( pl.listing.name ) + '</a>' + ( pl.listing.suburb ? ' · ' + esc( pl.listing.suburb ) : '' ) + ( pl.km_label ? ' · ' + esc( pl.km_label ) : '' ) + '</p>';
			// The venue's own price basis, only when it says more than the total does.
			var forWho = 2 === d.people ? 'two' : 'one';
			var basis = String( pl.basis || '' );
			var plain = basis === pl.money.cost || basis === pl.money.cost + ' for two';
			html += '<p class="dd__meta"><strong>' + esc( pl.money.cost ) + '</strong> for ' + forWho + ( basis && ! plain ? ' <span class="dd__basis">(' + esc( basis ) + ')</span>' : '' ) + ' · ' + esc( pl.session ) + ' min</p>';
			html += '<p class="dd__why">' + esc( pl.why ) + '</p>';
			if ( pl.note ) { html += '<p class="dd__note">' + esc( pl.note ) + '</p>'; }
			html += '<details class="dd__src"><summary>Where this price is from</summary><p>Checked on ' + esc( pl.checked ) + ( safeUrl( pl.source ) ? ' at <a href="' + safeUrl( pl.source ) + '" rel="nofollow noopener" target="_blank" data-dd-out="source">the venue\'s own page<span class="dd-vh"> (opens in a new tab)</span></a>' : ' from the venue' ) + '. Prices change; confirm when you book.</p></details>';
			html += '<p class="dd__links"><a class="btn btn--ghost btn--sm" href="' + esc( pl.listing.url ) + '" data-dd-out="venue">View venue</a>';
			if ( safeUrl( pl.source ) ) { html += ' <a class="btn btn--ghost btn--sm" href="' + safeUrl( pl.source ) + '" rel="nofollow noopener" target="_blank" data-dd-out="book">Prices &amp; booking<span class="dd-vh"> (opens the venue\'s site in a new tab)</span></a>'; }
			if ( safeUrl( pl.directions ) ) { html += ' <a class="dd__dir" href="' + safeUrl( pl.directions ) + '" rel="nofollow noopener" target="_blank" data-dd-out="directions">Directions<span class="dd-vh"> (opens Google Maps in a new tab)</span></a>'; }
			html += '</p></div></div></li>';
			if ( cafe ) {
				html += '<li class="dd__stop"><span class="dd__num" aria-hidden="true">2</span><div class="dd__stopbody"><div><p class="dd__service">Café break</p>';
				html += '<p class="dd__meta">Allowance ' + esc( pl.money.cafe ) + ' (' + esc( money( pl.cafe.each ) ) + ' a person) · 30 min</p>';
				html += '<p class="dd__note">Not a booked or mapped place — an allowance, so the total stays honest. Pick somewhere near the venue on the day.</p>';
				html += '<p class="dd__links"><button type="button" class="dd__link" data-dd-act="nocafe">Remove this stop</button></p></div></div></li>';
			}
			html += '</ol>';
			if ( pl.cafe_note && state.cafe ) { html += '<p class="dd__help">' + esc( pl.cafe_note ) + '</p>'; }
			html += '<div class="dd__actions">';
			html += d.plans.length > 1 ? '<button type="button" class="btn btn--ghost btn--sm" data-dd-act="swap">Swap experience <span class="dd__count">(' + ( state.index + 1 ) + ' of ' + d.plans.length + ')</span></button>' : '<span class="dd__help">No other option meets these choices.</span>';
			html += ' <button type="button" class="btn btn--ghost btn--sm" data-dd-act="save">Save on this device</button>';
			html += ' <button type="button" class="dd__link" data-dd-act="collapse" aria-expanded="true" aria-controls="' + esc( out.id ) + '">Collapse plan</button>';
			html += '</div>';
			out.innerHTML = html;
			out.classList.remove( 'is-collapsed' );
			live.textContent = 'Plan updated: ' + pl.service + ' at ' + pl.listing.name + ', ' + total + '.';
		}

		function money( c ) { return '$' + ( c % 100 ? ( c / 100 ).toFixed( 2 ) : String( Math.round( c / 100 ) ) ); }
		function dur( m ) { var h = Math.floor( m / 60 ), r = m % 60; return h ? h + ' hr' + ( r ? ' ' + r + ' min' : '' ) : m + ' min'; }

		out.addEventListener( 'click', function ( ev ) {
			var a = ev.target.closest( '[data-dd-act]' );
			var o = ev.target.closest( '[data-dd-out]' );
			if ( o ) { track( 'day_designer_outbound', { dd_context: ctx, dd_target: o.getAttribute( 'data-dd-out' ) } ); }
			if ( ! a || ! state ) { return; }
			var act = a.getAttribute( 'data-dd-act' );
			if ( 'swap' === act ) {
				state.index = ( state.index + 1 ) % state.data.plans.length;
				draw();
				track( 'day_designer_swap', { dd_context: ctx } );
			} else if ( 'nocafe' === act ) {
				state.cafe = false;
				form.querySelector( '[name="cafe"]' ).checked = false;
				draw();
			} else if ( 'save' === act ) {
				var pl = state.data.plans[ state.index ];
				var list = readSaved().filter( function ( s ) { return ! ( s.id === pl.id && s.ctx === ctx ); } );
				var p = state.prefs; p.cafe = state.cafe ? 1 : 0;
				list.unshift( { ctx: ctx, id: pl.id, title: pl.service + ' · ' + pl.listing.name, total: state.cafe && pl.cafe ? pl.money.total : pl.money.cost, prefs: p, at: Date.now() } );
				var ok = writeSaved( list );
				a.textContent = ok ? 'Saved on this device' : 'Could not save on this device';
				a.disabled = ok;
				live.textContent = ok ? 'Saved on this device. Prices are rechecked when you reopen it.' : 'Saving is blocked in this browser.';
				track( 'day_designer_save', { dd_context: ctx } );
				drawSaved();
			} else if ( 'collapse' === act ) {
				var col = out.classList.toggle( 'is-collapsed' );
				a.setAttribute( 'aria-expanded', col ? 'false' : 'true' );
				a.textContent = col ? 'Show plan' : 'Collapse plan';
			}
		} );

		form.addEventListener( 'submit', function ( ev ) { ev.preventDefault(); run( function () { out.focus( { preventScroll: true } ); } ); } );

		// Changes after a plan exists rebuild it -- debounced, latest request wins.
		var timer = null;
		form.addEventListener( 'change', function () {
			if ( ! state ) { return; }
			clearTimeout( timer );
			timer = setTimeout( run, 350 );
		} );

		// Saved days: on this device, rechecked on reopen.
		function drawSaved() {
			var mine = readSaved().filter( function ( s ) { return s.ctx === ctx; } );
			if ( ! mine.length ) { savedBox.hidden = true; return; }
			savedBox.hidden = false;
			savedBox.innerHTML = '<p class="micro">Saved on this device</p><ul>' + mine.map( function ( s, i ) {
				return '<li><button type="button" class="dd__link" data-dd-open="' + i + '">' + esc( s.title ) + '</button> <span class="dd__help">' + esc( s.total ) + ' when saved</span></li>';
			} ).join( '' ) + '</ul>';
		}
		savedBox.addEventListener( 'click', function ( ev ) {
			var b = ev.target.closest( '[data-dd-open]' );
			if ( ! b ) { return; }
			var s = readSaved().filter( function ( x ) { return x.ctx === ctx; } )[ parseInt( b.getAttribute( 'data-dd-open' ), 10 ) ];
			if ( ! s ) { return; }
			setForm( s.prefs );
			run( function ( data ) {
				var i = ( data.plans || [] ).findIndex( function ( p ) { return p.id === s.id; } );
				if ( i >= 0 ) {
					state.index = i; draw();
					var now = state.cafe && data.plans[ i ].cafe ? data.plans[ i ].money.total : data.plans[ i ].money.cost;
					live.textContent = now === s.total ? 'Reopened. The price is unchanged.' : 'Reopened. The price has changed from ' + s.total + ' to ' + now + '.';
					if ( now !== s.total ) { out.insertAdjacentHTML( 'afterbegin', '<p class="dd__changed">The price has changed since you saved this: ' + esc( s.total ) + ' then, ' + esc( now ) + ' now.</p>' ); }
				} else {
					out.insertAdjacentHTML( 'afterbegin', '<p class="dd__changed">The experience you saved no longer fits these choices, or its price needs rechecking. Here is the closest current plan.</p>' );
				}
			} );
		} );
		drawSaved();
	} );
} )();
