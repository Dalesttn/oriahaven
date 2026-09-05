<?php
/**
 * /ask/ — say what you are after, get real places. Oria Haven's front door.
 *
 * IT RETRIEVES. IT DOES NOT ADVISE.
 *
 * Someone describes the kind of thing they want, the sentence is read for
 * PREFERENCES, the reading is shown back as chips they can correct, and the
 * answer is a list of real listings with the reasons each one matched. The
 * only prose here that is not the site's own is a reviewer's, quoted and
 * credited by name.
 *
 * WHY IT ASKS WHAT YOU WANT AND NOT WHAT IS WRONG.
 *
 * The design brief opened with the placeholder "I'm stressed, sleeping badly
 * and my back is sore", and a worked example turning that into a week of
 * yoga, massage and meditation. Both are refused by the health guard in
 * ask.php, and deliberately: symptom in, modality out is a therapeutic claim
 * made by the architecture however carefully each sentence is worded.
 * writing style/schema.json names that trap, and TGA and AHPRA rules are the
 * reason it is not a matter of taste.
 *
 * So the placeholder describes a room instead of a body. The guard stays as
 * a net for the people who type a symptom anyway -- they get a plain note
 * and a pointer to a GP, never a list -- but nothing on this page teaches
 * anyone to do it.
 *
 * Oria speaks in "we" and "let's", never "I": identity.narrator in the
 * writing schema, and no loss, since the brief's own line "let's see what we
 * can find" was already plural.
 *
 * @see includes/ask.php  extraction schema, retrieval, match reasons
 * @see assets/js/ask-oria.js  the state machine
 */

declare(strict_types=1);

get_header();

$oria_cname = function_exists( '\Oria\Core\Cities\name' )
	? \Oria\Core\Cities\name( \Oria\Core\Cities\current() )
	: __( 'Perth', 'oria' );

/*
 * The examples are the instructions. Nobody reads a hint telling them to
 * "describe what you are looking for"; people click a sentence and edit it.
 * Each one exercises a different part of the extractor -- a kind, a budget,
 * a mood, a first-timer -- and not one is a symptom, because whatever is
 * printed here is what visitors will learn to type.
 */
$oria_examples = array(
	__( 'A quiet first class, somewhere near the city', 'oria' ),
	__( 'Something free and gentle in the evenings', 'oria' ),
	__( 'I want to meet people and I do not mind working hard', 'oria' ),
	__( 'Where is the best place to try a sauna?', 'oria' ),
	__( 'Hands-on, and nothing too spiritual', 'oria' ),
);
?>

<section class="askhero" id="askoria">
	<div class="wrap askhero__wrap">

		<nav class="crumbs askhero__crumbs" aria-label="<?php esc_attr_e( 'Breadcrumb', 'oria' ); ?>">
			<a href="<?php echo esc_url( home_url( '/' ) ); ?>"><?php esc_html_e( 'Home', 'oria' ); ?></a>
			<span aria-hidden="true">/</span><span><?php esc_html_e( 'Ask', 'oria' ); ?></span>
		</nav>

		<?php get_template_part( 'template-parts/oria-orb', null, array( 'class' => 'oria--hero', 'uid' => 'ask' ) ); ?>

		<div class="askhero__copy">
			<h1 class="askhero__title"><?php esc_html_e( 'What do you need right now?', 'oria' ); ?></h1>
			<p class="askhero__lede">
				<?php
				printf(
					/* translators: %s: city name. */
					esc_html__( 'Tell us the kind of thing you are after — how quiet, how much effort, what to spend — and we will find real places across %s.', 'oria' ),
					esc_html( $oria_cname )
				);
				?>
			</p>
		</div>

		<p class="oria__say" data-oria-say aria-hidden="true"></p>

		<form class="askbox" data-ask-form>
			<label class="sr-only" for="askq"><?php esc_html_e( 'Describe what you are looking for', 'oria' ); ?></label>
			<textarea class="askbox__input" id="askq" rows="1" maxlength="400"
				placeholder="<?php esc_attr_e( 'Somewhere quiet and hands-on, close to the city…', 'oria' ); ?>"
				data-ask-input></textarea>
			<button class="askbox__go" type="submit" data-ask-go>
				<span class="askbox__golabel"><?php esc_html_e( 'Ask Oria', 'oria' ); ?></span>
				<svg class="askbox__arrow" viewBox="0 0 20 20" aria-hidden="true" focusable="false">
					<path d="M3 10h13M11 5l5 5-5 5" fill="none" stroke="currentColor" stroke-width="1.8"
						stroke-linecap="round" stroke-linejoin="round" />
				</svg>
			</button>
		</form>

		<button class="asknoidea" type="button" data-ask-noidea>
			<?php esc_html_e( 'I do not know what I need', 'oria' ); ?>
		</button>

		<!--
			Guided mode. One question at a time, and not a questionnaire.

			Every branch is decided here in the browser, so it costs nothing
			against the daily limit -- which matters: a three-step conversation
			that spent a model call per step would use a visitor's whole
			allowance before they saw a single place. It also answers
			instantly, which a chat round-trip never does.

			The questions ask what someone wants the session to be like. None
			asks how they feel, because that invites the one answer this page
			has to refuse.
		-->
		<div class="askguide" data-ask-guide hidden>
			<p class="askguide__intro" data-ask-guide-intro></p>
			<h2 class="askguide__q" data-ask-guide-q></h2>
			<div class="askguide__opts" data-ask-guide-opts></div>
			<button class="askguide__back" type="button" data-ask-guide-back hidden><?php esc_html_e( 'Back', 'oria' ); ?></button>
		</div>

		<div class="askex" data-ask-examples>
			<span class="askex__label"><?php esc_html_e( 'Not sure what to say?', 'oria' ); ?></span>
			<div class="askex__row">
				<?php foreach ( $oria_examples as $oria_i => $oria_ex ) : ?>
					<button class="askex__btn" type="button" style="--i:<?php echo (int) $oria_i; ?>"
						data-ask-example="<?php echo esc_attr( $oria_ex ); ?>"><?php echo esc_html( $oria_ex ); ?></button>
				<?php endforeach; ?>
			</div>
		</div>

		<p class="asktrust">
			<span class="asktrust__strong"><?php esc_html_e( 'Oria helps you discover — it does not diagnose.', 'oria' ); ?></span>
			<?php esc_html_e( 'Real places, local information, human-checked listings. No medical advice, and nothing here is a recommendation for a health concern.', 'oria' ); ?>
		</p>
	</div>
