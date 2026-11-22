<?php
/**
 * Charts: line or area, stacked bars, grouped bars, funnel, sparkline, strip.
 *
 * @package BlissWebConcept\AdminKit
 */

namespace BlissWebConcept\AdminKit\Component;

use BlissWebConcept\AdminKit\Kit;

defined( 'ABSPATH' ) || exit;

/**
 * Charts. Drawn in PHP as SVG with a viewBox and
 * percentage geometry, laid out with flex, so no inline style is written and
 * nothing loads from outside. Series colours are the --bwc-color-chart-1…6
 * tokens. Each chart (but the sparkline and strip, which sit next to their
 * number) is a <figure> with a visible title, a one-sentence summary for
 * screen readers and a "Show the numbers" table.
 *
 * Line points sit in the middle of equal slots, so each hover zone (a flex
 * child) lines up with its point without a width written per point.
 */
final class Chart {

	use Renders;

	/**
	 * Bars or groups up to this many are focusable, each with its tooltip.
	 */
	private const FOCUSABLE_UP_TO = 12;

	/**
	 * Charts printed so far, for unique ids.
	 */
	private static int $count = 0;

	/**
	 * Any chart by kind: line (or area), bars (stacked), grouped, funnel, spark, strip.
	 *
	 * @param array<string, mixed> $props kind, plus that kind's props.
	 */
	public static function markup( array $props ): string {
		return match ( (string) ( $props['kind'] ?? 'line' ) ) {
			'line', 'area'       => self::line( $props + array( 'area' => 'area' === ( $props['kind'] ?? '' ) || ! empty( $props['area'] ) ) ),
			'bars', 'stacked'    => self::bars( $props ),
			'grouped'            => self::grouped( $props ),
			'funnel'             => self::funnel( $props ),
			'spark', 'sparkline' => self::spark( $props ),
			'strip'              => self::strip( $props ),
			default              => '',
		};
	}

