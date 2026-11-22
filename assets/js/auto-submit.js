/**
 * Auto-submit: a [data-bwc-auto-submit] control submits its form when its
 * value changes, and that form's [data-bwc-filter-submit] button is hidden.
 * Without the script the button stays and the form works as usual.
 */
bwc.behaviour( '[data-bwc-auto-submit]', ( control ) => {
	const form = control.form;
	if ( ! form ) {
		return;
	}

	form.querySelectorAll( '[data-bwc-filter-submit]' ).forEach( ( button ) => {
		button.hidden = true;
	} );

	control.addEventListener( 'change', () => {
		if ( typeof form.requestSubmit === 'function' ) {
			form.requestSubmit();
		} else {
			form.submit();
		}
	} );
} );
