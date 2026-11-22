/**
 * Header bar: on wide screens it stays under the admin bar (screen.css).
 * Its height goes into --bwc-screen-bar-height on the screen, so the sticky
 * parts below it (the settings menu, the log's runs) and jumps to a field
 * stop under it, even when its tabs wrap onto a second row. While it's stuck
 * it gets "is-stuck", for a soft shadow under it.
 *
 * When the tabs don't fit beside the brand, the bar gets "is-tabs-below" and
 * they take a row of their own (screen.css), so they don't scroll on a
 * desktop screen just because the title and subtitle are long.
 */
bwc.behaviour( '[data-bwc-screen-bar]', ( bar ) => {
	const screen = bar.parentElement;
	if ( ! screen ) {
		return;
	}

	// Measured beside the brand each time: the class comes off, the browser
	// lays the row out, and it goes back on if the tabs still overflow there.
	const tabs = bar.querySelector( '.bwc-screen__nav [data-bwc-scroll-current]' );
	const fit = () => {
		if ( ! tabs ) {
			return;
		}
		bar.classList.remove( 'is-tabs-below' );
		bar.classList.toggle( 'is-tabs-below', tabs.scrollWidth > tabs.clientWidth + 1 );
	};

	const height = () => screen.style.setProperty( '--bwc-screen-bar-height', bar.offsetHeight + 'px' );
	const layout = () => {
		fit();
		height();
	};
	if ( 'ResizeObserver' in window ) {
		new window.ResizeObserver( layout ).observe( bar );
	}
	layout();
	// Web fonts can change the tabs' width after the first layout.
	if ( document.fonts && document.fonts.ready ) {
		document.fonts.ready.then( layout );
	}

	let queued = false;
	const stuck = () => {
		queued = false;
		const top = parseFloat( window.getComputedStyle( bar ).top );
		bar.classList.toggle( 'is-stuck', 'sticky' === window.getComputedStyle( bar ).position && window.scrollY > 0 && ! Number.isNaN( top ) && bar.getBoundingClientRect().top <= top + 1 );
	};
	const later = () => {
		if ( ! queued ) {
			queued = true;
			window.requestAnimationFrame( stuck );
		}
	};
	window.addEventListener( 'scroll', later, { passive: true } );
	window.addEventListener( 'resize', later );
	stuck();
} );
