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
 * A cancelled order gives its points back.
 */
class ReturnOnCancel implements ObserverInterface
{
    public function __construct(private readonly OrderPoints $orderPoints)
    {
    }

    public function execute(Observer $observer): void
    {
        $order = $observer->getEvent()->getOrder();
        $this->orderPoints->giveBack($order, 1.0, 'cancel:' . $order->getIncrementId(), (string) __('Order cancelled'));
    }
}
