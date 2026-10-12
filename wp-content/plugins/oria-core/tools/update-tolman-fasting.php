<?php
/**
 * Fill in the "FASTING: The Ultimate Panacea" Perth event (Tyler Tolman, 18 Oct 2026).
 *
 *   wp eval-file wp-content/plugins/oria-core/tools/update-tolman-fasting.php
 *
 * The importer brought it in from Eventbrite with no times, no price and a
 * guessed description. Facts here are from tylertolman.com/tour and the
 * Eventbrite page (its event data and FAQ), checked 12 Oct 2026. The talk's
 * own marketing makes disease claims; none are repeated -- the topics are
 * described as the organiser's, and the page says to talk to a GP first.
 * Safe to rerun.
 *
 * @package Oria\Core
 */

defined( 'ABSPATH' ) || exit;

$event = get_page_by_path( 'fasting-the-ultimate-panacea-october-2026-perth', OBJECT, 'event' );
if ( ! $event instanceof WP_Post ) {
	WP_CLI::error( 'The Tyler Tolman fasting event (fasting-the-ultimate-panacea-october-2026-perth) was not found.' );
}
$id = (int) $event->ID;

$desc = '<p>A three-hour Sunday morning talk on fasting by Tyler Tolman, an Australian health speaker who runs the Tolman Health Academy and says he has spent decades leading group fasts and mentoring coaches. It opens the Perth leg of his 2026 Australian tour, at Rendezvous Scarborough on the Esplanade.</p>'
	. '<p>Tolman describes the talk as an introduction to fasting for people curious about it but unsure where to start: his take on fasting as a practice, the &ldquo;7 Principles of Health&rdquo; he draws from long-lived traditional cultures, and how he suggests building it into everyday life. It is a seminar-style talk rather than a hands-on workshop.</p>'
	. '<h3>Good to know</h3><ul>'
	. '<li><strong>Times:</strong> 9am to 12pm. General registration opens at 8.30am (VIP from 8.20am) and the talk starts at 9am sharp.</li>'
	. '<li><strong>Tickets:</strong> $48.96 to $99.79 on Eventbrite, depending on ticket type (checked 12 Oct 2026). Door sales only if it hasn&rsquo;t sold out; upgrades through events@tylertolman.com.</li>'
	. '<li><strong>Refunds:</strong> none, but you can transfer your ticket to someone else.</li>'
	. '<li><strong>Age:</strong> not suitable for under-14s.</li>'
	. '<li><strong>On sale at the event:</strong> Tolman Health products and colon cleanse packages.</li>'
	. '<li><strong>Parking:</strong> not provided, so book or find parking near the Esplanade beforehand.</li>'
	. '<li><strong>Same day:</strong> his separate &ldquo;Ancient Egypt: Reclaiming Wisdom&rdquo; talk runs from 4.30pm (its own ticket).</li>'
	. '</ul>'
	. '<p>Fasting isn&rsquo;t right for everyone. If you have a health condition, take regular medication, are pregnant or breastfeeding, or have a history of disordered eating, talk to your GP before changing how you eat.</p>';

update_field( 'field_oria_event_start', '2026-10-18 09:00:00', $id );
update_field( 'field_oria_event_end', '2026-10-18 12:00:00', $id );
update_field( 'field_oria_event_price', '$48.96–$99.79', $id );
update_field( 'field_oria_event_description', $desc, $id );
update_field( 'field_oria_event_booking', 'https://www.eventbrite.com.au/e/fasting-the-ultimate-panacea-october-2026-perth-tickets-1999481446832', $id );
update_post_meta( $id, '_oria_organiser', 'Tyler Tolman' );
update_post_meta( $id, '_oria_verified', '2026-10-12' );
wp_update_post(
	array(
		'ID'           => $id,
		'post_excerpt' => 'A three-hour Sunday morning talk on fasting by health speaker Tyler Tolman at Rendezvous Scarborough, 9am to 12pm, tickets from $48.96.',
	)
);

clean_post_cache( $id );
do_action( 'litespeed_purge_post', $id );
WP_CLI::success( sprintf( 'Updated #%d: %s', $id, get_permalink( $id ) ) );
