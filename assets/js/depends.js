/**
 * Dependent fields: a field with data-bwc-depends="{set}.{field}" is active
 * only while that field is on (or has one of data-bwc-depends-is, or none of
 * data-bwc-depends-not). Inactive fields are inert and greyed out, never
 * disabled, so their values are still sent and kept. The server prints the
 * right state first; this keeps it right as the owner changes things.
 */
bwc.behaviour( '[data-bwc-depends]', ( field ) => {
	const scope = field.closest( 'form' ) || document;
	const source = scope.querySelector( '[data-bwc-field="' + window.CSS.escape( field.dataset.bwcDepends ) + '"]' );
	if ( ! source ) {
		return;
	}

	const is = field.dataset.bwcDependsIs ? JSON.parse( field.dataset.bwcDependsIs ) : null;
	const not = field.dataset.bwcDependsNot ? JSON.parse( field.dataset.bwcDependsNot ) : null;

	const values = () => {
		const chosen = [];
		source.querySelectorAll( 'input, select, textarea' ).forEach( ( input ) => {
			if ( 'hidden' === input.type ) {
				return;
			}
			// A field inside the source (a radio option's own field) isn't the source's value.
			const nested = input.closest( '[data-bwc-depends]' );
			if ( nested && source.contains( nested ) ) {
				return;
			}
			if ( 'checkbox' === input.type || 'radio' === input.type ) {
				if ( input.checked ) {
					chosen.push( input.value );
				}
			} else {
				chosen.push( input.value );
			}
		} );
		return chosen;
	};

	const update = () => {
		const have = values();
		let active;
		if ( is ) {
			active = have.some( ( value ) => is.includes( value ) );
		} else if ( not ) {
			active = ! have.some( ( value ) => not.includes( value ) );
		} else {
			active = have.some( ( value ) => '' !== value && '0' !== value );
		}
		field.inert = ! active;
		field.classList.toggle( 'is-inactive', ! active );
	};

	source.addEventListener( 'change', update );
	source.addEventListener( 'input', update );
	update();
} );
