/**
 * Confirm: [data-bwc-confirm] buttons and links ask before going ahead, and
 * do nothing if the answer is no. A yes marks the button's form as confirmed
 * (its [data-bwc-confirmed] input), so the Actions service runs it straight
 * away. Without the script, the Actions service asks on a page of its own.
 */
bwc.behaviour( '[data-bwc-confirm]', ( control ) => {
	control.addEventListener( 'click', ( event ) => {
		if ( ! window.confirm( control.dataset.bwcConfirm ) ) {
			event.preventDefault();
			event.stopImmediatePropagation();
			return;
		}

		const confirmed = control.form && control.form.querySelector( '[data-bwc-confirmed]' );
		if ( confirmed ) {
			confirmed.value = '1';
		}
	} );
} );
