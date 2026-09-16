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
 * FAILURES
 *   Two kinds, and they are not the same emergency.
 *
 *   A SESSION failure is ours: the login was refused, the server would not
 *   connect, we have been rate limited. Sending the rest of the batch would
 *   fail the same way and burn the list, so the run stops.
 *
 *   A RECIPIENT failure is theirs: a dead domain, a mailbox that does not
 *   exist, a full inbox. That says nothing about the next business on the
 *   list, so it is written to outreach-failed.csv and the run continues.
 *
 *   Stopping on both was worse than it looked. A bad address is never
 *   written to the sent log, so it stays in the queue -- and because the
 *   queue is sorted by impressions, it stays in the SAME PLACE. Every run
 *   after it hit the same address, stopped in the same spot, and sent
 *   nothing. One dead domain could hold up the whole campaign for good.
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
 *     php wp-content/plugins/oria-core/tools/outreach-send.php --failures
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

/*
 * How many times a temporary failure is worth retrying before the address is
 * left alone. "Temporary" often is -- a mail server having a bad morning --
 * but a domain whose DNS has been broken for months reports the same thing
 * forever, and there is no point asking it every day until somebody notices.
 */
const OUTREACH_FAIL_MAX = 3;

$dir    = __DIR__;
$log    = $dir . '/outreach-sent.csv';
$unsub  = $dir . '/outreach-unsubscribed.txt';
$armed  = $dir . '/ARMED';
$failed = $dir . '/outreach-failed.csv';
$impMap = ORIA_CORE_DIR . 'data/outreach-impressions.json';

/* ------------------------------------------------------------------ args */

$argvv  = $argv ?? array();
$send   = in_array( '--send', $argvv, true );
$status = in_array( '--status', $argvv, true );
$limit  = OUTREACH_DEFAULT;
$testTo = '';
foreach ( $argvv as $a ) {
	if ( preg_match( '/^--limit=(\d+)$/', $a, $m ) ) {
		$limit = max( 1, (int) $m[1] );
	}
	if ( preg_match( '/^--to=(.+)$/', $a, $m ) ) {
		$testTo = trim( $m[1] );
	}
}
$debug    = in_array( '--debug', $argvv, true );
$failList = in_array( '--failures', $argvv, true );

/*
 * wp_mail() answers true or false and keeps the reason to itself. A silent
 * false is useless when the whole question is why the mail server said no, so
 * the reason is captured here and printed with the failure.
 */
$GLOBALS['oria_mail_error'] = '';
add_action(
	'wp_mail_failed',
	static function ( $err ): void {
		$GLOBALS['oria_mail_error'] = $err instanceof WP_Error ? $err->get_error_message() : (string) $err;
	}
);

/*
 * --to sends one real message to somewhere you control instead of to a
 * business, and does not write the send log. It exists for mail-tester.com
 * and the like: 195 emails is an expensive way to discover that SPF, DKIM or
 * the From address is subtly wrong, and a spam score costs one message.
 *
 * The recipient is the only thing that changes. Same wording, same headers,
 * same route out, so the score is the score the real batch would get.
 */
