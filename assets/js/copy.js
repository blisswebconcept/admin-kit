/**
 * Copy buttons: [data-bwc-copy] holds the value. The Clipboard API first,
 * then the select-and-copy fallback; "Copied" only after a real success.
 * The buttons are printed hidden and shown here, since they need the script.
 */

/**
 * Copies text, putting focus back where it was.
 *
 * @param {string}  value  Text to copy.
 * @param {Element} button Button to give focus back to.
 * @return {Promise<boolean>} Whether it was copied.
 */
const bwcCopyText = async ( value, button ) => {
	try {
		if ( window.navigator.clipboard && window.isSecureContext ) {
			await window.navigator.clipboard.writeText( value );
			return true;
		}
	} catch ( error ) {
		// Refused: try the fallback below.
	}

	const area = document.createElement( 'textarea' );
	area.value = value;
	area.setAttribute( 'readonly', '' );
	// Hidden with the kit's visually-hidden class, not an inline style; next to the button, so the scoped class applies.
	area.className = 'bwc-visually-hidden';
	button.after( area );
	area.select();

	let copied = false;
	try {
		copied = document.execCommand( 'copy' );
	} catch ( error ) {
		copied = false;
	}

	area.remove();
	button.focus();

	return copied;
};

bwc.behaviour( '[data-bwc-copy]', ( button ) => {
	const label = button.getAttribute( 'aria-label' );
	const text = button.querySelector( '[data-bwc-copy-text]' );
	const original = text ? text.textContent : '';
	let timer = 0;

	button.hidden = false;

	button.addEventListener( 'click', async () => {
		if ( ! ( await bwcCopyText( button.dataset.bwcCopy, button ) ) ) {
			bwc.announce( button, bwc.string( 'copy_failed' ) );
			return;
		}

		button.classList.add( 'is-copied' );
		if ( label ) {
			button.setAttribute( 'aria-label', bwc.string( 'copied' ) );
		}
		if ( text ) {
			text.textContent = bwc.string( 'copied' );
		}
		bwc.announce( button, bwc.string( 'copied' ) );

		window.clearTimeout( timer );
		timer = window.setTimeout( () => {
			button.classList.remove( 'is-copied' );
			if ( label ) {
				button.setAttribute( 'aria-label', label );
			}
			if ( text ) {
				text.textContent = original;
			}
		}, 1500 );
	} );
} );
