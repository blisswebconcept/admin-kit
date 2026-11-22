<?php
/**
 * A screen whose body the plugin draws.
 *
 * @package BlissWebConcept\AdminKit
 */

namespace BlissWebConcept\AdminKit\Screen;

defined( 'ABSPATH' ) || exit;

/**
 * For screens the kit has no pattern for yet: the Shell draws the frame,
 * tabs and notices; the plugin's render callback returns the body.
 */
final class CustomScreen extends BaseScreen {

	/**
	 * The body: the render callback's escaped HTML.
	 */
	public function render(): string {
		$render = $this->config['render'] ?? null;

		return is_callable( $render ) ? (string) $render( $this ) : '';
	}
}
