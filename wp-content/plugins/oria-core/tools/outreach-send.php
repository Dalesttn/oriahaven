<?php
/**
 * Claim outreach — one daily batch, run from cron on the live server.
 *
 * Why here rather than on a laptop: this runs whether or not anyone's machine
 * is on, it reads the live listing data so a business that claimed its page
 * yesterday is skipped today, and it sits on the same host as the mailbox.
 *
 * Reads nothing from a CSV. The list IS the listing data: published, has an
 * email and a website, and still unclaimed.
 *
 * WRITES NOTHING TO THE DATABASE. Who has been contacted is kept in a file
 * beside this script, so the outreach cannot corrupt listing data and can be
 * inspected or reset with a text editor.
 *
 * SAFETY
 *   Dry run is the default. --send is required to post anything, and even
 *   then it refuses unless ARMED exists. Delete ARMED to stop mid-campaign.
 *
 * CREDENTIALS — in wp-config.php on the server, never in this file:
 *
 *     define( 'ORIA_SMTP_USER', 'hello@oriahaven.com.au' );
 *     define( 'ORIA_SMTP_PASS', '...' );
 *
 * USAGE (from the WordPress root)
 *     php wp-content/plugins/oria-core/tools/outreach-send.php --status
 *     php wp-content/plugins/oria-core/tools/outreach-send.php            # dry run
 *     php wp-content/plugins/oria-core/tools/outreach-send.php --send
 *
 * CRON (hPanel → Advanced → Cron Jobs), daily:
 *     cd ~/domains/oriahaven.com.au/public_html && \
 *       php wp-content/plugins/oria-core/tools/outreach-send.php --send --limit=15
 */

declare(strict_types=1);

if ( PHP_SAPI !== 'cli' ) {
	http_response_code( 403 );
	exit( "CLI only.\n" );
}

$root = dirname( __DIR__, 4 );          // .../public_html
require $root . '/wp-load.php';

const OUTREACH_THROTTLE = 20;           // seconds between messages
const OUTREACH_DEFAULT  = 15;

$dir    = __DIR__;
$log    = $dir . '/outreach-sent.csv';
$unsub  = $dir . '/outreach-unsubscribed.txt';
$armed  = $dir . '/ARMED';
$impMap = ORIA_CORE_DIR . 'data/outreach-impressions.json';

/* ------------------------------------------------------------------ args */

$argvv  = $argv ?? array();
$send   = in_array( '--send', $argvv, true );
$status = in_array( '--status', $argvv, true );
$limit  = OUTREACH_DEFAULT;
foreach ( $argvv as $a ) {
	if ( preg_match( '/^--limit=(\d+)$/', $a, $m ) ) {
		$limit = max( 1, (int) $m[1] );
	}
}

/* --------------------------------------------------------------- helpers */

function outreach_done( string $log, string $unsub ): array {
	$done = array();
	if ( is_readable( $log ) ) {
		$fh = fopen( $log, 'r' );
		fgetcsv( $fh ); // header
		while ( ( $r = fgetcsv( $fh ) ) !== false ) {
			if ( isset( $r[1] ) ) {
				$done[ strtolower( trim( $r[1] ) ) ] = true;
			}
		}
		fclose( $fh );
	}
	if ( is_readable( $unsub ) ) {
		foreach ( file( $unsub, FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES ) as $l ) {
			$l = strtolower( trim( $l ) );
			if ( '' !== $l && 0 !== strpos( $l, '#' ) ) {
				$done[ $l ] = true;
			}
		}
	}
	return $done;
}

/** Published, contactable, still unclaimed. */
function outreach_candidates(): array {
	$q = new WP_Query(
		array(
			'post_type'      => 'listing',
			'post_status'    => 'publish',
			'posts_per_page' => -1,
			'no_found_rows'  => true,
			'orderby'        => 'ID',
			'order'          => 'ASC',
		)
	);

	$out = array();
	foreach ( $q->posts as $p ) {
		$email = trim( (string) get_post_meta( $p->ID, 'email', true ) );
		$web   = trim( (string) get_post_meta( $p->ID, 'website', true ) );
		$claim = trim( (string) get_post_meta( $p->ID, 'claim_status', true ) );

		if ( '' === $email || '' === $web ) {
			continue;
		}
		if ( '' !== $claim && 'unclaimed' !== $claim ) {
			continue; // they already have the page; nothing to offer
		}
		if ( ! is_email( $email ) ) {
			continue;
		}

		$areas  = wp_get_post_terms( $p->ID, 'area', array( 'fields' => 'names' ) );
		$out[] = array(
			'id'      => $p->ID,
			'slug'    => $p->post_name,
			'name'    => html_entity_decode( $p->post_title, ENT_QUOTES, 'UTF-8' ),
			'suburb'  => is_wp_error( $areas ) ? 'Perth' : ( $areas[0] ?? 'Perth' ),
			'email'   => $email,
			'url'     => get_permalink( $p->ID ),
		);
	}
	return $out;
}