	/**
	 * Line or area chart: a value per point, the early estimated part dashed.
	 *
	 * @param array<string, mixed> $props title, summary, description, legend, table, height, points (label, value, estimated, tip), y_max, area, x_labels, format.
	 */
	public static function line( array $props ): string {
		if ( ! Html::has( 'Chart', $props, 'title', 'summary' ) ) {
			return '';
		}

		$points = array_values( array_filter( (array) ( $props['points'] ?? array() ), 'is_array' ) );
		$n      = count( $points );
		if ( 0 === $n ) {
			return '';
		}

		$format = self::formatter( $props );
		$values = array_map( static fn( array $point ): float => (float) ( $point['value'] ?? 0 ), $points );
		$top    = self::top( $values, $props['y_max'] ?? null );
		$coords = array();
		$first  = null;

		foreach ( $points as $i => $point ) {
			$coords[] = array( round( ( $i + 0.5 ) / $n * 100, 3 ), round( 100 - $values[ $i ] / $top * 100, 3 ) );
			if ( empty( $point['estimated'] ) && null === $first ) {
				$first = $i;
			}
		}

		$path   = static fn( array $part ): string => $part ? 'M' . implode( ' L', array_map( static fn( array $p ): string => $p[0] . ',' . $p[1], $part ) ) : '';
		$dashed = null === $first ? $coords : array_slice( $coords, 0, $first + 1 );
		$solid  = null === $first ? array() : array_slice( $coords, $first );
		$end    = end( $coords );

		$svg  = '<svg class="bwc-chart__svg" viewBox="0 0 100 100" preserveAspectRatio="none" focusable="false">';
		$svg .= self::grid();
		if ( ! empty( $props['area'] ) ) {
			$svg .= '<path class="bwc-chart__area" d="M' . $coords[0][0] . ',100 L' . substr( $path( $coords ), 1 ) . ' L' . $end[0] . ',100 Z"/>';
		}
		if ( count( $dashed ) > 1 ) {
			$svg .= '<path class="bwc-chart__line bwc-chart__line--dashed" vector-effect="non-scaling-stroke" d="' . esc_attr( $path( $dashed ) ) . '"/>';
		}
		if ( count( $solid ) > 1 ) {
			$svg .= '<path class="bwc-chart__line" vector-effect="non-scaling-stroke" d="' . esc_attr( $path( $solid ) ) . '"/>';
		}
		// A zero-length line with round caps draws a dot that the stretched viewBox can't squash.
		$svg .= '<path class="bwc-chart__end" vector-effect="non-scaling-stroke" d="M' . $end[0] . ',' . $end[1] . ' L' . $end[0] . ',' . $end[1] . '"/></svg>';

		$hits     = '';
		$estimate = Kit::instance()->strings()->get( 'estimated' );
		foreach ( $points as $i => $point ) {
			$tip   = (string) ( $point['tip'] ?? ( (string) ( $point['label'] ?? '' ) . ': ' . $format( $values[ $i ] ) . ( empty( $point['estimated'] ) ? '' : ' ' . $estimate ) ) );
			$hits .= '<span class="bwc-chart__hit"><span class="' . esc_attr( self::tip_class( $i, $n ) ) . '">' . esc_html( $tip ) . '</span></span>';
		}

		$labels = array_map( static fn( array $point ): string => (string) ( $point['label'] ?? '' ), $points );
		$x      = 'ends' === ( $props['x_labels'] ?? 'ends-middle' ) ? array( $labels[0], $labels[ $n - 1 ] ) : array( $labels[0], $labels[ intdiv( $n, 2 ) ], $labels[ $n - 1 ] );

		$plot = '<div class="bwc-chart__plot" aria-hidden="true">'
			. self::y_axis( $top, $format )
			. '<div class="bwc-chart__canvas">' . $svg . '<div class="bwc-chart__hits">' . $hits . '</div></div>'
			. '<div class="bwc-chart__axis-x">' . implode( '', array_map( static fn( string $label ): string => '<span>' . esc_html( $label ) . '</span>', $n > 1 ? $x : array( $labels[0] ) ) ) . '</div>'
			. '</div>';

		$rows = array();
		foreach ( $points as $i => $point ) {
			$rows[] = array(
				'cells' => array(
					'label' => esc_html( (string) ( $point['label'] ?? '' ) ),
					'value' => esc_html( $format( $values[ $i ] ) . ( empty( $point['estimated'] ) ? '' : ' ' . $estimate ) ),
				),
			);
		}

		return self::frame(
			'line',
			$props,
			$plot,
			array(
				'label' => (string) ( $props['label_heading'] ?? Kit::instance()->strings()->get( 'chart_when' ) ),
				'value' => (string) ( $props['value_heading'] ?? Kit::instance()->strings()->get( 'chart_value' ) ),
			),
			$rows
		);
	}

