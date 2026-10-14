<?php
/**
 * Copyright © 2026 Roman Pasichnyk
 * SPDX-License-Identifier: OSL-3.0 OR AFL-3.0
 */

declare(strict_types=1);

namespace Zero\RewardPoints\Observer;

use Magento\Framework\Event\Observer;
use Magento\Framework\Event\ObserverInterface;
use Zero\RewardPoints\Model\Config;
use Zero\RewardPoints\Model\Points;

class SignupBonus implements ObserverInterface
{
    public function __construct(
        private readonly Points $points,
        private readonly Config $config
    ) {
    }

    public function execute(Observer $observer): void
    {
        $customer = $observer->getEvent()->getCustomer();
        $customerId = (int) $customer?->getId();
        $websiteId = (int) $customer?->getWebsiteId();
        if (!$customerId || !$this->config->isEnabled($websiteId) || $this->points->has(Points::EARN_SIGNUP, 'customer:' . $customerId)) {
            return;
        }
        $this->points->add($customerId, $websiteId, $this->config->signupPoints($websiteId), Points::EARN_SIGNUP, null, 'customer:' . $customerId);
    }
}
