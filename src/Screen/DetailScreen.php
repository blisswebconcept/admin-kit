<?php
/**
 * One record in full, opened in place from a list or report.
 *
 * @package BlissWebConcept\AdminKit
 */

namespace BlissWebConcept\AdminKit\Screen;

use BlissWebConcept\AdminKit\Component\Notice;
use BlissWebConcept\AdminKit\Kit;

defined( 'ABSPATH' ) || exit;

/**
 * DetailScreen. Not a page of its own: a
 * ListScreen or a report tab shows it in place when its parameter is in the
 * URL, with a back link that keeps the list's filters.
 */
final class DetailScreen {

	/**
	 * Constructor.
	 *
	 * @param Kit                  $kit    The kit.
	 * @param array<string, mixed> $config param, load, title, back, missing, blocks.
	 * @throws \InvalidArgumentException When param or load is missing.
	 */
	public function __construct( private Kit $kit, private array $config ) {
		if ( empty( $config['param'] ) || ! isset( $config['load'] ) || ! is_callable( $config['load'] ) ) {
			throw new \InvalidArgumentException( 'Admin Kit DetailScreen: "param" and a "load" callback are required.' );
		}
		$this->config['param'] = sanitize_key( (string) $config['param'] );
	}

	/**
	 * The URL parameter that opens it.
	 */
	public function param(): string {
		return (string) $this->config['param'];
	}

	/**
	 * The record id this request asks for, or null.
	 */
	public function requested(): ?string {
		// phpcs:ignore WordPress.Security.NonceVerification.Recommended -- which record to show; nothing is changed.
		$id = isset( $_GET[ $this->param() ] ) ? sanitize_text_field( wp_unslash( $_GET[ $this->param() ] ) ) : '';

		return '' === $id ? null : $id;
	}

	/**
	 * The record in full.
	 *
	 * @param string                                       $id   Record id.
	 * @param array<string, mixed>                         $view The list's or report's view, passed to the blocks.
	 * @param callable(array<string, scalar|null>): string $url  Link to the current page with changes; the back link drops this parameter.
	 */
	public function render( string $id, array $view, callable $url ): string {
		$strings = $this->kit->strings();
		$back    = '<a class="bwc-detail__back" href="' . esc_url( $url( array( $this->param() => null ) ) ) . '"><span aria-hidden="true">← </span>' . esc_html( (string) ( $this->config['back'] ?? $strings->get( 'back' ) ) ) . '</a>';
		$record  = ( $this->config['load'] )( $id );

		if ( ! is_array( $record ) ) {
			return '<div class="bwc-detail">' . $back . Notice::markup(
				array(
					'tone' => 'warning',
					'text' => (string) ( $this->config['missing'] ?? $strings->get( 'no_longer_exists' ) ),
				)
			) . '</div>';
		}

		$title  = is_callable( $this->config['title'] ?? null ) ? (string) $this->config['title']( $record ) : $id;
		$blocks = is_callable( $this->config['blocks'] ?? null ) ? (array) $this->config['blocks']( $record, $view ) : array();

		return '<div class="bwc-detail">' . $back
			. '<h2 class="bwc-detail__title">' . esc_html( $title ) . '</h2>'
			. '<div class="bwc-report__body">' . Blocks::render( array_values( array_filter( $blocks, 'is_array' ) ), $url ) . '</div></div>';
	}
}