if ( '' !== $testTo ) {
	if ( ! is_email( $testTo ) ) {
		fwrite( STDERR, "--to is not a valid address\n" );
		exit( 1 );
	}
	$limit = 1;
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

/**
 * Addresses that have failed before.
 *
 * @return array<string, array{n:int, done:bool, why:string, at:string, name:string}>
 */
function outreach_failures( string $file ): array {
	$out = array();
	if ( ! is_readable( $file ) ) {
		return $out;
	}
	$fh = fopen( $file, 'r' );
	fgetcsv( $fh ); // header
	while ( ( $r = fgetcsv( $fh ) ) !== false ) {
		if ( ! isset( $r[1] ) ) {
			continue;
		}
		$out[ strtolower( trim( $r[1] ) ) ] = array(
			'at'   => (string) ( $r[0] ?? '' ),
			'name' => (string) ( $r[2] ?? '' ),
			'n'    => (int) ( $r[3] ?? 1 ),
			'done' => ( 'yes' === ( $r[4] ?? '' ) ),
			'why'  => (string) ( $r[5] ?? '' ),
		);
	}
	fclose( $fh );
	return $out;
}

/** Rewrite the failure file from the map, newest state per address. */
function outreach_write_failures( string $file, array $rows ): void {
	$fh = fopen( $file, 'w' );
	fputcsv( $fh, array( 'last_at_utc', 'email', 'business', 'attempts', 'given_up', 'reason' ) );
	foreach ( $rows as $email => $r ) {
		fputcsv( $fh, array( $r['at'], $email, $r['name'], $r['n'], $r['done'] ? 'yes' : 'no', $r['why'] ) );
	}
	fclose( $fh );
}

/**
 * Whose fault was this?
 *
 * 'session' means the connection or the login, and every message after it
 * would fail the same way. 'recipient' means this address, and the next one
 * is unaffected. Anything unrecognised is treated as a session problem,
 * because stopping a batch costs a day and mis-sending costs a reputation.
 */
function outreach_fail_kind( string $error ): string {
	$e = strtolower( $error );

	foreach ( array( 'could not authenticate', 'smtp connect() failed', 'could not connect', 'authentication failed', 'too many', 'rate limit', 'quota exceeded', 'sending limit' ) as $needle ) {
		if ( str_contains( $e, $needle ) ) {
			return 'session';
		}
	}
	foreach ( array( 'recipients failed', 'recipient address rejected', 'user unknown', 'unknown user', 'no such user', 'mailbox unavailable', 'mailbox full', 'over quota', 'does not exist', 'address rejected', 'lookup failure', 'domain not found', 'invalid address' ) as $needle ) {
		if ( str_contains( $e, $needle ) ) {
			return 'recipient';
		}
	}

	return 'session';
}

/**
 * Is there anywhere for mail to this address to go?
 *
 * Checked before the message is handed to SMTP, because a bounce costs more
 * than a lookup: every undeliverable address we try counts against the
 * sending domain's reputation, and a domain with broken DNS will never
 * accept anything no matter how many mornings we ask.
 *
 * A domain with no MX but a working A record still takes mail by the old
 * rule, so both count. Only when neither resolves is it hopeless -- and a
 * resolver that is itself having a bad day returns false here too, which is
 * why this records an attempt rather than giving up on the first no.
 */
function outreach_deliverable( string $email ): bool {
	$at = strrpos( $email, '@' );
	if ( false === $at ) {
		return false;
	}
	$domain = substr( $email, $at + 1 );
	return checkdnsrr( $domain, 'MX' ) || checkdnsrr( $domain, 'A' );
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

$done     = outreach_done( $log, $unsub );
$failures = outreach_failures( $failed );
$all      = outreach_candidates();

/*
 * Anything we have given up on is out of the queue for good; anything that
 * has failed fewer than OUTREACH_FAIL_MAX times stays in and gets another
 * go on a later run.
 */
$pending = array_values(
	array_filter(
		$all,
		static function ( array $r ) use ( $done, $failures ): bool {
			$e = strtolower( $r['email'] );
			if ( isset( $done[ $e ] ) ) {
				return false;
			}
			return ! ( isset( $failures[ $e ] ) && $failures[ $e ]['done'] );
		}
	)
);

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

if ( $failList ) {
	if ( ! $failures ) {
		exit( "No failures recorded.\n" );
	}
	printf( "%-38s %-4s %-8s %s\n", 'address', 'n', 'given up', 'reason' );
	foreach ( $failures as $email => $f ) {
		printf( "%-38s %-4d %-8s %s\n", $email, $f['n'], $f['done'] ? 'yes' : 'no', substr( $f['why'], 0, 70 ) );
	}
	exit( 0 );
}

if ( $status ) {
	$given_up = count( array_filter( $failures, static fn( array $f ): bool => $f['done'] ) );
	printf( "contactable now : %d\n", count( $all ) );
	printf( "already sent    : %d\n", count( $done ) );
	printf( "remaining       : %d\n", count( $pending ) );
	printf( "failed          : %d recorded, %d given up on (--failures to list)\n", count( $failures ), $given_up );
	printf( "armed           : %s\n", file_exists( $armed ) ? 'yes' : 'NO — cron will not send' );
	printf(
		"smtp user       : %s\n",
		defined( 'ORIA_SMTP_USER' ) && ORIA_SMTP_USER ? (string) ORIA_SMTP_USER : 'NOT DEFINED in wp-config.php'
	);
	printf(
		"smtp pass       : %s\n",
		defined( 'ORIA_SMTP_PASS' ) && ORIA_SMTP_PASS
			? sprintf( 'set (%d characters)', strlen( (string) ORIA_SMTP_PASS ) )
			: 'NOT DEFINED in wp-config.php'
	);
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
$GLOBALS['oria_smtp_debug'] = $debug;

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

			if ( $GLOBALS['oria_smtp_debug'] ?? false ) {
				// 2 = the full conversation with the server, which is the only
				// thing that says whether it refused the login, the port or
				// the sender.
				$phpmailer->SMTPDebug   = 2;
				$phpmailer->Debugoutput = static function ( $str, $level ): void {
					fwrite( STDERR, '  smtp: ' . rtrim( (string) $str ) . "\n" );
				};
			}
		}
	);
}

