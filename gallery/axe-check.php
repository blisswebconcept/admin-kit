<?php
/**
 * The gallery's accessibility check: runs axe over every gallery page.
 *
 * Dev only. axe-core is a dev dependency of the kit; `npm run gallery:axe`
 * copies it into gallery/assets/vendor/, which isn't committed.
 *
 * @package BWCGallery
 */

namespace BWCGallery;

use BWCGallery\Vendor\BlissWebConcept\AdminKit\Component\Button;
use BWCGallery\Vendor\BlissWebConcept\AdminKit\Component\Notice;

defined( 'ABSPATH' ) || exit;

/**
 * Where the copied axe script lives, or '' when it hasn't been copied.
 */
function axe_url(): string {
	return is_readable( __DIR__ . '/assets/vendor/axe.min.js' ) ? plugins_url( 'assets/vendor/axe.min.js', __FILE__ ) : '';
}

/**
 * The pages to start from: every gallery section and every screen. The
 * check also follows the links in each page's tabs and side menu, so groups
 * and report tabs are found without listing them here.
 *
 * @return list<string>
 */
function axe_pages(): array {
	$pages = array();
	foreach ( array_keys( sections() ) as $key ) {
		if ( 'axe' !== $key ) {
			$pages[] = section_url( $key, '' );
		}
	}

	$shell = settings_demo_shell();
	if ( $shell ) {
		foreach ( $shell->screens() as $screen ) {
			$pages[] = $shell->url( $screen->id() );
		}
		// A record opened in place, which no menu links to.
		$members = report_demo_members();
		if ( $members ) {
			$pages[] = add_query_arg(
				array(
					'tab'    => 'members',
					'member' => (string) $members[0]['id'],
				),
				$shell->url( 'report' )
			);
		}
	}

	return $pages;
}

/**
 * Loads the check's script on its own tab only.
 */
function axe_scripts(): void {
	// phpcs:ignore WordPress.Security.NonceVerification.Recommended -- read-only tab switch on a dev screen.
	$section = isset( $_GET['section'] ) ? sanitize_key( wp_unslash( $_GET['section'] ) ) : '';
	if ( 'axe' !== $section || '' === axe_url() ) {
		return;
	}
	wp_enqueue_script( 'bwc-gallery-axe-check', plugins_url( 'assets/axe-check.js', __FILE__ ), array(), (string) filemtime( __DIR__ . '/assets/axe-check.js' ), true );
}
add_action( 'admin_enqueue_scripts', __NAMESPACE__ . '\\axe_scripts' );

/**
 * Accessibility: the axe check over every page.
 */
function section_axe(): string {
	if ( '' === axe_url() ) {
		return section(
			__( 'Accessibility', 'bwc-gallery' ),
			__( 'Runs axe over every gallery page.', 'bwc-gallery' ),
			array(
				Notice::markup(
					array(
						'tone'  => 'warning',
						'title' => __( 'axe isn\'t copied yet.', 'bwc-gallery' ),
						'text'  => __( 'Run "npm run gallery:axe" in the kit folder, then reload this page.', 'bwc-gallery' ),
					)
				),
			)
		);
	}

	$widths = array(
		'1440' => __( '1440px (desktop)', 'bwc-gallery' ),
		'768'  => __( '768px (tablet)', 'bwc-gallery' ),
		'320'  => __( '320px (phone)', 'bwc-gallery' ),
	);
	$width  = '<select class="bwc-control" id="bwc-gallery-axe-width" name="width">';
	foreach ( $widths as $value => $label ) {
		$width .= '<option value="' . esc_attr( $value ) . '">' . esc_html( $label ) . '</option>';
	}
	$width .= '</select>';

	$scheme = '<select class="bwc-control" id="bwc-gallery-axe-scheme" name="scheme"><option value="">' . esc_html__( 'Yours', 'bwc-gallery' ) . '</option>';
	foreach ( schemes() as $key => $info ) {
		$scheme .= '<option value="' . esc_attr( $key ) . '">' . esc_html( $info['label'] ) . '</option>';
	}
	$scheme .= '</select>';

	$form = '<form class="bwc-gallery-axe" data-bwc-gallery-axe="' . esc_attr( (string) wp_json_encode( axe_pages() ) ) . '" data-bwc-gallery-axe-script="' . esc_url( axe_url() ) . '">'
		. '<div class="bwc-gallery-row">'
		. '<label for="bwc-gallery-axe-width">' . esc_html__( 'Width', 'bwc-gallery' ) . '</label> ' . $width
		. ' <label for="bwc-gallery-axe-scheme">' . esc_html__( 'Colour scheme', 'bwc-gallery' ) . '</label> ' . $scheme
		. ' ' . Button::markup(
			array(
				'label'   => __( 'Check every page', 'bwc-gallery' ),
				'variant' => 'primary',
				'type'    => 'submit',
			)
		)
		. '</div>'
		. '<p class="bwc-gallery-help" role="status" data-bwc-gallery-axe-status></p>'
		. '<div data-bwc-gallery-axe-results></div>'
		. '</form>';

	return section(
		__( 'Accessibility', 'bwc-gallery' ),
		__( 'Runs axe (WCAG 2.2 A and AA, plus best practices) over every gallery page and screen, inside the kit\'s part of each page. It follows the tabs and side menus, so every group and report tab is checked.', 'bwc-gallery' ),
		array( example( __( 'Check', 'bwc-gallery' ), $form ) )
	);
}
