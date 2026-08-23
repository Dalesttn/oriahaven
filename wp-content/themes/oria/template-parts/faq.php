<?php
/**
 * The FAQ block on landing pages, plus its FAQPage JSON-LD.
 *
 * Markup and structured data are emitted together, from one array, so
 * they can never drift apart — the commonest way FAQ markup turns into a
 * manual action is schema that promises answers the page doesn't show.
 *
 * @var array $args {
 *     @type WP_Term $term    Term to build questions for.
 *     @type array   $faqs    Ready-made pairs, for the pages that have no
 *                            term behind them — the hub and the directory.
 *     @type string  $heading Optional heading override.
 * }
 */

declare(strict_types=1);

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

$oria_term = $args['term'] ?? null;
$oria_faqs = isset( $args['faqs'] ) && is_array( $args['faqs'] ) ? $args['faqs'] : null;

if ( null === $oria_faqs ) {
	if ( ! $oria_term instanceof WP_Term || ! function_exists( '\Oria\Core\Faq\for_term' ) ) {
		return;
	}
	$oria_faqs = \Oria\Core\Faq\for_term( $oria_term );
}
if ( ! $oria_faqs ) {
	return;
}

$oria_heading = (string) ( $args['heading'] ?? __( 'Common questions', 'oria' ) );
$oria_id      = sanitize_html_class( (string) ( $args['id'] ?? '' ) ); // an anchor, when the page's spine links here

$oria_ld = array(
	'@context'   => 'https://schema.org',
	'@type'      => 'FAQPage',
	'mainEntity' => array_map(
		static fn( array $f ): array => array(
			'@type'          => 'Question',
			'name'           => $f['q'],
			'acceptedAnswer' => array( '@type' => 'Answer', 'text' => $f['a'] ),
		),
		$oria_faqs
	),
);
?>

<section class="wrap section section--top-flush<?php echo '' !== $oria_id ? ' floor' : ''; ?>"<?php echo '' !== $oria_id ? ' id="' . esc_attr( $oria_id ) . '"' : ''; ?>>
	<h2 class="h3 faq__title"><?php echo esc_html( $oria_heading ); ?></h2>
	<div class="faq">
		<?php foreach ( $oria_faqs as $oria_i => $oria_f ) : ?>
			<details class="faq__item"<?php echo 0 === $oria_i ? ' open' : ''; ?>>
				<summary class="faq__q"><?php echo esc_html( $oria_f['q'] ); ?></summary>
				<div class="faq__a"><p><?php echo esc_html( $oria_f['a'] ); ?></p></div>
			</details>
		<?php endforeach; ?>
	</div>
</section>

<script type="application/ld+json"><?php echo wp_json_encode( $oria_ld, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE ); ?></script>
