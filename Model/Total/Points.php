<?php
/**
 * Copyright © 2026 Roman Pasichnyk
 * SPDX-License-Identifier: OSL-3.0 OR AFL-3.0
 */

declare(strict_types=1);

namespace Zero\RewardPoints\Model\Total;

use Magento\Framework\Pricing\PriceCurrencyInterface;
use Magento\Quote\Api\Data\ShippingAssignmentInterface;
use Magento\Quote\Model\Quote;
use Magento\Quote\Model\Quote\Address\Total;
use Magento\Quote\Model\Quote\Address\Total\AbstractTotal;
use Magento\Quote\Model\Quote\Item\AbstractItem;
use Zero\RewardPoints\Model\Config;
use Zero\RewardPoints\Model\Points as Ledger;
use Zero\RewardPoints\Model\Redemption;

/**
 * Points the customer chose to use (quote.zero_reward_points) become part of
 * the discount: spread over the products by their price after other
 * discounts (each line's discount grows), so tax, invoices, credit memos and
 * every total that shows "Discount" count them with nothing else to know.
 * The discount's description gets "Reward points". What was used lands on
 * the quote (zero_reward_points_used, zero_reward_amount), then the order.
 */
class Points extends AbstractTotal
{
    public function __construct(
        private readonly Ledger $ledger,
        private readonly Config $config,
        private readonly PriceCurrencyInterface $priceCurrency
    ) {
        $this->setCode('zero_reward');
    }

    public function collect(Quote $quote, ShippingAssignmentInterface $shippingAssignment, Total $total)
    {
        parent::collect($quote, $shippingAssignment, $total);
        $items = $shippingAssignment->getItems();
        if (!$items) {
            return $this;
        }
        $quote->setData('zero_reward_points_used', 0)->setData('zero_reward_amount', 0)->setData('base_zero_reward_amount', 0);
        $requested = (int) $quote->getData('zero_reward_points');
        $customerId = (int) $quote->getCustomerId();
        $websiteId = (int) $quote->getStore()->getWebsiteId();
        if ($requested <= 0 || !$customerId || !$this->config->isEnabled($websiteId)) {
            return $this;
        }

        $lines = $this->lines($items);
        $weights = array_map(static fn (AbstractItem $line) => max(0.0, (float) $line->getBaseRowTotal() - (float) $line->getBaseDiscountAmount()), $lines);
        $use = Redemption::calculate(
            $requested,
            $this->ledger->balance($customerId, $websiteId),
            $this->config->minPoints($websiteId),
            $this->config->pointValue($websiteId),
            array_sum($weights),
            $this->config->maxPercent($websiteId)
        );
        if (!$use['points']) {
            return $this;
        }
        $base = $use['base'];
        $amount = $this->priceCurrency->convertAndRound($base, $quote->getStore());
        $baseShares = Redemption::split($base, $weights);
        $shares = Redemption::split($amount, $weights);
        foreach ($lines as $key => $line) {
            $line->setBaseDiscountAmount((float) $line->getBaseDiscountAmount() + $baseShares[$key]);
            $line->setDiscountAmount((float) $line->getDiscountAmount() + $shares[$key]);
        }

        $total->addTotalAmount('discount', -$amount);
        $total->addBaseTotalAmount('discount', -$base);
        $total->setSubtotalWithDiscount((float) $total->getSubtotalWithDiscount() - $amount);
        $total->setBaseSubtotalWithDiscount((float) $total->getBaseSubtotalWithDiscount() - $base);
        $label = (string) __('Reward points');
        $description = (string) $total->getDiscountDescription();
        $total->setDiscountDescription($description !== '' ? $description . ', ' . $label : $label);

        $quote->setData('zero_reward_points_used', $use['points'])
            ->setData('zero_reward_amount', $amount)
            ->setData('base_zero_reward_amount', $base);

        return $this;
    }

    /**
     * Shown within "Discount".
     */
    public function fetch(Quote $quote, Total $total)
    {
        return null;
    }

    /**
     * The lines that carry prices: an item, or its children when they are
     * priced (dynamic bundles).
     *
     * @param AbstractItem[] $items
     * @return AbstractItem[]
     */
    private function lines(array $items): array
    {
        $lines = [];
        foreach ($items as $item) {
            if ($item->getParentItem() || $item->getNoDiscount()) {
                continue;
            }
            if ($item->getHasChildren() && $item->isChildrenCalculated()) {
                foreach ($item->getChildren() as $child) {
                    $lines[] = $child;
                }
            } else {
                $lines[] = $item;
            }
        }

        return $lines;
    }
}