	/**
	 * Stacked bar chart: one column per bar, its parts stacked by series.
	 *
	 * @param array<string, mixed> $props title, summary, description, legend, table, height, bars (label, parts (series, value), tip), y_max, format.
	 */
	public static function bars( array $props ): string {
		if ( ! Html::has( 'Chart', $props, 'title', 'summary' ) ) {
			return '';
		}

		$bars = array_values( array_filter( (array) ( $props['bars'] ?? array() ), 'is_array' ) );
		if ( ! $bars ) {
			return '';
		}

		$format  = self::formatter( $props );
		$names   = self::series_names( $props );
		$totals  = array_map( static fn( array $bar ): float => array_sum( array_map( static fn( $part ): float => (float) ( $part['value'] ?? 0 ), (array) ( $bar['parts'] ?? array() ) ) ), $bars );
		$top     = self::top( $totals, $props['y_max'] ?? null );
		$focus   = count( $bars ) <= self::FOCUSABLE_UP_TO;
		$columns = '';
		$series  = array();

		foreach ( $bars as $i => $bar ) {
			$y     = 100.0;
			$rects = '';
			$split = array();
			foreach ( (array) ( $bar['parts'] ?? array() ) as $part ) {
				$value = (float) ( $part['value'] ?? 0 );
				$s     = self::series( $part['series'] ?? 1 );
				if ( $value <= 0 ) {
					continue;
				}
				$height  = round( $value / $top * 100, 3 );
				$y      -= $height;
				$rects  .= '<rect class="bwc-chart__part bwc-chart__part--s' . $s . '" x="0" y="' . round( $y, 3 ) . '" width="1" height="' . $height . '"/>';
				$split[] = ( $names[ $s ] ?? '' ) . ' ' . $format( $value );

				$series[ $s ] = true;
			}

			$tip      = (string) ( $bar['tip'] ?? ( (string) ( $bar['label'] ?? '' ) . ': ' . $format( $totals[ $i ] ) . ( count( $split ) > 1 ? ' (' . implode( ', ', array_map( 'trim', $split ) ) . ')' : '' ) ) );
			$columns .= self::column( $rects, $tip, $focus, self::tip_class( $i, count( $bars ) ) );
		}

		$plot = self::bar_plot( $top, $format, $columns, array_map( static fn( array $bar ): string => (string) ( $bar['label'] ?? '' ), $bars ), $focus );

		$heads = array( 'label' => (string) ( $props['label_heading'] ?? Kit::instance()->strings()->get( 'chart_when' ) ) );
		ksort( $series );
		foreach ( array_keys( $series ) as $s ) {
			$heads[ 's' . $s ] = $names[ $s ] ?? (string) $s;
		}
		$heads['total'] = Kit::instance()->strings()->get( 'total' );

		$rows = array();
		foreach ( $bars as $i => $bar ) {
			$cells = array( 'label' => esc_html( (string) ( $bar['label'] ?? '' ) ) );
			foreach ( array_keys( $series ) as $s ) {
				$sum = 0.0;
				foreach ( (array) ( $bar['parts'] ?? array() ) as $part ) {
					$sum += self::series( $part['series'] ?? 1 ) === $s ? (float) ( $part['value'] ?? 0 ) : 0;
				}
				$cells[ 's' . $s ] = esc_html( $format( $sum ) );
			}
			$cells['total'] = esc_html( $format( $totals[ $i ] ) );
			$rows[]         = array( 'cells' => $cells );
		}

		return self::frame( 'bars', $props, $plot, $heads, $rows );
	}

