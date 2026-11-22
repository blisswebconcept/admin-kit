/**
 * Ordered chips: turns an ordered field's list (one choice per line) into
 * chips that can be dragged, moved with the arrow keys on their handle,
 * removed, and added from a select. Each move is announced, and the order
 * is posted as hidden inputs.
 */
bwc.behaviour( '[data-bwc-ordered]', ( root ) => {
	const fallback = root.querySelector( '[data-bwc-ordered-fallback]' );
	if ( ! fallback ) {
		return;
	}

	let options = [];
	let chosen = [];
	try {
		options = JSON.parse( root.dataset.bwcOrdered || '[]' );
		chosen = JSON.parse( root.dataset.bwcOrderedChosen || '[]' );
	} catch ( error ) {
		return;
	}

	const name = root.dataset.bwcOrderedName;
	const max = parseInt( root.dataset.bwcOrderedMax || '0', 10 );
	const id = root.dataset.bwcOrderedId;
	const labelOf = ( value ) => ( options.find( ( option ) => option.value === value ) || { label: value } ).label;
	const make = ( tag, props = {} ) => Object.assign( document.createElement( tag ), props );

	// The plain list and the line naming its choices; the chips replace both.
	const choices = root.querySelector( '[data-bwc-ordered-choices]' );
	if ( choices ) {
		choices.remove();
	}
	const label = document.querySelector( 'label[for="' + window.CSS.escape( id ) + '"]' );
	fallback.remove();

	const list = make( 'ol', { className: 'bwc-ordered__list' } );
	if ( label ) {
		list.setAttribute( 'aria-label', label.textContent.trim() );
	}
	const controls = make( 'p', { className: 'bwc-ordered__controls' } );
	const add = make( 'select', { className: 'bwc-control bwc-ordered__add', id } );
	const hint = make( 'span', { className: 'bwc-ordered__hint', textContent: bwc.string( 'ordered_hint' ) } );
	controls.append( add, hint );
	root.append( list, controls );

	let dragged = null;

	const move = ( from, to, focus ) => {
		if ( to < 0 || to >= chosen.length || from === to ) {
			return;
		}
		const [ value ] = chosen.splice( from, 1 );
		chosen.splice( to, 0, value );
		draw( focus ? to : -1 );
		bwc.announce( root, bwc.string( 'ordered_moved' ).replace( '%1$s', labelOf( value ) ).replace( '%2$d', String( to + 1 ) ) );
		root.dispatchEvent( new Event( 'change', { bubbles: true } ) );
	};

	const draw = ( focusAt = -1 ) => {
		list.replaceChildren(
			...chosen.map( ( value, i ) => {
				const label = labelOf( value );
				const item = make( 'li', { className: 'bwc-ordered__item', draggable: true } );
				const handle = make( 'button', { type: 'button', className: 'bwc-ordered__handle', innerHTML: '<span aria-hidden="true">⋮⋮</span>' } );
				handle.setAttribute( 'aria-label', bwc.string( 'ordered_handle' ).replace( '%1$s', label ).replace( '%2$d', String( i + 1 ) ) );
				const remove = make( 'button', { type: 'button', className: 'bwc-ordered__remove', textContent: '×' } );
				remove.setAttribute( 'aria-label', bwc.string( 'picker_remove' ).replace( '%s', label ) );

				handle.addEventListener( 'keydown', ( event ) => {
					if ( 'ArrowLeft' === event.key || 'ArrowUp' === event.key ) {
						event.preventDefault();
						move( i, i - 1, true );
					} else if ( 'ArrowRight' === event.key || 'ArrowDown' === event.key ) {
						event.preventDefault();
						move( i, i + 1, true );
					}
				} );
				remove.addEventListener( 'click', () => {
					chosen.splice( i, 1 );
					draw();
					add.focus();
					root.dispatchEvent( new Event( 'change', { bubbles: true } ) );
				} );

				item.addEventListener( 'dragstart', ( event ) => {
					dragged = i;
					item.classList.add( 'is-dragging' );
					event.dataTransfer.effectAllowed = 'move';
				} );
				item.addEventListener( 'dragend', () => item.classList.remove( 'is-dragging' ) );
				item.addEventListener( 'dragover', ( event ) => {
					event.preventDefault();
					item.classList.add( 'is-target' );
				} );
				item.addEventListener( 'dragleave', () => item.classList.remove( 'is-target' ) );
				item.addEventListener( 'drop', ( event ) => {
					event.preventDefault();
					item.classList.remove( 'is-target' );
					if ( null !== dragged ) {
						move( dragged, i, false );
					}
					dragged = null;
				} );

				item.append(
					handle,
					make( 'span', { className: 'bwc-ordered__position', textContent: String( i + 1 ) } ),
					make( 'span', { className: 'bwc-ordered__name', textContent: label } ),
					remove,
					make( 'input', { type: 'hidden', name, value } )
				);
				return item;
			} )
		);

		const free = options.filter( ( option ) => ! chosen.includes( option.value ) );
		add.replaceChildren(
			make( 'option', { value: '', textContent: root.dataset.bwcOrderedAdd || bwc.string( 'ordered_add' ) } ),
			...free.map( ( option ) => make( 'option', { value: option.value, textContent: option.label } ) )
		);
		add.disabled = 0 === free.length || ( max > 0 && chosen.length >= max );

		if ( focusAt >= 0 && list.children[ focusAt ] ) {
			list.children[ focusAt ].querySelector( 'button' ).focus();
		}
	};

	// Discard: the order and the choices the field had when the page loaded.
	const initial = chosen.slice();
	const form = root.closest( 'form' );
	if ( form ) {
		form.addEventListener( 'reset', () => {
			chosen = initial.slice();
			draw();
		} );
	}

	add.addEventListener( 'change', () => {
		if ( '' === add.value ) {
			return;
		}
		chosen.push( add.value );
		draw();
		add.focus();
		bwc.announce( root, labelOf( chosen[ chosen.length - 1 ] ) );
		root.dispatchEvent( new Event( 'change', { bubbles: true } ) );
	} );

	draw();
} );
