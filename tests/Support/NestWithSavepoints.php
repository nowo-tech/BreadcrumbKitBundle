<?php

declare(strict_types=1);

namespace Nowo\BreadcrumbKitBundle\Tests\Support;

use Composer\InstalledVersions;
use Doctrine\DBAL\Connection;

/**
 * doctrine/dbal 3 nests transactions with savepoints only when asked (the application's `use_savepoints` setting);
 * doctrine/dbal 4 always does and deprecates the setting. Not part of the bundle.
 */
final class NestWithSavepoints
{
    public static function enable(Connection $connection): void
    {
        if (\version_compare(InstalledVersions::getVersion('doctrine/dbal') ?? '4.0.0', '4.0.0', '<')) {
            $connection->setNestTransactionsWithSavepoints(true);
        }
    }
}
