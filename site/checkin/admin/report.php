<?php
// Private report of all check-ins. Shows participants' emails, so it must
// only be reachable behind the password set up in admin/.htaccess.
// Add ?format=csv to download the raw check-ins as a spreadsheet file.
declare(strict_types=1);
require dirname(__DIR__) . '/lib.php';

// Safety net: if the web server ever stops applying admin/.htaccess,
// refuse to show anything rather than exposing emails.
$user = $_SERVER['REMOTE_USER'] ?? $_SERVER['REDIRECT_REMOTE_USER'] ?? $_SERVER['PHP_AUTH_USER'] ?? '';
if ($user === '') {
    http_response_code(403);
    header('Content-Type: text/plain; charset=utf-8');
    echo "Password protection is not active. See docs/CHECKIN.md.\n";
    exit;
}

header('Cache-Control: no-store');
header('X-Robots-Tag: noindex');

$records = llama_read_all();

if (($_GET['format'] ?? '') === 'csv') {
    header('Content-Type: text/csv; charset=utf-8');
    header('Content-Disposition: attachment; filename="llama-checkins-' . date('Y-m-d') . '.csv"');
    $out = fopen('php://output', 'w');
    fputcsv($out, ['time', 'mode', 'park_key', 'park', 'email', 'initials', 'source']);
    foreach ($records as $r) {
        fputcsv($out, [
            $r['t'] ?? '', $r['mode'], $r['park'], llama_park_name($r['park']),
            $r['email'], $r['initials'] ?? '', $r['src'] ?? '',
        ]);
    }
    fclose($out);
    exit;
}

$people = llama_challenge_people($records);

// Finished: verified by hand (people.json) or through the website check-in.
$webFinish = [];
foreach (llama_finishers($records) as $f) {
    if ($f['mode'] === 'challenge') {
        $webFinish[$f['email']] = $f['date'];
    }
}
function finish_status(string $email, array $webFinish): string
{
    $verified = llama_verified_finish($email);
    if ($verified !== null) {
        return 'Finished ' . us_date($verified) . ' (verified)';
    }
    if (isset($webFinish[$email])) {
        return 'Finished ' . us_date($webFinish[$email]) . ' (check-in)';
    }
    return '';
}
function us_date(?string $ymd): string
{
    if (!$ymd || !preg_match('/^(\d{4})-(\d{2})-(\d{2})$/', $ymd, $m)) {
        return (string)$ymd;
    }
    return (int)$m[2] . '/' . (int)$m[3] . '/' . $m[1];
}
// Finishers first, then by number of parks.
uasort($people, function ($a, $b) {
    return count($b['parks']) <=> count($a['parks']);
});
$finishedFirst = [];
$others = [];
foreach ($people as $email => $p) {
    if (finish_status($email, $webFinish) !== '') {
        $finishedFirst[$email] = $p;
    } else {
        $others[$email] = $p;
    }
}
$people = $finishedFirst + $others;
$ultra = llama_ultra_people($records);
$total = count(LLAMA_CHALLENGE_PARKS);
$finishers = $finishedFirst;

function show_time(?string $t): string
{
    if (!$t) {
        return 'old system (no date)';
    }
    try {
        return (new DateTime($t))->setTimezone(new DateTimeZone('America/New_York'))->format('M j, Y g:i a');
    } catch (Exception $e) {
        return $t;
    }
}
?>
<!doctype html>
<html lang="en">
<head>
<meta charset="utf-8">
<meta name="viewport" content="width=device-width, initial-scale=1">
<meta name="robots" content="noindex">
<title>LLAMA Check-in Report</title>
<style>
  body { font-family: system-ui, sans-serif; margin: 0 auto; max-width: 1100px; padding: 16px; color: #1f2a22; background: #f6f7f3; }
  h1, h2 { font-weight: 700; }
  .summary { display: flex; flex-wrap: wrap; gap: 24px; margin: 12px 0 24px; }
  .summary div { background: #fff; border: 1px solid #d9ded6; border-radius: 8px; padding: 10px 16px; }
  .summary b { display: block; font-size: 24px; }
  .wrap { overflow-x: auto; background: #fff; border: 1px solid #d9ded6; border-radius: 8px; margin-bottom: 28px; }
  table { border-collapse: collapse; width: 100%; font-size: 14px; }
  th, td { text-align: left; padding: 8px 10px; border-bottom: 1px solid #e6e9e3; vertical-align: top; }
  th { background: #eef2ec; }
  .done { background: #e3efe6; }
  .num { text-align: right; font-variant-numeric: tabular-nums; }
  .muted { color: #5b6a5f; }
</style>
</head>
<body>
<h1>LLAMA Check-in Report</h1>
<p class="muted">Private: contains participants' email addresses. Do not share this page or forward the CSV.</p>

<div class="summary">
  <div><b><?= count($records) ?></b> check-ins</div>
  <div><b><?= count($people) ?></b> people (challenge)</div>
  <div><b><?= count($finishers) ?></b> finishers with check-in records</div>
  <div><b><?= count($ultra) ?></b> people (ultra)</div>
  <div><a href="?format=csv">Download all check-ins (CSV)</a></div>
</div>

<h2>LLAMA Challenge</h2>
<p class="muted">Finishers are highlighted and listed first. "Verified" finishers completed the challenge before the new check-in (mostly by photo), so their check-ins only show part of their visits; they are set in ~/llama-data/people.json. People who reach <?= LLAMA_FINISH_COUNT ?> through website check-ins are finished automatically, added to the Finishers list on challenge.html, and an email notice is sent so their patch can be mailed. Finishers are not shown on the public leaderboard.</p>
<div class="wrap"><table>
  <tr><th>Initials</th><th>Email</th><th>Status</th><th class="num">Parks checked in</th><th>Challenge parks visited</th><th>Other locations</th><th>Last check-in</th></tr>
  <?php foreach ($people as $email => $p): $status = finish_status($email, $webFinish); ?>
  <tr class="<?= $status !== '' ? 'done' : '' ?>">
    <td><?= llama_h($p['initials']) ?></td>
    <td><?= implode('<br>', array_map('llama_h', $p['emails'])) ?></td>
    <td><?= llama_h($status) ?></td>
    <td class="num"><?= count($p['parks']) ?> / <?= $total ?></td>
    <td><?= llama_h(implode(', ', array_map('llama_park_name', $p['parks']))) ?></td>
    <td class="muted"><?= llama_h(implode(', ', array_map('llama_park_name', $p['other']))) ?></td>
    <td><?= llama_h(show_time($p['last'])) ?></td>
  </tr>
  <?php endforeach; ?>
</table></div>

<h2>Ultra LLAMA Challenge</h2>
<p class="muted">Most challenge parks visited in a single day. <?= LLAMA_FINISH_COUNT ?> in one day completes it.</p>
<div class="wrap"><table>
  <tr><th>Initials</th><th>Email</th><th class="num">Best day</th><th>Date</th><th>All days (parks per day)</th></tr>
  <?php foreach ($ultra as $email => $u): ?>
  <tr class="<?= $u['best_count'] >= LLAMA_FINISH_COUNT ? 'done' : '' ?>">
    <td><?= llama_h($u['initials']) ?></td>
    <td><?= llama_h($email) ?></td>
    <td class="num"><?= $u['best_count'] ?></td>
    <td><?= llama_h($u['best_day']) ?></td>
    <td class="muted"><?php
      $parts = [];
      foreach ($u['days'] as $day => $n) { $parts[] = "$day: $n"; }
      echo llama_h(implode('; ', $parts));
    ?></td>
  </tr>
  <?php endforeach; ?>
</table></div>
</body>
</html>
