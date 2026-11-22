/**
 * Tab rows that scroll sideways ([data-bwc-scroll-current]) start scrolled so
 * the current tab is in view, instead of hiding it past the edge.
 */
bwc.behaviour( '[data-bwc-scroll-current]', ( row ) => {
	const current = row.querySelector( '[aria-current]' );
	if ( ! current || row.scrollWidth <= row.clientWidth ) {
		return;
	}

	const rowBox = row.getBoundingClientRect();
	const box = current.getBoundingClientRect();

	if ( box.left < rowBox.left || box.right > rowBox.right ) {
		row.scrollLeft += box.left - rowBox.left - ( rowBox.width - box.width ) / 2;
	}
} );
