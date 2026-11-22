/**
 * Header bar: on wide screens it stays under the admin bar (screen.css).
 * Its height goes into --bwc-screen-bar-height on the screen, so the sticky
 * parts below it (the settings menu, the log's runs) and jumps to a field
 * stop under it, even when its tabs wrap onto a second row. While it's stuck
 * it gets "is-stuck", for a soft shadow under it.
 */
bwc.behaviour( '[data-bwc-screen-bar]', ( bar ) => {
	const screen = bar.parentElement;
	if ( ! screen ) {
		return;
	}

	const height = () => screen.style.setProperty( '--bwc-screen-bar-height', bar.offsetHeight + 'px' );
	if ( 'ResizeObserver' in window ) {
		new window.ResizeObserver( height ).observe( bar );
	}
	height();

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
