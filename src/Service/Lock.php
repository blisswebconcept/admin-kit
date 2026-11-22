<?php
/**
 * A lock that only one request can hold.
 *
 * @package BlissWebConcept\AdminKit
 */

namespace BlissWebConcept\AdminKit\Service;

defined( 'ABSPATH' ) || exit;

/**
 * Stored as an option row. An insert either
 * succeeds or finds the row, so two requests can't both get it. A lock older
 * than its timeout was left by a request that died, and is taken over.
 */
final class Lock {

	/**
	 * Takes the lock.
	 *
	 * @param string $name    Option name.
	 * @param int    $timeout Seconds after which a held lock counts as left behind.
	 */
	public static function take( string $name, int $timeout ): bool {
		global $wpdb;

		// phpcs:disable WordPress.DB.DirectDatabaseQuery -- an atomic lock can't go through the options API.
		if ( $wpdb->query( $wpdb->prepare( "INSERT IGNORE INTO {$wpdb->options} (option_name, option_value, autoload) VALUES (%s, %s, 'off')", $name, (string) time() ) ) ) {
			wp_cache_delete( $name, 'options' );
			return true;
		}

		$held_since = (int) $wpdb->get_var( $wpdb->prepare( "SELECT option_value FROM {$wpdb->options} WHERE option_name = %s", $name ) );
		if ( $held_since && $held_since < time() - $timeout ) {
			$taken = (bool) $wpdb->query( $wpdb->prepare( "UPDATE {$wpdb->options} SET option_value = %s WHERE option_name = %s AND option_value = %s", (string) time(), $name, (string) $held_since ) );
			wp_cache_delete( $name, 'options' );
			return $taken;
		}
		// phpcs:enable

		return false;
	}

	/**
	 * Releases the lock.
	 *
	 * @param string $name Option name.
	 */
	public static function release( string $name ): void {
		delete_option( $name );
	}
}
