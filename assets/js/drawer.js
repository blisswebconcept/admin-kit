/**
 * Drawer: the server prints the side-panel form as an open <dialog>; this
 * reopens it as a modal, so the page behind can't be reached and focus stays
 * inside. Focus goes to the first field with an error, or the first field.
 * Escape and the backdrop follow the close link, which keeps the list's view;
 * when that would lose something typed (or what a failed save left), they
 * ask first. Without the script the form is a section after the list.
 *
 * Closing loads the list again, so focus would start at the top of the page.
 * The drawer's own address is remembered for that next page, which puts focus
 * back on the link that opened it (the row's Edit link, or Add).
 */
const BWC_DRAWER_RETURN = 'bwc-drawer-return';
bwc.behaviour( '[data-bwc-drawer]', ( dialog ) => {
	if ( typeof dialog.showModal !== 'function' ) {
		return;
	}

	const close = dialog.querySelector( '[data-bwc-drawer-close]' );

	dialog.close();
	dialog.showModal();

	const target = dialog.querySelector( '[aria-invalid="true"]' ) ||
		dialog.querySelector( 'input:not([type="hidden"]):not([disabled]), select:not([disabled]), textarea:not([disabled])' );
	if ( target ) {
		target.focus();
	}

	// What the fields held when the drawer opened, taken once every behaviour
	// has set its fields up (the same way as the settings save bar).
	const form = dialog.querySelector( 'form' );
	const snapshot = () => form ? [ ...new FormData( form ).entries() ]
		.filter( ( [ name ] ) => ! name.startsWith( '_' ) && 'action' !== name )
		.map( ( [ name, value ] ) => name + '=' + value )
		.join( '&' ) : '';
	let initial = null;
	window.setTimeout( () => {
		initial = snapshot();
	} );
	const losing = () => dialog.hasAttribute( 'data-bwc-drawer-unsaved' ) || ( null !== initial && snapshot() !== initial );
	const mayClose = () => ! losing() || window.confirm( bwc.string( 'drawer_discard' ) );

	const remember = () => {
		try {
			window.sessionStorage.setItem( BWC_DRAWER_RETURN, window.location.href );
		} catch ( error ) {
			// No storage: focus starts at the top, as without the script.
		}
	};
	dialog.querySelectorAll( '[data-bwc-drawer-close], .bwc-drawer__backdrop' ).forEach( ( link ) => link.addEventListener( 'click', ( event ) => {
		if ( ! mayClose() ) {
			event.preventDefault();
			return;
		}
		remember();
	} ) );
	if ( form ) {
		form.addEventListener( 'submit', remember );
	}

	dialog.addEventListener( 'cancel', ( event ) => {
		event.preventDefault();
		if ( close && mayClose() ) {
			remember();
			window.location.href = close.href;
		}
	} );
} );

document.addEventListener( 'DOMContentLoaded', () => {
	let back = null;
	try {
		back = window.sessionStorage.getItem( BWC_DRAWER_RETURN );
		window.sessionStorage.removeItem( BWC_DRAWER_RETURN );
	} catch ( error ) {
		return;
	}
	if ( ! back || document.querySelector( '[data-bwc-drawer]' ) ) {
		return;
	}
	const opener = bwc.find( 'a[href]' ).find( ( link ) => link.href === back );
	if ( opener ) {
		opener.focus();
	}
} );
