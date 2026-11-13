<?php
declare(strict_types=1);

const INVASIVE_SPECIES = [
    'knotweed', 'garlic_mustard', 'loosestrife', 'phragmites', 'barberry',
    'multiflora_rose', 'bittersweet', 'tree_of_heaven', 'buckthorn',
    'honeysuckle', 'swallowwort', 'water_chestnut', 'hogweed', 'unsure',
];
const INVASIVE_TIP_SPECIES = [
    'knotweed', 'garlic_mustard', 'loosestrife', 'phragmites', 'barberry',
    'multiflora_rose', 'bittersweet', 'tree_of_heaven', 'buckthorn',
    'honeysuckle', 'swallowwort', 'water_chestnut', 'hogweed',
];

function invasives_data_root(): string
{
    $root = getenv('LLAMA_DATA_DIR');
    if ($root === false || $root === '') {
        $home = getenv('HOME');
        if (($home === false || $home === '') && function_exists('posix_getpwuid')) {
            $user = posix_getpwuid(posix_geteuid());
            $home = is_array($user) ? ($user['dir'] ?? '') : '';
        }
        if ($home === '') {
            throw new RuntimeException('Cannot determine the private data directory.');
        }
        $root = rtrim($home, '/') . '/llama-data';
    }
    return rtrim($root, '/') . '/invasives';
}

function invasives_dirs(): array
{
    $root = invasives_data_root();
    $parent = dirname($root);
    if (is_link($parent)) {
        throw new RuntimeException('Private data path cannot be a symbolic link.');
    }
    if (!is_dir($parent) && !mkdir($parent, 0700, true) && !is_dir($parent)) {
        throw new RuntimeException('Cannot create the private data directory.');
    }
    @chmod($parent, 0700);
    $dirs = [
        'root' => $root,
        'pending_photos' => $root . '/pending-photos',
        'approved_photos' => $root . '/approved-photos',
    ];
    foreach ($dirs as $dir) {
        if (is_link($dir)) {
            throw new RuntimeException('Private data path cannot be a symbolic link.');
        }
        if (!is_dir($dir) && !mkdir($dir, 0700) && !is_dir($dir)) {
            throw new RuntimeException('Cannot create private data directory.');
        }
        @chmod($dir, 0700);
    }
    return $dirs;
}

function invasives_read_jsonl(string $name): array
{
    $dirs = invasives_dirs();
    $path = $dirs['root'] . '/' . $name;
    if (!is_file($path)) {
        return [];
    }
    $fh = fopen($path, 'rb');
    if ($fh === false) {
        throw new RuntimeException('Cannot read private report data.');
    }
    $rows = [];
    flock($fh, LOCK_SH);
    while (($line = fgets($fh)) !== false) {
        $row = json_decode(trim($line), true);
        if (is_array($row)) {
            $rows[] = $row;
        }
    }
    flock($fh, LOCK_UN);
    fclose($fh);
    return $rows;
}

function invasives_append_jsonl(string $name, array $row): void
{
    $dirs = invasives_dirs();
    $path = $dirs['root'] . '/' . $name;
    $json = json_encode($row, JSON_UNESCAPED_SLASHES | JSON_INVALID_UTF8_SUBSTITUTE);
    if (!is_string($json)) {
        throw new RuntimeException('Cannot encode report data.');
    }
    $fh = fopen($path, 'ab');
    if ($fh === false) {
        throw new RuntimeException('Cannot write private report data.');
    }
    try {
        if (!flock($fh, LOCK_EX) || fwrite($fh, $json . "\n") === false || !fflush($fh)) {
            throw new RuntimeException('Cannot save report data.');
        }
        @chmod($path, 0600);
    } finally {
        flock($fh, LOCK_UN);
        fclose($fh);
    }
}

function invasives_find_pending(string $id): ?array
{
    if (!preg_match('/^[a-f0-9]{32}$/', $id)) {
        return null;
    }
    foreach (invasives_read_jsonl('pending.jsonl') as $row) {
        if (($row['id'] ?? '') === $id) {
            return $row;
        }
    }
    return null;
}

