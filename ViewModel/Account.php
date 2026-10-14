<?php
/**
 * Copyright © 2026 Roman Pasichnyk
 * SPDX-License-Identifier: OSL-3.0 OR AFL-3.0
 */

declare(strict_types=1);

namespace Zero\RewardPoints\ViewModel;

use Magento\Customer\Model\Session as CustomerSession;
use Magento\Framework\Pricing\PriceCurrencyInterface;
use Magento\Framework\Stdlib\DateTime\TimezoneInterface;
use Magento\Framework\View\Element\Block\ArgumentInterface;
use Magento\Store\Model\StoreManagerInterface;
use Zero\RewardPoints\Model\Actions;
use Zero\RewardPoints\Model\Config;
use Zero\RewardPoints\Model\Points;

/**
 * My Account > Reward Points: the balance and its worth, what expires soon,
 * how to earn, every change.
 */
class Account implements ArgumentInterface
{
    public function __construct(
        private readonly CustomerSession $customerSession,
        private readonly StoreManagerInterface $storeManager,
        private readonly Points $points,
        private readonly Config $config,
        private readonly PriceCurrencyInterface $priceCurrency,
        private readonly TimezoneInterface $timezone
    ) {
    }

    public function balance(): int
    {
        return $this->points->balance($this->customerId(), $this->websiteId());
    }

    public function worth(int $points): string
    {
        return $this->priceCurrency->convertAndFormat($points * $this->config->pointValue($this->websiteId()), false);
    }

    /**
     * @return array{points: int, first: ?string}
     */
    public function expiring(): array
    {
        $expiring = $this->points->expiring($this->customerId(), $this->websiteId());
        if ($expiring['first']) {
            $expiring['first'] = $this->date($expiring['first']);
        }

        return $expiring;
    }

    /**
     * @return string[]
     */
    public function howToEarn(): array
    {
        $websiteId = $this->websiteId();
        $ways = [];
        $rate = $this->config->earnRate($websiteId);
        if ($rate > 0) {
            $ways[] = $rate == 1
                ? (string) __('1 point for every %1 you spend on products', $this->priceCurrency->convertAndFormat(1, false))
                : (string) __('%1 points for every %2 you spend on products', $this->number($rate), $this->priceCurrency->convertAndFormat(1, false));
        }
        if ($this->config->reviewPoints($websiteId) > 0) {
            $ways[] = (string) __('%1 points for a review, once it is published', $this->config->reviewPoints($websiteId));
        }
        $ways[] = (string) __('100 points take %1 off; use them at checkout', $this->worth(100));

        return $ways;
    }

    /**
     * @return array<int, array{date: string, label: string, points: int, comment: string}>
     */
    public function history(): array
    {
        return array_map(fn (array $row) => [
            'date' => $this->date((string) $row['created_at']),
            'label' => Actions::label($row),
            'points' => (int) $row['points'],
            'comment' => (string) ($row['comment'] ?? ''),
        ], $this->points->history($this->customerId(), $this->websiteId()));
    }

    private function date(string $utc): string
    {
        return $this->timezone->formatDate($this->timezone->date(new \DateTime($utc, new \DateTimeZone('UTC'))), \IntlDateFormatter::MEDIUM);
    }

    private function number(float $value): string
    {
        return rtrim(rtrim(number_format($value, 2, '.', ''), '0'), '.');
    }

    private function customerId(): int
    {
        return (int) $this->customerSession->getCustomerId();
    }

    private function websiteId(): int
    {
        return (int) $this->storeManager->getStore()->getWebsiteId();
    }
}
