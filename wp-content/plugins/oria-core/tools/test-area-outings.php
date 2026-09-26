<?php
/**
 * Checks the area guide's outing rules (oria-v4/inc/area-guide.php) against
 * the live Fremantle data: no clinical care or series as a day-out stop, no
 * nutrition practice as a meal stop, set-day sessions only in a plan that
 * names the day, and a plan with one bad stop dropped whole.
 *
 * Run: wp eval-file wp-content/plugins/oria-core/tools/test-area-outings.php
 */
use Oria\V4\Area;
$t = get_term_by('slug','fremantle','area');
$rows = Area\rows($t); $g = Area\guide($t); $ix = Area\by_slug($rows); $pl = (array) $g['places'];
$cases = array(
  'dietitian as meal'        => array(array('role'=>'refresh','listing'=>'essence-of-eating-fremantle'), '', 'listing is not a venue'),
  'cafe as meal'             => array(array('role'=>'refresh','listing'=>'moore-and-moore-fremantle'), '', ''),
  'dietitian as experience'  => array(array('role'=>'experience','listing'=>'essence-of-eating-fremantle'), '', 'clinical care'),
  'twelve-series treatment'  => array(array('role'=>'experience','listing'=>'fascia-space-fremantle'), '', 'series or assessment first'),
  'Saturday-only, no day'    => array(array('role'=>'group','listing'=>'meditationhq-sunrise-saturdays-fremantle','join'=>'x'), '', 'runs on a set day the plan does not name'),
  'Saturday-only, Saturday'  => array(array('role'=>'group','listing'=>'meditationhq-sunrise-saturdays-fremantle','join'=>'x'), 'Saturday mornings', ''),
  'group without join'       => array(array('role'=>'group','listing'=>'resonate-mens-collective'), 'monthly', 'group without joining details'),
  'speech pathology'         => array(array('role'=>'experience','listing'=>'fremantle-speech-pathology'), '', 'clinical care'),
  'listing from elsewhere'   => array(array('role'=>'experience','listing'=>'yoga-lab'), '', 'listing not in this area'),
  'sauna one-off'            => array(array('role'=>'experience','listing'=>'the-neighbourhood-sauna-fremantle'), '', ''),
  'place wrong role'         => array(array('role'=>'heritage','place'=>'bathers-beach'), '', 'place is not heritage'),
  'unknown place'            => array(array('role'=>'outdoors','place'=>'nowhere'), '', 'unknown place'),
);
$fail = 0;
foreach ($cases as $name => $c) { $got = Area\stop_problem($c[0], $ix, $pl, $c[1]); $ok = $got === $c[2]; $fail += $ok ? 0 : 1; printf("%s %-26s got=%s\n", $ok?'PASS':'FAIL', $name, $got===''?'(ok)':$got); }
$o = Area\outings($rows, $g);
echo "outings: ", count($o), "\n";
foreach ($o as $p) { echo " - {$p['name']} ({$p['when']}) checked {$p['checked']}: ", implode(' -> ', array_map(fn($s)=>$s['label'].': '.$s['name'], $p['stops'])), "\n   ", $p['directions'], "\n"; }
// A record with one bad stop drops whole.
$g2 = $g; $g2['outings'][1]['stops'][2]['listing'] = 'essence-of-eating-fremantle';
echo "with dietitian swapped in: ", count(Area\outings($rows, $g2)), " outing(s)\n";
echo "explore: ", implode(' | ', array_map(fn($e)=>$e['label'].': '.$e['name'], Area\explore($rows,$g))), "\n";
echo "elsewhere: ", count(Area\elsewhere($rows,$g)), "\n";
echo $fail ? "FAILURES: $fail\n" : "all stop checks pass\n";
