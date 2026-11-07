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
uasort($people, function ($a, $b) {
    return count($b['parks']) <=> count($a['parks']);
});
$ultra = llama_ultra_people($records);
$total = count(LLAMA_CHALLENGE_PARKS);
$finishers = array_filter($people, function ($p) {
    return count($p['parks']) >= LLAMA_FINISH_COUNT;
});

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
  <div><b><?= count($finishers) ?></b> with <?= LLAMA_FINISH_COUNT ?>+ of <?= $total ?> parks</div>
  <div><b><?= count($ultra) ?></b> people (ultra)</div>
  <div><a href="?format=csv">Download all check-ins (CSV)</a></div>
</div>

<h2>LLAMA Challenge</h2>
<p class="muted">Highlighted rows have reached <?= LLAMA_FINISH_COUNT ?> parks. Check their photos or check-ins, then add them to the Finishers table on challenge.html.</p>
<div class="wrap"><table>
  <tr><th>Initials</th><th>Email</th><th class="num">Parks</th><th>Challenge parks visited</th><th>Other locations</th><th>Last check-in</th></tr>
  <?php foreach ($people as $email => $p): ?>
  <tr class="<?= count($p['parks']) >= LLAMA_FINISH_COUNT ? 'done' : '' ?>">
    <td><?= llama_h($p['initials']) ?></td>
    <td><?= llama_h($email) ?></td>
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