$sent = 0;
foreach ( $batch as $i => $row ) {
	$imp = (int) ( $impressions[ $row['slug'] ] ?? 0 );
	list( $subject, $body ) = outreach_message( $row, $imp );

	$to = '' !== $testTo ? $testTo : $row['email'];

	if ( ! $send ) {
		printf( "[dry] %-38s tier %d  %s\n", $to, $imp > 0 ? 1 : 2, $row['name'] );
		printf( "      %s\n", $subject );
		continue;
	}

	/*
	 * Ask DNS before asking SMTP. A domain that does not resolve cannot
	 * receive anything, and trying anyway earns a bounce against our own
	 * sending reputation for no possible gain.
	 */
	if ( '' === $testTo && ! outreach_deliverable( $row['email'] ) ) {
		$why = 'domain does not resolve (no MX or A record)';
		$n   = (int) ( $failures[ strtolower( $row['email'] ) ]['n'] ?? 0 ) + 1;
		$failures[ strtolower( $row['email'] ) ] = array(
			'at'   => gmdate( 'c' ),
			'name' => $row['name'],
			'n'    => $n,
			'done' => $n >= OUTREACH_FAIL_MAX,
			'why'  => $why,
		);
		printf( "skip %-38s %s (%s, attempt %d)\n", $row['email'], $row['name'], $why, $n );
		continue;
	}

	$GLOBALS['oria_mail_error'] = '';
	$ok                         = wp_mail( $to, $subject, $body );

	if ( '' !== $testTo ) {
		// A test send proves the route, not that this business was contacted.
		printf( $ok ? "test sent to %s (nothing logged)\n" : "test FAILED to %s\n", $testTo );
		break;
	}

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
		$why  = $GLOBALS['oria_mail_error'] ?: 'wp_mail() gave no reason; re-run with --debug for the SMTP conversation';
		$kind = outreach_fail_kind( $why );

		if ( 'session' === $kind ) {
			// Ours, not theirs: the next message would fail identically.
			fwrite( STDERR, sprintf( "FAILED %s (%s) — stopping\n  reason: %s\n", $row['email'], $row['name'], $why ) );
			break;
		}

		// Theirs: record it, and carry on down the list.
		$key = strtolower( $row['email'] );
		$n   = (int) ( $failures[ $key ]['n'] ?? 0 ) + 1;
		$failures[ $key ] = array(
			'at'   => gmdate( 'c' ),
			'name' => $row['name'],
			'n'    => $n,
			'done' => $n >= OUTREACH_FAIL_MAX,
			'why'  => $why,
		);
		fwrite(
			STDERR,
			sprintf(
				"failed %-38s %s (attempt %d%s)\n  reason: %s\n",
				$row['email'],
				$row['name'],
				$n,
				$n >= OUTREACH_FAIL_MAX ? ', giving up' : '',
				$why
			)
		);
		continue;
	}

	if ( $i < count( $batch ) - 1 ) {
		sleep( OUTREACH_THROTTLE );
	}
}

if ( $send ) {
	outreach_write_failures( $failed, $failures );

	$skipped = count( array_filter( $failures, static fn( array $f ): bool => $f['at'] >= gmdate( 'c', time() - 3600 ) ) );
	printf( "\n%d sent. %d remaining.\n", $sent, count( $pending ) - $sent );
	if ( $skipped ) {
		printf( "%d address(es) failed this run — see %s\n", $skipped, $failed );
	}
} else {
	printf( "\nDry run — %d message(s) shown, nothing sent.\n", count( $batch ) );
	printf( "If that's right: touch %s\n", $armed );
}
