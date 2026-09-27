<?php
declare(strict_types=1);

namespace App;

/** A source refused the request (401/403/404) - skip it, keep syncing the rest. */
final class SyncBlockedException extends \RuntimeException
{
}