</section>

<section class="wrap section askresults" data-ask-results>

	<div class="askread" data-ask-read hidden>
		<h2 class="askread__head"><?php esc_html_e( 'What we understood', 'oria' ); ?></h2>
		<p class="askread__hint"><?php esc_html_e( 'Click any of these to change it — the list follows the chips, not the sentence.', 'oria' ); ?></p>
		<div class="askread__chips" data-ask-chips></div>
	</div>

	<!-- A health disclosure gets this and nothing else. No chips, no list. -->
	<div class="askhealth" data-ask-health hidden>
		<h2 class="askhealth__title"><?php esc_html_e( 'This one is not ours to answer', 'oria' ); ?></h2>
		<p><?php esc_html_e( 'That sounds like a health question, and a directory is the wrong thing to ask. We list what businesses say about themselves — how long a session runs, how many people are in the room, what it costs — and none of that tells you what would help. A GP or a registered practitioner can.', 'oria' ); ?></p>
		<p class="askhealth__note"><?php esc_html_e( 'If you would still like to browse, describe the kind of session you want rather than what is wrong, and we will search on that.', 'oria' ); ?></p>
		<p><a class="btn btn--dark" href="<?php echo esc_url( home_url( '/explore/' ) ); ?>"><?php esc_html_e( 'Browse wellness in Perth', 'oria' ); ?></a></p>
	</div>

	<p class="askstatus" data-ask-status role="status" aria-live="polite"></p>

	<ul class="asklist" data-ask-list></ul>

	<!--
		Change the vibe. One-way nudges, deliberately: each one pushes a single
		preference and re-runs the search, and the chips above are how you take
		it back. Two ways to undo the same thing is one too many.

		None of these calls the model -- they post the corrected preferences
		straight back -- so refining costs nothing against the daily limit.
	-->
	<div class="askvibe" data-ask-vibe hidden>
		<h2 class="askvibe__head"><?php esc_html_e( 'Make it more…', 'oria' ); ?></h2>
		<div class="askvibe__row" data-ask-vibe-row></div>
	</div>

	<!--
		The map. Second, and it stays second: the answer is the list, and this
		is the answer opening out into the city. Leaflet and OpenStreetMap,
		the same pair /wellness-map/ uses, with coordinates geocoded through
		Nominatim -- never Google's, whose terms forbid storing them.
	-->
	<section class="askmap" data-ask-map-wrap hidden>
		<h2 class="askmap__head"><?php esc_html_e( 'Around Perth', 'oria' ); ?></h2>
		<div class="askmap__canvas" data-ask-map></div>
		<p class="askmap__note"><?php echo wp_kses_post( function_exists( '\Oria\Core\Geo\attribution' ) ? \Oria\Core\Geo\attribution() : '' ); ?></p>
	</section>

	<div class="askplan" data-ask-plan hidden>
		<h2 class="askplan__head"><?php esc_html_e( 'Want this as a week?', 'oria' ); ?></h2>
		<p class="askplan__copy"><?php esc_html_e( 'We can lay the same preferences out across seven days — a few places a day, and one day with nothing booked.', 'oria' ); ?></p>
		<a class="btn btn--dark askplan__go" href="<?php echo esc_url( home_url( '/plan-my-week/' ) ); ?>" data-ask-plan-link><?php esc_html_e( 'Build my reset', 'oria' ); ?> &rarr;</a>
	</div>

	<p class="askfoot">
		<?php esc_html_e( 'These are search results, not recommendations. Match reasons describe the room — how busy, how physical, what it costs — never what a session does for you. Quoted lines are Google reviews by the people named, reproduced as written: that person\'s experience, not ours. Distances are measured from the city or town centre, not from where you are. "Nothing too spiritual" is approximate — it reads a listing\'s categories and its name, so a place whose name gives nothing away can still slip through.', 'oria' ); ?>
	</p>
</section>

<?php
get_footer();
