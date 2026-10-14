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
 * Placing an order: the points it uses move to the order and leave the
 * balance; too few left (spent elsewhere meanwhile) stops the order.
 */
class SpendOnOrder implements ObserverInterface
{
    public function __construct(private readonly Points $points)
    {
    }

    public function execute(Observer $observer): void
    {
        $quote = $observer->getEvent()->getQuote();
        $order = $observer->getEvent()->getOrder();
        $used = (int) $quote->getData('zero_reward_points_used');
        if ($used <= 0 || !$quote->getCustomerId()) {
            return;
        }
        $order->setData('zero_reward_points_used', $used)
            ->setData('zero_reward_amount', (float) $quote->getData('zero_reward_amount'))
            ->setData('base_zero_reward_amount', (float) $quote->getData('base_zero_reward_amount'));
        $this->points->spend(
            (int) $quote->getCustomerId(),
            (int) $quote->getStore()->getWebsiteId(),
            $used,
            Points::SPEND_ORDER,
            (string) $order->getIncrementId(),
            'order:' . $order->getIncrementId()
        );
    }
}
