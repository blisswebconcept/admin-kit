/**
 * Settings groups: shows one group (side menu) or tab at a time. The open
 * group comes from ?group= / ?tab=, then a link to something inside a group
 * (#field-id), then the one this user last opened (localStorage), then the
 * server's choice. Switching keeps the URL and the form's "come back here"
 * value in step, and the side menu's "3 of 4 on" counts follow the switches.
 * Without the script every group shows, one after another.
 */
bwc.behaviour( '[data-bwc-settings]', ( form ) => {
	const groups = [ ...form.querySelectorAll( '[data-bwc-settings-group]' ) ];
	const items = [ ...form.querySelectorAll( '[data-bwc-nav-item]' ) ];
	const current = form.querySelector( '[data-bwc-settings-current]' );
	const arg = form.dataset.bwcSettingsBy || 'group';
	const storeKey = 'bwc-settings:' + BWC_SCOPE + ':' + form.dataset.bwcSettings;
	const keys = groups.map( ( group ) => group.dataset.bwcSettingsGroup );

	const groupOf = ( key ) => groups.find( ( group ) => group.dataset.bwcSettingsGroup === key );

	const show = ( key, remember ) => {
		if ( ! keys.includes( key ) ) {
			return;
		}
		groups.forEach( ( group ) => group.classList.toggle( 'is-current', group.dataset.bwcSettingsGroup === key ) );
		items.forEach( ( item ) => {
			const on = item.dataset.bwcNavItem === key;
			item.classList.toggle( 'is-current', on );
			if ( on ) {
				item.setAttribute( 'aria-current', 'true' );
			} else {
				item.removeAttribute( 'aria-current' );
			}
		} );
		if ( current ) {
			current.value = key;
		}
		if ( groups.length > 1 ) {
			const url = new URL( window.location.href );
			url.searchParams.set( arg, key );
			window.history.replaceState( window.history.state, '', url );
		}
		if ( remember ) {
			try {
				window.localStorage.setItem( storeKey, key );
			} catch ( error ) {
				// Storage blocked: the group still opens, it just isn't remembered.
			}
		}
	};

	// Opens the group holding an element, and brings the element into view.
	const reveal = ( target ) => {
		const group = target && target.closest( '[data-bwc-settings-group]' );
		if ( ! group || ! form.contains( group ) ) {
			return false;
		}
		show( group.dataset.bwcSettingsGroup, false );
		if ( target !== group ) {
			target.scrollIntoView( { block: 'center' } );
			const control = target.matches( 'input, select, textarea, button' ) ? target : target.querySelector( 'input:not([type="hidden"]), select, textarea' );
			if ( control ) {
				control.focus( { preventScroll: true } );
			}
		}
		return true;
	};

	const fromHash = () => {
		const id = decodeURIComponent( window.location.hash.slice( 1 ) );
		return '' !== id && reveal( document.getElementById( id ) );
	};

	if ( ! fromHash() && ! new URL( window.location.href ).searchParams.has( arg ) ) {
		let saved = null;
		try {
			saved = window.localStorage.getItem( storeKey );
		} catch ( error ) {
			saved = null;
		}
		if ( saved ) {
			show( saved, false );
		}
	}

	window.addEventListener( 'hashchange', fromHash );
	form.addEventListener( 'bwc:reveal', ( event ) => reveal( event.detail ) );

	items.forEach( ( item ) => {
		item.addEventListener( 'click', ( event ) => {
			event.preventDefault();
			show( item.dataset.bwcNavItem, true );
			const group = groupOf( item.dataset.bwcNavItem );
			// On a phone the side menu sits above the groups: bring the group up.
			if ( group && group.getBoundingClientRect().top < 0 ) {
				group.scrollIntoView( { block: 'start' } );
			}
		} );
	} );

	const counts = () => {
		items.forEach( ( item ) => {
			const meta = item.querySelector( '[data-bwc-nav-meta]' );
			const group = groupOf( item.dataset.bwcNavItem );
			const switches = group ? [ ...group.querySelectorAll( '[data-bwc-section-switch]' ) ] : [];
			if ( meta && switches.length ) {
				meta.textContent = bwc.string( 'on_count' )
					.replace( '%1$d', String( switches.filter( ( input ) => input.checked ).length ) )
					.replace( '%2$d', String( switches.length ) );
			}
		} );
	};

	form.addEventListener( 'change', ( event ) => {
		if ( event.target.matches( '[data-bwc-section-switch]' ) ) {
			counts();
		}
	} );
	form.addEventListener( 'reset', () => window.setTimeout( counts ) );
} );
