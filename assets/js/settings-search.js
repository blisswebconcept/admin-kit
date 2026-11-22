/**
 * Settings search: the "Find a setting" box filters sections by the words of
 * their titles, descriptions and field labels, across every group, and says
 * how many match. Each match shows the group it's in. Escape clears it.
 * The box is printed hidden and shown here, since it needs the script.
 */
bwc.behaviour( '[data-bwc-settings-search]', ( input ) => {
	const form = input.closest( '[data-bwc-settings]' );
	if ( ! form ) {
		return;
	}

	const box = input.closest( '[data-bwc-settings-search-box]' );
	const groups = [ ...form.querySelectorAll( '[data-bwc-settings-group]' ) ];
	const empty = form.querySelector( '[data-bwc-settings-empty]' );

	if ( box ) {
		box.hidden = false;
	}

	const update = () => {
		const words = input.value.trim().toLowerCase().split( /\s+/ ).filter( Boolean );
		const searching = words.length > 0;
		let found = 0;

		form.classList.toggle( 'is-searching', searching );

		groups.forEach( ( group ) => {
			let inGroup = 0;
			group.querySelectorAll( '[data-bwc-search]' ).forEach( ( section ) => {
				const match = ! searching || words.every( ( word ) => section.dataset.bwcSearch.includes( word ) );
				section.hidden = ! match;
				if ( match ) {
					inGroup++;
				}
				// A match inside a closed Advanced fold opens it.
				if ( searching && match ) {
					section.querySelectorAll( 'details' ).forEach( ( fold ) => {
						const text = fold.textContent.toLowerCase();
						if ( words.some( ( word ) => text.includes( word ) ) ) {
							fold.open = true;
						}
					} );
				}
			} );
			group.hidden = searching && 0 === inGroup;
			found += inGroup;
		} );

		if ( empty ) {
			empty.hidden = ! searching || found > 0;
		}
		if ( searching ) {
			bwc.announce( input, bwc.string( 'filter_shown' ).replace( '%d', String( found ) ) );
		}
	};

	input.addEventListener( 'input', update );
	// Discard resets the form, box included, and sends change: show everything again.
	input.addEventListener( 'change', update );
	input.addEventListener( 'keydown', ( event ) => {
		// The box sits inside the settings form: Enter mustn't save it.
		if ( 'Enter' === event.key ) {
			event.preventDefault();
		}
		if ( 'Escape' === event.key && '' !== input.value ) {
			input.value = '';
			update();
		}
	} );
} );
