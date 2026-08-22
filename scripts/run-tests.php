<?php

/**
 * Test runner with automatic parallel → sequential fallback.
 *
 * Default `composer test` runs the suite as fast as the environment allows:
 *   - Paratest installed (vendor/bin/paratest) → run in parallel; each worker
 *     clones the test DB so tests stay isolated.
 *   - Otherwise → fall back to the sequential suite (the safe default).
 *
 * Pass `--sequential` to force sequential, or `--parallel` to force parallel
 * (errors if Paratest is unavailable). Extra args are forwarded to `artisan test`.
 */
$forceParallel = in_array('--parallel', $argv, true);
$forceSequential = in_array('--sequential', $argv, true);

$stdArgs = array_values(array_filter($argv, fn ($a) => ! in_array($a, ['--parallel', '--sequential'], true)));
$forward = array_slice($stdArgs, 1); // drop script name

function paratestInstalled(): bool
{
    $candidates = [
        __DIR__.'/../vendor/bin/paratest',
        __DIR__.'/../vendor/brianium/paratest/bin/paratest',
    ];

    foreach ($candidates as $bin) {
        if (is_file($bin) && is_executable($bin)) {
            return true;
        }
    }

    return false;
}

$useParallel = $forceParallel || (! $forceSequential && paratestInstalled());
$mode = $useParallel ? 'parallel' : 'sequential';

fwrite(STDERR, sprintf(
    "▶ Running tests in %s mode%s\n",
    $mode,
    $useParallel ? '' : ' (Paratest unavailable — using sequential fallback)',
));

if ($forceParallel && ! $useParallel) {
    fwrite(STDERR, "✖ Paratest is not installed. Run `composer require --dev brianium/paratest`.\n");
    exit(1);
}

$cmd = array_merge(['php', 'artisan', 'test'], $useParallel ? ['--parallel'] : [], $forward);

$process = proc_open(
    implode(' ', array_map('escapeshellarg', $cmd)),
    [STDIN, STDOUT, STDERR],
    $pipes,
);

exit(proc_close($process));
