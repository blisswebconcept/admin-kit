<?php
/**
 * Gallery: the sample job with a count.
 *
 * @package BWCGallery
 */

namespace BWCGallery;

use BWCGallery\Vendor\BlissWebConcept\AdminKit\Service\Job;

defined( 'ABSPATH' ) || exit;

/**
 * A job with a known count: "checks" 40 sample links, a few at a time.
 */
final class Demo_Check_Job extends Job {

	/**
	 * Job id.
	 */
	public static function id(): string {
		return 'demo_check';
	}

	/**
	 * Forty sample links.
	 *
	 * @return list<int>
	 */
	protected static function items(): array {
		return range( 1, 40 );
	}

	/**
	 * Three at a time.
	 *
	 * @param list<int|string> $pending Pending.
	 * @return list<int|string>
	 */
	protected static function batch( array $pending ): array {
		return array_slice( $pending, 0, 3 );
	}

	/**
	 * "Checks" each link: a short wait, every ninth is broken, and with
	 * failures turned on, 13 and 26 stop the batch.
	 *
	 * @param list<int|string>     $batch Items.
	 * @param array<string, mixed> $state State.
	 * @throws \RuntimeException When failures are turned on.
	 */
	protected static function work( array $batch, array &$state ): void {
		foreach ( $batch as $id ) {
			usleep( 250000 );
			if ( get_option( JOB_DEMO_FAIL ) && in_array( (int) $id, array( 13, 26 ), true ) ) {
				/* translators: %d: sample link number. */
				throw new \RuntimeException( esc_html( sprintf( __( 'Link %d didn\'t answer.', 'bwc-gallery' ), (int) $id ) ) );
			}
			if ( 0 === (int) $id % 9 ) {
				$state['counts']['broken'] = (int) ( $state['counts']['broken'] ?? 0 ) + 1;
			}
		}
	}

	/**
	 * "23 of 40 checked · 2 broken so far".
	 *
	 * @param array<string, mixed> $state State.
	 */
	public static function progress( array $state ): string {
		$broken = (int) ( $state['counts']['broken'] ?? 0 );

		return self::is_running( $state )
			/* translators: 1: links checked, 2: links in all, 3: broken links. */
			? sprintf( __( '%1$d of %2$d checked · %3$d broken so far', 'bwc-gallery' ), (int) $state['done'], (int) $state['total'], $broken )
			/* translators: 1: links checked, 2: broken links. */
			: sprintf( __( '%1$d checked, %2$d broken.', 'bwc-gallery' ), (int) $state['done'], $broken );
	}
}
