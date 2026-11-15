<?php

/*
 * Lists the classes of the source folder and the classes of the source folder each one uses.
 * Usage: php graph.php <project root> <config json>. Prints JSON: nodes (id, context, file, hash) and edges ([from, to]).
 * It runs against both the base and the head of the pull request with the same extractor, php-dep, pinned by composer.lock.
 * A class counts as used when its name appears in the code once resolved: a type, a new, a static call,
 * an instanceof, an attribute, a ::class. Imports alone and docblocks do not count.
 * The source folder and the root namespace come from the environment, filled by action.yml from the inputs;
 * the contexts come from the config file.
 */

use PhpDep\Api\PhpDep;

require __DIR__.'/../vendor/autoload.php';

// php-dep gives real paths, so the root is resolved the same way to cut them.
$root = rtrim(realpath($argv[1]) ?: $argv[1], '/');
$config = (require __DIR__.'/config.php')($argv[2]);
$source = trim(getenv('WHAT_MOVED_SOURCE'), '/');
$namespace = trim(getenv('WHAT_MOVED_NAMESPACE'), '\\').'\\';

// Each declared prefix and its context, the longest first so that Carnet\Notes wins over Carnet.
$prefixes = [];

foreach ($config['contexts'] as $name => $declared) {
    foreach ($declared['prefixes'] as $prefix) {
        $prefixes[trim($prefix, '\\')] = $name;
    }
}

uksort($prefixes, fn (string $a, string $b): int => strlen($b) <=> strlen($a));
$matches = fn (string $relative, string $prefix): bool => $relative === $prefix || str_starts_with($relative, $prefix.'\\');

// The context of a class, from its name without the root namespace: the one of the longest declared prefix that
// matches it, or else its first segment.
$context = function (string $class) use ($namespace, $config, $prefixes, $matches): ?string {
    if (! str_starts_with($class, $namespace)) {
        return null;
    }

    $relative = substr($class, strlen($namespace));

    foreach ($config['exclude'] as $prefix) {
        if ($matches($relative, trim($prefix, '\\'))) {
            return null;
        }
    }

    foreach ($prefixes as $prefix => $name) {
        if ($matches($relative, $prefix)) {
            return $name;
        }
    }

    return explode('\\', $relative)[0];
};

// php-dep extracts the dependencies; a file it cannot read stays off the map, said in the logs.
$analysis = PhpDep::analyze($root.'/'.$source);

foreach ($analysis->parseErrors() as $file => $reason) {
    fwrite(STDERR, sprintf("Skipped %s: %s\n", substr($file, strlen($root) + 1), $reason));
}

$nodes = [];
$edges = [];

foreach ($analysis->classes() as $id) {
    $from = $context($id);

    if ($from === null) {
        continue;
    }

    $file = $analysis->fileOf($id);
    $nodes[] = ['id' => $id, 'context' => $from, 'file' => substr($file, strlen($root) + 1), 'hash' => sha1_file($file)];

    foreach ($analysis->dependenciesOf($id) as $used) {
        if ($analysis->isDeclared($used) && $context($used) !== null) {
            $edges[] = [$id, $used];
        }
    }
}

usort($nodes, fn (array $a, array $b): int => strcmp($a['id'], $b['id']));
sort($edges);

echo json_encode(['nodes' => $nodes, 'edges' => $edges], JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES | JSON_THROW_ON_ERROR), "\n";
