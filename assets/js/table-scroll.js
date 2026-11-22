/**
 * Table boxes ([data-bwc-table-scroll]) are printed as a named, focusable
 * region so a table wider than the screen can be scrolled from the keyboard.
 * When the table fits, that extra tab stop and landmark only get in the way,
 * so they come off; they come back if the box gets narrow enough to scroll.
 */
bwc.behaviour( '[data-bwc-table-scroll]', ( box ) => {
	const label = box.getAttribute( 'aria-label' );
	const fit = () => {
		if ( box.scrollWidth > box.clientWidth + 1 ) {
			box.setAttribute( 'tabindex', '0' );
			box.setAttribute( 'role', 'region' );
			box.setAttribute( 'aria-label', label );
		} else {
			box.removeAttribute( 'tabindex' );
			box.removeAttribute( 'role' );
			box.removeAttribute( 'aria-label' );
		}
	};
	fit();
	if ( window.ResizeObserver ) {
		new window.ResizeObserver( fit ).observe( box );
	}
} );