	/**
	 * Grouped bar chart: groups side by side, a bar per series in each.
	 *
	 * @param array<string, mixed> $props title, summary, description, legend, table, height, groups (label, bars (series, value, max, tip)), percent, format.
	 */
	public static function grouped( array $props ): string {
		if ( ! Html::has( 'Chart', $props, 'title', 'summary' ) ) {
			return '';
		}

		$groups = array_values( array_filter( (array) ( $props['groups'] ?? array() ), 'is_array' ) );
		if ( ! $groups ) {
			return '';
		}

		$percent = ! empty( $props['percent'] );
		$format  = $percent ? static fn( float $value ): string => number_format_i18n( $value ) . '%' : self::formatter( $props );
		$names   = self::series_names( $props );
		$shown   = array();

		// A bar's height: its share of its own max when the chart shows percentages.
		foreach ( $groups as $g => $group ) {
			foreach ( (array) ( $group['bars'] ?? array() ) as $b => $bar ) {
				$value = (float) ( $bar['value'] ?? 0 );
				$max   = (float) ( $bar['max'] ?? 0 );

				$shown[ $g ][ $b ] = $percent && $max > 0 ? round( $value / $max * 100, 1 ) : $value;
			}
		}

		$top     = $percent ? 100.0 : self::top( array_merge( ...array_map( 'array_values', $shown ) ), $props['y_max'] ?? null );
		$focus   = count( $groups ) <= self::FOCUSABLE_UP_TO;
		$columns = '';
		$series  = array();

		foreach ( $groups as $g => $group ) {
			$bars  = '';
			$split = array();
			foreach ( (array) ( $group['bars'] ?? array() ) as $b => $bar ) {
				$s      = self::series( $bar['series'] ?? ( $b + 1 ) );
				$value  = (float) $shown[ $g ][ $b ];
				$height = round( min( $top, $value ) / $top * 100, 3 );
				$bars  .= '<span class="bwc-chart__group-bar"><svg viewBox="0 0 1 100" preserveAspectRatio="none" focusable="false"><rect class="bwc-chart__part bwc-chart__part--s' . $s . '" x="0" y="' . round( 100 - $height, 3 ) . '" width="1" height="' . $height . '"/></svg></span>';

				$split[]      = (string) ( $bar['tip'] ?? ( ( $names[ $s ] ?? '' ) . ' ' . $format( $value ) ) );
				$series[ $s ] = true;
			}

			$tip      = (string) ( $group['label'] ?? '' ) . ': ' . implode( ', ', array_map( 'trim', $split ) );
			$columns .= self::column( '', $tip, $focus, self::tip_class( $g, count( $groups ) ), $bars );
		}

		$plot = self::bar_plot( $top, $format, $columns, array_map( static fn( array $group ): string => (string) ( $group['label'] ?? '' ), $groups ), $focus, 'grouped' );

		$heads = array( 'label' => (string) ( $props['label_heading'] ?? Kit::instance()->strings()->get( 'chart_group' ) ) );
		ksort( $series );
		foreach ( array_keys( $series ) as $s ) {
			$heads[ 's' . $s ] = $names[ $s ] ?? (string) $s;
		}

		$rows = array();
		foreach ( $groups as $g => $group ) {
			$cells = array( 'label' => esc_html( (string) ( $group['label'] ?? '' ) ) );
			foreach ( array_keys( $series ) as $s ) {
				$cells[ 's' . $s ] = '';
			}
			foreach ( (array) ( $group['bars'] ?? array() ) as $b => $bar ) {
				$cells[ 's' . self::series( $bar['series'] ?? ( $b + 1 ) ) ] = esc_html( $format( (float) $shown[ $g ][ $b ] ) );
			}
			$rows[] = array( 'cells' => $cells );
		}

		return self::frame( 'grouped', $props, $plot, $heads, $rows );
	}

	/**
	 * Funnel: a list of meters, each step's count and its share of the first.
	 *
	 * @param array<string, mixed> $props title, summary, description, steps (label, value, of, unknown).
	 */
	public static function funnel( array $props ): string {
		if ( ! Html::has( 'Chart', $props, 'title', 'summary' ) ) {
			return '';
		}

		$steps = array_values( array_filter( (array) ( $props['steps'] ?? array() ), 'is_array' ) );
		if ( ! $steps ) {
			return '';
		}

		$first = max( 0, (int) ( $steps[0]['value'] ?? 0 ) );
		$html  = '<ol class="bwc-funnel">';
		foreach ( $steps as $step ) {
			$value   = (int) ( $step['value'] ?? 0 );
			$of      = max( 0, (int) ( $step['of'] ?? $first ) );
			$unknown = ! empty( $step['unknown'] );
			$share   = $of > 0 && ! $unknown ? (int) round( $value / $of * 100 ) : 0;

			// A step nobody can count gets an empty track, not a meter reading 0%.
			$bar   = $unknown ? '<span class="bwc-bar bwc-bar--regular" aria-hidden="true"></span>' : ProgressBar::markup(
				array(
					'value'      => $share,
					'label'      => (string) ( $step['label'] ?? '' ),
					'show_value' => false,
					'size'       => 'regular',
				)
			);
			$html .= '<li class="bwc-funnel__step"><span class="bwc-funnel__label">' . esc_html( (string) ( $step['label'] ?? '' ) ) . '</span>'
				. $bar
				. '<span class="bwc-funnel__count">' . ( $unknown ? '<span aria-hidden="true">—</span><span class="bwc-visually-hidden">' . esc_html( Kit::instance()->strings()->get( 'not_known' ) ) . '</span>' : esc_html( number_format_i18n( $value ) . ' · ' . $share . '%' ) ) . '</span></li>';
		}
		$html .= '</ol>';

		return self::frame( 'funnel', array( 'table' => false ) + $props, '<div class="bwc-chart__plot">' . $html . '</div>', array(), array() );
	}

