<?php
/**
 * Messages for the current user that survive the redirect after an action.
 *
 * @package BlissWebConcept\AdminKit
 */

namespace BlissWebConcept\AdminKit\Service;

use BlissWebConcept\AdminKit\Component\Notice;
use BlissWebConcept\AdminKit\Kit;

defined( 'ABSPATH' ) || exit;

/**
 * Notices. Two kinds of message, kept together in
 * one transient per user for five minutes and read once:
 *
 * - notices: "Settings saved.", shown at the top of the next kit screen
 *   (Screen prints them under the header) or, on someone else's screen
 *   where the kit has fields (a post after a meta box save), through
 *   admin_notices;
 * - field messages: errors and notes for one form's fields, keyed by form
 *   ("settings:{option}", "post:{box}:{id}"…), read by that form.
 */
final class Notices {

	/**
	 * How long a message waits for the next page load.
	 */
	private const TTL = 5 * MINUTE_IN_SECONDS;

	/**
	 * Constructor.
	 *
	 * @param Kit $kit The kit.
	 */
	public function __construct( private Kit $kit ) {}

	/**
	 * Hooks the admin_notices fallback.
	 */
	public function register(): void {
		add_action( 'admin_notices', array( $this, 'admin_notices' ) );
	}

	/**
	 * Queues a notice for the current user's next kit screen.
	 *
	 * @param string               $tone info, success, warning or danger.
	 * @param string               $text Plain text.
	 * @param array<string, mixed> $args title (plain text), links (list of array{label, url}).
	 */
	public function flash( string $tone, string $text, array $args = array() ): void {
		if ( '' === trim( $text ) || ! get_current_user_id() ) {
			return;
		}

		$links = array();
		foreach ( (array) ( $args['links'] ?? array() ) as $link ) {
			if ( is_array( $link ) && ! empty( $link['label'] ) && ! empty( $link['url'] ) ) {
				$links[] = array(
					'label' => (string) $link['label'],
					'url'   => (string) $link['url'],
				);
			}
		}

		$all              = $this->read();
		$all['notices'][] = array(
			'tone'  => in_array( $tone, array( 'info', 'success', 'warning', 'danger' ), true ) ? $tone : 'info',
			'text'  => $text,
			'title' => isset( $args['title'] ) ? (string) $args['title'] : '',
			'links' => $links,
		);
		$this->write( $all );
	}

	/**
	 * The queued notices, removed once read.
	 *
	 * @return list<array{tone: string, text: string, title: string, links: list<array{label: string, url: string}>}>
	 */
	public function take(): array {
		$all     = $this->read();
		$notices = $all['notices'];

		if ( $notices ) {
			$all['notices'] = array();
			$this->write( $all );
		}

		return $notices;
	}

	/**
	 * The queued notices as HTML, removed once read. '' when there are none.
	 */
	public function markup(): string {
		$html = '';
		foreach ( $this->take() as $notice ) {
			$links = array();
			foreach ( $notice['links'] as $link ) {
				$links[] = '<a href="' . esc_url( $link['url'] ) . '">' . esc_html( $link['label'] ) . '</a>';
			}

			$props = array(
				'tone' => $notice['tone'],
				'text' => $notice['text'],
			);
			if ( '' !== $notice['title'] ) {
				$props['title'] = $notice['title'];
			}
			if ( $links ) {
				$props['actions_html'] = '<span class="bwc-notices__links">' . implode( ' ', $links ) . '</span>';
			}

			$html .= Notice::markup( $props );
		}

		return '' === $html ? '' : '<div class="bwc-notices">' . $html . '</div>';
	}

	/**
	 * Keeps one form's field messages for the next page load.
	 *
	 * @param string                $form   Which form: "settings:{option}", "post:{box}:{id}"…
	 * @param array<string, string> $errors Field path => message, for values not saved.
	 * @param array<string, string> $notes  Field path => message, for values saved with a change.
	 */
	public function fields( string $form, array $errors, array $notes ): void {
		// A save with no user (cron, WP-CLI) has nobody to show them to, as with flash().
		if ( ( ! $errors && ! $notes ) || ! get_current_user_id() ) {
			return;
		}

		$all                    = $this->read();
		$all['fields'][ $form ] = array(
			'errors' => array_map( 'strval', $errors ),
			'notes'  => array_map( 'strval', $notes ),
		);
		$this->write( $all );
	}

	/**
	 * One form's field messages, removed once read.
	 *
	 * @param string $form Which form.
	 * @return array{errors: array<string, string>, notes: array<string, string>}
	 */
	public function take_fields( string $form ): array {
		$all  = $this->read();
		$mine = $all['fields'][ $form ] ?? array();

		if ( isset( $all['fields'][ $form ] ) ) {
			unset( $all['fields'][ $form ] );
			$this->write( $all );
		}

		return array(
			'errors' => array_map( 'strval', (array) ( $mine['errors'] ?? array() ) ),
			'notes'  => array_map( 'strval', (array) ( $mine['notes'] ?? array() ) ),
		);
	}

	/**
	 * Prints queued notices on screens where the kit shows fields but doesn't
	 * draw the screen itself (a post, a term, a profile). Kit screens print
	 * their own, under the header.
	 */
	public function admin_notices(): void {
		$hook   = isset( $GLOBALS['hook_suffix'] ) ? (string) $GLOBALS['hook_suffix'] : '';
		$assets = $this->kit->assets();

		if ( $assets->is_kit_screen( $hook ) || ! $assets->wanted( $hook ) ) {
			return;
		}

		$html = $this->markup();
		if ( '' !== $html ) {
			echo '<div class="' . esc_attr( $this->kit->root_classes() ) . '">' . $html . '</div>'; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- escaped in markup().
		}
	}

	/**
	 * Transient name for this plugin and user.
	 */
	private function transient(): string {
		return $this->kit->name( 'notices_' . get_current_user_id() );
	}

	/**
	 * Everything queued for this user.
	 *
	 * @return array{notices: list<array{tone: string, text: string, title: string, links: list<array{label: string, url: string}>}>, fields: array<string, array{errors: array<string, string>, notes: array<string, string>}>}
	 */
	private function read(): array {
		$stored = get_transient( $this->transient() );
		$stored = is_array( $stored ) ? $stored : array();

		return array(
			'notices' => array_values( (array) ( $stored['notices'] ?? array() ) ),
			'fields'  => (array) ( $stored['fields'] ?? array() ),
		);
	}

	/**
	 * Saves what's queued, or forgets it when empty.
	 *
	 * @param array{notices: list<mixed>, fields: array<string, mixed>} $all Everything queued.
	 */
	private function write( array $all ): void {
		if ( ! $all['notices'] && ! $all['fields'] ) {
			delete_transient( $this->transient() );
			return;
		}

		set_transient( $this->transient(), $all, self::TTL );
	}
}
