<?php
/**
 * Job progress: a background job that's running now, and how it ended.
 *
 * @package BlissWebConcept\AdminKit
 */

namespace BlissWebConcept\AdminKit\Component;

use BlissWebConcept\AdminKit\Kit;

defined( 'ABSPATH' ) || exit;

/**
 * Job progress. The job script moves the job
 * on from the screen and updates the line and the bar; without it the card
 * shows the state at load and WP-Cron finishes the job.
 *
 * @phpstan-type JobProgressProps array{
 *     job: string,
 *     title: string,
 *     state: array{ stage: 'running'|'done'|'cancelled', text: string, percent?: int|null, failed?: int },
 *     retry?: array{ label: string, action: string },
 * }
 */
final class JobProgress {

	use Renders;

	/**
	 * The card.
	 *
	 * @param array<string, mixed> $props job, title, state (stage, text, percent, failed), retry (label, action).
	 * @phpstan-param JobProgressProps $props
	 */
	public static function markup( array $props ): string {
		if ( ! Html::has( 'JobProgress', $props, 'job', 'title' ) ) {
			return '';
		}

		$kit     = Kit::instance();
		$strings = $kit->strings();
		$state   = (array) ( $props['state'] ?? array() );
		$stage   = (string) Html::pick( $state['stage'] ?? 'running', array( 'running', 'done', 'cancelled' ), 'running' );
		$running = 'running' === $stage;
		$percent = array_key_exists( 'percent', $state ) && null === $state['percent'] ? null : (int) ( $state['percent'] ?? 0 );
		$job     = sanitize_key( (string) $props['job'] );

		$bar = '<progress class="bwc-job__bar" max="100"' . ( null === $percent ? '' : ' value="' . esc_attr( (string) $percent ) . '"' ) . ' data-bwc-job-bar aria-label="' . esc_attr( $strings->get( 'progress' ) ) . '">'
			. ( null === $percent ? '' : esc_html( $percent . '%' ) ) . '</progress>';

		$actions = '';
		if ( $running && $kit->actions()->has( 'job_cancel' ) ) {
			$actions = $kit->actions()->button(
				'job_cancel',
				$strings->get( 'stop' ),
				array(
					'input'   => array( 'job' => $job ),
					'size'    => 'small',
					'variant' => 'outline',
				)
			);
		} elseif ( ! $running && ! empty( $state['failed'] ) && ! empty( $props['retry']['action'] ) && $kit->actions()->has( (string) $props['retry']['action'] ) ) {
			$actions = $kit->actions()->button(
				(string) $props['retry']['action'],
				sprintf( (string) $props['retry']['label'], (int) $state['failed'] ),
				array( 'size' => 'small' )
			);
		}

		return '<section' . Html::attributes(
			array(
				'class'        => Html::classes( 'bwc-job', array(), $running ? 'is-running' : ( 'done' === $stage ? 'is-done' : 'is-stopped' ) ),
				'data-bwc-job' => $running ? $job : null,
				'aria-busy'    => $running ? 'true' : null,
			)
		) . '>'
			. '<div class="bwc-job__body"><p class="bwc-job__line"><strong>' . esc_html( (string) $props['title'] ) . '</strong> '
			. '<span class="bwc-job__text" data-bwc-job-text>' . esc_html( (string) ( $state['text'] ?? '' ) ) . '</span></p>'
			. ( $running ? $bar : '' ) . '</div>'
			. ( '' === $actions ? '' : '<div class="bwc-job__actions">' . $actions . '</div>' )
			. '</section>';
	}
}
