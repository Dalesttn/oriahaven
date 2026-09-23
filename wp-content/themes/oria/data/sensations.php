<?php
/**
 * What a treatment feels like, in one word and one colour.
 *
 * The "Choose a style" cards were six identical white panels, so heat,
 * cold, light and sound all looked the same and the section read as a
 * list of search results. This is the map that lets a card say which
 * kind of thing it is before the visitor reads its name.
 *
 * One file rather than classes written per title through the template,
 * because the same six cards appear on every category page with
 * different contents, and a colour decided in markup would have to be
 * decided again in every one.
 *
 * Keyed by the term slug taken from the card's own URL, so nothing had
 * to change upstream. A slug that is not here gets the category's own
 * tint and no cue, which is the honest answer: we know it is a style,
 * we do not claim to know how it feels. There are hundreds of service
 * terms and guessing would be worse than staying quiet.
 *
 * Families map to colour and an icon; the cue is the small label on the
 * card. Cues are sensations, never claims about a benefit.
 *
 * @package Oria
 */

defined( 'ABSPATH' ) || exit;

return array(

	// Heat.
	'infrared-sauna'       => array( 'heat', 'Gentle heat' ),
	'traditional-sauna'    => array( 'heat', 'High heat' ),
	'finnish-sauna'        => array( 'heat', 'High heat' ),
	'sauna'                => array( 'heat', 'Dry heat' ),
	'steam-room'           => array( 'heat', 'Wet heat' ),
	'hot-yoga'             => array( 'heat', 'Heated room' ),
	'bikram'               => array( 'heat', 'Heated room' ),

	// Cold.
	'ice-bath'             => array( 'cold', 'Cold reset' ),
	'cold-plunge'          => array( 'cold', 'Cold reset' ),
	'cryotherapy'          => array( 'cold', 'Deep cold' ),

	// Both, in rounds.
	'contrast-therapy'     => array( 'contrast', 'Hot then cold' ),

	// Light.
	'red-light-therapy'    => array( 'light', 'Light therapy' ),
	'led-light-therapy'    => array( 'light', 'Light therapy' ),
	'infrared-therapy'     => array( 'light', 'Light therapy' ),

	// Sound.
	'sound-healing'        => array( 'sound', 'Deep stillness' ),
	'sound-bath'           => array( 'sound', 'Deep stillness' ),
	'gong-bath'            => array( 'sound', 'Deep stillness' ),
	'singing-bowls'        => array( 'sound', 'Deep stillness' ),

	// Held still.
	'meditation'           => array( 'still', 'Stillness' ),
	'yoga-nidra'           => array( 'still', 'Lying down' ),
	'yin-yoga'             => array( 'still', 'Slow and held' ),
	'restorative-yoga'     => array( 'still', 'Slow and held' ),
	'float-therapy'        => array( 'still', 'Weightless' ),
	'floatation'           => array( 'still', 'Weightless' ),

	// Hands on.
	'massage'              => array( 'touch', 'Hands-on' ),
	'remedial-massage'     => array( 'touch', 'Hands-on' ),
	'deep-tissue-massage'  => array( 'touch', 'Hands-on' ),
	'relaxation-massage'   => array( 'touch', 'Hands-on' ),
	'sports-massage'       => array( 'touch', 'Hands-on' ),
	'lymphatic-drainage'   => array( 'touch', 'Hands-on' ),
	'reflexology'          => array( 'touch', 'Hands-on' ),
	'myofascial-release'   => array( 'touch', 'Hands-on' ),
	'reiki'                => array( 'touch', 'Hands off' ),

	// Moving.
	'vinyasa-yoga'         => array( 'move', 'Flowing' ),
	'vinyasa'              => array( 'move', 'Flowing' ),
	'hatha-yoga'           => array( 'move', 'Steady pace' ),
	'power-yoga'           => array( 'move', 'Strong pace' ),
	'reformer-pilates'     => array( 'move', 'On a reformer' ),
	'mat-pilates'          => array( 'move', 'On a mat' ),
	'barre'                => array( 'move', 'Small movements' ),
	'breathwork'           => array( 'move', 'Led breathing' ),
);
