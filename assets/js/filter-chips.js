/**
 * Filter chips: [data-bwc-filters="{id}"] buttons show only the entries in
 * element {id} whose data-bwc-kind matches; "all" shows everything. Groups
 * marked data-bwc-filter-group hide when left empty and open when they have
 * matches; data-bwc-filter-empty shows when nothing matches. The chips are
 * printed hidden and shown here: without the script every entry shows.
 */
bwc.behaviour( '[data-bwc-filters]', ( chips ) => {
	const target = document.getElementById( chips.dataset.bwcFilters );
	if ( ! target ) {
		return;
	}

	const buttons = [ ...chips.querySelectorAll( '[data-bwc-filter]' ) ];

	const apply = ( value ) => {
		let shown = 0;

		target.querySelectorAll( '[data-bwc-kind]' ).forEach( ( item ) => {
			const match = 'all' === value || item.dataset.bwcKind === value;
			item.hidden = ! match;
			shown += match ? 1 : 0;
		} );

		target.querySelectorAll( '[data-bwc-filter-group]' ).forEach( ( group ) => {
			const hasMatch = null !== group.querySelector( '[data-bwc-kind]:not([hidden])' );
			group.hidden = ! hasMatch;
			if ( hasMatch && 'all' !== value && 'DETAILS' === group.tagName ) {
				group.open = true;
			}
		} );

		const empty = target.querySelector( '[data-bwc-filter-empty]' );
		if ( empty ) {
			empty.hidden = shown > 0;
		}

		buttons.forEach( ( button ) => {
			button.setAttribute( 'aria-pressed', button.dataset.bwcFilter === value ? 'true' : 'false' );
		} );

		return shown;
	};

	buttons.forEach( ( button ) => {
		button.addEventListener( 'click', () => {
			const shown = apply( button.dataset.bwcFilter );
			bwc.announce( chips, bwc.string( 'filter_shown' ).replace( '%d', String( shown ) ) );
		} );
	} );

	chips.hidden = false;

	const pressed = buttons.find( ( button ) => 'true' === button.getAttribute( 'aria-pressed' ) );
	if ( pressed && 'all' !== pressed.dataset.bwcFilter ) {
		apply( pressed.dataset.bwcFilter );
	}
} );
