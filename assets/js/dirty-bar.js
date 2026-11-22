/**
 * Save bar: Save waits until something changes; then the status says so,
 * Discard appears, and leaving the page asks first. Discard puts every field
 * back and returns focus to the first one changed. Without the script, Save
 * is always on and the status line is empty.
 */
bwc.behaviour( '[data-bwc-dirty]', ( form ) => {
	const status = form.querySelector( '[data-bwc-dirty-text]' );
	const discard = form.querySelector( '[data-bwc-discard]' );
	const save = form.querySelector( '[data-bwc-save]' );

	// Field values only: the kit's own inputs (nonce, group, search) start with "_".
	const snapshot = () => [ ...new FormData( form ).entries() ]
		.filter( ( [ name ] ) => ! name.startsWith( '_' ) && 'action' !== name )
		.map( ( [ name, value ] ) => name + '=' + value )
		.join( '&' );

	// Taken once every behaviour has started: chips, pickers and repeaters swap
	// their plain fields for their own inputs first, so an earlier snapshot
	// would never match the form again.
	let initial = null;
	window.setTimeout( () => {
		initial = snapshot();
	} );
	let dirty = false;
	let first = null;

	const set = ( value ) => {
		dirty = value;
		form.classList.toggle( 'is-dirty', value );
		if ( status ) {
			status.textContent = value ? bwc.string( 'unsaved' ) : '';
		}
		if ( discard ) {
			discard.hidden = ! value;
		}
		if ( save ) {
			save.disabled = ! value;
		}
	};

	const check = ( event ) => {
		if ( null === initial ) {
			initial = snapshot();
		}
		const changed = snapshot() !== initial;
		if ( changed && ! first && event && event.target.name && ! event.target.name.startsWith( '_' ) ) {
			first = event.target;
		}
		if ( ! changed ) {
			first = null;
		}
		if ( changed !== dirty ) {
			set( changed );
		}
	};

	set( false );
	form.addEventListener( 'input', check );
	form.addEventListener( 'change', check );

	if ( discard ) {
		discard.addEventListener( 'click', () => {
			const back = first;
			// Widgets built by scripts (chips, pickers, repeaters) put themselves back on the form's reset event.
			form.reset();
			// Let dependent fields, switch counts and the like catch up.
			form.querySelectorAll( 'input, select, textarea' ).forEach( ( control ) => {
				control.dispatchEvent( new Event( 'change', { bubbles: true } ) );
			} );
			set( snapshot() !== initial );
			first = null;
			if ( back && back.isConnected ) {
				form.dispatchEvent( new CustomEvent( 'bwc:reveal', { detail: back } ) );
				back.focus();
			}
		} );
	}

	form.addEventListener( 'submit', () => {
		dirty = false;
	} );

	window.addEventListener( 'beforeunload', ( event ) => {
		if ( dirty ) {
			event.preventDefault();
			event.returnValue = '';
		}
	} );
} );
