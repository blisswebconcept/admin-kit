<?php
/**
 * Gallery: the sample job that works in stages.
 *
 * @package BWCGallery
 */

namespace BWCGallery;

use BWCGallery\Vendor\BlissWebConcept\AdminKit\Service\Job;

defined( 'ABSPATH' ) || exit;

/**
 * A job in stages with no count, like a network fetch: string ids, no percent.
 */
final class Demo_Fetch_Job extends Job {

	/**
	 * Job id.
	 */
	public static function id(): string {
		return 'demo_fetch';
	}

	/**
	 * Pages of a feed, as cursors.
	 *
	 * @return list<string>
	 */
	protected static function items(): array {
		return array( 'page-a', 'page-b', 'page-c', 'page-d', 'page-e', 'page-f' );
	}

	/**
	 * Reads one "page".
	 *
	 * @param list<int|string>     $batch Items.
	 * @param array<string, mixed> $state State.
	 */
	protected static function work( array $batch, array &$state ): void {
		usleep( 900000 );
		$state['counts']['offers'] = (int) ( $state['counts']['offers'] ?? 0 ) + 37;
		$state['counts']['last']   = (string) $batch[0];
	}

	/**
	 * "Reading page-c · 74 offers so far".
	 *
	 * @param array<string, mixed> $state State.
	 */
	public static function progress( array $state ): string {
		/* translators: 1: feed page, 2: offers read. */
		return sprintf( __( 'Read %1$s · %2$d offers so far', 'bwc-gallery' ), (string) ( $state['counts']['last'] ?? '…' ), (int) ( $state['counts']['offers'] ?? 0 ) );
	}

	/**
	 * No count to show: the pages aren't known until they're read.
	 *
	 * @param array<string, mixed> $state State.
	 */
	public static function percent( array $state ): ?int {
		return self::is_running( $state ) ? null : 100;
	}
}
