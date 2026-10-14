<?php
/**
 * Copyright © 2026 Roman Pasichnyk
 * SPDX-License-Identifier: OSL-3.0 OR AFL-3.0
 */

declare(strict_types=1);

namespace Zero\RewardPoints\Model;

use Magento\Sales\Model\Order;

/**
 * An order's points after the fact: used ones given back, earned ones taken
 * back (all, or a refund's share), recorded on the order.
 */
class OrderPoints
{
    public function __construct(
        private readonly Points $points,
        private readonly Config $config
    ) {
    }

    public function earn(Order $order, float $baseAmount, ?string $reference = null): void
    {
        $customerId = (int) $order->getCustomerId();
        $websiteId = (int) $order->getStore()->getWebsiteId();
        if (!$customerId || $order->getCustomerIsGuest() || !$this->config->isEnabled($websiteId)) {
            return;
        }
        $earned = $this->config->pointsFor($baseAmount, $websiteId);
        if ($earned <= 0) {
            return;
        }
        $this->points->add($customerId, $websiteId, $earned, Points::EARN_ORDER, (string) $order->getIncrementId(), $reference);
        $order->setData('zero_reward_points_earned', (int) $order->getData('zero_reward_points_earned') + $earned);
    }

    /**
     * @param float $share Of the order (0..1); 1 gives back all that is left
     */
    public function giveBack(Order $order, float $share, string $reference, string $comment): void
    {
        $customerId = (int) $order->getCustomerId();
        if (!$customerId || $this->points->has(Points::RETURN_ORDER, $reference)) {
            return;
        }
        $websiteId = (int) $order->getStore()->getWebsiteId();
        $used = (int) $order->getData('zero_reward_points_used');
        $returned = (int) $order->getData('zero_reward_points_returned');
        $back = min($used - $returned, $share >= 1 ? $used : (int) round($used * $share));
        if ($back > 0) {
            $this->points->add($customerId, $websiteId, $back, Points::RETURN_ORDER, (string) $order->getIncrementId(), $reference, $comment);
            $order->setData('zero_reward_points_returned', $returned + $back);
        }
        $earned = (int) $order->getData('zero_reward_points_earned');
        $revoked = (int) $order->getData('zero_reward_points_revoked');
        $take = min($earned - $revoked, $share >= 1 ? $earned : (int) round($earned * $share));
        if ($take > 0) {
            $this->points->take($customerId, $websiteId, $take, Points::REVOKE_ORDER, (string) $order->getIncrementId(), $reference, $comment);
            $order->setData('zero_reward_points_revoked', $revoked + $take);
        }
    }
}