	/**
	 * Sparkline: a small line in a table cell, next to the number it sums up.
	 *
	 * @param array<string, mixed> $props points (numbers), label (what it shows, in words), max.
	 */
	public static function spark( array $props ): string {
		$points = array_values( array_map( 'floatval', array_filter( (array) ( $props['points'] ?? array() ), 'is_numeric' ) ) );
		$n      = count( $points );
		if ( $n < 2 || empty( $props['label'] ) ) {
			return '';
		}

		$top    = isset( $props['max'] ) && (float) $props['max'] > 0 ? (float) $props['max'] : max( 1.0, max( $points ) );
		$coords = array();
		foreach ( $points as $i => $value ) {
			$coords[] = round( $i / ( $n - 1 ) * 100, 2 ) . ',' . round( 19 - min( $value, $top ) / $top * 18, 2 );
		}

		return '<svg class="bwc-chart bwc-chart--spark" viewBox="0 0 100 20" preserveAspectRatio="none" role="img" aria-label="' . esc_attr( (string) $props['label'] ) . '" focusable="false">'
			. '<path class="bwc-chart__line" vector-effect="non-scaling-stroke" d="M' . implode( ' L', $coords ) . '"/></svg>';
	}

	/**
	 * Strip: which slices of something were covered, as filled runs on a track.
	 *
	 * @param array<string, mixed> $props map (one character per slice, "1" = covered), label (the share and ranges, in words), marker (a slice to mark).
	 */
	public static function strip( array $props ): string {
		$map    = preg_replace( '/[^01]/', '0', (string) ( $props['map'] ?? '' ) );
		$slices = strlen( (string) $map );
		if ( 0 === $slices || empty( $props['label'] ) ) {
			return '';
		}

		$runs = '';
		preg_match_all( '/1+/', (string) $map, $found, PREG_OFFSET_CAPTURE );
		foreach ( $found[0] as [ $run, $start ] ) {
			$runs .= '<rect class="bwc-chart__covered" x="' . (int) $start . '" y="0" width="' . strlen( $run ) . '" height="1"/>';
		}

		$marker = '';
		if ( isset( $props['marker'] ) && is_numeric( $props['marker'] ) && (int) $props['marker'] >= 0 && (int) $props['marker'] <= $slices ) {
			$marker = '<path class="bwc-chart__marker" vector-effect="non-scaling-stroke" d="M' . (int) $props['marker'] . ',0 L' . (int) $props['marker'] . ',1"/>';
		}

		return '<svg class="bwc-chart bwc-chart--strip" viewBox="0 0 ' . $slices . ' 1" preserveAspectRatio="none" role="img" aria-label="' . esc_attr( (string) $props['label'] ) . '" focusable="false">'
			. '<rect class="bwc-chart__track" x="0" y="0" width="' . $slices . '" height="1"/>' . $runs . $marker . '</svg>';
	}

