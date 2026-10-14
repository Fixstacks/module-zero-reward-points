<?php
/**
 * Copyright © 2026 Roman Pasichnyk
 * SPDX-License-Identifier: OSL-3.0 OR AFL-3.0
 */

declare(strict_types=1);

namespace Zero\RewardPoints\Model;

use Magento\Framework\App\Config\ScopeConfigInterface;
use Magento\Store\Model\ScopeInterface;

/**
 * Stores > Configuration > Zero > Reward Points, per website.
 */
class Config
{
    public function __construct(private readonly ScopeConfigInterface $scopeConfig)
    {
    }

    public function isEnabled(?int $websiteId = null): bool
    {
        return (bool) $this->value('enabled', $websiteId);
    }

    public function earnRate(?int $websiteId = null): float
    {
        return max(0.0, (float) $this->value('earn_rate', $websiteId));
    }

    /** One point in the base currency. */
    public function pointValue(?int $websiteId = null): float
    {
        $value = (float) $this->value('point_value', $websiteId);

        return $value > 0 ? $value : 0.01;
    }

    public function signupPoints(?int $websiteId = null): int
    {
        return max(0, (int) $this->value('signup_points', $websiteId));
    }

    public function reviewPoints(?int $websiteId = null): int
    {
        return max(0, (int) $this->value('review_points', $websiteId));
    }

    public function minPoints(?int $websiteId = null): int
    {
        return max(0, (int) $this->value('min_points', $websiteId));
    }

    public function maxPercent(?int $websiteId = null): float
    {
        return min(100.0, max(0.0, (float) $this->value('max_percent', $websiteId)));
    }

    public function expireDays(?int $websiteId = null): int
    {
        return max(0, (int) $this->value('expire_days', $websiteId));
    }

    public function showOnProduct(?int $websiteId = null): bool
    {
        return (bool) $this->value('show_on_product', $websiteId);
    }

    /** Points an amount in the base currency earns. */
    public function pointsFor(float $baseAmount, ?int $websiteId = null): int
    {
        return (int) floor(max(0.0, $baseAmount) * $this->earnRate($websiteId) + 1e-6);
    }

    private function value(string $field, ?int $websiteId): mixed
    {
        return $this->scopeConfig->getValue('zero_reward/general/' . $field, ScopeInterface::SCOPE_WEBSITE, $websiteId);
    }
}