function invasives_remove_from_queue(string $queue, string $id): bool
{
    if (!in_array($queue, ['pending.jsonl', 'pending-tips.jsonl'], true)
        || !preg_match('/^[a-f0-9]{32}$/', $id)) {
        return false;
    }
    $dirs = invasives_dirs();
    $path = $dirs['root'] . '/' . $queue;
    if (!is_file($path)) {
        return false;
    }
    $fh = fopen($path, 'c+');
    if ($fh === false) {
        throw new RuntimeException('Cannot update the review queue.');
    }
    try {
        if (!flock($fh, LOCK_EX)) {
            throw new RuntimeException('Cannot lock the review queue.');
        }
        rewind($fh);
        $keep = [];
        $removed = false;
        while (($line = fgets($fh)) !== false) {
            $row = json_decode(trim($line), true);
            if (is_array($row) && ($row['id'] ?? '') === $id && !$removed) {
                $removed = true;
                continue;
            }
            $keep[] = rtrim($line, "\r\n");
        }
        if ($removed) {
            rewind($fh);
            if (!ftruncate($fh, 0)) {
                throw new RuntimeException('Cannot update the review queue.');
            }
            foreach ($keep as $line) {
                if (fwrite($fh, $line . "\n") === false) {
                    throw new RuntimeException('Cannot update the review queue.');
                }
            }
            fflush($fh);
            @chmod($path, 0600);
        }
        return $removed;
    } finally {
        flock($fh, LOCK_UN);
        fclose($fh);
    }
}

function invasives_remove_pending(string $id): bool
{
    return invasives_remove_from_queue('pending.jsonl', $id);
}

function invasives_decide(string $id, string $decision): bool
{
    $row = invasives_find_pending($id);
    if ($row === null) {
        return false;
    }
    $dirs = invasives_dirs();
    $photo = $dirs['pending_photos'] . '/' . $id . '.jpg';
    if ($decision === 'approve') {
        if (!is_file($photo)) {
            throw new RuntimeException('The private report photo is missing.');
        }
        $approvedPhoto = $dirs['approved_photos'] . '/' . $id . '.jpg';
        if (!rename($photo, $approvedPhoto)) {
            throw new RuntimeException('Cannot move the approved photo.');
        }
        $row['approved_at'] = gmdate('Y-m-d\TH:i:s\Z');
        try {
            invasives_append_jsonl('approved.jsonl', $row);
        } catch (Throwable $e) {
            @rename($approvedPhoto, $photo);
            throw $e;
        }
    } elseif ($decision === 'reject') {
        invasives_append_jsonl('rejected.jsonl', [
            'id' => $id,
            'rejected_at' => gmdate('Y-m-d\TH:i:s\Z'),
        ]);
    } else {
        throw new InvalidArgumentException('Unknown review decision.');
    }

    if (!invasives_remove_pending($id)) {
        throw new RuntimeException('The report was reviewed but remains in the queue; ask Bill to check the queue.');
    }
    if ($decision === 'reject' && is_file($photo) && !unlink($photo)) {
        error_log('LLAMA invasive rejection: could not remove private photo ' . $id);
    }
    return true;
}

function invasives_find_pending_tip(string $id): ?array
{
    if (!preg_match('/^[a-f0-9]{32}$/', $id)) {
        return null;
    }
    foreach (invasives_read_jsonl('pending-tips.jsonl') as $row) {
        if (($row['id'] ?? '') === $id) {
            return $row;
        }
    }
    return null;
}

function invasives_decide_tip(string $id, string $decision): bool
{
    $row = invasives_find_pending_tip($id);
    if ($row === null) {
        return false;
    }
    if ($decision === 'approve') {
        $row['approved_at'] = gmdate('Y-m-d\\TH:i:s\\Z');
        invasives_append_jsonl('approved-tips.jsonl', $row);
    } elseif ($decision === 'reject') {
        invasives_append_jsonl('rejected-tips.jsonl', [
            'id' => $id,
            'rejected_at' => gmdate('Y-m-d\\TH:i:s\\Z'),
        ]);
    } else {
        throw new InvalidArgumentException('Unknown tip review decision.');
    }
    if (!invasives_remove_from_queue('pending-tips.jsonl', $id)) {
        throw new RuntimeException('The tip was reviewed but remains in the queue; ask Bill to check the queue.');
    }
    return true;
}