	/**
	 * The figure around a chart: title, legend, summary, plot, numbers.
	 *
	 * @param string                     $kind  Kind, for the modifier class.
	 * @param array<string, mixed>       $props Chart props.
	 * @param string                     $plot  The plot's HTML.
	 * @param array<string, string>      $heads Table column key => heading.
	 * @param list<array<string, mixed>> $rows  Table rows (Table component shape).
	 */
	private static function frame( string $kind, array $props, string $plot, array $heads, array $rows ): string {
		$id      = Kit::instance()->id() . '-chart-' . ( ++self::$count );
		$height  = (string) Html::pick( $props['height'] ?? 'regular', array( 'short', 'regular', 'tall' ), 'regular' );
		$legend  = empty( $props['legend'] ) ? '' : Legend::markup( array( 'items' => (array) $props['legend'] ) );
		$numbers = '';

		if ( false !== ( $props['table'] ?? true ) && $rows ) {
			$columns = array();
			foreach ( $heads as $key => $label ) {
				$columns[ $key ] = array(
					'label' => $label,
					'align' => 'label' === $key ? 'start' : 'end',
				);
			}
			$numbers = '<details class="bwc-chart__data"><summary class="bwc-chart__data-summary">' . esc_html( Kit::instance()->strings()->get( 'show_numbers' ) ) . '</summary>'
				. Table::markup(
					array(
						'caption'   => (string) $props['title'],
						'columns'   => $columns,
						'rows'      => $rows,
						'compact'   => true,
						'min_width' => 'none',
					)
				) . '</details>';
		}

		return '<figure' . Html::attributes(
			array(
				'class'            => Html::classes(
					'bwc-chart',
					array(
						$kind,
						'short' => 'short' === $height,
						'tall'  => 'tall' === $height,
					)
				),
				'aria-labelledby'  => $id . '-title',
				'aria-describedby' => $id . '-summary',
			)
		) . '>'
			. '<figcaption class="bwc-chart__head"><span class="bwc-chart__titles"><span class="bwc-chart__title" id="' . esc_attr( $id . '-title' ) . '">' . esc_html( (string) $props['title'] ) . '</span>'
			. ( empty( $props['description'] ) ? '' : '<span class="bwc-chart__description">' . esc_html( (string) $props['description'] ) . '</span>' )
			. '</span>' . $legend . '</figcaption>'
			. '<p class="bwc-visually-hidden" id="' . esc_attr( $id . '-summary' ) . '">' . esc_html( (string) $props['summary'] ) . '</p>'
			. $plot . $numbers . '</figure>';
	}

	/**
	 * The plot of a bar chart: Y axis, grid, the columns with their labels.
	 *
	 * @param float        $top     Axis top.
	 * @param callable     $format  Value format.
	 * @param string       $columns Columns' HTML.
	 * @param list<string> $labels  A label per column, in a row under the plot.
	 * @param bool         $focus   Whether the columns are focusable (else the plot is hidden from screen readers; the table carries it).
	 * @param string       $variant "stacked" or "grouped".
	 */
	private static function bar_plot( float $top, callable $format, string $columns, array $labels, bool $focus, string $variant = 'stacked' ): string {
		return '<div class="bwc-chart__plot"' . ( $focus ? '' : ' aria-hidden="true"' ) . '>'
			. self::y_axis( $top, $format )
			. '<div class="bwc-chart__canvas"><svg class="bwc-chart__svg" viewBox="0 0 100 100" preserveAspectRatio="none" focusable="false" aria-hidden="true">' . self::grid() . '</svg>'
			. '<div class="' . esc_attr( 'bwc-chart__columns bwc-chart__columns--' . $variant ) . '">' . $columns . '</div></div>'
			. '<div class="bwc-chart__axis-x bwc-chart__axis-x--each" aria-hidden="true">' . implode( '', array_map( static fn( string $label ): string => '<span>' . esc_html( $label ) . '</span>', $labels ) ) . '</div>'
			. '</div>';
	}

