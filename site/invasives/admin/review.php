<?php
declare(strict_types=1);
require __DIR__ . '/../api/lib.php';

header('Cache-Control: no-store');
header('X-Content-Type-Options: nosniff');
session_set_cookie_params([
    'secure' => !empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off',
    'httponly' => true,
    'samesite' => 'Strict',
]);
session_start();
if (!isset($_SESSION['invasives_csrf'])) {
    $_SESSION['invasives_csrf'] = bin2hex(random_bytes(32));
}

$message = '';
if (($_SERVER['REQUEST_METHOD'] ?? '') === 'POST') {
    $token = (string)($_POST['csrf'] ?? '');
    if (!hash_equals((string)$_SESSION['invasives_csrf'], $token)) {
        http_response_code(400);
        $message = 'This page expired. Reload it and try again.';
    } else {
        $id = (string)($_POST['id'] ?? '');
        $decision = (string)($_POST['decision'] ?? '');
        $kind = (string)($_POST['kind'] ?? 'report');
        try {
            $reviewed = $kind === 'tip'
                ? invasives_decide_tip($id, $decision)
                : ($kind === 'report' ? invasives_decide($id, $decision) : false);
            if (!$reviewed) {
                $message = $kind === 'tip'
                    ? 'That tip is no longer in the pending queue.'
                    : 'That report is no longer in the pending queue.';
            } else {
                if ($kind === 'tip') {
                    $message = $decision === 'approve'
                        ? 'Tip approved. It will appear in the guide after the next scheduled update.'
                        : 'Tip rejected.';
                } else {
                    $message = $decision === 'approve'
                        ? 'Report approved. It will appear on the public map after the next scheduled update.'
                        : 'Report rejected and its private photo removed.';
                }
            }
        } catch (Throwable $e) {
            error_log('LLAMA invasive review failed: ' . $e->getMessage());
            http_response_code(500);
            $message = 'The review action could not be completed. Ask Bill to check the server log.';
        }
    }
}

