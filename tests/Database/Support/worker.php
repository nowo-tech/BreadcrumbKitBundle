<?php

declare(strict_types=1);

/**
 * Worker process of the real-server load tests (a "second request" on its own connection).
 *
 * php worker.php <database url> <command> [arguments]
 *
 * - `import-loop <prefix> <count> <strategy> <tag>`: waits for GO on stdin, then imports `<count>` collections
 *   `<prefix>0..` (a tree of ten items named after `<tag>`), prints one line per import: `ok` or the failure class;
 * - `import-same <code> <count> <strategy> <tag>`: waits for GO, then imports collection `<code>` `<count>` times (the
 *   tree is named after `<tag>`);
 * - `hold <collectionId>`: opens a transaction, locks the collection row, prints READY, waits for a line on stdin, commits.
 */

use Nowo\BreadcrumbKitBundle\Tests\Support\BreadcrumbStore;

require \dirname(__DIR__, 3) . '/vendor/autoload.php';

$arguments = $argv;
array_shift($arguments);
$url     = (string) array_shift($arguments);
$command = (string) array_shift($arguments);
$store   = new BreadcrumbStore($url, createSchema: false);

/**
 * @return array<string, mixed>
 */
$payload = static function (string $code, string $tag): array {
    $items = [];
    for ($i = 0; $i < 10; ++$i) {
        $items[] = ['routeName' => $tag . $i, 'label' => $tag];
    }

    return ['collection' => ['code' => $code], 'items' => $items];
};

if ('hold' === $command) {
    $id = (int) array_shift($arguments);
    $store->connection()->beginTransaction();
    $store->connection()->executeQuery('SELECT id FROM dashboard_breadcrumb_collection WHERE id = ' . $id . ' FOR UPDATE')->fetchAllAssociative();
    fwrite(\STDOUT, "READY\n");
    fgets(\STDIN);
    $store->connection()->commit();
    exit(0);
}

$code  = (string) array_shift($arguments);
$count = (int) array_shift($arguments);
$strategy = (string) array_shift($arguments);
$tag   = (string) array_shift($arguments);
fwrite(\STDOUT, "READY\n");
fgets(\STDIN);
$importer = $store->importer();
for ($i = 0; $i < $count; ++$i) {
    try {
        $store->entityManager->clear();
        $target = 'import-loop' === $command ? $code . $i : $code;
        $importer->import($payload($target, $tag), $strategy);
        fwrite(\STDOUT, "ok\n");
    } catch (Throwable $failure) {
        fwrite(\STDOUT, $failure::class . "\n");
        if (!$store->entityManager->isOpen()) {
            fwrite(\STDOUT, "closed\n");

            break;
        }
    }
}
exit(0);
