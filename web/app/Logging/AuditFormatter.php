<?php

namespace App\Logging;

use Monolog\Formatter\LineFormatter;

/**
 * Audit lines look like: 2026-09-20 14:12:13 - WHO - ACTION - DETAIL
 */
class AuditFormatter
{
    public function __invoke($logger)
    {
        foreach ($logger->getHandlers() as $handler) {
            $handler->setFormatter(new LineFormatter("%datetime% - %message%\n", 'Y-m-d H:i:s', true, true));
        }
    }
}
