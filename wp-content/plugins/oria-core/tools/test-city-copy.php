<?php
/**
 * Checks that copy written for the default city (Perth) is relabelled or
 * withheld on another city's pages, and left alone on Perth's.
 *   wp eval-file wp-content/plugins/oria-core/tools/test-city-copy.php
 * Every line should read PASS.
 */
use Oria\Core\Cities;
use Oria\Core\IntentPages;

$fail = 0;
$ok   = static function ( string $name, bool $cond ) use ( &$fail ): void {
	$fail += $cond ? 0 : 1;
	echo ( $cond ? 'PASS ' : 'FAIL ' ) . $name . "\n";
};

$perth = Cities\get( 'perth' );
$mr    = Cities\get( 'margaret-river' );
$ok( 'both cities exist', is_array( $perth ) && is_array( $mr ) );
$ok( 'Perth is the default', Cities\is_default( $perth ) && ! Cities\is_default( $mr ) );

// Labels.
$ok( 'label relabelled', 'Saunas in Margaret River' === Cities\relabel( 'Saunas in Perth', $mr ) );
$ok( 'label untouched on Perth', 'Saunas in Perth' === Cities\relabel( 'Saunas in Perth', $perth ) );
$ok( 'metro phrase uses the city\'s own word', 'across the Margaret River region' === Cities\relabel( 'across the Perth metro', $mr ) );
$ok( 'suburb names containing the word are whole-word only', 'Perthshire' === Cities\relabel( 'Perthshire', $mr ) );

// Prose.
$ok( 'Perth observation withheld elsewhere', ! Cities\keeps( 'It ranges widely in Perth — some studios cap at six.', $mr ) );
$ok( 'Perth observation kept on Perth', Cities\keeps( 'It ranges widely in Perth — some studios cap at six.', $perth ) );
$ok( 'city-free prose kept everywhere', Cities\keeps( 'Check whether the room is private or shared.', $mr ) );

$faqs = Cities\localize_faqs(
	array(
		array( 'q' => 'How many Perth studios run it?', 'a' => '{count} of the {total} listings publish it.' ),
		array( 'q' => 'How much does it cost in Perth?', 'a' => 'From about $25 in Perth.' ),
	),
	$mr
);
$ok( 'count question kept and relabelled', 1 === count( $faqs ) && 'How many Margaret River studios run it?' === $faqs[0]['q'] );

// Intent frames.
$page = IntentPages\page( 'spa', 'saunas' );
$ok( 'sauna frame exists', is_array( $page ) );
if ( is_array( $page ) ) {
	$loc = IntentPages\localized( $page, $mr );
	$ok( 'frame h1 names Margaret River', 'Saunas in Margaret River' === $loc['frame']['h1'] );
	$ok( 'frame title names Margaret River', false !== strpos( $loc['frame']['title'], 'Margaret River' ) && false === strpos( $loc['frame']['title'], 'Perth' ) );
	$ok( 'Perth opener withheld', '' === $loc['frame']['opener'] );
	$ok( 'city-free paragraphs kept', count( $loc['frame']['worth_knowing'] ) === count( $page['frame']['worth_knowing'] ) );
	$ok( 'Perth frame unchanged on Perth', IntentPages\localized( $page, $perth ) === $page );
}

echo $fail ? "FAILURES: $fail\n" : "all city copy checks pass\n";
