<?php
/**
 * Copyright © 2026 Roman Pasichnyk
 * SPDX-License-Identifier: OSL-3.0 OR AFL-3.0
 */

declare(strict_types=1);

namespace Zero\RewardPoints\Observer;

use Magento\Framework\Event\Observer;
use Magento\Framework\Event\ObserverInterface;
use Magento\Review\Model\Review;
use Magento\Store\Model\StoreManagerInterface;
use Zero\RewardPoints\Model\Config;
use Zero\RewardPoints\Model\Points;

/**
 * A signed-in customer's review, once approved: points, once per product.
 */
class ReviewBonus implements ObserverInterface
{
    public function __construct(
        private readonly Points $points,
        private readonly Config $config,
        private readonly StoreManagerInterface $storeManager
    ) {
    }

    public function execute(Observer $observer): void
    {
        $review = $observer->getEvent()->getObject();
        if (!$review instanceof Review || (int) $review->getStatusId() !== Review::STATUS_APPROVED) {
            return;
        }
        $customerId = (int) $review->getCustomerId();
        $productId = (int) $review->getEntityPkValue();
        if (!$customerId || !$productId) {
            return;
        }
        $websiteId = (int) $this->storeManager->getStore((int) $review->getStoreId())->getWebsiteId();
        $reference = 'product:' . $productId;
        if (!$this->config->isEnabled($websiteId) || $this->points->has(Points::EARN_REVIEW, $reference, $customerId)) {
            return;
        }
        $this->points->add($customerId, $websiteId, $this->config->reviewPoints($websiteId), Points::EARN_REVIEW, null, $reference);
    }
}
