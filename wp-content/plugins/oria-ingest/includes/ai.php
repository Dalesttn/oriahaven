<?php
/**
 * AI classification and extraction via the Claude API.
 *
 * One call per candidate event. The model confirms wellness relevance,
 * picks an event type, normalises the fields, and writes an ORIGINAL short
 * description — the prompt forbids copying source wording, inventing facts,
 * and any medical/therapeutic outcome claims (TGA). Configure with:
 *
 *   define( 'ORIA_ANTHROPIC_KEY', 'sk-ant-…' );   // wp-config.php
 *
 * Without a key the pipeline falls back to keyword heuristics and leaves
 * descriptions empty for the reviewer to write.
 */

declare(strict_types=1);

namespace Oria\Ingest\AI;

use Oria\Ingest\Taxonomy;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

const MODEL = 'claude-sonnet-5';

function key(): string {
	return defined( 'ORIA_ANTHROPIC_KEY' ) ? (string) ORIA_ANTHROPIC_KEY : '';
}

function configured(): bool {
	return '' !== key();
}

/**
 * Classify + normalise one candidate. Returns the refined candidate, or
 * null when the model rules it irrelevant / the call fails (caller falls
 * back to heuristics on failure, so a flaky network never loses an event).
 *
 * @param array<string, string> $c candidate from Fetch.
 * @return array<string, mixed>|null
 */
function refine( array $c ): ?array {
	$types  = implode( ', ', Taxonomy\slugs() );
	$prompt = <<<PROMPT
You screen events for a Perth (Western Australia) wellness directory. Given raw event data scraped from a public page, decide if it is a genuine in-person wellness event in the Perth metro area, and normalise it.

Rules:
- relevant=false for: online-only events, anything outside Perth metro, non-wellness events, multi-level marketing, and anything you cannot place in one of the allowed types.
- type must be exactly one of: {$types}
- description: write 25-60 words in YOUR OWN words summarising what happens and who it suits. Never copy sentences from the source. Never invent details not present in the input. NO medical, therapeutic or outcome claims (no "heals", "treats", "cures", "reduces anxiety" etc.) — describe the activity, not promised results.
- suburb: the Perth suburb only (e.g. "Fremantle"). Empty string if unknown.
- price: like "$35", "Free", "By donation", or "" if unknown.
- start/end: "YYYY-MM-DD HH:MM" 24h Perth local time, end may be "".
- confidence: 0-1, how sure you are about relevance + extraction overall.

Input JSON:
%s

Reply with ONLY a JSON object: {"relevant": bool, "type": string, "title": string, "description": string, "suburb": string, "venue": string, "price": string, "start": string, "end": string, "organiser": string, "confidence": number}
PROMPT;

	$body = array(
		'model'      => apply_filters( 'oria_ingest_model', MODEL ),
		'max_tokens' => 700,
		'messages'   => array(
			array(
				'role'    => 'user',
				'content' => sprintf( $prompt, wp_json_encode( $c, JSON_UNESCAPED_SLASHES ) ),
			),
		),
	);

	$res = wp_remote_post(
		'https://api.anthropic.com/v1/messages',
		array(
			'timeout' => 60,
			'headers' => array(
				'content-type'      => 'application/json',
				'x-api-key'         => key(),
				'anthropic-version' => '2023-06-01',
			),
			'body'    => wp_json_encode( $body ),
		)
	);
	if ( is_wp_error( $res ) || 200 !== wp_remote_retrieve_response_code( $res ) ) {
		return null;
	}

	$payload = json_decode( wp_remote_retrieve_body( $res ), true );
	// The reply can open with a thinking block; the answer is the first
	// text block, wherever it sits.
	$text = '';
	foreach ( (array) ( $payload['content'] ?? array() ) as $block ) {
		if ( 'text' === ( $block['type'] ?? '' ) ) {
			$text = (string) ( $block['text'] ?? '' );
			break;
		}
	}
	// Tolerate accidental code fences.
	$text = preg_replace( '/^```(?:json)?|```$/m', '', trim( $text ) ) ?? $text;
	$out  = json_decode( trim( $text ), true );
	if ( ! is_array( $out ) || empty( $out['relevant'] ) ) {
		return is_array( $out ) ? array( 'relevant' => false ) : null;
	}

	$type = (string) ( $out['type'] ?? '' );
	if ( ! in_array( $type, Taxonomy\slugs(), true ) ) {
		return array( 'relevant' => false );
	}

	return array(
		'relevant'    => true,
		'type'        => $type,
		'title'       => sanitize_text_field( (string) ( $out['title'] ?? $c['title'] ) ),
		'description' => sanitize_textarea_field( (string) ( $out['description'] ?? '' ) ),
		'suburb'      => sanitize_text_field( (string) ( $out['suburb'] ?? $c['suburb'] ) ),
		'venue'       => sanitize_text_field( (string) ( $out['venue'] ?? $c['venue'] ) ),
		'price'       => sanitize_text_field( (string) ( $out['price'] ?? '' ) ),
		'start'       => sanitize_text_field( (string) ( $out['start'] ?? '' ) ),
		'end'         => sanitize_text_field( (string) ( $out['end'] ?? '' ) ),
		'organiser'   => sanitize_text_field( (string) ( $out['organiser'] ?? $c['organiser'] ) ),
		'confidence'  => max( 0.0, min( 1.0, (float) ( $out['confidence'] ?? 0.5 ) ) ),
	);
}
