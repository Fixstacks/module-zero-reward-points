<?php
/**
 * Copyright © 2026 Roman Pasichnyk
 * SPDX-License-Identifier: OSL-3.0 OR AFL-3.0
 */

declare(strict_types=1);

namespace Zero\RewardPoints\Observer;

use Magento\Framework\Event\Observer;
use Magento\Framework\Event\ObserverInterface;
use Zero\RewardPoints\Model\OrderPoints;

/**
 * A credit memo gives back its share of the points used (the refunded
 * products' share of the order's), and takes back that share of the points
 * earned; the last one, all that's left.
 */
class ReturnOnRefund implements ObserverInterface
{
    public function __construct(private readonly OrderPoints $orderPoints)
    {
    }

    public function execute(Observer $observer): void
    {
        /** @var \Magento\Sales\Model\Order\Creditmemo $creditmemo */
        $creditmemo = $observer->getEvent()->getCreditmemo();
        $order = $creditmemo->getOrder();
        $orderSubtotal = (float) $order->getBaseSubtotal();
        $refunded = (float) $order->getBaseSubtotalRefunded();
        $share = $orderSubtotal > 0 ? (float) $creditmemo->getBaseSubtotal() / $orderSubtotal : 1.0;
        if ($orderSubtotal > 0 && $refunded >= $orderSubtotal - 0.0001) {
            $share = 1.0;
        }
        $this->orderPoints->giveBack(
            $order,
            $share,
            'creditmemo:' . ($creditmemo->getIncrementId() ?: $creditmemo->getId()),
            (string) __('Refund')
        );
    }
}
