<?php

declare(strict_types=1);

use Rector\CodingStyle\Rector\FuncCall\FunctionFirstClassCallableRector;
use Rector\Config\RectorConfig;

return RectorConfig::configure()
    ->withPaths([__DIR__ . '/src', __DIR__ . '/tests'])
    ->withSkip([
        __DIR__ . '/demo',
        // The file declares its own namespaced file_get_contents() override and calls the real one by name on purpose;
        // `file_get_contents(...)` would resolve to the override itself (infinite recursion), so the rule must not run here.
        FunctionFirstClassCallableRector::class => [
            __DIR__ . '/tests/Support/CommandFunctionOverrides.php',
        ],
    ])
    // Machine-wide cap: at most 2 worker processes; generous timeout for slow (WSL2 / CI) hosts.
    ->withParallel(300, 2, 10)
    ->withPhpSets(php82: true);
