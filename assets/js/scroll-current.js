/**
 * Tab rows that scroll sideways ([data-bwc-scroll-current]): they start
 * scrolled so the current tab is in view, instead of hiding it past the edge.
 * Their bar is hidden (scroll.css), so the edge with more tabs past it fades
 * out ("is-more-start", "is-more-end"), and a mouse wheel scrolls the row
 * sideways until it reaches its end, then the page as usual.
 */
bwc.behaviour( '[data-bwc-scroll-current]', ( row ) => {
	const overflows = () => row.scrollWidth > row.clientWidth + 1;

	const current = row.querySelector( '[aria-current]' );
	if ( current && overflows() ) {
		const rowBox = row.getBoundingClientRect();
		const box = current.getBoundingClientRect();

		if ( box.left < rowBox.left || box.right > rowBox.right ) {
			row.scrollLeft += box.left - rowBox.left - ( rowBox.width - box.width ) / 2;
		}
	}

	// Right to left, scrollLeft runs from 0 down to minus the overflow.
	const edges = () => {
		const max = row.scrollWidth - row.clientWidth;
		const done = Math.abs( row.scrollLeft );
		row.classList.toggle( 'is-more-start', max > 1 && done > 1 );
		row.classList.toggle( 'is-more-end', max > 1 && done < max - 1 );
	};

	row.addEventListener( 'scroll', edges, { passive: true } );
	if ( 'ResizeObserver' in window ) {
		new window.ResizeObserver( edges ).observe( row );
	}
	edges();

	row.addEventListener( 'wheel', ( event ) => {
		if ( ! overflows() || event.ctrlKey || Math.abs( event.deltaX ) >= Math.abs( event.deltaY ) ) {
			return;
		}
		const before = row.scrollLeft;
		row.scrollLeft += ( 'rtl' === window.getComputedStyle( row ).direction ? -1 : 1 ) * event.deltaY;
		if ( row.scrollLeft !== before ) {
			event.preventDefault();
		}
	}, { passive: false } );
} );
