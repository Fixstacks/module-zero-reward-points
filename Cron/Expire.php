<?php
/**
 * Copyright © 2026 Roman Pasichnyk
 * SPDX-License-Identifier: OSL-3.0 OR AFL-3.0
 */

declare(strict_types=1);

namespace Zero\RewardPoints\Cron;

use Psr\Log\LoggerInterface;
use Zero\RewardPoints\Model\Points;

class Expire
{
    public function __construct(
        private readonly Points $points,
        private readonly LoggerInterface $logger
    ) {
    }

    public function execute(): void
    {
        $expired = $this->points->expire();
        if ($expired) {
            $this->logger->info(sprintf('Zero_RewardPoints: %d points expired', $expired));
        }
    }
}
