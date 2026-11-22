/**
 * Select all: the [data-bwc-select-all] box in a table's header checks or
 * clears every row box, and shows "some" (indeterminate) when only some are
 * checked. It's printed hidden and shown here; bulk actions work without it.
 */
bwc.behaviour( '[data-bwc-select-all]', ( all ) => {
	const table = all.closest( 'table' );
	if ( ! table ) {
		return;
	}

	const boxes = () => [ ...table.querySelectorAll( 'tbody input[type="checkbox"]' ) ].filter( ( box ) => ! box.disabled );

	const sync = () => {
		const list = boxes();
		const checked = list.filter( ( box ) => box.checked ).length;
		all.checked = list.length > 0 && checked === list.length;
		all.indeterminate = checked > 0 && checked < list.length;
	};

	all.hidden = false;

	all.addEventListener( 'change', () => {
		boxes().forEach( ( box ) => {
			box.checked = all.checked;
		} );
		all.indeterminate = false;
	} );

	table.addEventListener( 'change', ( event ) => {
		if ( event.target !== all ) {
			sync();
		}
	} );

	sync();
} );