	/**
	 * One column: the bar (or a group's bars) and its tooltip.
	 *
	 * @param string $rects     SVG rects of a stacked bar.
	 * @param string $tip       Tooltip, also the column's name when focusable.
	 * @param bool   $focus     Focusable.
	 * @param string $tip_class Tooltip classes.
	 * @param string $bars      A group's bars, instead of $rects.
	 */
	private static function column( string $rects, string $tip, bool $focus, string $tip_class, string $bars = '' ): string {
		$body = '' === $bars
			? '<span class="bwc-chart__bar"><svg viewBox="0 0 1 100" preserveAspectRatio="none" focusable="false">' . $rects . '</svg></span>'
			: '<span class="bwc-chart__group-bars">' . $bars . '</span>';

		return '<span' . Html::attributes(
			array(
				'class'      => 'bwc-chart__column',
				'tabindex'   => $focus ? '0' : null,
				'role'       => $focus ? 'img' : null,
				'aria-label' => $focus ? $tip : null,
			)
		) . '>'
			. '<span class="bwc-chart__stack" aria-hidden="true">' . $body . '</span>'
			. '<span class="' . esc_attr( $tip_class ) . '" aria-hidden="true">' . esc_html( $tip ) . '</span>'
			. '</span>';
	}

	/**
	 * The Y axis: the top, the middle and 0.
	 *
	 * @param float    $top    Axis top.
	 * @param callable $format Value format.
	 */
	private static function y_axis( float $top, callable $format ): string {
		return '<div class="bwc-chart__axis-y" aria-hidden="true"><span class="bwc-chart__tick bwc-chart__tick--top">' . esc_html( $format( $top ) ) . '</span>'
			. '<span class="bwc-chart__tick bwc-chart__tick--middle">' . esc_html( $format( $top / 2 ) ) . '</span>'
			. '<span class="bwc-chart__tick bwc-chart__tick--base">' . esc_html( $format( 0 ) ) . '</span></div>';
	}

	/**
	 * Grid lines at the top, the middle and the base.
	 */
	private static function grid(): string {
		return '<path class="bwc-chart__grid" vector-effect="non-scaling-stroke" d="M0,0.5 H100 M0,50 H100"/>'
			. '<path class="bwc-chart__grid bwc-chart__grid--base" vector-effect="non-scaling-stroke" d="M0,99.5 H100"/>';
	}

	/**
	 * The axis top: the given max, or the largest value rounded up to a
	 * multiple of 4, so the middle tick is whole.
	 *
	 * @param list<float> $values Values.
	 * @param mixed       $given  y_max from the props.
	 */
	private static function top( array $values, mixed $given ): float {
		if ( is_numeric( $given ) && (float) $given > 0 ) {
			return (float) $given;
		}

		return (float) max( 4, ceil( max( array_merge( array( 0 ), $values ) ) / 4 ) * 4 );
	}

	/**
	 * How values are written: the chart's format callable, or a localised number.
	 *
	 * @param array<string, mixed> $props Chart props.
	 * @return callable(float): string
	 */
	private static function formatter( array $props ): callable {
		$format = $props['format'] ?? null;

		return is_callable( $format )
			? static fn( float $value ): string => (string) $format( $value )
			: static fn( float $value ): string => number_format_i18n( $value, floor( $value ) === $value ? 0 : 1 );
	}

	/**
	 * Series names from the legend, by series number.
	 *
	 * @param array<string, mixed> $props Chart props.
	 * @return array<int, string>
	 */
	private static function series_names( array $props ): array {
		$names = array();
		foreach ( (array) ( $props['legend'] ?? array() ) as $i => $item ) {
			if ( is_array( $item ) ) {
				$names[ self::series( $item['series'] ?? ( $i + 1 ) ) ] = (string) ( $item['label'] ?? '' );
			}
		}

		return $names;
	}

	/**
	 * A series number, 1 to 6.
	 *
	 * @param mixed $series Series.
	 */
	private static function series( mixed $series ): int {
		return max( 1, min( 6, (int) $series ) );
	}

	/**
	 * Tooltip classes: near the edges it opens inwards, so it stays in the chart.
	 *
	 * @param int $i Position.
	 * @param int $n Count.
	 */
	private static function tip_class( int $i, int $n ): string {
		$edge = $i < $n * 0.2 ? ' bwc-chart__tip--start' : ( $i >= $n * 0.8 ? ' bwc-chart__tip--end' : '' );

		return 'bwc-chart__tip' . $edge;
	}
}
