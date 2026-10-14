<?php
/**
 * Copyright © 2026 Roman Pasichnyk
 * SPDX-License-Identifier: OSL-3.0 OR AFL-3.0
 */

declare(strict_types=1);

namespace Zero\RewardPoints\Observer;

use Magento\Framework\Event\Observer;
use Magento\Framework\Event\ObserverInterface;
use Zero\RewardPoints\Model\Points;

/**
 * The order failed to place after its points were taken: they come back.
 */
class ReturnOnFailure implements ObserverInterface
{
    public function __construct(private readonly Points $points)
    {
    }

    public function execute(Observer $observer): void
    {
        $quote = $observer->getEvent()->getQuote();
        $order = $observer->getEvent()->getOrder();
        $used = (int) $order?->getData('zero_reward_points_used');
        $incrementId = (string) $order?->getIncrementId();
        if ($used <= 0 || $incrementId === '' || !$quote->getCustomerId()
            || !$this->points->has(Points::SPEND_ORDER, 'order:' . $incrementId)
            || $this->points->has(Points::RETURN_ORDER, 'failed:' . $incrementId)
        ) {
            return;
        }
        $this->points->add(
            (int) $quote->getCustomerId(),
            (int) $quote->getStore()->getWebsiteId(),
            $used,
            Points::RETURN_ORDER,
            $incrementId,
            'failed:' . $incrementId,
            (string) __('The order was not placed.')
        );
    }
}
