/**
 * Gallery accessibility check: loads each gallery page in a hidden frame at
 * the chosen width, runs axe on the kit's part of it, follows the links in
 * its tabs and side menu, and lists what axe found. Dev only.
 *
 * The full results are kept on window.bwcAxeResults.
 */
( () => {
	const form = document.querySelector( '[data-bwc-gallery-axe]' );
	if ( ! form ) {
		return;
	}

	const seeds = JSON.parse( form.dataset.bwcGalleryAxe || '[]' );
	const script = form.dataset.bwcGalleryAxeScript;
	const status = form.querySelector( '[data-bwc-gallery-axe-status]' );
	const results = form.querySelector( '[data-bwc-gallery-axe-results]' );
	const LIMIT = 120;
	const IGNORE = [ 'bwc_scheme', 'section_scroll', '_wpnonce' ];

	// One page, one key: same path and query, whatever the order, without the scheme.
	const key = ( href ) => {
		const url = new URL( href, location.href );
		const params = [ ...url.searchParams ].filter( ( [ name ] ) => ! IGNORE.includes( name ) ).sort( ( a, b ) => ( a[ 0 ] + a[ 1 ] ).localeCompare( b[ 0 ] + b[ 1 ] ) );
		return url.pathname + '?' + new URLSearchParams( params ).toString();
	};

	const isGallery = ( href ) => {
		const url = new URL( href, location.href );
		const page = url.searchParams.get( 'page' ) || '';
		return url.origin === location.origin && url.pathname.endsWith( '/admin.php' ) && page.startsWith( 'bwc-gallery' ) && url.searchParams.get( 'section' ) !== 'axe';
	};

	const load = ( frame, href ) => new Promise( ( resolve, reject ) => {
		const timer = window.setTimeout( () => reject( new Error( 'timed out' ) ), 30000 );
		frame.onload = () => {
			window.clearTimeout( timer );
			// Let the kit's scripts finish setting the page up.
			window.setTimeout( resolve, 600 );
		};
		frame.src = href;
	} );

	const inject = ( frame ) => new Promise( ( resolve, reject ) => {
		const doc = frame.contentDocument;
		const tag = doc.createElement( 'script' );
		tag.src = script;
		tag.onload = resolve;
		tag.onerror = () => reject( new Error( 'axe did not load' ) );
		doc.head.append( tag );
	} );

	const draw = ( pages ) => {
		const failing = pages.filter( ( page ) => page.error || page.violations.length );
		const list = document.createElement( 'div' );
		list.className = 'bwc-stack bwc-stack--gap-3';
		failing.forEach( ( page ) => {
			const box = document.createElement( 'details' );
			box.open = true;
			const summary = document.createElement( 'summary' );
			summary.textContent = page.title + ' (' + ( page.error ? page.error : page.violations.length + ' rule(s)' ) + ')';
			box.append( summary );
			const link = document.createElement( 'p' );
			const a = document.createElement( 'a' );
			a.href = page.url;
			a.textContent = page.url;
			link.append( a );
			box.append( link );
			const ul = document.createElement( 'ul' );
			page.violations.forEach( ( one ) => {
				const li = document.createElement( 'li' );
				const help = document.createElement( 'a' );
				help.href = one.helpUrl;
				help.target = '_blank';
				help.rel = 'noopener';
				help.textContent = one.id;
				li.append( '[' + one.impact + '] ', help, ': ' + one.help + ' (' + one.nodes.length + ')' );
				const targets = document.createElement( 'ul' );
				one.nodes.slice( 0, 5 ).forEach( ( node ) => {
					const t = document.createElement( 'li' );
					const code = document.createElement( 'code' );
					code.textContent = node.target.join( ' ' );
					t.append( code );
					if ( node.failureSummary ) {
						t.append( ' ' + node.failureSummary.split( '\n' ).slice( 1, 2 ).join( '' ).trim() );
					}
					targets.append( t );
				} );
				li.append( targets );
				ul.append( li );
			} );
			box.append( ul );
			list.append( box );
		} );
		results.replaceChildren( list );
	};

	form.addEventListener( 'submit', async ( event ) => {
		event.preventDefault();
		const button = form.querySelector( '[type="submit"]' );
		const width = parseInt( form.elements.width.value, 10 );
		const scheme = form.elements.scheme.value;
		button.disabled = true;
		results.replaceChildren();

		const frame = document.createElement( 'iframe' );
		frame.title = 'Page being checked';
		frame.style.cssText = 'position:absolute;inset-inline-start:-10000px;inset-block-start:0;block-size:900px;border:0';
		frame.style.inlineSize = width + 'px';
		frame.setAttribute( 'aria-hidden', 'true' );
		frame.tabIndex = -1;
		document.body.append( frame );

		const queue = [ ...seeds ];
		const seen = new Set( queue.map( key ) );
		const pages = [];

		while ( queue.length && pages.length < LIMIT ) {
			const href = queue.shift();
			const url = new URL( href, location.href );
			if ( scheme ) {
				url.searchParams.set( 'bwc_scheme', scheme );
			}
			status.textContent = 'Checking ' + ( pages.length + 1 ) + ' of ' + ( pages.length + queue.length + 1 ) + '…';
			const page = { url: url.toString(), title: '', violations: [], error: '' };
			try {
				await load( frame, url.toString() );
				const doc = frame.contentDocument;
				page.title = ( doc.title || '' ).split( /\s[‹|–-]\s/ )[ 0 ] + ' — ' + ( [ ...url.searchParams ].filter( ( [ n ] ) => n !== 'page' && ! IGNORE.includes( n ) ).map( ( [ n, v ] ) => n + '=' + v ).join( ', ' ) || url.searchParams.get( 'page' ) );
				const scope = doc.querySelector( '.bwc-scope' );
				if ( ! scope ) {
					throw new Error( 'no kit screen on this page' );
				}
				doc.querySelectorAll( '.bwc-scope .bwc-nav a[href]' ).forEach( ( a ) => {
					if ( isGallery( a.href ) && ! seen.has( key( a.href ) ) ) {
						seen.add( key( a.href ) );
						queue.push( a.href );
					}
				} );
				await inject( frame );
				const check = async () => {
					const found = await frame.contentWindow.axe.run( { include: [ '.bwc-scope' ] }, {
						runOnly: { type: 'tag', values: [ 'wcag2a', 'wcag2aa', 'wcag21a', 'wcag21aa', 'wcag22aa', 'best-practice' ] },
						resultTypes: [ 'violations' ],
					} );
					return found.violations;
				};
				page.violations = await check();

				// Groups and tabs shown in place (links to #part of this same page): open each and check it too.
				const here = frame.contentWindow.location.href.split( '#' )[ 0 ];
				const parts = [ ...doc.querySelectorAll( '.bwc-scope .bwc-nav a[href*="#"]' ) ].filter( ( a ) => a.href.split( '#' )[ 0 ] === here );
				for ( const a of parts ) {
					a.click();
					await new Promise( ( resolve ) => window.setTimeout( resolve, 300 ) );
					pages.push( { url: a.href, title: page.title + ' #' + a.href.split( '#' )[ 1 ], violations: await check(), error: '' } );
				}
			} catch ( error ) {
				page.error = error.message || String( error );
			}
			pages.push( page );
		}

		frame.remove();
		window.bwcAxeResults = { width, scheme, pages };
		const failing = pages.filter( ( page ) => page.error || page.violations.length ).length;
		status.textContent = 0 === failing
			? 'Checked ' + pages.length + ' pages at ' + width + 'px: axe found nothing.'
			: 'Checked ' + pages.length + ' pages at ' + width + 'px: ' + failing + ' with problems.';
		draw( pages );
		button.disabled = false;
	} );
} )();
