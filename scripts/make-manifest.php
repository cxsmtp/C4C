<?php
// Writes SHA256SUMS: the SHA-256 of every shipped file, in the standard `sha256sum` format.
// Settings > Updates compares the live files with it to prove they are the released ones,
// and it can be checked by hand on any machine with: sha256sum -c SHA256SUMS
//   php scripts/make-manifest.php           write
//   php scripts/make-manifest.php --check   fail if SHA256SUMS is out of date (used by CI)
declare(strict_types=1);
$root = dirname(__DIR__);
$include = ['index.php', 'cron.php', '.htaccess', 'robots.txt', 'VERSION', 'CHANGELOG.md', 'app', 'assets'];
$files = [];
foreach ($include as $p) {
    $full = "$root/$p";
    if (is_file($full)) { $files[$p] = hash_file('sha256', $full); continue; }
    $it = new RecursiveIteratorIterator(new RecursiveDirectoryIterator($full, FilesystemIterator::SKIP_DOTS));
    foreach ($it as $f) {
        $rel = substr($f->getPathname(), strlen($root) + 1);
        if ($rel === 'app/config.php') continue; // per-server secrets, never shipped
        $files[$rel] = hash_file('sha256', $f->getPathname());
    }
}
foreach (['storage/.htaccess'] as $p) if (is_file("$root/$p")) $files[$p] = hash_file('sha256', "$root/$p");
ksort($files);
$sums = '';
foreach ($files as $p => $h) $sums .= "$h  $p\n";
if (in_array('--check', $argv, true)) {
    if ((string)@file_get_contents("$root/SHA256SUMS") !== $sums) {
        fwrite(STDERR, "SHA256SUMS is out of date. Run: php scripts/make-manifest.php\n");
        exit(1);
    }
    echo 'SHA256SUMS matches ' . count($files) . " files\n";
    exit(0);
}
file_put_contents("$root/SHA256SUMS", $sums);
echo 'Wrote SHA256SUMS for ' . count($files) . ' files, version ' . trim((string)file_get_contents("$root/VERSION")) . "\n";
