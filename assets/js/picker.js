/**
 * Picker: turns a post, user or term field's plain control (a select, or a
 * list of usernames) into chips and a search box. Typing searches after a
 * short pause; arrow keys move through the results, Enter chooses, Escape
 * closes, Backspace in an empty box removes the last chip. The count of
 * results is announced. Each chip carries a hidden input with its id.
 */
bwc.behaviour( '[data-bwc-picker]', ( root ) => {
	const search = bwc.config.search;
	const fallback = root.querySelector( '[data-bwc-picker-fallback]' );
	if ( ! search || ! fallback || root.dataset.bwcPickerReadonly ) {
		return;
	}

	const token = root.dataset.bwcPicker;
	const name = root.dataset.bwcPickerName;
	const max = parseInt( root.dataset.bwcPickerMax || '0', 10 );
	const id = root.dataset.bwcPickerId;
	const describedby = fallback.getAttribute( 'aria-describedby' );
	let chosen = [];
	try {
		chosen = JSON.parse( root.dataset.bwcPickerChosen || '[]' );
	} catch ( error ) {
		chosen = [];
	}

	// The plain control goes; the search box takes its id, so its label still points at it.
	const more = root.querySelector( '[data-bwc-picker-more]' );
	if ( more ) {
		more.remove();
	}
	fallback.remove();

	const make = ( tag, props = {} ) => Object.assign( document.createElement( tag ), props );

	const chips = make( 'ul', { className: 'bwc-picker__chosen' } );
	chips.setAttribute( 'aria-label', bwc.string( 'picker_chosen' ) + ': ' + root.dataset.bwcPickerLabel );

	const input = make( 'input', { type: 'search', className: 'bwc-control bwc-picker__search', id, autocomplete: 'off', placeholder: bwc.string( 'picker_search' ) } );
	const list = make( 'ul', { className: 'bwc-picker__list', id: id + '-list', hidden: true } );
	const status = make( 'p', { className: 'bwc-picker__status' } );
	status.setAttribute( 'role', 'status' );
	input.setAttribute( 'role', 'combobox' );
	input.setAttribute( 'aria-expanded', 'false' );
	input.setAttribute( 'aria-controls', list.id );
	input.setAttribute( 'aria-autocomplete', 'list' );
	if ( describedby ) {
		input.setAttribute( 'aria-describedby', describedby );
	}
	list.setAttribute( 'role', 'listbox' );

	// The box and its list share a wrapper, so the list opens right under the box.
	const box = make( 'div', { className: 'bwc-picker__box' } );
	box.append( input, list );
	root.append( chips, box, status );

	let results = [];
	let active = -1;
	let timer = 0;
	let asked = 0;

	const full = () => max > 0 && chosen.length >= max && max !== 1;

	const drawChips = () => {
		chips.replaceChildren(
			...chosen.map( ( item ) => {
				const chip = make( 'li', { className: 'bwc-picker__chip' } );
				const remove = make( 'button', { type: 'button', className: 'bwc-picker__remove', textContent: '×' } );
				remove.setAttribute( 'aria-label', bwc.string( 'picker_remove' ).replace( '%s', item.label ) );
				remove.addEventListener( 'click', () => {
					chosen = chosen.filter( ( one ) => one.value !== item.value );
					drawChips();
					input.focus();
					bwc.announce( root, bwc.string( 'removed' ).replace( '%s', item.label ) );
					input.dispatchEvent( new Event( 'change', { bubbles: true } ) );
				} );
				chip.append( make( 'span', { textContent: item.label } ), remove, make( 'input', { type: 'hidden', name, value: String( item.value ) } ) );
				return chip;
			} )
		);
		chips.hidden = 0 === chosen.length;
	};

	const close = () => {
		list.hidden = true;
		input.setAttribute( 'aria-expanded', 'false' );
		input.removeAttribute( 'aria-activedescendant' );
		active = -1;
	};

	const highlight = ( index ) => {
		const options = [ ...list.children ];
		active = Math.max( 0, Math.min( options.length - 1, index ) );
		options.forEach( ( option, i ) => option.setAttribute( 'aria-selected', i === active ? 'true' : 'false' ) );
		if ( options[ active ] ) {
			input.setAttribute( 'aria-activedescendant', options[ active ].id );
			options[ active ].scrollIntoView( { block: 'nearest' } );
		}
	};

	const choose = ( item ) => {
		if ( chosen.some( ( one ) => one.value === item.value ) ) {
			close();
			return;
		}
		if ( 1 === max ) {
			chosen = [ item ];
		} else if ( ! full() ) {
			chosen.push( item );
		}
		drawChips();
		input.value = '';
		results = [];
		status.textContent = '';
		close();
		input.dispatchEvent( new Event( 'change', { bubbles: true } ) );
		bwc.announce( root, item.label );
	};

	const draw = () => {
		list.replaceChildren(
			...results.map( ( item, i ) => {
				const option = make( 'li', { className: 'bwc-picker__option', id: id + '-option-' + i } );
				option.setAttribute( 'role', 'option' );
				option.setAttribute( 'aria-selected', 'false' );
				option.append( make( 'span', { className: 'bwc-picker__label', textContent: '— '.repeat( item.depth || 0 ) + item.label } ) );
				if ( item.detail ) {
					option.append( make( 'span', { className: 'bwc-picker__detail', textContent: item.detail } ) );
				}
				option.addEventListener( 'mousedown', ( event ) => {
					event.preventDefault();
					choose( item );
				} );
				return option;
			} )
		);
		list.hidden = 0 === results.length;
		input.setAttribute( 'aria-expanded', results.length ? 'true' : 'false' );
		active = -1;
	};

	const find = async () => {
		const mine = ++asked;
		try {
			const response = await window.fetch( bwc.config.ajaxUrl, {
				method: 'POST',
				credentials: 'same-origin',
				body: new URLSearchParams( { action: search.action, _ajax_nonce: search.nonce, field: token, q: input.value.trim(), page: '1' } ),
			} );
			const json = await response.json();
			if ( mine !== asked ) {
				return;
			}
			if ( ! json.success ) {
				throw new Error( ( json.data && json.data.message ) || '' );
			}
			results = json.data.items.filter( ( item ) => ! chosen.some( ( one ) => one.value === item.value ) );
			draw();
			if ( ! results.length ) {
				status.textContent = bwc.string( 'picker_none' );
			} else {
				status.textContent = bwc.string( json.data.more ? 'picker_more' : 'picker_found' ).replace( '%d', String( results.length ) );
			}
		} catch ( error ) {
			if ( mine === asked ) {
				results = [];
				draw();
				status.textContent = error.message || bwc.string( 'picker_failed' );
			}
		}
	};

	input.addEventListener( 'input', () => {
		window.clearTimeout( timer );
		timer = window.setTimeout( find, 250 );
	} );
	input.addEventListener( 'focus', () => {
		if ( ! results.length ) {
			find();
		}
	} );
	input.addEventListener( 'blur', () => window.setTimeout( () => {
		close();
		status.textContent = '';
	}, 150 ) );
	input.addEventListener( 'keydown', ( event ) => {
		if ( 'ArrowDown' === event.key ) {
			event.preventDefault();
			if ( ! results.length ) {
				// Nothing fetched yet: Down opens the list, as a combobox does.
				find().then( () => highlight( 0 ) );
				return;
			}
			if ( list.hidden ) {
				draw();
			}
			highlight( active + 1 );
		} else if ( 'ArrowUp' === event.key ) {
			event.preventDefault();
			highlight( active - 1 );
		} else if ( 'Enter' === event.key ) {
			// Never submits the form from the search box.
			event.preventDefault();
			if ( active >= 0 && results[ active ] ) {
				choose( results[ active ] );
			}
		} else if ( 'Escape' === event.key && ! list.hidden ) {
			event.preventDefault();
			close();
		} else if ( 'Backspace' === event.key && '' === input.value && chosen.length ) {
			const gone = chosen.pop();
			drawChips();
			bwc.announce( root, bwc.string( 'removed' ).replace( '%s', gone.label ) );
			input.dispatchEvent( new Event( 'change', { bubbles: true } ) );
		}
	} );

	// Discard: the chips the field had when the page loaded.
	const initial = chosen.slice();
	const form = root.closest( 'form' );
	if ( form ) {
		form.addEventListener( 'reset', () => {
			chosen = initial.slice();
			drawChips();
			results = [];
			status.textContent = '';
			close();
		} );
	}

	drawChips();
} );
