<?php

declare(strict_types=1);

namespace Nowo\BreadcrumbKitBundle\Command;

/**
 * Test seam: an unqualified file_get_contents() inside this namespace resolves here first. It delegates to the real
 * function for a file named `*.unreadable` (the "readable file that cannot be read" race of the import
 * command).
 */
function file_get_contents(string $filename): string|false
{
    if (str_ends_with($filename, '.unreadable')) {
        return false;
    }

    // By name, so no code-style rule can turn the call into one that resolves to this function again.
    return \call_user_func('file_get_contents', $filename);
}
