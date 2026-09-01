<?php
/**
 * The JSON seed importer.
 *
 *   wp oria import path/to/listings.json --dry-run
 *   wp oria import path/to/listings.json
 *
 * Reads the same file format as the prototype's data/listings.json:
 * { categories: [...], regions: [...], listings: [...] }.
 *
 * Rules the command enforces:
 *   - Upserts by slug, so re-running updates instead of duplicating.
 *   - NEVER touches a listing whose claim_status is claimed or featured.
 *     Once a practitioner owns their profile, the seed file is stale.
 *   - --dry-run reports everything it would do and writes nothing.
 */

declare(strict_types=1);

namespace Oria\Core\Import;

use Oria\Core\PostTypes;
use Oria\Core\Taxonomies;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class Command {

	/** DEV ONLY: whether import may refresh claimed/featured listings. */
	private bool $include_claimed = false;

	/** Whether import may update existing listings (default: add-only). */
	private bool $update_mode = false;

	/**
	 * Duplicate index of existing listings: normalized name, phone and
	 * website domain each map to the listing's title for reporting.
	 *
	 * @var array<string, array<string, string>>
	 */
	private array $dupe_index = array(
		'name'   => array(),
		'phone'  => array(),
		'domain' => array(),
	);

	/**
	 * Import listings from a JSON seed file.
	 *
	 * By default the import only ADDS listings. Anything that already exists
	 * — same id, or the same practice under a different id (matched by name,
	 * phone number or website domain) — is reported as a duplicate and left
	 * untouched.
	 *
	 * ## OPTIONS
	 *
	 * <file>
	 * : Path to the JSON file.
	 *
	 * [--dry-run]
	 * : Report what would change without writing anything.
	 *
	 * [--update]
	 * : Allow rows whose id matches an existing listing to update it.
	 *   Claimed/featured listings are still protected.
	 *
	 * [--include-claimed]
	 * : DEV ONLY, with --update. Also refresh claimed/featured listings.
	 *   Never use this against a site with real practitioner edits.
	 *
	 * ## EXAMPLES
	 *
	 *     wp oria import new-listings.json --dry-run
	 *     wp oria import new-listings.json
	 *     wp oria import corrections.json --update
	 */
	public function import( array $args, array $assoc ): void {
		list( $file ) = $args;
		$dry                    = isset( $assoc['dry-run'] );
		$this->update_mode      = isset( $assoc['update'] );
		$this->include_claimed  = isset( $assoc['include-claimed'] );
		$this->build_dupe_index();

		if ( ! file_exists( $file ) ) {
			\WP_CLI::error( "File not found: {$file}" );
		}

		$data = json_decode( (string) file_get_contents( $file ), true );
		if ( ! is_array( $data ) || empty( $data['listings'] ) ) {
			\WP_CLI::error( 'Not a valid seed file: expected top-level "listings".' );
		}

		if ( $dry ) {
			\WP_CLI::log( '— DRY RUN: nothing will be written —' );
		}

		$this->ensure_terms( $data, $dry );

		// After ensure_terms, so categories the file declares have been
		// created and only genuinely absent ones are reported.
		$this->check_terms( (array) $data['listings'], $dry );

		$created   = 0;
		$updated   = 0;
		$skipped   = 0;
		$duplicate = 0;

		foreach ( $data['listings'] as $row ) {
			$result = $this->upsert_listing( $row, $data, $dry );
			++${$result};
		}

		\WP_CLI::success( sprintf(
			'%s%d created, %d duplicate(s) left untouched, %d updated, %d skipped.',
			$dry ? '[dry-run] ' : '',
			$created,
			$duplicate,
			$updated,
			$skipped
		) );
	}

	/**
	 * Create the site's pages pre-built from the designed sections, and wire
	 * up the front page and posts page.
	 *
	 * ## OPTIONS
	 *
	 * [--force]
	 * : Overwrite the sections on pages that already exist.
	 *
	 * ## EXAMPLES
	 *
	 *     wp oria seed_pages
	 */
	/**
	 * Reconcile the JSON vocabularies with the terms on this site.
	 *
	 * The same three syncs as the admin screens, in the order they have to
	 * run: categories first, because services and audiences are described in
	 * terms of them, then the service vocabulary and its listing re-scan,
	 * then the audience terms.
	 *
	 * Exists because the admin buttons are the only way to run these, and a
	 * deploy over SSH otherwise ends with a browser trip that is easy to
	 * forget — and forgetting it leaves imported listings sitting in
	 * categories that have the seed file's name rather than the canonical one.
	 *
	 * ## OPTIONS
	 *
	 * [--skip-services]
	 * : Skip the service vocabulary and its listing re-scan, which is the
	 *   slow part on a large corpus.
	 *
	 * ## EXAMPLES
	 *
	 *     wp oria sync
	 *     wp oria sync --skip-services
	 */
	public function sync( array $args, array $assoc ): void {
		$c = \Oria\Core\Categories\sync();
		\WP_CLI::log( sprintf(
			'Categories: %d created, %d renamed, %d reparented, %d unchanged, %d intros written.',
			$c['created'], $c['renamed'], $c['reparented'], $c['unchanged'], $c['intros']
		) );
		foreach ( (array) ( $c['notes'] ?? array() ) as $note ) {
			\WP_CLI::warning( $note );
		}

		if ( ! isset( $assoc['skip-services'] ) ) {
			$s = \Oria\Core\Services\sync_terms();
			\WP_CLI::log( sprintf( 'Services: %d created, %d updated, %d unchanged.', $s['created'], $s['updated'], $s['unchanged'] ) );

			$m = \Oria\Core\Services\map_listings();
			\WP_CLI::log( sprintf( 'Re-scanned %d listings, attached %d service terms.', $m['listings'], $m['attached'] ) );

			// The unmatched list is how the vocabulary grows. Ignoring it is how
			// it stops growing.
			$top = array_slice( (array) $m['unmatched'], 0, 10, true );
			if ( $top ) {
				\WP_CLI::log( 'Most common unmatched service strings:' );
				foreach ( $top as $name => $count ) {
					\WP_CLI::log( sprintf( '    %3d  %s', $count, $name ) );
				}
			}
		}

		$a = \Oria\Core\Audience\sync_terms();
		\WP_CLI::log( sprintf( 'Audiences: %d created, %d updated, %d unchanged.', $a['created'], $a['renamed'], $a['unchanged'] ) );

		\WP_CLI::success( 'Vocabularies reconciled.' );
	}

	public function seed_pages( array $args, array $assoc ): void {
		if ( ! function_exists( 'update_field' ) ) {
			\WP_CLI::error( 'ACF Pro must be active to seed pages.' );
		}

		$force = isset( $assoc['force'] );
		$dir   = get_post_type_archive_link( \Oria\Core\PostTypes\LISTING ) ?: home_url( '/directory/' );

		$home_id    = $this->ensure_page( 'Home', 'home', $force, $this->home_sections( $dir ) );
		$claim_id   = $this->ensure_page( 'For practitioners', 'claim', $force, $this->claim_sections() );
		$about_id   = $this->ensure_page( 'About', 'about', $force, $this->about_sections() );
		$journal_id = $this->ensure_page( 'Journal', 'journal', $force, null );

		update_option( 'show_on_front', 'page' );
		update_option( 'page_on_front', $home_id );
		update_option( 'page_for_posts', $journal_id );

		\WP_CLI::success( sprintf(
			'Pages ready: Home #%d (front), For practitioners #%d, About #%d, Journal #%d (posts page).',
			$home_id, $claim_id, $about_id, $journal_id
		) );
	}

	private function ensure_page( string $title, string $slug, bool $force, ?array $sections ): int {
		$existing = get_page_by_path( $slug, OBJECT, 'page' );
		if ( $existing instanceof \WP_Post ) {
			$id = $existing->ID;
			if ( ! $force ) {
				\WP_CLI::log( "  exists, left alone: {$title} (#{$id}) — use --force to overwrite its sections" );
				return $id;
			}
			\WP_CLI::log( "  overwriting sections: {$title} (#{$id})" );
		} else {
			$id = (int) wp_insert_post(
				array(
					'post_type'   => 'page',
					'post_status' => 'publish',
					'post_title'  => $title,
					'post_name'   => $slug,
				)
			);
			\WP_CLI::log( "  created: {$title} (#{$id})" );
		}

		if ( null !== $sections ) {
			update_field( 'sections', $sections, $id );
		}
		return $id;
	}

	private function home_sections( string $dir ): array {
		return array(
			array(
				'acf_fc_layout' => 'hero',
				'eyebrow'       => 'Perth & Western Australia',
				'heading'       => 'Find a quieter hour, close to home.',
				'sub'           => 'Meditation, breathwork, mindfulness and slow movement across the Perth metro — searchable by practice and by suburb, from Joondalup to Mandurah.',
				'show_search'   => 1,
				'show_trust'    => 1,
				'trust_text'    => 'Across every practice listed here. We read the reviews before a listing goes live.',
				'tags'          => array(
					array( 'label' => 'Beginner classes', 'url' => $dir . '?cat=meditation' ),
					array( 'label' => 'Free sessions', 'url' => $dir . '?q=free' ),
					array( 'label' => 'Before work', 'url' => $dir . '?q=morning' ),
					array( 'label' => 'Breathwork', 'url' => $dir . '?cat=breathwork' ),
					array( 'label' => 'Day retreats', 'url' => $dir . '?cat=retreats' ),
				),
			),
			array(
				'acf_fc_layout' => 'starting_soon',
				'sessions'      => array(
					array( 'time_label' => 'Today 5.45pm', 'name' => 'Salt Air Studio', 'suburb' => 'Cottesloe', 'url' => $dir ),
					array( 'time_label' => 'Wed 6.15pm', 'name' => 'Gong House', 'suburb' => 'Mount Lawley', 'url' => $dir ),
					array( 'time_label' => 'Thu 7.00pm', 'name' => 'The Long Exhale', 'suburb' => 'Fremantle', 'url' => $dir ),
					array( 'time_label' => 'Sat 7.00am', 'name' => 'Forest Sits', 'suburb' => 'Kalamunda', 'url' => $dir ),
				),
			),
			array(
				'acf_fc_layout' => 'practice_tiles',
				'eyebrow'       => 'Browse by practice',
				'heading'       => "Start with what you're actually looking for.",
				'aside'         => 'Six ways in. Every listing is filed under one main practice and any others it genuinely offers, so a yoga studio that runs a weekly sit shows up in both.',
			),
			array(
				'acf_fc_layout' => 'stillness_map',
				'eyebrow'       => 'Where Perth practises',
				'heading'       => 'Eight corners of the city, one map.',
				'aside'         => "Hover a region to see what's there. Every dot is sized by how many practices we've listed in that part of town.",
			),
			array(
				'acf_fc_layout' => 'featured_listings',
				'eyebrow'       => 'Featured this month',
				'heading'       => 'Three places worth the drive.',
				'aside'         => 'Featured spots are paid placements, and we say so. They still have to meet the same listing standards as everyone else.',
			),
			array(
				'acf_fc_layout' => 'feature_split',
				'background'    => 'sand',
				'eyebrow'       => 'How this works',
				'heading'       => 'A directory you can trust at 10pm on a Tuesday.',
				'intro'         => "The platform that helps Perth discover what to do to feel better, meet people, and look after themselves. Most local directories are a wall of dead phone numbers; this one is built the slow way — every listing written by a person, every detail checked against the practitioner's own site before it goes live.",
				'rows'          => array(
					array( 'title' => "Checked before it's published", 'text' => "Address, class times and prices verified against the practitioner's own website or a phone call. Anything we can't confirm, we don't print." ),
					array( 'title' => 'Real timetables, not "contact for details"', 'text' => 'When a practice runs a class, you can see the day and the time on the profile.' ),
					array( 'title' => 'Filed by suburb, not by "Perth"', 'text' => 'Bicton and Balcatta are an hour apart. Every listing carries its actual suburb and region, and the search knows the difference.' ),
				),
			),
			array(
				'acf_fc_layout'   => 'steps_split',
				'eyebrow'         => 'For practitioners',
				'heading'         => "You're probably already listed.",
				'intro'           => "We've built profiles for meditation teachers, studios and coaches across the metro from public information. Claiming yours is free, takes about four minutes, and puts you in control of what it says.",
				'primary_label'   => 'Claim your listing',
				'primary_url'     => home_url( '/claim/' ),
				'secondary_label' => 'What it costs',
				'secondary_url'   => home_url( '/claim/#pricing' ),
				'steps'           => array(
					array( 'title' => 'Find your profile', 'text' => "Search your business name. If it's here, it'll be marked unclaimed." ),
					array( 'title' => "Verify it's you", 'text' => 'We email the address on your website, or call the number on your Google listing.' ),
					array( 'title' => 'Fix anything we got wrong', 'text' => 'Edit your description, timetable, prices and photos. Changes go live the same day.' ),
					array( 'title' => 'Get the enquiries', 'text' => 'Every message and click through to your site is tracked, and you see the numbers. Free, permanently.' ),
				),
			),
			array(
				'acf_fc_layout' => 'journal_latest',
				'background'    => 'sand',
				'eyebrow'       => 'The Journal',
				'heading'       => 'Local guides, written locally.',
			),
			array(
				'acf_fc_layout' => 'faq',
				'eyebrow'       => 'Common questions',
				'heading'       => 'Before you ask.',
				'items'         => array(
					array( 'question' => 'Does it cost anything to use?', 'answer' => 'No. Browsing, searching and contacting a practice is free and always will be. Practitioners can also claim and run a full listing for free — we only charge for optional featured placement.' ),
					array( 'question' => 'How do listings get here in the first place?', 'answer' => 'We build them from publicly available information and write an original description. The listing is marked unclaimed until the practitioner takes it over.' ),
					array( 'question' => "I'm listed and I'd rather not be.", 'answer' => "Email us and we'll remove the listing the same day, no questions and no follow-up." ),
					array( 'question' => 'Is this only for Perth?', 'answer' => 'For now, yes — Perth and the surrounding WA metro. The platform is built to add other cities later.' ),
				),
			),
			array(
				'acf_fc_layout' => 'reviews',
				'background'    => 'sand',
				'eyebrow'       => 'From people who used it',
				'heading'       => 'Found a class, turned up, went back.',
				'items'         => array(
					array( 'title' => 'Finally, times that exist', 'quote' => "I'd been trying to find a beginners' class for months and kept landing on studios that hadn't updated since 2019. Found a 6.30am sit ten minutes from work, and I've been every week since.", 'name' => 'Jess M.', 'where' => 'Highgate' ),
					array( 'title' => 'Worth it for the map alone', 'quote' => "We'd just moved to Perth and had no idea what was near us. Clicked the hills on the map, found a sound bath fifteen minutes up the road.", 'name' => 'Tom & Steph', 'where' => 'Darlington' ),
					array( 'title' => 'Enquiries in the first fortnight', 'quote' => 'I claimed my listing not expecting much. Six enquiries in two weeks, and every one of them mentioned they found me here.', 'name' => 'Rachel K.', 'where' => 'Breathwork facilitator, Bicton' ),
				),
			),
			array(
				'acf_fc_layout'   => 'cta',
				'eyebrow'         => 'Free to browse, free to be listed',
				'heading'         => "There's a room near you with the lights already low.",
				'primary_label'   => 'Find a practice',
				'primary_url'     => $dir,
				'secondary_label' => "I'm a practitioner",
				'secondary_url'   => home_url( '/claim/' ),
			),
		);
	}

	private function claim_sections(): array {
		return array(
			array(
				'acf_fc_layout' => 'page_head',
				'eyebrow'       => 'For practitioners',
				'heading'       => 'Claim your listing. It stays free.',
				'lede'          => "We've written profiles for practices across Perth from public information. Take yours over, fix what we got wrong, and keep the enquiries.",
			),
			array(
				'acf_fc_layout' => 'steps_split',
				'eyebrow'       => 'How claiming works',
				'heading'       => 'Four steps, four minutes.',
				'intro'         => 'No documents, no forms to print. We verify against details you already publish.',
				'steps'         => array(
					array( 'title' => 'Find your profile', 'text' => 'Search your business name in the directory. Unclaimed profiles carry a grey badge.' ),
					array( 'title' => "Verify it's you", 'text' => 'We email the address published on your own website, or call the number on your Google Business Profile.' ),
					array( 'title' => 'Fix anything we got wrong', 'text' => 'Description, timetable, prices, photos, booking link. Edits are reviewed the same working day.' ),
					array( 'title' => 'Watch the enquiries', 'text' => 'Every enquiry, phone tap and click through to your site is counted.' ),
				),
			),
			array(
				'acf_fc_layout' => 'form_card',
				'heading'       => 'Start a claim',
				'sub'           => "Four minutes. We'll come back to you within one working day.",
				'form'          => '<p><em>Paste your form plugin\'s shortcode into this section to activate the claim form.</em></p>',
			),
			array(
				'acf_fc_layout' => 'pricing',
				'background'    => 'sand',
				'eyebrow'       => 'What it costs',
				'heading'       => 'Free to be listed. Paid to make it yours.',
				'sub'           => 'Every practice gets a full free listing, built from public information. Claiming unlocks control; Featured grows your reach. Cancel any time — the listing simply returns to its free form.',
				'tiers'         => array(
					array( 'tier_label' => 'Free listing', 'amount' => '$0', 'suffix' => '/ forever', 'blurb' => 'Built and maintained by us.', 'features' => "Full profile with services and timetable\nGoogle rating and reviews\nFound in every search and category page\nCorrections fixed within a day", 'cta_label' => 'Find your listing', 'cta_url' => '/directory/', 'style' => 'default' ),
					array( 'tier_label' => 'Claimed', 'amount' => '$29', 'suffix' => '/ month', 'blurb' => 'Own your profile.', 'features' => "Edit every detail yourself\nVerified badge and date\nUp to 4 gallery photos\nSpecial offers on your profile and cards\nOpening hours and social links\nPerformance analytics", 'cta_label' => 'Claim your listing', 'cta_url' => '#claimform', 'style' => 'now' ),
					array( 'tier_label' => 'Featured', 'amount' => '$79', 'suffix' => '/ month', 'blurb' => 'Grow with the directory.', 'features' => "Everything in Claimed\nRun workshops & events — photos and booking links\nUnlimited gallery photos\nGold Featured badge\nPriority placement in directory and categories\nFeatured spots on the home and workshops pages", 'cta_label' => 'Claim, then upgrade', 'cta_url' => '#claimform', 'style' => 'default' ),
				),
			),
			array(
				'acf_fc_layout' => 'feature_list',
				'eyebrow'       => 'Listing standards',
				'heading'       => "What we will and won't publish",
				'items'         => array(
					array( 'title' => 'Original descriptions only', 'text' => 'We write our own copy and so should you. Nothing lifted from another site.' ),
					array( 'title' => 'No medical claims', 'text' => "You can describe what a practice involves. You can't claim it treats or cures a condition." ),
					array( 'title' => 'Prices stated plainly', 'text' => 'If a class costs $28, the profile says $28.' ),
					array( 'title' => 'Featured is always labelled', 'text' => 'Paid placement is marked on every card it appears on.' ),
					array( 'title' => 'Remove any time', 'text' => 'Ask and your listing comes down the same day.' ),
				),
			),
			array(
				'acf_fc_layout' => 'faq',
				'background'    => 'sand',
				'eyebrow'       => 'Practitioner questions',
				'heading'       => 'Before you ask.',
				'items'         => array(
					array( 'question' => 'Why is my practice listed without me agreeing to it?', 'answer' => "Because a directory with nothing in it helps nobody. We build listings from information you've already made public, write an original description, and mark it unclaimed until you take it over. One email removes it the same day." ),
					array( 'question' => 'Will you ever charge for the free listing?', 'answer' => 'No. The free listing stays free. Paid tiers only ever add placement and presentation on top.' ),
					array( 'question' => 'Do you take a cut of bookings?', 'answer' => 'No. We link out to your own booking system and take nothing.' ),
				),
			),
		);
	}

	private function about_sections(): array {
		return array(
			array(
				'acf_fc_layout' => 'page_head',
				'eyebrow'       => 'About',
				'heading'       => 'A directory built the slow way.',
				'lede'          => 'Oria Haven is an independent guide to meditation and wellness practice in Perth. No franchise, no aggregator, no scraped content.',
			),
			array(
				'acf_fc_layout' => 'prose',
				'content'       => "<p>This started with a genuinely annoying evening. Trying to find a beginners' meditation class in Perth meant six tabs, three studios that had closed, two timetables from 2019 and a directory whose top result was a physiotherapist in Adelaide.</p><p>Perth has a real practice community — small, mostly sole traders, largely word of mouth. What it doesn't have is one honest place to find them. So we're building one: every listing written by a person, every detail checked, and the paid placement labelled as paid placement.</p>",
			),
			array(
				'acf_fc_layout' => 'roadmap',
				'heading'       => "Where we're up to",
				'phases'        => array(
					array( 'title' => 'Seeding the directory', 'text' => 'Building 80–150 checked listings across every category and region.', 'current' => 1 ),
					array( 'title' => 'Opening claims', 'text' => 'Inviting practitioners to take over their profiles, free.', 'current' => 0 ),
					array( 'title' => 'Featured listings', 'text' => 'Optional paid placement, once we can show the traffic that justifies it.', 'current' => 0 ),
					array( 'title' => 'Events and matching', 'text' => 'A proper events calendar, then "get matched" enquiries.', 'current' => 0 ),
				),
			),
			array(
				'acf_fc_layout' => 'card_grid',
				'background'    => 'sand',
				'eyebrow'       => 'What we check',
				'heading'       => 'Before a listing goes live',
				'cards'         => array(
					array( 'title' => 'The practice still exists', 'text' => 'Trading in the last three months, with a working phone or email.' ),
					array( 'title' => 'The address is right', 'text' => 'Street address and suburb confirmed, not just "Perth".' ),
					array( 'title' => 'The timetable is current', 'text' => "Days and times taken from the practitioner's own source, with the date we checked." ),
					array( 'title' => 'The price is stated', 'text' => 'A real number, or "free", or "by donation".' ),
					array( 'title' => 'The description is ours', 'text' => 'Written here, in our words.' ),
				),
			),
			array(
				'acf_fc_layout' => 'contact',
				'eyebrow'       => 'Contact',
				'heading'       => 'Suggest a practice, report a listing, or just say hello.',
				'intro'         => 'We read everything. Removal requests are actioned the same day; everything else usually gets a reply within one working day.',
				'email'         => 'hello@oriahaven.com.au',
				'form'          => '<p><em>Paste your form plugin\'s shortcode into this section to activate the contact form.</em></p>',
			),
		);
	}

	/**
	 * Create the claim and contact forms in WPForms and wire their
	 * shortcodes into the Claim and About pages' form sections.
	 *
	 * ## OPTIONS
	 *
	 * [--force]
	 * : Replace the form embed even if a section already has one.
	 *
	 * ## EXAMPLES
	 *
	 *     wp oria seed_forms
	 */
	public function seed_forms( array $args, array $assoc ): void {
		if ( ! function_exists( 'wpforms' ) ) {
			\WP_CLI::error( 'WPForms must be active first.' );
		}
		if ( ! function_exists( 'update_field' ) ) {
			\WP_CLI::error( 'ACF Pro must be active first.' );
		}

		$force = isset( $assoc['force'] );

		$claim_form_id   = $this->ensure_wpform( 'Claim a listing', $this->claim_form_fields(), array(
			'submit_text'  => 'Start my claim',
			'confirmation' => 'Claim started. Check the inbox on your website\'s contact address — the verification link is on its way.',
		) );
		$contact_form_id = $this->ensure_wpform( 'Contact', $this->contact_form_fields(), array(
			'submit_text'  => 'Send message',
			'confirmation' => 'Thanks — message received. We\'ll come back to you within a working day.',
		) );

		$this->inject_form( 'claim', 'form_card', $claim_form_id, $force );
		$this->inject_form( 'about', 'contact', $contact_form_id, $force );

		\WP_CLI::success( sprintf( 'Forms ready: Claim #%d, Contact #%d.', $claim_form_id, $contact_form_id ) );
	}

	/** Create a WPForms form if a same-titled one does not already exist. */
	private function ensure_wpform( string $title, array $fields, array $opts ): int {
		$existing = get_posts(
			array(
				'post_type'      => 'wpforms',
				'title'          => $title,
				'posts_per_page' => 1,
				'post_status'    => 'publish',
			)
		);
		if ( $existing ) {
			\WP_CLI::log( "  form exists: {$title} (#{$existing[0]->ID})" );
			return $existing[0]->ID;
		}

		$form_id = (int) wp_insert_post(
			array(
				'post_type'   => 'wpforms',
				'post_status' => 'publish',
				'post_title'  => $title,
			)
		);

		// WPForms keeps the whole definition as JSON in post_content, and the
		// JSON must carry its own post ID — hence insert first, then fill.
		$data = array(
			'id'       => (string) $form_id,
			'field_id' => (string) ( count( $fields ) + 1 ),
			'fields'   => $fields,
			'settings' => array(
				'form_title'             => $title,
				'form_desc'              => '',
				'submit_text'            => (string) $opts['submit_text'],
				'submit_text_processing' => 'Sending…',
				'form_class'             => 'oria-form',
				'submit_class'           => '',
				'antispam_v3'            => '1',
				'ajax_submit'            => '1',
				'notification_enable'    => '1',
				'notifications'          => array(
					'1' => array(
						'notification_name' => 'Default notification',
						'email'             => '{admin_email}',
						'subject'           => "New entry: {$title}",
						'sender_name'       => get_bloginfo( 'name' ),
						'sender_address'    => '{admin_email}',
						'replyto'           => '{field_id="2"}',
						'message'           => '{all_fields}',
					),
				),
				'confirmations'          => array(
					'1' => array(
						'type'           => 'message',
						'message'        => '<p>' . (string) $opts['confirmation'] . '</p>',
						'message_scroll' => '1',
					),
				),
			),
			'meta'     => array( 'template' => 'blank' ),
		);

		wp_update_post(
			array(
				'ID'           => $form_id,
				'post_content' => wp_slash( (string) wp_json_encode( $data ) ),
			)
		);

		\WP_CLI::log( "  created form: {$title} (#{$form_id})" );
		return $form_id;
	}

	/** The designed claim form. Lite field set only — phone is a text field. */
	private function claim_form_fields(): array {
		return array(
			'0' => array( 'id' => '0', 'type' => 'text', 'label' => 'Business or practice name', 'required' => '1', 'size' => 'large', 'placeholder' => 'e.g. Still Point Meditation Rooms' ),
			'1' => array( 'id' => '1', 'type' => 'name', 'format' => 'simple', 'label' => 'Your name', 'required' => '1', 'size' => 'large' ),
			'2' => array( 'id' => '2', 'type' => 'email', 'label' => 'Email', 'required' => '1', 'size' => 'large', 'placeholder' => 'you@yourpractice.com.au' ),
			'3' => array( 'id' => '3', 'type' => 'text', 'label' => 'Phone', 'size' => 'large', 'placeholder' => 'Optional' ),
			'4' => array( 'id' => '4', 'type' => 'text', 'label' => 'Suburb', 'required' => '1', 'size' => 'large', 'placeholder' => 'Northbridge' ),
			'5' => array( 'id' => '5', 'type' => 'text', 'label' => 'Website or Instagram', 'size' => 'large', 'description' => 'We use this to verify it\'s you.' ),
			'6' => array( 'id' => '6', 'type' => 'textarea', 'label' => 'Anything we should fix straight away?', 'size' => 'medium', 'placeholder' => 'e.g. the Tuesday class moved to 7pm, and we no longer run the Saturday session' ),
			'7' => array(
				'id'       => '7',
				'type'     => 'checkbox',
				'label'    => 'Authorisation',
				'required' => '1',
				'choices'  => array(
					'1' => array( 'label' => 'I\'m authorised to manage this practice\'s information.', 'value' => '' ),
				),
			),
		);
	}

	/** The designed contact form. */
	private function contact_form_fields(): array {
		return array(
			'0' => array(
				'id'       => '0',
				'type'     => 'select',
				'label'    => 'What\'s this about?',
				'required' => '1',
				'size'     => 'large',
				'choices'  => array(
					'1' => array( 'label' => 'Suggest a practice we\'ve missed', 'value' => '' ),
					'2' => array( 'label' => 'Correct a listing', 'value' => '' ),
					'3' => array( 'label' => 'Remove my listing', 'value' => '' ),
					'4' => array( 'label' => 'Submit an event', 'value' => '' ),
					'5' => array( 'label' => 'Something else', 'value' => '' ),
				),
			),
			'1' => array( 'id' => '1', 'type' => 'name', 'format' => 'simple', 'label' => 'Your name', 'required' => '1', 'size' => 'large' ),
			'2' => array( 'id' => '2', 'type' => 'email', 'label' => 'Email', 'required' => '1', 'size' => 'large' ),
			'3' => array( 'id' => '3', 'type' => 'textarea', 'label' => 'Message', 'required' => '1', 'size' => 'medium', 'placeholder' => 'Tell us what you\'re after' ),
		);
	}

	/** Put a form shortcode into the named section of a page. */
	private function inject_form( string $page_slug, string $layout, int $form_id, bool $force ): void {
		$page = get_page_by_path( $page_slug, OBJECT, 'page' );
		if ( ! $page instanceof \WP_Post ) {
			\WP_CLI::warning( "page /{$page_slug}/ not found — run `wp oria seed_pages` first, then re-run seed_forms." );
			return;
		}

		$sections = get_field( 'sections', $page->ID );
		if ( ! is_array( $sections ) ) {
			\WP_CLI::warning( "page /{$page_slug}/ has no sections — run `wp oria seed_pages` first." );
			return;
		}

		$shortcode = sprintf( '[wpforms id="%d" title="false"]', $form_id );
		$changed   = false;

		foreach ( $sections as &$section ) {
			if ( ( $section['acf_fc_layout'] ?? '' ) !== $layout ) {
				continue;
			}
			$current = (string) ( $section['form'] ?? '' );
			$is_placeholder = '' === trim( wp_strip_all_tags( $current ) )
				|| str_contains( $current, 'Paste your form' );
			if ( $is_placeholder || $force ) {
				$section['form'] = $shortcode;
				$changed         = true;
			} else {
				\WP_CLI::log( "  /{$page_slug}/ {$layout}: already has a form embed — use --force to replace." );
			}
			break;
		}
		unset( $section );

		if ( $changed ) {
			update_field( 'sections', $sections, $page->ID );
			\WP_CLI::log( "  embedded {$shortcode} on /{$page_slug}/." );
		}
	}

	/**
	 * Create five journal articles with featured images so the Journal page
	 * and the home-page journal section have real content.
	 *
	 * ## EXAMPLES
	 *
	 *     wp oria seed_journal
	 */
	public function seed_journal( array $args, array $assoc ): void {
		require_once ABSPATH . 'wp-admin/includes/image.php';

		$made = 0;
		foreach ( $this->journal_posts() as $row ) {
			if ( get_page_by_path( $row['slug'], OBJECT, 'post' ) ) {
				\WP_CLI::log( "  exists: {$row['title']}" );
				continue;
			}

			$cat_id = $this->ensure_category( $row['category'] );

			$post_id = (int) wp_insert_post(
				array(
					'post_type'     => 'post',
					'post_status'   => 'publish',
					'post_title'    => $row['title'],
					'post_name'     => $row['slug'],
					'post_excerpt'  => $row['excerpt'],
					'post_content'  => $row['content'],
					'post_date'     => gmdate( 'Y-m-d 09:00:00', strtotime( $row['when'] ) ),
					'post_category' => array( $cat_id ),
				)
			);

			$thumb = $this->attachment_from_theme( $row['image'], $row['title'] );
			if ( $thumb ) {
				set_post_thumbnail( $post_id, $thumb );
			}
			\WP_CLI::log( "  created: {$row['title']} (#{$post_id})" );
			$made++;
		}

		// The sample post that ships with WordPress has no place on a launch site.
		$hello = get_page_by_path( 'hello-world', OBJECT, 'post' );
		if ( $hello instanceof \WP_Post && 'trash' !== $hello->post_status ) {
			wp_trash_post( $hello->ID );
			\WP_CLI::log( '  trashed the "Hello world!" sample post' );
		}

		\WP_CLI::success( "Journal ready: {$made} new article(s)." );
	}

	private function ensure_category( string $name ): int {
		$existing = term_exists( $name, 'category' );
		if ( $existing ) {
			return (int) ( is_array( $existing ) ? $existing['term_id'] : $existing );
		}
		$made = wp_insert_term( $name, 'category' );
		return is_wp_error( $made ) ? 0 : (int) $made['term_id'];
	}

	/** Copy a theme image into the media library once; reuse it after that. */
	private function attachment_from_theme( string $asset, string $title ): int {
		$existing = get_posts(
			array(
				'post_type'      => 'attachment',
				'post_status'    => 'inherit',
				'posts_per_page' => 1,
				'meta_key'       => '_oria_source_asset',
				'meta_value'     => $asset,
			)
		);
		if ( $existing ) {
			return $existing[0]->ID;
		}

		$src = get_template_directory() . '/assets/img/' . $asset;
		if ( ! file_exists( $src ) ) {
			\WP_CLI::warning( "  theme asset missing: {$asset}" );
			return 0;
		}

		$upload = wp_upload_bits( $asset, null, (string) file_get_contents( $src ) );
		if ( ! empty( $upload['error'] ) ) {
			\WP_CLI::warning( "  upload failed: {$upload['error']}" );
			return 0;
		}

		$attach_id = (int) wp_insert_attachment(
			array(
				'post_mime_type' => 'image/webp',
				'post_title'     => $title,
				'post_status'    => 'inherit',
			),
			$upload['file']
		);
		wp_update_attachment_metadata( $attach_id, wp_generate_attachment_metadata( $attach_id, $upload['file'] ) );
		update_post_meta( $attach_id, '_oria_source_asset', $asset );
		return $attach_id;
	}

	/** @return array<int, array<string, string>> */
	private function journal_posts(): array {
		$p  = static fn( string $text ): string => "<!-- wp:paragraph --><p>{$text}</p><!-- /wp:paragraph -->";
		$h  = static fn( string $text ): string => "<!-- wp:heading --><h2 class=\"wp-block-heading\">{$text}</h2><!-- /wp:heading -->";

		return array(
			array(
				'title'    => 'Your first meditation class: what actually happens',
				'slug'     => 'your-first-meditation-class',
				'category' => 'Beginners',
				'image'    => 'scene-room.webp',
				'when'     => '-3 days',
				'excerpt'  => 'Nobody tests you, nobody makes you chant, and you are allowed to fidget. A plain account of the first hour, for anyone who has been putting it off.',
				'content'  =>
					$p( 'Most people put off their first meditation class for the same reason: not knowing what happens in the room. So here it is, plainly, based on how beginner sessions across Perth actually run.' ) .
					$p( 'You arrive a few minutes early. Someone points you to a shelf for your shoes and a spot for your bag. There are cushions and usually chairs — nobody minds which you take, and switching halfway through is normal. Most rooms cap a beginner class somewhere under twenty people, and at least a third of them will be as new as you are.' ) .
					$h( 'The sitting itself' ) .
					$p( 'A teacher will usually talk for a few minutes about posture: sit tall without straining, hands resting anywhere comfortable, eyes closed or lowered. Then the room goes quiet, and the teacher guides attention — commonly to the breath — with short prompts spaced further and further apart. A first sit is typically fifteen to twenty-five minutes, not the hour people fear.' ) .
					$p( 'Your mind will wander constantly. That is not failing at meditation; noticing the wandering and returning is the entire exercise. Nobody can see your thoughts, nobody checks your progress, and there is no point where you are asked to share anything.' ) .
					$h( 'What it costs and what to bring' ) .
					$p( 'Drop-in sittings around Perth generally run $12–$25, with several groups free or by donation. Bring nothing. Wear whatever you can sit comfortably in — people come in work clothes to evening classes, and the 6am crowd comes in whatever they slept in, more or less.' ) .
					$p( 'If the first class does not land, try a different room before deciding meditation is not for you. Teachers vary more than the practice does. The directory lists every beginner-friendly sitting in the metro, filterable by suburb, price and time of day.' ),
			),
			array(
				'title'    => 'Where to sit outdoors in Perth, season by season',
				'slug'     => 'where-to-sit-outdoors-perth',
				'category' => 'Guides',
				'image'    => 'scene-canopy.webp',
				'when'     => '-10 days',
				'excerpt'  => 'Perth is one of the easiest cities in the world to practise outside — if you follow the weather rather than fight it. A year of good sitting spots.',
				'content'  =>
					$p( 'Perth gives you more sittable mornings a year than almost any capital in the country, but the good spots move with the seasons. Here is how a year outdoors tends to work.' ) .
					$h( 'Summer: go early, go coastal' ) .
					$p( 'From December to February the only comfortable window is dawn. The coastal parks — the dune benches above Cottesloe, the northern end of City Beach — catch a sea breeze even in a heatwave, and a 5.30am sit by the water is the best-kept routine in the city. By 8am it is over; do not fight it.' ) .
					$h( 'Autumn: the river' ) .
					$p( 'March to May is the river\'s season. The foreshores at Applecross, Bicton and Matilda Bay are still, warm and quiet on weekday mornings, and the light through the sheoaks in April is worth the trip on its own.' ) .
					$h( 'Winter: into the hills' ) .
					$p( 'Perth winter is rain in bursts with bright gaps between, which suits walking meditation better than sitting. The jarrah trails through Kalamunda and Mundaring are at their best from June to August — mist in the valleys, nobody around, and the smell of wet eucalypt doing half the work for you. Take something waterproof to sit on and treat the showers as part of it.' ) .
					$h( 'Spring: anywhere, honestly' ) .
					$p( 'September to November is the season the outdoor groups build their year around. Kings Park before the tourists arrive, Hyde Park under the plane trees, Lake Monger with the morning birds — everything works. Several Perth groups run their outdoor programs only in these months; the directory marks which listings sit outside and where they meet.' ),
			),
			array(
				'title'    => 'Eleven free and by-donation sessions across the metro',
				'slug'     => 'free-sessions-perth-metro',
				'category' => 'Roundups',
				'image'    => 'scene-hills.webp',
				'when'     => '-17 days',
				'excerpt'  => 'A regular practice should not depend on a studio budget. The metro\'s genuinely free sittings, from community halls to volunteer-run sanghas.',
				'content'  =>
					$p( 'A weekly class habit can quietly become a gym membership, and it does not need to. Perth has a healthy layer of free and by-donation practice that most people never find, because free groups rarely pay for advertising.' ) .
					$p( 'They fall into three kinds. Volunteer-run sitting groups — often in the Insight or Zen traditions — meet weekly in community rooms, ask for nothing, and welcome newcomers with a short orientation. Community-centre classes are council-subsidised and either free or a gold-coin donation. And several commercial studios run one by-donation session a week as their community contribution, which is often the same class the studio charges for on other nights.' ) .
					$h( 'What by-donation actually means' ) .
					$p( 'Give what the session was worth to you, including nothing if that is where things are this month. The groups mean it. If you can afford a few dollars, it keeps the hall hired; if you cannot, your being there is genuinely counted as the contribution. Nobody watches the bowl.' ) .
					$h( 'A note on quality' ) .
					$p( 'Free does not mean lesser here. Some of the most experienced teachers in the metro sit with volunteer groups, and the silent hours are often more serious than anything on a studio timetable. The trade-off is usually comfort — a community-hall floor rather than a heated studio — and a fixed weekly time rather than a full timetable.' ) .
					$p( 'The directory keeps a live filter for this: choose "Free or by donation" under price and the current list is yours, suburb by suburb. At the time of writing it runs to eleven regular sessions across the metro, from East Perth to Rockingham.' ),
			),
			array(
				'title'    => 'Breathwork, plainly: what the different styles actually are',
				'slug'     => 'breathwork-styles-explained',
				'category' => 'Beginners',
				'image'    => 'scene-scrub.webp',
				'when'     => '-24 days',
				'excerpt'  => 'Conscious connected, box breathing, pranayama, cold-exposure work — the names are confusing and the differences matter. A jargon-free guide.',
				'content'  =>
					$p( 'Breathwork has a naming problem. The same word covers a two-minute calming exercise and a ninety-minute session that can be genuinely intense, and studios rarely explain which end of the spectrum a class sits on. Here is the map.' ) .
					$h( 'The calming end' ) .
					$p( 'Box breathing, extended exhales and most of what gets called pranayama in a yoga class are regulation techniques: slow, structured patterns that settle the nervous system. They are gentle, you stay entirely present, and they are what most "breath and stretch" or lunchtime sessions teach. If you are new, start here.' ) .
					$h( 'The middle: pranayama as its own practice' ) .
					$p( 'Taught properly, pranayama is a discipline with decades of depth — breath ratios, retentions, alternate-nostril work. Classes are methodical rather than intense, and teachers who specialise in it tend to sequence carefully over weeks. It rewards regular attendance more than a big one-off session.' ) .
					$h( 'The strong end: conscious connected breathing' ) .
					$p( 'Sessions sold as breathwork journeys, holotropic-style sessions or conscious connected breathing use continuous, fuller breathing for an extended period, usually lying down with music. The experience can be physical and emotional — tingling, temperature shifts, sometimes strong feelings surfacing. Good facilitators screen for contraindications (pregnancy and some heart and blood-pressure conditions among them), keep groups small, and talk you through it beforehand. Ask how a facilitator handles all three if their listing does not say.' ) .
					$h( 'Cold work' ) .
					$p( 'The breath-and-cold sessions on the coast pair short breathing protocols with ocean or ice immersion. The breathing is the easy half. Go with a group rather than alone, and treat the winter sessions as the advanced version they are.' ) .
					$p( 'Every breathwork listing in the directory names its style on the profile, so you can match the class to the experience you actually want.' ),
			),
			array(
				'title'    => 'Day retreats within a two-hour drive of Perth',
				'slug'     => 'day-retreats-near-perth',
				'category' => 'Guides',
				'image'    => 'scene-still-water.webp',
				'when'     => '-31 days',
				'excerpt'  => 'A full day of practice does something a weekly hour cannot. Where to find single-day retreats from the hills to the coast, and how to choose one.',
				'content'  =>
					$p( 'There is a version of practice that only shows up when you give it a whole day: the noise takes hours to settle, and what is left afterwards is the reason people keep going back. You do not need a week in Bali for it. Within two hours of Perth there is a quiet circuit of single-day retreats running most of the year.' ) .
					$h( 'What a day retreat looks like' ) .
					$p( 'The common shape: arrive by 9am, phones into a box, and the day alternates sittings with walking meditation and a shared meal, finishing mid-afternoon. Most run in silence after the opening talk. First-timers are normal and expected — the schedule carries you, and there is nothing to get right.' ) .
					$h( 'Where they happen' ) .
					$p( 'The hills carry most of them: properties around Kalamunda, Darlington and Mundaring with a hall, a veranda and bush on all sides. South of the city, the retreats near Mandurah trade the forest for still water, which suits walking practice beautifully. A few coastal operators run half-day resets — a shorter format that pairs a long sitting with breathwork and time on the sand — which make a good first step if a full day sounds like a lot.' ) .
					$h( 'Choosing and booking' ) .
					$p( 'Look at three things: the teacher\'s background (profiles in the directory list credentials), whether the day is silent or guided, and what is included — most cover the meal, some ask you to bring your own. Prices around Perth generally run $60 for community-run half days to about $200 for a fully catered day with an experienced teacher. They book out further ahead than weekly classes, especially in spring; the events page lists upcoming dates as operators confirm them.' ),
			),
		);
	}

	/**
	 * Delete the listings named in a seed file. The inverse of import:
	 * only posts whose slug matches an id in the file are touched, so real
	 * listings imported from other files are safe.
	 *
	 * ## OPTIONS
	 *
	 * <file>
	 * : The JSON file whose listings should be removed.
	 *
	 * [--dry-run]
	 * : Report what would be deleted without deleting anything.
	 *
	 * ## EXAMPLES
	 *
	 *     wp oria remove wp-content/plugins/oria-core/seed-listings.json --dry-run
	 *     wp oria remove wp-content/plugins/oria-core/seed-listings.json
	 */
	public function remove( array $args, array $assoc ): void {
		list( $file ) = $args;
		$dry = isset( $assoc['dry-run'] );

		if ( ! file_exists( $file ) ) {
			\WP_CLI::error( "File not found: {$file}" );
		}
		$data = json_decode( (string) file_get_contents( $file ), true );
		if ( ! is_array( $data ) || empty( $data['listings'] ) ) {
			\WP_CLI::error( 'No listings found in that file.' );
		}

		$deleted = 0;
		$missing = 0;
		foreach ( $data['listings'] as $row ) {
			$slug = (string) ( $row['id'] ?? '' );
			$post = $slug ? get_page_by_path( $slug, OBJECT, \Oria\Core\PostTypes\LISTING ) : null;
			if ( ! $post instanceof \WP_Post ) {
				$missing++;
				continue;
			}
			if ( $dry ) {
				\WP_CLI::log( "  would delete: {$post->post_title} (#{$post->ID})" );
			} else {
				wp_delete_post( $post->ID, true );
				\WP_CLI::log( "  deleted: {$post->post_title} (#{$post->ID})" );
			}
			$deleted++;
		}

		$verb = $dry ? 'would delete' : 'deleted';
		\WP_CLI::success( "{$verb} {$deleted} listing(s); {$missing} from the file were not present." );
	}

	/**
	 * Create the specialty terms and tag every listing by keyword-matching
	 * its name, services and blurb. Deterministic and safe to re-run: each
	 * pass replaces a listing's specialty set rather than accumulating.
	 *
	 * Claimed and featured listings are skipped by default so an owner's own
	 * curation is never overwritten.
	 *
	 * ## OPTIONS
	 *
	 * [--include-claimed]
	 * : Also retag claimed and featured listings. DEV ONLY.
	 *
	 * [--dry-run]
	 * : Report what would be tagged without writing anything.
	 *
	 * ## EXAMPLES
	 *
	 *     wp oria seed_specialties --dry-run
	 *     wp oria seed_specialties
	 */
	/**
	 * Install the modality tile images that ship with the plugin.
	 *
	 * The pictures on the specialty cards cannot ride the normal deploy:
	 * uploads is gitignored, and the database only ever travels production
	 * to local. The chosen set lives in data/tiles instead, and this puts it
	 * into the media library and onto the terms.
	 *
	 * Safe to re-run. A modality that already has a tile is left alone
	 * unless --force is given, so a picture swapped by hand on the server
	 * is not quietly reverted by the next deploy.
	 *
	 * ## OPTIONS
	 *
	 * [--dry-run]
	 * : Report what would change and write nothing.
	 *
	 * [--force]
	 * : Replace tiles that are already set.
	 *
	 * ## EXAMPLES
	 *
	 *     wp oria tiles --dry-run
	 *     wp oria tiles
	 *
	 * @when after_wp_load
	 */
	public function tiles( array $args, array $assoc ): void {
		$dry   = isset( $assoc['dry-run'] );
		$force = isset( $assoc['force'] );

		$r = \Oria\Core\Tiles\install( $dry, $force );

		foreach ( $r['lines'] as $line ) {
			\WP_CLI::log( '  ' . $line );
		}

		$summary = sprintf(
			'%d set, %d left alone, %d problem(s).',
			$r['set'],
			$r['skipped'],
			$r['missing']
		);

		if ( $dry ) {
			\WP_CLI::success( 'Dry run. ' . $summary . ' Nothing written.' );
			return;
		}
		if ( $r['missing'] ) {
			\WP_CLI::warning( $summary );
			return;
		}
		\WP_CLI::success( $summary );
	}

	/**
	 * Today's claim-email run.
	 *
	 * Writes to listings people have actually visited -- five or more views,
	 * or a single click on their website or booking link. Never writes twice,
	 * never writes to a listing that opted out, was claimed, or has no email.
	 *
	 * The daily cron does exactly this. Run it by hand to see the list first.
	 *
	 * ## OPTIONS
	 *
	 * [--dry-run]
	 * : Show who would be written to and send nothing.
	 *
	 * ## EXAMPLES
	 *
	 *     wp oria invites --dry-run
	 *     wp oria invites
	 *
	 * @when after_wp_load
	 */
	public function invites( array $args, array $assoc ): void {
		$dry = isset( $assoc['dry-run'] );
		$r   = \Oria\Core\Invites\cron_run( $dry );

		if ( ! $r['armed'] ) {
			\WP_CLI::warning( 'Automatic sending is off. Arm it with: wp option update oria_invite_auto 1' );
		}
		\WP_CLI::log( sprintf( '  room today: %d of %d', $r['room'], \Oria\Core\Invites\DAY_PACE ) );
		foreach ( $r['rows'] as $row ) {
			\WP_CLI::log( sprintf(
				'  - %-38s views %-3d web %-2d book %-2d  %s',
				$row['name'],
				$row['view'],
				$row['web'],
				$row['book'],
				$row['email']
			) );
		}
		if ( isset( $r['left'] ) ) {
			\WP_CLI::log( sprintf( '  %d engaged listing(s) left in the queue', $r['left'] ) );
		}

		if ( $dry ) {
			\WP_CLI::success( sprintf( 'Dry run. %d would be written to. Nothing sent.', $r['picked'] ) );
			return;
		}
		if ( $r['failed'] ) {
			\WP_CLI::warning( sprintf( '%d sent, %d failed.', $r['sent'], $r['failed'] ) );
			return;
		}
		\WP_CLI::success( sprintf( '%d sent.', $r['sent'] ) );
	}

	public function seed_specialties( array $args, array $assoc ): void {
		$dry     = isset( $assoc['dry-run'] );
		$claimed = isset( $assoc['include-claimed'] );

		if ( ! $dry ) {
			\Oria\Core\Specialties\ensure_terms();
		}

		$posts = get_posts(
			array(
				'post_type'      => \Oria\Core\PostTypes\LISTING,
				'post_status'    => 'publish',
				'posts_per_page' => -1,
			)
		);

		$tagged  = 0;
		$skipped = 0;
		foreach ( $posts as $post ) {
			$status = (string) get_post_meta( $post->ID, 'claim_status', true );
			if ( ! $claimed && in_array( $status, array( 'claimed', 'featured' ), true ) ) {
				\WP_CLI::log( "  skipped ({$status}): {$post->post_title}" );
				$skipped++;
				continue;
			}
			$slugs = $dry
				? \Oria\Core\Specialties\tags_for( \Oria\Core\Specialties\haystack_for( $post->ID ) )
				: \Oria\Core\Specialties\tag_post( $post->ID );
			\WP_CLI::log( '  ' . $post->post_title . ' -> ' . ( $slugs ? implode( ', ', $slugs ) : '(none)' ) );
			$tagged++;
		}

		$verb = $dry ? 'would tag' : 'tagged';
		\WP_CLI::success( "{$verb} {$tagged} listing(s); {$skipped} skipped as claimed." );
	}

	/**
	 * Diagnose the Google Places integration for one listing: check the
	 * settings, clear the back-off and cache, make the lookup with the raw
	 * response shown, and repopulate the cache.
	 *
	 * ## OPTIONS
	 *
	 * <listing>
	 * : Listing slug or numeric ID.
	 *
	 * ## EXAMPLES
	 *
	 *     wp oria places_test beyond-rest-east-perth
	 */
	public function places_test( array $args, array $assoc ): void {
		list( $which ) = $args;

		// --- settings ---------------------------------------------------
		$server  = \Oria\Core\Places\server_key();
		$browser = \Oria\Core\Places\browser_key();
		$toggle  = function_exists( 'get_field' ) ? (bool) get_field( 'places_photos_enable', 'option' ) : false;

		$mask = static fn( string $k ): string => '' === $k ? 'MISSING' : substr( $k, 0, 6 ) . '…' . substr( $k, -4 ) . ' (' . strlen( $k ) . ' chars)';
		\WP_CLI::log( 'Server key:  ' . $mask( $server ) . ( defined( 'ORIA_GOOGLE_SERVER_KEY' ) ? '  [wp-config constant]' : '  [Site settings]' ) );
		\WP_CLI::log( 'Browser key: ' . $mask( $browser ) . ( defined( 'ORIA_GOOGLE_BROWSER_KEY' ) ? '  [wp-config constant]' : '  [Site settings]' ) . '  (map iframe only)' );
		\WP_CLI::log( 'Toggle:      ' . ( $toggle ? 'ON' : 'OFF' ) );
		if ( '' === $server || ! $toggle ) {
			\WP_CLI::error( 'Integration disabled — the server key and the toggle are required. (The browser key is only needed for the profile map.)' );
		}

		// --- listing ----------------------------------------------------
		$post = is_numeric( $which )
			? get_post( (int) $which )
			: get_page_by_path( $which, OBJECT, \Oria\Core\PostTypes\LISTING );
		if ( ! $post instanceof \WP_Post || \Oria\Core\PostTypes\LISTING !== $post->post_type ) {
			\WP_CLI::error( "Listing not found: {$which}" );
		}
		\WP_CLI::log( "Listing:     {$post->post_title} (#{$post->ID})" );

		$place_id = trim( (string) get_field( 'google_place_id', $post->ID ) );
		$address  = (string) get_field( 'address', $post->ID );
		\WP_CLI::log( 'Place ID:    ' . ( $place_id ?: '(none yet — will look up)' ) );
		\WP_CLI::log( 'Query:       ' . $post->post_title . ( $address ? ', ' . $address : '' ) );

		// --- clear the day-long back-off and stale cache ----------------
		delete_transient( 'oria_places_backoff_' . $post->ID );
		delete_post_meta( $post->ID, \Oria\Core\Places\META_CACHE );
		\WP_CLI::log( 'Cleared back-off and cache.' );

		// --- raw request so Google's own error is visible ---------------
		$response = wp_remote_post(
			'https://places.googleapis.com/v1/places:searchText',
			array(
				'timeout' => 10,
				'headers' => array(
					'Content-Type'     => 'application/json',
					'X-Goog-Api-Key'   => $server,
					'X-Goog-FieldMask' => 'places.id,places.displayName,places.rating,places.userRatingCount,places.photos',
				),
				'body'    => (string) wp_json_encode(
					array(
						'textQuery'  => $post->post_title . ( $address ? ', ' . $address : '' ),
						'regionCode' => 'AU',
						'pageSize'   => 1,
					)
				),
			)
		);

		if ( is_wp_error( $response ) ) {
			\WP_CLI::error( 'HTTP failure: ' . $response->get_error_message() );
		}

		$code = (int) wp_remote_retrieve_response_code( $response );
		$body = (string) wp_remote_retrieve_body( $response );
		\WP_CLI::log( "HTTP {$code}" );
		if ( 200 !== $code ) {
			\WP_CLI::log( substr( $body, 0, 800 ) );
			\WP_CLI::error( 'Google rejected the request — the error above says why (billing, API not enabled, or key restriction are the usual three).' );
		}

		$json  = json_decode( $body, true );
		$place = $json['places'][0] ?? null;
		if ( ! $place ) {
			\WP_CLI::error( 'Request OK but no place matched the query. Try pasting the correct place ID into the listing\'s Google place ID field.' );
		}

		\WP_CLI::log( 'Matched:     ' . ( $place['displayName']['text'] ?? '?' ) );
		\WP_CLI::log( 'Place ID:    ' . ( $place['id'] ?? '?' ) );
		\WP_CLI::log( 'Rating:      ' . ( $place['rating'] ?? 'none' ) . ' (' . ( $place['userRatingCount'] ?? 0 ) . ' reviews)' );
		\WP_CLI::log( 'Photos:      ' . count( (array) ( $place['photos'] ?? array() ) ) );

		// --- populate through the real code path ------------------------
		$data = \Oria\Core\Places\data_for( $post->ID );
		if ( $data ) {
			\WP_CLI::success( 'Cache written — reload the listing page and the rating/photos should appear.' );
		} else {
			\WP_CLI::warning( 'Direct request worked but data_for() declined — is the place ID field set to "off"?' );
		}
	}

	/** Create practice and area terms from the seed's categories/regions. */
	private function ensure_terms( array $data, bool $dry ): void {
		foreach ( (array) ( $data['categories'] ?? array() ) as $cat ) {
			/*
			 * An optional "parent" slug makes the category a SUB-category.
			 * That matters beyond tidiness: the practices index builds each
			 * tile's link row from declared sub-categories first and tops up
			 * with styles that clear a share of the category. A style below
			 * that share never surfaces -- so a small, real grouping like
			 * smoothies & juice inside a broad category can only be shown by
			 * being declared, which is exactly what a sub-category is for.
			 *
			 * An unknown parent slug is reported rather than silently
			 * creating the term at the root, where its URL would sit beside
			 * the top-level categories and read as one of them.
			 */
			$parent = 0;
			$pslug  = sanitize_title( (string) ( $cat['parent'] ?? '' ) );
			if ( '' !== $pslug ) {
				$pterm = get_term_by( 'slug', $pslug, Taxonomies\PRACTICE );
				if ( $pterm instanceof \WP_Term ) {
					$parent = (int) $pterm->term_id;
				} else {
					\WP_CLI::warning( sprintf( '%s: no "%s" category to hang it under, so it was not created.', (string) $cat['name'], $pslug ) );
					continue;
				}
			}
			$this->ensure_term( (string) $cat['name'], (string) $cat['id'], Taxonomies\PRACTICE, $parent, $dry );
		}

		/*
		 * Regions hang off the city, not off the root.
		 *
		 * Before the city migration the root was the right place and this
		 * passed 0. After it, a seed file naming a region the site does not
		 * have yet would create it at the root — outside the city, with a
		 * URL of /area/{region}/ while every sibling sits at
		 * /area/perth/{region}/. One import would quietly re-break the tree
		 * the migration just fixed.
		 *
		 * A file may name its own city; otherwise the default applies, which
		 * keeps every existing Perth seed file working untouched.
		 */
		$city_slug = (string) ( $data['city'] ?? \Oria\Core\Cities\path( \Oria\Core\Cities\default_city() ) );
		$city_term = get_term_by( 'slug', $city_slug, Taxonomies\AREA );
		$city_id   = ( $city_term instanceof \WP_Term && 0 === (int) $city_term->parent ) ? (int) $city_term->term_id : 0;

		if ( 0 === $city_id && isset( $data['regions'] ) ) {
			// Not an error: this is simply a site whose area tree has not been
			// migrated yet, and the root is still correct there.
			\WP_CLI::log( sprintf( '  no city term "%s" — regions will sit at the root, as before', $city_slug ) );
		}

		foreach ( (array) ( $data['regions'] ?? array() ) as $region ) {
			$parent_id = $this->ensure_term( (string) $region['name'], (string) $region['id'], Taxonomies\AREA, $city_id, $dry );
			foreach ( (array) ( $region['suburbs'] ?? array() ) as $suburb ) {
				$this->ensure_term( (string) $suburb, $this->area_slug( (string) $suburb ), Taxonomies\AREA, $parent_id, $dry );
			}
		}
	}

	/**
	 * The canonical slug for a suburb named in a seed file.
	 *
	 * sanitize_title() alone was enough while no term had ever been renamed.
	 * The CBD is now "Perth CBD" / perth-cbd, and a file still saying "Perth"
	 * would otherwise create a second term and split twenty-seven listings
	 * between them without erroring. data/area-aliases.json is the map.
	 *
	 * THE ALIAS ONLY APPLIES IF ITS TARGET ALREADY EXISTS. The first version
	 * returned the alias unconditionally, which caused the precise accident it
	 * was written to prevent. Run against a site where the rename had not
	 * happened yet, it sent the importer looking for a perth-cbd term, found
	 * none, created an empty one, and left the CBD split between a populated
	 * "perth" and a new "perth-cbd" holding four listings.
	 *
	 * An alias is a statement about a term that exists. It is never an
	 * instruction to create one.
	 */
	private function area_slug( string $name ): string {
		static $aliases = null;

		if ( null === $aliases ) {
			$aliases = array();
			$path    = ORIA_CORE_DIR . 'data/area-aliases.json';
			if ( is_readable( $path ) ) {
				$json    = json_decode( (string) file_get_contents( $path ), true ); // phpcs:ignore WordPress.WP.AlternativeFunctions
				$aliases = (array) ( $json['aliases'] ?? array() );
			}
		}

		$slug = sanitize_title( $name );

		if ( ! isset( $aliases[ $slug ] ) ) {
			return $slug;
		}

		$target = (string) $aliases[ $slug ];

		// Target present: use it. Absent: this site has not been renamed yet,
		// so the seed file's own slug is still the right answer.
		return get_term_by( 'slug', $target, Taxonomies\AREA ) instanceof \WP_Term ? $target : $slug;
	}

	private function ensure_term( string $name, string $slug, string $taxonomy, int $parent, bool $dry ): int {
		$existing = get_term_by( 'slug', $slug, $taxonomy );
		if ( $existing instanceof \WP_Term ) {
			return $existing->term_id;
		}

		if ( $dry ) {
			\WP_CLI::log( "  would create {$taxonomy} term: {$name}" );
			return 0;
		}

		$result = wp_insert_term( $name, $taxonomy, array( 'slug' => $slug, 'parent' => $parent ) );
		if ( is_wp_error( $result ) ) {
			\WP_CLI::warning( "term {$name}: " . $result->get_error_message() );
			return 0;
		}
		\WP_CLI::log( "  created {$taxonomy} term: {$name}" );
		return (int) $result['term_id'];
	}

	/** @return 'created'|'updated'|'skipped' */
	private function upsert_listing( array $row, array $data, bool $dry ): string {
		$slug = sanitize_title( (string) ( $row['id'] ?? $row['name'] ?? '' ) );
		if ( '' === $slug ) {
			\WP_CLI::warning( 'row with no id or name — skipped' );
			return 'skipped';
		}

		$existing = get_page_by_path( $slug, OBJECT, PostTypes\LISTING );

		// Same id already on the site: without --update that is a duplicate,
		// full stop. Existing data is never touched by a plain import.
		if ( $existing instanceof \WP_Post && ! $this->update_mode ) {
			\WP_CLI::log( "  duplicate (same id): {$row['name']} = \"{$existing->post_title}\" (#{$existing->ID}) — pass --update to refresh it" );
			return 'duplicate';
		}

		// New id, but possibly the same practice wearing a different slug:
		// match on normalized name, phone or website domain. A row may carry
		// "force": true to declare itself a known false positive — e.g. a
		// second venue of the same organisation sharing one website.
		if ( ! $existing instanceof \WP_Post && empty( $row['force'] ) ) {
			$hit = $this->find_duplicate( $row );
			if ( null !== $hit ) {
				\WP_CLI::log( "  duplicate ({$hit[0]}): {$row['name']} matches existing \"{$hit[1]}\" — add \"force\": true to the row if this is genuinely a different venue" );
				return 'duplicate';
			}
		}

		// The one rule that matters most: claimed profiles belong to their
		// owners now. The seed file has no authority over them.
		if ( $existing instanceof \WP_Post && ! $this->include_claimed ) {
			$status = (string) get_post_meta( $existing->ID, 'claim_status', true );
			if ( in_array( $status, array( 'claimed', 'featured' ), true ) ) {
				\WP_CLI::log( "  skip (claimed): {$row['name']} — pass --include-claimed to refresh seeds in dev" );
				return 'skipped';
			}
		}

		if ( $dry ) {
			\WP_CLI::log( ( $existing ? '  would update: ' : '  would create: ' ) . $row['name'] );
			// Registering even on dry runs means a file that duplicates
			// itself is reported honestly too.
			$this->register_in_index( $row );
			return $existing ? 'updated' : 'created';
		}

		$postarr = array(
			'post_type'    => PostTypes\LISTING,
			'post_status'  => 'publish',
			'post_title'   => (string) ( $row['name'] ?? $slug ),
			'post_name'    => $slug,
			'post_excerpt' => (string) ( $row['blurb'] ?? '' ),
		);
		if ( $existing instanceof \WP_Post ) {
			$postarr['ID'] = $existing->ID;
		}

		$post_id = wp_insert_post( $postarr, true );
		if ( is_wp_error( $post_id ) ) {
			\WP_CLI::warning( "{$row['name']}: " . $post_id->get_error_message() );
			return 'skipped';
		}

		$this->assign_terms( (int) $post_id, $row );
		$this->write_meta( (int) $post_id, $row );

		// Specialties last: the keyword matcher reads the services repeater
		// write_meta just filled in.
		\Oria\Core\Specialties\tag_post( (int) $post_id );
		$this->register_in_index( $row );

		\WP_CLI::log( ( $existing ? '  updated: ' : '  created: ' ) . $row['name'] );
		return $existing ? 'updated' : 'created';
	}

	/* ----------------------------------------------------- duplicate index */

	/** Index every existing listing by normalized name, phone and domain. */
	private function build_dupe_index(): void {
		$posts = get_posts(
			array(
				'post_type'      => PostTypes\LISTING,
				'post_status'    => 'any',
				'posts_per_page' => -1,
			)
		);
		foreach ( $posts as $post ) {
			$this->index_entry(
				$post->post_title,
				(string) get_post_meta( $post->ID, 'phone', true ),
				(string) get_post_meta( $post->ID, 'website', true ),
				$post->post_title
			);
		}
	}

	/** Add a just-imported row so later rows in the same file check against it. */
	private function register_in_index( array $row ): void {
		$this->index_entry(
			(string) ( $row['name'] ?? '' ),
			(string) ( $row['phone'] ?? '' ),
			(string) ( $row['web'] ?? '' ),
			(string) ( $row['name'] ?? '' )
		);
	}

	private function index_entry( string $name, string $phone, string $web, string $label ): void {
		$k = $this->norm_name( $name );
		if ( '' !== $k && ! isset( $this->dupe_index['name'][ $k ] ) ) {
			$this->dupe_index['name'][ $k ] = $label;
		}
		$k = $this->norm_phone( $phone );
		if ( '' !== $k && ! isset( $this->dupe_index['phone'][ $k ] ) ) {
			$this->dupe_index['phone'][ $k ] = $label;
		}
		$k = $this->norm_domain( $web );
		if ( '' !== $k && ! isset( $this->dupe_index['domain'][ $k ] ) ) {
			$this->dupe_index['domain'][ $k ] = $label;
		}
	}

	/**
	 * The first field on which this row collides with a known listing.
	 *
	 * @return array{0: string, 1: string}|null  [field, existing title]
	 */
	private function find_duplicate( array $row ): ?array {
		$checks = array(
			'name'   => $this->norm_name( (string) ( $row['name'] ?? '' ) ),
			'phone'  => $this->norm_phone( (string) ( $row['phone'] ?? '' ) ),
			'domain' => $this->norm_domain( (string) ( $row['web'] ?? '' ) ),
		);
		foreach ( $checks as $field => $key ) {
			if ( '' !== $key && isset( $this->dupe_index[ $field ][ $key ] ) ) {
				return array( $field, $this->dupe_index[ $field ][ $key ] );
			}
		}
		return null;
	}

	/** Lowercase letters and digits only — "The Yoga Space" == "Yoga Space?". */
	private function norm_name( string $name ): string {
		$key = strtolower( remove_accents( $name ) );
		$key = (string) preg_replace( '/^(the|a)\s+/', '', $key );
		return (string) preg_replace( '/[^a-z0-9]/', '', $key );
	}

	/** Digits only, Australian-normalised: +61 8 → 08, keep the last 9. */
	private function norm_phone( string $phone ): string {
		$digits = (string) preg_replace( '/\D/', '', $phone );
		if ( '' === $digits ) {
			return '';
		}
		if ( str_starts_with( $digits, '61' ) && strlen( $digits ) > 9 ) {
			$digits = substr( $digits, 2 );
		}
		return substr( $digits, -9 );
	}

	/** Host without scheme or www — one practice, one domain. */
	private function norm_domain( string $url ): string {
		if ( '' === trim( $url ) ) {
			return '';
		}
		$host = wp_parse_url(
			str_contains( $url, '//' ) ? $url : 'https://' . $url,
			PHP_URL_HOST
		);
		if ( ! is_string( $host ) || '' === $host ) {
			return '';
		}
		return (string) preg_replace( '/^www\./', '', strtolower( $host ) );
	}

	private function assign_terms( int $post_id, array $row ): void {
		// Practices: primary plus any secondaries.
		$practices = array_merge(
			array( (string) ( $row['cat'] ?? '' ) ),
			(array) ( $row['also'] ?? array() )
		);
		/*
		 * An unknown slug used to be skipped in silence. That is how twelve
		 * beauty listings reached production with no category at all: the
		 * seed said "beauty", production had no such term because the
		 * categories had not shipped yet, and the import reported twelve
		 * successes. Now it says so.
		 */
		$practice_ids = array();
		foreach ( array_filter( $practices ) as $slug ) {
			$term = get_term_by( 'slug', $slug, Taxonomies\PRACTICE );
			if ( $term instanceof \WP_Term ) {
				$practice_ids[] = $term->term_id;
			} else {
				\WP_CLI::warning( sprintf( '%s: no "%s" category on this site, so it has none.', (string) ( $row['name'] ?? $post_id ), $slug ) );
			}
		}
		if ( $practice_ids ) {
			wp_set_object_terms( $post_id, $practice_ids, Taxonomies\PRACTICE );
		}

		// Area: assign the suburb; the region is implied by ancestry.
		/*
		 * area_slug(), not sanitize_title(): the alias map is what stops a
		 * seed saying "Margaret River" resolving to the CITY term of that
		 * slug. It was applied when terms are created and skipped here,
		 * which filed four listings on the city itself.
		 */
		$suburb = get_term_by( 'slug', $this->area_slug( (string) ( $row['suburb'] ?? '' ) ), Taxonomies\AREA );
		if ( $suburb instanceof \WP_Term ) {
			wp_set_object_terms( $post_id, array( $suburb->term_id ), Taxonomies\AREA );
		} elseif ( ! empty( $row['region'] ) ) {
			$region = get_term_by( 'slug', (string) $row['region'], Taxonomies\AREA );
			if ( $region instanceof \WP_Term ) {
				wp_set_object_terms( $post_id, array( $region->term_id ), Taxonomies\AREA );
			} else {
				\WP_CLI::warning( sprintf( '%s: neither suburb "%s" nor region "%s" exists here.', (string) ( $row['name'] ?? $post_id ), (string) ( $row['suburb'] ?? '' ), (string) $row['region'] ) );
			}
		}

	}

	/**
	 * Refuse a file that names categories this site does not have.
	 *
	 * Checked up front against the site being written to, so the answer
	 * cannot depend on which machine the dry run happened to be run on —
	 * which is exactly how the beauty batch passed here and failed there.
	 *
	 * @param array<int, array<string, mixed>> $listings
	 */
	private function check_terms( array $listings, bool $dry ): void {
		$missing_cats  = array();
		$missing_areas = array();

		foreach ( $listings as $row ) {
			$slugs = array_filter(
				array_merge( array( (string) ( $row['cat'] ?? '' ) ), (array) ( $row['also'] ?? array() ) )
			);
			foreach ( $slugs as $slug ) {
				if ( ! get_term_by( 'slug', $slug, Taxonomies\PRACTICE ) ) {
					$missing_cats[ $slug ] = ( $missing_cats[ $slug ] ?? 0 ) + 1;
				}
			}

			$suburb = sanitize_title( (string) ( $row['suburb'] ?? '' ) );
			$region = (string) ( $row['region'] ?? '' );
			if ( '' !== $suburb
				&& ! get_term_by( 'slug', $suburb, Taxonomies\AREA )
				&& ( '' === $region || ! get_term_by( 'slug', $region, Taxonomies\AREA ) ) ) {
				$key                   = (string) ( $row['suburb'] ?? '' );
				$missing_areas[ $key ] = ( $missing_areas[ $key ] ?? 0 ) + 1;
			}
		}

		foreach ( $missing_areas as $name => $count ) {
			\WP_CLI::warning( sprintf( '%d listing(s) name the area "%s", which does not exist here.', $count, $name ) );
		}

		foreach ( $missing_cats as $slug => $count ) {
			\WP_CLI::warning( sprintf( '%d listing(s) name the category "%s", which does not exist here.', $count, $slug ) );
		}

		/*
		 * A dry run reports and returns — refusing to finish the report is
		 * the opposite of what it is for. A real run stops, because the
		 * alternative is listings on a live site filed under nothing.
		 */
		if ( $missing_cats && ! $dry ) {
			\WP_CLI::error( 'Declare those categories in the file\'s "categories" list, or create them first. Nothing has been written.' );
		}
	}

	private function write_meta( int $post_id, array $row ): void {
		$scalar = array(
			'claim_status' => (string) ( $row['status'] ?? 'unclaimed' ),
			'address'      => (string) ( $row['address'] ?? '' ),
			'phone'        => (string) ( $row['phone'] ?? '' ),
			'email'        => (string) ( $row['email'] ?? '' ),
			'website'      => (string) ( $row['web'] ?? '' ),
			'price_from'   => $row['priceFrom'] ?? '',
			'price_band'   => (string) ( $row['priceBand'] ?? '' ),
			'format'       => (string) ( $row['format'] ?? 'in-person' ),
			// practice unless the seed says otherwise -- see Theme\\words().
			'kind'         => in_array( $row['kind'] ?? '', array( 'practice', 'place', 'spot' ), true ) ? (string) $row['kind'] : 'practice',
			'rating'       => $row['rating'] ?? '',
			'review_count' => $row['reviews'] ?? '',
			'next_session' => (string) ( $row['next'] ?? '' ),
		);

		// The seed names a theme scene image (e.g. "scene-hall"); keep it so
		// the templates can show art-directed placeholders until the listing
		// has real photos.
		if ( ! empty( $row['image'] ) ) {
			update_post_meta( $post_id, 'placeholder_scene', sanitize_key( (string) $row['image'] ) );
		}

		foreach ( $scalar as $key => $value ) {
			if ( function_exists( 'update_field' ) ) {
				update_field( $key, $value, $post_id );
			} else {
				update_post_meta( $post_id, $key, $value );
			}
		}

		// services[] -> the ACF repeater's [{name}] shape.
		$services = array_map(
			static fn( $s ): array => array( 'name' => (string) $s ),
			(array) ( $row['services'] ?? array() )
		);
		if ( function_exists( 'update_field' ) ) {
			update_field( 'services', $services, $post_id );
		}
	}
}