function outreach_message( array $row, int $impressions ): array {
	$footer = "\n\nThanks,\nDale\nOria Haven — oriahaven.com.au\n\n"
		. "We also build websites, AI and automation for Australian businesses —\n"
		. "oriadigital.com.au\n\n"
		. "Not interested? Reply \"remove\" and I'll take the listing down.\n";

	if ( $impressions > 0 ) {
		$subject = sprintf( 'Your Oria Haven profile was seen %d times last month', $impressions );
		$body    = sprintf(
			"Hi there,\n\nI run Oria Haven, a Perth wellness directory. %s is listed on it —\nhere's the page:\n\n%s\n\n"
			. "In the last 28 days that page came up %d times in Google search.\nI thought you'd want to know, and to have control over what it says.\n\n"
			. "You can claim it for free. That lets you fix your details, hours and services,\nand add photos. No cost, no catch — an accurate listing is better for both of\nus.\n\n"
			. "If anything on there is wrong, just reply and I'll fix it myself.",
			$row['name'],
			$row['url'],
			$impressions
		);
	} else {
		$subject = sprintf( '%s is listed on Oria Haven — want to claim it?', $row['name'] );
		$body    = sprintf(
			"Hi there,\n\nI run Oria Haven, a wellness directory for Perth. I've listed %s in\n%s:\n\n%s\n\n"
			. "You can claim the page for free and keep your details, hours and services\ncurrent. It costs nothing and it means people find the right information.\n\n"
			. "If anything's wrong on there, reply and I'll correct it.",
			$row['name'],
			$row['suburb'],
			$row['url']
		);
	}

	return array( $subject, $body . $footer );
}

/* ----------------------------------------------------------------- start */

$impressions = array();
if ( is_readable( $impMap ) ) {
	$impressions = (array) json_decode( (string) file_get_contents( $impMap ), true );
}

$done    = outreach_done( $log, $unsub );
$all     = outreach_candidates();
$pending = array_values( array_filter( $all, static fn( $r ) => ! isset( $done[ strtolower( $r['email'] ) ] ) ) );

/*
 * Best prospects first. A business whose profile Google already shows gets an
 * email quoting a real number, and that is the version worth learning from
 * early -- if the wording is wrong, it is better to find out on batch one than
 * on batch nine. ID order would have buried all 20 of them at random.
 */
usort(
	$pending,
	static function ( array $a, array $b ) use ( $impressions ): int {
		return ( (int) ( $impressions[ $b['slug'] ] ?? 0 ) ) <=> ( (int) ( $impressions[ $a['slug'] ] ?? 0 ) );
	}
);

if ( $status ) {
	printf( "contactable now : %d\n", count( $all ) );
	printf( "already sent    : %d\n", count( $all ) - count( $pending ) );
	printf( "remaining       : %d\n", count( $pending ) );
	printf( "armed           : %s\n", file_exists( $armed ) ? 'yes' : 'NO — cron will not send' );
	exit( 0 );
}

$batch = array_slice( $pending, 0, $limit );
if ( ! $batch ) {
	exit( "Nothing left to send.\n" );
}

if ( $send && ! file_exists( $armed ) ) {
	fwrite( STDERR, "Refusing to send: ARMED is missing.\nRun without --send, read the output, then: touch " . $armed . "\n" );
	exit( 1 );
}

$user = defined( 'ORIA_SMTP_USER' ) ? (string) ORIA_SMTP_USER : '';
$pass = defined( 'ORIA_SMTP_PASS' ) ? (string) ORIA_SMTP_PASS : '';
if ( $send && ( '' === $user || '' === $pass ) ) {
	fwrite( STDERR, "Define ORIA_SMTP_USER and ORIA_SMTP_PASS in wp-config.php first.\n" );
	exit( 1 );
}

/*
 * Authenticated SMTP to the mailbox itself, not PHP mail(). The SPF record
 * authorises _spf.mail.hostinger.com; a message handed to the web server's
 * local sendmail is not covered by it and lands in spam.
 */
if ( $send ) {
	add_action(
		'phpmailer_init',
		static function ( $phpmailer ) use ( $user, $pass ): void {
			$phpmailer->isSMTP();
			$phpmailer->Host       = 'smtp.hostinger.com';
			$phpmailer->SMTPAuth   = true;
			$phpmailer->Port       = 465;
			$phpmailer->SMTPSecure = 'ssl';
			$phpmailer->Username   = $user;
			$phpmailer->Password   = $pass;
			$phpmailer->setFrom( $user, 'Dale — Oria Haven', false );
			$phpmailer->addReplyTo( $user, 'Dale — Oria Haven' );
		}
	);
}

$sent = 0;
foreach ( $batch as $i => $row ) {
	$imp = (int) ( $impressions[ $row['slug'] ] ?? 0 );
	list( $subject, $body ) = outreach_message( $row, $imp );

	if ( ! $send ) {
		printf( "[dry] %-38s tier %d  %s\n", $row['email'], $imp > 0 ? 1 : 2, $row['name'] );
		printf( "      %s\n", $subject );
		continue;
	}

	$ok = wp_mail( $row['email'], $subject, $body );

	if ( $ok ) {
		$new = ! file_exists( $log );
		$fh  = fopen( $log, 'a' );
		if ( $new ) {
			fputcsv( $fh, array( 'sent_at_utc', 'email', 'business', 'tier', 'listing_id' ) );
		}
		fputcsv( $fh, array( gmdate( 'c' ), $row['email'], $row['name'], $imp > 0 ? 1 : 2, $row['id'] ) );
		fclose( $fh );
		$sent++;
		printf( "sent %-38s tier %d  %s\n", $row['email'], $imp > 0 ? 1 : 2, $row['name'] );
	} else {
		fwrite( STDERR, sprintf( "FAILED %s (%s) — stopping\n", $row['email'], $row['name'] ) );
		break; // a broken mailbox should not burn the rest of the batch
	}

	if ( $i < count( $batch ) - 1 ) {
		sleep( OUTREACH_THROTTLE );
	}
}

if ( $send ) {
	printf( "\n%d sent. %d remaining.\n", $sent, count( $pending ) - $sent );
} else {
	printf( "\nDry run — %d message(s) shown, nothing sent.\n", count( $batch ) );
	printf( "If that's right: touch %s\n", $armed );
}
