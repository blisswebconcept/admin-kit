/**
 * Drawer: the server prints the side-panel form as an open <dialog>; this
 * reopens it as a modal, so the page behind can't be reached and focus stays
 * inside. Focus goes to the first field with an error, or the first field.
 * Escape and the backdrop follow the close link, which keeps the list's view.
 * Without the script the form is a section after the list.
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

	const remember = () => {
		try {
			window.sessionStorage.setItem( BWC_DRAWER_RETURN, window.location.href );
		} catch ( error ) {
			// No storage: focus starts at the top, as without the script.
		}
	};
	dialog.querySelectorAll( '[data-bwc-drawer-close]' ).forEach( ( link ) => link.addEventListener( 'click', remember ) );
	dialog.querySelectorAll( 'form' ).forEach( ( form ) => form.addEventListener( 'submit', remember ) );

	dialog.addEventListener( 'cancel', ( event ) => {
		event.preventDefault();
		if ( close ) {
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
