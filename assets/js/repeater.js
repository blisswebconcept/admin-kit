/**
 * Repeater: rows added from a template, removed, and moved up and down with
 * buttons (keyboard first; no dragging). The server's spare empty rows and
 * each row's Remove box are for browsers without the script, so they go.
 * Rows post in the order they appear, so moving needs no renumbering.
 */
bwc.behaviour( '[data-bwc-repeater]', ( root ) => {
	const rows = root.querySelector( '[data-bwc-repeater-rows]' );
	const template = root.querySelector( '[data-bwc-repeater-template]' );
	const add = root.querySelector( '[data-bwc-repeater-add]' );
	if ( ! rows || ! template || ! add ) {
		return;
	}

	const max = parseInt( root.dataset.bwcRepeaterMax || '0', 10 );
	const pattern = root.dataset.bwcRepeaterLabel || '%d';
	let next = 0;

	const changed = () => root.dispatchEvent( new Event( 'change', { bubbles: true } ) );
	const titleOf = ( row ) => {
		const title = row.querySelector( '[data-bwc-repeater-title]' );
		return title ? title.textContent : '';
	};

	const renumber = () => {
		[ ...rows.children ].forEach( ( row, i ) => {
			const title = row.querySelector( '[data-bwc-repeater-title]' );
			const text = pattern.replace( '%d', String( i + 1 ) );
			if ( title && /\d/.test( title.textContent ) ) {
				title.textContent = text;
			}
			[ [ 'up', 'move_up' ], [ 'down', 'move_down' ], [ 'remove', 'remove' ] ].forEach( ( [ hook, key ] ) => {
				const button = row.querySelector( '[data-bwc-repeater-' + hook + ']' );
				if ( button ) {
					button.setAttribute( 'aria-label', bwc.string( key ) + ': ' + ( title ? title.textContent : text ) );
				}
			} );
			const up = row.querySelector( '[data-bwc-repeater-up]' );
			const down = row.querySelector( '[data-bwc-repeater-down]' );
			if ( up ) {
				up.disabled = 0 === i;
			}
			if ( down ) {
				down.disabled = i === rows.children.length - 1;
			}
		} );
		add.disabled = max > 0 && rows.children.length >= max;
	};

	const wire = ( row ) => {
		const actions = row.querySelector( '[data-bwc-repeater-actions]' );
		const box = row.querySelector( '[data-bwc-repeater-remove-box]' );
		if ( actions ) {
			actions.hidden = false;
		}
		if ( box ) {
			box.remove();
		}
		row.querySelector( '[data-bwc-repeater-up]' ).addEventListener( 'click', ( event ) => {
			if ( row.previousElementSibling ) {
				rows.insertBefore( row, row.previousElementSibling );
				renumber();
				event.currentTarget.disabled ? row.querySelector( '[data-bwc-repeater-down]' ).focus() : event.currentTarget.focus();
				bwc.announce( root, bwc.string( 'repeater_moved' ).replace( '%s', titleOf( row ) ) );
				changed();
			}
		} );
		row.querySelector( '[data-bwc-repeater-down]' ).addEventListener( 'click', ( event ) => {
			if ( row.nextElementSibling ) {
				rows.insertBefore( row.nextElementSibling, row );
				renumber();
				event.currentTarget.disabled ? row.querySelector( '[data-bwc-repeater-up]' ).focus() : event.currentTarget.focus();
				bwc.announce( root, bwc.string( 'repeater_moved' ).replace( '%s', titleOf( row ) ) );
				changed();
			}
		} );
		row.querySelector( '[data-bwc-repeater-remove]' ).addEventListener( 'click', () => {
			const target = row.nextElementSibling || row.previousElementSibling;
			const gone = titleOf( row );
			row.remove();
			bwc.announce( root, bwc.string( 'removed' ).replace( '%s', gone ) );
			renumber();
			( target ? target.querySelector( 'input:not([type="hidden"]), select' ) : add ).focus();
			changed();
		} );
	};

	// Spare empty rows are for browsers without the script.
	rows.querySelectorAll( '[data-bwc-repeater-blank]' ).forEach( ( row ) => row.remove() );
	[ ...rows.children ].forEach( wire );

	add.hidden = false;
	add.addEventListener( 'click', () => {
		const html = template.innerHTML.replaceAll( '__i__', 'n' + Date.now().toString( 36 ) + ( next++ ) );
		const holder = document.createElement( 'div' );
		holder.innerHTML = html;
		const row = holder.firstElementChild;
		row.removeAttribute( 'data-bwc-repeater-blank' );
		rows.append( row );
		wire( row );
		renumber();
		const first = row.querySelector( 'input:not([type="hidden"]), select' );
		if ( first ) {
			first.focus();
		}
		bwc.announce( root, bwc.string( 'added' ).replace( '%s', titleOf( row ) ) );
		changed();
	} );

	// Discard: the rows the page loaded with, in their first order. Added rows
	// go; removed ones come back. The form's reset then puts their values back.
	const initial = [ ...rows.children ];
	const form = root.closest( 'form' );
	if ( form ) {
		form.addEventListener( 'reset', () => {
			rows.replaceChildren( ...initial );
			renumber();
		} );
	}

	renumber();
} );