try {
    $pending = invasives_read_jsonl('pending.jsonl');
} catch (Throwable $e) {
    error_log('LLAMA invasive review queue read failed: ' . $e->getMessage());
    $pending = [];
    $message = 'The review queue could not be read. Ask Bill to check the server log.';
}
try {
    $pendingTips = invasives_read_jsonl('pending-tips.jsonl');
} catch (Throwable $e) {
    error_log('LLAMA invasive tip queue read failed: ' . $e->getMessage());
    $pendingTips = [];
    $message = 'The tip review queue could not be read. Ask Bill to check the server log.';
}
function inv_h(string $value): string { return htmlspecialchars($value, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8'); }
function inv_safe_link(string $value): ?string
{
    $parts = parse_url($value);
    $scheme = is_array($parts) ? strtolower((string)($parts['scheme'] ?? '')) : '';
    return is_array($parts) && filter_var($value, FILTER_VALIDATE_URL) && in_array($scheme, ['http', 'https'], true)
        && !isset($parts['user']) && !isset($parts['pass']) ? $value : null;
}
function inv_species_name(string $id): string
{
    $names = [
        'knotweed' => 'Japanese knotweed', 'garlic_mustard' => 'Garlic mustard',
        'loosestrife' => 'Purple loosestrife', 'phragmites' => 'Common reed (Phragmites)',
        'barberry' => 'Japanese barberry', 'multiflora_rose' => 'Multiflora rose',
        'bittersweet' => 'Oriental bittersweet', 'tree_of_heaven' => 'Tree-of-heaven',
        'buckthorn' => 'Buckthorn', 'honeysuckle' => 'Bush honeysuckle',
        'swallowwort' => 'Swallow-wort', 'water_chestnut' => 'Water chestnut',
        'hogweed' => 'Giant hogweed',
    ];
    return $names[$id] ?? 'Unknown plant';
}
?>
<!doctype html>
<html lang="en">
<head>
<meta charset="utf-8">
<meta name="viewport" content="width=device-width, initial-scale=1">
<meta name="robots" content="noindex, nofollow">
<title>Invasive report review | LLAMA</title>
<style>
  body { margin: 0; padding: 20px; background: #f6f7f3; color: #1f2a22; font: 16px/1.45 system-ui, sans-serif; }
  main { max-width: 900px; margin: 0 auto; }
  article { display: grid; grid-template-columns: minmax(180px, 260px) minmax(0, 1fr); gap: 18px; background: #fff; border: 1px solid #d9ded6; border-radius: 12px; padding: 16px; margin: 14px 0; }
  article img { width: 100%; max-height: 320px; object-fit: contain; border-radius: 8px; background: #e3efe6; }
  article.tip-review { grid-template-columns: minmax(0, 1fr); }
  h1 { font-size: 24px; } h2 { font-size: 18px; margin: 0 0 8px; }
  .muted { color: #5b6a5f; font-size: 14px; }
  .actions { display: flex; gap: 8px; margin-top: 14px; }
  button { border: 0; border-radius: 8px; padding: 10px 14px; font: inherit; font-weight: 650; cursor: pointer; }
  .approve { background: #2c7a4b; color: #fff; } .reject { background: #f8e6e1; color: #a3361f; }
  .notice { background: #e3efe6; border-radius: 8px; padding: 10px 12px; }
  @media (max-width: 620px) { article { grid-template-columns: 1fr; } article img { max-height: 420px; } }
</style>
</head>
<body>
<main>
  <h1>Invasive tracker review</h1>
  <p class="muted">Review reports and community tips. Approved items become public after the next scheduled update. Do not publish personal details; public reports show initials only.</p>
  <?php if ($message !== ''): ?><p class="notice" role="status"><?= inv_h($message) ?></p><?php endif; ?>
  <h2>Plant reports</h2>
  <?php if (!$pending): ?>
    <p>No reports are waiting for review.</p>
  <?php else: ?>
    <?php foreach (array_reverse($pending) as $report): ?>
      <?php $id = (string)($report['id'] ?? ''); ?>
      <article>
        <img src="photo.php?id=<?= rawurlencode($id) ?>" alt="Photo submitted with this report">
        <div>
          <h2><?= inv_h((string)($report['species'] ?? 'Unknown plant')) ?></h2>
          <p><?= nl2br(inv_h((string)($report['notes'] ?? ''))) ?></p>
          <p class="muted">Seen: <?= inv_h((string)($report['date'] ?? '')) ?><br>
          Near coordinates: <?= inv_h((string)($report['lat'] ?? '')) ?>, <?= inv_h((string)($report['lng'] ?? '')) ?><br>
          Initials: <?= inv_h((string)($report['initials'] ?? '')) ?></p>
          <form method="post" class="actions">
            <input type="hidden" name="csrf" value="<?= inv_h((string)$_SESSION['invasives_csrf']) ?>">
            <input type="hidden" name="id" value="<?= inv_h($id) ?>">
            <button class="approve" type="submit" name="decision" value="approve">Approve</button>
            <button class="reject" type="submit" name="decision" value="reject">Reject</button>
          </form>
        </div>
      </article>
    <?php endforeach; ?>
  <?php endif; ?>
  <h2>Community mitigation tips</h2>
  <?php if (!$pendingTips): ?>
    <p>No tips are waiting for review.</p>
  <?php else: ?>
    <?php foreach (array_reverse($pendingTips) as $tip): ?>
      <?php
        $id = (string)($tip['id'] ?? '');
        $source = inv_safe_link((string)($tip['source'] ?? ''));
      ?>
      <article class="tip-review">
        <div>
          <h2><?= inv_h(inv_species_name((string)($tip['species'] ?? ''))) ?></h2>
          <p><?= nl2br(inv_h((string)($tip['tip'] ?? ''))) ?></p>
          <?php if ($source !== null): ?><p>Source: <a href="<?= inv_h($source) ?>" target="_blank" rel="noreferrer"><?= inv_h($source) ?></a></p><?php endif; ?>
          <p class="muted">Check that the tip is safe, accurate, and appropriate for the species and location. Reject advice that encourages touching hazardous plants, unauthorized removal, or unsupervised herbicide use.</p>
          <form method="post" class="actions">
            <input type="hidden" name="csrf" value="<?= inv_h((string)$_SESSION['invasives_csrf']) ?>">
            <input type="hidden" name="kind" value="tip">
            <input type="hidden" name="id" value="<?= inv_h($id) ?>">
            <button class="approve" type="submit" name="decision" value="approve">Approve</button>
            <button class="reject" type="submit" name="decision" value="reject">Reject</button>
          </form>
        </div>
      </article>
    <?php endforeach; ?>
  <?php endif; ?>
</main>
</body>
</html>
