<?php
/**
 * Copyright © 2026 Roman Pasichnyk
 * SPDX-License-Identifier: OSL-3.0 OR AFL-3.0
 */

declare(strict_types=1);

namespace Zero\RewardPoints\ViewModel;

use Magento\Checkout\Model\Session as CheckoutSession;
use Magento\Framework\Pricing\PriceCurrencyInterface;
use Magento\Framework\View\Element\Block\ArgumentInterface;
use Magento\Quote\Model\Quote;
use Zero\RewardPoints\Model\Config;
use Zero\RewardPoints\Model\Points;
use Zero\RewardPoints\Model\Redemption;

/**
 * The checkout's "Use your points" box: what a signed-in customer has, what
 * this order can take, what it uses.
 */
class Checkout implements ArgumentInterface
{
    private ?int $balance = null;

    public function __construct(
        private readonly CheckoutSession $checkoutSession,
        private readonly Points $points,
        private readonly Config $config,
        private readonly PriceCurrencyInterface $priceCurrency
    ) {
    }

    public function isAvailable(): bool
    {
        $quote = $this->quote();

        return $quote->getCustomerId()
            && $this->config->isEnabled($this->websiteId())
            && ($this->used() > 0 || ($this->balance() > 0 && $this->balance() >= $this->config->minPoints($this->websiteId())));
    }

    public function balance(): int
    {
        return $this->balance ??= $this->points->balance((int) $this->quote()->getCustomerId(), $this->websiteId());
    }

    public function used(): int
    {
        return (int) $this->quote()->getData('zero_reward_points_used');
    }

    public function usedAmount(): string
    {
        return $this->priceCurrency->format((float) $this->quote()->getData('zero_reward_amount'), false);
    }

    /**
     * What "Use my points" would take: points and their value here.
     *
     * @return array{points: int, amount: string}
     */
    public function usable(): array
    {
        $eligible = 0.0;
        foreach ($this->quote()->getAllItems() as $item) {
            if ($item->getParentItem() || ($item->getHasChildren() && $item->isChildrenCalculated())) {
                continue;
            }
            $eligible += (float) $item->getBaseRowTotal() - (float) $item->getBaseDiscountAmount();
        }
        // The points already used are in the lines' discounts: count them back in.
        $eligible += (float) $this->quote()->getData('base_zero_reward_amount');
        $use = Redemption::calculate(
            $this->balance(),
            $this->balance(),
            $this->config->minPoints($this->websiteId()),
            $this->config->pointValue($this->websiteId()),
            $eligible,
            $this->config->maxPercent($this->websiteId())
        );

        return [
            'points' => $use['points'],
            'amount' => $this->priceCurrency->convertAndFormat($use['base'], false, PriceCurrencyInterface::DEFAULT_PRECISION, $this->quote()->getStore()),
        ];
    }

    public function minPoints(): int
    {
        return $this->config->minPoints($this->websiteId());
    }

    public function maxPercent(): float
    {
        return $this->config->maxPercent($this->websiteId());
    }

    private function quote(): Quote
    {
        return $this->checkoutSession->getQuote();
    }

    private function websiteId(): int
    {
        return (int) $this->quote()->getStore()->getWebsiteId();
    }
}
