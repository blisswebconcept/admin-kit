/**
 * Expand all: a [data-bwc-toggle-details="{id}"] button opens every visible
 * <details> in element {id}, or closes them all when they're all open, and
 * says which it will do next. Printed hidden and shown here.
 */
bwc.behaviour( '[data-bwc-toggle-details]', ( button ) => {
	const container = document.getElementById( button.dataset.bwcToggleDetails );
	if ( ! container ) {
		return;
	}

	const label = button.querySelector( '[data-bwc-toggle-details-label]' ) || button;
	const visible = () => [ ...container.querySelectorAll( 'details' ) ].filter( ( details ) => null === details.closest( '[hidden]' ) );
	const allOpen = ( list ) => list.length > 0 && list.every( ( details ) => details.open );

	const update = () => {
		const open = allOpen( visible() );
		label.textContent = bwc.string( open ? 'collapse_all' : 'expand_all' );
		button.setAttribute( 'aria-expanded', open ? 'true' : 'false' );
	};

	button.hidden = false;

	button.addEventListener( 'click', () => {
		const list = visible();
		const open = ! allOpen( list );
		list.forEach( ( details ) => {
			details.open = open;
		} );
		update();
	} );

	// The toggle event doesn't bubble, so listen while it travels down.
	container.addEventListener( 'toggle', update, true );

	update();
} );
