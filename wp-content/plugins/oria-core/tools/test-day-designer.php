<?php
/**
 * Acceptance checks for the Day Designer planner (Core\DayDesigner).
 *
 *   wp eval-file wp-content/plugins/oria-core/tools/test-day-designer.php
 *
 * Read-only: builds plans from data/day-designer.json against the site's own
 * listings and asserts the rules the brief makes non-negotiable -- context,
 * party pricing, budget, time, distance, indoor, freshness and empty states.
 */

use Oria\Core\DayDesigner as DD;

$pass = 0;
$fail = 0;
$ok   = static function ( bool $cond, string $what ) use ( &$pass, &$fail ): void {
	if ( $cond ) {
		++$pass;
		echo "  ok    {$what}\n";
	} else {
		++$fail;
		echo "  FAIL  {$what}\n";
	}
};
$run = static function ( string $key, array $in ): array {
	$ctx = DD\context( $key );
	return $ctx ? DD\plan( $ctx, DD\prefs( $in, $ctx ), 999 ) : array( 'ok' => false, 'plans' => array(), 'empty' => 'no context' );
};
$byid = static function ( array $res, string $id ): ?array {
	foreach ( $res['plans'] as $p ) {
		if ( $p['id'] === $id ) {
			return $p;
		}
	}
	return null;
};
$rows = array();
foreach ( DD\data()['experiences'] as $e ) {
	$rows[ $e['id'] ] = $e;
}

echo "Context\n";
$r = $run( 'float', array( 'budget' => 500, 'minutes' => 240, 'people' => 1 ) );
$ok( $r['ok'] && ! array_filter( $r['plans'], static fn( $p ) => ! in_array( 'float', $p['tags'], true ) ), 'float page: every plan is a water float' );
$r = $run( 'head-spa', array( 'budget' => 1000, 'minutes' => 240 ) );
$ok( $r['ok'] && ! array_filter( $r['plans'], static fn( $p ) => ! in_array( 'head-spa', $p['tags'], true ) ), 'head spa page: every plan is a head spa' );
$ok( null === DD\context( 'yoga' ), 'no yoga context (no priced timetable data yet)' );

echo "Party pricing\n";
$r = $run( 'spa', array( 'budget' => 3000, 'minutes' => 480, 'people' => 2, 'preset' => 'affordable' ) );
$p = $byid( $r, 'soothe-massage-60' );
$ok( $p && 24000 === $p['cost'], 'per-person $120 massage for two = $240 (' . ( $p['money']['cost'] ?? 'missing' ) . ')' );
$p = $byid( $r, 'ganesha-romance-120' );
$ok( $p && 36900 === $p['cost'], 'published $369 package for two stays $369, not doubled' );
$p = $byid( $r, 'soothe-sauna-30' );
$ok( $p && 7000 === $p['cost'], 'sauna priced per session for up to two stays $70 for two' );
$r1 = $run( 'spa', array( 'budget' => 3000, 'minutes' => 480, 'people' => 1 ) );
$ok( null === $byid( $r1, 'ganesha-romance-120' ), 'a two-person package is not offered to one person' );
$r2 = $run( 'head-spa', array( 'budget' => 3000, 'minutes' => 480, 'people' => 2 ) );
$ok( null === $byid( $r2, 'skinwellness-signature-90' ), 'one-at-a-time head spa is not priced for two' );

echo "Budget\n";
$r = $run( 'sauna', array( 'budget' => 80, 'minutes' => 240, 'people' => 2, 'cafe' => 1, 'cafe_each' => 15, 'preset' => 'affordable' ) );
$p = $byid( $r, 'melt-sauna-60' );
$ok( $p && null === $p['cafe'] && '' !== $p['cafe_note'] && 7000 === $p['total'], 'two $35 admissions + $15 each café on $80: café dropped, visibly ($70 total)' );
$ok( ! array_filter( $r['plans'], static fn( $q ) => $q['total'] > 8000 ), 'no plan totals more than the $80 budget' );
$r = $run( 'spa', array( 'budget' => 20, 'minutes' => 240, 'people' => 1 ) );
$ok( ! $r['ok'] && false !== strpos( $r['empty'], '$20 budget' ) && false !== strpos( $r['empty'], 'lowest-priced option' ), 'tiny budget: empty state names the budget and the nearest miss' );

echo "Time\n";
$r = $run( 'spa', array( 'budget' => 3000, 'minutes' => 60, 'people' => 1 ) );
$ok( $r['ok'] && ! array_filter( $r['plans'], static fn( $q ) => $q['session'] + DD\BUFFER > 60 ), 'one hour available: no session longer than 45 minutes' );
$r = $run( 'spa', array( 'budget' => 3000, 'minutes' => 120, 'people' => 1, 'cafe' => 1 ) );
$ok( ! array_filter( $r['plans'], static fn( $q ) => $q['duration'] > 120 ), 'every plan, café included, fits two hours' );

echo "Distance and indoor\n";
$r = $run( 'spa', array( 'budget' => 3000, 'minutes' => 480, 'people' => 1, 'near' => 'fremantle', 'km' => 5 ) );
$ok( ! array_filter( $r['plans'], static fn( $q ) => null === $q['km'] || $q['km'] > 5 ), 'within 5 km of Fremantle: nothing further away' );
$r = $run( 'couples', array( 'budget' => 3000, 'minutes' => 480, 'people' => 2, 'indoor' => 1 ) );
$ok( null === $byid( $r, 'swan-couples-escape-120' ) && null === $byid( $r, 'swan-oasis-60' ), 'indoor only: unknown-exposure outdoor packages excluded' );

echo "Freshness\n";
$old = $rows['crown-relaxation-60'];
$old['checked'] = '2025-01-01';
$ok( ! DD\fresh( $old, 90 ) && DD\fresh( $rows['crown-relaxation-60'], 90 ), 'a price older than 90 days is not used; a fresh one is' );

echo "Money\n";
$ok( '$37.50' === DD\money( 3750 ) && '$1,040' === DD\money( 104000 ), 'money(): $37.50 and $1,040' );

echo "\n{$pass} passed, {$fail} failed\n";
