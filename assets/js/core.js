/**
 * Admin Kit core, the first module in every build.
 *
 * bin/bwc-build wraps all modules in one function and defines BWC_CONFIG
 * (this plugin's config object) and BWC_SCOPE (its scope class) above them.
 * Behaviours only look inside this plugin's own kit roots, and only through
 * data-bwc-* hooks.
 */

const bwc = {
	config: BWC_CONFIG,

	/**
	 * Every kit root of this plugin on the page.
	 *
	 * @return {Element[]} Roots.
	 */
	roots() {
		return [ ...document.getElementsByClassName( BWC_SCOPE ) ];
	},

	/**
	 * Elements matching a selector inside this plugin's kit roots, the roots included.
	 *
	 * @param {string} selector A data-bwc-* attribute selector.
	 * @return {Element[]} Matches.
	 */
	find( selector ) {
		const found = new Set();
		bwc.roots().forEach( ( root ) => {
			if ( root.matches( selector ) ) {
				found.add( root );
			}
			root.querySelectorAll( selector ).forEach( ( element ) => found.add( element ) );
		} );
		return [ ...found ];
	},

	/**
	 * A kit string from the config object.
	 *
	 * @param {string} key Key.
	 * @return {string} Text, or the key when missing.
	 */
	string( key ) {
		return ( bwc.config.strings && bwc.config.strings[ key ] ) || key;
	},

	/**
	 * Says something through the screen's polite live region.
	 *
	 * @param {Element} from    Element inside the screen.
	 * @param {string}  message Text.
	 */
	announce( from, message ) {
		const root = from.closest( '.' + BWC_SCOPE ) || bwc.roots()[ 0 ];
		const live = root && root.querySelector( '[data-bwc-live]' );
		if ( ! live ) {
			return;
		}
		live.textContent = '';
		window.setTimeout( () => {
			live.textContent = message;
		}, 50 );
	},

	behaviours: [],

	/**
	 * Registers a behaviour: init runs once per matching element on DOM ready.
	 *
	 * @param {string}   selector Hook selector, such as "[data-bwc-copy]".
	 * @param {Function} init     Gets the element.
	 */
	behaviour( selector, init ) {
		bwc.behaviours.push( { selector, init } );
	},
};

document.addEventListener( 'DOMContentLoaded', () => {
	bwc.behaviours.forEach( ( { selector, init } ) => {
		bwc.find( selector ).forEach( ( element ) => init( element ) );
	} );
} );
