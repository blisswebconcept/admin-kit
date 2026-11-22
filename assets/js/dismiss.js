/**
 * Dismissible notices: [data-bwc-dismiss] on the close link hides its notice
 * at once and records the dismissal in the background. Without the script
 * the link loads the URL, which records it and comes back.
 */
bwc.behaviour( '[data-bwc-dismiss]', ( link ) => {
	link.addEventListener( 'click', ( event ) => {
		event.preventDefault();

		const notice = link.closest( '[data-bwc-notice]' );
		if ( notice ) {
			// Focus was on the close link: move it to the next thing to use, or the page title, before the notice goes.
			const all = [ ...document.querySelectorAll( 'a[href], button:not([disabled]), input:not([type="hidden"]):not([disabled]), select, textarea, [tabindex]:not([tabindex="-1"])' ) ];
			const next = all.find( ( one ) => ! notice.contains( one ) && ( notice.compareDocumentPosition( one ) & window.Node.DOCUMENT_POSITION_FOLLOWING ) && null !== one.offsetParent );
			notice.hidden = true;
			if ( next ) {
				next.focus();
			}
		}

		window.fetch( link.href, { credentials: 'same-origin' } ).catch( () => {} );
	} );
} );
